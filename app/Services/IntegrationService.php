<?php
declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use RuntimeException;

final class IntegrationService
{
    private const DEFAULT_BASE_URL = 'https://business.coresuite.it';
    private const DEFAULT_TIMEOUT = 15;
    private const DEFAULT_ENDPOINTS = [
        'customers' => '/api/integrations/customers',
        'customer_delete' => '/api/integrations/customers/{id}',
        'products' => '/api/integrations/products',
        'sales' => '/api/integrations/sales',
        'inventory' => '/api/integrations/inventory',
    ];

    private string $baseUrl;
    private string $apiKey;
    private ?string $tenant;
    private ?string $webhookSecret;
    private array $endpoints;
    private int $timeout;
    private string $userAgent;
    private string $logFile;
    private bool $verifySsl;
    private ?string $caBundle;
    private bool $enabled;

    private function __construct(array $config)
    {
        $projectRoot = null;
        if (isset($config['project_root'])) {
            $candidateRoot = (string) $config['project_root'];
            if ($candidateRoot !== '') {
                $projectRoot = $candidateRoot;
            }
        }

        if ($projectRoot === null) {
            $detected = realpath(__DIR__ . '/..');
            $projectRoot = $detected !== false ? $detected : __DIR__ . '/..';
        }

        $projectRoot = rtrim($projectRoot, DIRECTORY_SEPARATOR);

        $baseUrl = trim((string) ($config['base_url'] ?? self::DEFAULT_BASE_URL));
        $this->baseUrl = rtrim($baseUrl !== '' ? $baseUrl : self::DEFAULT_BASE_URL, '/');

        $this->apiKey = trim((string) ($config['api_key'] ?? ''));
        $explicitEnabled = $config['enabled'] ?? null;
        $this->enabled = $explicitEnabled === null ? true : $this->castBool($explicitEnabled);
        if ($this->apiKey === '') {
            $this->enabled = false;
        }

        $tenant = $config['tenant'] ?? null;
        $tenant = is_string($tenant) ? trim($tenant) : null;
        $this->tenant = $tenant !== '' ? $tenant : null;

        $secret = $config['webhook_secret'] ?? null;
        $secret = is_string($secret) ? trim($secret) : null;
        $this->webhookSecret = $secret !== '' ? $secret : null;

        $this->timeout = $this->normalizePositiveInt($config['timeout'] ?? self::DEFAULT_TIMEOUT, self::DEFAULT_TIMEOUT);

        $userAgent = $config['user_agent'] ?? null;
        $userAgent = is_string($userAgent) ? trim($userAgent) : '';
        $this->userAgent = $userAgent !== '' ? $userAgent : 'CoresuiteExpress/1.0 (+https://coresuite.it)';

        $customEndpoints = $this->normalizeEndpoints($config['endpoints'] ?? null);
        $this->endpoints = array_merge(self::DEFAULT_ENDPOINTS, $customEndpoints);

        $verify = $config['verify_ssl'] ?? null;
        $this->verifySsl = $verify === null ? true : $this->castBool($verify);
        $caBundle = $config['ca_bundle'] ?? null;
        $caBundle = is_string($caBundle) ? trim($caBundle) : '';
        $this->caBundle = $caBundle !== '' ? $caBundle : null;

        $logFile = $config['log_file'] ?? ($projectRoot . '/storage/logs/integrations.log');
        $this->logFile = (string) $logFile;

        if ($this->enabled && !$this->prepareLogDirectory()) {
            $this->enabled = false;
        }
    }

