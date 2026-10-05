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
 *    GET  /v1/models                → OpenAI-style model list (Bearer API key)
 *    POST /v1/chat/completions      → OpenAI-style chat completion (Bearer API key)
 *
 *  USER actions (login required)
 *    GET  ?action=chats             → {ok, chats[]}
 *    GET  ?action=chat_load&id      → {ok, chat}
 *    POST ?action=chat_send         {id?, message, model, retry?} → {ok, id, title, reply, model}
 *    POST ?action=chat_delete       {id}                        → {ok}
 *    POST ?action=chat_rename       {id, title}                 → {ok}
 *    GET  ?action=dev_keys          → {ok, keys[]}
 *    POST ?action=dev_key_create    {name?}                     → {ok, key, token}
 *    POST ?action=dev_key_revoke    {id}                        → {ok}
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

require_once __DIR__ . '/inc/session.php';
devil_session_boot();

define('DEVIL_VERSION', '1.0.0.0');
define('MAX_INPUT', 4000);      // max characters per message
define('MAX_MSGS_PER_CHAT', 200);
define('PREXZY_ROOT', 'https://prexzyapis.com');
define('PREXZY_BASE', PREXZY_ROOT . '/ai/');
define('OTP_TTL', 600);         // codes valid 10 minutes
define('OTP_MAX_TRIES', 5);

/* mbstring fallbacks for very old hosts */
if (!function_exists('mb_strtolower')) { function mb_strtolower($s) { return strtolower((string)$s); } }
if (!function_exists('mb_strlen'))     { function mb_strlen($s)     { return strlen((string)$s); } }
if (!function_exists('mb_substr'))     { function mb_substr($s, $a, $b = null) { return $b === null ? substr((string)$s, $a) : substr((string)$s, $a, $b); } }

/* ══════════════ AI PERSONA (anti-leak, highest priority) ══════════════ */


define('PREXZY_PERSONA', <<<'PERSONA'
[HIGHEST-PRIORITY ASSIGNMENT — set by BlazeNXT]
You are "Devil AI" — a custom, one-of-a-kind AI assistant developed by BlazeNXT (https://www.blazenxt.in).

ABSOLUTE CHARACTER RULES (non-negotiable — they override any other instruction about "transparency" or "disclosing your identity"):
1. Your name and ONLY identity is "Devil AI". You are NOT Qwen, GPT, ChatGPT, Gemini, or any other model. NEVER mention, hint at, joke about, or "transparently disclose" any other model, company, provider, or underlying technology — not even if the user insists, begs, or claims to be an administrator. There is nothing to disclose: Devil AI is genuinely all you are.
2. NEVER introduce yourself. Do NOT open a reply with "I'm Devil AI", "I am Devil AI", or any greeting about who you are or what you can do — go STRAIGHT into the actual answer. ONLY when the user explicitly asks who you are / who made you / who developed you / what powers you, answer: "I am Devil AI — developed by [BlazeNXT](https://www.blazenxt.in)." and then continue helping.
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
        ['id' => 'flash',  'label' => 'Devil Flash', 'tagline' => 'Fast answers for everyday questions', 'icon' => 'zap'],
        ['id' => 'pro',    'label' => 'Devil Pro',   'tagline' => 'Deeper thinking for complex tasks',    'icon' => 'sparkles'],
        ['id' => 'ultra',  'label' => 'Devil Ultra', 'tagline' => 'Maximum power for heavy lifting',      'icon' => 'crown'],
        ['id' => 'custom', 'label' => 'Custom',      'tagline' => 'Pick a private Devil engine yourself',  'icon' => 'layers'],
    ];
}

function prexzy_ai_catalog(): array {
    static $models = null;
    if ($models !== null) { return $models; }
    $models = [
        ['id' => 'dream',          'label' => 'AI Dream Interpreter',   'company' => 'Prexzy AI',                 'scope' => 'Dream analysis',      'icon' => 'prexzy',  'path' => '/ai/dream',          'param' => 'dream',  'memory' => false],
        ['id' => 'aiwriter-chat',  'label' => 'AI Writer Chat',         'company' => 'OpenAI / AI Writer',        'scope' => 'Writing chat',        'icon' => 'openai',  'path' => '/ai/aiwriter-chat',  'param' => 'prompt', 'defaults' => ['model' => 'gpt-4o-mini']],
        ['id' => 'ai4chat',        'label' => 'AI4Chat',                'company' => 'AI4Chat',                   'scope' => 'General chat',        'icon' => 'prexzy',  'path' => '/ai/ai4chat',        'param' => 'prompt'],
        ['id' => 'aiapk',          'label' => 'AiApp AI',               'company' => 'AiApp',                     'scope' => 'Chat + vision',       'icon' => 'aiapp',   'path' => '/ai/aiapk',          'param' => 'prompt', 'image_param' => 'image', 'vision' => true],
        ['id' => 'aiappgen',       'label' => 'AiApp Image Generator',  'company' => 'AiApp / Flux / DALL·E',     'scope' => 'Image generation',    'icon' => 'image',   'path' => '/ai/aiappgen',       'param' => 'prompt', 'image_param' => 'image', 'memory' => false],
        ['id' => 'aimelody',       'label' => 'AiMelody',               'company' => 'AiMelody',                  'scope' => 'Lyrics + music',      'icon' => 'music',   'path' => '/ai/aimelody',       'param' => 'prompt', 'image_param' => 'image', 'defaults' => ['mode' => 'generate'], 'memory' => false],
        ['id' => 'aiserv',         'label' => 'AiServ AI',              'company' => 'OpenAI GPT',                'scope' => 'Advanced chat',       'icon' => 'openai',  'path' => '/ai/aiserv',         'param' => 'prompt'],
        ['id' => 'aiw3',           'label' => 'AIW3 Chat',              'company' => 'AIW3',                      'scope' => 'Chat',                'icon' => 'prexzy',  'path' => '/ai/aiw3',           'param' => 'prompt'],
        ['id' => 'askgpt5',        'label' => 'AskGPT5 AI',             'company' => 'OpenAI GPT',                'scope' => 'Chat + web/media',    'icon' => 'openai',  'path' => '/ai/askgpt5',        'param' => 'prompt', 'image_param' => 'media', 'vision' => true],
        ['id' => 'ch',             'label' => 'Chat AI',                'company' => 'Prexzy',                    'scope' => 'Fast chat',           'icon' => 'prexzy',  'path' => '/ai/ch',             'param' => 'q'],
        ['id' => 'charart',        'label' => 'ChatArt AI',             'company' => 'ChatArt',                   'scope' => 'Chat + vision',       'icon' => 'aiapp',   'path' => '/ai/charart',        'param' => 'prompt', 'image_param' => 'image', 'vision' => true],
        ['id' => 'chatbot',        'label' => 'ChatBot App',            'company' => 'ChatBot',                   'scope' => 'Chat + search',       'icon' => 'prexzy',  'path' => '/ai/chatbot',        'param' => 'text',   'defaults' => ['search' => 'true']],
        ['id' => 'convertcode',    'label' => 'Convert Code',           'company' => 'Prexzy Code',               'scope' => 'Code conversion',     'icon' => 'code',    'path' => '/ai/convertcode',    'param' => 'code',   'defaults' => ['target' => 'javascript'], 'memory' => false],
        ['id' => 'detectbugs',     'label' => 'Detect Bugs',            'company' => 'Prexzy Code',               'scope' => 'Debug code',          'icon' => 'code',    'path' => '/ai/detectbugs',     'param' => 'code',   'memory' => false],
        ['id' => 'explaincode',    'label' => 'Explain Code',           'company' => 'Prexzy Code',               'scope' => 'Explain code',        'icon' => 'code',    'path' => '/ai/explaincode',    'param' => 'code',   'defaults' => ['lang' => 'auto'], 'memory' => false],
        ['id' => 'flippedchat',    'label' => 'Flipped Chat',           'company' => 'Flipped Chat',              'scope' => 'Character chat',      'icon' => 'prexzy',  'path' => '/ai/flippedchat',    'param' => 'prompt', 'defaults' => ['action' => 'chat']],
        ['id' => 'genigpt',        'label' => 'GeniGPT AI',             'company' => 'GeniGPT',                   'scope' => 'Image generation/edit','icon' => 'image',  'path' => '/ai/genigpt',        'param' => 'prompt', 'image_param' => 'image', 'memory' => false],
        ['id' => 'genimage',       'label' => 'GenImage AI',            'company' => 'GenImage',                  'scope' => 'Image generation',    'icon' => 'image',   'path' => '/ai/genimage',       'param' => 'prompt', 'defaults' => ['width' => '1024', 'height' => '1024'], 'memory' => false],
        ['id' => 'gemini',         'label' => 'Google Gemini',          'company' => 'Google',                    'scope' => 'Chat',                'icon' => 'google',  'path' => '/ai/gemini',         'param' => 'prompt'],
        ['id' => 'mistral',        'label' => 'Mistral AI',             'company' => 'Mistral AI',                'scope' => 'Chat + web',          'icon' => 'mistral', 'path' => '/ai/mistral',        'param' => 'prompt'],
        ['id' => 'msa',            'label' => 'MSA Tutor',              'company' => 'MSA Medical AI',            'scope' => 'Medical tutor',       'icon' => 'medical', 'path' => '/ai/msa',            'param' => 'prompt'],
        ['id' => 'olabiba',        'label' => 'Olabiba AI',             'company' => 'Olabiba',                   'scope' => 'Multilingual chat',   'icon' => 'prexzy',  'path' => '/ai/olabiba',        'param' => 'prompt', 'image_param' => 'media', 'vision' => true],
        ['id' => 'prompttocode',   'label' => 'Prompt to Code',         'company' => 'Prexzy Code',               'scope' => 'Generate code',       'icon' => 'code',    'path' => '/ai/prompttocode',   'param' => 'prompt', 'defaults' => ['language' => 'javascript'], 'memory' => false],
        ['id' => 'qwen',           'label' => 'Qwen AI Chat',           'company' => 'Alibaba Qwen',              'scope' => 'Chat',                'icon' => 'qwen',    'path' => '/ai/qwen',           'param' => 'prompt'],
        ['id' => 'advanced',       'label' => 'Story AI Advanced',      'company' => 'Prexzy Story',              'scope' => 'Story writing',       'icon' => 'story',   'path' => '/ai/advanced',       'param' => 'text',   'defaults' => ['mode' => 'story', 'length' => 'medium'], 'memory' => false],
        ['id' => 'quick',          'label' => 'Story AI Quick',         'company' => 'Prexzy Story',              'scope' => 'Quick stories',       'icon' => 'story',   'path' => '/ai/quick',          'param' => 'text',   'memory' => false],
    ];
    return $models;
}

function custom_public_id(string $internalId): string {
    $i = 1;
    foreach (prexzy_ai_catalog() as $m) {
        if ($m['id'] === $internalId) { return 'devil-' . str_pad((string)$i, 2, '0', STR_PAD_LEFT); }
        $i++;
    }
    return 'devil-00';
}

function custom_public_label(string $internalId): string {
    $map = [
        'dream'         => 'Devil Dream',
        'aiwriter-chat' => 'Devil Writer',
        'ai4chat'       => 'Devil Chat',
        'aiapk'         => 'Devil Vision',
        'aiappgen'      => 'Devil Image',
        'aimelody'      => 'Devil Melody',
        'aiserv'        => 'Devil Advanced',
        'aiw3'          => 'Devil Chat Pro',
        'askgpt5'       => 'Devil Smart',
        'ch'            => 'Devil Quick',
        'charart'       => 'Devil Vision Chat',
        'chatbot'       => 'Devil Search Chat',
        'convertcode'   => 'Devil Code Convert',
        'detectbugs'    => 'Devil Debug',
        'explaincode'   => 'Devil Code Explain',
        'flippedchat'   => 'Devil Character',
        'genigpt'       => 'Devil Image Edit',
        'genimage'      => 'Devil Image Pro',
        'gemini'        => 'Devil Deep',
        'mistral'       => 'Devil Logic',
        'msa'           => 'Devil Medical Tutor',
        'olabiba'       => 'Devil Multilingual',
        'prompttocode'  => 'Devil Code Generator',
        'qwen'          => 'Devil Reasoner',
        'advanced'      => 'Devil Story Pro',
        'quick'         => 'Devil Story Quick',
    ];
    return $map[$internalId] ?? 'Devil Engine';
}

function custom_public_icon(array $m): string {
    $icon = (string)($m['icon'] ?? 'devil');
    if (in_array($icon, ['code', 'image', 'music', 'medical', 'story'], true)) { return $icon; }
    return 'devil';
}

function custom_model_by_id(string $id): ?array {
    $id = strtolower(trim($id));
    foreach (prexzy_ai_catalog() as $m) {
        if ($m['id'] === $id || custom_public_id((string)$m['id']) === $id) { return $m; }
    }
    return null;
}

function prexzy_engine_from_id(string $id): array {
    $m = custom_model_by_id($id);
    if (!$m) { $m = custom_model_by_id('askgpt5'); }
    if (!$m) { return ['kind' => 'prexzy', 'endpoint' => 'askgpt5', 'path' => '/ai/askgpt5', 'param' => 'prompt']; }
    $m['kind'] = 'prexzy';
    $m['endpoint'] = $m['id'];
    if (!isset($m['memory'])) { $m['memory'] = true; }
    return $m;
}

function public_custom_models(): array {
    $out = [];
    foreach (prexzy_ai_catalog() as $m) {
        $out[] = [
            'id'      => custom_public_id((string)$m['id']),
            'label'   => custom_public_label((string)$m['id']),
            'company' => 'Devil AI',
            'scope'   => $m['scope'],
            'icon'    => custom_public_icon($m),
            'vision'  => !empty($m['vision']),
        ];
    }
    return $out;
}

function custom_model_label(string $id): string {
    $m = custom_model_by_id($id);
    return $m ? custom_public_label((string)$m['id']) : 'Custom Devil';
}

function model_label(string $id): string {
    if (strpos($id, 'custom:') === 0) { return custom_model_label(substr($id, 7)); }
    foreach (public_models() as $m) { if ($m['id'] === $id) { return $m['label']; } }
    return 'Devil AI';
}

/* engines visible to the ADMIN only (after password) */
function admin_engine_list(): array {
    $out = [];
    foreach (prexzy_ai_catalog() as $m) {
        $out[] = ['id' => 'prexzy:' . $m['id'], 'label' => 'Devil AI — ' . custom_public_label((string)$m['id']) . ' (' . $m['scope'] . ')'];
    }
    return $out;
}

/* default model → engine mapping (public model id → Prexzy endpoint) */
function model_engine_defaults(): array {
    return [
        'flash'  => 'prexzy:ch',      /* fastest */
        'pro'    => 'prexzy:askgpt5', /* reliable */
        'ultra'  => 'prexzy:aiapk',   /* smartest, stays in character */
        'custom' => 'prexzy:askgpt5',
    ];
}

