<?php
/* ══════════════════════════════════════════════════════════════════════════
   Extra Devil Agent tools that need no API key and no sandbox.

   Every call is server-side cURL against a free, keyless public API (or pure
   PHP), so these work in Agent Mode whether or not the sandbox is enabled.
   Keep each one small: fetch, shape, return a short readable string.

   ══════════════════════════════════════════════════════════════════════════ */
declare(strict_types=1);

if (!function_exists('plus_http_json')) {

/** GET a URL and decode JSON. ['ok'=>bool,'json'=>?array,'raw'=>string,'error'=>string] */
function plus_http_json(string $url, int $timeout = 12): array {
    if (!function_exists('curl_init')) { return ['ok' => false, 'json' => null, 'raw' => '', 'error' => 'no cURL on this server']; }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'DevilAI/1.0 (+https://ai.devil.blazenxt.com)',
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $raw = is_string($raw) ? $raw : '';
    if ($code < 200 || $code >= 300) {
        return ['ok' => false, 'json' => null, 'raw' => $raw, 'error' => 'the service returned HTTP ' . $code];
    }
    $j = json_decode($raw, true);
    return ['ok' => true, 'json' => is_array($j) ? $j : null, 'raw' => $raw, 'error' => ''];
}

/** first "key: value" line pulled out of a tool input, and the rest of the text */
function plus_kv(string $input, string $key): array {
    $val = ''; $rest = [];
    foreach (preg_split('/\r?\n/', $input) as $l) {
        if ($val === '' && preg_match('/^' . preg_quote($key, '/') . '\s*:\s*(.+)$/i', trim($l), $m)) { $val = trim($m[1]); continue; }
        $rest[] = $l;
    }
    return [$val, trim(implode("\n", $rest))];
}

/* ── weather (Open-Meteo, no key) ──────────────────────────────────────── */
function plus_weather(string $input): array {
    $place = trim($input);
    if ($place === '') { return ['ok' => false, 'text' => 'Name a city or place, e.g. "Asansol" or "London".']; }
    $geo = plus_http_json('https://geocoding-api.open-meteo.com/v1/search?' . http_build_query(
        ['name' => $place, 'count' => 1, 'language' => 'en', 'format' => 'json']), 10);
    if (empty($geo['ok']) || empty($geo['json']['results'][0])) {
        return ['ok' => false, 'text' => 'Could not find a place called "' . $place . '".'];
    }
    $g = $geo['json']['results'][0];
    $lat = (float)$g['latitude']; $lon = (float)$g['longitude'];
    $where = trim(($g['name'] ?? '') . (empty($g['admin1']) ? '' : ', ' . $g['admin1']) . (empty($g['country']) ? '' : ', ' . $g['country']));
    $f = plus_http_json('https://api.open-meteo.com/v1/forecast?' . http_build_query([
        'latitude' => $lat, 'longitude' => $lon,
        'current' => 'temperature_2m,apparent_temperature,relative_humidity_2m,wind_speed_10m,weather_code',
        'daily' => 'temperature_2m_max,temperature_2m_min,precipitation_probability_max',
        'timezone' => 'auto', 'forecast_days' => 3,
    ]), 12);
    if (empty($f['ok']) || !is_array($f['json'])) { return ['ok' => false, 'text' => 'The weather service did not answer (' . $f['error'] . ').']; }
    $j = $f['json'];
    $cur = (array)($j['current'] ?? []);
    $codes = [0 => 'clear sky', 1 => 'mainly clear', 2 => 'partly cloudy', 3 => 'overcast', 45 => 'fog', 48 => 'depositing rime fog',
        51 => 'light drizzle', 53 => 'moderate drizzle', 55 => 'dense drizzle', 61 => 'slight rain', 63 => 'moderate rain',
        65 => 'heavy rain', 71 => 'slight snow', 73 => 'moderate snow', 75 => 'heavy snow', 77 => 'snow grains',
        80 => 'rain showers', 81 => 'moderate rain showers', 82 => 'violent rain showers', 85 => 'snow showers',
        86 => 'heavy snow showers', 95 => 'thunderstorm', 96 => 'thunderstorm with hail', 99 => 'thunderstorm with heavy hail'];
    $wc = (int)($cur['weather_code'] ?? -1);
    $unit = (string)($j['current_units']['temperature_2m'] ?? '°C');
    $out = "Weather in {$where}:\n"
         . 'Now: ' . ($cur['temperature_2m'] ?? '?') . $unit . ' (' . ($codes[$wc] ?? 'conditions unknown') . ')'
         . ', feels like ' . ($cur['apparent_temperature'] ?? '?') . $unit
         . ', humidity ' . ($cur['relative_humidity_2m'] ?? '?') . '%'
         . ', wind ' . ($cur['wind_speed_10m'] ?? '?') . ' ' . (string)($j['current_units']['wind_speed_10m'] ?? 'km/h') . "\n";
    $d = (array)($j['daily'] ?? []);
    if (!empty($d['time'])) {
        $out .= "Next 3 days:\n";
        foreach ((array)$d['time'] as $i => $day) {
            $out .= '  ' . $day . ': ' . ($d['temperature_2m_min'][$i] ?? '?') . '–' . ($d['temperature_2m_max'][$i] ?? '?')
                  . $unit . ', rain ' . ($d['precipitation_probability_max'][$i] ?? '?') . "%\n";
        }
    }
    return ['ok' => true, 'text' => rtrim($out)];
}

/* ── crypto price (CoinGecko, no key) ──────────────────────────────────── */
function plus_crypto(string $input): array {
    $ids = strtolower(preg_replace('/\s+/', ',', trim($input)));
    $ids = preg_replace('/[^a-z0-9,_-]/', '', (string)$ids);
    $ids = trim((string)$ids, ',');
    if ($ids === '') { return ['ok' => false, 'text' => 'Name a coin, e.g. "bitcoin" or "bitcoin, ethereum".']; }
    [$vs, ] = plus_kv($input, 'vs');
    $vs = strtolower(preg_replace('/[^a-z,]/', '', $vs ?: 'usd,inr')) ?: 'usd,inr';
    $r = plus_http_json('https://api.coingecko.com/api/v3/simple/price?' . http_build_query([
        'ids' => $ids, 'vs_currencies' => $vs, 'include_24hr_change' => 'true',
    ]), 12);
    if (empty($r['ok']) || !is_array($r['json']) || !$r['json']) {
        return ['ok' => false, 'text' => 'No prices found for "' . $ids . '". CoinGecko uses ids like bitcoin, ethereum, solana. (' . $r['error'] . ')'];
    }
    $curs = array_values(array_filter(array_map('trim', explode(',', $vs))));
    $out = '';
    foreach ($r['json'] as $coin => $data) {
        $bits = [];
        foreach ($curs as $c) {
            if (!isset($data[$c])) { continue; }
            $chg = isset($data[$c . '_24h_change']) ? sprintf(' (%+.2f%% 24h)', (float)$data[$c . '_24h_change']) : '';
            $bits[] = number_format((float)$data[$c], 2) . ' ' . strtoupper($c) . $chg;
        }
        $out .= ($bits ? ucfirst((string)$coin) . ': ' . implode(' · ', $bits) : ucfirst((string)$coin) . ': no data') . "\n";
    }
    return ['ok' => true, 'text' => rtrim($out)];
}

/* ── currency convert (Frankfurter / ECB, no key) ──────────────────────── */
function plus_currency(string $input): array {
    /* accepted: "100 USD to INR", "USD INR 100", or "usd,inr" */
    $s = trim($input);
    $amt = 1.0; $from = ''; $to = '';
    if (preg_match('/^\s*([\d.,]+)\s*([A-Za-z]{3})\s*(?:to|in|->|→)?\s*([A-Za-z]{3})\s*$/i', $s, $m)) {
        $amt = (float)str_replace(',', '', $m[1]); $from = strtoupper($m[2]); $to = strtoupper($m[3]);
    } elseif (preg_match('/^\s*([A-Za-z]{3})\s*(?:to|in|->|→)?\s*([A-Za-z]{3})\s*([\d.,]+)?\s*$/i', $s, $m)) {
        $from = strtoupper($m[1]); $to = strtoupper($m[2]); $amt = isset($m[3]) && $m[3] !== '' ? (float)str_replace(',', '', $m[3]) : 1.0;
    } else {
        return ['ok' => false, 'text' => 'Write it like "100 USD to INR" or "USD to INR".'];
    }
    $r = plus_http_json('https://api.frankfurter.app/latest?' . http_build_query(['from' => $from, 'to' => $to]), 12);
    if (empty($r['ok']) || !is_array($r['json']) || !isset($r['json']['rates'][$to])) {
        return ['ok' => false, 'text' => 'No rate for ' . $from . ' → ' . $to . ' (' . $r['error'] . '). Both must be 3-letter ISO codes (USD, INR, EUR, GBP, JPY…).'];
    }
    $rate = (float)$r['json']['rates'][$to];
    $date = (string)($r['json']['date'] ?? 'latest');
    return ['ok' => true, 'text' => sprintf("%s %s = %s %s  (rate %s, ECB %s)",
        number_format($amt, 2, '.', ''), $from, number_format($amt * $rate, 2, '.', ''), $to,
        number_format($rate, 6, '.', ''), $date)];
}

/* ── wikipedia (no key) ────────────────────────────────────────────────── */
function plus_wikipedia(string $input): array {
    $q = trim($input);
    if ($q === '') { return ['ok' => false, 'text' => 'What should I look up on Wikipedia?']; }
    $r = plus_http_json('https://en.wikipedia.org/w/api.php?' . http_build_query([
        'action' => 'query', 'format' => 'json', 'redirects' => 1, 'prop' => 'extracts',
        'explaintext' => 1, 'exintro' => 1, 'titles' => $q,
    ]), 12);
    $pages = (array)($r['json']['query']['pages'] ?? []);
    $page = reset($pages);
    if (empty($r['ok']) || !is_array($page) || isset($page['missing'])) {
        $s = plus_http_json('https://en.wikipedia.org/w/api.php?' . http_build_query([
            'action' => 'opensearch', 'format' => 'json', 'search' => $q, 'limit' => 5,
        ]), 10);
        if (!empty($s['ok']) && is_array($s['json']) && !empty($s['json'][1])) {
            return ['ok' => false, 'text' => 'No Wikipedia page titled "' . $q . '". Closest matches: ' . implode(', ', array_slice((array)$s['json'][1], 0, 5))];
        }
        return ['ok' => false, 'text' => 'No Wikipedia page found for "' . $q . '".'];
    }
    $title = (string)($page['title'] ?? $q);
    $extract = trim((string)($page['extract'] ?? ''));
    if ($extract === '') { return ['ok' => true, 'text' => "\"{$title}\" exists on Wikipedia but has no intro text. https://en.wikipedia.org/wiki/" . rawurlencode(str_replace(' ', '_', $title))]; }
    if (mb_strlen($extract) > 4000) { $extract = mb_substr($extract, 0, 4000) . '…'; }
    return ['ok' => true, 'text' => "Wikipedia — {$title}:\n\n" . $extract . "\n\nhttps://en.wikipedia.org/wiki/" . rawurlencode(str_replace(' ', '_', $title))];
}

/* ── json_query: pull a path out of a JSON document (pure PHP) ─────────── */
function plus_json_query(string $input): array {
    [$path, $rest] = plus_kv($input, 'path');
    if ($path === '') {
        $lines = preg_split('/\r?\n/', trim($input));
        $path = trim((string)array_shift($lines));
        $rest = trim(implode("\n", $lines));
    }
    if ($path === '' || $rest === '') {
        return ['ok' => false, 'text' => "Send the path on the first line and the JSON after it, e.g.\npath: data.items[0].name\n{ … }"];
    }
    $data = json_decode($rest, true);
    if ($data === null && strtolower($rest) !== 'null') {
        return ['ok' => false, 'text' => 'That is not valid JSON (error: ' . json_last_error_msg() . ').'];
    }
    /* tokenise "a.b[0].c" → [key a, key b, index 0, key c] (handles [0][1] chains) */
    if (!preg_match_all('/\[(\d+)\]|([A-Za-z0-9_\-]+)/', $path, $mm, PREG_SET_ORDER)) {
        return ['ok' => false, 'text' => 'I could not read the path "' . $path . '". Use something like data.items[0].name'];
    }
    $cur = $data; $seen = [];
    foreach ($mm as $m) {
        $isIdx = ($m[1] !== '');
        $seen[] = $isIdx ? '[' . $m[1] . ']' : $m[2];
        if ($isIdx) {
            $idx = (int)$m[1];
            if (!is_array($cur) || !array_key_exists($idx, $cur)) {
                return ['ok' => false, 'text' => 'Index [' . $idx . '] does not exist at "' . implode('.', $seen) . '".'];
            }
            $cur = $cur[$idx];
            continue;
        }
        $key = $m[2];
        if (is_object($cur)) { $cur = $cur->{$key} ?? null; }
        elseif (is_array($cur)) { $cur = $cur[$key] ?? null; }
        else {
            return ['ok' => false, 'text' => '"' . implode('.', $seen) . '" cannot be stepped into (it is ' . gettype($cur) . ').'];
        }
        if ($cur === null) { return ['ok' => false, 'text' => 'Path "' . $path . '" does not exist (stopped at "' . implode('.', $seen) . '").']; }
    }
    if (is_array($cur) || is_object($cur)) {
        $txt = (string)json_encode($cur, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (mb_strlen($txt) > 6000) { $txt = mb_substr($txt, 0, 6000) . "\n…"; }
        return ['ok' => true, 'text' => 'Path "' . $path . '" → ' . $txt];
    }
    return ['ok' => true, 'text' => 'Path "' . $path . '" → ' . (is_bool($cur) ? ($cur ? 'true' : 'false') : (string)$cur)];
}

/* ── hash_text: hashes + base64 (pure PHP) ─────────────────────────────── */
function plus_hash(string $input): array {
    [$algo, $text] = plus_kv($input, 'algo');
    if ($algo === '') {
        $lines = preg_split('/\r?\n/', $input);
        $algo = trim((string)array_shift($lines));
        $text = trim(implode("\n", $lines));
    }
    $algo = strtolower(preg_replace('/[^a-z0-9]/', '', (string)$algo));
    /* one line only → it is the text, not an algorithm name (check the raw input:
       "sha256\n" must still count as "algo, empty text") */
    if ($text === '' && strpos($input, "\n") === false) { $text = trim($input); $algo = ''; }
    if ($text === '') { return ['ok' => false, 'text' => 'Give me the text to hash (algo on the first line, text after).']; }
    $map = ['md5' => 'md5', 'sha1' => 'sha1', 'sha256' => 'sha256', 'sha512' => 'sha512', 'crc32' => 'crc32b'];
    if ($algo === 'base64' || $algo === 'b64') { return ['ok' => true, 'text' => 'base64: ' . base64_encode($text)]; }
    if ($algo === 'unbase64' || $algo === 'b64d') {
        $d = base64_decode($text, true);
        return ['ok' => $d !== false, 'text' => $d !== false ? 'decoded: ' . $d : 'That is not valid base64.'];
    }
    if ($algo === 'urlencode') { return ['ok' => true, 'text' => 'urlencoded: ' . rawurlencode($text)]; }
    if ($algo === '') { $algo = 'sha256'; }
    if (!isset($map[$algo])) {
        return ['ok' => false, 'text' => 'Unknown algorithm "' . $algo . '". Use md5, sha1, sha256, sha512, crc32, base64, unbase64 or urlencode.'];
    }
    return ['ok' => true, 'text' => $algo . ': ' . hash($map[$algo], $text)];
}

/* ── uuid: v4 identifiers (pure PHP) ───────────────────────────────────── */
function plus_uuid(string $input): array {
    $n = 1;
    if (preg_match('/(\d+)/', trim($input), $m)) { $n = max(1, min(50, (int)$m[1])); }
    $out = [];
    for ($i = 0; $i < $n; $i++) {
        $b = function_exists('random_bytes') ? random_bytes(16) : '';
        if (strlen($b) !== 16) { $b = ''; for ($k = 0; $k < 16; $k++) { $b .= chr(mt_rand(0, 255)); } }
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        $out[] = implode('-', [bin2hex(substr($b, 0, 4)), bin2hex(substr($b, 4, 2)), bin2hex(substr($b, 6, 2)), bin2hex(substr($b, 8, 2)), bin2hex(substr($b, 10, 6))]);
    }
    return ['ok' => true, 'text' => implode("\n", $out)];
}

/* ── qr_code: PNG via goqr (no key) ────────────────────────────────────── */
function plus_qr(string $input): array {
    $lines = preg_split('/\r?\n/', trim($input));
    $text = trim((string)array_shift($lines));
    if ($text === '') { return ['ok' => false, 'text' => 'Give me the text or URL to turn into a QR code.']; }
    if (mb_strlen($text) > 2000) { return ['ok' => false, 'text' => 'That is too long for a QR code (limit ~2000 characters).']; }
    $url = 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query(['size' => '400x400', 'data' => $text, 'format' => 'png']);
    if (!function_exists('curl_init')) { return ['ok' => true, 'text' => 'QR code image: ' . $url]; }
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_ENCODING => '', CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS]);
    $png = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 200 && is_string($png) && strlen($png) > 100 && substr($png, 0, 8) === "\x89PNG\r\n\x1a\n") {
        $dir = data_dir() . '/qr';
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $name = 'qr-' . substr(hash('sha256', $text), 0, 10) . '.png';
        if (@file_put_contents($dir . '/' . $name, $png) !== false) {
            return ['ok' => true, 'text' => 'QR code saved: data/qr/' . $name . ' (linked to: ' . $text . ')'];
        }
    }
    return ['ok' => true, 'text' => 'QR code image: ' . $url];
}

