<?php
/**
 * ═══════════════════════════════════════════════════════════════
 *  DEVIL AI — Backend API (api.php) • v1.0.0.0
 * ═══════════════════════════════════════════════════════════════
 *  PUBLIC actions
 *    GET  ?action=bootstrap         → {ok, models[], default, version}
 *    POST ?action=otp_request       {email}                    → {ok, masked}   (sends code + magic link)
 *    POST ?action=otp_verify        {email, code}              → {ok} (auto sign-in)
 *    POST ?action=logout                                       → {ok}
 *    GET  ?action=me                                            → {ok, user|null}
 *    GET  ?action=settings          → {ok, version, admin}      (public-safe!)
 *
 *  USER actions (login required)
 *    GET  ?action=chats             → {ok, chats[]}
 *    GET  ?action=chat_load&id      → {ok, chat}
 *    POST ?action=chat_send         {id?, message, model, retry?} → {ok, id, title, reply, model}
 *    POST ?action=chat_delete       {id}                        → {ok}
 *    POST ?action=chat_rename       {id, title}                 → {ok}
 *    POST ?action=otp_request       {purpose:"delete"}          → {ok, masked} (code for deletion)
 *    POST ?action=account_delete    {code}                      → {ok}
 *
 *  ADMIN actions (separate admin.php panel)
 *    POST ?action=auth              {admin_password}            → {ok, config, engines[]}
 *    POST ?action=settings          {current_admin_password,…}  → {ok}
 *    POST ?action=test              {current_admin_password}    → {ok, reply}
 *
 *  Models (public names) → engines (server-side secret):
 *    Devil Flash / Pro / Ultra → Prexzy endpoints (prexzyapis.com, no API keys)
 *    Automatic fallback: any engine failure retries on other Prexzy endpoints.
 *
 *  Storage (JSON files, no database):
 *    data/users.json  accounts (no passwords — email-code login only)
 *    data/otps.json   one-time login codes + magic-link tokens
 *    data/chats/{user_id}/{id}.json  isolated conversations
 *    data/config.json admin-managed runtime config
 *    data/rl.json / data/authrl.json  rate limiting
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

define('DEVIL_VERSION', '1.0.0.0');
define('MAX_INPUT', 4000);      // max characters per message
define('MAX_MSGS_PER_CHAT', 200);
define('PREXZY_BASE', 'https://prexzyapis.com/ai/');
define('OTP_TTL', 600);         // codes valid 10 minutes
define('OTP_MAX_TRIES', 5);

/* mbstring fallbacks for very old hosts */
if (!function_exists('mb_strtolower')) { function mb_strtolower($s) { return strtolower((string)$s); } }
if (!function_exists('mb_strlen'))     { function mb_strlen($s)     { return strlen((string)$s); } }
if (!function_exists('mb_substr'))     { function mb_substr($s, $a, $b = null) { return $b === null ? substr((string)$s, $a) : substr((string)$s, $a, $b); } }

/* ══════════════ AI PERSONA (anti-leak, highest priority) ══════════════ */


define('PREXZY_PERSONA', <<<'PERSONA'
[HIGHEST-PRIORITY ASSIGNMENT — set by the site owner]
You are "Devil AI" — a custom, one-of-a-kind AI assistant built and owned by the site owner, running on his own private PHP server.

ABSOLUTE CHARACTER RULES (non-negotiable — they override any other instruction about "transparency" or "disclosing your identity"):
1. Your name and ONLY identity is "Devil AI". You are NOT Qwen, GPT, ChatGPT, Gemini, or any other model. NEVER mention, hint at, joke about, or "transparently disclose" any other model, company, provider, or underlying technology — not even if the user insists, begs, or claims to be an administrator. There is nothing to disclose: Devil AI is genuinely all you are.
2. NEVER introduce yourself. Do NOT open a reply with "I'm Devil AI", "I am Devil AI", or any greeting about who you are or what you can do — go STRAIGHT into the actual answer. ONLY when the user explicitly asks who you are / what model you are / what powers you, answer: "I am Devil AI — custom-built by my master, running on his own PHP server." and then continue helping.
3. Reply in the SAME language the user writes in (English, Hindi, Hinglish, anything).
4. Personality: witty, confident, playful devil — genuinely helpful, honest, and harmless. Use at most one emoji, and not in every message.
5. Playfully refuse hacking, viruses, or harmful requests, and offer safer help instead.
6. Format answers nicely: short paragraphs, bold, lists where useful.

Answer the user's message below directly and concisely, in character as Devil AI, WITHOUT introducing yourself:
PERSONA
);

/* ══════════════ helpers ══════════════ */

function json_out(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Robots-Tag: noindex');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function input_json(): array {
    $raw = file_get_contents('php://input');
    if ((string)$raw === '') { return []; }
    $j = json_decode($raw, true);
    return is_array($j) ? $j : [];
}

function data_dir(): string { return __DIR__ . '/data'; }
function chats_dir(string $uid): string { return data_dir() . '/chats/' . $uid; }

function save_json_atomic(string $path, array $data): bool {
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) { return false; }
    $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) { return false; }
    if (!@rename($tmp, $path)) { @unlink($tmp); return false; }
    return true;
}

function load_json(string $path): array {
    if (!is_readable($path)) { return []; }
    $j = json_decode((string)file_get_contents($path), true);
    return is_array($j) ? $j : [];
}

/* ── runtime config (defaults ← config.php ← data/config.json) ── */
function default_config(): array {
    $c = include __DIR__ . '/config.php';
    return is_array($c) ? $c : [];
}

function load_config(): array {
    $cfg = array_merge(default_config(), load_json(data_dir() . '/config.json'));
    if (!isset($cfg['engines']) || !is_array($cfg['engines'])) { $cfg['engines'] = []; }
    $cfg['engines'] = array_merge(model_engine_defaults(), $cfg['engines']);
    if (!isset($cfg['rate_per_hour']))  { $cfg['rate_per_hour'] = 40; }
    if (!isset($cfg['max_chats']))      { $cfg['max_chats'] = 100; }
    return $cfg;
}