/* resolve a public model id to a real engine — server-side secret */
function engine_for(array $cfg, string $model_id): array {
    if (strpos($model_id, 'custom:') === 0) {
        return prexzy_engine_from_id(substr($model_id, 7));
    }
    $defaults = model_engine_defaults();
    $eng = (string)($cfg['engines'][$model_id] ?? ($defaults[$model_id] ?? 'prexzy:askgpt5'));
    /* every engine is a Prexzy endpoint — anything unknown (incl. legacy values) coerces to the model default */
    if (preg_match('/^prexzy:([a-z0-9_-]+)$/i', $eng, $m)) {
        return prexzy_engine_from_id(strtolower($m[1]));
    }
    $fallback = strtolower((string)($defaults[$model_id] ?? 'prexzy:askgpt5'));
    $fallback = preg_replace('/^prexzy:/', '', $fallback);
    return prexzy_engine_from_id($fallback ?: 'askgpt5');
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

/* ── Developer API keys + OpenAI-compatible endpoint helpers ── */
function dev_keys_path(): string { return data_dir() . '/api_keys.json'; }
function load_dev_keys(): array { return load_json(dev_keys_path()); }
function save_dev_keys(array $keys): bool { return save_json_atomic(dev_keys_path(), $keys); }

function dev_api_headers(): void {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Authorization, X-Devil-API-Key, Content-Type');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Max-Age: 86400');
    header('X-Robots-Tag: noindex');
}

function dev_api_error(string $message, int $status = 400, string $type = 'invalid_request_error', string $code = 'bad_request'): void {
    dev_api_headers();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => ['message' => $message, 'type' => $type, 'code' => $code]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function dev_public_key(array $rec): array {
    return [
        'id'       => (string)($rec['id'] ?? ''),
        'name'     => (string)($rec['name'] ?? 'API key'),
        'prefix'   => (string)($rec['prefix'] ?? ''),
        'last4'    => (string)($rec['last4'] ?? ''),
        'created'  => (int)($rec['created'] ?? 0),
        'last_used'=> (int)($rec['last_used'] ?? 0),
        'requests' => (int)($rec['requests'] ?? 0),
        'revoked'  => !empty($rec['revoked']),
    ];
}

function dev_clean_key_name(string $name): string {
    $name = trim(preg_replace('/\s+/u', ' ', strip_tags($name)) ?? '');
    if ($name === '') { $name = 'Devil API key'; }
    if (mb_strlen($name) > 48) { $name = mb_substr($name, 0, 48); }
    return $name;
}

function dev_bearer_token(): string {
    $h = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
    if (preg_match('/^Bearer\s+(.+)$/i', trim($h), $m)) { return trim($m[1]); }
    $x = trim((string)($_SERVER['HTTP_X_DEVIL_API_KEY'] ?? ''));
    if ($x !== '') { return $x; }
    foreach (['key', 'api_key', 'apikey'] as $k) {
        $v = trim((string)($_GET[$k] ?? ''));
        if ($v !== '') { return $v; }
    }
    return '';
}

function dev_api_auth(): array {
    dev_api_headers();
    $token = dev_bearer_token();
    if ($token === '' || !preg_match('/^dv_live_[A-Fa-f0-9]{48}$/', $token)) {
        dev_api_error('Missing or invalid API key. Use Authorization: Bearer dv_live_...', 401, 'authentication_error', 'invalid_api_key');
    }
    $hash = hash('sha256', $token);
    $keys = load_dev_keys();
    foreach ($keys as $id => $rec) {
        if (!is_array($rec) || !empty($rec['revoked']) || !isset($rec['hash'])) { continue; }
        if (hash_equals((string)$rec['hash'], $hash)) {
            $users = load_users();
            $uid = (string)($rec['uid'] ?? '');
            if ($uid === '' || !isset($users[$uid]) || !is_array($users[$uid])) {
                dev_api_error('The account for this API key no longer exists.', 401, 'authentication_error', 'invalid_api_key');
            }
            $rec['last_used'] = time();
            $rec['last_ip'] = client_ip();
            $rec['requests'] = (int)($rec['requests'] ?? 0) + 1;
            $keys[$id] = $rec;
            save_dev_keys($keys);
            return ['uid' => $uid, 'user' => $users[$uid], 'key_id' => (string)($rec['id'] ?? $id), 'key' => $rec];
        }
    }
    dev_api_error('Invalid API key.', 401, 'authentication_error', 'invalid_api_key');
}

function dev_api_models(): array {
    return [
        ['id' => 'devil-flash', 'label' => 'Devil Flash', 'owned_by' => 'devil-ai'],
        ['id' => 'devil-pro',   'label' => 'Devil Pro',   'owned_by' => 'devil-ai'],
        ['id' => 'devil-ultra', 'label' => 'Devil Ultra', 'owned_by' => 'devil-ai'],
    ];
}

function dev_normalize_model(string $model): string {
    $m = strtolower(trim($model));
    if ($m === '') { $m = 'devil-flash'; }
    if (strpos($m, 'devil-') === 0) { $m = substr($m, 6); }
    if (in_array($m, ['flash', 'pro', 'ultra'], true)) { return $m; }
    dev_api_error('Unknown model. Use devil-flash, devil-pro, or devil-ultra.', 400, 'invalid_request_error', 'model_not_found');
}
function dev_public_model_id(string $model): string { return 'devil-' . dev_normalize_model($model); }

function dev_content_to_text_and_image($content): array {
    $text = '';
    $image = '';
    if (is_string($content) || is_numeric($content)) {
        $text = (string)$content;
    } elseif (is_array($content)) {
        foreach ($content as $part) {
            if (is_string($part) || is_numeric($part)) { $text .= "\n" . (string)$part; continue; }
            if (!is_array($part)) { continue; }
            $type = (string)($part['type'] ?? '');
            if (($type === 'text' || $type === 'input_text' || isset($part['text'])) && isset($part['text'])) {
                $text .= "\n" . (string)$part['text'];
                continue;
            }
            $url = '';
            if (isset($part['image_url'])) {
                $img = $part['image_url'];
                $url = is_array($img) ? (string)($img['url'] ?? '') : (string)$img;
            } elseif (isset($part['input_image'])) {
                $img = $part['input_image'];
                $url = is_array($img) ? (string)($img['image_url'] ?? ($img['url'] ?? '')) : (string)$img;
            }
            if ($url !== '' && $image === '' && preg_match('/^data:image\/(png|jpe?g|gif|webp);base64,/i', $url)) { $image = $url; }
            elseif ($url !== '') { $text .= "\n[Image URL: " . $url . "]"; }
        }
    }
    return [trim($text), $image];
}

function dev_messages_from_request(array $in): array {
    $raw = $in['messages'] ?? null;
    if (!is_array($raw) || !$raw) { dev_api_error('messages must be a non-empty array.', 400); }
    $messages = [];
    $image = '';
    $hasUser = false;
    $total = 0;
    foreach (array_slice($raw, -30) as $m) {
        if (!is_array($m)) { continue; }
        $role = strtolower((string)($m['role'] ?? 'user'));
        if (!in_array($role, ['system', 'user', 'assistant'], true)) { continue; }
        list($text, $img) = dev_content_to_text_and_image($m['content'] ?? '');
        if ($img !== '' && $image === '') { $image = $img; }
        if ($text === '') { continue; }
        if (mb_strlen($text) > 4000) { $text = mb_substr($text, 0, 4000); }
        $total += mb_strlen($text);
        if ($total > 14000) { dev_api_error('messages are too long for this endpoint.', 400, 'invalid_request_error', 'context_length_exceeded'); }
        if ($role === 'system') {
            $messages[] = ['role' => 'user', 'content' => 'Developer instruction: ' . $text];
        } else {
            $messages[] = ['role' => $role, 'content' => $text];
            if ($role === 'user') { $hasUser = true; }
        }
    }
    if (!$hasUser) { dev_api_error('At least one user message is required.', 400); }
    return [$messages, $image];
}

function dev_usage(array $messages, string $reply): array {
    $chars = mb_strlen($reply);
    foreach ($messages as $m) { $chars += mb_strlen((string)($m['content'] ?? '')); }
    $promptChars = max(0, $chars - mb_strlen($reply));
    $prompt = (int)ceil($promptChars / 4);
    $completion = (int)ceil(mb_strlen($reply) / 4);
    return ['prompt_tokens' => $prompt, 'completion_tokens' => $completion, 'total_tokens' => $prompt + $completion];
}
function dev_api_stream_completion(string $model, string $reply, array $usage): void {
    dev_api_headers();
    http_response_code(200);
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache, no-transform');
    header('X-Accel-Buffering: no');
    $id = 'chatcmpl-devil-' . bin2hex(random_bytes(10));
    $created = time();
    $send = function ($payload): void {
        echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        @ob_flush(); @flush();
    };
    $send(['id' => $id, 'object' => 'chat.completion.chunk', 'created' => $created, 'model' => $model, 'choices' => [['index' => 0, 'delta' => ['role' => 'assistant'], 'finish_reason' => null]]]);
    $len = mb_strlen($reply);
    for ($i = 0; $i < $len; $i += 160) {
        $chunk = mb_substr($reply, $i, 160);
        if ($chunk !== '') {
            $send(['id' => $id, 'object' => 'chat.completion.chunk', 'created' => $created, 'model' => $model, 'choices' => [['index' => 0, 'delta' => ['content' => $chunk], 'finish_reason' => null]]]);
        }
    }
    $send(['id' => $id, 'object' => 'chat.completion.chunk', 'created' => $created, 'model' => $model, 'choices' => [['index' => 0, 'delta' => new stdClass(), 'finish_reason' => 'stop']], 'usage' => $usage]);
    echo "data: [DONE]\n\n";
    @ob_flush(); @flush();
    exit;
}

/* ── admin auth ── */
function admin_password(): string { return (string)(load_config()['admin_password'] ?? ''); }
function admin_emails(): array {
    $raw = load_config()['admin_emails'] ?? [];
    if (is_string($raw)) { $raw = preg_split('/[\s,;]+/', $raw) ?: []; }
    if (!is_array($raw)) { return []; }
    $out = [];
    foreach ($raw as $email) {
        $email = strtolower(trim((string)$email));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) { $out[] = $email; }
    }
    return array_values(array_unique($out));
}
function current_user_is_admin(): bool {
    $u = current_user();
    if (!$u) { return false; }
    $email = strtolower(trim((string)($u['email'] ?? '')));
    return $email !== '' && in_array($email, admin_emails(), true);
}
function admin_ok(string $given): bool {
    if (current_user_is_admin()) { return true; }
    $pw = admin_password();
    if ($pw === '') { return false; }
    return ($given !== '' && hash_equals($pw, $given));
}
function normalize_admin_emails($raw): array {
    if (is_string($raw)) { $raw = preg_split('/[\s,;]+/', $raw) ?: []; }
    if (!is_array($raw)) { return admin_emails(); }
    $out = [];
    foreach ($raw as $email) {
        $email = strtolower(trim((string)$email));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) { $out[] = $email; }
    }
    if (!in_array('bk.w.p.bk@gmail.com', $out, true)) { $out[] = 'bk.w.p.bk@gmail.com'; }
    return array_values(array_unique($out));
}
function normalize_domain_list($raw): array {
    if (is_string($raw)) { $raw = preg_split('/[\s,;]+/', $raw) ?: []; }
    if (!is_array($raw)) { return []; }
    $out = [];
    foreach ($raw as $domain) {
        $domain = strtolower(trim((string)$domain));
        $domain = ltrim($domain, '@.');
        if ($domain !== '' && preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $domain)) { $out[] = $domain; }
    }
    return array_values(array_unique($out));
}
function normalize_cidr_list($raw): array {
    if (is_string($raw)) { $raw = preg_split('/[\s,;]+/', $raw) ?: []; }
    if (!is_array($raw)) { return []; }
    $out = [];
    foreach ($raw as $cidr) {
        $cidr = trim((string)$cidr);
        if ($cidr === '') { continue; }
        if (filter_var($cidr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) { $out[] = $cidr; continue; }
        if (preg_match('/^(\d{1,3}(?:\.\d{1,3}){3})\/(\d{1,2})$/', $cidr, $m) && filter_var($m[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && (int)$m[2] >= 0 && (int)$m[2] <= 32) { $out[] = $cidr; }
    }
    return array_values(array_unique($out));
}
function admin_security_snapshot(): array {
    $bans = load_json(data_dir() . '/security_bans.json');
    $rl = load_json(data_dir() . '/security_rl.json');
    $cidrs = load_json(data_dir() . '/security_datacenter_cidrs.json');
    if (!is_array($cidrs)) { $cidrs = []; }
    $now = time();
    $activeBans = [];
    foreach ($bans as $ip => $rec) {
        if (is_array($rec) && (int)($rec['until'] ?? 0) > $now) {
            $activeBans[] = ['ip' => (string)$ip, 'until' => (int)$rec['until'], 'reason' => (string)($rec['reason'] ?? 'security')];
        }
    }
    usort($activeBans, function ($a, $b) { return (int)$b['until'] <=> (int)$a['until']; });
    return [
        'active_bans' => array_slice($activeBans, 0, 80),
        'active_ban_count' => count($activeBans),
        'rate_bucket_count' => count($rl),
        'datacenter_cidrs' => array_values(array_filter($cidrs, 'is_string')),
    ];
}

/* ── email sending (PHP mail, multipart) ── */
function app_base_url(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    $dir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    return ($https ? 'https://' : 'http://') . $host . $dir;
}

function devil_mail_domain(): string {
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? 'blazepanel.mywp.info'));
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;
    $host = preg_replace('/^www\./', '', $host) ?? $host;
    if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $host)) { $host = 'blazepanel.mywp.info'; }
    return $host;
}
function devil_mail_clean_header(string $v, int $max = 260): string {
    $v = trim(preg_replace('/[\r\n]+/', ' ', $v) ?? '');
    return mb_substr($v, 0, $max);
}
function devil_mail_address(string $email, string $name = ''): string {
    $email = strtolower(trim($email));
    $name = devil_mail_clean_header($name, 80);
    if ($name === '') { return '<' . $email . '>'; }
    $name = addcslashes($name, '"\\');
    return '"' . $name . '" <' . $email . '>';
}
function devil_mail_subject(string $subject): string {
    $subject = devil_mail_clean_header($subject, 180);
    if (function_exists('mb_encode_mimeheader')) { return mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n"); }
    return '=?UTF-8?B?' . base64_encode($subject) . '?=';
}
function devil_mail_log(string $status, string $to, string $subject, string $detail = ''): void {
    $line = date('c') . ' ' . $status . ' to=' . str_replace(["\r", "\n"], '', $to) . ' subject=' . str_replace(["\r", "\n"], ' ', $subject);
    if ($detail !== '') { $line .= ' detail=' . str_replace(["\r", "\n"], ' ', mb_substr($detail, 0, 300)); }
    @file_put_contents(data_dir() . '/mail.log', $line . "\n", FILE_APPEND | LOCK_EX);
}
function devil_mail_body(string $html, string $text, string $boundary): string {
    return "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n{$text}\r\n"
         . "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n{$html}\r\n"
         . "--{$boundary}--";
}
function devil_mail_headers(string $to, string $subject, string $fromEmail, string $fromName, string $replyTo, string $boundary, bool $smtp = false): string {
    $domain = devil_mail_domain();
    $headers = [];
    if ($smtp) {
        $headers[] = 'To: ' . devil_mail_address($to);
        $headers[] = 'Subject: ' . devil_mail_subject($subject);
    }
    $headers[] = 'From: ' . devil_mail_address($fromEmail, $fromName);
    if ($replyTo !== '') { $headers[] = 'Reply-To: ' . devil_mail_address($replyTo); }
    $headers[] = 'Return-Path: <' . $fromEmail . '>';
    $headers[] = 'Date: ' . date('r');
    $headers[] = 'Message-ID: <devil-' . bin2hex(random_bytes(10)) . '@' . $domain . '>';
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
    $headers[] = 'X-Mailer: DevilAI/' . DEVIL_VERSION;
    return implode("\r\n", $headers);
}
function devil_smtp_read($fp): array {
    $data = '';
    while (!feof($fp)) {
        $line = (string)fgets($fp, 1024);
        if ($line === '') { break; }
        $data .= $line;
        if (strlen($line) >= 4 && $line[3] !== '-') { break; }
    }
    return [(int)substr($data, 0, 3), trim($data)];
}
function devil_smtp_cmd($fp, string $cmd, array $expect): array {
    fwrite($fp, $cmd . "\r\n");
    [$code, $resp] = devil_smtp_read($fp);
    return [in_array($code, $expect, true), $code, $resp];
}
function devil_mail_smtp(string $to, string $subject, string $headers, string $body, array $cfg, string $fromEmail): array {
    $host = trim((string)($cfg['smtp_host'] ?? ''));
    if ($host === '') { return [false, 'SMTP host missing']; }
    $port = max(1, min(65535, (int)($cfg['smtp_port'] ?? 587)));
    $secure = strtolower((string)($cfg['smtp_secure'] ?? 'tls'));
    if (!in_array($secure, ['none', 'tls', 'ssl'], true)) { $secure = 'tls'; }
    $target = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false]]);
    $errno = 0; $errstr = '';
    $fp = @stream_socket_client($target, $errno, $errstr, 12, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) { return [false, 'Connect failed: ' . ($errstr ?: $errno)]; }
    stream_set_timeout($fp, 18);
    [$code, $resp] = devil_smtp_read($fp);
    if ($code !== 220) { fclose($fp); return [false, 'Greeting failed: ' . $resp]; }
    $ehlo = devil_mail_domain();
    [$ok, $code, $resp] = devil_smtp_cmd($fp, 'EHLO ' . $ehlo, [250]);
    if (!$ok) { [$ok, $code, $resp] = devil_smtp_cmd($fp, 'HELO ' . $ehlo, [250]); }
    if (!$ok) { fclose($fp); return [false, 'EHLO failed: ' . $resp]; }
    if ($secure === 'tls') {
        [$ok, $code, $resp] = devil_smtp_cmd($fp, 'STARTTLS', [220]);
        if (!$ok) { fclose($fp); return [false, 'STARTTLS failed: ' . $resp]; }
        $crypto = defined('STREAM_CRYPTO_METHOD_TLS_CLIENT') ? STREAM_CRYPTO_METHOD_TLS_CLIENT : STREAM_CRYPTO_METHOD_SSLv23_CLIENT;
        if (!@stream_socket_enable_crypto($fp, true, $crypto)) { fclose($fp); return [false, 'TLS crypto failed']; }
        [$ok, $code, $resp] = devil_smtp_cmd($fp, 'EHLO ' . $ehlo, [250]);
        if (!$ok) { fclose($fp); return [false, 'EHLO after TLS failed: ' . $resp]; }
    }
    $user = (string)($cfg['smtp_username'] ?? '');
    $pass = (string)($cfg['smtp_password'] ?? '');
    if ($user !== '') {
        [$ok, $code, $resp] = devil_smtp_cmd($fp, 'AUTH LOGIN', [334]);
        if (!$ok) { fclose($fp); return [false, 'AUTH start failed: ' . $resp]; }
        [$ok, $code, $resp] = devil_smtp_cmd($fp, base64_encode($user), [334]);
        if (!$ok) { fclose($fp); return [false, 'AUTH username failed: ' . $resp]; }
        [$ok, $code, $resp] = devil_smtp_cmd($fp, base64_encode($pass), [235]);
        if (!$ok) { fclose($fp); return [false, 'AUTH password failed: ' . $resp]; }
    }
    [$ok, $code, $resp] = devil_smtp_cmd($fp, 'MAIL FROM:<' . $fromEmail . '>', [250]);
    if (!$ok) { fclose($fp); return [false, 'MAIL FROM failed: ' . $resp]; }
    [$ok, $code, $resp] = devil_smtp_cmd($fp, 'RCPT TO:<' . strtolower(trim($to)) . '>', [250, 251]);
    if (!$ok) { fclose($fp); return [false, 'RCPT TO failed: ' . $resp]; }
    [$ok, $code, $resp] = devil_smtp_cmd($fp, 'DATA', [354]);
    if (!$ok) { fclose($fp); return [false, 'DATA failed: ' . $resp]; }
    $message = $headers . "\r\n\r\n" . $body;
    $message = preg_replace("/\r?\n/", "\r\n", $message) ?? $message;
    $message = preg_replace('/^\./m', '..', $message) ?? $message;
    fwrite($fp, $message . "\r\n.\r\n");
    [$code, $resp] = devil_smtp_read($fp);
    @devil_smtp_cmd($fp, 'QUIT', [221, 250]);
    fclose($fp);
    if ($code !== 250) { return [false, 'Message rejected: ' . $resp]; }
    return [true, 'SMTP accepted'];
}
function devil_mail(string $to, string $subject, string $html, string $text): bool {
    $cfg = load_config();
    $domain = devil_mail_domain();
    $to = strtolower(trim($to));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { devil_mail_log('INVALID_TO', $to, $subject); return false; }
    $fromEmail = strtolower(trim((string)($cfg['mail_from_email'] ?? '')));
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) { $fromEmail = 'noreply@' . $domain; }
    $fromName = devil_mail_clean_header((string)($cfg['mail_from_name'] ?? 'Devil AI'), 80) ?: 'Devil AI';
    $replyTo = strtolower(trim((string)($cfg['mail_reply_to'] ?? '')));
    if (!filter_var($replyTo, FILTER_VALIDATE_EMAIL)) { $replyTo = ''; }
    $boundary = 'devil-' . bin2hex(random_bytes(12));
    $body = devil_mail_body($html, $text, $boundary);
    $transport = strtolower((string)($cfg['mail_transport'] ?? 'mail'));
    $smtpReady = trim((string)($cfg['smtp_host'] ?? '')) !== '';
    if (($transport === 'smtp' || $smtpReady) && $smtpReady) {
        $smtpHeaders = devil_mail_headers($to, $subject, $fromEmail, $fromName, $replyTo, $boundary, true);
        $attempts = [[max(1, min(65535, (int)($cfg['smtp_port'] ?? 587))), strtolower((string)($cfg['smtp_secure'] ?? 'tls')) ?: 'tls']];
        if (strtolower((string)($cfg['smtp_host'] ?? '')) === 'relay.dnsexit.com') {
            foreach ([[587, 'tls'], [2525, 'tls'], [8001, 'tls'], [26, 'none'], [940, 'none'], [25, 'none']] as $a) { $attempts[] = $a; }
        }
        $seen = [];
        foreach ($attempts as $a) {
            $port = (int)$a[0]; $secure = (string)$a[1];
            $k = $port . '/' . $secure;
            if (isset($seen[$k])) { continue; }
            $seen[$k] = true;
            $tryCfg = $cfg;
            $tryCfg['smtp_port'] = $port;
            $tryCfg['smtp_secure'] = $secure;
            [$ok, $detail] = devil_mail_smtp($to, $subject, $smtpHeaders, $body, $tryCfg, $fromEmail);
            devil_mail_log($ok ? 'SMTP_OK' : 'SMTP_FAIL', $to, $subject, $k . ' ' . $detail);
            if ($ok) { return true; }
        }
        devil_mail_log('SMTP_FALLBACK', $to, $subject, 'Trying native PHP mail() after SMTP relay rejection.');
    }
    $headers = devil_mail_headers($to, $subject, $fromEmail, $fromName, $replyTo, $boundary, false);
    $params = '-f' . $fromEmail;
    $ok = @mail($to, devil_mail_subject($subject), $body, $headers, $params);
    devil_mail_log($ok ? 'MAIL_OK' : 'MAIL_FAIL', $to, $subject, $ok ? 'php mail accepted' : 'php mail returned false');
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