/* ── image_gen: Pollinations — no key, no signup ───────────────────────────
   Pollinations is the only image API we found that answers with no auth at all,
   so this tool works on a fresh install and for guests. INPUT: a description;
   an optional second line "1024x1024" sets the size (64-2048, default 1024²). */
function plus_image_gen(string $input): array {
    $lines  = preg_split('/\r?\n/', trim($input));
    $prompt = trim((string)array_shift($lines));
    if ($prompt === '') { return ['ok' => false, 'text' => 'Describe the image you want me to generate.']; }
    if (mb_strlen($prompt) > 1000) { $prompt = mb_substr($prompt, 0, 1000); }

    $w = 1024; $h = 1024;
    foreach ($lines as $ln) {
        if (preg_match('/^(\d{2,4})\s*[x×]\s*(\d{2,4})$/i', trim((string)$ln), $m)) {
            $w = max(64, min(2048, (int)$m[1]));
            $h = max(64, min(2048, (int)$m[2]));
            break;
        }
    }
    /* Only width/height/nologo stay free: adding "safe" or "seed" makes
       Pollinations answer 402 (paid tier). Verified against the live API. */
    $url = 'https://image.pollinations.ai/prompt/' . rawurlencode($prompt) . '?' . http_build_query([
        'width' => $w, 'height' => $h, 'nologo' => 'true',
    ]);
    if (!function_exists('curl_init')) { return ['ok' => true, 'text' => 'Image (open this URL): ' . $url]; }

    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 90,
        CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_ENCODING => '', CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS]);
    $bin  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    $isImg = is_string($bin) && strlen($bin) > 500
        && (substr($bin, 0, 8) === "\x89PNG\r\n\x1a\n" || substr($bin, 0, 3) === "\xFF\xD8\xFF");
    if ($code === 200 && $isImg) {
        $ext = (stripos($type, 'png') !== false) ? 'png' : 'jpg';
        $dir = data_dir() . '/gen';
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $name = 'img-' . gmdate('Ymd-His') . '-' . substr(hash('sha256', $prompt . microtime(true)), 0, 8) . '.' . $ext;
        if (@file_put_contents($dir . '/' . $name, $bin) !== false) {
            return ['ok' => true, 'text' => "Image generated and saved: data/gen/{$name} ({$w}x{$h}) — prompt: {$prompt}"];
        }
    }
    return ['ok' => true, 'text' => 'Image (open this URL): ' . $url];
}

