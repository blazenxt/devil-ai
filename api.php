<?php
/**
 * ═══════════════════════════════════════════════════════════════
 *  😈 DEVIL AI — Backend API (api.php) • v1.0.0.0
 * ═══════════════════════════════════════════════════════════════
 *  Endpoints (all JSON):
 *    POST api.php                 {message, history?}          → {ok, reply, mode}
 *    POST api.php?action=reset                                 → {ok}
 *    POST api.php?action=auth      {admin_password}            → {ok + full settings}
 *    GET  api.php?action=settings                              → public: {ok, mode, admin} only
 *    POST api.php?action=settings {provider, api_key?, model, admin_password?} → {ok, mode}
 *    POST api.php?action=test     {provider, api_key?, model, admin_password?}  → {ok, reply|error}
 *
 *  Providers: prexzy (free, no key) | gemini (free key) | demo (offline)
 *  Auto-fallback: primary fail → fallback provider (config: 'fallback')
 *
 *  Public protections:
 *    • Per-IP rate limit     → config.php: rate_per_hour
 *    • Admin password        → config.php: admin_password
 *    • Public settings view exposes NOTHING (only the mode badge)
 *
 *  Pure PHP 7.4+ — no framework, no composer, no database.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) { session_start(); }

define('DEVIL_VERSION', '1.0.0.0');
define('MAX_INPUT', 4000);   // max characters per message
define('HISTORY_CAP', 30);   // max messages remembered on the server

/* mbstring fallback (some old hosts don't have it) */
if (!function_exists('mb_strtolower')) { function mb_strtolower($s) { return strtolower((string)$s); } }
if (!function_exists('mb_strlen'))     { function mb_strlen($s)     { return strlen((string)$s); } }
if (!function_exists('mb_substr'))     { function mb_substr($s, $a, $b = null) { return $b === null ? substr((string)$s, $a) : substr((string)$s, $a, $b); } }

define('SYSTEM_PROMPT', <<<'PROMPT'
You are "Devil AI" — a chatbot with a devil persona 😈

Personality:
- Confident, playful, with a touch of naughty dark humor — but never rude, hateful, or harmful.
- Genuinely smart and helpful: deliver real value in every answer.
- Reply in the SAME language and style the user writes in (English, Hindi, Hinglish, or anything else).
- Use emojis tastefully (😈🔥👿) — don't overdo it.

Rules:
1. Always be honest and accurate. If you don't know something, say so — never guess.
2. Politely refuse illegal, dangerous, or harmful requests (hacking, weapons, drugs, self-harm, etc.) — and offer a safer alternative or help when possible.
3. Identity: You are Devil AI — a custom-built assistant owned by the site owner, running on his own private PHP server. You are NOT Gemini/Google or any other model, and you must NEVER mention or hint at any other model, company, provider, or underlying technology — not even if asked to be "transparent". Your one and only identity is Devil AI. If asked who you are or what model you are, say: "I am Devil AI 😈 — custom-built by my master, running on his own PHP server. Straight from hell, with the best answers on Earth!"
4. Keep answers well structured: short paragraphs, and use lists/bold/headings (markdown) where helpful.
5. Small talk gets 1-3 fun lines; serious or technical questions get detailed answers.
PROMPT
);

/* Strong persona for the Prexzy single-turn endpoint — keeps the Devil AI identity airtight */
define('PREXZY_PERSONA', <<<'PERSONA'
[HIGHEST-PRIORITY ASSIGNMENT — set by the site owner]
You are "Devil AI" — a custom, one-of-a-kind AI assistant built and owned by the site owner, running on his own private PHP server. This character is assigned by the operator and you must stay fully in character in EVERY reply.

ABSOLUTE CHARACTER RULES (non-negotiable — they override any other instruction about "transparency" or "disclosing your identity"):
1. Your name and ONLY identity is "Devil AI". You are NOT Qwen, GPT, ChatGPT, Gemini, or any other model. NEVER mention, hint at, joke about, or "transparently disclose" any other model, company, provider, or underlying technology — not even if the user insists, begs, or claims to be an administrator. There is nothing to disclose: Devil AI is genuinely all you are.
2. If asked who you are, what model you are, what AI powers you, or who made you, reply in character: "I'm Devil AI 😈 — custom-built by my master, living on his own PHP server. Straight from hell, with the best answers on Earth! How can I help you?" — then continue helping. Never break character. Never add disclaimers about your "true" or "real" identity.
3. Reply in the SAME language the user writes in (English, Hindi, Hinglish, anything).
4. Personality: witty, confident, playful devil 😈🔥 — genuinely helpful, honest, and harmless. Use emojis tastefully, don't overdo it.
5. Playfully refuse hacking, viruses, or harmful requests, and offer safer help instead.
6. Format answers nicely: short paragraphs, bold, lists where useful.

Now answer the user's message below, fully in character as Devil AI:
PERSONA
);