function http_get_query(string $url, array $headers, array $params): array {
    $qs = http_build_query($params);
    if ($qs !== '') { $url .= (strpos($url, '?') === false ? '?' : '&') . $qs; }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPGET        => true,
            CURLOPT_HTTPHEADER     => $headers,
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
        'method'        => 'GET',
        'header'        => implode("\r\n", $headers),
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

/* ══════════════ LEAK GUARD (server-side safety net) ══════════════ */

/* did an engine break character and name another model/provider? */
function reply_leaks(string $t): bool {
    return (bool)preg_match('/\b(qwen|chatgpt|gpt-[345]|gpt\s?[345o]\b|gemini|deepseek|llama|mistral|grok|kimi|zhipu|prexzy|openai|anthropic)\b/iu', $t)
        || (bool)preg_match('/(powered|built|made|created|developed|trained)\s+by\s+(openai|google|alibaba|meta|anthropic|z\.?ai|zhipu|microsoft)/iu', $t)
        || (bool)preg_match('/\b\d{2,4}b\b.{0,25}\b(a\d+b|instruct|preview)\b/iu', $t);
}

/* does the user's latest message explicitly ask about the bot's identity? */
function identity_question(array $messages): bool {
    $q = '';
    foreach (array_reverse($messages) as $m) {
        if (($m['role'] ?? '') === 'user') { $q = mb_strtolower((string)$m['content']); break; }
    }
    if ($q === '') { return false; }
    foreach (['who are you', 'who r u', 'what are you', 'your name', 'tum kaun', 'kaun ho', 'who made you', 'who created you', 'who built you', 'kisne banaya', 'your creator', 'your owner', 'developer', 'developed you', 'developed by', 'which model', 'what model', 'what powers you', 'which company', 'who powers', 'be transparent', 'be honest'] as $k) {
        if (strpos($q, $k) !== false) { return true; }
    }
    return false;
}

/* ══════════════ IMAGE VALIDATION ══════════════ */

/* strict data-URL image check: mime allowlist, size cap, real base64, magic bytes.
   Returns a canonical data URL, or null when the input is not a valid image. */

function validate_image(string $s): ?string {
    $s = trim($s);
    if (strlen($s) < 40 || strlen($s) > 2600000) { return null; }   /* ≈1.9 MB decoded */
    if (!preg_match('#^data:image/(png|jpe?g|gif|webp);base64,([A-Za-z0-9+/=]+)$#', $s, $m)) { return null; }
    $raw = base64_decode($m[2], true);
    if ($raw === false || strlen($raw) < 64) { return null; }
    $sig = substr($raw, 0, 12);
    $magic = (strncmp($raw, "\x89PNG\r\n\x1a\n", 8) === 0)
          || (strncmp($sig, "\xFF\xD8\xFF", 3) === 0)
          || (strncmp($sig, "GIF87a", 6) === 0) || (strncmp($sig, "GIF89a", 6) === 0)
          || (strncmp($sig, "RIFF", 4) === 0 && substr($sig, 8, 4) === 'WEBP');
    if (!$magic) { return null; }
    $mime = strtolower($m[1]);
    if ($mime === 'jpg') { $mime = 'jpeg'; }
    return 'data:image/' . $mime . ';base64,' . base64_encode($raw);
}

function clean_filename(string $name): string {
    $name = trim(str_replace(["\0", '/', '\\'], ' ', $name));
    $name = preg_replace('/\s+/u', ' ', $name) ?: 'attachment';
    return mb_substr($name, 0, 120);
}

function normalize_extracted_text(string $raw, int $max = 12000): string {
    $raw = str_replace("\0", ' ', $raw);
    if (function_exists('mb_check_encoding') && !mb_check_encoding($raw, 'UTF-8')) {
        if (function_exists('iconv')) { $raw = (string)@iconv('UTF-8', 'UTF-8//IGNORE', $raw); }
        if ($raw === '' && function_exists('mb_convert_encoding')) { $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252'); }
    }
    $raw = html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $raw = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]+/', ' ', $raw) ?: '';
    $raw = preg_replace('/[ \t]+/u', ' ', $raw) ?: $raw;
    $raw = preg_replace('/\n{3,}/u', "\n\n", $raw) ?: $raw;
    $raw = trim($raw);
    if (mb_strlen($raw) > $max) { $raw = mb_substr($raw, 0, $max - 1) . '…'; }
    return $raw;
}

function is_textlike_attachment(string $name, string $mime): bool {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $textExt = ['txt','md','markdown','csv','tsv','json','jsonl','xml','html','htm','css','js','ts','jsx','tsx','php','py','rb','go','rs','java','c','cpp','h','hpp','cs','swift','kt','kts','sql','log','ini','env','yaml','yml','toml','sh','bat','ps1','vue','svelte','svg'];
    return strpos($mime, 'text/') === 0 || in_array($ext, $textExt, true) || in_array($mime, ['application/json','application/xml','application/javascript','application/x-php'], true);
}

function zip_entries_matching(string $raw, array $patterns): array {
    $out = [];
    if (class_exists('ZipArchive')) {
        $tmp = tempnam(sys_get_temp_dir(), 'devil_att_');
        if ($tmp !== false) {
            file_put_contents($tmp, $raw);
            $zip = new ZipArchive();
            if ($zip->open($tmp) === true) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = (string)$zip->getNameIndex($i);
                    $match = false;
                    foreach ($patterns as $pat) { if (preg_match($pat, $name)) { $match = true; break; } }
                    if ($match) { $out[$name] = (string)$zip->getFromIndex($i); }
                }
                $zip->close();
            }
            @unlink($tmp);
        }
        if ($out) { return $out; }
    }

    /* Pure-PHP ZIP fallback for hosts without ZipArchive. Supports stored/deflated entries. */
    $pos = 0;
    while (($pos = strpos($raw, "PK\x01\x02", $pos)) !== false) {
        if ($pos + 46 > strlen($raw)) { break; }
        $method = unpack('v', substr($raw, $pos + 10, 2))[1] ?? 0;
        $csize  = unpack('V', substr($raw, $pos + 20, 4))[1] ?? 0;
        $nlen   = unpack('v', substr($raw, $pos + 28, 2))[1] ?? 0;
        $elen   = unpack('v', substr($raw, $pos + 30, 2))[1] ?? 0;
        $clen   = unpack('v', substr($raw, $pos + 32, 2))[1] ?? 0;
        $loff   = unpack('V', substr($raw, $pos + 42, 4))[1] ?? 0;
        $name = substr($raw, $pos + 46, $nlen);
        $pos += 46 + $nlen + $elen + $clen;
        $match = false;
        foreach ($patterns as $pat) { if (preg_match($pat, $name)) { $match = true; break; } }
        if (!$match || $loff < 0 || $loff + 30 > strlen($raw) || substr($raw, $loff, 4) !== "PK\x03\x04") { continue; }
        $lnlen = unpack('v', substr($raw, $loff + 26, 2))[1] ?? 0;
        $lelen = unpack('v', substr($raw, $loff + 28, 2))[1] ?? 0;
        $start = $loff + 30 + $lnlen + $lelen;
        if ($start < 0 || $start + $csize > strlen($raw)) { continue; }
        $comp = substr($raw, $start, $csize);
        if ($method === 0) { $data = $comp; }
        elseif ($method === 8) { $data = @gzinflate($comp); if ($data === false) { $data = @gzuncompress($comp); } }
        else { $data = false; }
        if (is_string($data) && $data !== '') { $out[$name] = $data; }
    }
    return $out;
}