/* ── code_lookup: package facts from npm and PyPI (no key) ─────────────────
   A coding agent constantly needs "what is the current version / when was it
   released / what does it do". Both registries answer anonymously. */
function plus_code_lookup(string $input): array {
    $lines = preg_split('/\r?\n/', trim($input));
    $name  = trim((string)array_shift($lines));
    $eco   = strtolower(trim((string)(array_shift($lines) ?? '')));
    if ($name === '') { return ['ok' => false, 'text' => 'Give me a package name, optionally with "npm" or "pypi" on the second line.']; }
    if (!preg_match('/^[A-Za-z0-9._@\/@\-]{1,120}$/', $name)) { return ['ok' => false, 'text' => 'That package name has characters I cannot send to a registry.']; }
    /* guess the ecosystem if it was not given */
    if (!in_array($eco, ['npm', 'pypi', 'python', 'py'], true)) {
        $eco = preg_match('/^[A-Z][A-Za-z0-9_]*$|^Flask$|^Django$|^requests$/', $name) ? 'pypi' : 'npm';
    }
    if ($eco === 'pypi' || $eco === 'python' || $eco === 'py') {
        $r = plus_http_json('https://pypi.org/pypi/' . rawurlencode($name) . '/json', 12);
        if (empty($r['ok']) || !is_array($r['json'])) { return ['ok' => false, 'text' => 'PyPI has no package called "' . $name . '" (or it did not answer).']; }
        $i = $r['json']['info'] ?? [];
        $v = (string)($i['version'] ?? '?');
        $rel = (string)($r['json']['urls'][0]['upload_time'] ?? '');
        $out = 'PyPI — ' . (string)($i['name'] ?? $name) . "\n"
             . 'Latest version: ' . $v . "\n"
             . ($rel ? 'Released: ' . substr($rel, 0, 10) . "\n" : '')
             . 'Summary: ' . mb_substr(trim((string)($i['summary'] ?? '')), 0, 300) . "\n"
             . 'License: ' . ((string)($i['license'] ?? '') !== '' ? (string)$i['license'] : 'not stated') . "\n"
             . 'Requires Python: ' . ((string)($i['requires_python'] ?? '') !== '' ? (string)$i['requires_python'] : 'not stated') . "\n"
             . 'Home page: ' . (string)($i['home_page'] ?? $i['project_url'] ?? '—');
        return ['ok' => true, 'text' => $out];
    }
    $r = plus_http_json('https://registry.npmjs.org/' . rawurlencode($name) . '/latest', 12);
    if (empty($r['ok']) || !is_array($r['json'])) { return ['ok' => false, 'text' => 'npm has no package called "' . $name . '" (or it did not answer).']; }
    $j = $r['json'];
    $out = 'npm — ' . (string)($j['name'] ?? $name) . "\n"
         . 'Latest version: ' . (string)($j['version'] ?? '?') . "\n"
         . 'Description: ' . mb_substr(trim((string)($j['description'] ?? '')), 0, 300) . "\n"
         . 'License: ' . (string)($j['license'] ?? 'not stated') . "\n"
         . 'Unpacked size: ' . (isset($j['dist']['unpackedSize']) ? round(((int)$j['dist']['unpackedSize']) / 1024) . ' KB' : '—') . "\n"
         . 'Homepage: ' . (string)($j['homepage'] ?? '—');
    return ['ok' => true, 'text' => $out];
}

