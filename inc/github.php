<?php
/**
 * GitHub connector for Agent Mode.
 *   Settings → GitHub: the user saves a personal access token (stored encrypted, like hosting passwords).
 *   Agent composer: pick a repo + branch → the repo is cloned into the chat's sandbox on the next run,
 *   the agent works on its own branch (devil/<id>), the Diff tab shows the changes (last turn / full branch),
 *   and the github_pr tool commits, pushes and opens (or updates) a pull request.
 */
declare(strict_types=1);

function gh_dir(): string {
    $d = dirname(__DIR__) . '/data/github';
    if (!is_dir($d)) { @mkdir($d, 0700, true); @file_put_contents($d . '/.htaccess', "Require all denied\n"); }
    return $d;
}
function gh_file(string $uid): string { return gh_dir() . '/' . preg_replace('/[^A-Za-z0-9_-]/', '', $uid) . '.json'; }

/** saved account with the token decrypted, or null */
function gh_load(string $uid): ?array {
    $f = gh_file($uid);
    if ($uid === '' || !is_file($f)) { return null; }
    $j = json_decode((string)@file_get_contents($f), true);
    if (!is_array($j) || empty($j['token_enc'])) { return null; }
    if (!function_exists('host_decrypt')) { require_once __DIR__ . '/hosting.php'; }
    $j['token'] = host_decrypt((string)$j['token_enc']);
    unset($j['token_enc']);
    return $j['token'] !== '' ? $j : null;
}
function gh_public(?array $g): ?array {
    if (!$g) { return null; }
    return ['login' => (string)($g['login'] ?? ''), 'name' => (string)($g['name'] ?? ''), 'avatar' => (string)($g['avatar'] ?? ''), 'updated' => (int)($g['updated'] ?? 0)];
}
function gh_save(string $uid, string $token, array $user): bool {
    if (!function_exists('host_encrypt')) { require_once __DIR__ . '/hosting.php'; }
    $j = ['login' => (string)($user['login'] ?? ''), 'name' => (string)($user['name'] ?? ''), 'avatar' => (string)($user['avatar_url'] ?? ''),
        'token_enc' => host_encrypt($token), 'updated' => time()];
    $f = gh_file($uid);
    $ok = @file_put_contents($f . '.tmp', json_encode($j), LOCK_EX) !== false && @rename($f . '.tmp', $f);
    @chmod($f, 0600);
    return $ok;
}
function gh_delete(string $uid): void { @unlink(gh_file($uid)); }