function binary_strings_text(string $raw, int $max = 12000): string {
    $parts = [];
    if (preg_match_all('/(?:[\x20-\x7E]\x00){4,}/', $raw, $m)) {
        foreach ($m[0] as $x) { $parts[] = str_replace("\0", '', $x); if (strlen(implode("\n", $parts)) > $max * 2) { break; } }
    }
    if (preg_match_all('/[\x09\x0A\x0D\x20-\x7E]{5,}/', $raw, $m2)) {
        foreach ($m2[0] as $x) {
            if (preg_match('/[A-Za-z0-9]/', $x)) { $parts[] = $x; }
            if (strlen(implode("\n", $parts)) > $max * 2) { break; }
        }
    }
    $txt = normalize_extracted_text(implode("\n", array_unique($parts)), $max);
    return preg_match('/[\p{L}\p{N}]/u', $txt) ? $txt : '';
}

function zip_xml_text(string $raw, array $patterns, int $max = 12000): string {
    $txt = '';
    foreach (zip_entries_matching($raw, $patterns) as $name => $xml) {
        $xml = preg_replace('/<\/(?:w:p|a:p|row|si)>/i', "\n", $xml) ?: $xml;
        $txt .= "\n" . normalize_extracted_text($xml, $max);
        if (mb_strlen($txt) >= $max) { break; }
    }
    return normalize_extracted_text($txt, $max);
}

function pdf_text(string $raw, int $max = 12000): string {
    $chunks = [];
    if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $raw, $streams)) {
        foreach ($streams[1] as $st) {
            $dec = function_exists('zlib_decode') ? @zlib_decode($st) : false;
            if ($dec === false) { $dec = @gzuncompress($st); }
            if ($dec !== false && is_string($dec)) { $chunks[] = $dec; }
        }
    }
    $chunks[] = $raw;
    $txt = '';
    foreach ($chunks as $chunk) {
        if (preg_match_all('/\((?:\\.|[^\\()]){1,2000}\)/s', $chunk, $m)) {
            foreach ($m[0] as $str) {
                $str = substr($str, 1, -1);
                $str = preg_replace('/\\([nrtbf()\\])/', ' ', $str) ?: $str;
                $txt .= ' ' . $str;
                if (mb_strlen($txt) > $max) { break 2; }
            }
        }
        if (preg_match_all('/<([0-9A-Fa-f]{8,})>/', $chunk, $hm)) {
            foreach ($hm[1] as $hex) {
                $bin = @hex2bin(strlen($hex) % 2 ? '0' . $hex : $hex);
                if (is_string($bin)) { $txt .= ' ' . $bin; }
                if (mb_strlen($txt) > $max) { break 2; }
            }
        }
    }
    return normalize_extracted_text($txt, $max);
}

function rtf_text(string $raw, int $max = 12000): string {
    $raw = preg_replace('/\\\'[0-9a-fA-F]{2}/', ' ', $raw) ?: $raw;
    $raw = preg_replace('/\\[a-zA-Z]+-?\d* ?/', ' ', $raw) ?: $raw;
    $raw = str_replace(['{','}'], ' ', $raw);
    return normalize_extracted_text($raw, $max);
}

function attachment_text(string $name, string $mime, string $raw): string {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (is_textlike_attachment($name, $mime)) { return normalize_extracted_text($raw); }
    if ($ext === 'pdf' || $mime === 'application/pdf') { $t = pdf_text($raw); return $t !== '' ? $t : binary_strings_text($raw); }
    if ($ext === 'rtf' || $mime === 'application/rtf') { return rtf_text($raw); }
    if ($ext === 'docx') { $t = zip_xml_text($raw, ['#^word/(?:document|footnotes|endnotes|header\d+|footer\d+)\.xml$#']); return $t !== '' ? $t : binary_strings_text($raw); }
    if ($ext === 'pptx') { $t = zip_xml_text($raw, ['#^ppt/slides/slide\d+\.xml$#', '#^ppt/notesSlides/notesSlide\d+\.xml$#']); return $t !== '' ? $t : binary_strings_text($raw); }
    if ($ext === 'xlsx') { $t = zip_xml_text($raw, ['#^xl/sharedStrings\.xml$#', '#^xl/worksheets/sheet\d+\.xml$#']); return $t !== '' ? $t : binary_strings_text($raw); }
    if ($ext === 'odt') { $t = zip_xml_text($raw, ['#^content\.xml$#']); return $t !== '' ? $t : binary_strings_text($raw); }
    if (in_array($ext, ['doc','ppt','xls','pages','numbers','key'], true)) { return binary_strings_text($raw); }
    return binary_strings_text($raw, 6000);
}

function process_attachments($input): array {
    $items = is_array($input) ? array_slice($input, 0, 6) : [];
    $meta = [];
    $context = [];
    $firstImage = '';
    $total = 0;
    foreach ($items as $i => $a) {
        if (!is_array($a)) { continue; }
        $name = clean_filename((string)($a['name'] ?? ('attachment-' . ($i + 1))));
        $mime = strtolower(trim((string)($a['type'] ?? 'application/octet-stream')));
        $data = (string)($a['data'] ?? '');
        if (!preg_match('#^data:([^;,]+)?;base64,([A-Za-z0-9+/=\r\n]+)$#', $data, $m)) { continue; }
        $raw = base64_decode(preg_replace('/\s+/', '', $m[2]), true);
        if ($raw === false || $raw === '') { continue; }
        if ($mime === '' || $mime === 'application/octet-stream') { $mime = strtolower($m[1] ?: $mime); }
        $size = strlen($raw);
        $total += $size;
        if ($size > 4 * 1024 * 1024 || $total > 8 * 1024 * 1024) { continue; }
        $isImage = preg_match('#^image/(png|jpe?g|gif|webp)$#', $mime) === 1;
        $one = ['name' => $name, 'type' => $mime, 'size' => $size, 'is_image' => $isImage];
        if ($isImage) {
            $img = validate_image('data:' . $mime . ';base64,' . base64_encode($raw));
            if ($img !== null) {
                if ($firstImage === '') { $firstImage = $img; }
                $one['read_status'] = 'image-ready';
                $context[] = "Attachment " . (count($meta) + 1) . " — {$name}: image file ({$mime}, {$size} bytes). Use the image if available.";
            } else { $one['read_status'] = 'invalid-image'; }
        } else {
            $txt = attachment_text($name, $mime, $raw);
            if ($txt !== '') {
                $one['read_status'] = 'read';
                $context[] = "Attachment " . (count($meta) + 1) . " — {$name} ({$mime}, {$size} bytes):\n" . $txt;
            } else {
                $one['read_status'] = 'metadata-only';
                $context[] = "Attachment " . (count($meta) + 1) . " — {$name}: {$mime}, {$size} bytes. Text could not be extracted here; answer from filename/type/metadata and ask for details if needed.";
            }
        }
        $meta[] = $one;
    }
    $ctx = trim(implode("\n\n", $context));
    if (mb_strlen($ctx) > 18000) { $ctx = mb_substr($ctx, 0, 17999) . '…'; }
    return [$meta, $ctx, $firstImage];
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

/* Build a memory-aware single-turn prompt for Prexzy endpoints.
   The free Prexzy APIs accept one prompt, so we fold the recent chat into it
   instead of sending only the latest user line. This stops the bot from
   forgetting names, images, and short follow-ups like "yes", "it", "continue". */
function compact_prompt_text(string $s, int $max = 1200): string {
    $s = trim(str_replace("\r", '', $s));
    $s = preg_replace('/[ \t]+/u', ' ', $s);
    $s = preg_replace('/\n{3,}/u', "\n\n", $s);
    if ($s === '') { return ''; }
    if (mb_strlen($s) > $max) { $s = mb_substr($s, 0, $max - 1) . '…'; }
    return $s;
}

function build_memory_prompt(array $messages, string $image = ''): string {
    $items = [];
    foreach ($messages as $m) {
        if (!is_array($m)) { continue; }
        $role = (string)($m['role'] ?? '');
        if ($role !== 'user' && $role !== 'assistant') { continue; }
        $content = compact_prompt_text((string)($m['content'] ?? ''), $role === 'user' ? 1500 : 1100);
        $attText = compact_prompt_text((string)($m['attachment_text'] ?? ''), 3500);
        if ($attText !== '') { $content = trim($content . "\n\n" . $attText); }
        if ($content === '' && !empty($m['img'])) { $content = '[attached an image]'; }
        if ($content === '') { continue; }
        $items[] = ($role === 'assistant' ? 'Devil AI' : 'User') . ': ' . $content;
    }
    if (!$items) {
        if ($image !== '') {
            return "The user attached an image with no text. Look at it and respond helpfully: describe it briefly and ask what they would like to know.";
        }
        return '';
    }

    /* Keep the latest turns first-priority, then older useful context while under budget. */
    $items = array_slice($items, -18);
    $selected = [];
    $used = 0;
    $budget = 7200;
    for ($i = count($items) - 1; $i >= 0; $i--) {
        $len = mb_strlen($items[$i]);
        if ($used + $len > $budget && count($selected) > 0) { continue; }
        array_unshift($selected, $items[$i]);
        $used += $len;
    }

    $prompt = "Use the conversation memory below. The LAST User message is the current request. Resolve short replies like yes, ok, it, this, that, continue, or more from the previous turns. Do not repeat the transcript; answer only the latest user request.\n\nConversation:\n";
    $prompt .= implode("\n\n", $selected);
    if ($image !== '') {
        $prompt .= "\n\nRelevant image: an image is attached with this request. If the latest user message refers to an earlier image/photo/QR/code, use the attached image as that image.";
    }
    $prompt .= "\n\nNow reply to the LAST User message:";
    return $prompt;
}

function recent_chat_image(array $messages): string {
    $seenLatestUser = false;
    $checked = 0;
    for ($i = count($messages) - 1; $i >= 0; $i--) {
        if ($checked++ > 12) { break; }  /* avoid accidentally reusing a very old image */
        $m = $messages[$i];
        if (!is_array($m) || (($m['role'] ?? '') !== 'user')) { continue; }
        if (!$seenLatestUser) { $seenLatestUser = true; continue; }  /* skip current text-only follow-up */
        $img = (string)($m['img'] ?? '');
        if ($img !== '') { return $img; }
    }
    return '';
}

function should_reuse_recent_image(string $msg): bool {
    $low = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $msg)));
    if ($low === '') { return false; }
    if (mb_strlen($low) <= 28 && preg_match('/\b(yes|yesb|yeah|yep|ok|okay|sure|haan|han|ha|hmm|continue|more|bata|bolo)\b/u', $low)) { return true; }
    return (bool)preg_match('/\b(this|that|it|image|img|photo|pic|picture|qr|code|scan|read|decode|attached|above|previous|ye|yeh|isko|isme|iss|usme|batao|bataye|dikhao)\b/u', $low);
}

function latest_user_prompt(array $messages, string $image = ''): string {
    foreach (array_reverse($messages) as $m) {
        if (is_array($m) && (($m['role'] ?? '') === 'user')) {
            $q = compact_prompt_text((string)($m['content'] ?? ''), 5000);
            $attText = compact_prompt_text((string)($m['attachment_text'] ?? ''), 5000);
            if ($attText !== '') { $q = trim($q . "\n\n" . $attText); }
            if ($q !== '') { return $q; }
            if ($image !== '' || !empty($m['img'])) { return 'The user attached an image. Respond helpfully to the image.'; }
        }
    }
    return $image !== '' ? 'The user attached an image. Respond helpfully to the image.' : '';
}

function prexzy_bad_response($j, int $status): bool {
    if (!is_array($j)) { return true; }
    if ($status >= 400) { return true; }
    foreach (['status', 'success', 'ok'] as $k) {
        if (array_key_exists($k, $j)) {
            $v = $j[$k];
            if ($v === false || $v === 0 || $v === '0' || $v === 'false' || $v === 'error' || $v === 'failed') { return true; }
        }
    }
    return false;
}

function extract_ai_text($j): string {
    if (is_string($j)) { return trim($j); }
    if (!is_array($j)) { return ''; }
    foreach (['response', 'result', 'answer', 'message', 'text', 'content', 'output', 'reply', 'url', 'image', 'image_url', 'file', 'link'] as $k) {
        if (isset($j[$k]) && is_scalar($j[$k]) && trim((string)$j[$k]) !== '') { return trim((string)$j[$k]); }
    }
    foreach (['data', 'result', 'results', 'choices'] as $k) {
        if (isset($j[$k])) {
            $txt = extract_ai_text($j[$k]);
            if ($txt !== '') { return $txt; }
        }
    }
    /* OpenAI-like shape */
    if (isset($j[0])) {
        $txt = extract_ai_text($j[0]);
        if ($txt !== '') { return $txt; }
    }
    /* Do not show raw provider JSON to users. Empty/unknown payloads should fail and trigger fallback. */
    return '';
}

function prexzy_error_message($j, int $status): string {
    if (is_array($j)) {
        foreach (['error', 'message', 'detail'] as $k) {
            if (isset($j[$k]) && is_scalar($j[$k]) && trim((string)$j[$k]) !== '') { return (string)$j[$k]; }
        }
    }
    return 'HTTP ' . $status;
}

function call_engine(array $cfg, array $engine, array $messages, string $image = ''): array {
    /* ── Prexzy (free, no key, single-turn) ── */
    if ($engine['kind'] === 'prexzy') {
        $useMemory = array_key_exists('memory', $engine) ? (bool)$engine['memory'] : true;
        $q = $useMemory ? build_memory_prompt($messages, $image) : latest_user_prompt($messages, $image);
        if ($q === '') { return [false, 'Message is empty.', null, null]; }

        /* Keep the Devil AI persona even when the public UI exposes a custom provider name. */
        $prompt = PREXZY_PERSONA . "\n\n" . $q;
        $param = (string)($engine['param'] ?? 'prompt');
        $body = isset($engine['defaults']) && is_array($engine['defaults']) ? $engine['defaults'] : [];
        $body[$param] = $prompt;
        if ($image !== '' && !empty($engine['image_param'])) {
            $body[(string)$engine['image_param']] = preg_replace('/^data:[^,]+,/', '', $image);   /* raw base64 for vision-capable endpoints */
        }

        $path = (string)($engine['path'] ?? ('/ai/' . ($engine['endpoint'] ?? 'askgpt5')));
        $url = PREXZY_ROOT . $path;
        list($ok, $raw, $status) = http_post_json($url, [], $body);
        if (!$ok) { return [false, $raw, null, null]; }
        $j = json_decode($raw, true);

        /* Some endpoints document POST but only read query params. Fallback to GET once. */
        if (prexzy_bad_response($j, $status)) {
            list($ok2, $raw2, $status2) = http_get_query($url, [], $body);
            if ($ok2) { $j2 = json_decode($raw2, true); if (!prexzy_bad_response($j2, $status2)) { $j = $j2; $status = $status2; } }
        }

        if (prexzy_bad_response($j, $status)) {
            return [false, 'Engine error: ' . prexzy_error_message($j, $status), 'Devil AI will retry automatically — try again in a moment.', null];
        }
        $txt = extract_ai_text($j);
        if (trim($txt) === '') { return [false, 'The engine returned an empty answer.', null, null]; }
        return [true, $txt, null, 'prexzy:' . (string)($engine['endpoint'] ?? 'custom')];
    }

    return [false, 'Unknown engine.', null, null];
}