/* ── geocode: place → coordinates (OpenStreetMap Nominatim, no key) ─────── */
function plus_geocode(string $input): array {
    $place = trim($input);
    if ($place === '') { return ['ok' => false, 'text' => 'Give me a place name to look up.']; }
    $r = plus_http_json('https://nominatim.openstreetmap.org/search?' . http_build_query([
        'q' => $place, 'format' => 'json', 'limit' => 3, 'addressdetails' => 1,
    ]), 15);
    $j = (array)($r['json'] ?? []);
    if (empty($r['ok']) || !$j) { return ['ok' => false, 'text' => 'No place found for "' . $place . '".']; }
    $out = [];
    foreach (array_slice($j, 0, 3) as $x) {
        $x = (array)$x;
        $a = (array)($x['address'] ?? []);
        $out[] = (string)($x['display_name'] ?? '')
               . '  →  ' . (string)($x['lat'] ?? '') . ', ' . (string)($x['lon'] ?? '')
               . (isset($a['country']) ? '  (' . $a['country'] . ')' : '');
    }
    return ['ok' => true, 'text' => "Places matching \"{$place}\":\n" . implode("\n", $out)];
}

/* ── forecast: multi-day weather (Open-Meteo, no key) ─────────────────────
   wttr.in (the weather tool) gives today and tomorrow; this gives a real
   multi-day forecast for anywhere, as long as we know the coordinates. */
