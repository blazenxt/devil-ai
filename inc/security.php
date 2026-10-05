<?php
/** Devil AI — security guard: anti-bot, anti-scrape, auth-domain checks, optional reCAPTCHA. */
if (defined('DEVIL_SECURITY_LOADED')) { return; }
define('DEVIL_SECURITY_LOADED', 1);
if (!function_exists('str_contains')) { function str_contains(string $haystack, string $needle): bool { return $needle === '' || strpos($haystack, $needle) !== false; } }
if (!function_exists('str_ends_with')) { function str_ends_with(string $haystack, string $needle): bool { return $needle === '' || substr($haystack, -strlen($needle)) === $needle; } }
if (!function_exists('mb_strlen')) { function mb_strlen($s) { return strlen((string)$s); } }
if (!function_exists('mb_substr')) { function mb_substr($s, $a, $b = null) { return $b === null ? substr((string)$s, $a) : substr((string)$s, $a, $b); } }

function devil_sec_root(): string { return dirname(__DIR__); }
function devil_sec_data_dir(): string { return devil_sec_root() . '/data'; }
function devil_sec_first_ip(string $value): string {
    $first = trim(explode(',', $value)[0]);
    return filter_var($first, FILTER_VALIDATE_IP) ? $first : '';
}
function devil_sec_ip(): string {
    if (strtolower((string)($_SERVER['HTTP_X_DEVIL_AI_PROXY'] ?? '')) === 'cloudflare') {
        $proxiedIp = devil_sec_first_ip((string)($_SERVER['HTTP_X_DEVIL_CLIENT_IP'] ?? ''));
        if ($proxiedIp !== '') { return $proxiedIp; }
    }
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP'] as $k) {
        if (!empty($_SERVER[$k])) {
            $first = devil_sec_first_ip((string)$_SERVER[$k]);
            if ($first !== '') { return $first; }
        }
    }
    return (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}