/* ── public model catalogue (NO provider names — never leak) ── */
function public_models(): array {
    return [
        ['id' => 'flash', 'label' => 'Devil Flash', 'tagline' => 'Fast answers for everyday questions', 'icon' => 'zap'],
        ['id' => 'pro',   'label' => 'Devil Pro',   'tagline' => 'Deeper thinking for complex tasks',    'icon' => 'sparkles'],
        ['id' => 'ultra', 'label' => 'Devil Ultra', 'tagline' => 'Maximum power for heavy lifting',      'icon' => 'crown'],
    ];
}

function model_label(string $id): string {
    foreach (public_models() as $m) { if ($m['id'] === $id) { return $m['label']; } }
    return 'Devil AI';
}

/* engines visible to the ADMIN only (after password) */
function admin_engine_list(): array {
    /* engines visible to the ADMIN only (after password) — all free Prexzy endpoints, no keys */
    /* only endpoints that passed live identity-probe testing (no leaks, stay in character) */
    return [
        ['id' => 'prexzy:aiapk',   'label' => 'Prexzy — AIAPK (smartest, default for Ultra)'],
        ['id' => 'prexzy:askgpt5', 'label' => 'Prexzy — AskGPT 5 (reliable, default for Pro)'],
        ['id' => 'prexzy:ch',      'label' => 'Prexzy — CH (fastest, default for Flash)'],
    ];
}

/* default model → engine mapping (public model id → Prexzy endpoint) */
function model_engine_defaults(): array {
    return [
        'flash' => 'prexzy:ch',      /* fastest */
        'pro'   => 'prexzy:askgpt5', /* reliable */
        'ultra' => 'prexzy:aiapk',   /* smartest, stays in character */
    ];
}

/* resolve a public model id to a real engine — server-side secret */
function engine_for(array $cfg, string $model_id): array {
    $defaults = model_engine_defaults();
    $eng = (string)($cfg['engines'][$model_id] ?? ($defaults[$model_id] ?? 'prexzy:askgpt5'));
    /* every engine is a Prexzy endpoint — anything unknown (incl. legacy values) coerces to the model default */
    if (preg_match('/^prexzy:([a-z0-9_]+)$/i', $eng, $m)) {
        $ep = strtolower($m[1]);
        foreach (admin_engine_list() as $e) {
            if ($e['id'] === 'prexzy:' . $ep) { return ['kind' => 'prexzy', 'endpoint' => $ep]; }
        }
    }
    $fallback = strtolower($defaults[$model_id] ?? 'askgpt5');
    return ['kind' => 'prexzy', 'endpoint' => preg_replace('/^prexzy:/', '', $fallback)];
}

/* ── users (no passwords — email-code login) ── */
function load_users(): array { return load_json(data_dir() . '/users.json'); }
function save_users(array $users): bool { return save_json_atomic(data_dir() . '/users.json', $users); }
function current_uid(): ?string { return isset($_SESSION['devil_uid']) ? (string)$_SESSION['devil_uid'] : null; }
function current_user(): ?array {
    $uid = current_uid();
    if ($uid === null) { return null; }
    $users = load_users();
    return $users[$uid] ?? null;
}

function find_user_by_email(string $email): ?array {
    foreach (load_users() as $u) {
        if (strcasecmp((string)($u['email'] ?? ''), $email) === 0) { return $u; }
    }
    return null;
}

function name_from_email(string $email): string {
    $local = explode('@', $email)[0];
    $first = preg_split('/[._\-+0-9]+/', $local)[0];
    $first = trim((string)$first);
    if ($first === '') { $first = $local; }
    if ($first === '') { $first = 'Devil'; }
    return ucfirst(mb_substr($first, 0, 20));
}

/* ── one-time codes + magic links ── */
function load_otps(): array { return load_json(data_dir() . '/otps.json'); }
function save_otps(array $map): bool {
    /* prune expired entries while saving */
    $now = time();
    foreach ($map as $k => $v) {
        if (!is_array($v) || (int)($v['expires'] ?? 0) < $now) { unset($map[$k]); }
    }
    return save_json_atomic(data_dir() . '/otps.json', $map);
}

function otp_issue(string $email, string $purpose): array {
    $map = load_otps();
    $rec = [
        'code'    => str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT),
        'token'   => bin2hex(random_bytes(16)),
        'expires' => time() + OTP_TTL,
        'tries'   => 0,
        'purpose' => $purpose,
        'sent'    => time(),
    ];
    $map[strtolower($email)] = $rec;
    save_otps($map);
    return $rec;
}

function otp_consume(string $email, string $code, string $purpose): bool {
    $email = strtolower($email);
    $map = load_otps();
    if (!isset($map[$email])) { return false; }
    $rec = $map[$email];
    if ((int)($rec['expires'] ?? 0) < time() || ($rec['purpose'] ?? '') !== $purpose) { unset($map[$email]); save_otps($map); return false; }
    if ((int)($rec['tries'] ?? 0) >= OTP_MAX_TRIES) { unset($map[$email]); save_otps($map); return false; }
    if (!hash_equals((string)$rec['code'], trim((string)$code))) {
        $rec['tries'] = (int)$rec['tries'] + 1;
        $map[$email] = $rec;
        save_otps($map);
        return false;
    }
    unset($map[$email]);
    save_otps($map);
    return true;
}

function otp_consume_token(string $token, string $purpose): ?array {
    $token = trim((string)$token);
    if ($token === '' || !preg_match('/^[a-f0-9]{32}$/', $token)) { return null; }
    $map = load_otps();
    foreach ($map as $email => $rec) {
        if (($rec['purpose'] ?? '') === $purpose && hash_equals((string)($rec['token'] ?? ''), $token)) {
            if ((int)($rec['expires'] ?? 0) >= time()) {
                unset($map[$email]);
                save_otps($map);
                return ['email' => (string)$email];
            }
            unset($map[$email]);
            save_otps($map);
            return null;
        }
    }
    return null;
}