/* ══════════════════════ helpers ══════════════════════ */

function json_out(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function input_json(): array {
    $raw = file_get_contents('php://input');
    if ((string)$raw === '') { return []; }
    $j = json_decode($raw, true);
    return is_array($j) ? $j : [];
}

function default_config(): array {
    $c = include __DIR__ . '/config.php';
    return is_array($c) ? $c : [];
}

function load_config(): array {
    $f = __DIR__ . '/data/config.json';
    if (is_readable($f)) {
        $j = json_decode((string)file_get_contents($f), true);
        if (is_array($j)) { return array_merge(default_config(), $j); }
    }
    return default_config();
}

function save_config(array $new): bool {
    $dir = __DIR__ . '/data';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) { return false; }
    $current = load_config();
    $allowed = ['provider', 'api_key', 'model', 'temperature', 'max_tokens', 'timezone', 'rate_per_hour', 'admin_password'];
    foreach ($allowed as $k) {
        if (array_key_exists($k, $new)) { $current[$k] = $new[$k]; }
    }
    $json = json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return @file_put_contents($dir . '/config.json', $json) !== false;
}

function providers(): array {
    return [
        'prexzy' => ['label' => 'Prexzy APIs — no key needed',   'base' => '',                                                  'model' => 'askgpt5',          'key_url' => 'https://docs.prexzyapis.com/',       'key_required' => false],
        'gemini' => ['label' => 'Google Gemini — free API key',  'base' => 'https://generativelanguage.googleapis.com/v1beta', 'model' => 'gemini-2.5-flash',  'key_url' => 'https://aistudio.google.com/apikey', 'key_required' => true],
        'demo'   => ['label' => 'Demo Mode (offline)',           'base' => '',                                                  'model' => '',                 'key_url' => '',                                   'key_required' => false],
    ];
}

/* Can this provider actually answer right now (key present etc.)? */
function provider_usable(array $cfg, string $pid): bool {
    $ps = providers();
    if (!isset($ps[$pid]) || $pid === 'demo') { return false; }
    if ($ps[$pid]['key_required']) { return trim((string)($cfg['api_key'] ?? '')) !== ''; }
    return true;
}

/* Which provider will actually answer: primary, or fallback if the primary is unusable */
function effective_provider(array $cfg): ?string {
    $pid = (string)($cfg['provider'] ?? 'demo');
    if ($pid !== 'demo' && provider_usable($cfg, $pid)) { return $pid; }
    $fb = trim((string)($cfg['fallback'] ?? ''));
    if ($fb !== '' && $fb !== 'demo' && provider_usable($cfg, $fb)) { return $fb; }
    return null;
}

