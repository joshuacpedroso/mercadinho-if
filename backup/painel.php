<?php
declare(strict_types=1);

session_start();

const APP_NAME = 'Mercadinho IF';
const CORPORATE_DOMAIN = '@italianofacil.com';
const LOGO_URL = 'https://i.imgur.com/jWebIr7.png';
const COIN_WIN_IMAGE_URL = 'https://i.imgur.com/6cBzdMs.jpeg';
const MERCADINHO_URL = 'https://falaritalianofacil.com.br/mercadinho/';
const DB_PATH = __DIR__ . '/data/mercadinho.sqlite';
const UPLOAD_DIR = __DIR__ . '/uploads';

date_default_timezone_set('America/Sao_Paulo');

if (!is_dir(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data', 0775, true);
}
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0775, true);
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function flash(?string $message = null, string $type = 'success'): ?array
{
    if ($message !== null) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
        return null;
    }

    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function redirect_to(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        flash('Sessão expirada. Tente novamente.', 'error');
        redirect_to('painel.php');
    }
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

function normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function is_corporate_email(string $email): bool
{
    $email = normalize_email($email);
    foreach (corporate_domains() as $domain) {
        if (substr($email, -strlen($domain)) === $domain) {
            return true;
        }
    }
    return false;
}

function slug_email_from_name(string $name): string
{
    $base = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
    $base = strtolower($base);
    $base = preg_replace('/[^a-z0-9]+/', '.', $base);
    $base = trim((string) $base, '.');
    return ($base ?: 'colaborador') . CORPORATE_DOMAIN;
}

function corporate_domains(): array
{
    return ['@italianofacil.com', '@italianofacil.com.br', '@italiafacil.com', '@italiafacil.com.br'];
}

function allowed_email_domains_text(): string
{
    return '@italianofacil.com, @italianofacil.com.br, @italiafacil.com ou @italiafacil.com.br';
}

function sector_options(): array
{
    return [
        'comercial' => 'Comercial',
        'suporte' => 'Suporte',
        'tecnologia' => 'Tecnologia',
        'marketing' => 'Marketing',
        'cuidado_bem_estar' => 'Cuidado e Bem-estar',
        'rh' => 'RH',
        'financeiro' => 'Financeiro',
        'estagiario_aprendiz' => 'Estagiário/Aprendiz',
        'administrativo' => 'Administrativo',
        'lideranca' => 'Liderança',
    ];
}

function normalize_sector(string $sector, string $role = 'user'): string
{
    $sector = strtolower(trim($sector));
    $sector = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $sector) ?: $sector;
    $sector = preg_replace('/[^a-z0-9]+/', '_', $sector);
    $sector = trim((string) $sector, '_');

    $aliases = [
        'lideranca' => 'lideranca',
        'lideran_a' => 'lideranca',
        'rh' => 'rh',
        'recursos_humanos' => 'rh',
        'cuidado_e_bem_estar' => 'cuidado_bem_estar',
        'cuidado_bem_estar' => 'cuidado_bem_estar',
        'bem_estar' => 'cuidado_bem_estar',
        'estagiario' => 'estagiario_aprendiz',
        'aprendiz' => 'estagiario_aprendiz',
        'estagiario_aprendiz' => 'estagiario_aprendiz',
    ];

    if (isset($aliases[$sector])) {
        $sector = $aliases[$sector];
    }

    $allowed = array_keys(sector_options());
    if (!in_array($sector, $allowed, true)) {
        return 'administrativo';
    }

    return $sector;
}

function sector_label(?string $sector): string
{
    $sector = strtolower(trim((string) $sector));
    $sector = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $sector) ?: $sector;
    $sector = preg_replace('/[^a-z0-9]+/', '_', $sector);
    $sector = trim((string) $sector, '_');
    return sector_options()[$sector] ?? 'Administrativo';
}

function monthly_coin_allowance(array $user): int
{
    $sector = normalize_sector((string) ($user['sector'] ?? 'administrativo'), (string) ($user['role'] ?? 'user'));

    if ($sector === 'lideranca') {
        return 10;
    }

    if ($sector === 'comercial') {
        return 0;
    }

    if ($sector === 'estagiario_aprendiz') {
        return 2;
    }

    return 5;
}

function column_exists(string $table, string $column): bool
{
    $stmt = db()->query('PRAGMA table_info(' . $table . ')');
    foreach ($stmt->fetchAll() as $row) {
        if (($row['name'] ?? '') === $column) {
            return true;
        }
    }
    return false;
}