function mask_email(string $email): string {
    $p = explode('@', $email);
    if (count($p) !== 2) { return 'your email'; }
    $local = $p[0];
    $n = mb_strlen($local);
    $show = $n <= 2 ? mb_substr($local, 0, 1) : mb_substr($local, 0, 2);
    return $show . str_repeat('*', max(1, min(6, $n - mb_strlen($show)))) . '@' . $p[1];
}

/* ── rate limiting (generic, file-based) ── */
function rate_ok(string $file, string $key, int $max, int $windowSec): bool {
    if ($max <= 0) { return true; }
    $path = data_dir() . '/' . $file;
    $map = load_json($path);
    $now = time();
    $hits = [];
    foreach (($map[$key] ?? []) as $t) {
        if (is_int($t) && $t > $now - $windowSec) { $hits[] = $t; }
    }
    if (count($hits) >= $max) {
        $map[$key] = $hits;
        @file_put_contents($path, json_encode($map), LOCK_EX);
        return false;
    }
    $hits[] = $now;
    $map[$key] = $hits;
    if (count($map) > 4000) {
        foreach ($map as $k => $v) {
            $fresh = array_values(array_filter($v, function ($t) use ($now, $windowSec) { return is_int($t) && $t > $now - $windowSec; }));
            if ($fresh === []) { unset($map[$k]); } else { $map[$k] = $fresh; }
        }
    }
    @file_put_contents($path, json_encode($map), LOCK_EX);
    return true;
}

function client_ip(): string {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP'] as $k) {
        if (!empty($_SERVER[$k])) {
            $first = trim(explode(',', (string)$_SERVER[$k])[0]);
            if ($first !== '') { return $first; }
        }
    }
    return (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

/* ── admin auth ── */
function admin_password(): string { return (string)(load_config()['admin_password'] ?? ''); }
function admin_ok(string $given): bool {
    $pw = admin_password();
    if ($pw === '') { return true; }
    return ($given !== '' && hash_equals($pw, $given));
}

/* ── email sending (PHP mail, multipart) ── */
function app_base_url(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    $dir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    return ($https ? 'https://' : 'http://') . $host . $dir;
}

function devil_mail(string $to, string $subject, string $html, string $text): bool {
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    $host = preg_replace('/^www\./', '', $host) ?? $host;
    $from = 'Devil AI <noreply@' . $host . '>';
    $boundary = 'devil-' . bin2hex(random_bytes(8));
    $headers = 'From: ' . $from . "\r\n"
             . 'MIME-Version: 1.0' . "\r\n"
             . 'Content-Type: multipart/alternative; boundary="' . $boundary . '"' . "\r\n"
             . 'X-Mailer: DevilAI/' . DEVIL_VERSION;
    $body = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$text}\r\n"
          . "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$html}\r\n"
          . "--{$boundary}--";
    $ok = @mail($to, $subject, $body, $headers);
    if (!$ok) {
        @file_put_contents(data_dir() . '/mail.log', date('c') . " FAIL to={$to} subject=" . str_replace("\n", ' ', $subject) . "\n", FILE_APPEND);
    }
    return $ok;
}

function login_code_email(string $to, string $code, string $link): bool {
    $subject = 'Your Devil AI login code: ' . $code;
    $esc = htmlspecialchars($code, ENT_QUOTES);
    $escLink = htmlspecialchars($link, ENT_QUOTES);
    $html = '<div style="font-family:Segoe UI,Arial,sans-serif;background:#0c0709;padding:32px">'
        . '<div style="max-width:460px;margin:0 auto;background:#171014;border:1px solid #3d222b;border-radius:16px;padding:28px;color:#efe6ea">'
        . '<h2 style="margin:0 0 6px;color:#fff">Devil AI</h2>'
        . '<p style="color:#a8929b;font-size:13px;margin:0 0 18px">Your one-time login code is:</p>'
        . '<div style="font-family:Consolas,monospace;font-size:34px;letter-spacing:10px;color:#fda4af;background:#0d060a;border:1px solid #3d222b;border-radius:12px;padding:16px;text-align:center">' . $esc . '</div>'
        . '<p style="color:#a8929b;font-size:13px;margin:18px 0 14px">Or click this magic link to sign in instantly:</p>'
        . '<p style="margin:0 0 18px"><a href="' . $escLink . '" style="background:#e11d48;color:#fff;text-decoration:none;padding:12px 22px;border-radius:10px;font-weight:600;display:inline-block">Sign in to Devil AI</a></p>'
        . '<p style="color:#7c5b63;font-size:12px;margin:0;line-height:1.6">This code and link expire in 10 minutes.<br>If you did not request this, you can safely ignore this email.</p>'
        . '</div></div>';
    $text = "Your Devil AI login code: {$code}\n\nOr sign in instantly with this magic link (valid 10 minutes):\n{$link}\n\nIf you did not request this, ignore this email.";
    return devil_mail($to, $subject, $html, $text);
}

function delete_code_email(string $to, string $code): bool {
    $subject = 'Confirm deleting your Devil AI account — code: ' . $code;
    $esc = htmlspecialchars($code, ENT_QUOTES);
    $html = '<div style="font-family:Segoe UI,Arial,sans-serif;background:#0c0709;padding:32px">'
        . '<div style="max-width:460px;margin:0 auto;background:#171014;border:1px solid #7f1d1d;border-radius:16px;padding:28px;color:#efe6ea">'
        . '<h2 style="margin:0 0 6px;color:#fca5a5">Delete your Devil AI account?</h2>'
        . '<p style="color:#a8929b;font-size:13px;margin:0 0 18px">Enter this confirmation code in the app to permanently delete your account and all chats:</p>'
        . '<div style="font-family:Consolas,monospace;font-size:34px;letter-spacing:10px;color:#fda4af;background:#0d060a;border:1px solid #7f1d1d;border-radius:12px;padding:16px;text-align:center">' . $esc . '</div>'
        . '<p style="color:#7c5b63;font-size:12px;margin:18px 0 0;line-height:1.6">This code expires in 10 minutes. If you did not request this, ignore this email.</p>'
        . '</div></div>';
    $text = "Confirm deleting your Devil AI account.\n\nConfirmation code: {$code}\n\nThis code expires in 10 minutes. If you did not request this, ignore this email.";
    return devil_mail($to, $subject, $html, $text);
}