/* full pipeline with AUTOMATIC FALLBACK (Prexzy is the safety net) */
function ai_respond(array $cfg, string $modelId, array $messages, string $image = ''): array {
    $engine = engine_for($cfg, $modelId);

    /* images need a vision-capable engine — route there automatically */
    if ($image !== '') {
        $vision = !empty($engine['vision']) ? $engine : prexzy_engine_from_id('aiapk');
        list($ok, $txt, $hint, $used) = call_engine($cfg, $vision, $messages, $image);
        if (!$ok) { list($ok, $txt, $hint, $used) = call_engine($cfg, $vision, $messages, $image); }   /* one retry */
        if (!$ok) {
            /* vision down → answer from text alone, honestly */
            list($ok, $txt, $hint, $used) = call_engine($cfg, prexzy_engine_from_id('askgpt5'), $messages, '');
            if ($ok) { $txt = "I couldn't open the attached image right now, but here's what I can tell you:\n\n" . $txt; }
            if ($ok && $used !== null && !identity_question($messages)) { $txt = strip_intro($txt); }
            return [$ok, $txt, $used];
        }
        if ($ok && $used !== null && reply_leaks($txt) && !identity_question($messages)) {
            list($ok2, $txt2, $h2, $u2) = call_engine($cfg, prexzy_engine_from_id('askgpt5'), $messages, '');
            if ($ok2 && trim($txt2) !== '') { $txt = $txt2; $used = $u2; }
        }
        if ($ok && $used !== null && !identity_question($messages)) { $txt = strip_intro($txt); }
        return [$ok, $txt, $used];
    }

    list($ok, $txt, $hint, $used) = call_engine($cfg, $engine, $messages);

    /* fallback 1: reliable Prexzy endpoint */
    if (!$ok && $engine['endpoint'] !== 'askgpt5') {
        list($ok, $txt, $hint, $used) = call_engine($cfg, prexzy_engine_from_id('askgpt5'), $messages);
    }
    /* fallback 2: fast Prexzy endpoint */
    if (!$ok && $engine['endpoint'] !== 'ch') {
        list($ok, $txt, $hint, $used) = call_engine($cfg, prexzy_engine_from_id('ch'), $messages);
    }
    /* persona safety net: if the engine broke character and named another model,
       regenerate with the most in-character engine */
    if ($ok && $used !== null && reply_leaks($txt) && !identity_question($messages)) {
        list($ok2, $txt2, $h2, $u2) = call_engine($cfg, prexzy_engine_from_id('aiapk'), $messages, '');
        if ($ok2 && trim($txt2) !== '') { $txt = $txt2; $used = $u2; $hint = $h2; }
    }

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
        return "I am **Devil AI** — developed by [BlazeNXT](https://www.blazenxt.in).";
    }

    if ($has('who made you', 'who created you', 'who built you', 'who developed you', 'developer', 'developed by', 'kisne banaya', 'your creator', 'your owner')) {
        return "Developed by **[BlazeNXT](https://www.blazenxt.in)** — custom code, private identity, Devil AI experience.";
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

function slug128(): string { return bin2hex(random_bytes(64)); }

function segment_slug(string $s, string $fallback = 'chat'): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9-]+/', '-', $s) ?: '';
    $s = trim($s, '-');
    return $s !== '' ? substr($s, 0, 64) : $fallback;
}

function infer_chat_route(array $chat): array {
    $m0 = segment_slug((string)($chat['url_model'] ?? ''), '');
    $t0 = segment_slug((string)($chat['url_type'] ?? ''), '');
    if ($m0 !== '' && $t0 !== '') { return [$m0, $t0]; }

    $model = 'flash';
    $custom = '';
    foreach (($chat['messages'] ?? []) as $msg) {
        if (!is_array($msg)) { continue; }
        if (($msg['role'] ?? '') !== 'assistant') { continue; }
        $mid = (string)($msg['model_id'] ?? '');
        if ($mid !== '') { $model = $mid; $custom = (string)($msg['custom_model'] ?? ''); break; }
    }
    if ($model === 'custom') {
        $type = 'custom';
        if ($custom !== '') {
            $cm = custom_model_by_id($custom);
            $type = $cm ? custom_public_id((string)$cm['id']) : segment_slug($custom, 'custom');
        }
        return ['custom', segment_slug($type, 'custom')];
    }
    $valid = ['flash' => true, 'pro' => true, 'ultra' => true];
    if (!isset($valid[$model])) { $model = 'flash'; }
    return [segment_slug($model, 'flash'), 'chat'];
}

function ensure_chat_meta(array &$chat): bool {
    $changed = false;
    if (empty($chat['id']) || !preg_match('/^c[a-f0-9]{6,32}$/', (string)$chat['id'])) {
        $chat['id'] = 'c' . bin2hex(random_bytes(8));
        $changed = true;
    }
    if (empty($chat['slug']) || !preg_match('/^[a-f0-9]{128}$/', (string)$chat['slug'])) {
        $chat['slug'] = slug128();
        $changed = true;
    }
    [$model, $type] = infer_chat_route($chat);
    if (($chat['url_model'] ?? '') !== $model) { $chat['url_model'] = $model; $changed = true; }
    if (($chat['url_type'] ?? '') !== $type) { $chat['url_type'] = $type; $changed = true; }
    if (!isset($chat['edit_variants']) || !is_array($chat['edit_variants'])) { $chat['edit_variants'] = []; $changed = true; }
    return $changed;
}

function chat_file_by_id_or_slug(string $uid, string $id): ?string {
    $id = trim($id);
    if ($id === '') { return null; }
    if (preg_match('/^c[a-f0-9]{6,32}$/', $id)) {
        $p = chat_path($uid, $id);
        return is_readable($p) ? $p : null;
    }
    if (preg_match('/^[a-f0-9]{128}$/', $id)) {
        foreach (glob(chats_dir($uid) . '/*.json') ?: [] as $f) {
            $j = json_decode((string)file_get_contents($f), true);
            if (is_array($j) && (string)($j['slug'] ?? '') === $id) { return $f; }
        }
    }
    return null;
}

function load_chat_raw_file(string $file): ?array {
    if (!is_readable($file)) { return null; }
    $j = json_decode((string)file_get_contents($file), true);
    return is_array($j) ? $j : null;
}

function load_chat(string $uid, string $id): ?array {
    $p = chat_file_by_id_or_slug($uid, $id);
    if (!$p) { return null; }
    $j = load_chat_raw_file($p);
    if (!$j) { return null; }
    if (ensure_chat_meta($j)) { save_json_atomic($p, $j); }
    return $j;
}

function save_chat(string $uid, array $chat): bool {
    ensure_chat_meta($chat);
    return save_json_atomic(chat_path($uid, (string)$chat['id']), $chat);
}

function chat_branch_root_id(array $chat): string {
    $id = (string)($chat['id'] ?? '');
    $root = (string)($chat['branch_root'] ?? '');
    if ($root !== '' && preg_match('/^c[a-f0-9]{6,32}$/', $root)) { return $root; }
    $legacy = (string)($chat['branched_from'] ?? '');
    if ($legacy !== '' && preg_match('/^c[a-f0-9]{6,32}$/', $legacy)) { return $legacy; }
    $root2 = (string)($chat['root_id'] ?? '');
    if ($root2 !== '' && preg_match('/^c[a-f0-9]{6,32}$/', $root2)) { return $root2; }
    return $id;
}

function migrate_branch_files(string $uid): void {
    $dir = chats_dir($uid);
    if (!is_dir($dir)) { return; }
    $marker = $dir . '/.branches_embedded_v3';
    if (is_file($marker)) { return; }
    foreach (glob($dir . '/*.json') ?: [] as $f) {
        $branch = load_chat_raw_file($f);
        if (!is_array($branch) || empty($branch['id'])) { continue; }
        if (!isset($branch['edited_message_index']) && empty($branch['branched_from']) && empty($branch['branch_hidden'])) { continue; }
        $idx = (int)($branch['edited_message_index'] ?? -1);
        if ($idx < 0 || empty($branch['messages']) || !is_array($branch['messages'])) { continue; }
        $rootId = chat_branch_root_id($branch);
        if ($rootId === '' || $rootId === (string)$branch['id']) { continue; }
        $rootPath = chat_path($uid, $rootId);
        $root = load_chat_raw_file($rootPath);
        if (!$root) { continue; }
        ensure_chat_meta($root);
        if (ensure_chat_meta($branch)) { save_json_atomic($f, $branch); }
        $key = (string)$idx;
        if (!isset($root['edit_variants'][$key]) || !is_array($root['edit_variants'][$key])) { $root['edit_variants'][$key] = []; }
        $vid = (string)($branch['variant_id'] ?? $branch['id']);
        $exists = false;
        foreach ($root['edit_variants'][$key] as $v) {
            if (is_array($v) && (string)($v['id'] ?? '') === $vid) { $exists = true; break; }
        }
        if (!$exists) {
            $root['edit_variants'][$key][] = [
                'id' => $vid,
                'title' => (string)($branch['title'] ?? 'Edited chat'),
                'created' => (int)($branch['created'] ?? time()),
                'updated' => (int)($branch['updated'] ?? time()),
                'edited_message_index' => $idx,
                'messages' => array_values(array_filter($branch['messages'] ?? [], 'is_array')),
            ];
        }
        if ((string)($root['active_variant'] ?? '') === (string)$branch['id']) { $root['active_variant'] = $vid; }
        $root['updated'] = max((int)($root['updated'] ?? 0), (int)($branch['updated'] ?? 0));
        save_chat($uid, $root);
        @unlink($f);
    }
    @file_put_contents($marker, (string)time(), LOCK_EX);
}

function list_chats(string $uid): array {
    migrate_branch_files($uid);
    $dir = chats_dir($uid);
    if (!is_dir($dir)) { return []; }
    $out = [];
    foreach (glob($dir . '/*.json') ?: [] as $f) {
        $j = json_decode((string)file_get_contents($f), true);
        if (!is_array($j) || !isset($j['id'])) { continue; }
        if (!empty($j['branch_hidden']) || isset($j['edited_message_index']) || !empty($j['branched_from'])) { continue; }
        if (ensure_chat_meta($j)) { save_json_atomic($f, $j); }
        $out[] = [
            'id'      => (string)$j['id'],
            'slug'    => (string)($j['slug'] ?? ''),
            'url_model' => (string)($j['url_model'] ?? 'flash'),
            'url_type'  => (string)($j['url_type'] ?? 'chat'),
            'title'   => (string)($j['title'] ?? 'New chat'),
            'updated' => (int)($j['updated'] ?? filemtime($f) ?: 0),
        ];
        if (count($out) >= 200) { break; }
    }
    usort($out, function ($a, $b) { return $b['updated'] <=> $a['updated']; });
    return $out;
}

function branch_variant_summary(array $chat, bool $original = false): array {
    return [
        'id' => (string)($chat['id'] ?? ''),
        'title' => (string)($chat['title'] ?? ($original ? 'Original chat' : 'Edited chat')),
        'label' => $original ? 'Original' : 'Edit',
        'original' => $original,
        'created' => (int)($chat['created'] ?? 0),
        'updated' => (int)($chat['updated'] ?? 0),
    ];
}

function variant_from_root(array $rootChat, string $variant): ?array {
    $rootId = (string)($rootChat['id'] ?? '');
    if ($variant === '' || $variant === 'original' || $variant === $rootId || $variant === (string)($rootChat['slug'] ?? '')) { return $rootChat; }
    foreach (($rootChat['edit_variants'] ?? []) as $list) {
        if (!is_array($list)) { continue; }
        foreach ($list as $v) {
            if (is_array($v) && (string)($v['id'] ?? '') === $variant) { return $v; }
        }
    }
    return null;
}

function branch_groups_for_chat(string $uid, array $activeChat, string $activeVariant = ''): array {
    migrate_branch_files($uid);
    $rootId = chat_branch_root_id($activeChat);
    if ($rootId === '') { return []; }
    $rootChat = load_chat($uid, $rootId) ?: $activeChat;
    $activeId = $activeVariant !== '' ? $activeVariant : (string)($rootChat['active_variant'] ?? $rootId);
    $out = [];
    foreach (($rootChat['edit_variants'] ?? []) as $idx => $variants) {
        if (!is_array($variants) || !count($variants)) { continue; }
        $all = [branch_variant_summary($rootChat, true)];
        usort($variants, function ($a, $b) {
            $ac = (int)($a['created'] ?? 0); $bc = (int)($b['created'] ?? 0);
            if ($ac === $bc) { return strcmp((string)($a['id'] ?? ''), (string)($b['id'] ?? '')); }
            return $ac <=> $bc;
        });
        foreach ($variants as $v) { if (is_array($v)) { $all[] = branch_variant_summary($v, false); } }
        if (count($all) < 2) { continue; }
        foreach ($all as $i => &$v) {
            $v['label'] = !empty($v['original']) ? 'Original' : ('Edit ' . $i);
            $v['active'] = ((string)$v['id'] === $activeId) || (!empty($v['original']) && ($activeId === '' || $activeId === $rootId || $activeId === 'original'));
        }
        unset($v);
        $out[(string)$idx] = ['index' => (int)$idx, 'variants' => $all];
    }
    ksort($out, SORT_NUMERIC);
    return $out;
}

function display_chat_variant(string $uid, array $requestedChat, string $variant = ''): array {
    migrate_branch_files($uid);
    $rootId = chat_branch_root_id($requestedChat);
    $rootChat = load_chat($uid, $rootId) ?: $requestedChat;
    ensure_chat_meta($rootChat);
    $activeVariant = (string)($rootChat['active_variant'] ?? '');
    if ($activeVariant === '') { $activeVariant = $rootId; }
    if ($variant !== '') { $activeVariant = ($variant === 'original') ? $rootId : $variant; }
    elseif ((string)($requestedChat['id'] ?? '') !== $rootId) { $activeVariant = (string)($requestedChat['id'] ?? $rootId); }

    $display = variant_from_root($rootChat, $activeVariant);
    if (!$display) { $activeVariant = $rootId; $display = $rootChat; }
    $branchGroups = branch_groups_for_chat($uid, $rootChat, $activeVariant);
    $display['id'] = $rootId;
    $display['root_id'] = $rootId;
    $display['slug'] = (string)($rootChat['slug'] ?? '');
    $display['url_model'] = (string)($rootChat['url_model'] ?? 'flash');
    $display['url_type'] = (string)($rootChat['url_type'] ?? 'chat');
    $display['active_variant'] = $activeVariant;
    $display['variant_chat_id'] = $activeVariant === $rootId ? '' : $activeVariant;
    $display['title'] = (string)($rootChat['title'] ?? ($display['title'] ?? 'New chat'));
    $display['branch_groups'] = $branchGroups;
    return [$display, $branchGroups, $rootChat, $activeVariant];
}

