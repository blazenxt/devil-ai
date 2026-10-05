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

    /* ═══════ MODELS → ENGINES (server-side secret) ═══════
       The public only sees "Devil Flash / Pro / Ultra".
       Which real engine powers each model is configured here
       (or in Admin settings) and is NEVER exposed publicly.
         'prexzy:askgpt5'  — free, no key (fast & strong)
         'prexzy:gemini'   — free, no key
         'prexzy:quick'    — free, no key
         'gemini:key'      — Google Gemini via site API key
         'demo'            — offline brain
       Automatic fallback: if any engine fails, Devil AI
       retries on Prexzy — the chat never dies.            */
    'engines' => [
        'flash' => 'prexzy:askgpt5',
        'pro'   => 'prexzy:gemini',
        'ultra' => 'gemini:key',
    ],

    /* Gemini settings (only used by the 'gemini:key' engine).
       Free key: https://aistudio.google.com/apikey */
    'gemini_api_key' => '',
    'gemini_model'   => 'gemini-2.5-flash',

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
    'recaptcha_min_score' => 0.45,
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