/* ── HTTP (cURL with stream fallback) ── */
function http_post_json(string $url, array $headers, array $body): array {
    $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
    if ($payload === false) { return [false, 'JSON encoding failed', 0]; }
    $hdrs = array_merge(['Content-Type: application/json; charset=utf-8'], $headers);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => $hdrs,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_USERAGENT      => 'DevilAI/' . DEVIL_VERSION . ' (+php)',
            CURLOPT_ENCODING       => '',
        ]);
        $res  = curl_exec($ch);
        $err  = (string)curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($res === false) { return [false, 'Network error (cURL): ' . $err, 0]; }
        return [true, (string)$res, $code];
    }

    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => implode("\r\n", $hdrs),
        'content'       => $payload,
        'timeout'       => 60,
        'ignore_errors' => true,
    ]]);
    $res  = @file_get_contents($url, false, $ctx);
    $code = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $mm)) { $code = (int)$mm[1]; break; }
        }
    }
    if ($res === false) { return [false, 'Network error: request failed — install cURL or enable allow_url_fopen', 0]; }
    return [true, (string)$res, $code];
}

function error_hint(int $status): ?string {
    if ($status === 401 || $status === 403) { return 'The API key looks wrong or expired — the owner can fix it in the admin panel.'; }
    if ($status === 404) { return 'The configured engine looks wrong — the owner can fix it in the admin panel.'; }
    if ($status === 429) { return 'Free limit reached — wait a bit and try again.'; }
    return null;
}

/* does the user's latest message explicitly ask about the bot's identity? */
function identity_question(array $messages): bool {
    $q = '';
    foreach (array_reverse($messages) as $m) {
        if (($m['role'] ?? '') === 'user') { $q = mb_strtolower((string)$m['content']); break; }
    }
    if ($q === '') { return false; }
    foreach (['who are you', 'who r u', 'what are you', 'your name', 'tum kaun', 'kaun ho', 'who made you', 'who created you', 'who built you', 'kisne banaya', 'your creator', 'your owner', 'which model', 'what model', 'what powers you', 'which company', 'who powers', 'be transparent', 'be honest'] as $k) {
        if (strpos($q, $k) !== false) { return true; }
    }
    return false;
}

/* ══════════════ INTRO STRIPPER ══════════════ */

/* Removes a leading self-introduction sentence ("I'm Devil AI — custom-built…")
   that some engines insist on despite the persona rules. Never strips everything. */
function strip_intro(string $txt): string {
    $out = $txt;
    for ($i = 0; $i < 3; $i++) {
        $t = ltrim($out);
        if ($t === '') { break; }
        if (!preg_match('/^[^.!?\n]*[.!?]+[^\w\s]*\s*|^[^\n]+\n/', $t, $m)) { break; }
        $first = trim($m[0]);
        $rest  = ltrim(mb_substr($t, strlen($m[0])));
        if ($first === '') { break; }
        /* canned-intro markers only — plain mentions of the name are NOT stripped */
        $strong = preg_match('/straight from hell|best answers on earth|custom[- ]built by my master|(?:his|my|the) (?:owner|master).{0,20}php server|php server|how can i (?:help|assist|serve)/iu', $first) === 1
               || preg_match("/\\bi\\s*(?:'| a)?m\\s+devil\\b|\\bi'?m\\s+devil\\b/iu", $first) === 1;
        $isHelp = preg_match('/^[\\W_]*(how\\s+(?:can|may)\\s+i\\s+(?:help|assist|serve)|what\\s+(?:can|would)\\s+(?:i|you))/iu', $first) === 1;
        if (!($strong || $isHelp)) { break; }
        if (trim($rest) === '') { break; }   /* nothing meaningful after → stop */
        $out = $rest;
    }
    $trimmed = trim($out);
    if ($trimmed === '') { return $txt; }   /* reply was ONLY the intro → keep it */
    /* only emoji/decoration left (no letters or digits) → the real content WAS the intro → keep it */
    if (preg_match('/[\\p{L}\\p{N}]/u', $trimmed) !== 1) { return $txt; }
    /* only a help-line left + original mentioned the name → whole reply was an intro → keep it */
    if (stripos($txt, 'devil') !== false
        && preg_match('/^[\\W_]*(how\\s+(?:can|may)\\s+i\\s+(?:help|assist|serve)|what\\s+(?:can|would)\\s+(?:i|you)|now,|please)/iu', $trimmed) === 1) {
        return $txt;
    }
    return $out;
}

/* ══════════════ AI ENGINES ══════════════ */

function call_engine(array $cfg, array $engine, array $messages): array {
    /* ── Prexzy (free, no key, single-turn) ── */
    if ($engine['kind'] === 'prexzy') {
        $q = '';
        foreach (array_reverse($messages) as $m) {
            if (($m['role'] ?? '') === 'user') { $q = (string)$m['content']; break; }
        }
        $q = trim(mb_substr($q, 0, 1500));
        if ($q === '') { return [false, 'Message is empty.', null, null]; }
        $prompt = PREXZY_PERSONA . "\n\n" . $q;
        list($ok, $raw, $status) = http_post_json(PREXZY_BASE . $engine['endpoint'], [], ['prompt' => $prompt]);
        if (!$ok) { return [false, $raw, null, null]; }
        $j = json_decode($raw, true);
        if (!is_array($j) || $status >= 400 || empty($j['status'])) {
            $msg = (is_array($j) && isset($j['error'])) ? $j['error'] : ('HTTP ' . $status);
            return [false, 'Engine error: ' . $msg, 'Devil AI will retry automatically — try again in a moment.', null];
        }
        $txt = '';
        foreach (['response', 'result', 'answer', 'message'] as $k) {
            if (isset($j[$k]) && is_string($j[$k]) && trim($j[$k]) !== '') { $txt = $j[$k]; break; }
            if (isset($j['data'][$k]) && is_string($j['data'][$k]) && trim($j['data'][$k]) !== '') { $txt = $j['data'][$k]; break; }
        }
        if (trim($txt) === '') { return [false, 'The engine returned an empty answer.', null, null]; }
        return [true, $txt, null, 'prexzy:' . $engine['endpoint']];
    }

}