    public static function fromEnv(?string $projectRoot = null): self
    {
        $config = [
            'project_root' => $projectRoot,
            'base_url' => self::env('CORESUITE_BASE_URL', self::DEFAULT_BASE_URL),
            'api_key' => self::env('CORESUITE_API_KEY'),
            'tenant' => self::env('CORESUITE_TENANT'),
            'webhook_secret' => self::env('CORESUITE_WEBHOOK_SECRET'),
            'endpoints' => self::env('CORESUITE_ENDPOINTS'),
            'timeout' => self::env('CORESUITE_TIMEOUT'),
            'verify_ssl' => self::env('CORESUITE_VERIFY_SSL'),
            'ca_bundle' => self::env('CORESUITE_CA_BUNDLE'),
            'enabled' => self::env('CORESUITE_INTEGRATION_ENABLED'),
        ];

        return new self($config);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function syncCustomer(int $customerId, array $customerData): array
    {
        $payload = $this->buildCustomerPayload($customerId, $customerData);
        return $this->request('PUT', 'customers', $payload);
    }

    public function deleteCustomer(int $customerId): array
    {
        $externalId = $this->resolveExternalId('customer', $customerId);
        $endpoint = $this->resolveEndpoint('customer_delete', ['id' => $externalId]);
        return $this->request('DELETE', $endpoint);
    }

    public function syncProduct(int $productId, array $productData): array
    {
        $payload = $this->buildProductPayload($productId, $productData);
        return $this->request('PUT', 'products', $payload);
    }

    public function syncSale(int $saleId, array $movementRow, ?array $items = null): array
    {
        $payload = $this->buildSalePayload($saleId, $movementRow, $items);
        return $this->request('POST', 'sales', $payload);
    }

    public function pushInventoryMovement(int $movementId, array $movementData): array
    {
        $payload = $this->buildInventoryPayload($movementId, $movementData);
        return $this->request('POST', 'inventory', $payload);
    }

    private function buildCustomerPayload(int $customerId, array $customerData): array
    {
        $namePieces = [];
        $company = isset($customerData['ragione_sociale']) ? trim((string) $customerData['ragione_sociale']) : '';
        if ($company !== '') {
            $namePieces[] = $company;
        }

        $first = isset($customerData['nome']) ? trim((string) $customerData['nome']) : '';
        $last = isset($customerData['cognome']) ? trim((string) $customerData['cognome']) : '';
        $fullName = trim(implode(' ', array_filter([$first, $last])));
        if ($fullName !== '') {
            $namePieces[] = $fullName;
        }

        $display = $namePieces ? implode(' | ', array_unique($namePieces)) : 'Cliente #' . $customerId;

        $payload = [
            'external_id' => $this->resolveExternalId('customer', $customerId),
            'full_name' => $display,
            'email' => $this->nullOrString($customerData['email'] ?? null),
            'phone' => $this->nullOrString($customerData['telefono'] ?? null),
            'tax_code' => $this->nullOrString($customerData['cf_piva'] ?? null),
            'note' => $this->nullOrString($customerData['note'] ?? null),
            'synced_at' => $this->nowIso8601(),
        ];

        if (isset($customerData['indirizzo'])) {
            $address = $this->nullOrString($customerData['indirizzo']);
            if ($address !== null) {
                $payload['address'] = $address;
            }
        }

        return $payload;
    }

    private function buildProductPayload(int $productId, array $productData): array
    {
        $payload = [
            'external_id' => $this->resolveExternalId('product', $productId),
            'name' => $this->nullOrString($productData['name'] ?? $productData['nome'] ?? ''),
            'sku' => $this->nullOrString($productData['sku'] ?? null),
            'imei' => $this->nullOrString($productData['imei'] ?? null),
            'category' => $this->nullOrString($productData['category'] ?? $productData['categoria'] ?? null),
            'price' => $this->normalizeNumber($productData['price'] ?? $productData['prezzo'] ?? null),
            'stock_quantity' => $this->normalizeNumber($productData['stock_quantity'] ?? $productData['quantita'] ?? null),
            'tax_rate' => $this->normalizeNumber($productData['tax_rate'] ?? $productData['aliquota'] ?? null),
            'vat_code' => $this->nullOrString($productData['vat_code'] ?? $productData['codice_iva'] ?? null),
            'is_active' => $this->castBool($productData['is_active'] ?? $productData['attivo'] ?? true),
            'synced_at' => $this->nowIso8601(),
        ];

        if (array_key_exists('description', $productData)) {
            $description = $this->nullOrString($productData['description']);
            if ($description !== null) {
                $payload['description'] = $description;
            }
        }

        return $payload;
    }

    private function buildSalePayload(int $saleId, array $movementRow, ?array $items): array
    {
        $customerId = $movementRow['cliente_id'] ?? null;
        $customerId = $customerId !== null ? (int) $customerId : null;
        $customerName = $this->resolveCustomerName($movementRow);

        $total = $this->normalizeNumber($movementRow['importo'] ?? 0.0);
        $vatRate = $this->resolveVatRate();
        $vatAmount = $this->calculateVatAmount($total, $vatRate);
        $balanceDue = $this->resolveBalanceDue($total, (string) ($movementRow['stato'] ?? ''));

        if ($items === null) {
            $items = [[
                'description' => $this->nullOrString($movementRow['descrizione'] ?? 'Movimento #' . $saleId) ?? 'Movimento #' . $saleId,
                'quantity' => 1,
                'unit_price' => $total,
                'tax_rate' => $vatRate,
                'tax_amount' => $vatAmount,
                'product_external_id' => $this->nullOrString($movementRow['product_external_id'] ?? null),
                'iccid_code' => $this->nullOrString($movementRow['iccid_code'] ?? null),
            ]];
        }

        $payload = [
            'external_id' => $this->resolveExternalId('sale', $saleId),
            'customer_external_id' => $customerId !== null ? $this->resolveExternalId('customer', $customerId) : null,
            'customer_name' => $customerName,
            'total' => $total,
            'total_paid' => $total - $balanceDue,
            'balance_due' => $balanceDue,
            'payment_status' => $this->mapPaymentStatus((string) ($movementRow['stato'] ?? '')),
            'due_date' => $this->formatDate($movementRow['data_scadenza'] ?? null),
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'discount' => $this->normalizeNumber($movementRow['discount'] ?? 0),
            'items' => $items,
            'synced_at' => $this->nowIso8601(),
        ];

        if (!empty($movementRow['note'])) {
            $note = $this->nullOrString($movementRow['note']);
            if ($note !== null) {
                $payload['notes'] = $note;
            }
        }

        if (!empty($movementRow['riferimento'])) {
            $ref = $this->nullOrString($movementRow['riferimento']);
            if ($ref !== null) {
                $payload['reference'] = $ref;
            }
        }

        return $payload;
    }

    private function buildInventoryPayload(int $movementId, array $movementData): array
    {
        $payload = [
            'external_id' => $this->resolveExternalId('inventory', $movementId),
            'product_external_id' => $this->nullOrString($movementData['product_external_id'] ?? null),
            'quantity' => $this->normalizeNumber($movementData['quantity'] ?? 0),
            'reason' => $this->nullOrString($movementData['reason'] ?? $movementData['descrizione'] ?? ''),
            'synced_at' => $this->nowIso8601(),
        ];

        if (!empty($movementData['metadata']) && is_array($movementData['metadata'])) {
            $payload['metadata'] = $movementData['metadata'];
        }

        return $payload;
    }

    private function request(string $method, string $endpointKeyOrPath, ?array $payload = null): array
    {
        if (!$this->enabled) {
            return ['status' => 0, 'body' => null];
        }

        $method = strtoupper($method);
        $path = $this->endpoints[$endpointKeyOrPath] ?? $endpointKeyOrPath;
        if (!str_starts_with($path, '/')) {
            $path = '/' . ltrim($path, '/');
        }

        $url = $this->baseUrl . $path;
        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Impossibile inizializzare la richiesta verso business.coresuite.it');
        }

        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->apiKey,
            'User-Agent: ' . $this->userAgent,
        ];