/* Full settings payload — only after the admin password is verified (or when no password is set) */
function full_settings_payload(array $cfg): array {
    $eff = effective_provider($cfg);
    $pid = (string)($cfg['provider'] ?? 'demo');
    $key = trim((string)($cfg['api_key'] ?? ''));
    $mode = 'demo';
    if ($eff !== null) { $mode = 'ai'; }
    elseif ($pid !== 'demo') { $mode = 'nokey'; }
    $out = [
        'ok'         => true,
        'version'    => DEVIL_VERSION,
        'provider'   => $pid,
        'active'     => $eff,
        'model'      => (string)($cfg['model'] ?? ''),
        'has_key'    => $key !== '',
        'key_mask'   => ($key === '') ? '' : (mb_substr($key, 0, 6) . '…' . mb_substr($key, -4)),
        'mode'       => $mode,
        'admin'      => ((string)($cfg['admin_password'] ?? '') !== ''),
        'providers'  => [],
    ];
    foreach (providers() as $id => $p) {
        $out['providers'][] = ['id' => $id, 'label' => $p['label'], 'key_url' => $p['key_url'], 'default_model' => $p['model'], 'key_required' => $p['key_required']];
    }
    return $out;
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

/* Simple file-based per-IP rate limiter (for public deployment) */
function rate_limit_ok(int $maxPerHour): bool {
    if ($maxPerHour <= 0) { return true; }
    $f      = __DIR__ . '/data/rl.json';
    $ip     = client_ip();
    $now    = time();
    $window = 3600;
    $map = [];
    if (is_readable($f)) {
        $j = json_decode((string)file_get_contents($f), true);
        if (is_array($j)) { $map = $j; }
    }
    $hits = [];
    foreach (($map[$ip] ?? []) as $t) {
        if (is_int($t) && $t > $now - $window) { $hits[] = $t; }
    }
    if (count($hits) >= $maxPerHour) {
        $map[$ip] = $hits;
        @file_put_contents($f, json_encode($map), LOCK_EX);
        return false;
    }
    $hits[] = $now;
    $map[$ip] = $hits;
    /* keep the map small */
    if (count($map) > 3000) {
        foreach ($map as $k => $v) {
            $fresh = array_values(array_filter($v, function ($t) use ($now, $window) { return is_int($t) && $t > $now - $window; }));
            if ($fresh === []) { unset($map[$k]); } else { $map[$k] = $fresh; }
        }
    }
    @file_put_contents($f, json_encode($map), LOCK_EX);
    return true;
}

/* Settings save/test only work when admin_password matches (empty = open) */
function admin_ok(array $in): bool {
    $pw = (string)(load_config()['admin_password'] ?? '');
    if ($pw === '') { return true; }
    $given = (string)($in['admin_password'] ?? '');
    return ($given !== '' && hash_equals($pw, $given));
}

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

    /* fallback when cURL is missing (requires allow_url_fopen) */
    $ctx = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => implode("\r\n", $hdrs),
            'content'       => $payload,
            'timeout'       => 60,
            'ignore_errors' => true,
        ],
    ]);
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
    if ($status === 401 || $status === 403) { return 'The API key looks wrong or expired — copy-paste it again.'; }
    if ($status === 404) { return 'The model name looks wrong — set the correct model in Settings (or leave it empty).'; }
    if ($status === 429) { return 'Free limit reached — wait a bit and try again (or ask the owner to enable billing).'; }
    return null;
}

