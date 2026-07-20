<?php
declare(strict_types=1);

const DB_PATH = __DIR__ . '/data/mercadinho.sqlite';
const MERCADINHO_URL = 'https://falaritalianofacil.com.br/mercadinho/';
const LOGO_URL = 'https://i.imgur.com/jWebIr7.png';
const COIN_WIN_IMAGE_URL = 'https://i.imgur.com/6cBzdMs.jpeg';
const TOKEN_IMPORTACAO = 'ifcoins-equipe-2026';

date_default_timezone_set('America/Sao_Paulo');

$TEAM_USERS = [
    ['name' => 'Daiane', 'email' => 'daiane@italianofacil.com.br', 'sector' => 'lideranca', 'role' => 'admin'],
    ['name' => 'Amanda', 'email' => 'amanda@italianofacil.com.br', 'sector' => 'lideranca', 'role' => 'admin'],
    ['name' => 'Ronaldo', 'email' => 'ronaldo@italianofacil.com.br', 'sector' => 'lideranca', 'role' => 'admin'],
    ['name' => 'Sheron', 'email' => 'sheron@italianofacil.com', 'sector' => 'lideranca', 'role' => 'admin'],

    ['name' => 'Felipe', 'email' => 'felipe@italianofacil.com', 'sector' => 'suporte', 'role' => 'user'],
    ['name' => 'Nicole', 'email' => 'nicole@italianofacil.com', 'sector' => 'suporte', 'role' => 'user'],
    ['name' => 'Caroline', 'email' => 'caroline@italianofacil.com', 'sector' => 'suporte', 'role' => 'user'],
    ['name' => 'Gabriel', 'email' => 'gabriel@italianofacil.com', 'sector' => 'suporte', 'role' => 'user'],
    ['name' => 'Adryan', 'email' => 'adryan@italianofacil.com', 'sector' => 'suporte', 'role' => 'user'],

    ['name' => 'Maurício', 'email' => 'mauricio@italianofacil.com', 'sector' => 'marketing', 'role' => 'user'],
    ['name' => 'Emmanoel', 'email' => 'emmanoel@italianofacil.com', 'sector' => 'marketing', 'role' => 'user'],

    ['name' => 'Daniele', 'email' => 'daniele@italianofacil.com', 'sector' => 'cuidado_bem_estar', 'role' => 'admin'],
    ['name' => 'Nicole Oliveira', 'email' => 'nicoleoliveira@italianofacil.com', 'sector' => 'cuidado_bem_estar', 'role' => 'user'],

    ['name' => 'Claudia', 'email' => 'claudia@italianofacil.com', 'sector' => 'rh', 'role' => 'admin'],

    ['name' => 'Joshua', 'email' => 'joshua@italianofacil.com', 'sector' => 'tecnologia', 'role' => 'user'],

    ['name' => 'Marina', 'email' => 'marina@italianofacil.com', 'sector' => 'comercial', 'role' => 'user'],
    ['name' => 'Heloisa', 'email' => 'heloisa@italianofacil.com', 'sector' => 'comercial', 'role' => 'user'],
    ['name' => 'Kelvyn', 'email' => 'kelvyn@italianofacil.com', 'sector' => 'comercial', 'role' => 'user'],
    ['name' => 'Gabriel Martins', 'email' => 'gabrielmartins@italianofacil.com', 'sector' => 'comercial', 'role' => 'user'],
    ['name' => 'Victoria', 'email' => 'victoria@italianofacil.com', 'sector' => 'comercial', 'role' => 'user'],
    ['name' => 'Alana', 'email' => 'alana@italianofacil.com', 'sector' => 'comercial', 'role' => 'user'],
    ['name' => 'Bruna', 'email' => 'bruna@italianofacil.com', 'sector' => 'comercial', 'role' => 'user'],

    ['name' => 'Mellany', 'email' => 'mellany@italianofacil.com', 'sector' => 'financeiro', 'role' => 'user'],
    ['name' => 'Melanie', 'email' => 'melanie@italianofacil.com', 'sector' => 'financeiro', 'role' => 'user'],
];

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!is_file(DB_PATH)) {
        throw new RuntimeException('Banco não encontrado em data/mercadinho.sqlite. Abra o sistema uma vez antes de importar.');
    }

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    return $pdo;
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function code_alphabet(): string
{
    return 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
}