function plus_forecast(string $input): array {
    $lines = preg_split('/\r?\n/', trim($input));
    $lat = trim((string)array_shift($lines));
    $lon = trim((string)(array_shift($lines) ?? ''));
    if ($lat === '' || $lon === '' || !is_numeric($lat) || !is_numeric($lon)) {
        return ['ok' => false, 'text' => 'Give me latitude on the first line and longitude on the second. Use the geocode tool first if you only have a place name.'];
    }
    $days = max(1, min(16, (int)trim((string)(array_shift($lines) ?? '7')) ?: 7));
    $r = plus_http_json('https://api.open-meteo.com/v1/forecast?' . http_build_query([
        'latitude' => $lat, 'longitude' => $lon, 'forecast_days' => $days, 'timezone' => 'auto',
        'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max',
    ]), 15);
    $d = (array)($r['json']['daily'] ?? []);
    if (empty($r['ok']) || empty($d['time'])) { return ['ok' => false, 'text' => 'Open-Meteo did not return a forecast for those coordinates.']; }
    $codes = [0 => 'Clear', 1 => 'Mainly clear', 2 => 'Partly cloudy', 3 => 'Overcast', 45 => 'Fog', 48 => 'Freezing fog',
        51 => 'Light drizzle', 53 => 'Drizzle', 55 => 'Heavy drizzle', 61 => 'Light rain', 63 => 'Rain', 65 => 'Heavy rain',
        71 => 'Light snow', 73 => 'Snow', 75 => 'Heavy snow', 80 => 'Rain showers', 81 => 'Rain showers', 82 => 'Violent showers',
        95 => 'Thunderstorm', 96 => 'Thunderstorm with hail', 99 => 'Thunderstorm with hail'];
    $out = [];
    foreach ((array)$d['time'] as $i => $day) {
        $c = (int)($d['weather_code'][$i] ?? -1);
        $out[] = $day . ': ' . ($codes[$c] ?? 'code ' . $c)
               . ', ' . (string)($d['temperature_2m_min'][$i] ?? '?') . '–' . (string)($d['temperature_2m_max'][$i] ?? '?') . '°C'
               . ', rain ' . (string)($d['precipitation_probability_max'][$i] ?? '?') . '%';
    }
    return ['ok' => true, 'text' => "Forecast for {$lat},{$lon}:\n" . implode("\n", $out)];
}