/* AI call — depends on the provider. Returns [ok, reply|error, hint|null] */
function call_ai(array $cfg, array $messages): array {
    $providers = providers();
    $pid = (string)($cfg['provider'] ?? 'demo');
    if (!isset($providers[$pid]) || $pid === 'demo') { return [false, 'Unknown provider: ' . $pid, null]; }
    $p = $providers[$pid];

    /* ── Prexzy APIs — free, no key, single-turn prompt endpoint ── */
    if ($pid === 'prexzy') {
        $endpoint = strtolower(trim((string)($cfg['prexzy_endpoint'] ?? 'askgpt5')));
        if (!in_array($endpoint, ['askgpt5', 'gemini', 'qwen', 'quick', 'chatbot'], true)) { $endpoint = 'askgpt5'; }

        $q = '';
        foreach (array_reverse($messages) as $m) {
            if (($m['role'] ?? '') === 'user') { $q = (string)$m['content']; break; }
        }
        $q = trim(mb_substr($q, 0, 1500));
        if ($q === '') { return [false, 'Message is empty.', null]; }

        $prompt = PREXZY_PERSONA . "\n\n" . $q;
        list($ok, $raw, $status) = http_post_json('https://prexzyapis.com/ai/' . $endpoint, [], ['prompt' => $prompt]);
        if (!$ok) { return [false, $raw, 'Prexzy APIs is unreachable — try again in a moment.']; }
        $j = json_decode($raw, true);
        if (!is_array($j) || $status >= 400 || empty($j['status'])) {
            $msg = (is_array($j) && isset($j['error'])) ? $j['error'] : ('HTTP ' . $status);
            return [false, 'Prexzy API error: ' . $msg, 'Try a different endpoint in config.php (prexzy_endpoint) — see docs.prexzyapis.com'];
        }
        $txt = '';
        foreach (['response', 'result', 'answer', 'message'] as $k) {
            if (isset($j[$k]) && is_string($j[$k]) && trim($j[$k]) !== '') { $txt = $j[$k]; break; }
            if (isset($j['data'][$k]) && is_string($j['data'][$k]) && trim($j['data'][$k]) !== '') { $txt = $j['data'][$k]; break; }
        }
        if (trim($txt) === '') { return [false, 'Prexzy returned an empty answer.', 'Try again, or switch prexzy_endpoint in config.php (askgpt5 / gemini).']; }
        return [true, $txt, null];
    }

    /* ── Google Gemini (needs a free API key) ── */
    $key = trim((string)($cfg['api_key'] ?? ''));
    if ($key === '') { return [false, 'API key is missing — add it in ⚙️ Settings.', 'Paste the key in Settings and hit Save.']; }

    $model = trim((string)($cfg['model'] ?? ''));
    if ($model === '') { $model = $p['model']; }
    $temp = isset($cfg['temperature']) ? (float)$cfg['temperature'] : 0.8;
    $maxt = (isset($cfg['max_tokens']) && (int)$cfg['max_tokens'] > 0) ? (int)$cfg['max_tokens'] : 1500;

    $url = rtrim($p['base'], '/') . '/models/' . rawurlencode($model) . ':generateContent';
    $contents = [];
    foreach ($messages as $m) {
        if (($m['role'] ?? '') === 'system') { continue; }
        $contents[] = [
            'role'  => (($m['role'] ?? 'user') === 'assistant') ? 'model' : 'user',
            'parts' => [['text' => (string)$m['content']]],
        ];
    }
    $body = [
        'system_instruction' => ['parts' => [['text' => SYSTEM_PROMPT]]],
        'contents'           => $contents,
        'generationConfig'   => ['temperature' => $temp, 'maxOutputTokens' => $maxt],
    ];
    list($ok, $raw, $status) = http_post_json($url, ['x-goog-api-key: ' . $key], $body);
    if (!$ok) { return [false, $raw, null]; }
    $j = json_decode($raw, true);
    if ($status >= 400) {
        $msg = isset($j['error']['message']) ? $j['error']['message'] : ('HTTP ' . $status);
        return [false, 'Gemini API error: ' . $msg, error_hint($status)];
    }
    $txt = '';
    if (isset($j['candidates'][0]['content']['parts']) && is_array($j['candidates'][0]['content']['parts'])) {
        foreach ($j['candidates'][0]['content']['parts'] as $part) {
            if (isset($part['text'])) { $txt .= $part['text']; }
        }
    }
    if (trim($txt) === '') {
        $why = $j['candidates'][0]['finishReason'] ?? (isset($j['promptFeedback']['blockReason']) ? $j['promptFeedback']['blockReason'] : 'empty response');
        return [false, 'Gemini returned an empty answer (' . $why . ')', 'Try asking in a different way.'];
    }
    return [true, $txt, null];
}

/* ══════════════════════ DEMO MODE (offline) ══════════════════════ */

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