        if ($this->tenant !== null) {
            $headers[] = 'X-Tenant: ' . $this->tenant;
        }

        if ($this->webhookSecret !== null) {
            $headers[] = 'X-Webhook-Secret: ' . $this->webhookSecret;
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 2,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($this->verifySsl === false) {
            $options[CURLOPT_SSL_VERIFYHOST] = 0;
        } elseif ($this->caBundle !== null) {
            $options[CURLOPT_CAINFO] = $this->caBundle;
        }

        if ($method === 'GET') {
            $options[CURLOPT_HTTPGET] = true;
            if ($payload) {
                $query = http_build_query($payload);
                if ($query !== '') {
                    $urlWithQuery = $url . (str_contains($url, '?') ? '&' : '?') . $query;
                    $options[CURLOPT_URL] = $urlWithQuery;
                }
            }
        } else {
            $options[CURLOPT_CUSTOMREQUEST] = $method;
            $encoded = $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
            if ($encoded === false) {
                curl_close($handle);
                throw new RuntimeException('Impossibile serializzare il payload di integrazione.');
            }
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_HTTPHEADER] = $headers;
            $options[CURLOPT_POSTFIELDS] = $encoded;
        }

        $start = microtime(true);
        curl_setopt_array($handle, $options);
        $response = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error = $response === false ? curl_error($handle) : null;
        curl_close($handle);
        $duration = (int) round((microtime(true) - $start) * 1000);