function setting_get(string $key): ?string
{
    $stmt = db()->prepare('SELECT value FROM app_settings WHERE key = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : (string) $value;
}

function setting_set(string $key, string $value): void
{
    db()->prepare('INSERT INTO app_settings (key, value, updated_at) VALUES (?, ?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at')
        ->execute([$key, $value, now()]);
}

function run_monthly_recharge_if_needed(): void
{
    if (date('d') !== '01') {
        return;
    }

    $monthKey = date('Y-m');
    if (setting_get('last_monthly_recharge') === $monthKey) {
        return;
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $users = $pdo->query('SELECT id, role, sector FROM users WHERE active = 1')->fetchAll();
        foreach ($users as $row) {
            $newBalance = monthly_coin_allowance($row);
            $pdo->prepare('UPDATE users SET balance = ?, updated_at = ? WHERE id = ?')
                ->execute([$newBalance, now(), (int) $row['id']]);
            $pdo->prepare('INSERT INTO credit_logs (user_id, admin_id, amount, reason, created_at) VALUES (?, NULL, ?, ?, ?)')
                ->execute([(int) $row['id'], $newBalance, 'Recarga mensal automática de ' . date('m/Y'), now()]);
        }
        $pdo->prepare('INSERT INTO app_settings (key, value, updated_at) VALUES (?, ?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at')
            ->execute(['last_monthly_recharge', $monthKey, now()]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND active = 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function require_admin(): array
{
    $user = current_user();
    if (!$user || $user['role'] !== 'admin') {
        flash('Entre como administrador para acessar essa área.', 'error');
        redirect_to('painel.php');
    }
    return $user;
}

function relative_referrer(string $fallback): string
{
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    if ($referrer === '') {
        return $fallback;
    }

    $path = parse_url($referrer, PHP_URL_PATH) ?: 'index.php';
    $query = parse_url($referrer, PHP_URL_QUERY);
    $target = basename($path) ?: 'index.php';
    if ($target !== 'painel.php') {
        return $fallback;
    }
    return $query ? $target . '?' . $query : $target;
}

function image_for_product(array $product): string
{
    if (!empty($product['image_url'])) {
        return $product['image_url'];
    }

    return LOGO_URL;
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
        throw new RuntimeException('Envio de email não configurado.');
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
        $toName = $toName !== '' ? '=?UTF-8?B?' . base64_encode($toName) . '?= ' : '';
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $fromName . ' <' . $config['from_email'] . '>',
            'To: ' . $toName . '<' . $toEmail . '>',
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

function email_shell(string $pretitle, string $title, string $subtitle, string $bodyHtml, string $accent = '#0b7a42', bool $showCoinImage = true): string
{
    $image = e(COIN_WIN_IMAGE_URL);
    $imageBlock = $showCoinImage ? '
        <div style="padding:0 28px 26px;text-align:center;">
            <img src="' . $image . '" alt="IF Coins" style="display:block;width:100%;max-width:320px;margin:0 auto;border-radius:26px;box-shadow:0 22px 48px rgba(8,95,50,.22);border:1px solid rgba(255,255,255,.85);">
        </div>' : '';

    return '
    <div style="margin:0;padding:0;background:#f5f2e7;font-family:Arial,Helvetica,sans-serif;color:#132019;">
        <div style="max-width:680px;margin:0 auto;padding:30px 16px;">
            <div style="border-radius:30px;overflow:hidden;background:#fffaf0;border:1px solid #eadfbe;box-shadow:0 26px 70px rgba(21,42,29,.16);">
                <div style="padding:30px 28px 24px;background:linear-gradient(135deg,#082f1d 0%,' . $accent . ' 52%,#d8a83e 100%);color:#ffffff;">
                    <div style="font-size:12px;letter-spacing:.18em;text-transform:uppercase;font-weight:900;opacity:.88;">' . $pretitle . '</div>
                    <h1 style="margin:10px 0 10px;font-size:34px;line-height:1.05;font-weight:900;">' . $title . '</h1>
                    <p style="margin:0;font-size:16px;line-height:1.5;color:rgba(255,255,255,.88);">' . $subtitle . '</p>
                </div>
                ' . $imageBlock . '
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

function send_ifcoins_email(array $recipient, int $amount, string $reason, int $newBalance, string $context = 'reward'): void
{
    if ($amount <= 0 && $context !== 'initial') {
        return;
    }

    $safeName = e((string) $recipient['name']);
    $safeReason = e($reason !== '' ? $reason : 'Reconhecimento interno');
    $safeCode = e((string) $recipient['code']);
    $safeUrl = e(MERCADINHO_URL);

    $amountText = $amount . ' IF Coin' . ($amount === 1 ? '' : 's');

    if ($context === 'initial') {
        $subject = 'Seu acesso ao Mercadinho IF foi liberado';
        $emailTitle = 'Acesso liberado';
        $subtitle = 'Seu código já está pronto para entrar no Mercadinho IF.';

        $text = "Oi, {$recipient['name']}!\n\n"
            . "Seu acesso ao Mercadinho IF foi criado.\n"
            . "Seu código: {$recipient['code']}\n"
            . "Saldo inicial: {$newBalance} IF Coins\n"
            . "Acesse: " . MERCADINHO_URL . "\n\n"
            . "Use seu código para entrar no mercadinho e acompanhar suas recompensas.";

        $bodyHtml = '
            <div style="text-align:center;margin:0 0 22px;">
                <div style="display:inline-block;background:#132019;color:#f4d35e;border:1px solid #d6ad38;padding:9px 15px;border-radius:999px;font-size:12px;font-weight:900;letter-spacing:.16em;text-transform:uppercase;">
                    ACESSO LIBERADO
                </div>
            </div>

            <div style="text-align:center;margin:0 0 24px;">
                <img src="https://i.imgur.com/c7U4Mud.png" alt="IF Coins" style="width:120px;max-width:120px;height:auto;display:block;margin:0 auto 14px;">
                <h2 style="margin:0;color:#132019;font-size:26px;line-height:1.15;font-weight:900;">
                    Bem-vindo ao Mercadinho IF
                </h2>
                <p style="margin:10px 0 0;color:#526057;font-size:16px;line-height:1.5;">
                    Oi, <strong>' . $safeName . '</strong>! Sua conta foi criada e seu código já está pronto.
                </p>
            </div>

            <div style="background:#eef7ed;border:1px solid #d7ead3;border-radius:20px;padding:20px;text-align:center;margin:20px 0;">
                <div style="font-size:12px;color:#617066;font-weight:900;text-transform:uppercase;letter-spacing:.13em;margin-bottom:8px;">
                    Seu código de acesso
                </div>
                <div style="font-size:28px;letter-spacing:.22em;font-weight:900;color:#0b7a42;">
                    ' . $safeCode . '
                </div>
            </div>

            <div style="background:#f8f5ea;border:1px solid #eadfbe;border-radius:18px;padding:14px 16px;text-align:center;margin:18px 0;">
                <div style="font-size:14px;color:#132019;font-weight:800;">
                    Saldo inicial: ' . $newBalance . ' IF Coins
                </div>
            </div>

            <div style="text-align:center;margin:26px 0 10px;">
                <a href="' . $safeUrl . '" style="display:inline-block;background:#0f8f4f;color:#ffffff;text-decoration:none;padding:15px 24px;border-radius:14px;font-weight:900;font-size:15px;box-shadow:0 10px 24px rgba(15,143,79,.25);">
                    Entrar no Mercadinho IF
                </a>
            </div>

            <p style="font-size:13px;line-height:1.5;color:#7a837c;text-align:center;margin:16px 0 0;">
                Guarde seu código. Ele será usado para acessar o mercadinho e resgatar itens disponíveis.
            </p>
        ';
    } else {
        $isDailyGoal = $context === 'daily_goal';

        $subject = $isDailyGoal
            ? 'Meta diária batida: +' . $amountText
            : 'Você recebeu +' . $amountText;

        $emailTitle = $isDailyGoal ? 'Meta batida!' : 'Coins adicionados';
        $subtitle = $isDailyGoal
            ? 'Recompensa liberada e IF Coins caindo na conta.'
            : 'Seu saldo foi atualizado no Mercadinho IF.';

        $badge = $isDailyGoal ? 'META BATIDA' : 'COINS ADICIONADOS';
        $headline = $isDailyGoal ? '1... 2... 3... Bravo!' : 'Seu saldo foi atualizado';

        $text = ($isDailyGoal ? "1... 2... 3... Bravo!\n\n" : "")
            . "Oi, {$recipient['name']}!\n"
            . "Você recebeu +{$amountText} no Mercadinho IF.\n"
            . "Motivo: {$reason}\n"
            . "Saldo atual: {$newBalance} IF Coins\n"
            . "Seu código: {$recipient['code']}\n"
            . "Acesse: " . MERCADINHO_URL . "\n\n"
            . "Use seu código no Mercadinho IF e escolha seus itens disponíveis.";

        $bodyHtml = '
            <div style="text-align:center;margin:0 0 22px;">
                <div style="display:inline-block;background:#132019;color:#f4d35e;border:1px solid #d6ad38;padding:9px 15px;border-radius:999px;font-size:12px;font-weight:900;letter-spacing:.16em;text-transform:uppercase;">
                    ' . $badge . '
                </div>
            </div>

            <div style="text-align:center;margin:0 0 24px;">
                <img src="https://i.imgur.com/c7U4Mud.png" alt="IF Coins" style="width:120px;max-width:120px;height:auto;display:block;margin:0 auto 14px;">
                <h2 style="margin:0;color:#132019;font-size:26px;line-height:1.15;font-weight:900;">
                    ' . $headline . '
                </h2>
                <p style="margin:10px 0 0;color:#526057;font-size:16px;line-height:1.5;">
                    Oi, <strong>' . $safeName . '</strong>! Você recebeu novas moedas no Mercadinho IF.
                </p>
            </div>

            <div style="background:linear-gradient(180deg,#fff8df,#f4edcf);border:1px solid #e5d49a;border-radius:24px;padding:24px;text-align:center;margin:22px 0;box-shadow:0 18px 42px rgba(19,32,25,.10);">
                <div style="font-size:13px;color:#71632d;font-weight:900;text-transform:uppercase;letter-spacing:.14em;">
                    IF Coins recebidos
                </div>

                <div style="font-size:64px;font-weight:900;color:#0b7a42;line-height:1;margin:10px 0;text-shadow:0 8px 24px rgba(11,122,66,.18);">
                    +' . $amount . '
                </div>

                <div style="font-size:15px;color:#132019;font-weight:800;">
                    Saldo atual: ' . $newBalance . ' IF Coins
                </div>
            </div>

            <div style="background:#eef7ed;border:1px solid #d7ead3;border-radius:18px;padding:16px;text-align:center;margin:18px 0;">
                <div style="font-size:12px;color:#617066;font-weight:900;text-transform:uppercase;letter-spacing:.13em;margin-bottom:8px;">
                    Seu código de acesso
                </div>
                <div style="font-size:24px;letter-spacing:.22em;font-weight:900;color:#0b7a42;">
                    ' . $safeCode . '
                </div>
            </div>

            <div style="background:#f8f5ea;border:1px solid #eadfbe;border-radius:18px;padding:14px 16px;text-align:center;margin:18px 0;">
                <div style="font-size:13px;color:#687369;line-height:1.45;">
                    <strong>Motivo:</strong> ' . $safeReason . '
                </div>
            </div>

            <div style="text-align:center;margin:26px 0 10px;">
                <a href="' . $safeUrl . '" style="display:inline-block;background:#0f8f4f;color:#ffffff;text-decoration:none;padding:15px 24px;border-radius:14px;font-weight:900;font-size:15px;box-shadow:0 10px 24px rgba(15,143,79,.25);">
                    Entrar no Mercadinho IF
                </a>
            </div>
        ';
    }

    $html = email_shell('IF Coins • ' . $emailTitle, $emailTitle, $subtitle, $bodyHtml, '#0b7a42', true);

    smtp_send((string) $recipient['email'], (string) $recipient['name'], $subject, $html, $text);
}

function send_ifcoins_donation_email(array $recipient, array $donor, int $amount, string $message, int $newBalance): void
{
    $safeRecipient = e($recipient['name']);
    $safeDonor = e($donor['name']);
    $safeMessage = e($message !== '' ? $message : 'Doação de IF Coins');
    $code = e($recipient['code']);
    $amountText = $amount . ' IF Coin' . ($amount === 1 ? '' : 's');
    $subject = $donor['name'] . ' te enviou +' . $amount . ' IF Coin' . ($amount === 1 ? '' : 's');
    $text = "1... 2... 3... Bravo!\n\nOi, {$recipient['name']}!\n{$donor['name']} acabou de te enviar {$amountText}.\nMensagem: {$message}\nSaldo atual: {$newBalance} IF Coins\nSeu código: {$recipient['code']}\n\nUse seu código no Mercadinho IF e escolha seus itens disponíveis.";

    $bodyHtml = '
        <div style="text-align:center;margin:-4px 0 24px;">
            <div style="display:inline-block;padding:10px 16px;border-radius:999px;background:#132019;color:#fff;font-size:16px;font-weight:900;letter-spacing:.04em;box-shadow:0 12px 28px rgba(19,32,25,.18);">1... 2... 3... Bravo!</div>
        </div>
        <p style="font-size:17px;line-height:1.55;margin:0 0 18px;">Oi, <strong>' . $safeRecipient . '</strong>! <strong>' . $safeDonor . '</strong> acabou de enviar IF Coins para você.</p>
        <div style="background:linear-gradient(180deg,#ffffff,#f7f2df);border:1px solid #eadfbe;border-radius:24px;padding:24px;text-align:center;margin:22px 0;box-shadow:inset 0 0 0 1px rgba(255,255,255,.8);">
            <div style="font-size:13px;color:#6b6f62;font-weight:900;text-transform:uppercase;letter-spacing:.14em;">Coins recebidos</div>
            <div style="font-size:62px;font-weight:900;color:#0b7a42;line-height:1;margin:8px 0;text-shadow:0 8px 24px rgba(11,122,66,.16);">+' . $amount . '</div>
            <div style="font-size:16px;color:#132019;font-weight:900;">Saldo atual: ' . $newBalance . ' IF Coins</div>
        </div>
        <div style="display:grid;gap:12px;margin:20px 0;">
            <div style="padding:15px 16px;border-radius:18px;background:#f8f5ea;border:1px solid #eadfbe;"><strong>Mensagem:</strong> ' . $safeMessage . '</div>
            <div style="padding:15px 16px;border-radius:18px;background:#eef7ed;border:1px solid #d8ead6;"><strong>Seu código:</strong> <span style="letter-spacing:.18em;font-weight:900;color:#0b7a42;">' . $code . '</span></div>
        </div>
        <p style="font-size:15px;line-height:1.55;color:#687369;margin:0;">Use seu código no Mercadinho IF e aproveite sua nova recompensa.</p>';

    $html = email_shell('IF Coins • Presente recebido', 'Você recebeu uma doação!', 'Alguém da equipe mandou coins para você continuar jogando.', $bodyHtml, '#0b7a42', true);
    smtp_send((string) $recipient['email'], (string) $recipient['name'], $subject, $html, $text);
}

function schema(): void
{
    db()->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'user',
            sector TEXT NOT NULL DEFAULT 'administrativo',
            code TEXT NOT NULL UNIQUE,
            balance INTEGER NOT NULL DEFAULT 0,
            active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT,
            image_url TEXT,
            stock INTEGER NOT NULL DEFAULT 0,
            cost INTEGER NOT NULL DEFAULT 1,
            active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            product_id INTEGER NOT NULL,
            user_name TEXT NOT NULL,
            product_name TEXT NOT NULL,
            code_used TEXT NOT NULL,
            cost INTEGER NOT NULL,
            created_at TEXT NOT NULL,
            FOREIGN KEY(user_id) REFERENCES users(id),
            FOREIGN KEY(product_id) REFERENCES products(id)
        );

        CREATE TABLE IF NOT EXISTS credit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            admin_id INTEGER,
            amount INTEGER NOT NULL,
            reason TEXT NOT NULL,
            created_at TEXT NOT NULL,
            FOREIGN KEY(user_id) REFERENCES users(id),
            FOREIGN KEY(admin_id) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS donation_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            donor_id INTEGER NOT NULL,
            recipient_id INTEGER NOT NULL,
            donor_name TEXT NOT NULL,
            recipient_name TEXT NOT NULL,
            recipient_code TEXT NOT NULL,
            amount INTEGER NOT NULL,
            message TEXT,
            email_status TEXT NOT NULL DEFAULT 'pending',
            created_at TEXT NOT NULL,
            FOREIGN KEY(donor_id) REFERENCES users(id),
            FOREIGN KEY(recipient_id) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS app_settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL,
            updated_at TEXT NOT NULL
        );
    ");

    if (!column_exists('users', 'sector')) {
        db()->exec("ALTER TABLE users ADD COLUMN sector TEXT NOT NULL DEFAULT 'administrativo'");
    }
}


function create_user_seed(string $name, int $balance, string $role = 'user', string $sector = 'administrativo'): void
{
    $role = $role === 'admin' ? 'admin' : 'user';
    $sector = normalize_sector($sector, $role);
    $email = slug_email_from_name($name);
    $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ((int) $stmt->fetchColumn() > 0) {
        return;
    }

    $stmt = db()->prepare('
        INSERT INTO users (name, email, password_hash, role, sector, code, balance, active, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)
    ');
    $stmt->execute([
        $name,
        $email,
        password_hash('if123456', PASSWORD_DEFAULT),
        $role,
        $sector,
        generate_code(),
        $balance,
        now(),
        now(),
    ]);
}


function create_product_seed(string $name, int $stock): void
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM products WHERE name = ?');
    $stmt->execute([$name]);
    if ((int) $stmt->fetchColumn() > 0) {
        return;
    }

    $stmt = db()->prepare('
        INSERT INTO products (name, description, image_url, stock, cost, active, created_at, updated_at)
        VALUES (?, ?, NULL, ?, 1, 1, ?, ?)
    ');
    $stmt->execute([$name, 'Item importado da planilha inicial do IF Coins.', $stock, now(), now()]);
}

function seed_if_needed(): void
{
    $count = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count === 0) {
        create_user_seed('Administrador IF', 10, 'admin', 'lideranca');
        db()->prepare('UPDATE users SET email = ?, password_hash = ?, role = ?, sector = ?, balance = ? WHERE name = ?')
            ->execute([
                'admin' . CORPORATE_DOMAIN,
                password_hash('admin123', PASSWORD_DEFAULT),
                'admin',
                'lideranca',
                10,
                'Administrador IF',
            ]);
    }

    $productCount = (int) db()->query('SELECT COUNT(*) FROM products')->fetchColumn();
    if ($productCount === 0) {
        $products = [
            ['Heineken latão', 6], ['Stella Artois', 1], ['Stella Puro Gold', 0],
            ['Bali vermelha', 4], ['Monster rosa', 3], ['Monster', 3], ['Bali verde', 0],
            ['Corona', 6], ['Corona zero', 8], ['Kit Kat', 24], ['Bis', 24],
            ['Snickers', 10], ['Bala Fini', 3], ['Yopro', 6], ['Blue Moon', 6],
            ['Coca zero', 9], ['Água com gás', 8],
        ];
        foreach ($products as [$name, $stock]) {
            create_product_seed($name, $stock);
        }
    }
}


function upload_image(?array $file, string $imageUrl): string
{
    $imageUrl = trim($imageUrl);
    if ($file && isset($file['tmp_name']) && is_uploaded_file($file['tmp_name'])) {
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        $mime = mime_content_type($file['tmp_name']);
        if (!isset($allowed[$mime])) {
            throw new RuntimeException('Envie uma imagem JPG, PNG, WEBP ou GIF.');
        }

        $filename = 'produto-' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
        $target = UPLOAD_DIR . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new RuntimeException('Não foi possível salvar a imagem.');
        }
        return 'uploads/' . $filename;
    }

    return $imageUrl;
}

function handle_post(): void
{
    $action = $_POST['action'] ?? '';
    verify_csrf();

    try {
        if ($action === 'login') {
            $email = normalize_email($_POST['email'] ?? '');
            $password = (string) ($_POST['password'] ?? '');

            $stmt = db()->prepare('SELECT * FROM users WHERE email = ? AND active = 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                flash('Email ou senha incorretos.', 'error');
                redirect_to('painel.php');
            }

            $_SESSION['user_id'] = (int) $user['id'];
            flash('Login realizado com sucesso.');
            redirect_to($user['role'] === 'admin' ? 'painel.php?tab=overview' : 'painel.php?page=conta');
        }

        if ($action === 'logout') {
            session_destroy();
            session_start();
            flash('Você saiu da conta.');
            redirect_to('painel.php');
        }

        if ($action === 'checkout') {
            $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
            $productId = (int) ($_POST['product_id'] ?? 0);

            $pdo = db();
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT * FROM users WHERE code = ? AND active = 1');
            $stmt->execute([$code]);
            $codeUser = $stmt->fetch();

            $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? AND active = 1');
            $stmt->execute([$productId]);
            $product = $stmt->fetch();

            if (!$codeUser) {
                throw new RuntimeException('Código não encontrado ou inativo.');
            }
            if (!$product) {
                throw new RuntimeException('Produto não encontrado.');
            }
            if ((int) $product['stock'] <= 0) {
                throw new RuntimeException('Esse item está sem estoque.');
            }
            if ((int) $codeUser['balance'] < (int) $product['cost']) {
                throw new RuntimeException('Saldo insuficiente para retirar esse item.');
            }

            $pdo->prepare('UPDATE users SET balance = balance - ?, updated_at = ? WHERE id = ?')
                ->execute([(int) $product['cost'], now(), (int) $codeUser['id']]);
            $pdo->prepare('UPDATE products SET stock = stock - 1, updated_at = ? WHERE id = ?')
                ->execute([now(), (int) $product['id']]);
            $pdo->prepare('
                INSERT INTO transactions (user_id, product_id, user_name, product_name, code_used, cost, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ')->execute([
                (int) $codeUser['id'],
                (int) $product['id'],
                $codeUser['name'],
                $product['name'],
                $code,
                (int) $product['cost'],
                now(),
            ]);

            $pdo->commit();
            flash('Resgate confirmado! Seus IF Coins e o estoque já foram atualizados.');
            redirect_to('index.php?page=shop&picked=1&code=' . urlencode($code));
        }

        if ($action === 'donate_coins') {
            $donor = current_user();
            if (!$donor) {
                throw new RuntimeException('Entre na sua conta para doar IF Coins.');
            }

            $recipientCode = strtoupper(trim((string) ($_POST['recipient_code'] ?? '')));
            $amount = max(0, (int) ($_POST['amount'] ?? 0));
            $message = trim((string) ($_POST['message'] ?? 'Doação de IF Coins'));

            if (!preg_match('/^[A-Z0-9]{5}$/', $recipientCode)) {
                throw new RuntimeException('Informe um código válido com 5 caracteres.');
            }
            if ($amount <= 0) {
                throw new RuntimeException('Informe uma quantidade de IF Coins maior que zero.');
            }
            if ((function_exists('mb_strlen') ? mb_strlen($message) : strlen($message)) > 180) {
                throw new RuntimeException('A mensagem da doação pode ter no máximo 180 caracteres.');
            }

            $pdo = db();
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND active = 1');
            $stmt->execute([(int) $donor['id']]);
            $donorFresh = $stmt->fetch();

            $stmt = $pdo->prepare('SELECT * FROM users WHERE code = ? AND active = 1');
            $stmt->execute([$recipientCode]);
            $recipient = $stmt->fetch();

            if (!$donorFresh) {
                throw new RuntimeException('Sua conta não está ativa para doar IF Coins.');
            }
            if (!$recipient) {
                throw new RuntimeException('Código de destino não encontrado ou inativo.');
            }
            if ((int) $recipient['id'] === (int) $donorFresh['id']) {
                throw new RuntimeException('Você não pode doar IF Coins para o próprio código.');
            }
            if ((int) $donorFresh['balance'] < $amount) {
                throw new RuntimeException('Saldo insuficiente para essa doação.');
            }

            $pdo->prepare('UPDATE users SET balance = balance - ?, updated_at = ? WHERE id = ?')
                ->execute([$amount, now(), (int) $donorFresh['id']]);
            $pdo->prepare('UPDATE users SET balance = balance + ?, updated_at = ? WHERE id = ?')
                ->execute([$amount, now(), (int) $recipient['id']]);
            $pdo->prepare('
                INSERT INTO donation_logs (donor_id, recipient_id, donor_name, recipient_name, recipient_code, amount, message, email_status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ')->execute([
                (int) $donorFresh['id'],
                (int) $recipient['id'],
                $donorFresh['name'],
                $recipient['name'],
                $recipient['code'],
                $amount,
                $message,
                'pending',
                now(),
            ]);
            $donationId = (int) $pdo->lastInsertId();
            $newRecipientBalance = (int) $recipient['balance'] + $amount;
            $pdo->commit();

            $mailNote = '';
            try {
                send_ifcoins_donation_email($recipient, $donorFresh, $amount, $message, $newRecipientBalance);
                db()->prepare('UPDATE donation_logs SET email_status = ? WHERE id = ?')->execute(['sent', $donationId]);
                $mailNote = ' A pessoa recebeu a notificação por email.';
            } catch (Throwable $mailError) {
                db()->prepare('UPDATE donation_logs SET email_status = ? WHERE id = ?')->execute(['failed: ' . $mailError->getMessage(), $donationId]);
                $mailNote = ' A doação foi concluída, mas o email não foi enviado: ' . $mailError->getMessage();
            }

            flash('Doação enviada com sucesso.' . $mailNote);
            redirect_to('painel.php?page=conta&donated=1');
        }

        $admin = require_admin();

        if ($action === 'create_user') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = normalize_email($_POST['email'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
            $sector = normalize_sector((string) ($_POST['sector'] ?? 'administrativo'), $role);
            $balance = monthly_coin_allowance(['role' => $role, 'sector' => $sector]);

            if ($name === '' || $email === '' || $password === '') {
                throw new RuntimeException('Preencha nome, email e senha.');
            }
            if (!is_corporate_email($email)) {
                throw new RuntimeException('Use um email corporativo terminado em ' . allowed_email_domains_text() . '.');
            }

            $stmt = db()->prepare('SELECT active FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $existingActive = $stmt->fetchColumn();
            if ($existingActive !== false) {
                if ((int) $existingActive === 1) {
                    throw new RuntimeException('Já existe usuário ativo cadastrado com esse email.');
                }
                throw new RuntimeException('Já existe usuário inativo cadastrado com esse email. Exclua definitivamente esse usuário na lista antes de cadastrar de novo.');
            }

            $stmt = db()->prepare('
                INSERT INTO users (name, email, password_hash, role, sector, code, balance, active, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)
            ');
            $stmt->execute([
                $name,
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                $role,
                $sector,
                generate_code(),
                $balance,
                now(),
                now(),
            ]);
            $newUserId = (int) db()->lastInsertId();
            $mailNote = '';
            if ($balance > 0 && function_exists('send_ifcoins_email')) {
                try {
                    $createdUser = find_user($newUserId);
                    if ($createdUser) {
                        send_ifcoins_email($createdUser, $balance, 'Saldo inicial no Mercadinho IF', $balance, 'initial');
                        $mailNote = ' Email de IF Coins enviado.';
                    }
                } catch (Throwable $mailError) {
                    $mailNote = ' O usuário foi criado, mas o email não foi enviado: ' . $mailError->getMessage();
                }
            }
            flash('Usuário cadastrado com código automático.' . $mailNote);
            redirect_to('painel.php?tab=users&edit_user=' . $newUserId);
        }

        if ($action === 'update_user') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = normalize_email($_POST['email'] ?? '');
            $balance = max(0, (int) ($_POST['balance'] ?? 0));
            $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
            $sector = normalize_sector((string) ($_POST['sector'] ?? 'administrativo'), $role);
            $active = isset($_POST['active']) ? 1 : 0;
            $password = (string) ($_POST['password'] ?? '');

            if ($id <= 0 || $name === '' || $email === '') {
                throw new RuntimeException('Dados inválidos para atualizar usuário.');
            }
            if (!is_corporate_email($email)) {
                throw new RuntimeException('Use um email corporativo terminado em ' . allowed_email_domains_text() . '.');
            }

            $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?');
            $stmt->execute([$email, $id]);
            if ((int) $stmt->fetchColumn() > 0) {
                throw new RuntimeException('Outro usuário já está usando esse email.');
            }

            $fields = 'name = ?, email = ?, role = ?, sector = ?, balance = ?, active = ?, updated_at = ?';
            $params = [$name, $email, $role, $sector, $balance, $active, now()];
            if ($password !== '') {
                $fields .= ', password_hash = ?';
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }
            if (isset($_POST['regenerate_code'])) {
                $fields .= ', code = ?';
                $params[] = generate_code();
            }
            $params[] = $id;

            db()->prepare("UPDATE users SET {$fields} WHERE id = ?")->execute($params);
            flash('Usuário atualizado.');
            redirect_to('painel.php?tab=users&edit_user=' . $id);
        }

        if ($action === 'delete_user') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Usuário inválido para exclusão.');
            }
            if ($id === (int) $admin['id']) {
                throw new RuntimeException('Você não pode excluir o próprio usuário logado.');
            }

            $stmt = db()->prepare('SELECT id, name FROM users WHERE id = ?');
            $stmt->execute([$id]);
            $targetUser = $stmt->fetch();
            if (!$targetUser) {
                throw new RuntimeException('Usuário não encontrado.');
            }

            $pdo = db();
            $pdo->beginTransaction();

            $pdo->prepare('DELETE FROM donation_logs WHERE donor_id = ? OR recipient_id = ?')
                ->execute([$id, $id]);
            $pdo->prepare('DELETE FROM credit_logs WHERE user_id = ? OR admin_id = ?')
                ->execute([$id, $id]);
            $pdo->prepare('DELETE FROM transactions WHERE user_id = ?')
                ->execute([$id]);
            $pdo->prepare('DELETE FROM users WHERE id = ?')
                ->execute([$id]);

            $pdo->commit();

            flash('Usuário excluído definitivamente.');
            redirect_to('painel.php?tab=users');
        }

        if ($action === 'adjust_credit') {
            $id = (int) ($_POST['id'] ?? 0);
            $amount = (int) ($_POST['amount'] ?? 0);
            $reason = trim((string) ($_POST['reason'] ?? 'Ajuste manual'));
            if ($id <= 0 || $amount === 0) {
                throw new RuntimeException('Informe um ajuste diferente de zero.');
            }

            $stmt = db()->prepare('SELECT balance FROM users WHERE id = ?');
            $stmt->execute([$id]);
            $balance = $stmt->fetchColumn();
            if ($balance === false || ((int) $balance + $amount) < 0) {
                throw new RuntimeException('Ajuste inválido. O saldo não pode ficar negativo.');
            }

            db()->prepare('UPDATE users SET balance = balance + ?, updated_at = ? WHERE id = ?')
                ->execute([$amount, now(), $id]);
            db()->prepare('INSERT INTO credit_logs (user_id, admin_id, amount, reason, created_at) VALUES (?, ?, ?, ?, ?)')
                ->execute([$id, (int) $admin['id'], $amount, $reason, now()]);
            $newBalance = (int) $balance + $amount;
            $mailNote = '';
            if ($amount > 0) {
                try {
                    $recipient = find_user($id);
                    if ($recipient) {
                        send_ifcoins_email($recipient, $amount, $reason, $newBalance, 'reward');
                        $mailNote = ' Email de IF Coins enviado.';
                    }
                } catch (Throwable $mailError) {
                    $mailNote = ' Os IF Coins foram adicionados, mas o email não foi enviado: ' . $mailError->getMessage();
                }
            }
            flash('IF Coins atualizados.' . $mailNote);
            redirect_to('painel.php?tab=users&edit_user=' . $id);
        }

        if ($action === 'daily_goal_reward') {
            $id = (int) ($_POST['user_id'] ?? 0);
            $amount = max(0, (int) ($_POST['amount'] ?? 0));
            $reason = trim((string) ($_POST['reason'] ?? 'Meta diária batida'));

            if ($id <= 0) {
                throw new RuntimeException('Escolha quem bateu a meta diária.');
            }
            if ($amount <= 0) {
                throw new RuntimeException('Informe uma quantidade de IF Coins maior que zero.');
            }
            if ($reason === '') {
                throw new RuntimeException('Informe o motivo da premiação.');
            }
            if ((function_exists('mb_strlen') ? mb_strlen($reason) : strlen($reason)) > 160) {
                throw new RuntimeException('O motivo pode ter no máximo 160 caracteres.');
            }

            $stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND active = 1');
            $stmt->execute([$id]);
            $recipient = $stmt->fetch();
            if (!$recipient) {
                throw new RuntimeException('Pessoa não encontrada ou inativa.');
            }

            db()->prepare('UPDATE users SET balance = balance + ?, updated_at = ? WHERE id = ?')
                ->execute([$amount, now(), $id]);
            db()->prepare('INSERT INTO credit_logs (user_id, admin_id, amount, reason, created_at) VALUES (?, ?, ?, ?, ?)')
                ->execute([$id, (int) $admin['id'], $amount, 'Meta diária: ' . $reason, now()]);

            $newBalance = (int) $recipient['balance'] + $amount;
            $mailNote = '';
            try {
                send_ifcoins_email($recipient, $amount, $reason, $newBalance, 'daily_goal');
                $mailNote = ' Email bonito de meta diária enviado.';
            } catch (Throwable $mailError) {
                $mailNote = ' Os IF Coins foram adicionados, mas o email não foi enviado: ' . $mailError->getMessage();
            }

            flash('Meta diária premiada com sucesso.' . $mailNote);
            redirect_to('painel.php?tab=users&rewarded=1');
        }

        if ($action === 'create_product') {
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '') {
                throw new RuntimeException('Informe o nome do produto.');
            }
            $image = upload_image($_FILES['image'] ?? null, (string) ($_POST['image_url'] ?? ''));
            db()->prepare('
                INSERT INTO products (name, description, image_url, stock, cost, active, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 1, ?, ?)
            ')->execute([
                $name,
                trim((string) ($_POST['description'] ?? '')),
                $image,
                max(0, (int) ($_POST['stock'] ?? 0)),
                max(1, (int) ($_POST['cost'] ?? 1)),
                now(),
                now(),
            ]);
            flash('Produto cadastrado.');
            redirect_to('painel.php?tab=products');
        }

        if ($action === 'update_product') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($id <= 0 || $name === '') {
                throw new RuntimeException('Dados inválidos para atualizar produto.');
            }
            $current = db()->prepare('SELECT image_url FROM products WHERE id = ?');
            $current->execute([$id]);
            $currentImage = (string) ($current->fetchColumn() ?: '');
            $image = upload_image($_FILES['image'] ?? null, (string) ($_POST['image_url'] ?? $currentImage));

            db()->prepare('
                UPDATE products
                SET name = ?, description = ?, image_url = ?, stock = ?, cost = ?, active = ?, updated_at = ?
                WHERE id = ?
            ')->execute([
                $name,
                trim((string) ($_POST['description'] ?? '')),
                $image,
                max(0, (int) ($_POST['stock'] ?? 0)),
                max(1, (int) ($_POST['cost'] ?? 1)),
                isset($_POST['active']) ? 1 : 0,
                now(),
                $id,
            ]);
            flash('Produto atualizado.');
            redirect_to('painel.php?tab=products&edit_product=' . $id);
        }

        if ($action === 'remove_product') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Produto inválido.');
            }

            db()->prepare('UPDATE products SET active = 0, updated_at = ? WHERE id = ?')->execute([now(), $id]);
            flash('Produto removido da vitrine. O histórico foi preservado.');
            redirect_to('painel.php?tab=products');
        }
    } catch (Throwable $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        flash($exception->getMessage(), 'error');
        redirect_to(relative_referrer('painel.php?tab=overview'));
    }

    redirect_to('painel.php');
}