function generate_code(): string
{
    $alphabet = code_alphabet();
    do {
        $code = '';
        for ($i = 0; $i < 5; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE code = ?');
        $stmt->execute([$code]);
    } while ((int) $stmt->fetchColumn() > 0);

    return $code;
}

function password_from_email(string $email): string
{
    $name = strtolower(trim(explode('@', $email)[0] ?? $email));
    return $name . '@123';
}

function sector_label(string $sector): string
{
    return [
        'lideranca' => 'Liderança',
        'suporte' => 'Suporte',
        'marketing' => 'Marketing',
        'cuidado_bem_estar' => 'Cuidado e Bem-estar',
        'rh' => 'RH',
        'tecnologia' => 'Tecnologia',
        'comercial' => 'Comercial',
        'financeiro' => 'Financeiro',
        'administrativo' => 'Administrativo',
    ][$sector] ?? 'Administrativo';
}

function expected_monthly_balance(string $sector): int
{
    if ($sector === 'lideranca') {
        return 10;
    }
    if ($sector === 'comercial') {
        return 0;
    }
    return 5;
}

function ensure_database_ready(): void
{
    $tables = db()->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetchColumn();
    if (!$tables) {
        throw new RuntimeException('Tabela users não encontrada. Abra o index.php ou painel.php uma vez para criar o banco.');
    }

    $columns = db()->query('PRAGMA table_info(users)')->fetchAll();
    $hasSector = false;
    foreach ($columns as $column) {
        if (($column['name'] ?? '') === 'sector') {
            $hasSector = true;
            break;
        }
    }

    if (!$hasSector) {
        db()->exec("ALTER TABLE users ADD COLUMN sector TEXT NOT NULL DEFAULT 'administrativo'");
    }
}

function mail_config(): array
{
    $path = __DIR__ . '/data/mail_config.php';
    if (!is_file($path)) {
        return ['enabled' => false];
    }

    $config = require $path;
    return is_array($config) ? $config : ['enabled' => false];
}

function smtp_read($socket): string
{
    $data = '';
    while (!feof($socket)) {
        $line = fgets($socket, 515);
        if ($line === false) {
            break;
        }
        $data .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    return $data;
}

function smtp_command($socket, string $command, array $expected): string
{
    fwrite($socket, $command . "\r\n");
    $response = smtp_read($socket);
    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $expected, true)) {
        throw new RuntimeException('SMTP respondeu de forma inesperada: ' . trim($response));
    }
    return $response;
}

function smtp_send(string $toEmail, string $toName, string $subject, string $html, string $text): void
{
    $config = mail_config();
    if (empty($config['enabled'])) {
        throw new RuntimeException('Envio de email não configurado em data/mail_config.php.');
    }

    foreach (['host', 'port', 'username', 'password', 'from_email', 'from_name'] as $requiredKey) {
        if (!isset($config[$requiredKey]) || (string) $config[$requiredKey] === '') {
            throw new RuntimeException('Configuração SMTP incompleta: falta ' . $requiredKey . '.');
        }
    }

    $host = (string) $config['host'];
    $port = (int) $config['port'];
    $remote = (($config['encryption'] ?? '') === 'ssl' ? 'ssl://' : '') . $host;
    $socket = fsockopen($remote, $port, $errno, $errstr, (int) ($config['timeout'] ?? 20));
    if (!$socket) {
        throw new RuntimeException('Não foi possível conectar ao SMTP: ' . $errstr);
    }

    stream_set_timeout($socket, (int) ($config['timeout'] ?? 20));

    try {
        $hello = smtp_read($socket);
        if ((int) substr($hello, 0, 3) !== 220) {
            throw new RuntimeException('SMTP não iniciou corretamente: ' . trim($hello));
        }

        smtp_command($socket, 'EHLO italianofacil.com', [250]);
        smtp_command($socket, 'AUTH LOGIN', [334]);
        smtp_command($socket, base64_encode((string) $config['username']), [334]);
        smtp_command($socket, base64_encode((string) $config['password']), [235]);
        smtp_command($socket, 'MAIL FROM:<' . $config['from_email'] . '>', [250]);
        smtp_command($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251]);
        smtp_command($socket, 'DATA', [354]);

        $boundary = 'ifcoins_' . bin2hex(random_bytes(12));
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $fromName = '=?UTF-8?B?' . base64_encode((string) $config['from_name']) . '?=';
        $toNameEncoded = $toName !== '' ? '=?UTF-8?B?' . base64_encode($toName) . '?= ' : '';

        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $fromName . ' <' . $config['from_email'] . '>',
            'To: ' . $toNameEncoded . '<' . $toEmail . '>',
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        $message = implode("\r\n", $headers) . "\r\n\r\n";
        $message .= '--' . $boundary . "\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $text . "\r\n\r\n";
        $message .= '--' . $boundary . "\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $html . "\r\n\r\n";
        $message .= '--' . $boundary . "--\r\n";
        $message = preg_replace('/^\./m', '..', $message);

        fwrite($socket, $message . "\r\n.\r\n");
        $response = smtp_read($socket);
        if ((int) substr($response, 0, 3) !== 250) {
            throw new RuntimeException('SMTP não aceitou a mensagem: ' . trim($response));
        }
        smtp_command($socket, 'QUIT', [221]);
    } finally {
        fclose($socket);
    }
}

