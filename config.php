<?php
/**
 * ═══════════════════════════════════════════════════════
 *  😈 DEVIL AI — Configuration (config.php)
 * ═══════════════════════════════════════════════════════
 *  You can also change everything from the in-app ⚙️ Settings
 *  panel — it saves to data/config.json and overrides this
 *  file (so you never have to edit code by hand).
 *
 *  ⚠️ BEFORE DEPLOYING PUBLICLY:
 *     1. Set admin_password (protects the Settings panel)
 *     2. Review rate_per_hour (protects your API quota)
 */

return [

    // 'gemini'     (FREE — Google) → key: aistudio.google.com/apikey
    // 'groq'       (FREE — fast)   → key: console.groq.com/keys
    // 'openrouter' (free models)   → key: openrouter.ai/settings/keys
    // 'openai'     (paid)
    // 'custom'     (any OpenAI-compatible API — base_url required)
    // 'demo'       (offline, no key needed)
    'provider' => 'gemini',

    // Paste your API key here (or use the ⚙️ Settings panel).
    // Until a key is added, the app runs in Demo Mode.
    'api_key' => '',

    // Model — leave empty to use the provider default.
    //   gemini:      gemini-2.5-flash  |  gemini-2.5-flash-lite
    //   groq:        llama-3.3-70b-versatile  |  llama-3.1-8b-instant
    //   openai:      gpt-4o-mini
    //   openrouter:  meta-llama/llama-3.3-70b-instruct:free
    'model' => '',

    // Only for the 'custom' provider — e.g. https://api.example.com/v1
    'base_url' => '',

    /* ═══════ PUBLIC DEPLOYMENT ═══════ */

    // Password required to save settings / run tests.
    // On a public site, ANYONE could change your settings — so set this!
    // (empty = settings open — fine for local testing only)
    'admin_password' => '',

    // Max messages per user (per IP) per hour — protects your free quota
    'rate_per_hour' => 40,

    /* ═══════ FINE TUNING ═══════ */

    // Timezone (used for time/date questions)
    'timezone' => 'Asia/Kolkata',

    // Personality heat — 0.0 = plain, 0.8 = perfect, 1.2+ = crazy 😈
    'temperature' => 0.8,

    // Max length of one answer
    'max_tokens' => 1500,
];
