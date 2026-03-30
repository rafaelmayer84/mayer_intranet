<?php
/**
 * track-whatsapp.php — Script Standalone de Tracking
 *
 * Local: public/track-whatsapp.php (dentro do Laravel, mas NÃO usa Laravel)
 * Propósito: Recebe parâmetros de tracking do JavaScript do site,
 *            salva na tabela lead_tracking via PDO direto,
 *            e redireciona o visitante para o WhatsApp.
 *
 * Este arquivo NÃO carrega o Laravel. Usa PDO puro.
 * Em caso de qualquer erro, o redirect para WhatsApp acontece normalmente.
 *
 * @version 1.0.0
 * @date    2026-02-09
 */

// Telefone padrão se não informado (escritório Mayer)
define('DEFAULT_PHONE', '554738421050');

// ============================================================
// 1. LEITURA DE CREDENCIAIS DO .env
// ============================================================
$envPath = __DIR__ . '/../.env';
$env = [];

if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $pos = strpos($line, '=');
        if ($pos === false) continue;
        $key = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));
        // Remover aspas
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'")) {
            $value = substr($value, 1, -1);
        }
        $env[$key] = $value;
    }
}

// ============================================================
// 2. SANITIZAÇÃO DOS PARÂMETROS
// ============================================================
$phone       = preg_replace('/[^0-9]/', '', $_GET['phone'] ?? '');
$gclid       = mb_substr($_GET['gclid'] ?? '', 0, 500);
$fbclid      = mb_substr($_GET['fbclid'] ?? '', 0, 500);
$utm_source  = mb_substr($_GET['utm_source'] ?? '', 0, 255);
$utm_medium  = mb_substr($_GET['utm_medium'] ?? '', 0, 255);
$utm_campaign = mb_substr($_GET['utm_campaign'] ?? '', 0, 255);
$utm_content = mb_substr($_GET['utm_content'] ?? '', 0, 255);
$utm_term    = mb_substr($_GET['utm_term'] ?? '', 0, 255);
$landing_page = mb_substr($_GET['landing_page'] ?? '', 0, 2000);
$referrer    = mb_substr($_GET['referrer'] ?? '', 0, 2000);
$text        = $_GET['text'] ?? '';

if (!$phone) {
    $phone = DEFAULT_PHONE;
}

// ============================================================
// 3. GRAVAÇÃO NO BANCO DE DADOS (fail-safe)
// ============================================================
$hasData = $gclid || $fbclid || $utm_source || $utm_medium || $utm_campaign || $landing_page || $referrer;

if ($hasData && !empty($env['DB_DATABASE'])) {
    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $env['DB_HOST'] ?? '127.0.0.1',
            $env['DB_PORT'] ?? '3306',
            $env['DB_DATABASE']
        );

        $pdo = new PDO($dsn, $env['DB_USERNAME'] ?? '', $env['DB_PASSWORD'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3
        ]);

        $sql = "INSERT INTO lead_tracking
            (phone, gclid, fbclid, utm_source, utm_medium, utm_campaign, utm_content, utm_term, landing_page, referrer, ip_address, user_agent, created_at)
            VALUES
            (:phone, :gclid, :fbclid, :utm_source, :utm_medium, :utm_campaign, :utm_content, :utm_term, :landing_page, :referrer, :ip, :ua, NOW())";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':phone'        => $phone,
            ':gclid'        => $gclid ?: null,
            ':fbclid'       => $fbclid ?: null,
            ':utm_source'   => $utm_source ?: null,
            ':utm_medium'   => $utm_medium ?: null,
            ':utm_campaign' => $utm_campaign ?: null,
            ':utm_content'  => $utm_content ?: null,
            ':utm_term'     => $utm_term ?: null,
            ':landing_page' => $landing_page ?: null,
            ':referrer'     => $referrer ?: null,
            ':ip'           => $_SERVER['REMOTE_ADDR'] ?? null,
            ':ua'           => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]);
    } catch (Exception $e) {
        // Log silencioso — nunca bloquear o redirect
        error_log('[track-whatsapp] DB Error: ' . $e->getMessage());
    }
}

// ============================================================
// 4. REDIRECT PARA WHATSAPP
// ============================================================
$waUrl = 'https://wa.me/' . $phone;
if ($text !== '') {
    $waUrl .= '?text=' . rawurlencode($text);
}

header('HTTP/1.1 302 Found');
header('Location: ' . $waUrl);
header('Cache-Control: no-cache, no-store, must-revalidate');
exit;
