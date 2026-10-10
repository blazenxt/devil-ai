<?php
/**
 * Extra Agent Mode tools (sandbox):
 *   edit_file        — change part of a file with SEARCH/REPLACE blocks (no full rewrite)
 *   image_search     — find real photos on the web (openly licensed) and save them in the workspace
 *   generate_speech  — text → spoken audio (one voice or a two-voice dialogue), saved as .mp3/.wav
 *   present_file     — open a finished file in the user's viewer
 *   stop_server      — stop whatever listens on a port
 *   fetch_url (PDF)  — PDFs are saved in the workspace and their text is extracted
 */

/* ───────────────────────── edit_file ───────────────────────── */

/** parse SEARCH/REPLACE blocks → [[search, replace], …] */
function xt_parse_edit_blocks(string $body): array {
    $blocks = [];
    if (preg_match_all('/^[ \t]*<{5,9}[ \t]*SEARCH[ \t]*\r?\n(.*?)^[ \t]*={5,9}[ \t]*\r?\n(.*?)^[ \t]*>{5,9}[ \t]*REPLACE[ \t]*$/ms', $body, $m, PREG_SET_ORDER)) {
        foreach ($m as $b) {
            $s = preg_replace('/\r?\n$/', '', $b[1]);
            $r = preg_replace('/\r?\n$/', '', $b[2]);
            /* an empty REPLACE section ends right after the ===== line */
            $blocks[] = [(string)$s, (string)$r];
        }
    }
    return $blocks;
}

/** find $search in $text: exact first, then line-by-line ignoring indentation / trailing spaces.
    Returns [start, length, count] — count 0 = not found, >1 = ambiguous. */
function xt_find_block(string $text, string $search): array {
    $n = substr_count($text, $search);
    if ($n === 1) { return [strpos($text, $search), strlen($search), 1]; }
    if ($n > 1) { return [-1, 0, $n]; }
    $tl = preg_split('/(?<=\n)/', $text);
    $sl = array_values(array_filter(preg_split('/\r?\n/', $search), static function ($l) { return true; }));
    while ($sl && trim((string)end($sl)) === '') { array_pop($sl); }
    while ($sl && trim((string)$sl[0]) === '') { array_shift($sl); }
    if (!$sl) { return [-1, 0, 0]; }
    $norm = static function (string $l): string { return preg_replace('/\s+/', ' ', trim($l)); };
    $want = array_map($norm, $sl);
    $k = count($want); $hits = [];
    for ($i = 0; $i + $k <= count($tl); $i++) {
        $ok = true;
        for ($j = 0; $j < $k; $j++) { if ($norm((string)$tl[$i + $j]) !== $want[$j]) { $ok = false; break; } }
        if ($ok) { $hits[] = $i; if (count($hits) > 1) { break; } }
    }
    if (count($hits) !== 1) { return [-1, 0, count($hits)]; }
    $start = 0; for ($i = 0; $i < $hits[0]; $i++) { $start += strlen((string)$tl[$i]); }
    $len = 0; for ($j = 0; $j < $k; $j++) { $len += strlen((string)$tl[$hits[0] + $j]); }
    /* keep the final newline of the matched range outside the replacement */
    $chunk = substr($text, $start, $len);
    if (substr($chunk, -1) === "\n") { $len--; if (substr($chunk, -2, 1) === "\r") { $len--; } }
    return [$start, $len, 1];
}

/** the line in $text that looks most like $line (for a helpful "not found" message) */
function xt_closest_line(string $text, string $line): string {
    $line = trim($line); if ($line === '') { return ''; }
    $best = ''; $bestP = 0.0; $no = 0; $bestNo = 0;
    foreach (preg_split('/\r?\n/', $text) as $l) {
        $no++;
        $t = trim($l); if ($t === '' || abs(strlen($t) - strlen($line)) > max(20, strlen($line))) { continue; }
        similar_text($t, $line, $p);
        if ($p > $bestP) { $bestP = $p; $best = $t; $bestNo = $no; }
    }
    return $bestP >= 55 ? 'line ' . $bestNo . ': ' . mb_substr($best, 0, 200) : '';
}