/** GitHub REST call → [status, json|null] */
function gh_api(string $token, string $method, string $path, ?array $body = null, int $timeout = 20): array {
    $ch = curl_init('https://api.github.com' . $path);
    $h = ['Accept: application/vnd.github+json', 'Authorization: Bearer ' . $token, 'X-GitHub-Api-Version: 2022-11-28', 'User-Agent: DevilAI'];
    $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $h];
    if ($body !== null) { $o[CURLOPT_POSTFIELDS] = json_encode($body); $o[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json'; }
    curl_setopt_array($ch, $o);
    $raw = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $j = is_string($raw) ? json_decode($raw, true) : null;
    return [$code, is_array($j) ? $j : null];
}

function gh_valid_repo(string $r): bool { return (bool)preg_match('~^[A-Za-z0-9_.-]{1,100}/[A-Za-z0-9_.-]{1,100}$~', $r); }
function gh_valid_branch(string $b): bool { return $b !== '' && strlen($b) <= 200 && (bool)preg_match('~^[A-Za-z0-9._/-]+$~', $b) && strpos($b, '..') === false; }

function gh_repos(string $token, string $q = ''): array {
    $out = [];
    for ($page = 1; $page <= 3; $page++) {
        [$c, $j] = gh_api($token, 'GET', '/user/repos?per_page=100&sort=pushed&affiliation=owner,collaborator,organization_member&page=' . $page);
        if ($c !== 200 || !is_array($j)) { break; }
        foreach ($j as $r) {
            if (!is_array($r) || empty($r['full_name'])) { continue; }
            if (empty($r['permissions']['push'])) { continue; }
            if ($q !== '' && stripos((string)$r['full_name'], $q) === false) { continue; }
            $out[] = ['repo' => (string)$r['full_name'], 'private' => !empty($r['private']), 'branch' => (string)($r['default_branch'] ?? 'main'), 'pushed' => (string)($r['pushed_at'] ?? '')];
        }
        if (count($j) < 100) { break; }
    }
    return $out;
}
function gh_branches(string $token, string $repo): array {
    [$c, $j] = gh_api($token, 'GET', '/repos/' . $repo . '/branches?per_page=100');
    if ($c !== 200 || !is_array($j)) { return []; }
    return array_values(array_filter(array_map(static function ($b) { return is_array($b) ? (string)($b['name'] ?? '') : ''; }, $j), 'strlen'));
}

/** folder of the repo inside the sandbox work dir */
function gh_dirname(string $repo): string { return preg_replace('/[^A-Za-z0-9_.-]/', '-', (string)substr($repo, (int)strpos($repo, '/') + 1)) ?: 'repo'; }

/** git helper prefix: credentials come from $GITHUB_TOKEN (saved as a sandbox secret), never from the command line */
function gh_git_env(): string {
    return 'export GIT_TERMINAL_PROMPT=0; git config --global credential.helper \'!f() { echo username=x-access-token; echo "password=$GITHUB_TOKEN"; }; f\' 2>/dev/null; '
        . 'git config --global user.name >/dev/null 2>&1 || git config --global user.name "Devil Agent"; git config --global user.email >/dev/null 2>&1 || git config --global user.email "agent@devil.ai"; ';
}

/** snapshot of the working tree (tracked + untracked, ignoring .gitignore'd files) as a git tree id — no commit, no history change */
function gh_tree_cmd(string $dir): string {
    return 'cd ' . escapeshellarg(SBX_WORKDIR . '/' . $dir) . ' && export GIT_INDEX_FILE=.git/devil-snap-idx && rm -f "$GIT_INDEX_FILE" && git read-tree HEAD 2>/dev/null; git add -A . >/dev/null 2>&1 && git write-tree';
}

/** make sure the linked repo is cloned in the sandbox; returns the link with dir/work branch/base filled in */
function gh_ensure_clone(array $cfg, string $sid, string $uid, array $link, string $msg = ''): array {
    $g = gh_load($uid);
    if (!$g) { return ['ok' => false, 'error' => 'GitHub is not connected (Settings → GitHub).']; }
    $repo = (string)($link['repo'] ?? ''); $branch = (string)($link['branch'] ?? '');
    if (!gh_valid_repo($repo) || !gh_valid_branch($branch)) { return ['ok' => false, 'error' => 'Invalid repository or branch.']; }
    $dir = gh_dirname($repo);
    $work = (string)($link['work_branch'] ?? '');
    if (!gh_valid_branch($work)) { $work = gh_work_branch($msg); }
    /* token → sandbox secret GITHUB_TOKEN (masked in every tool output) */
    if (function_exists('sbx_secret_set')) { sbx_secret_set($cfg, $sid, 'GITHUB_TOKEN', (string)$g['token']); }
    $D = escapeshellarg($dir);
    $auth = base64_encode('x-access-token:' . $g['token']);
    $cmd = 'cd ' . escapeshellarg(SBX_WORKDIR) . ' && ' . gh_git_env()
        . 'if [ -d ' . $D . '/.git ]; then echo HAVE; else '
        . 'git -c http.extraheader="Authorization: Basic ' . $auth . '" clone -q --depth 50 --branch ' . escapeshellarg($branch) . ' ' . escapeshellarg('https://github.com/' . $repo . '.git') . ' ' . $D . ' 2>&1 | tail -5 && '
        . 'cd ' . $D . ' && git checkout -q -b ' . escapeshellarg($work) . ' && echo CLONED; fi; '
        . 'cd ' . escapeshellarg(SBX_WORKDIR) . '/' . $D . ' 2>/dev/null && echo "BASE $(git rev-parse origin/' . $branch . ' 2>/dev/null || git rev-parse HEAD)" && echo "CUR $(git rev-parse --abbrev-ref HEAD)"';
    $r = sbx_exec($cfg, $sid, $cmd, 90);
    $out = (string)($r['stdout'] ?? '') . (string)($r['stderr'] ?? '');
    if (strpos($out, 'HAVE') === false && strpos($out, 'CLONED') === false) {
        return ['ok' => false, 'error' => 'Could not clone ' . $repo . ': ' . mb_substr(trim(str_replace($g['token'], '***', $out)), 0, 300)];
    }
    if (preg_match('/^BASE ([0-9a-f]{40})/m', $out, $m)) { $link['base'] = $m[1]; }
    if (preg_match('/^CUR (\S+)/m', $out, $m) && $m[1] !== 'HEAD') { $work = $m[1]; }
    $link['dir'] = $dir; $link['work_branch'] = $work; $link['cloned'] = 1;
    return ['ok' => true, 'link' => $link, 'fresh' => strpos($out, 'CLONED') !== false];
}

/** tree id of the current working state (for "last turn" diffs) */
function gh_snapshot(array $cfg, string $sid, string $dir): string {
    $r = sbx_exec($cfg, $sid, gh_tree_cmd($dir), 40);
    return preg_match('/^([0-9a-f]{40})\s*$/m', (string)($r['stdout'] ?? ''), $m) ? $m[1] : '';
}

/** unified diff: $mode 'turn' (since $fromTree) or 'full' (since the base commit) */
function gh_diff(array $cfg, string $sid, array $link, string $mode, string $fromTree = ''): array {
    $dir = (string)($link['dir'] ?? '');
    if ($dir === '') { return ['ok' => false, 'error' => 'No repository in this chat yet.']; }
    $from = $mode === 'turn' && preg_match('/^[0-9a-f]{40}$/', $fromTree) ? $fromTree : (preg_match('/^[0-9a-f]{40}$/', (string)($link['base'] ?? '')) ? (string)$link['base'] : 'HEAD');
    $cmd = gh_tree_cmd($dir) . ' > .git/devil-now 2>/dev/null; T=$(cat .git/devil-now); '
        . 'echo "__STAT__"; git diff --stat=200 ' . escapeshellarg($from) . ' "$T" | tail -40; echo "__DIFF__"; git diff -M ' . escapeshellarg($from) . ' "$T" | head -c 300000';
    $r = sbx_exec($cfg, $sid, $cmd, 40);
    $out = (string)($r['stdout'] ?? '');
    $k = strpos($out, "__DIFF__\n");
    $stat = trim((string)substr($out, (int)strpos($out, "__STAT__\n") + 9, $k !== false ? $k - strpos($out, "__STAT__\n") - 9 : 0));
    $diff = $k !== false ? substr($out, $k + 9) : '';
    return ['ok' => true, 'mode' => $mode, 'stat' => $stat, 'diff' => $diff, 'truncated' => strlen($diff) >= 300000];
}

/** github_pr tool: commit everything on the work branch, push, open (or update) the pull request */
function gh_tool_pr(array $cfg, string $sid, string $uid, array $link, string $input): array {
    $g = gh_load($uid);
    if (!$g) { return ['ok' => false, 'text' => 'GitHub is not connected. Ask the user to connect it in Settings → GitHub.']; }
    $dir = (string)($link['dir'] ?? ''); $repo = (string)($link['repo'] ?? ''); $base = (string)($link['branch'] ?? ''); $work = (string)($link['work_branch'] ?? '');
    if ($dir === '' || !gh_valid_repo($repo) || !gh_valid_branch($work)) { return ['ok' => false, 'text' => 'No GitHub repository is connected to this chat.']; }
    if (!empty($link['pr'])) {
        $pi = gh_pr_info((string)$g['token'], $link);
        if ($pi && $pi['state'] !== 'open') {
            return ['ok' => false, 'text' => 'The pull request ' . $link['pr'] . ' was already ' . $pi['state'] . ', so this chat can no longer push. Start a new chat for new work on this repository.'];
        }
    }
    $lines = preg_split('/\r?\n/', trim($input), 2);
    $title = trim((string)($lines[0] ?? '')) ?: 'Changes by Devil Agent';
    $title = mb_substr(preg_replace('/^(title\s*:\s*)/i', '', $title), 0, 200);
    $body = trim((string)($lines[1] ?? ''));
    if (function_exists('sbx_secret_set')) { sbx_secret_set($cfg, $sid, 'GITHUB_TOKEN', (string)$g['token']); }
    $cmd = sbx_env_prefix($cfg, $sid) . "\n" . gh_git_env() . 'cd ' . escapeshellarg(SBX_WORKDIR . '/' . $dir) . ' && git add -A && '
        . '(git diff --cached --quiet && echo NOCHANGE || git commit -q -m ' . escapeshellarg($title) . ' && echo COMMITTED); '
        . 'git push -q -u origin ' . escapeshellarg($work) . ' 2>&1 | tail -5; echo "PUSH_RC=${PIPESTATUS[0]}"; git log --oneline -1';
    $r = sbx_exec($cfg, $sid, $cmd, 80);
    $out = str_replace((string)$g['token'], '***', (string)($r['stdout'] ?? '') . (string)($r['stderr'] ?? ''));
    if (!preg_match('/PUSH_RC=0/', $out)) { return ['ok' => false, 'text' => "Push failed:\n" . mb_substr(trim($out), 0, 1200)]; }
    $owner = substr($repo, 0, (int)strpos($repo, '/'));
    [$c, $j] = gh_api((string)$g['token'], 'GET', '/repos/' . $repo . '/pulls?state=open&head=' . rawurlencode($owner . ':' . $work));
    $pr = (is_array($j) && isset($j[0]['html_url'])) ? $j[0] : null;
    if ($pr) {
        if ($body !== '') { gh_api((string)$g['token'], 'PATCH', '/repos/' . $repo . '/pulls/' . (int)$pr['number'], ['title' => $title, 'body' => $body]); }
        return ['ok' => true, 'text' => 'Pushed to branch ' . $work . ' and updated pull request #' . (int)$pr['number'] . ': ' . $pr['html_url'], 'meta' => ['pr' => (string)$pr['html_url'], 'branch' => $work]];
    }
    [$c, $j] = gh_api((string)$g['token'], 'POST', '/repos/' . $repo . '/pulls', ['title' => $title, 'head' => $work, 'base' => $base, 'body' => $body !== '' ? $body : 'Opened by Devil Agent.']);
    if ($c === 201 && !empty($j['html_url'])) {
        return ['ok' => true, 'text' => 'Pushed branch ' . $work . ' and opened pull request #' . (int)$j['number'] . ': ' . $j['html_url'], 'meta' => ['pr' => (string)$j['html_url'], 'branch' => $work]];
    }
    $why = is_array($j) ? (string)($j['message'] ?? '') . (isset($j['errors'][0]['message']) ? ' — ' . $j['errors'][0]['message'] : '') : 'HTTP ' . $c;
    return ['ok' => false, 'text' => 'Pushed branch ' . $work . ', but the pull request could not be opened: ' . mb_substr($why, 0, 300) . '. (If there are no changes compared to ' . $base . ', there is nothing to review.)', 'meta' => ['branch' => $work]];
}

/** remember the PR link on the chat so the Diff tab can show it */
function gh_remember_pr(string $uid, array $link, string $url): void {
    $cid = (string)($link['chat_id'] ?? '');
    if ($cid === '' || !function_exists('load_chat')) { return; }
    $c = load_chat($uid, $cid);
    if (!$c || !is_array($c['github'] ?? null)) { return; }
    $c['github']['pr'] = $url;
    save_chat($uid, $c);
}

/* ───────────── one-click connect (GitHub OAuth) ─────────────
   Uses github_repo_client_id/secret from the main config, or the same OAuth app as "Sign in with GitHub".
   The callback is the existing /auth/github/callback; repo-connect states start with "repo." */
function gh_oauth_cfg(): array {
    $c = function_exists('load_config') ? load_config() : (json_decode((string)@file_get_contents(dirname(__DIR__) . '/data/config.json'), true) ?: []);
    $id = (string)($c['github_repo_client_id'] ?? ''); $sec = (string)($c['github_repo_client_secret'] ?? '');
    if (($id === '' || $sec === '') && function_exists('devil_security_config')) {
        $s = devil_security_config();
        $id = (string)($s['github_client_id'] ?? ''); $sec = (string)($s['github_client_secret'] ?? '');
    }
    return ($id !== '' && $sec !== '') ? ['id' => $id, 'secret' => $sec] : [];
}
function gh_oauth_callback_url(): string {
    $h = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($h === 'ai.devil.blazenxt.in') { return 'https://ai.devil.blazenxt.in/auth/github/callback'; }
    if (preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $h)) { return 'http://' . $h . '/auth/github/callback'; }
    return 'https://ai.devil.blazenxt.com/auth/github/callback';
}
function gh_b64u(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function gh_oauth_state(string $uid): string {
    if (!function_exists('host_key')) { require_once __DIR__ . '/hosting.php'; }
    $p = gh_b64u((string)json_encode(['u' => $uid, 'e' => time() + 900, 'n' => bin2hex(random_bytes(8))]));
    return 'repo.' . $p . '.' . gh_b64u(hash_hmac('sha256', 'gh-oauth|' . $p, host_key(), true));
}
/** uid from a valid, unexpired, unused state — or '' */
function gh_oauth_check(string $state): string {
    if (!function_exists('host_key')) { require_once __DIR__ . '/hosting.php'; }
    if (!preg_match('/^repo\.([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]+)$/', $state, $m)) { return ''; }
    if (!hash_equals(gh_b64u(hash_hmac('sha256', 'gh-oauth|' . $m[1], host_key(), true)), $m[2])) { return ''; }
    $j = json_decode((string)base64_decode(strtr($m[1], '-_', '+/')), true);
    if (!is_array($j) || (int)($j['e'] ?? 0) < time() || empty($j['u']) || empty($j['n'])) { return ''; }
    $f = gh_dir() . '/.nonces.json';
    $fh = @fopen($f, 'c+');
    if ($fh) { flock($fh, LOCK_EX); }
    $used = json_decode((string)@file_get_contents($f), true); if (!is_array($used)) { $used = []; }
    foreach ($used as $k => $t) { if ($t < time()) { unset($used[$k]); } }
    if (isset($used[$j['n']])) { if ($fh) { flock($fh, LOCK_UN); fclose($fh); } return ''; }
    $used[$j['n']] = time() + 1000;
    @file_put_contents($f, json_encode($used));
    if ($fh) { flock($fh, LOCK_UN); fclose($fh); }
    return (string)$j['u'];
}
function gh_oauth_url(string $uid): string {
    $o = gh_oauth_cfg();
    if (!$o) { return ''; }
    return 'https://github.com/login/oauth/authorize?' . http_build_query(['client_id' => $o['id'], 'redirect_uri' => gh_oauth_callback_url(), 'scope' => 'repo read:user', 'state' => gh_oauth_state($uid), 'allow_signup' => 'true']);
}
/** finish the OAuth dance → ['ok'=>bool,'error'=>..,'login'=>..] */
function gh_oauth_finish(string $code, string $state): array {
    $uid = gh_oauth_check($state);
    if ($uid === '') { return ['ok' => false, 'error' => 'This GitHub connection link expired. Please try again from Devil AI.']; }
    $o = gh_oauth_cfg();
    if (!$o || $code === '') { return ['ok' => false, 'error' => 'GitHub did not send an authorization code.']; }
    $ch = curl_init('https://github.com/login/oauth/access_token');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: DevilAI'],
        CURLOPT_POSTFIELDS => http_build_query(['client_id' => $o['id'], 'client_secret' => $o['secret'], 'code' => $code, 'redirect_uri' => gh_oauth_callback_url()])]);
    $raw = curl_exec($ch); curl_close($ch);
    $j = is_string($raw) ? json_decode($raw, true) : null;
    $tok = is_array($j) ? (string)($j['access_token'] ?? '') : '';
    if ($tok === '') { return ['ok' => false, 'error' => 'GitHub refused the connection' . (is_array($j) && !empty($j['error_description']) ? ': ' . $j['error_description'] : '.')]; }
    [$c, $u] = gh_api($tok, 'GET', '/user');
    if ($c !== 200 || empty($u['login'])) { return ['ok' => false, 'error' => 'Could not read your GitHub profile.']; }
    if (!gh_save($uid, $tok, $u)) { return ['ok' => false, 'error' => 'Could not save the connection.']; }
    return ['ok' => true, 'login' => (string)$u['login']];
}
/** the small page shown in the popup after GitHub sends the user back */
function gh_oauth_page(array $r): void {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    $ok = !empty($r['ok']);
    $msg = $ok ? 'GitHub connected as @' . htmlspecialchars((string)$r['login']) . '. You can close this window.' : htmlspecialchars((string)($r['error'] ?? 'Something went wrong.'));
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>GitHub — Devil AI</title>'
        . '<body style="font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:90vh;background:#faf9f7;color:#222">'
        . '<div style="max-width:420px;text-align:center;padding:24px"><div style="font-size:40px">' . ($ok ? '✓' : '⚠') . '</div><p style="font-size:16px;line-height:1.5">' . $msg . '</p>'
        . '<a href="/agent" style="color:#222">Back to Devil AI</a></div>'
        . '<script>try{if(window.opener){window.opener.postMessage({type:"devil-github",ok:' . ($ok ? 'true' : 'false') . '},"*");' . ($ok ? 'setTimeout(function(){window.close()},900);' : '') . '}}catch(e){}</script></body>';
    exit;
}

