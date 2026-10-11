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