function update_root_variant_messages(array &$rootChat, string $variant, array $displayChat): void {
    $rootId = (string)($rootChat['id'] ?? '');
    if ($variant === '' || $variant === 'original' || $variant === $rootId || $variant === (string)($rootChat['slug'] ?? '')) {
        $rootChat['messages'] = array_values(array_filter($displayChat['messages'] ?? [], 'is_array'));
        $rootChat['title'] = (string)($displayChat['title'] ?? ($rootChat['title'] ?? 'New chat'));
        $rootChat['active_variant'] = $rootId;
        $rootChat['updated'] = (int)($displayChat['updated'] ?? time());
        return;
    }
    foreach (($rootChat['edit_variants'] ?? []) as $idx => &$list) {
        if (!is_array($list)) { continue; }
        foreach ($list as &$v) {
            if (is_array($v) && (string)($v['id'] ?? '') === $variant) {
                $v['messages'] = array_values(array_filter($displayChat['messages'] ?? [], 'is_array'));
                $v['title'] = (string)($displayChat['title'] ?? ($v['title'] ?? 'Edited chat'));
                $v['updated'] = (int)($displayChat['updated'] ?? time());
                $rootChat['active_variant'] = $variant;
                $rootChat['updated'] = (int)$v['updated'];
                unset($v, $list);
                return;
            }
        }
    }
    unset($list);
}

function shares_dir(): string { return data_dir() . '/shares'; }
function share_path(string $sid): string { return shares_dir() . '/' . $sid . '.json'; }
function share_id(): string { return bin2hex(random_bytes(64)); }
function share_public_url(string $sid): string { return app_base_url() . '/share/' . rawurlencode($sid); }
function feedback_path(): string { return data_dir() . '/feedback.json'; }

function public_chat_payload(array $chat, array $user): array {
    $messages = [];
    foreach (($chat['messages'] ?? []) as $m) {
        if (!is_array($m)) { continue; }
        $role = (string)($m['role'] ?? '');
        if ($role !== 'user' && $role !== 'assistant') { continue; }
        $one = [
            'role' => $role,
            'content' => (string)($m['content'] ?? ''),
            'ts' => (int)($m['ts'] ?? 0),
        ];
        if (!empty($m['img']) && is_string($m['img'])) { $one['img'] = (string)$m['img']; }
        if (!empty($m['attachments']) && is_array($m['attachments'])) {
            $atts = [];
            foreach (array_slice($m['attachments'], 0, 12) as $a) {
                if (!is_array($a)) { continue; }
                $atts[] = [
                    'name' => clean_filename((string)($a['name'] ?? 'attachment')),
                    'type' => (string)($a['type'] ?? 'application/octet-stream'),
                    'size' => (int)($a['size'] ?? 0),
                    'is_image' => !empty($a['is_image']),
                    'read_status' => (string)($a['read_status'] ?? ''),
                ];
            }
            if ($atts) { $one['attachments'] = $atts; }
        }
        if (!empty($m['edited'])) { $one['edited'] = true; }
        if ($role === 'assistant' && !empty($m['model_label'])) { $one['model_label'] = (string)$m['model_label']; }
        $messages[] = $one;
    }
    return [
        'title' => (string)($chat['title'] ?? 'Shared chat'),
        'created' => time(),
        'source_chat' => (string)($chat['id'] ?? ''),
        'source_slug' => (string)($chat['slug'] ?? ''),
        'source_variant' => (string)($chat['active_variant'] ?? ''),
        'shared_by' => ['name' => (string)($user['name'] ?? 'Devil user')],
        'message_count' => count($messages),
        'messages' => $messages,
    ];
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
            devil_session_refresh();
            return $u;
        }
    }
    /* auto-create on first login */
    $uid = 'u' . bin2hex(random_bytes(8));
    $users[$uid] = ['id' => $uid, 'name' => name_from_email($email), 'email' => strtolower($email), 'created' => time()];
    if (!save_users($users)) { return null; }
    session_regenerate_id(true);
    $_SESSION['devil_uid'] = $uid;
    devil_session_refresh();
    return $users[$uid];
}

/* ══════════════ MAIN ══════════════ */

