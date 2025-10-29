<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/db_connect.php';
require_once __DIR__ . '/functions.php';

// Verifica autenticazione
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// Funzioni per la gestione dei servizi CIE

/**
 * Crea una nuova richiesta CIE
 */
function cie_create_service(PDO $pdo, array $data): int
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO servizi_cie (
                cliente_id, nome, cognome, data_nascita, luogo_nascita,
                codice_fiscale, indirizzo, citta, cap, telefono, email,
                documento_tipo, documento_numero, documento_rilascio,
                stato, note, created_at, updated_at
            ) VALUES (
                :cliente_id, :nome, :cognome, :data_nascita, :luogo_nascita,
                :codice_fiscale, :indirizzo, :citta, :cap, :telefono, :email,
                :documento_tipo, :documento_numero, :documento_rilascio,
                'nuova', :note, NOW(), NOW()
            )
        ");
        
        $stmt->execute([
            ':cliente_id' => $data['cliente_id'],
            ':nome' => $data['nome'],
            ':cognome' => $data['cognome'],
            ':data_nascita' => $data['data_nascita'],
            ':luogo_nascita' => $data['luogo_nascita'],
            ':codice_fiscale' => $data['codice_fiscale'],
            ':indirizzo' => $data['indirizzo'],
            ':citta' => $data['citta'],
            ':cap' => $data['cap'],
            ':telefono' => $data['telefono'],
            ':email' => $data['email'],
            ':documento_tipo' => $data['documento_tipo'],
            ':documento_numero' => $data['documento_numero'],
            ':documento_rilascio' => $data['documento_rilascio'],
            ':note' => $data['note'] ?? null,
        ]);
        
        return (int) $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log(CIE_MODULE_LOG . " - Errore creazione servizio CIE: " . $e->getMessage());
        throw new RuntimeException('Errore durante la creazione del servizio CIE');
    }
}

/**
 * Aggiorna lo stato di una richiesta CIE
 */
function cie_update_status(PDO $pdo, int $serviceId, string $newStatus, ?string $note = null): bool
{
    try {
        $validStatuses = array_keys(cie_status_map());
        if (!in_array($newStatus, $validStatuses, true)) {
            throw new InvalidArgumentException('Stato non valido: ' . $newStatus);
        }
        
        $stmt = $pdo->prepare("
            UPDATE servizi_cie 
            SET stato = :stato, updated_at = NOW()
            WHERE id = :id
        ");
        
        $result = $stmt->execute([
            ':stato' => $newStatus,
            ':id' => $serviceId,
        ]);
        
        // Aggiungi nota se presente
        if ($note && $result) {
            cie_add_note($pdo, $serviceId, $note);
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log(CIE_MODULE_LOG . " - Errore aggiornamento stato CIE: " . $e->getMessage());
        return false;
    }
}

/**
 * Aggiunge una nota a una richiesta CIE
 */
function cie_add_note(PDO $pdo, int $serviceId, string $note): bool
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO cie_note (servizio_id, nota, user_id, created_at)
            VALUES (:servizio_id, :nota, :user_id, NOW())
        ");
        
        return $stmt->execute([
            ':servizio_id' => $serviceId,
            ':nota' => $note,
            ':user_id' => $_SESSION['user_id'],
        ]);
    } catch (PDOException $e) {
        error_log(CIE_MODULE_LOG . " - Errore aggiunta nota CIE: " . $e->getMessage());
        return false;
    }
}

/**
 * Recupera una richiesta CIE per ID
 */