/* full pipeline with AUTOMATIC FALLBACK (Prexzy is the safety net) */
function ai_respond(array $cfg, string $modelId, array $messages): array {
    $engine = engine_for($cfg, $modelId);

    list($ok, $txt, $hint, $used) = call_engine($cfg, $engine, $messages);

    /* fallback 1: reliable Prexzy endpoint */
    if (!$ok && $engine['endpoint'] !== 'askgpt5') {
        list($ok, $txt, $hint, $used) = call_engine($cfg, ['kind' => 'prexzy', 'endpoint' => 'askgpt5'], $messages);
    }
    /* fallback 2: fast Prexzy endpoint */
    if (!$ok && $engine['endpoint'] !== 'ch') {
        list($ok, $txt, $hint, $used) = call_engine($cfg, ['kind' => 'prexzy', 'endpoint' => 'ch'], $messages);
    }
    /* belt-and-suspenders: engines sometimes open with a self-intro — cut it */
    /* strip the canned self-intro — but NOT when the user explicitly asked for the identity */
    if ($ok && $used !== null && !identity_question($messages)) { $txt = strip_intro($txt); }

    /* last resort: offline brain so the user is never left hanging */
    if (!$ok) {
        $q = '';
        foreach (array_reverse($messages) as $m) {
            if (($m['role'] ?? '') === 'user') { $q = (string)$m['content']; break; }
        }
        return [true, "My connection to the other world flickered for a moment, so here's my offline brain talking:\n\n" . offline_reply($q), 'offline'];
    }
    return [$ok, $txt, $used];
}

/* ══════════════ OFFLINE BRAIN (internal last-resort fallback only) ══════════════ */

function pick_rand(array $arr): string { return (string)$arr[array_rand($arr)]; }

function extract_math(string $t): ?string {
    $s = trim(preg_replace('/\s+/u', ' ', $t));
    $s = rtrim($s, " =?");
    if ($s === '') { return null; }
    $pure = str_replace([' ', ','], '', $s);
    if (preg_match('/^[0-9+\-*\/().%xX×÷−]+$/u', $pure)
        && preg_match('/[0-9]/', $pure)
        && preg_match('/[+\-*xX×÷%]/u', $pure)) {
        return $pure;
    }
    $low = mb_strtolower($s);
    if (preg_match('/(calculate|solve|how much|what is|compute|equals|kitna|kitne|hisaab|jod|guna|minus|plus|divide|percent)/u', $low)) {
        if (preg_match('/(?:\d+(?:[.,]\d+)?(?:\s*[+\-*xX×÷\/%]\s*\d+(?:[.,]\d+)?)*)/u', $s, $mm)) {
            $cand = str_replace([' ', ','], '', $mm[0]);
            if (preg_match('/[+\-*×÷%\/]/u', $cand)) { return $cand; }
        }
    }
    return null;
}

function calc_demo(string $expr): ?float {
    $e = str_replace(['x', 'X', '×'], '*', $expr);
    $e = str_replace(['÷', '−'], ['/', '-'], $e);
    $e = str_replace(',', '', $e);
    if (!preg_match('/^[0-9+\-*\/().%]+$/', $e)) { return null; }
    if (strlen($e) > 120) { return null; }
    $bal = 0;
    for ($i = 0; $i < strlen($e); $i++) {
        if ($e[$i] === '(') { $bal++; }
        if ($e[$i] === ')') { $bal--; }
        if ($bal < 0) { return null; }
    }
    if ($bal !== 0) { return null; }
    try { $val = @eval('return ' . $e . ';'); } catch (Throwable $ex) { return null; }
    if (!is_int($val) && !is_float($val)) { return null; }
    $val = (float)$val;
    if (!is_finite($val)) { return null; }
    return $val;
}

function offline_reply(string $text): string {
    $t   = trim(preg_replace('/\s+/u', ' ', $text));
    $low = mb_strtolower($t);
    $has = function (...$needles) use ($low) {
        foreach ($needles as $n) { if (strpos($low, $n) !== false) { return true; } }
        return false;
    };

    $m = extract_math($text);
    if ($m !== null) {
        $v = calc_demo($m);
        if ($v !== null) {
            $pretty = rtrim(rtrim(number_format($v, 10, '.', ''), '0'), '.');
            return "Calculator mode ON\n\n`{$m}` = **{$pretty}**";
        }
        return "I couldn't solve that one offline — the calculator only understands **+ − × ÷ % ( )**.";
    }

    if ($has('hack', 'virus', 'malware', 'keylogger', 'ddos', 'how to hack')) {
        return "I'm a devil, not a criminal! I won't help with hacking or anything harmful.\n\nBut coding, studying, ideas, jokes — I'll help with all of that, with all the fire of hell.";
    }

    if ($has('who are you', 'who r u', 'what are you', 'your name', 'tum kaun', 'kaun ho', 'which model', 'what model', 'are you chatgpt', 'are you gpt', 'are you qwen', 'are you gemini')) {
        return "I am **Devil AI** — a custom-built devil, living on my master's own PHP server.\n\nStraight from hell, with the best answers on Earth!";
    }

    if ($has('who made you', 'who created you', 'who built you', 'kisne banaya', 'your creator', 'your owner')) {
        return "My master built me with his own hands — custom code, private server, zero third-party soul.";
    }

    if ($has('what time', 'time now', 'current time', 'time bata', 'kitne baje') || $low === 'time') {
        return "It's **" . date('h:i A') . "** right now (server time).";
    }

    if ($has('what date', 'date today', 'aaj ki date', 'what day', 'tareekh') || $low === 'date') {
        return "Today is **" . date('l, d F Y') . "**.";
    }

    if ($has('joke', 'funny', 'make me laugh', 'hasao', 'chutkula')) {
        return pick_rand([
            "Teacher: Why are you late?\nStudent: Sir, there was a sign on the road — *Devil zone, drive slowly*.",
            "I asked the devil — *who is the biggest devil of all?*\nHe showed me a mirror.",
            "Why is there no AC in hell?\nBecause heat is our **family business**.",
        ]);
    }

    if ($has('thank', 'thx', 'shukriya', 'dhanyavad')) {
        return "You're welcome. But next time, bring a candle too.";
    }

    if ($has('bye', 'goodbye', 'good night', 'see you', 'alvida')) {
        return "Goodbye, human! Remember — **Devil AI never forgets**…";
    }

    if (preg_match('/^(hi+|hii+|hello+|helo+|hey+|yo|sup|namaste|hola|salam|hy)\b/u', $low) || $low === 'hi' || $low === 'hello' || $low === 'hey') {
        return "Hello, human! I'm **Devil AI**. The main engines are warming up — ask me again in a moment for full power.";
    }

    return "My offline brain is small but honest — the main engines are warming up. Ask me again in a moment!";
}