function email_shell(string $title, string $subtitle, string $bodyHtml): string
{
    return '
    <div style="margin:0;padding:0;background:#f5f2e7;font-family:Arial,Helvetica,sans-serif;color:#132019;">
        <div style="max-width:680px;margin:0 auto;padding:30px 16px;">
            <div style="border-radius:30px;overflow:hidden;background:#fffaf0;border:1px solid #eadfbe;box-shadow:0 26px 70px rgba(21,42,29,.16);">
                <div style="padding:30px 28px 24px;background:linear-gradient(135deg,#082f1d 0%,#0b7a42 52%,#d8a83e 100%);color:#ffffff;">
                    <div style="font-size:12px;letter-spacing:.18em;text-transform:uppercase;font-weight:900;opacity:.88;">Mercadinho IF</div>
                    <h1 style="margin:10px 0 10px;font-size:34px;line-height:1.05;font-weight:900;">' . $title . '</h1>
                    <p style="margin:0;font-size:16px;line-height:1.5;color:rgba(255,255,255,.88);">' . $subtitle . '</p>
                </div>
                <div style="padding:26px 28px;text-align:center;">
                    <img src="' . h(COIN_WIN_IMAGE_URL) . '" alt="IF Coins" style="display:block;width:100%;max-width:280px;margin:0 auto;border-radius:24px;box-shadow:0 18px 42px rgba(8,95,50,.18);">
                </div>
                <div style="padding:0 28px 30px;">
                    ' . $bodyHtml . '
                    <div style="margin-top:24px;padding:16px 18px;border-radius:18px;background:#f2f7ed;border:1px solid #dcebd8;color:#506052;font-size:13px;line-height:1.5;">
                        Mercadinho IF • Uma brincadeira interna para reconhecer conquistas, metas e bons momentos da equipe.
                    </div>
                </div>
            </div>
        </div>
    </div>';
}

