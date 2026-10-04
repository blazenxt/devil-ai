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

    /* Password for the Admin settings panel (empty = open —
       fine for local testing only!) */
    'admin_password' => '',

    /* Max messages per user per hour (protects your quota) */
    'rate_per_hour' => 40,

    /* Max saved chats per user */
    'max_chats' => 100,

    /* ═══════ FINE TUNING ═══════ */

    /* Timezone (used for time/date questions) */
    'timezone' => 'Asia/Kolkata',

    /* Personality heat — 0.0 = plain, 0.8 = perfect, 1.2+ = wild
       (Gemini engine only) */
    'temperature' => 0.8,

    /* Max length of one answer (Gemini engine only) */
    'max_tokens' => 1500,
];