function xt_tool_edit_file(array $cfg, string $sid, string $input, bool $cut = false): array {
    $lines = preg_split('/\r?\n/', ltrim($input, "\r\n"), 2);
    $path = sbx_rel((string)($lines[0] ?? ''));
    $body = (string)($lines[1] ?? '');
    $help = "edit_file format:\nline 1 = file path, then one or more blocks:\n<<<<<<< SEARCH\nexact lines that are in the file now\n=======\nthe new lines\n>>>>>>> REPLACE";
    if ($path === '.' || $path === '') { return ['ok' => false, 'text' => "edit_file: the first line must be the file path.\n\n" . $help]; }
    $blocks = xt_parse_edit_blocks($body);
    if (!$blocks) { return ['ok' => false, 'text' => "edit_file: no SEARCH/REPLACE block found.\n\n" . $help]; }
    $old = sbx_read($cfg, $sid, $path);
    if (empty($old['ok'])) { return ['ok' => false, 'text' => 'Could not read ' . $path . ': ' . (string)($old['error'] ?? 'not found') . ' — create it with write_file first.']; }
    $text = (string)$old['data'];
    $applied = 0; $errs = [];
    foreach ($blocks as $i => $b) {
        [$s, $r] = $b;
        if (trim($s) === '') { $errs[] = 'block ' . ($i + 1) . ': the SEARCH part is empty (use write_file / append_file to add a new file or text at the end)'; continue; }
        [$pos, $len, $cnt] = xt_find_block($text, $s);
        if ($cnt === 1) { $text = substr($text, 0, $pos) . $r . substr($text, $pos + $len); $applied++; continue; }
        if ($cnt > 1) { $errs[] = 'block ' . ($i + 1) . ": the SEARCH text appears {$cnt} times — add a few more surrounding lines so it matches only one place"; continue; }
        $first = '';
        foreach (preg_split('/\r?\n/', $s) as $l) { if (trim($l) !== '') { $first = $l; break; } }
        $near = xt_closest_line($text, $first);
        $errs[] = 'block ' . ($i + 1) . ': the SEARCH text was not found in ' . $path . ($near !== '' ? ' (closest: ' . $near . ')' : '') . ' — read the file again (read_file or bash: grep -n) and copy the lines exactly';
    }
    if ($applied === 0) { return ['ok' => false, 'text' => 'No change made. ' . implode("\n", $errs)]; }
    $w = sbx_write($cfg, $sid, $path, $text);
    if (empty($w['ok'])) { return ['ok' => false, 'text' => 'Could not save ' . $path . ': ' . (string)($w['error'] ?? '')]; }
    $t = 'Edited ' . $path . ': ' . $applied . ' of ' . count($blocks) . ' change' . (count($blocks) === 1 ? '' : 's') . ' applied (now ' . substr_count($text, "\n") . ' lines).';
    if ($errs) { $t .= "\n⚠ Not applied:\n" . implode("\n", $errs); }
    if (function_exists('sbx_file_check')) { $t .= sbx_file_check($path, $text, $cut); }
    return ['ok' => !$errs, 'text' => $t, 'meta' => ['path' => $path, 'changes' => $applied]];
}

/* ───────────────────────── image_search ───────────────────────── */

function xt_http(string $url, int $timeout = 12, array $headers = []): array {
    if (!function_exists('curl_init')) { return [0, '', '']; }
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4, CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_USERAGENT => 'DevilAI/1.0 (+https://ai.devil.blazenxt.com)', CURLOPT_HTTPHEADER => $headers, CURLOPT_ENCODING => '',
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS]);
    $b = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $ct = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE); curl_close($ch);
    return [$code, is_string($b) ? $b : '', $ct];
}