function send_welcome_email(array $user, string $plainPassword): void
{
    $safeName = h((string) $user['name']);
    $safeCode = h((string) $user['code']);
    $safeEmail = h((string) $user['email']);
    $safePassword = h($plainPassword);
    $safeSector = h(sector_label((string) $user['sector']));
    $balance = (int) $user['balance'];
    $expected = expected_monthly_balance((string) $user['sector']);
    $safeUrl = h(MERCADINHO_URL);

    $subject = 'Seu acesso ao Mercadinho IF foi liberado';
    $text = "Oi, {$user['name']}!\n\n"
        . "Seu acesso ao Mercadinho IF foi criado.\n"
        . "Email: {$user['email']}\n"
        . "Senha inicial: {$plainPassword}\n"
        . "Código de resgate: {$user['code']}\n"
        . "Setor: " . sector_label((string) $user['sector']) . "\n"
        . "Saldo atual: {$balance} IF Coins\n"
        . "Recarga prevista no mês: {$expected} IF Coins\n\n"
        . "Acesse: " . MERCADINHO_URL;

    $bodyHtml = '
        <p style="font-size:17px;line-height:1.55;margin:0 0 18px;">Oi, <strong>' . $safeName . '</strong>! Seu acesso ao Mercadinho IF está pronto.</p>

        <div style="background:#eef7ed;border:1px solid #d7ead3;border-radius:20px;padding:20px;text-align:center;margin:20px 0;">
            <div style="font-size:12px;color:#617066;font-weight:900;text-transform:uppercase;letter-spacing:.13em;margin-bottom:8px;">Seu código de resgate</div>
            <div style="font-size:30px;letter-spacing:.22em;font-weight:900;color:#0b7a42;">' . $safeCode . '</div>
        </div>

        <div style="background:#fff8df;border:1px solid #e5d49a;border-radius:20px;padding:18px;margin:18px 0;">
            <p style="margin:0 0 10px;font-size:15px;line-height:1.5;color:#132019;"><strong>Email:</strong> ' . $safeEmail . '</p>
            <p style="margin:0 0 10px;font-size:15px;line-height:1.5;color:#132019;"><strong>Senha inicial:</strong> ' . $safePassword . '</p>
            <p style="margin:0 0 10px;font-size:15px;line-height:1.5;color:#132019;"><strong>Setor:</strong> ' . $safeSector . '</p>
            <p style="margin:0;font-size:15px;line-height:1.5;color:#132019;"><strong>Saldo atual:</strong> ' . $balance . ' IF Coins</p>
        </div>

        <div style="background:#f8f5ea;border:1px solid #eadfbe;border-radius:18px;padding:14px 16px;text-align:center;margin:18px 0;">
            <div style="font-size:14px;color:#132019;font-weight:800;">Recarga mensal prevista para este setor: ' . $expected . ' IF Coins</div>
        </div>

        <div style="text-align:center;margin:26px 0 10px;">
            <a href="' . $safeUrl . '" style="display:inline-block;background:#0f8f4f;color:#ffffff;text-decoration:none;padding:15px 24px;border-radius:14px;font-weight:900;font-size:15px;box-shadow:0 10px 24px rgba(15,143,79,.25);">Entrar no Mercadinho IF</a>
        </div>
    ';

    $html = email_shell('Acesso liberado', 'Use seu código para resgatar itens e acompanhar seus IF Coins.', $bodyHtml);
    smtp_send((string) $user['email'], (string) $user['name'], $subject, $html, $text);
}