/* ───────────── session status / PR lifecycle ───────────── */
/** +added / −removed lines on the working branch (base … working tree) */
function gh_numstat(array $cfg, string $sid, array $link): array {
    $dir = (string)($link['dir'] ?? '');
    if ($dir === '') { return ['add' => 0, 'del' => 0, 'files' => 0]; }
    $base = preg_match('/^[0-9a-f]{40}$/', (string)($link['base'] ?? '')) ? (string)$link['base'] : 'HEAD';
    $r = sbx_exec($cfg, $sid, gh_tree_cmd($dir) . ' > .git/devil-now 2>/dev/null; git diff --numstat ' . escapeshellarg($base) . ' "$(cat .git/devil-now)" | head -2000', 30);
    $a = 0; $d = 0; $f = 0;
    foreach (preg_split('/\n/', (string)($r['stdout'] ?? '')) as $ln) {
        if (preg_match('/^(\d+|-)\t(\d+|-)\t/', $ln, $m)) { $f++; $a += (int)$m[1]; $d += (int)$m[2]; }
    }
    return ['add' => $a, 'del' => $d, 'files' => $f];
}
/** live PR state from GitHub: open / merged / closed (+ number, mergeable) */
function gh_pr_info(string $token, array $link): ?array {
    if (!preg_match('~^https://github\.com/([^/]+/[^/]+)/pull/(\d+)~', (string)($link['pr'] ?? ''), $m)) { return null; }
    [$c, $j] = gh_api($token, 'GET', '/repos/' . $m[1] . '/pulls/' . $m[2], null, 12);
    if ($c !== 200 || !is_array($j)) { return ['number' => (int)$m[2], 'state' => (string)($link['pr_state'] ?? 'open'), 'url' => (string)$link['pr']]; }
    return ['number' => (int)$m[2], 'url' => (string)$link['pr'], 'title' => (string)($j['title'] ?? ''),
        'state' => !empty($j['merged']) ? 'merged' : ((string)($j['state'] ?? 'open') === 'closed' ? 'closed' : 'open'),
        'mergeable' => $j['mergeable'] ?? null, 'draft' => !empty($j['draft'])];
}
function gh_pr_merge(string $token, array $link, string $method = 'squash'): array {
    if (!preg_match('~^https://github\.com/([^/]+/[^/]+)/pull/(\d+)~', (string)($link['pr'] ?? ''), $m)) { return ['ok' => false, 'error' => 'No pull request yet.']; }
    if (!in_array($method, ['merge', 'squash', 'rebase'], true)) { $method = 'squash'; }
    [$c, $j] = gh_api($token, 'PUT', '/repos/' . $m[1] . '/pulls/' . $m[2] . '/merge', ['merge_method' => $method]);
    if ($c === 200 && !empty($j['merged'])) { return ['ok' => true, 'sha' => (string)($j['sha'] ?? '')]; }
    return ['ok' => false, 'error' => 'GitHub could not merge it: ' . (is_array($j) ? (string)($j['message'] ?? ('HTTP ' . $c)) : ('HTTP ' . $c))];
}
/** branch name for a new session: devil/<id>-<words of the request> */
function gh_work_branch(string $msg): string {
    $slug = strtolower(trim((string)preg_replace('/[^A-Za-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $msg) ?: ''), '-'));
    $slug = trim(substr(implode('-', array_slice(array_filter(explode('-', $slug)), 0, 5)), 0, 32), '-');
    return 'devil/' . substr(bin2hex(random_bytes(4)), 0, 8) . ($slug !== '' ? '-' . $slug : '');
}
