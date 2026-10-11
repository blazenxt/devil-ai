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
            'arrow-left'  => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
            'arrow-down'  => '<path d="M12 5v14M19 12l-7 7-7-7"/>',
            'swords'      => '<path d="M14.5 17.5 3 6V3h3l11.5 11.5"/><path d="m13 19 6-6"/><path d="m16 16 4 4"/><path d="m19 21 2-2"/><path d="M14.5 6.5 18 3h3v3l-3.5 3.5"/><path d="m5 14 4 4"/><path d="m7 17-3 3"/><path d="m3 19 2 2"/>',
            'columns'     => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M12 4v16"/>',
            'trophy'      => '<path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0V4Z"/><path d="M17 5h3v2a3 3 0 0 1-3 3M7 5H4v2a3 3 0 0 0 3 3"/>',
            'maximize'    => '<path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/>',
            'shuffle'     => '<path d="M16 3h5v5M4 20 21 3M21 16v5h-5M15 15l6 6M4 4l5 5"/>',
            'equal'       => '<path d="M5 9h14M5 15h14"/>',
            'pin'         => '<path d="M12 17v5M9 10.76V6a1 1 0 0 0-1-1 2 2 0 0 1 0-4h8a2 2 0 0 1 0 4 1 1 0 0 0-1 1v4.76a2 2 0 0 0 1.11 1.79l1.78.9A2 2 0 0 1 19 15.24V17H5v-1.76a2 2 0 0 1 1.11-1.79l1.78-.9A2 2 0 0 0 9 10.76Z"/>',
            'keyboard'    => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M6 9h.01M10 9h.01M14 9h.01M18 9h.01M6 13h.01M18 13h.01M10 13h4M7 16h10"/>',
            'swap'        => '<path d="M8 3 4 7l4 4M4 7h16M16 21l4-4-4-4M20 17H4"/>',
            'fork'        => '<circle cx="6" cy="5" r="2"/><circle cx="18" cy="5" r="2"/><circle cx="12" cy="19" r="2"/><path d="M6 7v2a3 3 0 0 0 3 3h6a3 3 0 0 0 3-3V7M12 12v5"/>',
            'external'    => '<path d="M15 3h6v6M10 14 21 3"/><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/>',
            'share'       => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 10.5 15.4 6.5M8.6 13.5l6.8 4"/>',
            'download'    => '<path d="M12 3v12M7 10l5 5 5-5"/><path d="M5 21h14"/>',

            /* ── chat ── */
            'new-chat'    => '<path d="M12 5v14M5 12h14"/>',
            'plus'        => '<path d="M12 5v14M5 12h14"/>',
            'square-pen'  => '<path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.4 2.6a2 2 0 0 1 2.8 2.8L12 14.6 8 15.6l1-4Z"/>',
            'message'     => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z"/>',
            'message-circle'=> '<path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7A8.4 8.4 0 0 1 4 11.5a8.5 8.5 0 1 1 17 0Z"/>',
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
            'book'        => '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H19a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H6.5a1 1 0 0 1 0-5H20"/>',
            'fold'        => '<path d="M12 22v-6M12 8V2M4 12H2M10 12H8M16 12h-2M22 12h-2M15 19l-3-3-3 3M15 5l-3 3-3-3"/>',
            'git-branch'  => '<line x1="6" x2="6" y1="3" y2="15"/><circle cx="18" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M18 9a9 9 0 0 1-9 9"/>',
            'git-pr'      => '<circle cx="18" cy="18" r="3"/><circle cx="6" cy="6" r="3"/><path d="M13 6h3a2 2 0 0 1 2 2v7"/><line x1="6" x2="6" y1="9" y2="21"/>',
            'github'      => '<path d="M15 22v-4a4.8 4.8 0 0 0-1-3.5c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.4 5.4 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4"/><path d="M9 18c-4.51 2-5-2-7-2"/>',
            'mail'        => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6 10-6"/>',
            'key'         => '<circle cx="8" cy="15" r="4"/><path d="m10.8 12.2 8.7-8.7M15 4l3 3M18 7l2 2"/>',
            'eye'         => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
            'eye-off'     => '<path d="M2 12s3.5-7 10-7c2 0 3.8.6 5.3 1.5M22 12s-3.5 7-10 7c-2 0-3.8-.6-5.3-1.5"/><path d="m2 2 20 20"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',

            /* ── chat management ── */
            'trash'       => '<path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M10 11v6M14 11v6"/>',
            'pencil'      => '<path d="M17 3a2.8 2.8 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>',
            'search'      => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
            'globe'       => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 0 20M12 2a15.3 15.3 0 0 0 0 20"/>',

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
            'folder'      => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/>',
            'spark'       => '<path d="M12 3v5M12 16v5M3 12h5M16 12h5M5.6 5.6l3.5 3.5M14.9 14.9l3.5 3.5M5.6 18.4l3.5-3.5M14.9 9.1l3.5-3.5"/>',
            'calculator'  => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M8 7h8M8 11h.01M12 11h.01M16 11h.01M8 15h.01M12 15h.01M16 15h.01M8 18h.01M12 18h4"/>',
            'clock'       => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'link'        => '<path d="M10 14a4 4 0 0 0 5.66 0l3-3a4 4 0 0 0-5.66-5.66l-1 1"/><path d="M14 10a4 4 0 0 0-5.66 0l-3 3a4 4 0 0 0 5.66 5.66l1-1"/>',
            'terminal'    => '<path d="m4 17 6-5-6-5"/><path d="M12 19h8"/>',
            'monitor'     => '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>',
            'upload'      => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5M12 3v12"/>',
            'help-circle' => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3M12 17h.01"/>',
            'file-text'   => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
            'list'        => '<path d="M9 6h11M9 12h11M9 18h11M4 6h.01M4 12h.01M4 18h.01"/>',
            'brain'       => '<path d="M12 4a3 3 0 0 0-3 3v10a3 3 0 0 0 3-3"/><path d="M12 4a3 3 0 0 1 3 3v10a3 3 0 0 1-3-3V4Z"/><path d="M9 7a3 3 0 0 0-3 3 2.5 2.5 0 0 0 0 5 3 3 0 0 0 3 2"/><path d="M15 7a3 3 0 0 1 3 3 2.5 2.5 0 0 1 0 5 3 3 0 0 1-3 2"/>',
            'server'      => '<rect x="3" y="4" width="18" height="7" rx="2"/><rect x="3" y="13" width="18" height="7" rx="2"/><path d="M7 7.5v.01M7 16.5v.01"/>',
            'code'        => '<path d="m8 8-4 4 4 4M16 8l4 4-4 4M14 4l-4 16"/>',
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