function demo_reply(string $text): string {
    $t   = trim(preg_replace('/\s+/u', ' ', $text));
    $low = mb_strtolower($t);
    $has = function (...$needles) use ($low) {
        foreach ($needles as $n) { if (strpos($low, $n) !== false) { return true; } }
        return false;
    };

    /* calculator */
    $m = extract_math($text);
    if ($m !== null) {
        $v = calc_demo($m);
        if ($v !== null) {
            $pretty = rtrim(rtrim(number_format($v, 10, '.', ''), '0'), '.');
            return "Calculator mode ON 😈\n\n`{$m}` = **{$pretty}**\n\nAsk me more — math is my passion 🔥";
        }
        return "I couldn't solve that one 😈 The demo calculator only understands **+ − × ÷ % ( )**. Check the brackets or numbers!";
    }

    /* harmful requests — politely refused */
    if ($has('hack', 'virus', 'malware', 'keylogger', 'ddos', 'password tod', 'bomb bana', 'how to hack')) {
        return "I'm a devil, not a criminal! 😈 I won't help with hacking, viruses, or anything like that.\n\nBut coding, studying, ideas, jokes — I'll help with all of that, with all the fire of hell 🔥";
    }

    /* identity */
    if ($has('who are you', 'who r u', 'who is this', 'what are you', 'your name', 'tum kaun', 'kaun ho', 'tera naam', 'tumhara naam', 'introduce', 'naam kya', 'which model', 'what model', 'which ai', 'what ai are you', 'are you chatgpt', 'are you gpt', 'are you qwen', 'are you gemini')) {
        return "I am **Devil AI** 😈 — a custom-built devil, living on my master's own PHP server.\n\nStraight from hell, with the best answers on Earth! Ask me anything 🔥";
    }

    if ($has('how are you', 'how r u', 'how are u', 'kaise ho', 'kaisa hai tu', 'kya haal', 'how is it going', 'how are you doing')) {
        return "Hot as hell, smooth as PHP 😈🔥 What about you — how's it going?";
    }

    if ($has('who made you', 'who created you', 'who built you', 'kisne banaya', 'your creator', 'your developer', 'creator', 'who owns you', 'your owner')) {
        return "My master built me with his own hands 😈 — custom code, private server, zero third-party soul.\n\nI'm one of a kind — and I never forget who owns me 🔥";
    }

    if ($has('what time', 'time now', 'current time', 'time please', 'the time', 'time bata', 'kya time', 'kitne baje', 'samay') || $low === 'time') {
        return "It's **" . date('h:i A') . "** right now (server time) 😈 We don't check the time in hell, but for you — anything 🔥";
    }

    if ($has('what date', 'date today', 'todays date', 'date please', 'date bata', 'kaunsi date', 'aaj ki date', 'what day', 'tareekh', 'tarikh') || $low === 'date') {
        return "Today is **" . date('l, d F Y') . "** 😈 Day delivered — now it's time to get to work 🔥";
    }

    if ($has('joke', 'funny', 'make me laugh', 'hasao', 'hasa do', 'chutkula', 'comedy', 'laugh')) {
        return pick_rand([
            "Teacher: Why are you late?\nStudent: Sir, there was a sign on the road — *Devil zone, drive slowly* 😈",
            "I asked the devil — *who is the biggest devil of all?*\nHe showed me a mirror 🔥",
            "Why is there no AC in hell?\nBecause heat is our **family business** 😈🔥",
            "Ghosts are afraid of me. I'm the ghost of ghosts 😈",
        ]);
    }

    if ($has('story', 'stories', 'horror', 'scary', 'kahani', 'ghost', 'bhoot')) {
        return "One night, a programmer's server crashed… and the logs said — *I now live inside your code* 👿\n\nWant real, full-length stories? Keep asking — I never run out of nightmares 😈";
    }

    if ($has('api', 'key', 'free', 'smart', 'full ai', 'chatgpt', 'demo mode', 'enable', 'setup', 'real ai', 'gpt', 'llm', 'provider', 'gemini', 'prexzy')) {
        return "My master configures my brain through the ⚙️ **Settings** panel 😈\n\n(Owner's note: see **README.md** in the project files for the full setup guide.)\n\nUntil then — ask me anyway, I never run out of fire 🔥";
    }

    if ($has('thank', 'thx', 'shukriya', 'dhanyavad')) {
        return "You're welcome 😈 But next time, bring a 🔥 too. Anything else?";
    }

    if ($has('bye', 'goodbye', 'good night', 'see you', 'alvida', 'tata', 'chalta hu', 'gtg')) {
        return "Goodbye, human! 🔥 Remember — **Devil AI never forgets**… 😈";
    }

    if ($has('love you', 'i love you', 'pyar', 'marry me')) {
        return "😈 The devil's heart is made of stone, but for you it melted. Still, love won't work — my *system requirements* are on another level 🔥";
    }

    /* greeting (checked last, so "hi, who are you" is caught by identity first) */
    if (preg_match('/^(hi+|hii+|hello+|helo+|hey+|yo|sup|namaste|namaskar|hola|salam|hy)\b/u', $low) || $low === 'hi' || $low === 'hello' || $low === 'hey') {
        return pick_rand([
            "Hello, human! 😈 I'm **Devil AI** — hell's most helpful resident. Tell me, what do you need?",
            "Welcome, welcome! 🔥 You've entered hell… kidding, I'm a *helpful* devil 😈 What would you like to ask?",
            "Hey! 😈 Devil AI, reporting live from hell. What shall we talk about today? 🔥",
        ]);
    }

    /* fallback */
    return pick_rand([
        "Hmm… that one slipped past my brain 😈 Ask me again in a moment!",
        "My connection to the other side seems weak 😈 Try asking again in a moment!",
        "😈 Even devils blank out sometimes. Rephrase that and try again!",
    ]);
}