function devil_sec_json(string $file): array {
    $path = devil_sec_data_dir() . '/' . $file;
    if (!is_readable($path)) { return []; }
    $j = json_decode((string)file_get_contents($path), true);
    return is_array($j) ? $j : [];
}
function devil_sec_save(string $file, array $data): void {
    $dir = devil_sec_data_dir();
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    @file_put_contents($dir . '/' . $file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
}
function devil_sec_is_json_request(): bool {
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    return str_contains($uri, '/v1/') || str_ends_with((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/api.php') || str_contains($accept, 'application/json');
}
function devil_sec_block(string $message = 'Request blocked for security reasons.', int $status = 403): void {
    http_response_code($status);
    header('X-Robots-Tag: noindex');
    if (devil_sec_is_json_request()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        $safe = htmlspecialchars($message, ENT_QUOTES);
        echo '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><title>Blocked — Devil AI</title><body style="font-family:system-ui;background:#0c0709;color:#efe6ea;display:grid;place-items:center;min-height:100vh;margin:0"><main style="max-width:520px;padding:28px;border:1px solid rgba(244,63,94,.25);border-radius:18px;background:#171014"><h1 style="margin:0 0 8px">Request blocked</h1><p style="color:#a8929b;line-height:1.6">' . $safe . '</p></main></body>';
    }
    exit;
}
function devil_sec_rate(string $bucket, string $key, int $max, int $window): bool {
    if ($max <= 0) { return true; }
    $file = 'security_rl.json';
    $map = devil_sec_json($file);
    $now = time();
    $full = $bucket . ':' . $key;
    $hits = [];
    foreach (($map[$full] ?? []) as $t) { if (is_int($t) && $t > $now - $window) { $hits[] = $t; } }
    if (count($hits) >= $max) { $map[$full] = $hits; devil_sec_save($file, $map); return false; }
    $hits[] = $now;
    $map[$full] = $hits;
    if (count($map) > 5000) {
        foreach ($map as $k => $v) {
            $fresh = array_values(array_filter((array)$v, function ($t) use ($now, $window) { return is_int($t) && $t > $now - max(3600, $window); }));
            if ($fresh) { $map[$k] = $fresh; } else { unset($map[$k]); }
        }
    }
    devil_sec_save($file, $map);
    return true;
}
function devil_sec_ban_ip(string $ip, int $seconds, string $reason): void {
    $bans = devil_sec_json('security_bans.json');
    $bans[$ip] = ['until' => time() + $seconds, 'reason' => $reason, 'ts' => time()];
    devil_sec_save('security_bans.json', $bans);
}
function devil_sec_ip_in_cidr(string $ip, string $cidr): bool {
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) { return false; }
    if (!str_contains($cidr, '/')) { return $ip === $cidr; }
    [$subnet, $mask] = explode('/', $cidr, 2);
    $mask = (int)$mask;
    if ($mask < 0 || $mask > 32 || !filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) { return false; }
    $ipLong = ip2long($ip); $subLong = ip2long($subnet);
    $maskLong = $mask === 0 ? 0 : (-1 << (32 - $mask));
    return (($ipLong & $maskLong) === ($subLong & $maskLong));
}
function devil_sec_datacenter_like(string $ip, bool $suspiciousClient): bool {
    foreach (devil_sec_json('security_datacenter_cidrs.json') as $cidr) {
        if (is_string($cidr) && devil_sec_ip_in_cidr($ip, trim($cidr))) { return true; }
    }
    if (!$suspiciousClient || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) { return false; }
    $host = strtolower((string)@gethostbyaddr($ip));
    if ($host === '' || $host === $ip) { return false; }
    return preg_match('/(amazonaws|compute|googleusercontent|azure|digitalocean|linode|vultr|ovh|hetzner|contabo|oraclecloud|scaleway|leaseweb|colo|datacenter|cloud|server|host|vps)/i', $host) === 1;
}

function devil_sec_proxy_vpn_org(string $org): bool {
    $org = trim($org);
    if ($org === '') { return false; }
    return preg_match('/(\bvpn\b|proxy|tor\b|anonymous|anonymizer|privacy|tunnel|mullvad|nordvpn|expressvpn|surfshark|proton\s*(vpn)?|windscribe|private\s*internet\s*access|cyberghost|torguard|hidemyass|hide\s*my|purevpn|ivpn|airvpn|vyprvpn|hotspot\s*shield|ipvanish|warp|cloudflare\s*warp|datacenter|data\s*center|colo(cation)?|hosting|hoster|\bvps\b|dedicated\s*server|amazon|aws|google\s*cloud|microsoft\s*azure|digitalocean|akamai\s*linode|linode|vultr|ovh|hetzner|contabo|leaseweb|scaleway|oracle\s*cloud|alibaba|tencent|choopa|m247|datacamp|cdn77|hivelocity|psychz|shinjiru|quadra|frantech|racknerd|hostwinds|upcloud|clouvider|packet\s*exchange|servermania|ionos|strato|kamatera|netcup|timeweb)/i', $org) === 1;
}
function devil_sec_proxy_vpn_asn(int $asn): bool {
    static $blocked = [13335,14618,16509,8075,15169,396982,14061,63949,20473,53667,16276,24940,9009,60068,62240,51167,31898,398101,12876,60781,28753,45102,132203,6939,202053,47583,20454,29802,29838,55286,29854,8100,35916,40676,46606,36352,55293,32244,399629];
    return $asn > 0 && in_array($asn, $blocked, true);
}
function devil_sec_proxy_header_present(): string {
    $headers = ['HTTP_VIA','HTTP_FORWARDED','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','HTTP_X_PROXY_ID','HTTP_X_PROXY_AUTHORIZATION','HTTP_PROXY_AUTHORIZATION','HTTP_PROXY_CONNECTION','HTTP_CLIENT_IP','HTTP_X_CLIENT_IP','HTTP_FORWARDED_FOR','HTTP_X_FORWARDED','HTTP_X_CLUSTER_CLIENT_IP'];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) { return $h; }
    }
    return '';
}
function devil_sec_proxy_vpn_reason(string $ip, bool $trustedDeveloperApi): string {
    if ($trustedDeveloperApi) { return ''; }
    $proxyHeader = devil_sec_proxy_header_present();
    if ($proxyHeader !== '') { return 'proxy header'; }

    $country = strtoupper(trim((string)($_SERVER['HTTP_X_DEVIL_CLIENT_COUNTRY'] ?? ($_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''))));
    if ($country === 'T1') { return 'tor/proxy network'; }

    $threatRaw = (string)($_SERVER['HTTP_X_DEVIL_CLIENT_THREAT'] ?? ($_SERVER['HTTP_CF_THREAT_SCORE'] ?? ''));
    if ($threatRaw !== '' && is_numeric($threatRaw) && (float)$threatRaw >= 30) { return 'high risk IP reputation'; }

    $asnRaw = (string)($_SERVER['HTTP_X_DEVIL_CLIENT_ASN'] ?? ($_SERVER['HTTP_CF_ASN'] ?? ''));
    $asn = ctype_digit($asnRaw) ? (int)$asnRaw : 0;
    if (devil_sec_proxy_vpn_asn($asn)) { return 'blocked ASN'; }

    $org = (string)($_SERVER['HTTP_X_DEVIL_CLIENT_ASO'] ?? ($_SERVER['HTTP_CF_ASORGANIZATION'] ?? ''));
    if (devil_sec_proxy_vpn_org($org)) { return 'blocked network'; }

    if (devil_sec_datacenter_like($ip, false)) { return 'blocked datacenter range'; }
    $fromCloudflareWorker = strtolower((string)($_SERVER['HTTP_X_DEVIL_AI_PROXY'] ?? '')) === 'cloudflare';
    if (!$fromCloudflareWorker && devil_sec_datacenter_like($ip, true)) { return 'datacenter network'; }
    return '';
}
function devil_security_boot(): void {
    if (PHP_SAPI === 'cli' || headers_sent()) { return; }
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), geolocation=(), payment=()');

    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['GET', 'POST', 'OPTIONS', 'HEAD'], true)) { devil_sec_block('HTTP method not allowed.', 405); }

    $ip = devil_sec_ip();
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $isApi = str_contains($uri, '/v1/') || str_ends_with($script, '/api.php');
    $ua = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $uaLow = strtolower($ua);

    $bans = devil_sec_json('security_bans.json');
    if (isset($bans[$ip]) && is_array($bans[$ip]) && (int)($bans[$ip]['until'] ?? 0) > time()) {
        devil_sec_block('Too many suspicious requests. Please try again later.', 403);
    }

    if (preg_match('/(\.env|wp-login|xmlrpc\.php|phpmyadmin|adminer|\.git|composer\.(json|lock)|vendor\/|config\.php|backup|\.sql|passwd|\.DS_Store)/i', $uri)) {
        devil_sec_ban_ip($ip, 3600, 'probe');
        devil_sec_block('Security probe blocked.', 403);
    }

    $limit = $isApi ? 180 : 240;
    if (!devil_sec_rate('all', $ip, $limit, 60)) {
        devil_sec_ban_ip($ip, 1800, 'rate');
        devil_sec_block('Rate limit exceeded. Please wait and try again.', 429);
    }

    $hasApiKey = preg_match('/Bearer\s+(?:devil_blazenxt_|dv_live_)/i', (string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''))
        || !empty($_SERVER['HTTP_X_DEVIL_API_KEY']) || !empty($_GET['key']) || !empty($_GET['api_key']) || !empty($_GET['apikey']);
    $trustedDeveloperApi = (str_contains($uri, '/v1/') || preg_match('#/v1$#', $uri) === 1) && $hasApiKey;
    if (($proxyVpnReason = devil_sec_proxy_vpn_reason($ip, $trustedDeveloperApi)) !== '') {
        devil_sec_block('VPN, proxy, Tor and datacenter networks are blocked for security.', 403);
    }
    $badUa = $ua === '' || preg_match('/(python-requests|scrapy|curl|wget|httpclient|libwww|go-http-client|java\/|okhttp|node-fetch|axios|phantomjs|headless|selenium|playwright|puppeteer|nikto|sqlmap|nmap|masscan|zgrab|crawler|spider|\bbot\b)/i', $ua) === 1;
    $noBrowserHints = empty($_SERVER['HTTP_ACCEPT_LANGUAGE']) && empty($_SERVER['HTTP_SEC_CH_UA']) && !$hasApiKey;

    if (!$isApi && $badUa && !str_contains($uaLow, 'devil-agent')) {
        devil_sec_ban_ip($ip, 1800, 'scraper ua');
        devil_sec_block('Automated scraping is blocked.', 403);
    }
    if (!$isApi && $noBrowserHints && devil_sec_datacenter_like($ip, true)) {
        devil_sec_ban_ip($ip, 3600, 'datacenter');
        devil_sec_block('Datacenter and automated traffic is blocked.', 403);
    }
    if ($isApi && !$hasApiKey && $badUa && preg_match('/(python-requests|scrapy|wget|nikto|sqlmap|nmap|masscan|zgrab)/i', $ua) === 1) {
        devil_sec_block('Automated API probing is blocked.', 403);
    }
}