/* ── github_repo: public repository facts (no key, 60 req/hour per IP) ───── */
function plus_github_repo(string $input): array {
    $repo = trim($input);
    $repo = preg_replace('#^https?://(www\.)?github\.com/#i', '', $repo);
    $repo = trim((string)$repo, '/');
    if (!preg_match('#^[A-Za-z0-9._-]+/[A-Za-z0-9._-]+$#', $repo)) {
        return ['ok' => false, 'text' => 'Give me a repository as "owner/name" or its GitHub URL.'];
    }
    $r = plus_http_json('https://api.github.com/repos/' . $repo, 15);
    if (empty($r['ok']) || !is_array($r['json']) || isset($r['json']['message'])) {
        return ['ok' => false, 'text' => 'GitHub did not return that repository (' . (string)($r['json']['message'] ?? 'request failed') . ').'];
    }
    $j = $r['json'];
    $kb = (int)($j['size'] ?? 0);
    return ['ok' => true, 'text' => 'GitHub — ' . (string)($j['full_name'] ?? $repo) . "\n"
        . 'Description: ' . mb_substr((string)($j['description'] ?? '—'), 0, 300) . "\n"
        . 'Stars: ' . (int)($j['stargazers_count'] ?? 0) . ' · Forks: ' . (int)($j['forks_count'] ?? 0)
        . ' · Open issues: ' . (int)($j['open_issues_count'] ?? 0) . "\n"
        . 'Language: ' . (string)($j['language'] ?? '—') . ' · License: ' . (string)((is_array($j['license'] ?? null) ? ($j['license']['spdx_id'] ?? '—') : '—')) . "\n"
        . 'Size: ' . ($kb ? round($kb / 1024) . ' MB' : '—') . "\n"
        . 'Default branch: ' . (string)($j['default_branch'] ?? '—') . "\n"
        . 'Created: ' . substr((string)($j['created_at'] ?? ''), 0, 10) . ' · Last push: ' . substr((string)($j['pushed_at'] ?? ''), 0, 10) . "\n"
        . 'Homepage: ' . ((string)($j['homepage'] ?? '') !== '' ? (string)$j['homepage'] : '—')];
}

/* ═══════════ local tools — no network, instant ═══════════ */

