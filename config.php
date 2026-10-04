<?php
/**
 * ═══════════════════════════════════════════════════════
 *  😈 DEVIL AI — Configuration (config.php) • v1.0.0.0
 * ═══════════════════════════════════════════════════════
 *  You can also change providers from the in-app ⚙️ Settings
 *  panel — it saves to data/config.json and overrides this
 *  file (so you never have to edit code by hand).
 *
 *  ⚠️ BEFORE DEPLOYING PUBLICLY:
 *     1. Set admin_password (protects the Settings panel)
 *     2. Review rate_per_hour (protects your API quota)
 */

return [

    // 'prexzy' — FREE, NO API KEY (prexzyapis.com) ← default, zero setup
    // 'gemini' — Google Gemini, free key → aistudio.google.com/apikey (best quality)
    // 'demo'   — offline mode (basic replies only)
    'provider' => 'prexzy',

    // Gemini API key (only needed if provider = gemini).
    // Prexzy works without any key!
    'api_key' => '',

    // Gemini model — leave empty for default.
    //   gemini-2.5-flash  (default, best balance)
    //   gemini-2.5-flash-lite  (faster, higher free limits)
    'model' => '',

    // Prexzy endpoint (only for provider = prexzy):
    //   askgpt5  (default — Qwen 3.5 397B, strong)
    //   gemini   (Google Gemini via Prexzy)
    //   qwen
    'prexzy_endpoint' => 'askgpt5',

    // AUTO-FALLBACK: if the primary provider fails (bad key,
    // rate limit, downtime), Devil AI automatically retries
    // with this provider. Set '' to disable.
    'fallback' => 'prexzy',

    /* ═══════ PUBLIC DEPLOYMENT ═══════ */

    // Password required to save settings / run tests.
    // On a public site, ANYONE could change your settings — so set this!
    // (empty = settings open — fine for local testing only)
    'admin_password' => '',

    // Max messages per user (per IP) per hour — protects your quota
    'rate_per_hour' => 40,

    /* ═══════ FINE TUNING ═══════ */

    // Timezone (used for time/date questions)
    'timezone' => 'Asia/Kolkata',

    // Personality heat — 0.0 = plain, 0.8 = perfect, 1.2+ = crazy 😈
    // (Gemini only — Prexzy manages this itself)
    'temperature' => 0.8,

    // Max length of one answer (Gemini only)
    'max_tokens' => 1500,
];