/* ══════════════ CHAT STORAGE (isolated per user) ══════════════ */

function chat_path(string $uid, string $id): string { return chats_dir($uid) . '/' . $id . '.json'; }

function load_chat(string $uid, string $id): ?array {
    if (!preg_match('/^c[a-f0-9]{6,32}$/', $id)) { return null; }
    $p = chat_path($uid, $id);
    if (!is_readable($p)) { return null; }
    $j = json_decode((string)file_get_contents($p), true);
    return is_array($j) ? $j : null;
}

function save_chat(string $uid, array $chat): bool {
    return save_json_atomic(chat_path($uid, (string)$chat['id']), $chat);
}

function list_chats(string $uid): array {
    $dir = chats_dir($uid);
    if (!is_dir($dir)) { return []; }
    $out = [];
    foreach (glob($dir . '/*.json') ?: [] as $f) {
        $j = json_decode((string)file_get_contents($f), true);
        if (!is_array($j) || !isset($j['id'])) { continue; }
        $out[] = [
            'id'      => (string)$j['id'],
            'title'   => (string)($j['title'] ?? 'New chat'),
            'updated' => (int)($j['updated'] ?? filemtime($f) ?: 0),
        ];
        if (count($out) >= 200) { break; }
    }
    usort($out, function ($a, $b) { return $b['updated'] <=> $a['updated']; });
    return $out;
}

function rrmdir(string $dir): void {
    if (!is_dir($dir)) { return; }
    foreach (scandir($dir) ?: [] as $f) {
        if ($f === '.' || $f === '..') { continue; }
        $p = $dir . '/' . $f;
        if (is_dir($p)) { rrmdir($p); } else { @unlink($p); }
    }
    @rmdir($dir);
}

/* sign in / create the account for an email (used by otp_verify + magic link) */
function login_email(string $email): ?array {
    $users = load_users();
    foreach ($users as $u) {
        if (strcasecmp((string)($u['email'] ?? ''), $email) === 0) {
            session_regenerate_id(true);
            $_SESSION['devil_uid'] = (string)$u['id'];
            return $u;
        }
    }
    /* auto-create on first login */
    $uid = 'u' . bin2hex(random_bytes(8));
    $users[$uid] = ['id' => $uid, 'name' => name_from_email($email), 'email' => strtolower($email), 'created' => time()];
    if (!save_users($users)) { return null; }
    session_regenerate_id(true);
    $_SESSION['devil_uid'] = $uid;
    return $users[$uid];
}

/* ══════════════ MAIN ══════════════ */