function devil_security_signup_closed_message(): string {
    return "We aren’t able to create new users right now. We’ll start accepting more sign-ups soon.";
}
function devil_security_email_domain(string $email): string {
    $email = strtolower(trim($email));
    if (!str_contains($email, '@')) { return ''; }
    return trim(substr(strrchr($email, '@'), 1));
}
function devil_security_domain_list(string $key): array {
    $cfg = devil_security_config();
    $raw = $cfg[$key] ?? [];
    if (is_string($raw)) { $raw = preg_split('/[\s,;]+/', $raw) ?: []; }
    if (!is_array($raw)) { return []; }
    $out = [];
    foreach ($raw as $d) {
        $d = strtolower(trim((string)$d));
        $d = ltrim($d, '@.');
        if ($d !== '' && preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $d)) { $out[] = $d; }
    }
    return array_values(array_unique($out));
}
function devil_security_domain_matches(string $domain, array $list): bool {
    $domain = strtolower(trim($domain));
    foreach ($list as $d) {
        $d = strtolower(trim((string)$d));
        if ($d !== '' && ($domain === $d || str_ends_with($domain, '.' . $d))) { return true; }
    }
    return false;
}
function devil_security_is_registered_domain_like(string $domain): bool {
    $labels = array_values(array_filter(explode('.', strtolower(trim($domain)))));
    $n = count($labels);
    if ($n === 2) { return true; }
    if ($n !== 3) { return false; }
    $last2 = $labels[$n - 2] . '.' . $labels[$n - 1];
    $multiPublicSuffixes = [
        'co.in','firm.in','net.in','org.in','gen.in','ind.in','ac.in','edu.in','res.in','gov.in','nic.in',
        'co.uk','org.uk','me.uk','ltd.uk','plc.uk','ac.uk','gov.uk','net.uk',
        'com.au','net.au','org.au','edu.au','gov.au','asn.au','id.au',
        'co.nz','net.nz','org.nz','ac.nz','school.nz','govt.nz',
        'com.br','net.br','org.br','com.mx','com.tr','com.sg','com.my','com.ph','com.pk','com.bd','co.jp','ne.jp','or.jp','co.kr','or.kr','com.cn','net.cn','org.cn','com.hk','net.hk','org.hk','co.za','org.za'
    ];
    return in_array($last2, $multiPublicSuffixes, true);
}
function devil_security_is_disposable_domain(string $domain): bool {
    $domain = strtolower(trim($domain));
    if (devil_security_domain_matches($domain, devil_security_domain_list('security_extra_blocked_email_domains'))) { return true; }
    $cfg = devil_security_config();
    if (isset($cfg['security_block_disposable_emails']) && empty($cfg['security_block_disposable_emails'])) { return false; }
    $blocked = [
        'mailinator.com','tempmail.com','temp-mail.org','10minutemail.com','10minutemail.net','guerrillamail.com','guerrillamail.net','guerrillamail.org','sharklasers.com','grr.la','guerrillamailblock.com','yopmail.com','yopmail.fr','yopmail.net','maildrop.cc','getnada.com','inboxkitten.com','trashmail.com','trashmail.de','dispostable.com','fakeinbox.com','mailnesia.com','mohmal.com','mohmal.in','tempail.com','emailondeck.com','throwawaymail.com','mintemail.com','mytemp.email','tmpmail.org','tempmailo.com','tempmail.plus','tempinbox.com','burnermail.io','simplelogin.com','anonaddy.com','addy.io','duck.com','mail.tm','maxxspace.com','1secmail.com','1secmail.org','1secmail.net','wwjmp.com','esiix.com','xojxe.com','yoggm.com','rteet.com','dpptd.com','laafd.com','txcct.com','vjuum.com','mailto.plus','fexpost.com','fexbox.org','mailbox.in.ua','rover.info','chitthi.in','moakt.com','tmailor.com','tempmail.email','smailpro.com','emailfake.com','generator.email','mail-temp.com','linshiyouxiang.net','bccto.me','mailcatch.com','spamgourmet.com','spam4.me','spamdecoy.net','mailnull.com','mailforspam.com','dropmail.me','33mail.com','tempm.com','tmpeml.com'
    ];
    if (in_array($domain, $blocked, true)) { return true; }
    foreach ($blocked as $b) { if (str_ends_with($domain, '.' . $b)) { return true; } }
    return preg_match('/(temp|trash|disposable|throwaway|fake|guerrilla|mailinator|yopmail|10minute|1sec|burner|spam)/i', $domain) === 1;
}
function devil_security_trusted_signup_domain(string $domain): bool {
    $trusted = ['gmail.com','googlemail.com','outlook.com','hotmail.com','live.com','msn.com','yahoo.com','ymail.com','icloud.com','me.com','mac.com','proton.me','protonmail.com','pm.me','zoho.com','zohomail.com','aol.com'];
    $trusted = array_values(array_unique(array_merge($trusted, devil_security_domain_list('security_trusted_email_domains'))));
    return devil_security_domain_matches(strtolower($domain), $trusted);
}
function devil_security_email_auth_status(string $email, bool $existingUser): array {
    $domain = devil_security_email_domain($email);
    if ($domain === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { return [false, 'Please enter a valid email address.', 'invalid']; }
    if (filter_var($domain, FILTER_VALIDATE_IP) || devil_security_is_disposable_domain($domain)) {
        return [false, devil_security_signup_closed_message(), 'disposable'];
    }
    $cfg = devil_security_config();
    $blockSubdomains = !isset($cfg['security_block_subdomain_emails']) || !empty($cfg['security_block_subdomain_emails']);
    if ($blockSubdomains && !devil_security_is_registered_domain_like($domain) && !devil_security_trusted_signup_domain($domain)) {
        return [false, devil_security_signup_closed_message(), 'subdomain'];
    }
    return [true, '', 'ok'];
}
function devil_security_config(): array {
    $cfg = [];
    $base = devil_sec_root() . '/config.php';
    if (is_readable($base)) { $c = include $base; if (is_array($c)) { $cfg = $c; } }
    $runtime = devil_sec_json('config.json');
    return array_merge($cfg, $runtime);
}
function devil_security_recaptcha_site_key(): string {
    $cfg = devil_security_config();
    return !empty($cfg['security_require_recaptcha']) ? (string)($cfg['recaptcha_site_key'] ?? '') : '';
}
function devil_security_recaptcha_required(): bool {
    $cfg = devil_security_config();
    return !empty($cfg['security_require_recaptcha']) && (string)($cfg['recaptcha_secret_key'] ?? '') !== '';
}
function devil_security_verify_recaptcha(string $token, string $ip = ''): bool {
    $cfg = devil_security_config();
    $secret = (string)($cfg['recaptcha_secret_key'] ?? '');
    if ($secret === '') { return true; }
    if ($token === '') { return false; }
    $body = http_build_query(['secret' => $secret, 'response' => $token, 'remoteip' => $ip]);
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $body, 'timeout' => 8, 'ignore_errors' => true]]);
    $raw = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $ctx);
    if ($raw === false) { return false; }
    $j = json_decode($raw, true);
    if (!is_array($j) || empty($j['success'])) { return false; }
    if (isset($j['score']) && (float)$j['score'] < (float)($cfg['recaptcha_min_score'] ?? 0.45)) { return false; }
    return true;
}
