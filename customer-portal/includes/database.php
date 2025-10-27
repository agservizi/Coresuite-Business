<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Classe per la gestione della connessione database del portale cliente
 */
class PortalDatabase {
    private static ?PDO $connection = null;

    /**
     * Recupera una variabile d'ambiente con preferenza per i valori del portale.
     */
    private static function envValue(string $key, $default = null): ?string
    {
        $portalKey = 'PORTAL_' . $key;
        $value = env($portalKey);

        if ($value === null || $value === '') {
            $value = env($key, $default);
        }

        if ($value === null || $value === '') {
            return $default === null ? null : (string) $default;
        }

        return is_string($value) ? trim($value) : (string) $value;
    }
    
    public static function getConnection(): PDO {
        if (self::$connection === null) {
            $host = self::envValue('DB_HOST', '127.0.0.1');
            $port = self::envValue('DB_PORT', '3306');
            $database = self::envValue('DB_DATABASE', 'coresuite');
            $username = self::envValue('DB_USERNAME', 'root');
            $password = self::envValue('DB_PASSWORD', '');
            $charset = self::envValue('DB_CHARSET', 'utf8mb4');

            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $database, $charset);
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
            ];
            
            try {
                self::$connection = new PDO($dsn, $username, $password, $options);
                self::$connection->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
                $timezone = self::resolveMysqlTimezone(self::envValue('DB_TIMEZONE', '+00:00'));
                if ($timezone !== null) {
                    self::$connection->exec(sprintf("SET time_zone = '%s'", addslashes($timezone)));
                }
                
                portal_debug_log('Database connection established for portal');
            } catch (PDOException $e) {
                portal_error_log('Database connection failed: ' . $e->getMessage());
                throw new Exception('Errore di connessione al database');
            }
        }
        
        return self::$connection;
    }
    
    public static function beginTransaction(): bool {
        return self::getConnection()->beginTransaction();
    }
    
    public static function commit(): bool {
        return self::getConnection()->commit();
    }
    
    public static function rollback(): bool {
        return self::getConnection()->rollback();
    }
    
    public static function lastInsertId(): string {
        return self::getConnection()->lastInsertId();
    }
    
    /**
     * Esegue una query preparata
     */
    public static function execute(string $sql, array $params = []): PDOStatement {
        try {
            $stmt = self::getConnection()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            portal_error_log('Database query failed: ' . $e->getMessage(), [
                'sql' => $sql,
                'params' => $params
            ]);
            throw new Exception('Errore durante l\'esecuzione della query');
        }
    }
    
    /**
     * Recupera un singolo record
     */
    public static function fetchOne(string $sql, array $params = []): ?array {
        $stmt = self::execute($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Recupera tutti i record
     */
    public static function fetchAll(string $sql, array $params = []): array {
        $stmt = self::execute($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Recupera un singolo valore
     */
    public static function fetchValue(string $sql, array $params = []) {
        $stmt = self::execute($sql, $params);
        return $stmt->fetchColumn();
    }
    
    /**
     * Controlla se esiste almeno un record
     */
    public static function exists(string $sql, array $params = []): bool {
        $stmt = self::execute($sql, $params);
        return $stmt->fetch() !== false;
    }
    
    /**
     * Conta i record
     */
    public static function count(string $sql, array $params = []): int {
        $result = self::fetchValue($sql, $params);
        return (int) $result;
    }
    
    /**
     * Inserisce un record e restituisce l'ID
     */
    public static function insert(string $table, array $data): int {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ':' . $col, $columns);
        
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        
        self::execute($sql, $data);
        return (int) self::lastInsertId();
    }
    
    /**
     * Aggiorna record
     */
    public static function update(string $table, array $data, array $where): int {
        $setClause = implode(', ', array_map(fn($col) => $col . ' = :' . $col, array_keys($data)));
        $whereClause = implode(' AND ', array_map(fn($col) => $col . ' = :where_' . $col, array_keys($where)));
        
        $sql = sprintf('UPDATE %s SET %s WHERE %s', $table, $setClause, $whereClause);
        
        $params = $data;
        foreach ($where as $key => $value) {
            $params['where_' . $key] = $value;
        }
        
        $stmt = self::execute($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Elimina record
     */
    public static function delete(string $table, array $where): int {
        $whereClause = implode(' AND ', array_map(fn($col) => $col . ' = :' . $col, array_keys($where)));
        $sql = sprintf('DELETE FROM %s WHERE %s', $table, $whereClause);
        
        $stmt = self::execute($sql, $where);
        return $stmt->rowCount();
    }

    private static function resolveMysqlTimezone(?string $timezone): ?string
    {
        if ($timezone === null || $timezone === '') {
            return null;
        }

        $timezone = trim($timezone);

        if (preg_match('/^[+-]\d{2}:\d{2}$/', $timezone) === 1) {
            return $timezone;
        }

        try {
            $tz = new DateTimeZone($timezone);
            $now = new DateTime('now', $tz);
            return $now->format('P');
        } catch (Exception $e) {
            portal_error_log('Invalid DB timezone provided: ' . $timezone, ['exception' => $e->getMessage()]);
            return '+00:00';
        }
    }
}

/**
 * Funzioni helper per l'accesso rapido al database
 */
function portal_db(): PDO {
    return PortalDatabase::getConnection();
}

function portal_query(string $sql, array $params = []): PDOStatement {
    return PortalDatabase::execute($sql, $params);
}

function portal_fetch_one(string $sql, array $params = []): ?array {
    return PortalDatabase::fetchOne($sql, $params);
}

function portal_fetch_all(string $sql, array $params = []): array {
    return PortalDatabase::fetchAll($sql, $params);
}

function portal_fetch_value(string $sql, array $params = []) {
    return PortalDatabase::fetchValue($sql, $params);
}

function portal_exists(string $sql, array $params = []): bool {
    return PortalDatabase::exists($sql, $params);
}

function portal_count(string $sql, array $params = []): int {
    return PortalDatabase::count($sql, $params);
}

function portal_insert(string $table, array $data): int {
    return PortalDatabase::insert($table, $data);
}

function portal_update(string $table, array $data, array $where): int {
    return PortalDatabase::update($table, $data, $where);
}

function portal_delete(string $table, array $where): int {
    return PortalDatabase::delete($table, $where);
}