function cie_get_service(PDO $pdo, int $serviceId): ?array
{
    try {
        $stmt = $pdo->prepare("
            SELECT sc.*, c.ragione_sociale, c.nome as cliente_nome, c.cognome as cliente_cognome
            FROM servizi_cie sc
            LEFT JOIN clienti c ON sc.cliente_id = c.id
            WHERE sc.id = :id
        ");
        
        $stmt->execute([':id' => $serviceId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    } catch (PDOException $e) {
        error_log(CIE_MODULE_LOG . " - Errore recupero servizio CIE: " . $e->getMessage());
        return null;
    }
}

/**
 * Recupera tutte le richieste CIE con filtri
 */
function cie_get_services(PDO $pdo, array $filters = []): array
{
    try {
        $where = [];
        $params = [];
        
        if (!empty($filters['cliente_id'])) {
            $where[] = 'sc.cliente_id = :cliente_id';
            $params[':cliente_id'] = $filters['cliente_id'];
        }
        
        if (!empty($filters['stato'])) {
            $where[] = 'sc.stato = :stato';
            $params[':stato'] = $filters['stato'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = '(sc.nome LIKE :search OR sc.cognome LIKE :search OR sc.codice_fiscale LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $stmt = $pdo->prepare("
            SELECT sc.*, c.ragione_sociale, c.nome as cliente_nome, c.cognome as cliente_cognome
            FROM servizi_cie sc
            LEFT JOIN clienti c ON sc.cliente_id = c.id
            {$whereClause}
            ORDER BY sc.created_at DESC
        ");
        
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log(CIE_MODULE_LOG . " - Errore recupero servizi CIE: " . $e->getMessage());
        return [];
    }
}

/**
 * Recupera le note per una richiesta CIE
 */
function cie_get_notes(PDO $pdo, int $serviceId): array
{
    try {
        $stmt = $pdo->prepare("
            SELECT cn.*, u.nome, u.cognome
            FROM cie_note cn
            LEFT JOIN users u ON cn.user_id = u.id
            WHERE cn.servizio_id = :servizio_id
            ORDER BY cn.created_at DESC
        ");
        
        $stmt->execute([':servizio_id' => $serviceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log(CIE_MODULE_LOG . " - Errore recupero note CIE: " . $e->getMessage());
        return [];
    }
}

/**
 * Elimina una richiesta CIE
 */
function cie_delete_service(PDO $pdo, int $serviceId): bool
{
    try {
        $pdo->beginTransaction();
        
        // Elimina le note associate
        $stmt = $pdo->prepare("DELETE FROM cie_note WHERE servizio_id = :servizio_id");
        $stmt->execute([':servizio_id' => $serviceId]);
        
        // Elimina il servizio
        $stmt = $pdo->prepare("DELETE FROM servizi_cie WHERE id = :id");
        $result = $stmt->execute([':id' => $serviceId]);
        
        $pdo->commit();
        return $result;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log(CIE_MODULE_LOG . " - Errore eliminazione servizio CIE: " . $e->getMessage());
        return false;
    }
}

/**
 * Ottieni statistiche dei servizi CIE
 */
function cie_get_statistics(PDO $pdo): array
{
    try {
        $stats = [];
        
        // Totale richieste
        $stmt = $pdo->query("SELECT COUNT(*) FROM servizi_cie");
        $stats['totale'] = (int) $stmt->fetchColumn();
        
        // Richieste per stato
        $stmt = $pdo->query("
            SELECT stato, COUNT(*) as count 
            FROM servizi_cie 
            GROUP BY stato
        ");
        $stats['per_stato'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Richieste del mese corrente
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM servizi_cie 
            WHERE MONTH(created_at) = MONTH(CURDATE()) 
            AND YEAR(created_at) = YEAR(CURDATE())
        ");
        $stmt->execute();
        $stats['mese_corrente'] = (int) $stmt->fetchColumn();
        
        return $stats;
    } catch (PDOException $e) {
        error_log(CIE_MODULE_LOG . " - Errore recupero statistiche CIE: " . $e->getMessage());
        return [];
    }
}

/**
 * Verifica se una richiesta CIE può essere modificata
 */
function cie_can_edit(array $service): bool
{
    $editableStatuses = ['nuova', 'dati_inviati'];
    return in_array($service['stato'], $editableStatuses, true);
}

/**
 * Verifica se una richiesta CIE può essere eliminata
 */
function cie_can_delete(array $service): bool
{
    $deletableStatuses = ['nuova', 'annullata'];
    return in_array($service['stato'], $deletableStatuses, true);
}



/**
 * Validazione dati per creazione/modifica richiesta CIE
 */
function cie_validate_data(array $data): array
{
    $errors = [];
    
    if (empty($data['nome'])) {
        $errors['nome'] = 'Il nome è obbligatorio';
    }
    
    if (empty($data['cognome'])) {
        $errors['cognome'] = 'Il cognome è obbligatorio';
    }
    
    if (empty($data['codice_fiscale'])) {
        $errors['codice_fiscale'] = 'Il codice fiscale è obbligatorio';
    } elseif (!preg_match('/^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]$/', $data['codice_fiscale'])) {
        $errors['codice_fiscale'] = 'Il codice fiscale non è valido';
    }
    
    if (empty($data['data_nascita'])) {
        $errors['data_nascita'] = 'La data di nascita è obbligatoria';
    }
    
    if (empty($data['luogo_nascita'])) {
        $errors['luogo_nascita'] = 'Il luogo di nascita è obbligatorio';
    }
    
    if (empty($data['documento_tipo'])) {
        $errors['documento_tipo'] = 'Il tipo di documento è obbligatorio';
    }
    
    if (empty($data['documento_numero'])) {
        $errors['documento_numero'] = 'Il numero di documento è obbligatorio';
    }
    
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'L\'email non è valida';
    }
    
    return $errors;
}