/** candidates from openly licensed image libraries: [{url, page, title, creator, license, w, h}] */
function xt_image_candidates(string $q, int $want): array {
    $out = [];
    [$c, $b] = xt_http('https://api.openverse.org/v1/images/?page_size=' . min(20, $want * 4) . '&mature=false&q=' . rawurlencode($q), 10, ['Accept: application/json']);
    $j = $c === 200 ? json_decode($b, true) : null;
    foreach ((array)($j['results'] ?? []) as $r) {
        if (!is_array($r) || empty($r['url'])) { continue; }
        if ((int)($r['width'] ?? 0) && (int)$r['width'] < 500) { continue; }
        $out[] = ['url' => (string)$r['url'], 'page' => (string)($r['foreign_landing_url'] ?? ''), 'title' => (string)($r['title'] ?? ''), 'creator' => (string)($r['creator'] ?? ''),
            'license' => strtoupper((string)($r['license'] ?? '')) . (isset($r['license_version']) ? ' ' . $r['license_version'] : ''), 'w' => (int)($r['width'] ?? 0), 'h' => (int)($r['height'] ?? 0)];
    }
    if (count($out) < $want * 2) {
        [$c, $b] = xt_http('https://commons.wikimedia.org/w/api.php?action=query&format=json&generator=search&gsrnamespace=6&gsrlimit=' . min(20, $want * 4)
            . '&prop=imageinfo&iiprop=url|size|mime|extmetadata&iiurlwidth=1600&gsrsearch=' . rawurlencode($q . ' filetype:bitmap'), 10);
        $j = $c === 200 ? json_decode($b, true) : null;
        $pages = (array)($j['query']['pages'] ?? []);
        uasort($pages, static function ($a, $b) { return ((int)($a['index'] ?? 0)) <=> ((int)($b['index'] ?? 0)); });
        foreach ($pages as $p) {
            $ii = $p['imageinfo'][0] ?? null;
            if (!is_array($ii) || !preg_match('#^image/(jpeg|png|webp)#', (string)($ii['mime'] ?? ''))) { continue; }
            if ((int)($ii['width'] ?? 0) < 500) { continue; }
            $em = (array)($ii['extmetadata'] ?? []);
            $out[] = ['url' => (string)($ii['thumburl'] ?? $ii['url']), 'page' => (string)($ii['descriptionurl'] ?? ''), 'title' => preg_replace('/^File:|\.\w+$/', '', (string)($p['title'] ?? '')),
                'creator' => trim(strip_tags((string)($em['Artist']['value'] ?? ''))), 'license' => (string)($em['LicenseShortName']['value'] ?? ''), 'w' => (int)($ii['thumbwidth'] ?? $ii['width'] ?? 0), 'h' => (int)($ii['thumbheight'] ?? $ii['height'] ?? 0)];
        }
    }
    return $out;
}

function xt_is_image(string $b): string {
    if (strncmp($b, "\xFF\xD8\xFF", 3) === 0) { return 'jpg'; }
    if (strncmp($b, "\x89PNG", 4) === 0) { return 'png'; }
    if (strncmp($b, 'RIFF', 4) === 0 && substr($b, 8, 4) === 'WEBP') { return 'webp'; }
    return '';
}

