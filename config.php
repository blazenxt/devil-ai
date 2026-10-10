<?php
/**
 * ═══════════════════════════════════════════════════════
 *  DEVIL AI — Configuration (config.php) • v1.0.0.0
 * ═══════════════════════════════════════════════════════
 *  These are DEFAULT values. Everything here can also be
 *  changed from the in-app "Admin settings" panel — those
 *  changes are saved to data/config.json and override this
 *  file (so you never have to edit code by hand).
 *
 *  ⚠️ BEFORE DEPLOYING PUBLICLY: set admin_password via
 *  data/config.json or this file. It protects Admin settings.
 */

return [

    /* ═══════ QUICK MODEL SLOTS ═══════
       The three quick picks in the model menu. Each value is
       'gemini:<model id>' from the model catalogue in api.php
       (Admin settings can change them). Models show their real names.
       Needs gemini_api_key (data/config.json).                */
    'engines' => [
        'flash' => 'gemini:gemini-3.5-flash-lite',
        'pro'   => 'gemini:gemini-3.6-flash',
        'ultra' => 'gemini:gemini-3.8-flash',
    ],

    /* Gemini settings (also used by Agent Mode and the quick picks).
       Free key: https://aistudio.google.com/apikey */
    'gemini_api_key' => '',
    /* Extra Gemini keys, added from Admin settings. Key #1 (above) answers every request; the next one
       takes over automatically when that key hits its quota or rate limit. */
    'gemini_api_keys' => [],
    'gemini_model'   => 'gemini-2.5-flash',

    /* OpenRouter models use this key. Set it in Admin settings; never commit a real key here. */
    'openrouter_api_key' => '',

    /* ═══════ PUBLIC DEPLOYMENT ═══════ */

    /* Password for the Admin settings panel (empty = password login disabled;
       admin_emails below can still access after normal email login). */
    'admin_password' => '',

    /* Emails that get admin access after normal passwordless login. */
    'admin_emails' => ['bk.w.p.bk@gmail.com'],

    /* Max messages per user per hour (protects your quota) */
    'rate_per_hour' => 40,

    /* Max saved chats per user */
    'max_chats' => 100,

    /* Security hardening: optional Google reCAPTCHA v3 for login/signup.
       Set security_require_recaptcha=true plus both keys in data/config.json
       when attacks spike. */
    'security_require_recaptcha' => false,
    'recaptcha_site_key' => '',
    'recaptcha_secret_key' => '',
    'recaptcha_v2_site_key' => '',
    'recaptcha_v2_secret_key' => '',
    'recaptcha_min_score' => 0.45,

    /* Agent mode (Devil Agent): tools = web_search, fetch_url, calculator, datetime.
       agent_search_provider: duckduckgo (keyless) | brave | tavily (both need agent_search_api_key). */
    'agent_enabled' => true,
    'agent_max_steps' => 6,
    'agent_search_provider' => 'duckduckgo',
    'agent_search_api_key' => '',
    'agent_fetch_max_bytes' => 200000,
    /* Agent sandbox (real Linux computer per chat). Secret lives in data/config.json, never in git. */
    'sandbox_enabled' => false,
    'sandbox_url' => 'https://api.sandbox.devil.blazenxt.qzz.io',
    'sandbox_secret' => '',
    'agent_sandbox_max_steps' => 50,
    'security_block_disposable_emails' => true,
    'security_block_subdomain_emails' => true,
    'security_extra_blocked_email_domains' => [],
    'security_trusted_email_domains' => [],

    /* Email delivery. Native PHP mail() is kept as fallback, but Gmail delivery
       is much more reliable after SMTP is configured in Admin. */
    'mail_transport' => 'mail',
    'mail_from_email' => '',
    'mail_from_name' => 'Devil AI',
    'mail_reply_to' => 'bk.w.p.bk@gmail.com',
    'smtp_host' => '',
    'smtp_port' => 587,
    'smtp_secure' => 'tls',
    'smtp_username' => '',
    'smtp_password' => '',
    'resend_api_key' => '',

    /* ═══════ FINE TUNING ═══════ */

    /* Timezone (used for time/date questions) */
    'timezone' => 'Asia/Kolkata',

    /* Personality heat — 0.0 = plain, 0.8 = perfect, 1.2+ = wild
       (Gemini engine only) */
    'temperature' => 0.8,

    /* Max length of one answer (Gemini engine only) */
    'max_tokens' => 1500,
];