/* ── regex_test: try a pattern against text (no network) ───────────────── */
function plus_regex(string $input): array {
    $lines = preg_split('/\r?\n/', (string)$input);
    $pattern = trim((string)array_shift($lines));
    if ($pattern === '') { return ['ok' => false, 'text' => 'First line: the regex pattern. Following lines: the text to match it against.']; }
    $text = implode("\n", $lines);
    if (trim($text) === '') { return ['ok' => false, 'text' => 'Give me the pattern on the first line and the text to test it on after that.']; }
    $delim = $pattern[0] ?? '/';
    $body  = $pattern;
    $flags = '';
    if (preg_match('#^([\~/\#%|!,@;:])(.*)\1([a-zA-Z]*)$#s', $pattern, $m)) {
        $delim = $m[1]; $body = $m[2]; $flags = $m[3];
    }
    $final = $delim . $body . $delim . $flags;
    set_error_handler(static function () { return true; });
    $ok  = @preg_match($final, $text, $m1);
    $all = $ok !== false ? @preg_match_all($final, $text, $m2) : false;
    $err = preg_last_error();
    restore_error_handler();
    if ($ok === false) {
        $msg = $err === PREG_BACKTRACK_LIMIT_ERROR ? 'backtrack limit exceeded (the pattern is too greedy)'
             : ($err === PREG_RECURSION_LIMIT_ERROR ? 'recursion limit exceeded'
             : 'the pattern is not valid');
        return ['ok' => false, 'text' => 'Regex error: ' . $msg . '. Send it as /pattern/flags if it needs different delimiters.'];
    }
    $out = 'Pattern: ' . $final . "\nMatches: " . (int)$all;
    if ($all > 0) {
        $show = array_slice((array)$m2[0], 0, 10);
        $out .= "\nFirst " . count($show) . ": " . implode(' | ', array_map(static function ($s) {
            return mb_strlen((string)$s) > 80 ? mb_substr((string)$s, 0, 77) . '…' : (string)$s;
        }, $show));
        if (isset($m1[1]) && $m1[1] !== '') { $out .= "\nGroup 1: " . $m1[1]; }
    } else {
        $out .= "\nNo match found.";
    }
    return ['ok' => true, 'text' => $out];
}

/* ── units: convert between common units (no network) ──────────────────── */
function plus_units(string $input): array {
    if (!preg_match('/^\s*(-?[\d.,eE+]+)\s*([a-zA-Z°µ\/^*\s]+?)\s*(?:to|in|->|→|as)\s*([a-zA-Z°µ\/^*\s]+?)\s*$/u', trim($input), $m)) {
        return ['ok' => false, 'text' => 'Write it as "10 km to miles" or "72 f to c".'];
    }
    $val   = (float)str_replace(',', '', $m[1]);
    $from  = strtolower(preg_replace('/\s+/', '', $m[2]));
    $to    = strtolower(preg_replace('/\s+/', '', $m[3]));
    $alias = [
        'c' => 'celsius', 'f' => 'fahrenheit', 'k' => 'kelvin', 'kelvins' => 'kelvin',
        'm' => 'metre', 'meter' => 'metre', 'meters' => 'metre', 'metres' => 'metre',
        'km' => 'kilometre', 'kilometers' => 'kilometre', 'kilometres' => 'kilometre',
        'mi' => 'mile', 'miles' => 'mile', 'ft' => 'foot', 'feet' => 'foot', 'foot' => 'foot',
        'in' => 'inch', 'inch' => 'inch', 'inches' => 'inch', 'cm' => 'centimetre', 'centimeters' => 'centimetre',
        'mm' => 'millimetre', 'millimeters' => 'millimetre', 'yd' => 'yard', 'yards' => 'yard',
        'kg' => 'kilogram', 'kilograms' => 'kilogram', 'g' => 'gram', 'grams' => 'gram',
        'lb' => 'pound', 'lbs' => 'pound', 'pounds' => 'pound', 'oz' => 'ounce', 'ounces' => 'ounce',
        'l' => 'litre', 'liter' => 'litre', 'liters' => 'litre', 'litres' => 'litre',
        'ml' => 'millilitre', 'milliliters' => 'millilitre', 'gal' => 'gallon', 'gallons' => 'gallon',
        's' => 'second', 'sec' => 'second', 'seconds' => 'second', 'min' => 'minute', 'minutes' => 'minute',
        'h' => 'hour', 'hr' => 'hour', 'hours' => 'hour', 'd' => 'day', 'days' => 'day',
        'kmh' => 'kmh', 'kph' => 'kmh', 'mph' => 'mph',
        'sqm' => 'sqm', 'm2' => 'sqm', 'sqft' => 'sqft', 'ft2' => 'sqft',
    ];
    $from = $alias[$from] ?? $from;
    $to   = $alias[$to] ?? $to;
    $base = [
        /* all three temperatures share the group 'temp' — the branch below
           handles their offset conversion, so they must not be split apart */
        'celsius' => ['temp', 0], 'fahrenheit' => ['temp', 0], 'kelvin' => ['temp', 0],
        'metre' => ['len', 1], 'kilometre' => ['len', 1000], 'centimetre' => ['len', 0.01], 'millimetre' => ['len', 0.001],
        'mile' => ['len', 1609.344], 'foot' => ['len', 0.3048], 'inch' => ['len', 0.0254], 'yard' => ['len', 0.9144],
        'kilogram' => ['mass', 1], 'gram' => ['mass', 0.001], 'pound' => ['mass', 0.45359237], 'ounce' => ['mass', 0.028349523125],
        'litre' => ['vol', 1], 'millilitre' => ['vol', 0.001], 'gallon' => ['vol', 3.785411784],
        'second' => ['time', 1], 'minute' => ['time', 60], 'hour' => ['time', 3600], 'day' => ['time', 86400],
        'kmh' => ['speed', 1], 'mph' => ['speed', 1.609344],
        'sqm' => ['area', 1], 'sqft' => ['area', 0.09290304],
    ];
    if (!isset($base[$from], $base[$to])) {
        return ['ok' => false, 'text' => 'I do not know one of those units. Known: c/f/k, m/km/cm/mm/mi/ft/in/yd, kg/g/lb/oz, l/ml/gal, s/min/h/d, kmh/mph, sqm/sqft.'];
    }
    $f = $base[$from]; $t = $base[$to];
    if ($f[0] !== $t[0]) { return ['ok' => false, 'text' => 'Cannot convert ' . $from . ' to ' . $to . ' — different kinds of unit.']; }
    if ($f[0] === 'temp') {
        $out = $val;
        if ($from === 'fahrenheit') { $out = ($val - 32) * 5 / 9; }
        elseif ($from === 'kelvin') { $out = $val - 273.15; }
        if ($to === 'fahrenheit') { $out = $out * 9 / 5 + 32; }
        elseif ($to === 'kelvin') { $out = $out + 273.15; }
    } else {
        $out = $val * $f[1] / $t[1];
    }
    $out = round($out, 6);
    if (abs($out) >= 10000) { $out = round($out, 2); }
    return ['ok' => true, 'text' => $m[1] . ' ' . $m[2] . ' = ' . rtrim(rtrim(number_format((float)$out, 6, '.', ''), '0'), '.') . ' ' . $m[3]];
}

