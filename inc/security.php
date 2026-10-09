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
function devil_sec_origin_key(): string {
    static $key = null;
    if ($key === null) {
        $f = __DIR__ . '/origin_key.php';
        $key = is_file($f) ? trim((string)(include $f)) : '';
    }
    return $key;
}
/* True only for requests forwarded by our Cloudflare Worker. When the deploy step has written
   inc/origin_key.php, the Worker must also present the matching secret X-Devil-Origin-Key header,
   so nobody can bypass Cloudflare and spoof the client-IP headers by hitting the server directly. */
function devil_sec_from_worker(): bool {
    if (strtolower((string)($_SERVER['HTTP_X_DEVIL_AI_PROXY'] ?? '')) !== 'cloudflare') { return false; }
    $key = devil_sec_origin_key();
    if ($key === '') { return true; }
    return hash_equals($key, (string)($_SERVER['HTTP_X_DEVIL_ORIGIN_KEY'] ?? ''));
}
function devil_sec_ip(): string {
    if (devil_sec_from_worker()) {
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
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    if (str_contains($uri, '/v1/') || str_ends_with($script, '/api.php')) { return true; }
    if (str_contains($accept, 'text/html')) { return false; }
    return str_contains($accept, 'application/json');
}
function devil_sec_vpn_proxy_message(): string {
    return 'Please disconnect VPN or proxy, then refresh Devil AI.';
}
function devil_sec_logo_data_uri(): string {
    $path = devil_sec_root() . '/assets/logo.svg';
    if (!is_readable($path)) { return ''; }
    $svg = (string)@file_get_contents($path);
    return $svg !== '' ? 'data:image/svg+xml;base64,' . base64_encode($svg) : '';
}
function devil_sec_block(string $message = 'Request blocked for security reasons.', int $status = 403): void {
    http_response_code($status);
    header('X-Robots-Tag: noindex');
    header('Cache-Control: no-store');
    if (devil_sec_is_json_request()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        $safe = htmlspecialchars($message, ENT_QUOTES);
        $logo = devil_sec_logo_data_uri();
        $logoHtml = $logo !== '' ? '<img src="' . htmlspecialchars($logo, ENT_QUOTES) . '" alt="Devil AI logo">' : '<span>Devil AI</span>';
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Security check — Devil AI</title><style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:radial-gradient(900px 420px at 70% -10%,rgba(244,63,94,.20),transparent 62%),#0c0709;color:#efe6ea;font-family:Segoe UI,system-ui,-apple-system,Roboto,sans-serif}.card{width:min(92vw,560px);padding:30px;border:1px solid rgba(244,63,94,.28);border-radius:24px;background:linear-gradient(180deg,rgba(255,255,255,.04),rgba(255,255,255,.015)),#171014;box-shadow:0 28px 80px rgba(0,0,0,.42)}.brand{display:flex;align-items:center;gap:12px;font-weight:900;font-size:1.15rem;margin-bottom:18px}.logo{width:46px;height:46px;border-radius:14px;display:grid;place-items:center;background:rgba(244,63,94,.10);box-shadow:0 0 26px rgba(244,63,94,.32);overflow:hidden}.logo img{width:42px;height:42px;display:block}h1{font-size:1.65rem;line-height:1.1;margin:0 0 10px}p{color:#b99aa5;line-height:1.65;margin:0 0 18px}.steps{border:1px solid rgba(244,63,94,.18);background:#100a0d;border-radius:16px;padding:14px 16px;color:#f5c8d0}.btn{display:inline-flex;margin-top:18px;padding:12px 16px;border-radius:12px;background:linear-gradient(135deg,#f43f5e,#be123c);color:white;text-decoration:none;font-weight:800}</style></head><body><main class="card"><div class="brand"><div class="logo">' . $logoHtml . '</div><span>Devil AI</span></div><h1>Security check</h1><p>' . $safe . '</p><div class="steps">If this looks wrong, wait a few minutes and reload the page. If it keeps happening, contact Devil AI support.</div><a class="btn" href="' . htmlspecialchars((string)($_SERVER['REQUEST_URI'] ?? '/'), ENT_QUOTES) . '">Refresh Devil AI</a></main></body></html>';
    }
    exit;
}
function devil_sec_rate(string $bucket, string $key, int $max, int $window): bool {
    /* One tiny file per visitor instead of one shared file holding every visitor: each request now
       reads/writes a few bytes instead of decoding and rewriting the whole map (that got slow as it grew). */
    if ($max <= 0) { return true; }
    $dir = devil_sec_data_dir() . '/security_rl';
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    $now = time();
    $fh = @fopen($dir . '/' . sha1($bucket . ':' . $key) . '.txt', 'c+');
    if (!$fh) { return true; }
    flock($fh, LOCK_EX);
    $hits = [];
    foreach (explode(',', (string)stream_get_contents($fh)) as $t) { $t = (int)$t; if ($t > $now - $window) { $hits[] = $t; } }
    $ok = count($hits) < $max;
    if ($ok) { $hits[] = $now; }
    ftruncate($fh, 0); rewind($fh); fwrite($fh, implode(',', $hits));
    flock($fh, LOCK_UN); fclose($fh);
    if (mt_rand(1, 400) === 1) {   /* now and then: drop visitors not seen for an hour */
        foreach (glob($dir . '/*.txt') ?: [] as $f) { if (@filemtime($f) < $now - max(3600, $window)) { @unlink($f); } }
        @unlink(devil_sec_data_dir() . '/security_rl.json');   /* the old shared file */
    }
    return $ok;
}
/* v2: the old file held bans made by the URL-probe check that also matched query strings
   (e.g. opening .gitignore in the agent workspace) — starting a fresh file drops all of those. */
function devil_sec_bans_file(): string { return 'security_bans_v3.json'; }
function devil_sec_ban_ip(string $ip, int $seconds, string $reason): void {
    $bans = devil_sec_json(devil_sec_bans_file());
    $now = time();
    foreach ($bans as $k => $b) { if (!is_array($b) || (int)($b['until'] ?? 0) <= $now) { unset($bans[$k]); } }
    $bans[$ip] = ['until' => $now + $seconds, 'reason' => $reason, 'ts' => $now];
    devil_sec_save(devil_sec_bans_file(), $bans);
}
function devil_sec_ban_message(): string {
    return 'Too many suspicious requests came from your network, so access is paused for a little while. Please try again later.';
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
function devil_sec_residential_org(string $org): bool {
    $org = trim($org);
    if ($org === '' || devil_sec_proxy_vpn_org($org)) { return false; }
    return preg_match('/(jio|reliance\s*jio|airtel|bharti|vodafone|idea|vi\s*india|bsnl|mtnl|hathway|excitel|act\s*fibernet|a\s*tria|alliance\s*broadband|railwire|railtel|asianet|siti\s*cable|den\s*networks|gtpl|you\s*broadband|tikona|spectra|tata\s*(play|teleservices)|broadband|telecom|telco|internet\s*service|cable|fiber|fibre|ftth|wireless|mobile|cellular|communications)/i', $org) === 1;
}
function devil_sec_proxy_vpn_asn(int $asn): bool {
    static $blocked = [13335,14618,16509,8075,15169,396982,14061,63949,20473,53667,16276,24940,9009,60068,62240,51167,31898,398101,12876,60781,28753,45102,132203,6939,202053,47583,20454,29802,29838,55286,29854,8100,35916,40676,46606,36352,55293,32244,399629];
    return $asn > 0 && in_array($asn, $blocked, true);
}
function devil_sec_proxy_header_present(): string {
    if (devil_sec_from_worker()) { return ''; }
    $headers = ['HTTP_X_PROXY_ID','HTTP_X_PROXY_AUTHORIZATION','HTTP_PROXY_AUTHORIZATION','HTTP_PROXY_CONNECTION'];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) { return $h; }
    }
    return '';
}
function devil_sec_proxy_vpn_reason(string $ip, bool $trustedDeveloperApi): string {
    if ($trustedDeveloperApi) { return ''; }
    $proxyHeader = devil_sec_proxy_header_present();
    if ($proxyHeader !== '') { return 'proxy header'; }

    $country = strtoupper(trim((string)((devil_sec_from_worker() ? ($_SERVER['HTTP_X_DEVIL_CLIENT_COUNTRY'] ?? '') : ($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '')))));
    if ($country === 'T1') { return 'tor/proxy network'; }

    $org = (string)((devil_sec_from_worker() ? ($_SERVER['HTTP_X_DEVIL_CLIENT_ASO'] ?? '') : ($_SERVER['HTTP_CF_ASORGANIZATION'] ?? '')));
    if (devil_sec_residential_org($org)) { return ''; }

    $threatRaw = (string)((devil_sec_from_worker() ? ($_SERVER['HTTP_X_DEVIL_CLIENT_THREAT'] ?? '') : ($_SERVER['HTTP_CF_THREAT_SCORE'] ?? '')));
    if ($threatRaw !== '' && is_numeric($threatRaw) && (float)$threatRaw >= 80) { return 'high risk IP reputation'; }

    $asnRaw = (string)((devil_sec_from_worker() ? ($_SERVER['HTTP_X_DEVIL_CLIENT_ASN'] ?? '') : ($_SERVER['HTTP_CF_ASN'] ?? '')));
    $asn = ctype_digit($asnRaw) ? (int)$asnRaw : 0;
    if (devil_sec_proxy_vpn_asn($asn)) { return 'blocked ASN'; }

    if (devil_sec_proxy_vpn_org($org)) { return 'blocked network'; }

    if (devil_sec_datacenter_like($ip, false)) { return 'blocked datacenter range'; }
    $fromCloudflareWorker = devil_sec_from_worker();
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

    $bans = devil_sec_json(devil_sec_bans_file());
    if (isset($bans[$ip]) && is_array($bans[$ip]) && (int)($bans[$ip]['until'] ?? 0) > time()) {
        devil_sec_block(devil_sec_ban_message(), 403);
    }

    /* scanner probes: look at the URL PATH only. The query string carries user data — e.g. the agent
       workspace opens api.php?action=sbx_file&path=.gitignore — and must never trigger a ban. */
    $path = (string)(parse_url($uri, PHP_URL_PATH) ?? '');
    if (!$isApi && preg_match('#(/\.env|/wp-login|/wp-admin|xmlrpc\.php|phpmyadmin|adminer|/\.git(/|$)|/composer\.(json|lock)|/vendor/|/config\.php|\.sql$|/etc/passwd|\.DS_Store)#i', $path)) {
        devil_sec_ban_ip($ip, 600, 'probe');
        devil_sec_block('Security probe blocked.', 403);
    }

    $limit = $isApi ? 180 : 240;
    if (!devil_sec_rate('all', $ip, $limit, 60)) {
        /* this request only — no IP ban: on mobile networks (CGNAT) one IP is shared by many people */
        devil_sec_block('Too many requests. Please wait a moment and try again.', 429);
    }

    $hasApiKey = preg_match('/Bearer\s+(?:devil_blazenxt_|dv_live_)/i', (string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''))
        || !empty($_SERVER['HTTP_X_DEVIL_API_KEY']) || !empty($_GET['key']) || !empty($_GET['api_key']) || !empty($_GET['apikey']);
    $trustedDeveloperApi = (str_contains($uri, '/v1/') || preg_match('#/v1$#', $uri) === 1) && $hasApiKey;
    /* VPN / proxy / datacenter screening now happens at Cloudflare's edge as a Managed Challenge
       (a real person passes it in a second, bots do not). The old IP-reputation hard block here
       produced false positives on normal home and mobile networks, so it is off. */
    $badUa = $ua === '' || preg_match('/(python-requests|scrapy|curl|wget|httpclient|libwww|go-http-client|java\/|okhttp|node-fetch|axios|phantomjs|headless|selenium|playwright|puppeteer|nikto|sqlmap|nmap|masscan|zgrab|crawler|spider|\bbot\b)/i', $ua) === 1;
    $noBrowserHints = empty($_SERVER['HTTP_ACCEPT_LANGUAGE']) && empty($_SERVER['HTTP_SEC_CH_UA']) && !$hasApiKey;

    if (!$isApi && $badUa) {
        devil_sec_block('Automated scraping is blocked.', 403);   /* block the request, never ban the (possibly shared) IP */
    }
    if (!$isApi && $noBrowserHints && devil_sec_datacenter_like($ip, true)) {
        devil_sec_block('Automated access is blocked.', 403);
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
function devil_security_turnstile_site_key(): string {
    $cfg = devil_security_config();
    return (string)($cfg['turnstile_site_key'] ?? '');
}
function devil_security_turnstile_required(): bool {
    $cfg = devil_security_config();
    return (string)($cfg['turnstile_secret_key'] ?? '') !== '' && (string)($cfg['turnstile_site_key'] ?? '') !== '';
}
function devil_security_verify_turnstile(string $token, string $ip = ''): bool {
    $cfg = devil_security_config();
    $secret = (string)($cfg['turnstile_secret_key'] ?? '');
    if ($secret === '') { return false; } /* fail closed — never pass when unconfigured */
    if ($token === '') { return false; }
    $body = http_build_query(['secret' => $secret, 'response' => $token, 'remoteip' => $ip]);
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $body, 'timeout' => 8, 'ignore_errors' => true]]);
    $raw = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $ctx);
    if ($raw === false) { return false; }
    $j = json_decode($raw, true);
    return is_array($j) && !empty($j['success']);
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
    if ($secret === '') { return false; } /* fail closed — never pass when unconfigured */
    if ($token === '') { return false; }
    $body = http_build_query(['secret' => $secret, 'response' => $token, 'remoteip' => $ip]);
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $body, 'timeout' => 8, 'ignore_errors' => true]]);
    $raw = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $ctx);
    if ($raw === false) { return false; }
    $j = json_decode($raw, true);
    $GLOBALS['devil_recaptcha_last'] = is_array($j) ? $j : [];
    if (!is_array($j) || empty($j['success'])) { return false; }
    if (isset($j['score']) && (float)$j['score'] < (float)($cfg['recaptcha_min_score'] ?? 0.45)) { return false; }
    return true;
}

/* Optional visible reCAPTCHA v2 checkbox — used as the fallback when the
   invisible v3 verification fails. Verified with its own secret key. */
function devil_security_recaptcha_v2_site_key(): string {
    $cfg = devil_security_config();
    return !empty($cfg['security_require_recaptcha']) ? (string)($cfg['recaptcha_v2_site_key'] ?? '') : '';
}
function devil_security_verify_recaptcha_v2(string $token, string $ip = ''): bool {
    $cfg = devil_security_config();
    $secret = (string)($cfg['recaptcha_v2_secret_key'] ?? '');
    if ($secret === '') { return false; }
    if ($token === '') { return false; }
    $body = http_build_query(['secret' => $secret, 'response' => $token, 'remoteip' => $ip]);
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $body, 'timeout' => 8, 'ignore_errors' => true]]);
    $raw = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $ctx);
    if ($raw === false) { return false; }
    $j = json_decode($raw, true);
    return is_array($j) && !empty($j['success']);
}