function stats(): array
{
    return [
        'active_users' => (int) db()->query("SELECT COUNT(*) FROM users WHERE active = 1 AND role = 'user'")->fetchColumn(),
        'total_balance' => (int) db()->query("SELECT COALESCE(SUM(balance), 0) FROM users WHERE active = 1")->fetchColumn(),
        'active_products' => (int) db()->query("SELECT COUNT(*) FROM products WHERE active = 1")->fetchColumn(),
        'stock' => (int) db()->query("SELECT COALESCE(SUM(stock), 0) FROM products WHERE active = 1")->fetchColumn(),
        'today' => (int) db()->query("SELECT COUNT(*) FROM transactions WHERE date(created_at) = date('now', 'localtime')")->fetchColumn(),
    ];
}

function all_products(bool $onlyAvailable = false): array
{
    $sql = 'SELECT * FROM products WHERE active = 1';
    if ($onlyAvailable) {
        $sql .= ' AND stock > 0';
    }
    $sql .= ' ORDER BY stock <= 0, name';
    return db()->query($sql)->fetchAll();
}

function all_users(): array
{
    return db()->query("SELECT * FROM users ORDER BY role = 'admin' DESC, active DESC, sector, name")->fetchAll();
}

function transactions(int $limit = 30): array
{
    $stmt = db()->prepare('SELECT * FROM transactions ORDER BY id DESC LIMIT ?');
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}


