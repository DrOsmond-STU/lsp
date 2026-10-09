<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    global $CONFIG;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $c = $CONFIG['db'];
    $pdo = new PDO($c['dsn'], $c['user'] ?? null, $c['pass'] ?? null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
    }
    return $pdo;
}

function driver(): string
{
    return db()->getAttribute(PDO::ATTR_DRIVER_NAME);
}

/** Selalu prepared statement; jangan pernah menyambung input ke SQL. */
function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}