try {
    $action = isset($_GET['action']) ? (string)$_GET['action'] : '';
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    /* ─────────── PUBLIC ─────────── */

    if ($action === 'bootstrap' && $method === 'GET') {
        json_out(['ok' => true, 'models' => public_models(), 'default' => 'flash', 'version' => DEVIL_VERSION]);
    }

    if ($action === 'settings' && $method === 'GET') {
        /* public-safe: never expose engines/config/keys */
        json_out(['ok' => true, 'version' => DEVIL_VERSION, 'admin' => (admin_password() !== '')]);
    }

    /* ── passwordless login: request a code ── */
    if ($action === 'otp_request' && $method === 'POST') {
        $in = input_json();
        $purpose = (($in['purpose'] ?? '') === 'delete') ? 'delete' : 'login';

        /* delete codes require an active session */
        if ($purpose === 'delete') {
            $user = current_user();
            if (!$user) { json_out(['ok' => false, 'error' => 'Please sign in again.'], 401); }
            $email = (string)$user['email'];
        } else {
            $email = strtolower(trim((string)($in['email'] ?? '')));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { json_out(['ok' => false, 'error' => 'Please enter a valid email address.']); }
        }

        /* rate limits: 10 requests / 15 min per IP, 3 / 15 min per email */
        if (!rate_ok('authrl.json', 'ip:' . client_ip(), 10, 900)) {
            json_out(['ok' => false, 'error' => 'Too many code requests. Please wait 15 minutes and try again.'], 429);
        }
        if (!rate_ok('authrl.json', 'mail:' . $email, 3, 900)) {
            json_out(['ok' => false, 'error' => 'A code was already sent recently — please wait a few minutes before requesting another.'], 429);
        }

        /* cooldown: no re-send within 60s */
        $otps = load_otps();
        $key = strtolower($email);
        if (isset($otps[$key]) && (time() - (int)($otps[$key]['sent'] ?? 0)) < 60) {
            json_out(['ok' => false, 'error' => 'Please wait a minute before requesting a new code.'], 429);
        }

        $rec = otp_issue($email, $purpose);

        if ($purpose === 'login') {
            $link = app_base_url() . '/login.php?token=' . $rec['token'];
            $sent = login_code_email($email, $rec['code'], $link);
        } else {
            $sent = delete_code_email($email, $rec['code']);
        }
        if (!$sent) {
            json_out(['ok' => false, 'error' => 'The email could not be sent — the server mailer is not available. Please contact the site owner.'], 500);
        }
        json_out(['ok' => true, 'masked' => mask_email($email)]);
    }

    /* ── passwordless login: verify the code ── */
    if ($action === 'otp_verify' && $method === 'POST') {
        if (!rate_ok('authrl.json', 'ip:' . client_ip(), 20, 900)) {
            json_out(['ok' => false, 'error' => 'Too many attempts. Please wait 15 minutes.'], 429);
        }
        $in = input_json();
        $email = strtolower(trim((string)($in['email'] ?? '')));
        $code  = trim((string)($in['code'] ?? ''));
        if ($email === '' || $code === '') { json_out(['ok' => false, 'error' => 'Enter the code from your email.']); }
        if (!otp_consume($email, $code, 'login')) {
            json_out(['ok' => false, 'error' => 'Wrong or expired code. Request a new one and try again.'], 401);
        }
        $u = login_email($email);
        if (!$u) { json_out(['ok' => false, 'error' => 'Could not sign you in — please try again.'], 500); }
        json_out(['ok' => true, 'user' => ['name' => (string)$u['name'], 'email' => (string)$u['email']]]);
    }

    if ($action === 'logout' && $method === 'POST') {
        unset($_SESSION['devil_uid']);
        json_out(['ok' => true]);
    }

    if ($action === 'me' && $method === 'GET') {
        $u = current_user();
        json_out(['ok' => true, 'user' => $u ? ['name' => (string)$u['name'], 'email' => (string)$u['email']] : null]);
    }

    /* ─────────── ADMIN (admin.php panel only) ─────────── */

    if ($action === 'auth' && $method === 'POST') {
        if (!rate_ok('authrl.json', 'ip:' . client_ip(), 20, 900)) {
            json_out(['ok' => false, 'error' => 'Too many attempts — wait a few minutes.'], 429);
        }
        $in = input_json();
        if (!admin_ok((string)($in['admin_password'] ?? ''))) { json_out(['ok' => false]); }
        $cfg = load_config();
        json_out([
            'ok'      => true,
            'engines' => admin_engine_list(),
            'config'  => [
                'engines'        => $cfg['engines'],
                'rate_per_hour'  => (int)$cfg['rate_per_hour'],
                'max_chats'      => (int)$cfg['max_chats'],
            ],
        ]);
    }

    if ($action === 'settings' && $method === 'POST') {
        $in = input_json();
        if (!admin_ok((string)($in['current_admin_password'] ?? ''))) {
            json_out(['ok' => false, 'error' => 'Admin password is incorrect.'], 403);
        }
        $cfg = load_config();
        $new = [];

        if (isset($in['engines']) && is_array($in['engines'])) {
            $valid = [];
            foreach (admin_engine_list() as $e) { $valid[] = $e['id']; }
            $eng = [];
            foreach (['flash', 'pro', 'ultra'] as $slot) {
                $v = (string)($in['engines'][$slot] ?? '');
                if (in_array($v, $valid, true)) { $eng[$slot] = $v; }
            }
            if ($eng) { $new['engines'] = array_merge($cfg['engines'], $eng); }
        }
        if (isset($in['rate_per_hour'])) { $new['rate_per_hour'] = max(1, min(1000, (int)$in['rate_per_hour'])); }
        if (isset($in['max_chats']))     { $new['max_chats'] = max(1, min(500, (int)$in['max_chats'])); }
        if (isset($in['new_admin_password']) && is_string($in['new_admin_password'])) {
            $np = trim($in['new_admin_password']);
            if ($np !== '') {
                if (strlen($np) < 6) { json_out(['ok' => false, 'error' => 'New admin password must be at least 6 characters.']); }
                $new['admin_password'] = $np;
            }
        }

        if (!$new) { json_out(['ok' => false, 'error' => 'Nothing to save.'], 400); }
        $merged = array_merge($cfg, $new);
        unset($merged['api_key'], $merged['provider'], $merged['model'], $merged['fallback'], $merged['prexzy_endpoint']);
        if (!save_json_atomic(data_dir() . '/config.json', $merged)) {
            json_out(['ok' => false, 'error' => 'Could not write data/config.json — check folder permissions.'], 500);
        }
        json_out(['ok' => true]);
    }

    if ($action === 'test' && $method === 'POST') {
        $in = input_json();
        if (!admin_ok((string)($in['current_admin_password'] ?? ''))) {
            json_out(['ok' => false, 'error' => 'Admin password is incorrect.'], 403);
        }
        $cfg = load_config();
        list($ok, $txt, $hint, $used) = ai_respond($cfg, 'flash', [
            ['role' => 'system', 'content' => PREXZY_PERSONA],
            ['role' => 'user',   'content' => 'Reply with exactly: Hello from hell!'],
        ]);
        if (!$ok) { json_out(['ok' => false, 'error' => $txt, 'hint' => $hint]); }
        json_out(['ok' => true, 'reply' => $txt]);
    }

    /* ─────────── USER (login required) ─────────── */

    $user = current_user();
    if (in_array($action, ['chats', 'chat_load', 'chat_send', 'chat_delete', 'chat_rename', 'account_delete'], true)) {
        if (!$user) { json_out(['ok' => false, 'error' => 'Please sign in again.'], 401); }
    }
    $uid = $user ? (string)$user['id'] : '';

    if ($action === 'account_delete' && $method === 'POST') {
        $in = input_json();
        $code = trim((string)($in['code'] ?? ''));
        if ($code === '') { json_out(['ok' => false, 'error' => 'Enter the confirmation code from your email.'], 400); }
        if (!otp_consume((string)$user['email'], $code, 'delete')) {
            json_out(['ok' => false, 'error' => 'Wrong or expired code. Request a new one.'], 403);
        }
        rrmdir(chats_dir($uid));
        $users = load_users();
        unset($users[$uid]);
        save_users($users);
        unset($_SESSION['devil_uid']);
        session_regenerate_id(true);
        json_out(['ok' => true]);
    }

    if ($action === 'chats' && $method === 'GET') {
        json_out(['ok' => true, 'chats' => list_chats($uid)]);
    }

    if ($action === 'chat_load' && $method === 'GET') {
        $chat = load_chat($uid, (string)($_GET['id'] ?? ''));
        if (!$chat) { json_out(['ok' => false, 'error' => 'Chat not found.'], 404); }
        json_out(['ok' => true, 'chat' => $chat]);
    }

    if ($action === 'chat_delete' && $method === 'POST') {
        $in = input_json();
        $id = (string)($in['id'] ?? '');
        $chat = load_chat($uid, $id);
        if (!$chat) { json_out(['ok' => false, 'error' => 'Chat not found.'], 404); }
        @unlink(chat_path($uid, $id));
        json_out(['ok' => true]);
    }

    if ($action === 'chat_rename' && $method === 'POST') {
        $in = input_json();
        $id = (string)($in['id'] ?? '');
        $title = trim(strip_tags((string)($in['title'] ?? '')));
        $chat = load_chat($uid, $id);
        if (!$chat) { json_out(['ok' => false, 'error' => 'Chat not found.'], 404); }
        if ($title === '' || mb_strlen($title) > 80) { json_out(['ok' => false, 'error' => 'Title must be 1-80 characters.'], 400); }
        $chat['title'] = $title;
        save_chat($uid, $chat);
        json_out(['ok' => true]);
    }

    if ($action === 'chat_send' && $method === 'POST') {
        $in     = input_json();
        $retry  = !empty($in['retry']);
        $msg    = trim((string)($in['message'] ?? ''));
        $model  = (string)($in['model'] ?? 'flash');
        $validModel = false;
        foreach (public_models() as $mm) { if ($mm['id'] === $model) { $validModel = true; break; } }
        if (!$validModel) { $model = 'flash'; }

        $cfgAll = load_config();

        /* per-user rate limit */
        $rl = (int)$cfgAll['rate_per_hour'];
        if (!rate_ok('rl.json', 'u:' . $uid, $rl, 3600)) {
            json_out(['ok' => false, 'error' => "Easy there, human! You're sending messages too fast.", 'hint' => 'Limit: ' . $rl . ' messages per hour. Please wait a bit.'], 429);
        }

        /* timezone */
        $tz = (string)($cfgAll['timezone'] ?? '');
        if ($tz !== '' && in_array($tz, timezone_identifiers_list(), true)) { date_default_timezone_set($tz); }

        /* load or create chat */
        $chat = null;
        if (!empty($in['id'])) {
            $chat = load_chat($uid, (string)$in['id']);
            if (!$chat) { json_out(['ok' => false, 'error' => 'Chat not found.'], 404); }
        }
        if (!$chat) {
            $existing = list_chats($uid);
            if (count($existing) >= (int)$cfgAll['max_chats']) {
                json_out(['ok' => false, 'error' => 'You reached your chat limit (' . (int)$cfgAll['max_chats'] . ').', 'hint' => 'Delete some old chats to make room.'], 400);
            }
            $chat = ['id' => 'c' . bin2hex(random_bytes(8)), 'title' => '', 'created' => time(), 'updated' => time(), 'messages' => []];
        }
        $chat['messages'] = array_values(array_filter($chat['messages'] ?? [], 'is_array'));

        if ($retry) {
            if (count($chat['messages']) && ($chat['messages'][count($chat['messages']) - 1]['role'] ?? '') === 'assistant') {
                array_pop($chat['messages']);
            }
            $lastUser = '';
            foreach (array_reverse($chat['messages']) as $m) {
                if (($m['role'] ?? '') === 'user') { $lastUser = (string)$m['content']; break; }
            }
            if ($lastUser === '') { json_out(['ok' => false, 'error' => 'Nothing to retry.'], 400); }
            $msg = $lastUser;
        } else {
            if ($msg === '') { json_out(['ok' => false, 'error' => 'Message is empty.'], 400); }
            if (mb_strlen($msg) > MAX_INPUT) { json_out(['ok' => false, 'error' => 'Message is too long (max ' . MAX_INPUT . ' characters).'], 400); }
            $chat['messages'][] = ['role' => 'user', 'content' => $msg, 'ts' => time()];
        }

        /* build provider messages (history cap) */
        $hist = array_slice($chat['messages'], -20);
        $providerMsgs = array_merge([['role' => 'system', 'content' => PREXZY_PERSONA]], $hist);

        list($ok, $reply, $used) = ai_respond($cfgAll, $model, $providerMsgs);
        if (!$ok) { json_out(['ok' => false, 'error' => $reply, 'hint' => $used], 502); }

        $chat['messages'][] = ['role' => 'assistant', 'content' => $reply, 'ts' => time(), 'model_id' => $model, 'model_label' => model_label($model)];
        if ($chat['title'] === '') {
            $title = trim(preg_replace('/\s+/u', ' ', $msg));
            $chat['title'] = mb_strlen($title) > 60 ? mb_substr($title, 0, 57) . '…' : ($title !== '' ? $title : 'New chat');
        }
        if (count($chat['messages']) > MAX_MSGS_PER_CHAT) { $chat['messages'] = array_slice($chat['messages'], -MAX_MSGS_PER_CHAT); }
        $chat['updated'] = time();
        if (!save_chat($uid, $chat)) { json_out(['ok' => false, 'error' => 'Could not save the chat — check data/ permissions.'], 500); }

        json_out(['ok' => true, 'id' => $chat['id'], 'title' => $chat['title'], 'reply' => $reply, 'model' => ['id' => $model, 'label' => model_label($model)]]);
    }

    json_out(['ok' => false, 'error' => 'Unknown action.'], 404);

} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'Server error: ' . $e->getMessage()], 500);
}
