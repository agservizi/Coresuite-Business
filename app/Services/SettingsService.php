<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

class SettingsService
{
    private const MOVEMENT_DESCRIPTIONS_KEY = 'entrate_uscite_descrizioni';
    private PDO $pdo;
    private string $rootPath;
    private string $backupPath;
    private string $brandingPath;
    private ?string $backupPassphrase;
    private string $backupCipher;

    public function __construct(PDO $pdo, string $rootPath)
    {
        $this->pdo = $pdo;
        $this->rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);
        $this->backupPath = $this->rootPath . DIRECTORY_SEPARATOR . 'backups';
        $this->brandingPath = $this->rootPath . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'branding';
    $this->backupPassphrase = function_exists('env') ? (env('BACKUP_ENCRYPTION_KEY') ?: null) : null;
    $this->backupCipher = function_exists('env') ? (env('BACKUP_ENCRYPTION_CIPHER', 'AES-256-CBC') ?: 'AES-256-CBC') : 'AES-256-CBC';
    }

    public function fetchCompanySettings(array $defaults): array
    {
        try {
            $stmt = $this->pdo->query('SELECT chiave, valore FROM configurazioni');
            $config = $stmt ? $stmt->fetchAll(PDO::FETCH_KEY_PAIR) : [];
        } catch (PDOException $e) {
            error_log('Settings fetch failed: ' . $e->getMessage());
            $config = [];
        }

        foreach ($defaults as $key => $default) {
            if (!array_key_exists($key, $config)) {
                $config[$key] = $default;
            }
        }

        return $config;
    }

    public function recentBackups(int $limit = 5): array
    {
        $recent = [];
        if (!is_dir($this->backupPath)) {
            return $recent;
        }

        $files = glob($this->backupPath . DIRECTORY_SEPARATOR . '*.sql');
        if (!$files) {
            return $recent;
        }

        rsort($files);
        foreach (array_slice($files, 0, $limit) as $filePath) {
            $size = @filesize($filePath);
            $mtime = @filemtime($filePath);
            $recent[] = [
                'name' => basename($filePath),
                'size' => $size !== false ? $this->formatBytes((int) $size) : '—',
                'mtime' => $mtime ?: null,
            ];
        }

        return $recent;
    }

    public function getMovementDescriptions(): array
    {
        $defaults = [
            'entrate' => [],
            'uscite' => [],
        ];

        try {
            $stmt = $this->pdo->prepare('SELECT valore FROM configurazioni WHERE chiave = :chiave LIMIT 1');
            $stmt->execute([':chiave' => self::MOVEMENT_DESCRIPTIONS_KEY]);
            $value = $stmt->fetchColumn();
            if ($value) {
                $decoded = json_decode((string) $value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $defaults['entrate'] = $this->sanitizeDescriptions($decoded['entrate'] ?? []);
                    $defaults['uscite'] = $this->sanitizeDescriptions($decoded['uscite'] ?? []);
                }
            }
        } catch (Throwable $e) {
            error_log('Movement descriptions fetch failed: ' . $e->getMessage());
        }

        return $defaults;
    }

    public function saveMovementDescriptions(array $entrate, array $uscite, int $userId): array
    {
        $entrate = $this->sanitizeDescriptions($entrate);
        $uscite = $this->sanitizeDescriptions($uscite);

        $invalid = array_merge(
            $this->validateDescriptions($entrate),
            $this->validateDescriptions($uscite)
        );
        $invalid = array_values(array_filter($invalid));

        if ($invalid) {
            return ['success' => false, 'errors' => $invalid];
        }

        $payload = json_encode([
            'entrate' => array_values($entrate),
            'uscite' => array_values($uscite),
        ], JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            return ['success' => false, 'errors' => ['Impossibile serializzare le descrizioni dei movimenti.']];
        }

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO configurazioni (chiave, valore) VALUES (:chiave, :valore)
                 ON DUPLICATE KEY UPDATE valore = VALUES(valore)'
            );
            $stmt->execute([
                ':chiave' => self::MOVEMENT_DESCRIPTIONS_KEY,
                ':valore' => $payload,
            ]);

            $this->logActivity($userId, 'Aggiornamento descrizioni movimenti', [
                'entrate' => $entrate,
                'uscite' => $uscite,
            ]);

            return ['success' => true, 'errors' => []];
        } catch (Throwable $e) {
            error_log('Movement descriptions save failed: ' . $e->getMessage());
            return ['success' => false, 'errors' => ['Impossibile salvare le descrizioni dei movimenti.']];
        }
    }

    public function updateCompanySettings(
        array $payload,
        array $vatCountries,
        array $currentConfig,
        ?array $logoFile,
        bool $removeLogo,
        int $userId
    ): array {
        $errors = $this->validateCompanyPayload($payload, $vatCountries);
        $logoPath = $currentConfig['company_logo'] ?? '';

        if ($logoFile && $logoFile['error'] !== UPLOAD_ERR_NO_FILE) {
            $logoResult = $this->processLogoUpload($logoFile, $logoPath);
            $errors = array_merge($errors, $logoResult['errors']);
            $logoPath = $logoResult['path'];
        }

        if ($removeLogo && $logoPath !== '') {
            $this->deleteExistingLogo($logoPath);
            $logoPath = '';
        }

        if ($errors) {
            return [
                'success' => false,
                'errors' => $errors,
                'config' => array_merge($currentConfig, $payload, ['company_logo' => $logoPath]),
            ];
        }

        $payload['company_logo'] = $logoPath;

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare(
                'INSERT INTO configurazioni (chiave, valore) VALUES (:chiave, :valore_inserimento)
                 ON DUPLICATE KEY UPDATE valore = :valore_aggiornamento'
            );

            foreach ($payload as $key => $value) {
                $stmt->execute([
                    'chiave' => $key,
                    'valore_inserimento' => $value,
                    'valore_aggiornamento' => $value,
                ]);
                $currentConfig[$key] = $value;
            }

            $this->pdo->commit();
            $this->logActivity($userId, 'Aggiornamento dati aziendali', $payload);

            return [
                'success' => true,
                'errors' => [],
                'config' => $currentConfig,
            ];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            error_log('Company settings update failed: ' . $e->getMessage());
            return [
                'success' => false,
                'errors' => ['Errore durante il salvataggio dei dati aziendali. ' . $e->getMessage()],
                'config' => array_merge($currentConfig, $payload),
            ];
        }
    }

    public function generateBackup(int $userId): array
    {
        if (!is_dir($this->backupPath) && !mkdir($concurrentDirectory = $this->backupPath, 0775, true) && !is_dir($concurrentDirectory)) {
            return ['success' => false, 'error' => 'Impossibile creare la cartella dei backup.'];
        }

        if (!is_writable($this->backupPath)) {
            return ['success' => false, 'error' => 'La cartella dei backup non è scrivibile.'];
        }

        $timestamp = date('Ymd_His');
        $backupFile = $this->backupPath . DIRECTORY_SEPARATOR . 'backup_' . $timestamp . '.sql';

        try {
            set_time_limit(0);
            $charsetResult = $this->pdo->query('SELECT @@character_set_database');
            $charset = $charsetResult ? $charsetResult->fetchColumn() : null;
            if ($charset && preg_match('/^[a-zA-Z0-9_]+$/', (string) $charset)) {
                $this->pdo->exec('SET NAMES ' . $charset);
            }

            $tablesStmt = $this->pdo->query('SHOW FULL TABLES');
            $tables = [];
            while ($row = $tablesStmt->fetch(PDO::FETCH_NUM)) {
                if (($row[1] ?? '') === 'BASE TABLE') {
                    $tables[] = $row[0];
                }
            }

            $dump = "-- Backup Coresuite Business\n";
            $dump .= '-- Generato il ' . date('Y-m-d H:i:s') . "\n\n";
            foreach ($tables as $table) {
                $dump .= '-- Struttura per tabella `' . $table . "`\n";
                $dump .= 'DROP TABLE IF EXISTS `' . $table . "`;\n";
                $createStmt = $this->pdo->query('SHOW CREATE TABLE `' . $table . '`');
                $create = $createStmt->fetch(PDO::FETCH_ASSOC);
                $dump .= ($create['Create Table'] ?? '') . ";\n\n";

                $rowsStmt = $this->pdo->query('SELECT * FROM `' . $table . '`');
                while ($row = $rowsStmt->fetch(PDO::FETCH_ASSOC)) {
                    $columns = array_map(static fn($col) => '`' . str_replace('`', '``', $col) . '`', array_keys($row));
                    $values = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = $this->pdo->quote((string) $value);
                        }
                    }
                    $dump .= 'INSERT INTO `' . $table . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n";
                }
                $dump .= "\n";
            }

            if (file_put_contents($backupFile, $dump) === false) {
                throw new RuntimeException('Scrittura file di backup fallita.');
            }

            if ($this->backupPassphrase) {
                $backupFile = $this->encryptBackup($backupFile);
            }

            $this->logActivity($userId, 'Backup manuale', ['file' => basename($backupFile)]);

            return ['success' => true, 'file' => basename($backupFile)];
        } catch (Throwable $e) {
            error_log('Backup generation failed: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Errore durante la generazione del backup.'];
        }
    }

    private function validateCompanyPayload(array &$payload, array $vatCountries): array
    {
        $errors = [];

        $payload['ragione_sociale'] = trim($payload['ragione_sociale'] ?? '');
        $payload['indirizzo'] = trim($payload['indirizzo'] ?? '');
        $payload['cap'] = trim($payload['cap'] ?? '');
        $payload['citta'] = trim($payload['citta'] ?? '');
        $payload['provincia'] = strtoupper(trim($payload['provincia'] ?? ''));
        $payload['telefono'] = trim($payload['telefono'] ?? '');
        $payload['email'] = trim($payload['email'] ?? '');
        $payload['pec'] = trim($payload['pec'] ?? '');
        $payload['sdi'] = strtoupper(trim($payload['sdi'] ?? ''));
        $payload['vat_country'] = strtoupper(trim($payload['vat_country'] ?? 'IT'));
        $payload['piva'] = strtoupper(preg_replace('/\s+/', '', $payload['piva'] ?? ''));
        $payload['iban'] = strtoupper(str_replace(' ', '', $payload['iban'] ?? ''));
        $payload['note'] = trim($payload['note'] ?? '');

        if ($payload['ragione_sociale'] === '') {
            $errors[] = 'La ragione sociale è obbligatoria.';
        }
        if ($payload['email'] !== '' && !filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Inserisci un indirizzo email valido.';
        }
        if ($payload['pec'] !== '' && !filter_var($payload['pec'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Inserisci una PEC valida.';
        }
        if ($payload['telefono'] !== '' && !preg_match('/^[0-9+()\s-]{6,}$/', $payload['telefono'])) {
            $errors[] = 'Inserisci un numero di telefono valido.';
        }
        if ($payload['cap'] !== '' && !preg_match('/^[0-9]{5}$/', $payload['cap'])) {
            $errors[] = 'Inserisci un CAP a 5 cifre.';
        }
        if ($payload['provincia'] !== '' && !preg_match('/^[A-Z]{2}$/', $payload['provincia'])) {
            $errors[] = 'Inserisci la sigla della provincia (es. MI).';
        }
        if ($payload['sdi'] !== '' && !preg_match('/^[A-Z0-9]{7}$/', $payload['sdi'])) {
            $errors[] = 'Il codice SDI deve contenere 7 caratteri alfanumerici.';
        }
        if (!array_key_exists($payload['vat_country'], $vatCountries)) {
            $errors[] = 'Seleziona un paese IVA valido.';
        }
        if ($payload['piva'] !== '') {
            if (!preg_match('/^[A-Z0-9]{8,15}$/', $payload['piva'])) {
                $errors[] = 'La partita IVA deve contenere tra 8 e 15 caratteri alfanumerici.';
            } elseif ($payload['vat_country'] === 'IT' && !preg_match('/^[0-9]{11}$/', $payload['piva'])) {
                $errors[] = "Per l'Italia la partita IVA deve contenere 11 cifre.";
            }
        }
        if ($payload['iban'] !== '' && !preg_match('/^[A-Z0-9]{15,34}$/', $payload['iban'])) {
            $errors[] = 'Inserisci un IBAN valido (15-34 caratteri alfanumerici).';
        }
        if (mb_strlen($payload['note']) > 2000) {
            $errors[] = 'Le note non possono superare i 2000 caratteri.';
        }

        return $errors;
    }

    private function processLogoUpload(array $file, string $currentLogoPath): array
    {
        $errors = [];
        $path = $currentLogoPath;

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Errore durante il caricamento del logo.';
            return ['errors' => $errors, 'path' => $path];
        }

        if ($file['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Il logo non può superare i 2MB.';
        }

        $info = @getimagesize($file['tmp_name']);
        if ($info === false) {
            $errors[] = 'Carica un file immagine valido per il logo.';
        } else {
            $allowedFormats = ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'];
            if (!in_array($info['mime'], $allowedFormats, true)) {
                $errors[] = 'Formato logo non supportato. Usa PNG, JPG, WEBP o SVG.';
            }
        }

        if ($errors) {
            return ['errors' => $errors, 'path' => $path];
        }

        if (!is_dir($this->brandingPath) && !mkdir($concurrentDirectory = $this->brandingPath, 0775, true) && !is_dir($concurrentDirectory)) {
            $errors[] = 'Impossibile creare la cartella per il logo aziendale.';
            return ['errors' => $errors, 'path' => $path];
        }

        if (!is_writable($this->brandingPath)) {
            $errors[] = 'La cartella per il logo aziendale non è scrivibile.';
            return ['errors' => $errors, 'path' => $path];
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $safeName = function_exists('sanitize_filename')
            ? sanitize_filename('logo_' . date('Ymd_His') . '.' . $extension)
            : preg_replace('/[^A-Za-z0-9._-]/', '_', 'logo_' . date('Ymd_His') . '.' . $extension);

        $destination = $this->brandingPath . DIRECTORY_SEPARATOR . $safeName;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $errors[] = 'Impossibile salvare il file del logo.';
            return ['errors' => $errors, 'path' => $path];
        }

        if ($currentLogoPath) {
            $this->deleteExistingLogo($currentLogoPath);
        }

        $relativePath = 'assets/uploads/branding/' . $safeName;

        return [
            'errors' => [],
            'path' => $relativePath,
        ];
    }

    private function encryptBackup(string $filePath): string
    {
        if (!$this->backupPassphrase) {
            return $filePath;
        }

        $contents = file_get_contents($filePath);
        if ($contents === false) {
            throw new RuntimeException('Lettura file di backup fallita prima della cifratura.');
        }

        $cipher = $this->backupCipher;
        $ivLength = openssl_cipher_iv_length($cipher);
        if (!is_int($ivLength) || $ivLength <= 0) {
            throw new RuntimeException('Cipher per la cifratura del backup non valido.');
        }

        $iv = random_bytes($ivLength);
        $ciphertext = openssl_encrypt($contents, $cipher, $this->backupPassphrase, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            throw new RuntimeException('Cifratura del backup non riuscita.');
        }

        $payload = base64_encode($iv . $ciphertext);
        $encryptedPath = $filePath . '.enc';

        if (file_put_contents($encryptedPath, $payload) === false) {
            throw new RuntimeException('Scrittura del backup cifrato non riuscita.');
        }

        @unlink($filePath);

        return $encryptedPath;
    }

    private function deleteExistingLogo(string $relativePath): void
    {
        $absoluteRoot = realpath($this->rootPath);
        $candidate = realpath($this->rootPath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath));
        if (!$absoluteRoot || !$candidate) {
            return;
        }

        if (strpos($candidate, $absoluteRoot) === 0 && is_file($candidate)) {
            @unlink($candidate);
        }
    }

    private function sanitizeDescriptions(array $values): array
    {
        $clean = [];
        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }
            $trimmed = trim($value);
            if ($trimmed === '') {
                continue;
            }
            $clean[$trimmed] = $trimmed;
        }

        return array_values($clean);
    }

    private function validateDescriptions(array $values): array
    {
        $errors = [];
        foreach ($values as $value) {
            if (mb_strlen($value) > 180) {
                $errors[] = 'Le descrizioni non possono superare i 180 caratteri.';
                break;
            }
        }

        return $errors;
    }

    private function logActivity(int $userId, string $action, array $payload): void
    {
        try {
            $filtered = array_filter(
                $payload,
                static fn($value, $key) => $key !== 'note' && $key !== 'company_logo',
                ARRAY_FILTER_USE_BOTH
            );

            $stmt = $this->pdo->prepare(
                'INSERT INTO log_attivita (user_id, modulo, azione, dettagli, created_at)
                 VALUES (:user_id, :modulo, :azione, :dettagli, NOW())'
            );

            $stmt->execute([
                ':user_id' => $userId,
                ':modulo' => 'Impostazioni',
                ':azione' => $action,
                ':dettagli' => json_encode($filtered, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (Throwable $e) {
            error_log('Activity log failed: ' . $e->getMessage());
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return number_format($value, $index === 0 ? 0 : 2) . ' ' . $units[$index];
    }
}