function xt_tool_image_search(array $cfg, string $sid, string $input): array {
    $lines = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', trim($input))), 'strlen'));
    $q = (string)array_shift($lines);
    $count = 3; $folder = 'images';
    foreach ($lines as $l) {
        if (preg_match('/^count\s*[:=]\s*(\d+)/i', $l, $m)) { $count = (int)$m[1]; }
        elseif (preg_match('/^(?:folder|dir|path)\s*[:=]\s*(\S+)/i', $l, $m)) { $folder = trim(sbx_rel($m[1]), '/'); }
    }
    $count = max(1, min(6, $count));
    if ($folder === '' || $folder === '.') { $folder = 'images'; }
    if (mb_strlen($q) < 2) { return ['ok' => false, 'text' => 'image_search: line 1 must be what to look for, e.g. "masala chai cup".']; }
    $cands = xt_image_candidates($q, $count);
    if (!$cands) { return ['ok' => false, 'text' => 'No openly licensed photos found for "' . $q . '". Try simpler English words, or make one with generate_image.']; }
    /* download in parallel, keep the first $count good ones */
    $cands = array_slice($cands, 0, min(count($cands), $count * 3));
    $mh = curl_multi_init(); $hs = [];
    foreach ($cands as $k => $cd) {
        if (function_exists('agent_url_safe') && !agent_url_safe($cd['url'])) { continue; }
        $ch = curl_init($cd['url']);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_USERAGENT => 'DevilAI/1.0 (+https://ai.devil.blazenxt.com)', CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS]);
        curl_multi_add_handle($mh, $ch); $hs[$k] = $ch;
    }
    do { $st = curl_multi_exec($mh, $run); if ($run) { curl_multi_select($mh, 1.0); } } while ($run && $st === CURLM_OK);
    $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($q)), '-') ?: 'image';
    $slug = substr($slug, 0, 40);
    $saved = []; $n = 0;
    foreach ($hs as $k => $ch) {
        $b = (string)curl_multi_getcontent($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch); curl_close($ch);
        if (count($saved) >= $count || $code !== 200 || strlen($b) < 5000) { continue; }
        $ext = xt_is_image($b); if ($ext === '') { continue; }
        $n++;
        $path = $folder . '/' . $slug . '-' . $n . '.' . ($ext === 'png' ? 'png' : ($ext === 'webp' ? 'webp' : 'jpg'));
        $asked = $path;
        if (function_exists('sbx_compress_image')) { $b = sbx_compress_image($b, $path); $path = $asked; }
        $w = sbx_write($cfg, $sid, $path, $b);
        if (empty($w['ok'])) { $n--; continue; }
        $saved[] = $cands[$k] + ['path' => $path, 'kb' => (int)round(strlen($b) / 1024)];
    }
    curl_multi_close($mh);
    /* big photos → shrink inside the sandbox (Pillow) so pages stay fast */
    $big = array_values(array_filter($saved, static function ($s) { return $s['kb'] > 350; }));
    if ($big) {
        $py = 'import sys,os' . "\n" . 'from PIL import Image' . "\n" . 'for f in sys.argv[1:]:' . "\n" . '  try:' . "\n" . '    i=Image.open(f); i.thumbnail((1600,1600))' . "\n"
            . '    i=i.convert("RGB") if f.lower().endswith((".jpg",".jpeg")) else i' . "\n" . '    i.save(f, quality=82, optimize=True); print("S",f,os.path.getsize(f))' . "\n" . '  except Exception as e: pass';
        $c = sbx_exec($cfg, $sid, 'cd ' . escapeshellarg(SBX_WORKDIR) . ' && python3 -c ' . escapeshellarg($py) . ' ' . implode(' ', array_map(static function ($s) { return escapeshellarg($s['path']); }, $big)), 40);
        if (preg_match_all('/^S (\S+) (\d+)$/m', (string)($c['stdout'] ?? ''), $cm, PREG_SET_ORDER)) {
            foreach ($cm as $x) { foreach ($saved as &$sv) { if ($sv['path'] === $x[1]) { $sv['kb'] = (int)round((int)$x[2] / 1024); } } unset($sv); }
        }
    }
    if (!$saved) { return ['ok' => false, 'text' => 'Found photos for "' . $q . '" but could not download them. Try other words, or use generate_image.']; }
    $rows = [];
    foreach ($saved as $s) {
        $rows[] = '- ' . $s['path'] . ' (' . ($s['w'] && $s['h'] ? $s['w'] . '×' . $s['h'] . ', ' : '') . $s['kb'] . ' KB) — "' . mb_substr($s['title'], 0, 80) . '"'
            . ($s['creator'] !== '' ? ' by ' . mb_substr($s['creator'], 0, 60) : '') . ($s['license'] !== '' ? ', license ' . $s['license'] : '') . ($s['page'] !== '' ? ', source ' . $s['page'] : '');
    }
    return ['ok' => true, 'text' => 'Saved ' . count($saved) . ' photo(s) for "' . $q . '":' . "\n" . implode("\n", $rows)
        . "\nUse them with relative paths (e.g. " . $saved[0]['path'] . '). Look at them (browser tool or open the file) before relying on what they show; credit the creator where the license asks for it (CC BY / BY-SA).',
        'meta' => ['images' => array_map(static function ($s) { return $s['path']; }, $saved), 'image' => $saved[0]['path']]];
}

