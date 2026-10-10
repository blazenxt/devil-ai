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
function gh_ensure_clone(array $cfg, string $sid, string $uid, array $link): array {
    $g = gh_load($uid);
    if (!$g) { return ['ok' => false, 'error' => 'GitHub is not connected (Settings → GitHub).']; }
    $repo = (string)($link['repo'] ?? ''); $branch = (string)($link['branch'] ?? '');
    if (!gh_valid_repo($repo) || !gh_valid_branch($branch)) { return ['ok' => false, 'error' => 'Invalid repository or branch.']; }
    $dir = gh_dirname($repo);
    $work = (string)($link['work_branch'] ?? '');
    if (!gh_valid_branch($work)) { $work = 'devil/' . substr(bin2hex(random_bytes(4)), 0, 8); }
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