function donations(int $limit = 50): array
{
    $stmt = db()->prepare('SELECT * FROM donation_logs ORDER BY id DESC LIMIT ?');
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function user_donations(int $userId, int $limit = 20): array
{
    $stmt = db()->prepare('
        SELECT * FROM donation_logs
        WHERE donor_id = ? OR recipient_id = ?
        ORDER BY id DESC
        LIMIT ?
    ');
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $userId, PDO::PARAM_INT);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}


function rewardable_users(): array
{
    return db()->query("SELECT * FROM users WHERE active = 1 ORDER BY role = 'admin' DESC, sector, name")->fetchAll();
}


function credit_rewards(int $limit = 80): array
{
    $stmt = db()->prepare('SELECT cl.*, u.name AS user_name FROM credit_logs cl JOIN users u ON u.id = cl.user_id ORDER BY cl.id DESC LIMIT ?');
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function find_code_user(string $code): ?array
{
    if ($code === '') {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE code = ? AND active = 1');
    $stmt->execute([strtoupper($code)]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function find_user(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function find_product(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    return $product ?: null;
}

schema();
seed_if_needed();
run_monthly_recharge_if_needed();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_post();
}

$user = current_user();
$page = $_GET['page'] ?? ($user ? ($user['role'] === 'admin' ? 'admin' : 'conta') : 'login');
$tab = $_GET['tab'] ?? 'overview';
$flash = flash();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?></title>
    <link rel="icon" href="<?= e(LOGO_URL) ?>">
    <link rel="stylesheet" href="assets/styles.css?v=3434-00-users-mobile-card-final">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800;900&display=swap" rel="stylesheet">
</head>
<body data-picked="<?= isset($_GET['picked']) ? '1' : '0' ?>" data-donated="<?= isset($_GET['donated']) ? '1' : '0' ?>" data-rewarded="<?= isset($_GET['rewarded']) ? '1' : '0' ?>" data-flash="<?= e($flash['type'] ?? '') ?>">
<div class="shell">
    <header class="topbar">
            <span class="brand-text">
               <img class="brand-logo-img" src="https://i.imgur.com/xscaQlZ.png" alt="Mercadinho IF">
                <small>Controle interno de IF Coins</small>
            </span>
        </a>
        <button class="mobile-menu-toggle" type="button" id="mobile-menu-toggle" aria-expanded="false" aria-controls="top-nav" aria-label="Abrir menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <nav class="nav" id="top-nav">
            <a href="index.php">Mercadinho</a>
            <?php if ($user && $user['role'] === 'admin'): ?>
                <a href="painel.php?tab=overview">Painel</a>
            <?php endif; ?>
            <button class="sound-toggle" type="button" id="sound-toggle" aria-pressed="true">Som ligado</button>
            <?php if ($user): ?>
                <a href="painel.php?page=conta">Minha conta</a>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="logout">
                    <button class="link-button" type="submit">Sair</button>
                </form>
            <?php endif; ?>
        </nav>
    </header>

    <?php if ($flash): ?>
        <div class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <?php if ($page === 'login'): ?>
        <main class="hero-grid">
            <section class="hero-panel">
                <img class="hero-panel-coin" src="https://i.imgur.com/c7U4Mud.png" alt="IF Coins">
                <div class="eyebrow">Painel interno</div>
                <h1>Organize o mercadinho sem perder tempo.</h1>
                <p>Aqui você acompanha resgates, atualiza o estoque e libera novos códigos para a equipe usar no dia a dia.</p>

                <div class="mini-metrics">
                    <?php $s = stats(); ?>
                    <span><strong><?= $s['active_users'] ?></strong> pessoas ativas</span>
                    <span><strong><?= $s['stock'] ?></strong> itens disponíveis</span>
                    <span><strong><?= $s['today'] ?></strong> resgates hoje</span>
                </div>
            </section>

            <aside class="login-card">
                <h2>Entrar no painel</h2>
                <p>Use seu acesso corporativo para cuidar dos produtos, dos códigos e dos saldos da equipe.</p>
                <form method="post" class="stack">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="login">
                    <label>Email corporativo
                        <input type="email" name="email" placeholder="admin@italianofacil.com" required>
                    </label>
                    <label>Senha
                        <input type="password" name="password" placeholder="Sua senha" required>
                    </label><br>
                    <button type="submit" class="wide">Entrar</button>
                </form>
            </aside>
        </main>
    <?php elseif ($page === 'shop'): ?>
        <?php
        $code = strtoupper(trim((string) ($_GET['code'] ?? '')));
        $codeUser = find_code_user($code);
        ?>
        <main>
            <?php if (!$codeUser): ?>
                <section class="empty-state">
                    <h1>Código não encontrado</h1>
                    <p>Confira os 5 caracteres e tente novamente.</p>
                    <form class="code-form compact" method="get" action="index.php">
                        <input type="hidden" name="page" value="shop">
                        <div class="code-row">
                            <input name="code" maxlength="5" required placeholder="A1B2C" autocomplete="off">
                            <button type="submit">Buscar</button>
                        </div>
                    </form>
                </section>
            <?php else: ?>
                <section class="store-head">
                    <div>
                        <div class="eyebrow">Código validado</div>
                        <h1>Oi, <?= e($codeUser['name']) ?>.</h1>
                        <p>Selecione o produto e confirme o resgate. O sistema desconta automaticamente do seu saldo e do estoque.</p>
                    </div>
                    <div class="balance-card">
                        <span>IF Coins</span>
                        <strong><?= (int) $codeUser['balance'] ?></strong>
                        <small>Código <?= e($codeUser['code']) ?></small>
                    </div>
                </section>

                <section class="product-grid">
                    <?php foreach (all_products(true) as $product): ?>
                        <article class="product-card">
                            <div class="product-image">
                                <img src="<?= e(image_for_product($product)) ?>" alt="<?= e($product['name']) ?>">
                            </div>
                            <div class="product-body">
                                <h3><?= e($product['name']) ?></h3>
                                <p><?= e($product['description'] ?: 'Produto disponível no mercadinho.') ?></p>
                                <div class="product-meta">
                                    <span><?= (int) $product['stock'] ?> em estoque</span>
                                    <span><?= (int) $product['cost'] ?> IF Coin<?= (int) $product['cost'] > 1 ? 's' : '' ?></span>
                                </div>
                                <form method="post" data-confirm="Confirmar resgate de <?= e($product['name']) ?>?">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="checkout">
                                    <input type="hidden" name="code" value="<?= e($codeUser['code']) ?>">
                                    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                    <button type="submit" <?= (int) $codeUser['balance'] < (int) $product['cost'] ? 'disabled' : '' ?>>
                                        Resgatar item
                                    </button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>
        </main>
    <?php elseif ($page === 'conta' && $user): ?>
        <?php $freshUser = current_user() ?: $user; ?>
        <main class="account-layout account-layout-wide">
            <section class="profile-card user-card">
                <div class="eyebrow">Minha conta</div>
                <h1><?= e($freshUser['name']) ?></h1>
                <p><?= e($freshUser['email']) ?></p>
                <div class="code-display"><?= e($freshUser['code']) ?></div>
                <p class="muted">IF Coins disponíveis: <strong><?= (int) $freshUser['balance'] ?></strong></p>
            </section>

            <section class="panel donate-panel">
                <div class="panel-heading">
                    <div>
                        <div class="eyebrow">Doar coins</div>
                        <h2>Enviar IF Coins para alguém da equipe</h2>
                    </div>
                    <span class="badge-soft">Novo</span>
                </div>
                <p>Digite o código da pessoa, escolha a quantidade e envie. O saldo sai da sua conta, entra na conta dela e o email de notificação é disparado automaticamente.</p>
                <form method="post" class="form-grid donation-form" data-confirm="Confirmar doação de IF Coins?">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="donate_coins">
                    <label>Código da pessoa
                        <input name="recipient_code" maxlength="5" required placeholder="A1B2C" autocomplete="off">
                    </label>
                    <label>Quantidade
                        <input type="number" name="amount" min="1" max="<?= (int) $freshUser['balance'] ?>" required placeholder="Ex: 1">
                    </label>
                    <label class="full-span">Mensagem opcional
                        <input name="message" maxlength="180" value="Mandou bem!" placeholder="Ex: Mandou bem na campanha!">
                    </label>
                    <button type="submit" <?= (int) $freshUser['balance'] <= 0 ? 'disabled' : '' ?>>Doar IF Coins</button>
                </form>
            </section>

            <section class="panel">
                <h2>Meus últimos resgates</h2>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Data</th><th>Produto</th><th>Custo</th></tr></thead>
                        <tbody>
                        <?php
                        $stmt = db()->prepare('SELECT * FROM transactions WHERE user_id = ? ORDER BY id DESC LIMIT 20');
                        $stmt->execute([(int) $freshUser['id']]);
                        $myTransactions = $stmt->fetchAll();
                        ?>
                        <?php if (!$myTransactions): ?>
                            <tr><td colspan="3">Nenhum resgate registrado ainda.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($myTransactions as $row): ?>
                            <tr>
                                <td data-label="Data"><?= e(date('d/m/Y H:i', strtotime($row['created_at']))) ?></td>
                                <td data-label="Produto"><?= e($row['product_name']) ?></td>
                                <td data-label="Custo"><?= (int) $row['cost'] ?> IF Coin<?= (int) $row['cost'] > 1 ? 's' : '' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel">
                <h2>Minhas doações</h2>
                <?php render_donations_table(user_donations((int) $freshUser['id'], 20), (int) $freshUser['id']); ?>
            </section>
        </main>
    <?php elseif ($page === 'admin'): ?>
        <?php $admin = require_admin(); $s = stats(); ?>
        <main class="admin-layout" id="admin-layout">
            <aside class="sidebar" id="admin-sidebar">
                <div class="admin-mini">
                    <strong><?= e($admin['name']) ?></strong>
                    <span><?= e($admin['email']) ?></span>
                </div>
                <a class="<?= $tab === 'overview' ? 'active' : '' ?>" href="painel.php?tab=overview"><span class="icon icon-overview" aria-hidden="true"></span><span class="nav-text">Visão geral</span></a>
                <a class="<?= $tab === 'users' ? 'active' : '' ?>" href="painel.php?tab=users"><span class="icon icon-users" aria-hidden="true"></span><span class="nav-text">Usuários</span></a>
                <a class="<?= $tab === 'products' ? 'active' : '' ?>" href="painel.php?tab=products"><span class="icon icon-products" aria-hidden="true"></span><span class="nav-text">Produtos</span></a>
                <a class="<?= $tab === 'history' ? 'active' : '' ?>" href="painel.php?tab=history"><span class="icon icon-history" aria-hidden="true"></span><span class="nav-text">Histórico</span></a>
            </aside>

            <section class="admin-content">
                <?php if ($tab === 'overview'): ?>
                    <div class="admin-title">
                        <div>
                            <div class="eyebrow">Painel administrativo</div>
                            <h1>Mercadinho em tempo real</h1>
                        </div>
                    </div>
                    <div class="stat-grid">
                        <article><span>Usuários ativos</span><strong><?= $s['active_users'] ?></strong></article>
                        <article><span>IF Coins</span><strong><?= $s['total_balance'] ?></strong></article>
                        <article><span>Produtos ativos</span><strong><?= $s['active_products'] ?></strong></article>
                        <article><span>Estoque total</span><strong><?= $s['stock'] ?></strong></article>
                    </div>
                    <div class="panel">
                        <h2>Últimos resgates</h2>
                        <?php render_transactions_table(transactions(12)); ?>
                    </div>
                <?php elseif ($tab === 'users'): ?>
                    <?php $editUser = isset($_GET['edit_user']) ? find_user((int) $_GET['edit_user']) : null; ?>
                    <div class="admin-title">
                        <div>
                            <div class="eyebrow">Usuários</div>
                            <h1>Códigos e saldos</h1>
                        </div>
                    </div>
                    <section class="panel daily-goal-panel">
                        <div class="panel-heading">
                            <div>
                                <div class="eyebrow">Meta diária</div>
                                <h2>Premiação por Meta!</h2>
                            </div>
                            <span class="badge-soft">Bravo</span>
                        </div>
                        <p>Escolha a pessoa, defina a quantidade de IF Coins e escreva o motivo. O sistema adiciona as moedas e envia um email com cara de conquista.</p>
                        <form method="post" class="form-grid daily-goal-form" data-confirm="Adicionar IF Coins por meta diária?">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="daily_goal_reward">
                            <label>Quem bateu a meta
                                <select name="user_id" required>
                                    <option value="">Selecionar pessoa</option>
                                    <?php foreach (rewardable_users() as $rewardUser): ?>
                                        <option value="<?= (int) $rewardUser['id'] ?>"><?= e($rewardUser['name']) ?> — <?= e(sector_label($rewardUser['sector'] ?? 'administrativo')) ?> — <?= e($rewardUser['code']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>Quantidade de IF Coins
                                <input type="number" name="amount" min="1" required placeholder="Ex: 3">
                            </label>
                            <label class="full-span">Motivo
                                <input name="reason" maxlength="160" required value="Bateu a meta diária" placeholder="Ex: Bateu a meta diária de vendas">
                            </label>
                            <button type="submit">Premiar meta diária</button>
                        </form>
                    </section>

                    <div class="split users-split">
                        <section class="panel user-editor-panel">
                            <h2><?= $editUser ? 'Editar usuário' : 'Cadastrar usuário' ?></h2>
                            <form method="post" class="form-grid">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="<?= $editUser ? 'update_user' : 'create_user' ?>">
                                <?php if ($editUser): ?><input type="hidden" name="id" value="<?= (int) $editUser['id'] ?>"><?php endif; ?>
                                <label>Nome<input name="name" required value="<?= e($editUser['name'] ?? '') ?>"></label>
                                <label>Email<input type="email" name="email" required value="<?= e($editUser['email'] ?? '') ?>" placeholder="nome@italianofacil.com"></label>
                                <label>Senha<input type="password" name="password" <?= $editUser ? '' : 'required' ?> placeholder="<?= $editUser ? 'Deixe vazio para manter' : 'Senha inicial' ?>"></label>
                                <?php if ($editUser): ?>
                                    <label>IF Coins<input type="number" name="balance" min="0" value="<?= (int) $editUser['balance'] ?>"></label>
                                <?php else: ?>
                                    <div class="hint full-span">O saldo inicial é automático: Liderança recebe 10 IF Coins, Estagiário/Aprendiz recebe 2, Comercial começa com 0, e os demais setores recebem 5. Administrador só libera acesso ao painel.</div>
                                <?php endif; ?>
                                <label>Perfil
                                    <select name="role">
                                        <option value="user" <?= ($editUser['role'] ?? '') === 'user' ? 'selected' : '' ?>>Usuário</option>
                                        <option value="admin" <?= ($editUser['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrador</option>
                                    </select>
                                </label>
                                <label>Setor
                                    <select name="sector">
                                        <?php foreach (sector_options() as $sectorKey => $sectorName): ?>
                                            <option value="<?= e($sectorKey) ?>" <?= normalize_sector((string) ($editUser['sector'] ?? 'administrativo'), (string) ($editUser['role'] ?? 'user')) === $sectorKey ? 'selected' : '' ?>><?= e($sectorName) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <div class="hint full-span">Administrador não altera o setor. Para receber 10 IF Coins, selecione Liderança. Para receber 2 IF Coins, selecione Estagiário/Aprendiz.</div>
                                <?php if ($editUser): ?>
                                    <label class="check"><input type="checkbox" name="active" <?= (int) $editUser['active'] === 1 ? 'checked' : '' ?>> Ativo</label>
                                    <label class="check"><input type="checkbox" name="regenerate_code"> Gerar novo código</label>
                                    <div class="code-pill">Código atual: <strong><?= e($editUser['code']) ?></strong></div>
                                <?php endif; ?>
                                <button type="submit"><?= $editUser ? 'Salvar usuário' : 'Cadastrar usuário' ?></button>
                                <?php if ($editUser): ?><a class="ghost-button" href="painel.php?tab=users">Novo cadastro</a><?php endif; ?>
                            </form>

                            <?php if ($editUser): ?>
                                <hr>
                                <form method="post" class="form-grid compact-form">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="adjust_credit">
                                    <input type="hidden" name="id" value="<?= (int) $editUser['id'] ?>">
                                    <label>Adicionar ou remover IF Coins<input type="number" name="amount" required placeholder="+5 ou -2"></label>
                                    <label>Motivo<input name="reason" value="Ajuste manual"></label>
                                    <button type="submit">Aplicar ajuste</button>
                                </form>
                                <?php if ((int) $editUser['id'] !== (int) $admin['id']): ?>
                                    <form method="post" class="compact-form" data-confirm="Excluir definitivamente <?= e($editUser['name']) ?>? O usuário e os históricos ligados a ele serão apagados.">
                                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="id" value="<?= (int) $editUser['id'] ?>">
                                        <br>
                                        <button class="danger-button" type="submit">Excluir usuário</button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </section>
                        <section class="panel users-list-panel">
                            <h2>Lista de usuários</h2>
                            <div class="table-wrap users-table-wrap">
                                <table class="users-admin-table">
                                    <thead><tr><th>Usuário</th><th>Setor</th><th>Código</th><th>Coins</th><th>Status</th><th>Ação</th></tr></thead>
                                    <tbody>
                                    <?php foreach (all_users() as $row): ?>
                                        <tr>
                                            <td data-label="Usuário"><span class="table-user-main"><span class="user-name"><?= e($row['name']) ?></span><span class="user-email"><?= e($row['email']) ?></span></span></td>
                                            <td data-label="Setor"><?= e(sector_label($row['sector'] ?? "administrativo")) ?></td>
                                            <td data-label="Código"><span class="badge"><?= e($row['code']) ?></span></td>
                                            <td data-label="Coins"><?= (int) $row['balance'] ?></td>
                                            <td data-label="Status"><?= (int) $row['active'] === 1 ? 'Ativo' : 'Inativo' ?></td>
                                            <td data-label="Ação"><a class="table-action-link" href="painel.php?tab=users&edit_user=<?= (int) $row['id'] ?>">Editar</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                <?php elseif ($tab === 'products'): ?>
                    <?php $editProduct = isset($_GET['edit_product']) ? find_product((int) $_GET['edit_product']) : null; ?>
                    <div class="admin-title">
                        <div>
                            <div class="eyebrow">Estoque</div>
                            <h1>Produtos do mercadinho</h1>
                        </div>
                    </div>
                    <div class="split products-split">
                        <section class="panel product-editor-panel">
                            <h2><?= $editProduct ? 'Editar produto' : 'Adicionar produto' ?></h2>
                            <form method="post" enctype="multipart/form-data" class="form-grid product-form">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="<?= $editProduct ? 'update_product' : 'create_product' ?>">
                                <?php if ($editProduct): ?><input type="hidden" name="id" value="<?= (int) $editProduct['id'] ?>"><?php endif; ?>
                                <label>Nome<input name="name" required value="<?= e($editProduct['name'] ?? '') ?>"></label>
                                <label class="full-span">Descrição<input name="description" value="<?= e($editProduct['description'] ?? '') ?>"></label>
                                <label>Estoque<input type="number" name="stock" min="0" value="<?= (int) ($editProduct['stock'] ?? 0) ?>"></label>
                                <label>Custo em IF Coins<input type="number" name="cost" min="1" value="<?= (int) ($editProduct['cost'] ?? 1) ?>"></label>
                                <label class="full-span">URL da imagem<input name="image_url" value="<?= e($editProduct['image_url'] ?? '') ?>" placeholder="https://..."></label>
                                <label class="full-span file-field file-field-custom">
                                    <span>Ou enviar foto</span>
                                
                                    <input id="product-image-input" type="file" name="image" accept="image/*">
                                
                                    <span class="file-fake-button">Selecionar</span>
                                </label>
                                <?php if ($editProduct): ?>
                                    <label class="check"><input type="checkbox" name="active" <?= (int) $editProduct['active'] === 1 ? 'checked' : '' ?>> Produto ativo</label>
                                <?php endif; ?>
                                <button type="submit"><?= $editProduct ? 'Salvar produto' : 'Cadastrar produto' ?></button>
                                <?php if ($editProduct): ?><a class="ghost-button" href="painel.php?tab=products">Novo produto</a><?php endif; ?>
                            </form>
                        </section>
                        <section class="panel product-stock-panel">
                            <h2>Estoque atual</h2>
                            <div class="table-wrap product-table-wrap">
                                <table class="product-admin-table">
                                    <thead><tr><th>Produto</th><th>Estoque</th><th>Custo</th><th>Status</th><th></th><th></th></tr></thead>
                                    <tbody>
                                    <?php foreach (db()->query('SELECT * FROM products ORDER BY active DESC, stock <= 0, name')->fetchAll() as $row): ?>
                                        <tr>
                                            <td data-label="Produto"><?= e($row['name']) ?></td>
                                            <td data-label="Estoque"><?= (int) $row['stock'] ?></td>
                                            <td data-label="Custo"><?= (int) $row['cost'] ?> IF Coin<?= (int) $row['cost'] > 1 ? 's' : '' ?></td>
                                            <td data-label="Status"><?= (int) $row['active'] === 1 ? 'Ativo' : 'Inativo' ?></td>
                                            <td data-label="Editar"><a href="painel.php?tab=products&edit_product=<?= (int) $row['id'] ?>">Editar</a></td>
                                            <td data-label="Remover">
                                                <?php if ((int) $row['active'] === 1): ?>
                                                    <form method="post" data-confirm="Remover <?= e($row['name']) ?> da vitrine?">
                                                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                                        <input type="hidden" name="action" value="remove_product">
                                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                                        <button class="danger-button" type="submit">Remover</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                <?php elseif ($tab === 'history'): ?>
                    <div class="admin-title">
                        <div>
                            <div class="eyebrow">Histórico</div>
                            <h1>Movimentos do Mercadinho</h1>
                        </div>
                    </div>
                    <div class="history-grid">
                        <section class="panel">
                            <h2>Resgates realizados</h2>
                            <?php render_transactions_table(transactions(200)); ?>
                        </section>
                        <section class="panel">
                            <h2>Doações de IF Coins</h2>
                            <?php render_donations_table(donations(200)); ?>
                        </section>
                        <section class="panel">
                            <h2>Premiações e ajustes de coins</h2>
                            <?php render_credit_rewards_table(credit_rewards(200)); ?>
                        </section>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    <?php else: ?>
        <main class="empty-state">
            <h1>Acesso indisponível</h1>
            <p>Entre com uma conta válida para continuar.</p>
            <a class="ghost-button" href="index.php">Voltar</a>
        </main>
    <?php endif; ?>
</div>
<?php if ($user): ?>
    <nav class="bottom-nav hide-desktop" aria-label="Navegação principal">
        <?php if ($user['role'] === 'admin'): ?>
            <a href="painel.php?tab=overview" class="nav-item <?= $tab === 'overview' ? 'active' : '' ?>"><span class="icon icon-overview" aria-hidden="true"></span><span>Home</span></a>
            <a href="painel.php?tab=users" class="nav-item <?= $tab === 'users' ? 'active' : '' ?>"><span class="icon icon-users" aria-hidden="true"></span><span>Usuários</span></a>
            <a href="painel.php?tab=products" class="nav-item <?= $tab === 'products' ? 'active' : '' ?>"><span class="icon icon-products" aria-hidden="true"></span><span>Itens</span></a>
            <a href="painel.php?tab=history" class="nav-item <?= $tab === 'history' ? 'active' : '' ?>"><span class="icon icon-history" aria-hidden="true"></span><span>Histórico</span></a>
        <?php else: ?>
            <a href="index.php" class="nav-item"><span class="icon icon-store" aria-hidden="true"></span><span>Loja</span></a>
            <a href="painel.php?page=conta" class="nav-item active"><span class="icon icon-user" aria-hidden="true"></span><span>Conta</span></a>
            <form method="post" style="display:contents">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="nav-item"><span class="icon icon-logout" aria-hidden="true"></span><span>Sair</span></button>
            </form>
        <?php endif; ?>
    </nav>
<?php endif; ?>

<script src="assets/app.js?v=20260625-users-mobile-card-final"></script>
</body>
</html>

<?php
function render_transactions_table(array $rows): void
{
    ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Data</th><th>Colaborador</th><th>Código</th><th>Produto</th><th>Custo</th></tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="5">Nenhum resgate registrado ainda.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td data-label="Data"><?= e(date('d/m/Y H:i', strtotime($row['created_at']))) ?></td>
                    <td data-label="Colaborador"><?= e($row['user_name']) ?></td>
                    <td data-label="Código"><strong><?= e($row['code_used']) ?></strong></td>
                    <td data-label="Produto"><?= e($row['product_name']) ?></td>
                    <td data-label="Custo"><?= (int) $row['cost'] ?> IF Coin<?= (int) $row['cost'] > 1 ? 's' : '' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}


function render_donations_table(array $rows, ?int $viewerId = null): void
{
    ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Data</th><th>Origem</th><th>Destino</th><th>Código</th><th>Coins</th><th>Email</th></tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="6">Nenhuma doação registrada ainda.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <?php
                    $direction = '';
                    if ($viewerId !== null) {
                        $direction = (int) $row['donor_id'] === $viewerId ? 'Enviada' : 'Recebida';
                    }
                ?>
                <tr>
                    <td data-label="Data"><?= e(date('d/m/Y H:i', strtotime($row['created_at']))) ?></td>
                    <td data-label="Origem"><?= e($direction ? $direction . ': ' . $row['donor_name'] : $row['donor_name']) ?></td>
                    <td data-label="Destino"><?= e($row['recipient_name']) ?></td>
                    <td data-label="Código"><strong><?= e($row['recipient_code']) ?></strong></td>
                    <td data-label="Coins"><?= (int) $row['amount'] ?></td>
                    <td data-label="Email"><?= strpos((string) $row['email_status'], 'sent') === 0 ? 'Enviado' : e($row['email_status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function render_credit_rewards_table(array $rows): void
{
    ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Data</th><th>Pessoa</th><th>Coins</th><th>Motivo</th></tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="4">Nenhuma premiação registrada ainda.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td data-label="Data"><?= e(date('d/m/Y H:i', strtotime($row['created_at']))) ?></td>
                    <td data-label="Pessoa"><?= e($row['user_name']) ?></td>
                    <td data-label="Coins"><?= (int) $row['amount'] ?></td>
                    <td data-label="Motivo"><?= e($row['reason']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

?>