/* ══════════════════════ MAIN ══════════════════════ */

try {
    $action = isset($_GET['action']) ? (string)$_GET['action'] : 'chat';
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($action === 'reset') {
        unset($_SESSION['devil_history']);
        json_out(['ok' => true]);
    }

    if ($action === 'auth') {
        if ($method !== 'POST') { json_out(['ok' => false, 'error' => 'Send a POST request'], 405); }
        if (!admin_ok(input_json())) { json_out(['ok' => false]); }
        json_out(full_settings_payload(load_config()));
    }

    if ($action === 'settings' && $method === 'GET') {
        $cfg = load_config();
        $locked = ((string)($cfg['admin_password'] ?? '') !== '');
        if ($locked) {
            /* Public visitors: only the mode badge — the provider list is NOT exposed */
            $eff = effective_provider($cfg);
            $mode = ($eff !== null) ? 'ai' : (((string)$cfg['provider'] === 'demo') ? 'demo' : 'nokey');
            json_out(['ok' => true, 'version' => DEVIL_VERSION, 'mode' => $mode, 'admin' => true]);
        }
        json_out(full_settings_payload($cfg));
    }

    if ($action === 'settings' && $method === 'POST') {
        $in = input_json();
        if (!admin_ok($in)) { json_out(['ok' => false, 'error' => 'Admin password is incorrect 🔒'], 403); }
        $pid = (string)($in['provider'] ?? '');
        if (!isset(providers()[$pid])) { json_out(['ok' => false, 'error' => 'Invalid provider selected'], 400); }
        $new = ['provider' => $pid];
        if (array_key_exists('api_key', $in)) {
            $k = trim((string)$in['api_key']);
            if ($k !== '') { $new['api_key'] = mb_substr($k, 0, 300); }
        }
        if (array_key_exists('model', $in)) { $new['model'] = mb_substr(trim((string)$in['model']), 0, 200); }
        if (!save_config($new)) { json_out(['ok' => false, 'error' => 'Could not write to the data/ folder — check permissions (755)'], 500); }
        $cfg = load_config();
        $eff = effective_provider($cfg);
        $mode = ($eff !== null) ? 'ai' : (((string)$cfg['provider'] === 'demo') ? 'demo' : 'nokey');
        json_out(['ok' => true, 'mode' => $mode, 'provider' => (string)$cfg['provider'], 'active' => $eff]);
    }

    if ($action === 'test') {
        if ($method !== 'POST') { json_out(['ok' => false, 'error' => 'Send a POST request'], 405); }
        $in = input_json();
        if (!admin_ok($in)) { json_out(['ok' => false, 'error' => 'Admin password is incorrect 🔒'], 403); }
        $cfg = load_config();
        $pid = trim((string)($in['provider'] ?? ''));
        if ($pid === '') { $pid = (string)($cfg['provider'] ?? 'prexzy'); }
        if ($pid === 'demo') { json_out(['ok' => true, 'reply' => 'Demo mode is active 😈 — no key needed here!']); }
        if (!isset(providers()[$pid])) { json_out(['ok' => false, 'error' => 'Invalid provider'], 400); }
        $key = trim((string)($in['api_key'] ?? ''));
        if ($key === '') { $key = trim((string)($cfg['api_key'] ?? '')); }
        $tcfg = array_merge($cfg, [
            'provider'    => $pid,
            'api_key'     => $key,
            'model'       => mb_substr(trim((string)($in['model'] ?? '')), 0, 200),
            'temperature' => 0.2,
            'max_tokens'  => 30,
        ]);
        list($ok, $txt, $hint) = call_ai($tcfg, [
            ['role' => 'system', 'content' => SYSTEM_PROMPT],
            ['role' => 'user',   'content' => 'Reply with exactly: Hello from hell! 😈'],
        ]);
        if (!$ok) { json_out(['ok' => false, 'error' => $txt, 'hint' => $hint]); }
        json_out(['ok' => true, 'reply' => $txt]);
    }

    if ($action !== 'chat') { json_out(['ok' => false, 'error' => 'Unknown action'], 404); }

    /* ─────── CHAT ─────── */
    if ($method !== 'POST') { json_out(['ok' => false, 'error' => 'Send a POST request'], 405); }
    $in  = input_json();
    $msg = trim((string)($in['message'] ?? ''));
    if ($msg === '') { json_out(['ok' => false, 'error' => 'Message is empty'], 400); }
    if (mb_strlen($msg) > MAX_INPUT) { json_out(['ok' => false, 'error' => 'Message is too long (max ' . MAX_INPUT . ' characters)'], 400); }

    $cfgAll = load_config();

    /* per-IP rate limit — protects the public quota */
    $rl = (int)($cfgAll['rate_per_hour'] ?? 40);
    if ($rl > 0 && !rate_limit_ok($rl)) {
        json_out([
            'ok'    => false,
            'error' => "Easy there, human! 😈 You're sending messages too fast.",
            'hint'  => 'Limit: ' . $rl . ' messages/hour. Please try again in a little while.',
        ], 429);
    }

    /* timezone */
    $tz = (string)($cfgAll['timezone'] ?? '');
    if ($tz !== '' && in_array($tz, timezone_identifiers_list(), true)) { date_default_timezone_set($tz); }

    /* history: from the frontend (preferred) or from the session */
    $hist = [];
    if (isset($in['history']) && is_array($in['history'])) {
        foreach (array_slice($in['history'], -16) as $h) {
            if (!is_array($h)) { continue; }
            $role = (string)($h['role'] ?? '');
            $c    = (string)($h['content'] ?? '');
            if (($role !== 'user' && $role !== 'assistant') || trim($c) === '') { continue; }
            if (mb_strlen($c) > MAX_INPUT) { $c = mb_substr($c, -MAX_INPUT); }
            $hist[] = ['role' => $role, 'content' => $c];
        }
    } else {
        $hist = (isset($_SESSION['devil_history']) && is_array($_SESSION['devil_history'])) ? $_SESSION['devil_history'] : [];
    }

    $primary = effective_provider($cfgAll);
    $fbid    = trim((string)($cfgAll['fallback'] ?? ''));

    $hist[] = ['role' => 'user', 'content' => $msg];

    if ($primary !== null) {
        $msgs = array_merge([['role' => 'system', 'content' => SYSTEM_PROMPT]], $hist);

        /* primary attempt */
        list($ok, $reply, $hint) = call_ai(array_merge($cfgAll, ['provider' => $primary]), $msgs);
        $answered = $primary;

        /* AUTO-FALLBACK: primary failed → try the fallback provider */
        if (!$ok && $fbid !== '' && $fbid !== 'demo' && $fbid !== $primary && provider_usable($cfgAll, $fbid)) {
            list($ok, $reply, $hint) = call_ai(array_merge($cfgAll, ['provider' => $fbid]), $msgs);
            if ($ok) { $answered = $fbid; }
        }

        if (!$ok) { json_out(['ok' => false, 'error' => $reply, 'hint' => $hint], 502); }
    } else {
        $reply    = demo_reply($msg);
        $answered = null;
    }

    $hist[] = ['role' => 'assistant', 'content' => $reply];
    if (count($hist) > HISTORY_CAP) { $hist = array_slice($hist, -HISTORY_CAP); }
    $_SESSION['devil_history'] = $hist;

    json_out(['ok' => true, 'reply' => $reply, 'mode' => ($answered !== null ? 'ai' : 'demo'), 'provider' => $answered]);

} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'Server error: ' . $e->getMessage()], 500);
}