try {
    $action = isset($_GET['action']) ? (string)$_GET['action'] : '';
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    /* ─────────── PUBLIC ─────────── */

    if ($action === 'bootstrap' && $method === 'GET') {
        json_out(['ok' => true, 'models' => public_models(), 'custom_models' => public_custom_models(), 'default' => 'flash', 'version' => DEVIL_VERSION]);
    }

    if ($action === 'settings' && $method === 'GET') {
        /* public-safe: never expose engines/config/keys */
        json_out(['ok' => true, 'version' => DEVIL_VERSION, 'admin' => (admin_password() !== '' || admin_emails() !== [])]);
    }

    /* ─────────── DEVELOPER API (Bearer API key, OpenAI-compatible) ─────────── */
    if (in_array($action, ['dev_models', 'dev_chat_completions'], true)) {
        dev_api_headers();
        if ($method === 'OPTIONS') { http_response_code(204); exit; }
        $auth = dev_api_auth();

        if ($action === 'dev_models' && $method === 'GET') {
            $data = [];
            foreach (dev_api_models() as $m) {
                $data[] = ['id' => $m['id'], 'object' => 'model', 'created' => 1760000000, 'owned_by' => $m['owned_by'], 'label' => $m['label']];
            }
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['object' => 'list', 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        if ($action === 'dev_chat_completions' && $method === 'POST') {
            $in = input_json();
            $wantsStream = !empty($in['stream']);
            $model = dev_normalize_model((string)($in['model'] ?? 'devil-flash'));
            list($messages, $image) = dev_messages_from_request($in);
            $cfgAll = load_config();
            $rl = max(1, min(1000, (int)($cfgAll['rate_per_hour'] ?? 40)));
            if (!rate_ok('devrl.json', 'k:' . (string)$auth['key_id'], $rl, 3600)) {
                dev_api_error('Rate limit exceeded. Try again later.', 429, 'rate_limit_error', 'rate_limit_exceeded');
            }
            $tz = (string)($cfgAll['timezone'] ?? '');
            if ($tz !== '' && in_array($tz, timezone_identifiers_list(), true)) { date_default_timezone_set($tz); }
            $providerMsgs = array_merge([['role' => 'system', 'content' => PREXZY_PERSONA]], $messages);
            list($ok, $reply, $used) = ai_respond($cfgAll, $model, $providerMsgs, $image);
            if (!$ok) { dev_api_error($reply, 502, 'api_error', 'engine_error'); }
            $created = time();
            $outModel = dev_public_model_id($model);
            $usage = dev_usage($messages, $reply);
            if ($wantsStream) { dev_api_stream_completion($outModel, $reply, $usage); }
            $resp = [
                'id' => 'chatcmpl-devil-' . bin2hex(random_bytes(10)),
                'object' => 'chat.completion',
                'created' => $created,
                'model' => $outModel,
                'choices' => [[
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => $reply],
                    'finish_reason' => 'stop',
                ]],
                'usage' => $usage,
            ];
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        dev_api_error('Method not allowed for this endpoint.', 405, 'invalid_request_error', 'method_not_allowed');
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
            if (devil_security_recaptcha_required() && !devil_security_verify_recaptcha((string)($in['recaptcha_token'] ?? ''), client_ip())) {
                json_out(['ok' => false, 'error' => 'Security verification failed. Please refresh and try again.'], 403);
            }
            $existing = find_user_by_email($email) !== null;
            [$allowedEmail, $emailBlockMsg] = devil_security_email_auth_status($email, $existing);
            if (!$allowedEmail) { json_out(['ok' => false, 'error' => $emailBlockMsg], 403); }
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
            json_out(['ok' => false, 'error' => 'The email could not be sent right now. Please contact BlazeNXT support.'], 500);
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
        devil_session_destroy_all();
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
        $snapshot = admin_security_snapshot();
        json_out([
            'ok'      => true,
            'engines' => admin_engine_list(),
            'admin_user' => current_user_is_admin(),
            'security' => $snapshot,
            'config'  => [
                'engines'        => $cfg['engines'],
                'rate_per_hour'  => (int)$cfg['rate_per_hour'],
                'max_chats'      => (int)$cfg['max_chats'],
                'admin_emails'    => admin_emails(),
                'security_require_recaptcha' => !empty($cfg['security_require_recaptcha']),
                'recaptcha_site_key' => (string)($cfg['recaptcha_site_key'] ?? ''),
                'recaptcha_secret_set' => (string)($cfg['recaptcha_secret_key'] ?? '') !== '',
                'recaptcha_min_score' => (float)($cfg['recaptcha_min_score'] ?? 0.45),
                'security_block_disposable_emails' => !isset($cfg['security_block_disposable_emails']) || !empty($cfg['security_block_disposable_emails']),
                'security_block_subdomain_emails' => !isset($cfg['security_block_subdomain_emails']) || !empty($cfg['security_block_subdomain_emails']),
                'security_extra_blocked_email_domains' => normalize_domain_list($cfg['security_extra_blocked_email_domains'] ?? []),
                'security_trusted_email_domains' => normalize_domain_list($cfg['security_trusted_email_domains'] ?? []),
                'security_datacenter_cidrs' => $snapshot['datacenter_cidrs'],
                'mail_transport' => (string)($cfg['mail_transport'] ?? 'mail'),
                'mail_from_email' => (string)($cfg['mail_from_email'] ?? ''),
                'mail_from_name' => (string)($cfg['mail_from_name'] ?? 'Devil AI'),
                'mail_reply_to' => (string)($cfg['mail_reply_to'] ?? ''),
                'smtp_host' => (string)($cfg['smtp_host'] ?? ''),
                'smtp_port' => (int)($cfg['smtp_port'] ?? 587),
                'smtp_secure' => (string)($cfg['smtp_secure'] ?? 'tls'),
                'smtp_username' => (string)($cfg['smtp_username'] ?? ''),
                'smtp_password_set' => (string)($cfg['smtp_password'] ?? '') !== '',
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
        $sideSaved = false;

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
        if (array_key_exists('admin_emails', $in)) { $new['admin_emails'] = normalize_admin_emails($in['admin_emails']); }
        if (array_key_exists('security_require_recaptcha', $in)) { $new['security_require_recaptcha'] = !empty($in['security_require_recaptcha']); }
        if (isset($in['recaptcha_site_key']) && is_string($in['recaptcha_site_key'])) { $new['recaptcha_site_key'] = mb_substr(trim($in['recaptcha_site_key']), 0, 220); }
        if (isset($in['recaptcha_secret_key']) && is_string($in['recaptcha_secret_key'])) {
            $sec = trim($in['recaptcha_secret_key']);
            if ($sec !== '') { $new['recaptcha_secret_key'] = mb_substr($sec, 0, 260); }
        }
        if (!empty($in['recaptcha_secret_clear'])) { $new['recaptcha_secret_key'] = ''; }
        if (isset($in['recaptcha_min_score'])) { $new['recaptcha_min_score'] = max(0.1, min(0.9, (float)$in['recaptcha_min_score'])); }
        if (array_key_exists('security_block_disposable_emails', $in)) { $new['security_block_disposable_emails'] = !empty($in['security_block_disposable_emails']); }
        if (array_key_exists('security_block_subdomain_emails', $in)) { $new['security_block_subdomain_emails'] = !empty($in['security_block_subdomain_emails']); }
        if (array_key_exists('security_extra_blocked_email_domains', $in)) { $new['security_extra_blocked_email_domains'] = normalize_domain_list($in['security_extra_blocked_email_domains']); }
        if (array_key_exists('security_trusted_email_domains', $in)) { $new['security_trusted_email_domains'] = normalize_domain_list($in['security_trusted_email_domains']); }
        if (array_key_exists('security_datacenter_cidrs', $in)) {
            $cidrs = normalize_cidr_list($in['security_datacenter_cidrs']);
            if (!save_json_atomic(data_dir() . '/security_datacenter_cidrs.json', $cidrs)) { json_out(['ok' => false, 'error' => 'Could not save datacenter CIDR list.'], 500); }
            $sideSaved = true;
        }
        if (isset($in['mail_transport'])) {
            $mt = strtolower(trim((string)$in['mail_transport']));
            if (in_array($mt, ['mail', 'smtp'], true)) { $new['mail_transport'] = $mt; }
        }
        if (isset($in['mail_from_email']) && is_string($in['mail_from_email'])) {
            $v = strtolower(trim($in['mail_from_email']));
            $new['mail_from_email'] = filter_var($v, FILTER_VALIDATE_EMAIL) ? $v : '';
        }
        if (isset($in['mail_from_name']) && is_string($in['mail_from_name'])) { $new['mail_from_name'] = devil_mail_clean_header($in['mail_from_name'], 80) ?: 'Devil AI'; }
        if (isset($in['mail_reply_to']) && is_string($in['mail_reply_to'])) {
            $v = strtolower(trim($in['mail_reply_to']));
            $new['mail_reply_to'] = filter_var($v, FILTER_VALIDATE_EMAIL) ? $v : '';
        }
        if (isset($in['smtp_host']) && is_string($in['smtp_host'])) { $new['smtp_host'] = mb_substr(trim($in['smtp_host']), 0, 180); }
        if (isset($in['smtp_port'])) { $new['smtp_port'] = max(1, min(65535, (int)$in['smtp_port'])); }
        if (isset($in['smtp_secure']) && is_string($in['smtp_secure'])) {
            $secMode = strtolower(trim($in['smtp_secure']));
            if (in_array($secMode, ['none', 'tls', 'ssl'], true)) { $new['smtp_secure'] = $secMode; }
        }
        if (isset($in['smtp_username']) && is_string($in['smtp_username'])) { $new['smtp_username'] = mb_substr(trim($in['smtp_username']), 0, 180); }
        if (isset($in['smtp_password']) && is_string($in['smtp_password'])) {
            $sp = trim($in['smtp_password']);
            if ($sp !== '') { $new['smtp_password'] = mb_substr($sp, 0, 260); }
        }
        if (!empty($in['smtp_password_clear'])) { $new['smtp_password'] = ''; }
        if (isset($in['new_admin_password']) && is_string($in['new_admin_password'])) {
            $np = trim($in['new_admin_password']);
            if ($np !== '') {
                if (strlen($np) < 6) { json_out(['ok' => false, 'error' => 'New admin password must be at least 6 characters.']); }
                $new['admin_password'] = $np;
            }
        }

        if (!$new && !$sideSaved) { json_out(['ok' => false, 'error' => 'Nothing to save.'], 400); }
        if (!$new && $sideSaved) { json_out(['ok' => true, 'security' => admin_security_snapshot()]); }
        $merged = array_merge($cfg, $new);
        unset($merged['api_key'], $merged['provider'], $merged['model'], $merged['fallback'], $merged['prexzy_endpoint']);
        if (!save_json_atomic(data_dir() . '/config.json', $merged)) {
            json_out(['ok' => false, 'error' => 'Could not write data/config.json — check folder permissions.'], 500);
        }
        json_out(['ok' => true, 'security' => admin_security_snapshot()]);
    }

    if ($action === 'admin_security_clear' && $method === 'POST') {
        $in = input_json();
        if (!admin_ok((string)($in['current_admin_password'] ?? ''))) {
            json_out(['ok' => false, 'error' => 'Admin password is incorrect.'], 403);
        }
        @file_put_contents(data_dir() . '/security_bans.json', json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        @file_put_contents(data_dir() . '/security_rl.json', json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        json_out(['ok' => true, 'security' => admin_security_snapshot()]);
    }

    if ($action === 'admin_mail_test' && $method === 'POST') {
        $in = input_json();
        if (!admin_ok((string)($in['current_admin_password'] ?? ''))) {
            json_out(['ok' => false, 'error' => 'Admin password is incorrect.'], 403);
        }
        $to = strtolower(trim((string)($in['to'] ?? '')));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { $to = admin_emails()[0] ?? 'bk.w.p.bk@gmail.com'; }
        $html = '<div style="font-family:Segoe UI,Arial,sans-serif;line-height:1.6"><h2>Devil AI mail test</h2><p>If this reached Gmail, SMTP/mail delivery is working.</p><p><b>Time:</b> ' . htmlspecialchars(date('c'), ENT_QUOTES) . '</p></div>';
        $text = "Devil AI mail test\nIf this reached Gmail, SMTP/mail delivery is working.\nTime: " . date('c');
        $ok = devil_mail($to, 'Devil AI mail test', $html, $text);
        json_out(['ok' => $ok, 'to' => $to, 'error' => $ok ? '' : 'Mail send failed. Check SMTP settings or data/mail.log.']);
    }

    if ($action === 'admin_feedback' && $method === 'POST') {
        $in = input_json();
        if (!admin_ok((string)($in['current_admin_password'] ?? ''))) {
            json_out(['ok' => false, 'error' => 'Admin password is incorrect.'], 403);
        }
        $all = load_json(feedback_path());
        $items = [];
        foreach ($all as $entry) {
            if (!is_array($entry)) { continue; }
            $items[] = [
                'rating' => (string)($entry['rating'] ?? ''),
                'content' => mb_substr((string)($entry['content'] ?? ''), 0, 1200),
                'chat_id' => (string)($entry['chat_id'] ?? ''),
                'message_index' => (int)($entry['message_index'] ?? -1),
                'user' => is_array($entry['user'] ?? null) ? $entry['user'] : [],
                'ip' => (string)($entry['ip'] ?? ''),
                'ts' => (int)($entry['ts'] ?? 0),
            ];
        }
        usort($items, function ($a, $b) { return (int)($b['ts'] ?? 0) <=> (int)($a['ts'] ?? 0); });
        json_out(['ok' => true, 'feedback' => array_slice($items, 0, 120)]);
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
    if (in_array($action, ['chats', 'chat_load', 'chat_send', 'chat_edit', 'chat_share', 'feedback', 'chat_delete', 'chat_rename', 'account_delete', 'dev_keys', 'dev_key_create', 'dev_key_revoke', 'dev_usage', 'dev_playground'], true)) {
        if (!$user) { json_out(['ok' => false, 'error' => 'Please sign in again.'], 401); }
    }
    $uid = $user ? (string)$user['id'] : '';

    /* Release the PHP session lock before chat/file/AI work so parallel requests
       (chat_load + sidebar list, or page refresh + API calls) do not block each other. */
    if ($action !== 'account_delete' && session_status() === PHP_SESSION_ACTIVE) { @session_write_close(); }

    if ($action === 'dev_keys' && $method === 'GET') {
        $all = load_dev_keys();
        $mine = [];
        foreach ($all as $rec) {
            if (is_array($rec) && (string)($rec['uid'] ?? '') === $uid) { $mine[] = dev_public_key($rec); }
        }
        usort($mine, function ($a, $b) { return (int)($b['created'] ?? 0) <=> (int)($a['created'] ?? 0); });
        json_out(['ok' => true, 'keys' => $mine]);
    }

    if ($action === 'dev_key_create' && $method === 'POST') {
        $in = input_json();
        $all = load_dev_keys();
        $active = 0;
        foreach ($all as $rec) { if (is_array($rec) && (string)($rec['uid'] ?? '') === $uid && empty($rec['revoked'])) { $active++; } }
        if ($active >= 12) { json_out(['ok' => false, 'error' => 'You can keep up to 12 active API keys. Revoke an old key first.'], 400); }
        $token = 'dv_live_' . bin2hex(random_bytes(24));
        $id = 'dk_' . bin2hex(random_bytes(8));
        $rec = [
            'id' => $id,
            'uid' => $uid,
            'name' => dev_clean_key_name((string)($in['name'] ?? '')),
            'hash' => hash('sha256', $token),
            'prefix' => substr($token, 0, 15),
            'last4' => substr($token, -4),
            'created' => time(),
            'last_used' => 0,
            'requests' => 0,
            'revoked' => false,
        ];
        $all[$id] = $rec;
        if (!save_dev_keys($all)) { json_out(['ok' => false, 'error' => 'Could not create API key.'], 500); }
        json_out(['ok' => true, 'key' => dev_public_key($rec), 'token' => $token]);
    }

    if ($action === 'dev_key_revoke' && $method === 'POST') {
        $in = input_json();
        $id = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($in['id'] ?? ''));
        $all = load_dev_keys();
        if ($id === '' || !isset($all[$id]) || !is_array($all[$id]) || (string)($all[$id]['uid'] ?? '') !== $uid) {
            json_out(['ok' => false, 'error' => 'API key not found.'], 404);
        }
        $all[$id]['revoked'] = true;
        $all[$id]['revoked_at'] = time();
        if (!save_dev_keys($all)) { json_out(['ok' => false, 'error' => 'Could not revoke API key.'], 500); }
        json_out(['ok' => true]);
    }

    if ($action === 'dev_usage' && $method === 'GET') {
        $all = load_dev_keys();
        $rlMap = load_json(data_dir() . '/devrl.json');
        $now = time();
        $keys = [];
        $active = 0; $revoked = 0; $totalRequests = 0; $hourRequests = 0; $lastUsed = 0;
        foreach ($all as $rec) {
            if (!is_array($rec) || (string)($rec['uid'] ?? '') !== $uid) { continue; }
            $pub = dev_public_key($rec);
            $kid = (string)($rec['id'] ?? '');
            $hits = [];
            foreach (($rlMap['k:' . $kid] ?? []) as $t) { if (is_int($t) && $t > $now - 3600) { $hits[] = $t; } }
            $pub['used_this_hour'] = count($hits);
            $hourRequests += count($hits);
            $totalRequests += (int)($rec['requests'] ?? 0);
            $lastUsed = max($lastUsed, (int)($rec['last_used'] ?? 0));
            if (!empty($rec['revoked'])) { $revoked++; } else { $active++; }
            $keys[] = $pub;
        }
        usort($keys, function ($a, $b) { return (int)($b['created'] ?? 0) <=> (int)($a['created'] ?? 0); });
        $cfgAll = load_config();
        json_out([
            'ok' => true,
            'summary' => [
                'active_keys' => $active,
                'revoked_keys' => $revoked,
                'total_requests' => $totalRequests,
                'used_this_hour' => $hourRequests,
                'rate_per_hour' => (int)($cfgAll['rate_per_hour'] ?? 40),
                'last_used' => $lastUsed,
            ],
            'keys' => $keys,
            'models' => dev_api_models(),
            'base_url' => app_base_url() . '/v1',
        ]);
    }

    if ($action === 'dev_playground' && $method === 'POST') {
        $in = input_json();
        $rawModel = strtolower(trim((string)($in['model'] ?? 'devil-flash')));
        if (strpos($rawModel, 'devil-') === 0) { $rawModel = substr($rawModel, 6); }
        if (!in_array($rawModel, ['flash', 'pro', 'ultra'], true)) { json_out(['ok' => false, 'error' => 'Choose a valid Devil model.'], 400); }
        $message = trim((string)($in['message'] ?? ''));
        $system = trim((string)($in['system'] ?? ''));
        if ($message === '') { json_out(['ok' => false, 'error' => 'Enter a prompt to test.'], 400); }
        if (mb_strlen($message) > MAX_INPUT) { json_out(['ok' => false, 'error' => 'Prompt is too long (max ' . MAX_INPUT . ' characters).'], 400); }
        if (mb_strlen($system) > 1200) { $system = mb_substr($system, 0, 1200); }
        $cfgAll = load_config();
        $rl = max(1, min(1000, (int)($cfgAll['rate_per_hour'] ?? 40)));
        if (!rate_ok('devrl.json', 'play:u:' . $uid, $rl, 3600)) {
            json_out(['ok' => false, 'error' => 'Playground rate limit reached. Try again later.'], 429);
        }
        $messages = [];
        if ($system !== '') { $messages[] = ['role' => 'user', 'content' => 'Developer instruction: ' . $system]; }
        $messages[] = ['role' => 'user', 'content' => $message];
        $providerMsgs = array_merge([['role' => 'system', 'content' => PREXZY_PERSONA]], $messages);
        list($ok, $reply, $used) = ai_respond($cfgAll, $rawModel, $providerMsgs, '');
        if (!$ok) { json_out(['ok' => false, 'error' => $reply, 'hint' => $used], 502); }
        json_out(['ok' => true, 'reply' => $reply, 'model' => ['id' => dev_public_model_id($rawModel), 'label' => model_label($rawModel)], 'usage' => dev_usage($messages, $reply)]);
    }

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
        devil_session_destroy_all();
        json_out(['ok' => true]);
    }

    if ($action === 'chats' && $method === 'GET') {
        json_out(['ok' => true, 'chats' => list_chats($uid)]);
    }

    if ($action === 'chat_load' && $method === 'GET') {
        $chat = load_chat($uid, (string)($_GET['id'] ?? ''));
        if (!$chat) { json_out(['ok' => false, 'error' => 'Chat not found.'], 404); }
        $variant = (string)($_GET['variant'] ?? '');
        if ($variant !== '' && $variant !== 'original' && !preg_match('/^(?:c[a-f0-9]{6,32}|[a-f0-9]{128})$/', $variant)) { $variant = ''; }
        list($displayChat, $branchGroups) = display_chat_variant($uid, $chat, $variant);
        json_out(['ok' => true, 'chat' => $displayChat, 'branch_groups' => $branchGroups]);
    }

    if ($action === 'chat_delete' && $method === 'POST') {
        $in = input_json();
        $id = (string)($in['id'] ?? '');
        $chat = load_chat($uid, $id);
        if (!$chat) { json_out(['ok' => false, 'error' => 'Chat not found.'], 404); }
        $rootId = chat_branch_root_id($chat);
        foreach (glob(chats_dir($uid) . '/*.json') ?: [] as $f) {
            $j = json_decode((string)file_get_contents($f), true);
            if (!is_array($j) || empty($j['id'])) { continue; }
            if ((string)$j['id'] === $rootId || chat_branch_root_id($j) === $rootId) { @unlink($f); }
        }
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

    if ($action === 'feedback' && $method === 'POST') {
        $in = input_json();
        $rating = strtolower(trim((string)($in['rating'] ?? '')));
        if (!in_array($rating, ['good', 'bad'], true)) { json_out(['ok' => false, 'error' => 'Invalid feedback.'], 400); }
        $content = trim((string)($in['content'] ?? ''));
        if ($content === '') { json_out(['ok' => false, 'error' => 'Feedback content is empty.'], 400); }
        $chatId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($in['chat_id'] ?? ''));
        $msgIndex = (int)($in['message_index'] ?? -1);
        $key = hash('sha256', $uid . '|' . $chatId . '|' . $msgIndex . '|' . mb_substr($content, 0, 500));
        $all = load_json(feedback_path());
        if (isset($all[$key])) { json_out(['ok' => true, 'duplicate' => true]); }
        $entry = [
            'key' => $key,
            'rating' => $rating,
            'content' => mb_substr($content, 0, 4000),
            'chat_id' => $chatId,
            'message_index' => $msgIndex,
            'user' => ['id' => $uid, 'name' => (string)($user['name'] ?? ''), 'email' => (string)($user['email'] ?? '')],
            'ip' => client_ip(),
            'ts' => time(),
        ];
        $all[$key] = $entry;
        save_json_atomic(feedback_path(), $all);
        $safeContent = nl2br(htmlspecialchars($entry['content'], ENT_QUOTES));
        $safeRating = htmlspecialchars(strtoupper($rating), ENT_QUOTES);
        $safeUser = htmlspecialchars(($entry['user']['name'] ?? '') . ' <' . ($entry['user']['email'] ?? '') . '>', ENT_QUOTES);
        $safeChat = htmlspecialchars($chatId, ENT_QUOTES);
        $link = $chatId ? app_base_url() . '/app.php?chat=' . rawurlencode($chatId) : '';
        $safeLink = htmlspecialchars($link, ENT_QUOTES);
        $html = '<div style="font-family:Segoe UI,Arial,sans-serif;line-height:1.6">'
              . '<h2>Devil AI feedback: ' . $safeRating . '</h2>'
              . '<p><b>User:</b> ' . $safeUser . '<br><b>Chat:</b> ' . $safeChat . '<br><b>Message index:</b> ' . $msgIndex . '</p>'
              . ($link ? '<p><a href="' . $safeLink . '">Open chat</a></p>' : '')
              . '<hr><p><b>Response:</b></p><div style="white-space:pre-wrap;background:#f6f6f6;padding:12px;border-radius:8px">' . $safeContent . '</div></div>';
        $textMail = "Devil AI feedback: " . strtoupper($rating) . "\nUser: " . ($entry['user']['name'] ?? '') . " <" . ($entry['user']['email'] ?? '') . ">\nChat: {$chatId}\nMessage index: {$msgIndex}\n" . ($link ? "Link: {$link}\n" : '') . "\nResponse:\n" . $entry['content'];
        $recipients = admin_emails() ?: ['bk.w.p.bk@gmail.com'];
        $mailed = false;
        foreach ($recipients as $rcpt) { if (devil_mail($rcpt, 'Devil AI feedback: ' . strtoupper($rating), $html, $textMail)) { $mailed = true; } }
        json_out(['ok' => true, 'mailed' => $mailed]);
    }

    if ($action === 'chat_share' && $method === 'POST') {
        $in = input_json();
        $id = (string)($in['id'] ?? '');
        $variant = (string)($in['variant'] ?? '');
        if ($variant !== '' && $variant !== 'original' && !preg_match('/^(?:c[a-f0-9]{6,32}|[a-f0-9]{128})$/', $variant)) { $variant = ''; }
        $chat = load_chat($uid, $id);
        if (!$chat) { json_out(['ok' => false, 'error' => 'Chat not found.'], 404); }
        list($displayChat) = display_chat_variant($uid, $chat, $variant);
        $sid = share_id();
        $payload = public_chat_payload($displayChat, $user);
        $payload['id'] = $sid;
        $payload['legacy_url'] = app_base_url() . '/share.php?id=' . rawurlencode($sid);
        if (!save_json_atomic(share_path($sid), $payload)) { json_out(['ok' => false, 'error' => 'Could not create share link.'], 500); }
        json_out(['ok' => true, 'id' => $sid, 'url' => share_public_url($sid), 'legacy_url' => $payload['legacy_url']]);
    }

    if ($action === 'chat_edit' && $method === 'POST') {
        $in = input_json();
        $id = (string)($in['id'] ?? '');
        $variantId = (string)($in['variant'] ?? '');
        $idx = (int)($in['message_index'] ?? -1);
        $newText = trim((string)($in['message'] ?? ''));
        if ($newText === '') { json_out(['ok' => false, 'error' => 'Edited message is empty.'], 400); }
        if (mb_strlen($newText) > MAX_INPUT) { json_out(['ok' => false, 'error' => 'Message is too long (max ' . MAX_INPUT . ' characters).'], 400); }
        $srcBase = load_chat($uid, $id);
        if (!$srcBase) { json_out(['ok' => false, 'error' => 'Chat not found.'], 404); }
        list($src, $unusedBranchGroups, $rootChatForEdit, $activeVariantForEdit) = display_chat_variant($uid, $srcBase, $variantId);
        $rootIdForEdit = (string)($rootChatForEdit['id'] ?? chat_branch_root_id($srcBase));
        $msgs0 = array_values(array_filter($src['messages'] ?? [], 'is_array'));
        if (!isset($msgs0[$idx]) || (($msgs0[$idx]['role'] ?? '') !== 'user')) { json_out(['ok' => false, 'error' => 'That message cannot be edited.'], 400); }

        $cfgAll = load_config();
        $rl = (int)$cfgAll['rate_per_hour'];
        if (!rate_ok('rl.json', 'u:' . $uid, $rl, 3600)) {
            json_out(['ok' => false, 'error' => "Easy there, human! You're sending messages too fast.", 'hint' => 'Limit: ' . $rl . ' messages per hour. Please wait a bit.'], 429);
        }
        $tz = (string)($cfgAll['timezone'] ?? '');
        if ($tz !== '' && in_array($tz, timezone_identifiers_list(), true)) { date_default_timezone_set($tz); }

        $model = 'flash'; $customModel = '';
        for ($i = $idx + 1; $i < count($msgs0); $i++) {
            if (($msgs0[$i]['role'] ?? '') === 'assistant') {
                $model = (string)($msgs0[$i]['model_id'] ?? 'flash');
                $customModel = (string)($msgs0[$i]['custom_model'] ?? '');
                break;
            }
        }
        $validModel = false;
        foreach (public_models() as $mm) { if ($mm['id'] === $model) { $validModel = true; break; } }
        if (!$validModel) { $model = 'flash'; $customModel = ''; }
        if ($model === 'custom' && !custom_model_by_id($customModel)) { $customModel = 'askgpt5'; }

        $branchMsgs = array_slice($msgs0, 0, $idx);
        $edited = $msgs0[$idx];
        $edited['content'] = $newText;
        $edited['edited'] = true;
        $edited['edited_from_chat'] = (string)$src['id'];
        $edited['edited_at'] = time();
        $branchMsgs[] = $edited;

        $hist = array_slice($branchMsgs, -20);
        $providerMsgs = array_merge([['role' => 'system', 'content' => PREXZY_PERSONA]], $hist);
        $img = (string)($edited['img'] ?? '');
        $aiModel = ($model === 'custom') ? ('custom:' . $customModel) : $model;
        $displayLabel = ($model === 'custom') ? custom_model_label($customModel) : model_label($model);
        list($ok, $reply, $used) = ai_respond($cfgAll, $aiModel, $providerMsgs, $img);
        if (!$ok) { json_out(['ok' => false, 'error' => $reply, 'hint' => $used], 502); }

        $assistantMsg = ['role' => 'assistant', 'content' => $reply, 'ts' => time(), 'model_id' => $model, 'model_label' => $displayLabel];
        if ($customModel !== '') { $assistantMsg['custom_model'] = $customModel; }
        $branchMsgs[] = $assistantMsg;
        $title = trim(preg_replace('/\s+/u', ' ', $newText));
        $title = mb_strlen($title) > 54 ? mb_substr($title, 0, 51) . '…' : ($title ?: 'Edited chat');
        $branchRoot = $rootIdForEdit;
        $variantIdNew = slug128();
        $variant = [
            'id' => $variantIdNew,
            'title' => $title . ' (edited)',
            'created' => time(),
            'updated' => time(),
            'edited_message_index' => $idx,
            'messages' => $branchMsgs,
        ];
        $key = (string)$idx;
        if (!isset($rootChatForEdit['edit_variants']) || !is_array($rootChatForEdit['edit_variants'])) { $rootChatForEdit['edit_variants'] = []; }
        if (!isset($rootChatForEdit['edit_variants'][$key]) || !is_array($rootChatForEdit['edit_variants'][$key])) { $rootChatForEdit['edit_variants'][$key] = []; }
        $rootChatForEdit['edit_variants'][$key][] = $variant;
        $rootChatForEdit['active_variant'] = $variantIdNew;
        $rootChatForEdit['updated'] = time();
        if (!save_chat($uid, $rootChatForEdit)) { json_out(['ok' => false, 'error' => 'Could not save edited version.'], 500); }
        list($displayBranch, $branchGroups) = display_chat_variant($uid, $rootChatForEdit, $variantIdNew);
        $modelOut = ['id' => $model, 'label' => $displayLabel];
        if ($customModel !== '') { $modelOut['custom'] = $customModel; }
        json_out(['ok' => true, 'id' => $branchRoot, 'slug' => (string)($rootChatForEdit['slug'] ?? ''), 'variant' => $variantIdNew, 'title' => $displayBranch['title'], 'reply' => $reply, 'model' => $modelOut, 'branched' => true, 'chat' => $displayBranch, 'branch_groups' => $branchGroups]);
    }

    if ($action === 'chat_send' && $method === 'POST') {
        $in     = input_json();
        $retry  = !empty($in['retry']);
        $temp   = !empty($in['temp']);
        $variantId = (string)($in['variant'] ?? '');
        $msg    = trim((string)($in['message'] ?? ''));
        $model  = (string)($in['model'] ?? 'flash');
        $img    = '';
        if (isset($in['image']) && is_string($in['image']) && trim($in['image']) !== '') {
            $img = validate_image($in['image']);
            if ($img === null) { json_out(['ok' => false, 'error' => 'That image could not be read. Use a PNG, JPEG, GIF or WebP file under 2 MB.'], 400); }
        }
        list($attachmentsMeta, $attachmentContext, $attachmentImage) = process_attachments($in['attachments'] ?? []);
        if ($img === '' && $attachmentImage !== '') { $img = $attachmentImage; }
        $validModel = false;
        foreach (public_models() as $mm) { if ($mm['id'] === $model) { $validModel = true; break; } }
        if (!$validModel) { $model = 'flash'; }
        $customModel = '';
        if ($model === 'custom') {
            $customModel = strtolower(trim((string)($in['custom_model'] ?? 'askgpt5')));
            if (!custom_model_by_id($customModel)) { $customModel = 'askgpt5'; }
        }

        $cfgAll = load_config();

        /* per-user rate limit */
        $rl = (int)$cfgAll['rate_per_hour'];
        if (!rate_ok('rl.json', 'u:' . $uid, $rl, 3600)) {
            json_out(['ok' => false, 'error' => "Easy there, human! You're sending messages too fast.", 'hint' => 'Limit: ' . $rl . ' messages per hour. Please wait a bit.'], 429);
        }

        /* timezone */
        $tz = (string)($cfgAll['timezone'] ?? '');
        if ($tz !== '' && in_array($tz, timezone_identifiers_list(), true)) { date_default_timezone_set($tz); }

        /* load/create saved chat, or build an unsaved temporary chat from client history */
        $chat = null;
        $responseRootId = '';
        $responseVariant = '';
        $rootChatForSend = null;
        if ($temp) {
            $chat = ['id' => null, 'title' => 'Temporary chat', 'created' => time(), 'updated' => time(), 'messages' => []];
            $histIn = isset($in['history']) && is_array($in['history']) ? array_slice($in['history'], -20) : [];
            foreach ($histIn as $hm) {
                if (!is_array($hm)) { continue; }
                $r = (string)($hm['role'] ?? '');
                if ($r !== 'user' && $r !== 'assistant') { continue; }
                $c = trim((string)($hm['content'] ?? ''));
                if ($c === '') { continue; }
                $chat['messages'][] = ['role' => $r, 'content' => mb_substr($c, 0, MAX_INPUT), 'ts' => time()];
            }
        } else {
            if (!empty($in['id'])) {
                $baseChat = load_chat($uid, (string)$in['id']);
                if (!$baseChat) { json_out(['ok' => false, 'error' => 'Chat not found.'], 404); }
                list($chat, $unusedBranchGroups, $rootChatForSend, $activeVariantForSend) = display_chat_variant($uid, $baseChat, $variantId);
                $responseRootId = (string)($rootChatForSend['id'] ?? chat_branch_root_id($baseChat));
                $responseVariant = ($activeVariantForSend !== '' && $activeVariantForSend !== $responseRootId && $activeVariantForSend !== 'original') ? $activeVariantForSend : '';
            }
            if (!$chat) {
                $existing = list_chats($uid);
                if (count($existing) >= (int)$cfgAll['max_chats']) {
                    json_out(['ok' => false, 'error' => 'You reached your chat limit (' . (int)$cfgAll['max_chats'] . ').', 'hint' => 'Delete some old chats to make room.'], 400);
                }
                $chat = ['id' => 'c' . bin2hex(random_bytes(8)), 'title' => '', 'created' => time(), 'updated' => time(), 'messages' => []];
                $responseRootId = (string)$chat['id'];
            }
        }
        $chat['messages'] = array_values(array_filter($chat['messages'] ?? [], 'is_array'));

        if ($retry) {
            if (count($chat['messages']) && ($chat['messages'][count($chat['messages']) - 1]['role'] ?? '') === 'assistant') {
                array_pop($chat['messages']);
            }
            $lastUser = ''; $lastImg = '';
            foreach (array_reverse($chat['messages']) as $m) {
                if (($m['role'] ?? '') === 'user') { $lastUser = (string)$m['content']; $lastImg = (string)($m['img'] ?? ''); break; }
            }
            if ($lastUser === '' && $lastImg === '') { json_out(['ok' => false, 'error' => 'Nothing to retry.'], 400); }
            $msg = $lastUser; $img = $lastImg;
        } else {
            if ($msg === '' && $img === '' && empty($attachmentsMeta)) { json_out(['ok' => false, 'error' => 'Message is empty.'], 400); }
            if (mb_strlen($msg) > MAX_INPUT) { json_out(['ok' => false, 'error' => 'Message is too long (max ' . MAX_INPUT . ' characters).'], 400); }
            $newMsg = ['role' => 'user', 'content' => $msg, 'ts' => time()];
            if ($img !== '') { $newMsg['img'] = $img; }
            if (!empty($attachmentsMeta)) { $newMsg['attachments'] = $attachmentsMeta; }
            if ($attachmentContext !== '') { $newMsg['attachment_text'] = "Attached files read by Devil AI:\n" . $attachmentContext; }
            $chat['messages'][] = $newMsg;
        }

        /* build provider messages (history cap) */
        $hist = array_slice($chat['messages'], -20);
        $providerMsgs = array_merge([['role' => 'system', 'content' => PREXZY_PERSONA]], $hist);

        /* If the user sends a short follow-up after uploading an image ("yes", "what is this?",
           "decode it"), carry the most recent image into the AI call so the chat keeps context. */
        $aiImg = $img;
        if ($aiImg === '' && should_reuse_recent_image($msg)) {
            $aiImg = recent_chat_image($chat['messages']);
        }

        $aiModel = ($model === 'custom') ? ('custom:' . $customModel) : $model;
        $displayLabel = ($model === 'custom') ? custom_model_label($customModel) : model_label($model);

        list($ok, $reply, $used) = ai_respond($cfgAll, $aiModel, $providerMsgs, $aiImg);
        if (!$ok) { json_out(['ok' => false, 'error' => $reply, 'hint' => $used], 502); }

        $assistantMsg = ['role' => 'assistant', 'content' => $reply, 'ts' => time(), 'model_id' => $model, 'model_label' => $displayLabel];
        if ($customModel !== '') { $assistantMsg['custom_model'] = $customModel; }
        $chat['messages'][] = $assistantMsg;
        if ($chat['title'] === '') {
            $title = trim(preg_replace('/\s+/u', ' ', $msg));
            if ($title === '' && !empty($attachmentsMeta)) { $title = 'Attachment: ' . (string)($attachmentsMeta[0]['name'] ?? 'file'); }
            if ($title === '' && $img !== '') { $title = 'Image'; }
            $chat['title'] = mb_strlen($title) > 60 ? mb_substr($title, 0, 57) . '…' : ($title !== '' ? $title : 'New chat');
        }
        if (count($chat['messages']) > MAX_MSGS_PER_CHAT) { $chat['messages'] = array_slice($chat['messages'], -MAX_MSGS_PER_CHAT); }
        $chat['updated'] = time();

        $modelOut = ['id' => $model, 'label' => $displayLabel];
        if ($customModel !== '') { $modelOut['custom'] = $customModel; }

        if ($temp) {
            json_out(['ok' => true, 'id' => null, 'temp' => true, 'title' => 'Temporary chat', 'reply' => $reply, 'model' => $modelOut]);
        }

        if ($rootChatForSend) {
            update_root_variant_messages($rootChatForSend, $responseVariant !== '' ? $responseVariant : (string)$responseRootId, $chat);
            ensure_chat_meta($rootChatForSend);
            if (!save_chat($uid, $rootChatForSend)) { json_out(['ok' => false, 'error' => 'Could not save the chat — check data/ permissions.'], 500); }
            $outId = (string)$rootChatForSend['id'];
            $outSlug = (string)($rootChatForSend['slug'] ?? '');
            $outTitle = (string)($rootChatForSend['title'] ?? $chat['title']);
            $outModel = (string)($rootChatForSend['url_model'] ?? 'flash');
            $outType = (string)($rootChatForSend['url_type'] ?? 'chat');
        } else {
            ensure_chat_meta($chat);
            if (!save_chat($uid, $chat)) { json_out(['ok' => false, 'error' => 'Could not save the chat — check data/ permissions.'], 500); }
            $outId = (string)$chat['id'];
            $outSlug = (string)($chat['slug'] ?? '');
            $outTitle = (string)$chat['title'];
            $outModel = (string)($chat['url_model'] ?? 'flash');
            $outType = (string)($chat['url_type'] ?? 'chat');
        }
        json_out(['ok' => true, 'id' => $outId, 'slug' => $outSlug, 'variant' => $responseVariant, 'title' => $outTitle, 'url_model' => $outModel, 'url_type' => $outType, 'reply' => $reply, 'model' => $modelOut]);
    }

    json_out(['ok' => false, 'error' => 'Unknown action.'], 404);

} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'Server error: ' . $e->getMessage()], 500);
}
