<?php
// ============================================
// config/db.php — Connessione PDO centralizzata
// ============================================

function getDB(): PDO {
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'TradeMarketAi';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $candidates = [$name];
    // Fallback compatibilita per ambienti dove il DB non e stato ancora rinominato.
    if ($name !== 'auth_system') {
        $candidates[] = 'auth_system';
    }

    $lastError = null;
    foreach ($candidates as $dbName) {
        $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
            return $pdo;
        } catch (PDOException $e) {
            $lastError = $e;
            // 1049 = Unknown database, prova il prossimo candidato.
            if ((string)$e->getCode() !== '1049') {
                break;
            }
        }
    }

    if ($lastError instanceof PDOException) {
        throw new RuntimeException('Connessione al database non riuscita: ' . $lastError->getMessage(), 0, $lastError);
    }

    return $pdo;
}