function import_users(array $teamUsers, bool $resetBalance): array
{
    $results = [];
    $pdo = db();
    $pdo->beginTransaction();

    try {
        foreach ($teamUsers as $item) {
            $email = strtolower(trim((string) $item['email']));
            $password = password_from_email($email);
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $role = ($item['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
            $sector = (string) $item['sector'];

            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $existing = $stmt->fetch();

            if ($existing) {
                if ($resetBalance) {
                    $pdo->prepare('UPDATE users SET name = ?, password_hash = ?, role = ?, sector = ?, balance = 0, active = 1, updated_at = ? WHERE id = ?')
                        ->execute([$item['name'], $passwordHash, $role, $sector, now(), (int) $existing['id']]);
                } else {
                    $pdo->prepare('UPDATE users SET name = ?, password_hash = ?, role = ?, sector = ?, active = 1, updated_at = ? WHERE id = ?')
                        ->execute([$item['name'], $passwordHash, $role, $sector, now(), (int) $existing['id']]);
                }
                $status = 'Atualizado';
            } else {
                $pdo->prepare('INSERT INTO users (name, email, password_hash, role, sector, code, balance, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 0, 1, ?, ?)')
                    ->execute([$item['name'], $email, $passwordHash, $role, $sector, generate_code(), now(), now()]);
                $status = 'Criado';
            }

            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            $results[] = ['status' => $status, 'user' => $user, 'password' => $password];
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    return $results;
}

function fetch_team_users(array $teamUsers): array
{
    $results = [];
    foreach ($teamUsers as $item) {
        $email = strtolower(trim((string) $item['email']));
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? AND active = 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            $results[] = ['status' => 'Encontrado', 'user' => $user, 'password' => password_from_email($email)];
        } else {
            $results[] = ['status' => 'Não encontrado', 'user' => ['name' => $item['name'], 'email' => $email, 'sector' => $item['sector'], 'role' => $item['role'], 'code' => '', 'balance' => 0], 'password' => password_from_email($email)];
        }
    }
    return $results;
}

$token = $_GET['token'] ?? '';
if ($token !== TOKEN_IMPORTACAO) {
    http_response_code(403);
    echo 'Acesso negado. Use o token correto.';
    exit;
}

$modo = $_GET['modo'] ?? 'importar';
$validModes = ['importar', 'emails', 'tudo'];
if (!in_array($modo, $validModes, true)) {
    http_response_code(400);
    echo 'Modo inválido. Use modo=importar, modo=emails ou modo=tudo.';
    exit;
}

try {
    ensure_database_ready();

    if ($modo === 'importar') {
        $rows = import_users($TEAM_USERS, true);
        $mainMessage = 'Usuários importados/atualizados com saldo zerado. Nenhum email foi enviado.';
    } elseif ($modo === 'tudo') {
        $rows = import_users($TEAM_USERS, true);
        $mainMessage = 'Usuários importados/atualizados com saldo zerado e tentativa de envio de boas-vindas realizada.';
    } else {
        $rows = fetch_team_users($TEAM_USERS);
        $mainMessage = 'Tentativa de envio de boas-vindas realizada usando o saldo atual do banco.';
    }

    if ($modo === 'emails' || $modo === 'tudo') {
        foreach ($rows as $index => $row) {
            if (($row['status'] ?? '') === 'Não encontrado') {
                $rows[$index]['email_status'] = 'Não enviado: usuário não existe no banco.';
                continue;
            }

            try {
                send_welcome_email($row['user'], $row['password']);
                $rows[$index]['email_status'] = 'Enviado';
            } catch (Throwable $mailError) {
                $rows[$index]['email_status'] = 'Falhou: ' . $mailError->getMessage();
            }
        }
    }

    echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Importação IF Coins</title>';
    echo '<body style="font-family:Arial,sans-serif;background:#f5f2e7;color:#132019;padding:24px;">';
    echo '<h1>Mercadinho IF • Importação da equipe</h1>';
    echo '<p><strong>' . h($mainMessage) . '</strong></p>';
    echo '<p><strong>Modo:</strong> ' . h($modo) . '</p>';
    echo '<p>Importante: depois de concluir, remova este arquivo do servidor.</p>';
    echo '<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;background:white;width:100%;font-size:14px;">';
    echo '<tr><th>Status</th><th>Nome</th><th>Email</th><th>Perfil</th><th>Setor</th><th>Código</th><th>Saldo atual</th><th>Senha inicial</th><th>Email boas-vindas</th></tr>';

    foreach ($rows as $row) {
        $user = $row['user'];
        echo '<tr>';
        echo '<td>' . h($row['status'] ?? '') . '</td>';
        echo '<td>' . h((string) ($user['name'] ?? '')) . '</td>';
        echo '<td>' . h((string) ($user['email'] ?? '')) . '</td>';
        echo '<td>' . h((string) ($user['role'] ?? '')) . '</td>';
        echo '<td>' . h(sector_label((string) ($user['sector'] ?? 'administrativo'))) . '</td>';
        echo '<td><strong>' . h((string) ($user['code'] ?? '')) . '</strong></td>';
        echo '<td>' . (int) ($user['balance'] ?? 0) . '</td>';
        echo '<td>' . h($row['password'] ?? '') . '</td>';
        echo '<td>' . h($row['email_status'] ?? 'Não solicitado') . '</td>';
        echo '</tr>';
    }

    echo '</table>';
    echo '<h2>Links rápidos</h2>';
    echo '<p><code>importar_usuarios_zerados_e_boas_vindas.php?token=' . TOKEN_IMPORTACAO . '&modo=importar</code> → importa/atualiza e zera as moedas.</p>';
    echo '<p><code>importar_usuarios_zerados_e_boas_vindas.php?token=' . TOKEN_IMPORTACAO . '&modo=emails</code> → envia boas-vindas com saldo atual.</p>';
    echo '</body></html>';
} catch (Throwable $exception) {
    http_response_code(500);
    echo '<pre>Erro: ' . h($exception->getMessage()) . '</pre>';
}