/* ───────────────────────── generate_speech ───────────────────────── */

function xt_tts_voices(): array {
    return ['Zephyr' => 'bright', 'Puck' => 'upbeat', 'Charon' => 'informative', 'Kore' => 'firm', 'Fenrir' => 'excitable', 'Leda' => 'youthful', 'Orus' => 'firm',
        'Aoede' => 'breezy', 'Callirrhoe' => 'easy-going', 'Autonoe' => 'bright', 'Enceladus' => 'breathy', 'Iapetus' => 'clear', 'Umbriel' => 'easy-going',
        'Algieba' => 'smooth', 'Despina' => 'smooth', 'Erinome' => 'clear', 'Algenib' => 'gravelly', 'Rasalgethi' => 'informative', 'Laomedeia' => 'upbeat',
        'Achernar' => 'soft', 'Alnilam' => 'firm', 'Schedar' => 'even', 'Gacrux' => 'mature', 'Pulcherrima' => 'forward', 'Achird' => 'friendly',
        'Zubenelgenubi' => 'casual', 'Vindemiatrix' => 'gentle', 'Sadachbia' => 'lively', 'Sadaltager' => 'knowledgeable', 'Sulafat' => 'warm'];
}
function xt_voice_name(string $v, string $fallback = 'Kore'): string {
    foreach (array_keys(xt_tts_voices()) as $n) { if (strcasecmp($n, trim($v)) === 0) { return $n; } }
    return $fallback;
}

/** 16-bit mono PCM → WAV file bytes */
function xt_pcm_to_wav(string $pcm, int $rate = 24000): string {
    $len = strlen($pcm);
    return 'RIFF' . pack('V', 36 + $len) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, $rate, $rate * 2, 2, 16) . 'data' . pack('V', $len) . $pcm;
}

