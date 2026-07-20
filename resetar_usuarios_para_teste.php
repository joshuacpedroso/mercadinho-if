<?php
declare(strict_types=1);

session_start();

const DB_PATH = __DIR__ . '/data/mercadinho.sqlite';

date_default_timezone_set('America/Sao_Paulo');

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = OFF');
    return $pdo;
}

$token = $_GET['token'] ?? '';
if ($token !== 'resetar-ifcoins') {
    http_response_code(403);
    echo 'Acesso negado. Use: resetar_usuarios_para_teste.php?token=resetar-ifcoins';
    exit;
}

$pdo = db();
$pdo->beginTransaction();
try {
    $admin = $pdo->query("SELECT * FROM users WHERE role = 'admin' ORDER BY id LIMIT 1")->fetch();
    if (!$admin) {
        throw new RuntimeException('Nenhum administrador encontrado.');
    }

    $pdo->exec('DELETE FROM donation_logs');
    $pdo->exec('DELETE FROM transactions');
    $pdo->exec('DELETE FROM credit_logs');
    $stmt = $pdo->prepare('DELETE FROM users WHERE id <> ?');
    $stmt->execute([(int) $admin['id']]);
    $pdo->prepare("UPDATE users SET email = ?, role = 'admin', sector = 'lideranca', balance = 10, active = 1, updated_at = ? WHERE id = ?")
        ->execute(['admin@italiafacil.com', date('Y-m-d H:i:s'), (int) $admin['id']]);

    $pdo->commit();
    echo 'Base de teste limpa. Ficou somente o administrador: admin@italiafacil.com';
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo 'Erro: ' . $exception->getMessage();
}