/**
 * Real brand logos (fill-based, own viewBox each) — used where a service must be
 * recognisable at a glance (Connections panel). Unlike icon() these keep their
 * official colours; 'notion' is monochrome via currentColor so it works on both
 * themes. Returns '' for an unknown name so callers degrade gracefully.
 */
function icon_brand(string $name, int $size = 16, string $cls = ''): string
{
    static $B = null;
    if ($B === null) {
        $B = [
            /* ── real brand marks (fill-based, NOT the 24×24 stroke set above) ── */
            'drive' => ['vb' => '0 0 87.3 78', 'p' =>
                '<path fill="#0066da" d="m6.6 66.85 3.85 6.65c.8 1.4 1.95 2.5 3.3 3.3L27.5 53H0c0 1.55.4 3.1 1.2 4.5z"/> <path fill="#00ac47" d="M43.65 25 29.9 1.2c-1.35.8-2.5 1.9-3.3 3.3l-25.4 44A9.06 9.06 0 0 0 0 53h27.5z"/> <path fill="#ea4335" d="M73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l1.6-2.75L86.1 57.5c.8-1.4 1.2-2.95 1.2-4.5H59.798l5.852 11.5z"/> <path fill="#00832d" d="M43.65 25 57.4 1.2C56.05.4 54.5 0 52.9 0H34.4c-1.6 0-3.15.45-4.5 1.2z"/> <path fill="#2684fc" d="M59.8 53H27.5L13.75 76.8c1.35.8 2.9 1.2 4.5 1.2h50.8c1.6 0 3.15-.45 4.5-1.2z"/> <path fill="#ffba00" d="m73.4 26.5-12.7-22c-.8-1.4-1.95-2.5-3.3-3.3L43.65 25 59.8 53h27.45c0-1.55-.4-3.1-1.2-4.5z"/>'],
            'docs' => ['vb' => '0 0 24 24', 'p' =>
                '<path fill="#4285F4" d="M14.727 6.727H14V0H4.91c-.905 0-1.637.732-1.637 1.636v20.728c0 .904.732 1.636 1.636 1.636h14.182c.904 0 1.636-.732 1.636-1.636V6.727h-6zm-.545 10.455H7.09v-1.364h7.09v1.364zm2.727-3.273H7.091v-1.364h9.818v1.364zm0-3.273H7.091V9.273h9.818v1.363zM14.727 6h6l-6-6v6z"/>'],
            'notion' => ['vb' => '0 0 256 268', 'p' =>
                '<path fill="currentColor" d="M164.09.608 16.092 11.538C4.155 12.573 0 20.374 0 29.726v162.245c0 7.284 2.585 13.516 8.826 21.843l34.789 45.237c5.715 7.284 10.912 8.844 21.825 8.327l171.864-10.404c14.532-1.035 18.696-7.801 18.696-19.24V55.207c0-5.911-2.336-7.614-9.21-12.66l-1.185-.856L198.37 8.409C186.94.1 182.27-.952 164.09.608ZM69.327 52.22c-14.033.945-17.216 1.159-25.186-5.323L23.876 30.778c-2.06-2.086-1.026-4.69 4.163-5.207l142.274-10.395c11.947-1.043 18.17 3.12 22.842 6.758l24.401 17.68c1.043.525 3.638 3.637.517 3.637L71.146 52.095l-1.819.125Zm-16.36 183.954V81.222c0-6.767 2.077-9.887 8.3-10.413L230.02 60.93c5.724-.517 8.31 3.12 8.31 9.879v153.917c0 6.767-1.044 12.49-10.387 13.008l-161.487 9.361c-9.343.517-13.489-2.594-13.489-10.921ZM212.377 89.53c1.034 4.681 0 9.362-4.681 9.897l-7.783 1.542v114.404c-6.758 3.637-12.981 5.715-18.18 5.715-8.308 0-10.386-2.604-16.609-10.396l-50.898-80.079v77.476l16.1 3.646s0 9.362-12.989 9.362l-35.814 2.077c-1.043-2.086 0-7.284 3.63-8.318l9.351-2.595V109.823l-12.98-1.052c-1.044-4.68 1.55-11.439 8.826-11.965l38.426-2.585 52.958 81.113v-71.76l-13.498-1.552c-1.043-5.733 3.111-9.896 8.3-10.404l35.84-2.087Z"/>'],
        ];
    }
    $b = $B[$name] ?? null;
    if (!is_array($b)) { return ''; }
    return '<svg' . ($cls !== '' ? ' class="' . htmlspecialchars($cls, ENT_QUOTES) . '"' : '')
         . ' width="' . $size . '" height="' . $size . '" viewBox="' . $b['vb'] . '"'
         . ' xmlns="http://www.w3.org/2000/svg" fill="none" role="img" aria-hidden="true" focusable="false">'
         . $b['p'] . '</svg>';
}