        if ($response === false || $error !== null) {
            $this->writeLog(sprintf('%s %s | error=%s | duration=%dms', $method, $path, $error ?? 'unknown', $duration));
            throw new RuntimeException('Errore di comunicazione con l\'ERP: ' . ($error ?? 'sconosciuto'));
        }

        $decoded = $response === '' ? [] : json_decode($response, true);
        if ($response !== '' && json_last_error() !== JSON_ERROR_NONE) {
            $this->writeLog(sprintf('%s %s | status=%d | invalid_json | duration=%dms', $method, $path, $status, $duration));
            throw new RuntimeException('Risposta ERP non valida: formato JSON non riconosciuto.');
        }

        $this->writeLog(sprintf('%s %s | status=%d | duration=%dms', $method, $path, $status, $duration));

        if ($status < 200 || $status >= 300) {
            $message = is_array($decoded) ? ($decoded['error'] ?? $decoded['message'] ?? ('Status ' . $status)) : ('Status ' . $status);
            throw new RuntimeException('Errore ERP: ' . $message, $status);
        }

        return ['status' => $status, 'body' => is_array($decoded) ? $decoded : null];
    }

    private function resolveEndpoint(string $key, array $parameters): string
    {
        $template = $this->endpoints[$key] ?? self::DEFAULT_ENDPOINTS[$key] ?? null;
        if ($template === null) {
            throw new RuntimeException('Endpoint non configurato: ' . $key);
        }

        foreach ($parameters as $placeholder => $value) {
            $template = str_replace('{' . $placeholder . '}', rawurlencode((string) $value), $template);
        }

        if (str_contains($template, '{')) {
            throw new RuntimeException('Placeholder endpoint non risolto per chiave: ' . $key);
        }

        return str_starts_with($template, '/') ? $template : '/' . ltrim($template, '/');
    }

    private function resolveExternalId(string $prefix, int $id): string
    {
        return $prefix . '-' . $id;
    }

    private function resolveCustomerName(array $movementRow): ?string
    {
        $company = $this->nullOrString($movementRow['ragione_sociale'] ?? null);
        $first = $this->nullOrString($movementRow['nome'] ?? null);
        $last = $this->nullOrString($movementRow['cognome'] ?? null);

        $full = trim(($first ?? '') . ' ' . ($last ?? ''));
        if ($company && $full) {
            return $company . ' | ' . $full;
        }

        if ($company) {
            return $company;
        }

        if ($full !== '') {
            return $full;
        }

        return null;
    }

    private function mapPaymentStatus(string $status): string
    {
        $normalized = strtolower(trim($status));
        return match ($normalized) {
            'completato', 'pagato', 'chiuso' => 'Paid',
            'annullato', 'cancellato' => 'Cancelled',
            'in attesa', 'sospeso' => 'Pending',
            default => 'Draft',
        };
    }

    private function resolveBalanceDue(float $total, string $status): float
    {
        $mapped = strtolower(trim($status));
        if (in_array($mapped, ['completato', 'pagato', 'chiuso'], true)) {
            return 0.0;
        }

        return $total;
    }

    private function calculateVatAmount(float $total, float $vatRate): float
    {
        if ($vatRate <= 0.0) {
            return 0.0;
        }

        $divider = 100 + $vatRate;
        if ($divider <= 0) {
            return 0.0;
        }

        $vat = $total * $vatRate / $divider;
        return round($vat, 2);
    }

    private function formatDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value->format('Y-m-d');
        }

        $string = (string) $value;
        if ($string === '') {
            return null;
        }

        $timestamp = strtotime($string);
        if ($timestamp === false) {
            return null;
        }

        return gmdate('Y-m-d', $timestamp);
    }

    private function resolveVatRate(): float
    {
        $envValue = self::env('CORESUITE_DEFAULT_VAT_RATE');
        if ($envValue === null || $envValue === '') {
            return 22.0;
        }

        $numeric = $this->normalizeNumber($envValue);
        return $numeric !== null ? $numeric : 22.0;
    }

    private function nowIso8601(): string
    {
        return (new DateTimeImmutable())->format(DateTimeImmutable::ATOM);
    }

    private function normalizeNumber(mixed $value, ?float $default = null): ?float
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $normalized = str_replace(',', '.', trim($value));
            if ($normalized === '') {
                return $default;
            }
            if (!is_numeric($normalized)) {
                return $default;
            }
            return (float) $normalized;
        }

        return $default;
    }

    private function castBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            return in_array($normalized, ['1', 'true', 'yes', 'on', 'enabled'], true);
        }

        return false;
    }

    private function nullOrString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);
        return $trimmed !== '' ? $trimmed : null;
    }

    private function normalizePositiveInt(mixed $value, int $default): int
    {
        $numeric = $this->normalizeNumber($value, $default);
        $intValue = (int) round((float) ($numeric ?? $default));
        return $intValue > 0 ? $intValue : $default;
    }

    private function prepareLogDirectory(): bool
    {
        $directory = dirname($this->logFile);
        if (is_dir($directory)) {
            return is_writable($directory);
        }

        if (!@mkdir($directory, 0775, true) && !is_dir($directory)) {
            error_log('IntegrationService: impossibile creare la cartella log: ' . $directory);
            return false;
        }

        return true;
    }

    private function writeLog(string $message): void
    {
        $timestamp = $this->nowIso8601();
        $line = sprintf('%s | %s%s', $timestamp, $message, PHP_EOL);
        @file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }

    private function normalizeEndpoints(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (!is_array($decoded)) {
                return [];
            }
            return $this->sanitizeEndpointsArray($decoded);
        }

        if (is_array($value)) {
            return $this->sanitizeEndpointsArray($value);
        }

        return [];
    }

    private function sanitizeEndpointsArray(array $input): array
    {
        $sanitized = [];
        foreach ($input as $key => $path) {
            if (!is_string($key) || !is_string($path)) {
                continue;
            }
            $trimmed = trim($path);
            if ($trimmed === '') {
                continue;
            }
            $sanitized[$key] = str_starts_with($trimmed, '/') ? $trimmed : '/' . ltrim($trimmed, '/');
        }

        return $sanitized;
    }

    private static function env(string $key, mixed $default = null): mixed
    {
        if (function_exists('env')) {
            return env($key, $default);
        }

        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return $value;
    }
}