/** Gemini TTS → ['ok', 'wav', 'model'].  $lines = [[speaker, text], …] for a dialogue (newer models want one part per speaker line) */
function xt_tts(array $cfg, string $text, array $voice, array $lines = []): array {
    $key = function_exists('gemini_api_key') ? gemini_api_key($cfg) : '';
    if ($key === '') { return ['ok' => false, 'error' => 'speech is not configured on this site']; }
    $speech = isset($voice['multi'])
        ? ['multiSpeakerVoiceConfig' => ['speakerVoiceConfigs' => array_map(static function ($sp, $vn) { return ['speaker' => $sp, 'voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => $vn]]]; }, array_keys($voice['multi']), $voice['multi'])]]
        : ['voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => (string)($voice['one'] ?? 'Kore')]]];
    $gen = ['responseModalities' => ['AUDIO'], 'speechConfig' => $speech];
    $bodyOld = json_encode(['contents' => [['parts' => [['text' => $text]]]], 'generationConfig' => $gen], JSON_UNESCAPED_UNICODE);
    $bodyNew = $bodyOld;
    if (isset($voice['multi']) && $lines) {
        $parts = array_map(static function ($l) { return ['text' => $l[1], 'speechMetadata' => ['speaker' => $l[0]]]; }, $lines);
        $bodyNew = json_encode(['contents' => [['parts' => $parts]], 'generationConfig' => $gen], JSON_UNESCAPED_UNICODE);
    }
    $last = 'speech service busy';
    foreach (['gemini-3.8-flash-tts', 'gemini-3.8-flash-lite-tts', 'gemini-3.1-flash-tts-preview', 'gemini-2.5-flash-preview-tts'] as $model) {
        $body = strpos($model, 'gemini-3.8') === 0 ? $bodyNew : $bodyOld;
        $tl = function_exists('devil_time_left') ? devil_time_left(85) - 4 : 60;
        if ($tl < 10) { $last = 'out of time'; break; }
        $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent');
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => min(70, $tl), CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8', 'x-goog-api-key: ' . $key]]);
        $raw = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $j = is_string($raw) ? json_decode($raw, true) : null;
        $part = null;
        foreach ((array)($j['candidates'][0]['content']['parts'] ?? []) as $p) { if (isset($p['inlineData']['data'])) { $part = $p['inlineData']; break; } }
        if ($code !== 200 || !$part) { $last = $code === 429 ? 'daily speech limit reached' : ($code === 400 ? 'the text could not be spoken (' . mb_substr((string)($j['error']['message'] ?? ''), 0, 160) . ')' : 'speech service busy'); continue; }
        $audio = base64_decode((string)$part['data']);
        $mime = strtolower((string)($part['mimeType'] ?? ''));
        if (strncmp($audio, 'RIFF', 4) !== 0) { $rate = preg_match('/rate=(\d+)/', $mime, $m) ? (int)$m[1] : 24000; $audio = xt_pcm_to_wav($audio, $rate); }
        return ['ok' => true, 'wav' => $audio, 'model' => $model];
    }
    return ['ok' => false, 'error' => $last];
}

function xt_tool_speech(array $cfg, string $sid, string $input): array {
    $lines = preg_split('/\r?\n/', trim($input));
    $path = sbx_rel((string)array_shift($lines));
    if (!preg_match('/\.(mp3|wav)$/i', $path)) {
        array_unshift($lines, $path === '.' ? '' : $path);
        $path = 'audio/speech-' . date('His') . '.mp3';
    }
    $voice = ['one' => 'Kore']; $style = '';
    while ($lines && preg_match('/^\s*(voice|voices|style)\s*:\s*(.+)$/i', (string)$lines[0], $m)) {
        array_shift($lines);
        $k = strtolower($m[1]);
        if ($k === 'style') { $style = trim($m[2]); continue; }
        if ($k === 'voices' || strpos($m[2], '=') !== false) {
            $multi = [];
            foreach (preg_split('/[,;]/', $m[2]) as $pair) {
                if (preg_match('/^\s*([^=]+?)\s*=\s*(\w+)\s*$/', $pair, $pm)) { $multi[trim($pm[1])] = xt_voice_name($pm[2], count($multi) ? 'Puck' : 'Kore'); }
            }
            if (count($multi) >= 2) { $voice = ['multi' => array_slice($multi, 0, 2, true)]; }
        } else {
            $voice = ['one' => xt_voice_name($m[2])];
        }
    }
    $text = trim(implode("\n", $lines));
    if ($text === '') {
        return ['ok' => false, 'text' => "generate_speech: nothing to say.\nFormat: line 1 = output file (audio/intro.mp3), optional \"voice: Kore\" (or \"voices: Host=Kore, Guest=Puck\" for a dialogue whose lines start with \"Host:\" / \"Guest:\"), optional \"style: warm and cheerful\", then the text."];
    }
    if (mb_strlen($text) > 4500) { return ['ok' => false, 'text' => 'generate_speech: the text is ' . mb_strlen($text) . ' characters — keep each call under ~4,000 characters. Make several parts (part1.mp3, part2.mp3…) and join them with: ffmpeg -i "concat:part1.mp3|part2.mp3" -c copy full.mp3']; }
    $dialog = [];
    if (isset($voice['multi'])) {
        $names = array_keys($voice['multi']);
        foreach (preg_split('/\r?\n/', $text) as $l) {
            if (trim($l) === '') { continue; }
            $hit = '';
            foreach ($names as $nm) { if (stripos(ltrim($l), $nm . ':') === 0) { $hit = $nm; break; } }
            if ($hit !== '') { $dialog[] = [$hit, trim(substr(ltrim($l), strlen($hit) + 1))]; }
            elseif ($dialog) { $dialog[count($dialog) - 1][1] .= ' ' . trim($l); }
            else { $dialog[] = [$names[0], trim($l)]; }
        }
        $intro = 'TTS the following conversation between ' . implode(' and ', $names) . ($style !== '' ? ' (' . $style . ')' : '') . ":\n";
        $say = $intro . $text;
    } else {
        $say = $style !== '' ? 'Say in a ' . $style . ' way: ' . $text : $text;
    }
    $r = xt_tts($cfg, $say, $voice, $dialog);
    if (empty($r['ok'])) { return ['ok' => false, 'text' => 'Speech generation failed: ' . (string)$r['error'] . '. Try again later or shorten the text.']; }
    $wav = (string)$r['wav'];
    $secs = max(1, (int)round((strlen($wav) - 44) / 48000));
    if (preg_match('/\.wav$/i', $path)) {
        $w = sbx_write($cfg, $sid, $path, $wav);
        if (empty($w['ok'])) { return ['ok' => false, 'text' => 'Could not save the audio: ' . (string)($w['error'] ?? '')]; }
    } else {
        $tmp = '.devil-tts-' . bin2hex(random_bytes(3)) . '.wav';
        $w = sbx_write($cfg, $sid, $tmp, $wav);
        if (empty($w['ok'])) { return ['ok' => false, 'text' => 'Could not save the audio: ' . (string)($w['error'] ?? '')]; }
        $P = escapeshellarg($path); $T = escapeshellarg($tmp);
        $c = sbx_exec($cfg, $sid, 'mkdir -p "$(dirname ' . $P . ')" && ffmpeg -y -loglevel error -i ' . $T . ' -codec:a libmp3lame -b:a 128k ' . $P . ' && rm -f ' . $T . ' && stat -c %s ' . $P, 40);
        if ((int)($c['exit_code'] ?? 1) !== 0) {
            $path = preg_replace('/\.mp3$/i', '.wav', $path);
            sbx_exec($cfg, $sid, 'mkdir -p "$(dirname ' . escapeshellarg($path) . ')" && mv -f ' . $T . ' ' . escapeshellarg($path), 15);
        }
    }
    $vtxt = isset($voice['multi']) ? implode(', ', array_map(static function ($s, $v) { return $s . '=' . $v; }, array_keys($voice['multi']), $voice['multi'])) : $voice['one'];
    return ['ok' => true, 'text' => 'Audio saved to ' . $path . ' (about ' . $secs . ' s, voice ' . $vtxt . '). The user can play it in the Files panel; use present_file to open it for them.', 'meta' => ['audio' => $path]];
}

/* ───────────────────────── present_file / stop_server ───────────────────────── */

function xt_tool_present(array $cfg, string $sid, string $input): array {
    $path = sbx_rel((string)strtok(trim($input), "\n"));
    if ($path === '.' || $path === '') { return ['ok' => false, 'text' => 'present_file: give the file path.']; }
    $r = sbx_exec($cfg, $sid, 'cd ' . escapeshellarg(SBX_WORKDIR) . ' && [ -f ' . escapeshellarg($path) . ' ] && stat -c %s ' . escapeshellarg($path), 15);
    $size = trim((string)($r['stdout'] ?? ''));
    if ((int)($r['exit_code'] ?? 1) !== 0 || $size === '') { return ['ok' => false, 'text' => 'present_file: ' . $path . ' does not exist. Check the path with list_files.']; }
    return ['ok' => true, 'text' => 'Opened ' . $path . ' (' . (int)round((int)$size / 1024) . ' KB) in the user\'s viewer.', 'meta' => ['present' => $path]];
}

function xt_tool_stop_server(array $cfg, string $sid, string $input): array {
    $port = (int)preg_replace('/\D/', '', (string)strtok(trim($input), "\n"));
    if ($port < 1024 || $port > 65535) { return ['ok' => false, 'text' => 'stop_server: give the port number (1024-65535).']; }
    $r = sbx_exec($cfg, $sid, '(fuser -k ' . $port . '/tcp || (command -v lsof >/dev/null && lsof -ti tcp:' . $port . ' | xargs -r kill)) >/dev/null 2>&1; sleep 1; (fuser ' . $port . '/tcp >/dev/null 2>&1 && echo STILL) || echo STOPPED', 20);
    $out = (string)($r['stdout'] ?? '');
    return strpos($out, 'STOPPED') !== false ? ['ok' => true, 'text' => "Stopped the server on port {$port}."] : ['ok' => false, 'text' => "Something is still listening on port {$port}. Try: bash  pkill -f <command name>"];
}

/* ───────────────────────── fetch_url: PDF + long pages ───────────────────────── */

/** split "URL\npart: N" → [url, part] */
function xt_fetch_args(string $input): array {
    $lines = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', trim($input))), 'strlen'));
    $url = (string)($lines[0] ?? ''); $part = 1;
    if (preg_match('/^(\S+)\s+(?:part|page|chunk)\s*[:=]?\s*(\d+)$/i', $url, $m)) { $url = $m[1]; $part = (int)$m[2]; }
    foreach (array_slice($lines, 1) as $l) { if (preg_match('/^(?:part|page|chunk)\s*[:=]?\s*(\d+)/i', $l, $m)) { $part = (int)$m[1]; } }
    return [$url, max(1, $part)];
}

/** cut long text into parts of ~12k characters */
function xt_text_part(string $text, int $part, int $size = 12000): array {
    $total = max(1, (int)ceil(mb_strlen($text) / $size));
    $part = min($part, $total);
    $chunk = mb_substr($text, ($part - 1) * $size, $size);
    $note = $total > 1 ? "\n\n[part {$part} of {$total}" . ($part < $total ? ' — for the next part call fetch_url again with a second line: part: ' . ($part + 1) : '') . ']' : '';
    return [$chunk . $note, $total];
}

/** a PDF that fetch_url downloaded: save it in the workspace and pull its text out with pypdf */
function xt_pdf_text(array $cfg, string $sid, string $url, string $pdf, int $part): array {
    $name = basename((string)parse_url($url, PHP_URL_PATH)) ?: 'document.pdf';
    $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name);
    if (!preg_match('/\.pdf$/i', $name)) { $name .= '.pdf'; }
    $path = 'downloads/' . substr($name, -80);
    $w = sbx_write($cfg, $sid, $path, $pdf);
    if (empty($w['ok'])) { return ['ok' => false, 'text' => 'The URL is a PDF but it could not be saved in the workspace.']; }
    $py = 'import sys' . "\n" . 'try:' . "\n" . '  from pypdf import PdfReader' . "\n" . 'except Exception:' . "\n" . '  import subprocess; subprocess.run([sys.executable,"-m","pip","install","-q","pypdf"],capture_output=True); from pypdf import PdfReader' . "\n"
        . 'r=PdfReader(sys.argv[1]); print("PAGES",len(r.pages))' . "\n" . 'for i,p in enumerate(r.pages[:60]):' . "\n" . '  print(f"\n--- page {i+1} ---"); print((p.extract_text() or "").strip())';
    $c = sbx_exec($cfg, $sid, 'cd ' . escapeshellarg(SBX_WORKDIR) . ' && python3 -c ' . escapeshellarg($py) . ' ' . escapeshellarg($path) . ' 2>&1 | head -c 400000', 60);
    $out = (string)($c['stdout'] ?? '');
    $pages = preg_match('/^PAGES (\d+)/', $out, $m) ? (int)$m[1] : 0;
    $text = trim((string)preg_replace('/^PAGES \d+\s*/', '', $out));
    if ($pages === 0 || trim(preg_replace('/--- page \d+ ---/', '', $text)) === '') {
        return ['ok' => true, 'text' => "URL: {$url}\nThis PDF was saved to {$path}, but it has no text layer (probably scanned). Work with the file in bash (e.g. convert pages to images) if needed."];
    }
    [$chunk] = xt_text_part($text, $part);
    return ['ok' => true, 'text' => "URL: {$url}\nPDF saved to {$path} ({$pages} pages).\n\n" . $chunk];
}