/* ── stats: word/character counts and reading time (no network) ─────────── */
function plus_stats(string $input): array {
    $t = trim($input);
    if ($t === '') { return ['ok' => false, 'text' => 'Give me some text to measure.']; }
    $chars  = mb_strlen($t);
    $words  = preg_split('/\s+/u', $t) ?: [];
    $words  = count(array_filter($words, static function ($w) { return $w !== ''; }));
    $sent   = preg_match_all('/[.!?]+(?:\s|$)/u', $t) ?: 0;
    $lines  = count(preg_split('/\r\n|\r|\n/', $t) ?: []);
    $bytes  = strlen($t);
    $read   = max(1, (int)ceil($words / 200));
    $uniq   = count(array_unique(array_map('mb_strtolower', preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($t), -1, PREG_SPLIT_NO_EMPTY) ?: [])));
    return ['ok' => true, 'text' => "Characters: {$chars}\nWords: {$words}\nSentences: {$sent}\nLines: {$lines}\n"
        . "Unique words: {$uniq}\nBytes (UTF-8): {$bytes}\nReading time: ~{$read} min at 200 wpm"];
}

/* ── b64: encode or decode base64 (no network) ──────────────────────────── */
function plus_b64(string $input): array {
    $lines = preg_split('/\r?\n/', trim($input));
    $mode  = strtolower(trim((string)array_shift($lines)));
    $body  = implode("\n", $lines);
    if ($body === '') { return ['ok' => false, 'text' => 'First line: "encode" or "decode". Remaining lines: the text.']; }
    if (strpos($mode, 'dec') === 0) {
        $out = base64_decode(preg_replace('/\s+/', '', $body), true);
        if ($out === false) { return ['ok' => false, 'text' => 'That is not valid base64.']; }
        return ['ok' => true, 'text' => $out];
    }
    return ['ok' => true, 'text' => base64_encode($body)];
}

/* ── table: turn CSV/TSV into a markdown table (no network) ─────────────── */
function plus_table(string $input): array {
    $lines = preg_split('/\r\n|\r|\n/', trim($input));
    $lines = array_values(array_filter($lines, static function ($l) { return trim($l) !== ''; }));
    if (count($lines) < 2) { return ['ok' => false, 'text' => 'Give me at least two lines: a header row and one data row.']; }
    $sep = (substr_count($lines[0], "\t") > 0) ? "\t" : ',';
    $parse = static function (string $l) use ($sep): array {
        return $sep === "\t" ? explode("\t", $l) : str_getcsv($l);
    };
    $rows = array_map($parse, $lines);
    $n    = max(array_map('count', $rows));
    foreach ($rows as $i => $r) { $rows[$i] = array_pad(array_slice((array)$r, 0, $n), $n, ''); }
    $esc  = static function ($c) { return str_replace('|', '\\|', trim((string)$c)); };
    $head = array_shift($rows);
    $out  = '| ' . implode(' | ', array_map($esc, $head)) . " |\n";
    $out .= '| ' . implode(' | ', array_fill(0, $n, '---')) . " |\n";
    foreach ($rows as $r) { $out .= '| ' . implode(' | ', array_map($esc, $r)) . " |\n"; }
    return ['ok' => true, 'text' => "Markdown table ({$n} columns, " . count($rows) . " data rows):\n\n" . rtrim($out)];
}

/* ── url_shorten: is.gd (no key) ───────────────────────────────────────── */
function plus_shorten(string $input): array {
    $url = trim($input);
    if (!preg_match('~^https?://\S+$~i', $url)) { return ['ok' => false, 'text' => 'Give me one full http(s) URL to shorten.']; }
    $r = plus_http_json('https://is.gd/create.php?' . http_build_query(['format' => 'json', 'url' => $url]), 12);
    if (!empty($r['ok']) && is_array($r['json']) && !empty($r['json']['shorturl'])) {
        return ['ok' => true, 'text' => 'Short link: ' . (string)$r['json']['shorturl']];
    }
    return ['ok' => false, 'text' => 'The shortener refused that URL (' . $r['error'] . ').'];
}

}   /* end function_exists guard */
