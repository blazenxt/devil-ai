<?php
/**
 * ═══════════════════════════════════════════════════════
 *  DEVIL AI — SVG Icon Library (inc/icons.php) • v1.0.0.0
 * ═══════════════════════════════════════════════════════
 *  Clean stroke-based SVG icons (24x24, currentColor).
 *  Every UI icon in Devil AI comes from here — no emoji UI.
 *
 *  Usage:  <?= icon('send', 18) ?>
 *          <?= icon('trash', 16, 'danger') ?>
 */

/* mbstring fallbacks (some shared hosts don't have the extension) */
if (!function_exists('mb_strtolower')) { function mb_strtolower($s) { return strtolower((string)$s); } }
if (!function_exists('mb_strlen'))     { function mb_strlen($s)     { return strlen((string)$s); } }
if (!function_exists('mb_substr'))     { function mb_substr($s, $a, $b = null) { return $b === null ? substr((string)$s, $a) : substr((string)$s, $a, $b); } }

function icon(string $name, int $size = 20, string $cls = ''): string
{
    static $I = null;
    if ($I === null) {
        $I = [

            /* ── navigation / layout ── */
            'menu'        => '<path d="M4 6h16M4 12h16M4 18h16"/>',
            'panel-left'  => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16"/>',
            'chevron-down'=> '<path d="m6 9 6 6 6-6"/>',
            'chevron-right'=>'<path d="m9 6 6 6-6 6"/>',
            'arrow-up'    => '<path d="M12 19V5M5 12l7-7 7 7"/>',
            'arrow-right' => '<path d="M5 12h14M12 5l7 7-7 7"/>',
            'external'    => '<path d="M15 3h6v6M10 14 21 3"/><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/>',
            'share'       => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 10.5 15.4 6.5M8.6 13.5l6.8 4"/>',
            'download'    => '<path d="M12 3v12M7 10l5 5 5-5"/><path d="M5 21h14"/>',

            /* ── chat ── */
            'new-chat'    => '<path d="M12 5v14M5 12h14"/>',
            'square-pen'  => '<path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.4 2.6a2 2 0 0 1 2.8 2.8L12 14.6 8 15.6l1-4Z"/>',
            'message'     => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z"/>',
            'copy'        => '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
            'thumb-up'    => '<path d="M7 10v11"/><path d="M15 6.5 14 10h4.8a2 2 0 0 1 2 2.3l-1.1 7a2 2 0 0 1-2 1.7H7l-4-1V10h4l5-7a2 2 0 0 1 3 2.3Z"/>',
            'thumb-down'  => '<path d="M7 14V3"/><path d="M15 17.5 14 14h4.8a2 2 0 0 0 2-2.3l-1.1-7a2 2 0 0 0-2-1.7H7L3 4v10h4l5 7a2 2 0 0 0 3-2.3Z"/>',
            'retry'       => '<path d="M21 12a9 9 0 1 1-2.6-6.4L21 8"/><path d="M21 3v5h-5"/>',
            'paperclip'   => '<path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/>',
            'mic'         => '<path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v3M8 22h8"/>',
            'mic-off'     => '<path d="m2 2 20 20"/><path d="M9 9v3a3 3 0 0 0 5.1 2.1M15 9.3V5a3 3 0 0 0-5.1-2.1"/><path d="M19 10v2a7 7 0 0 1-.7 3M5 10v2a7 7 0 0 0 11 5.7M12 19v3M8 22h8"/>',
            'volume'      => '<path d="M11 5 6 9H3v6h3l5 4V5Z"/><path d="M15.5 8.5a5 5 0 0 1 0 7M18.5 5.5a9 9 0 0 1 0 13"/>',
            'volume-x'    => '<path d="M11 5 6 9H3v6h3l5 4V5Z"/><path d="m22 9-6 6M16 9l6 6"/>',
            'image'       => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>',
            'send'        => '<path d="M12 19V5M5 12l7-7 7 7"/>',
            'stop'        => '<rect x="6" y="6" width="12" height="12" rx="2"/>',

            /* ── user / account ── */
            'user'        => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6 8-6s8 2 8 6"/>',
            'logout'      => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
            'mail'        => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6 10-6"/>',
            'key'         => '<circle cx="8" cy="15" r="4"/><path d="m10.8 12.2 8.7-8.7M15 4l3 3M18 7l2 2"/>',
            'eye'         => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
            'eye-off'     => '<path d="M2 12s3.5-7 10-7c2 0 3.8.6 5.3 1.5M22 12s-3.5 7-10 7c-2 0-3.8-.6-5.3-1.5"/><path d="m2 2 20 20"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',

            /* ── chat management ── */
            'trash'       => '<path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M10 11v6M14 11v6"/>',
            'pencil'      => '<path d="M17 3a2.8 2.8 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>',
            'search'      => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',

            /* ── status / feedback ── */
            'check'       => '<path d="M20 6 9 17l-5-5"/>',
            'x'           => '<path d="M18 6 6 18M6 6l12 12"/>',
            'warning'     => '<path d="M12 3 2 20h20L12 3Z"/><path d="M12 9v5M12 17.5v.5"/>',
            'info'        => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8v.5"/>',
            'loader'      => '<path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1"/>',

            /* ── security / privacy ── */
            'lock'        => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
            'unlock'      => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 7.5-2"/>',
            'shield'      => '<path d="M12 2 4 5v6c0 5 3.4 8.8 8 11 4.6-2.2 8-6 8-11V5l-8-3Z"/>',
            'shield-check'=> '<path d="M12 2 4 5v6c0 5 3.4 8.8 8 11 4.6-2.2 8-6 8-11V5l-8-3Z"/><path d="m9 12 2 2 4-4"/>',
            'cookie'      => '<path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5Z"/><path d="M8.5 10.5v.01M13.5 15.5v.01M8 15v.01M15.5 10v.01M11 19v.01M18 15v.01"/>',

            /* ── theme ── */
            'sun'         => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
            'moon'        => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/>',
            'play'        => '<path d="m6 4 14 8-14 8V4Z"/>',

            /* ── models / branding ── */
            'zap'         => '<path d="M13 2 4 14h6l-1 8 9-12h-6l1-8Z"/>',
            'sparkles'    => '<path d="M12 3l1.9 4.6L18.5 9.5l-4.6 1.9L12 16l-1.9-4.6L5.5 9.5l4.6-1.9L12 3Z"/><path d="M19 15l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8.8-2Z"/>',
            'crown'       => '<path d="M4 8l4 4 4-6 4 6 4-4v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8Z"/><path d="M4 20h16"/>',
            'ghost'       => '<path d="M5 11a7 7 0 0 1 14 0v10l-2.3-2-2.4 2-2.3-2-2.3 2-2.4-2L5 21V11Z"/><path d="M9.5 10v.01M14.5 10v.01"/>',
            'flame'       => '<path d="M12 2c1 4-4 5.5-4 10a4 4 0 0 0 8 0c0-1.5-.6-2.6-1.3-3.6C13.6 9.7 13 8 13.5 6 12.8 6.6 12 7 12 2Z"/><path d="M12 22a6.5 6.5 0 0 0 6.5-6.5c0-2-1-4-2.5-5.5"/>',
            'settings'    => '<path d="M4 6h9M17 6h3M4 12h3M11 12h9M4 18h9M17 18h3"/><circle cx="15" cy="6" r="2"/><circle cx="9" cy="12" r="2"/><circle cx="15" cy="18" r="2"/>',
            'lightbulb'   => '<path d="M9 18h6M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.4 1 2.3h6c0-.9.4-1.8 1-2.3A7 7 0 0 0 12 2Z"/>',
            'brain'       => '<path d="M12 4a3 3 0 0 0-3 3v10a3 3 0 0 0 3-3"/><path d="M12 4a3 3 0 0 1 3 3v10a3 3 0 0 1-3-3V4Z"/><path d="M9 7a3 3 0 0 0-3 3 2.5 2.5 0 0 0 0 5 3 3 0 0 0 3 2"/><path d="M15 7a3 3 0 0 1 3 3 2.5 2.5 0 0 1 0 5 3 3 0 0 1-3 2"/>',
            'server'      => '<rect x="3" y="4" width="18" height="7" rx="2"/><rect x="3" y="13" width="18" height="7" rx="2"/><path d="M7 7.5v.01M7 16.5v.01"/>',
            'users'       => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.4 3-5 6.5-5s6.5 1.6 6.5 5"/><path d="M16.5 4.6a3.5 3.5 0 0 1 0 6.8M17.5 15.2c2.4.5 4 1.9 4 4.8"/>',
            'chat-group'  => '<path d="M21 15a2 2 0 0 1-2 2H8l-4 4V5a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2Z"/><path d="M8 9h8M8 12.5h5"/>',
            'layers'      => '<path d="m12 2 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5M3 17l9 5 9-5"/>',
            'gauge'       => '<path d="M4 14a8 8 0 1 1 16 0"/><path d="m12 14 3.5-3.5"/><path d="M2 20h20"/>',
        ];
    }

    $path = $I[$name] ?? $I['sparkles'];
    $class = ($cls !== '') ? ' class="' . htmlspecialchars($cls, ENT_QUOTES) . '"' : '';
    return '<svg' . $class . ' width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24"'
         . ' fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"'
         . ' stroke-linejoin="round" aria-hidden="true" focusable="false">' . $path . '</svg>';
}

/* Small helper: SVG icon wrapped in a round icon-badge */
function icon_badge(string $name, int $size = 18, string $cls = 'ibadge'): string
{
    return '<span class="' . htmlspecialchars($cls, ENT_QUOTES) . '">' . icon($name, $size) . '</span>';
}
