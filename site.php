<?php
/**
 * ═══════════════════════════════════════════════════════
 *  DEVIL AI — Pro-style site pages (site.php)
 *  /leaderboard[/text|/my-votes]  /history/search  /how-it-works  /faq
 *  /blog[/slug]  /company/about  /company/careers
 *  /privacy-policy  /terms-of-use  /cookie-policy
 * ═══════════════════════════════════════════════════════
 */
require_once __DIR__ . '/inc/site_shell.php';
devil_session_boot();
require_once __DIR__ . '/inc/site_content.php';

$P = (string)($_GET['p'] ?? '');

/* /site.php?p=… must never show up in the address bar */
$reqPath = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
if (preg_match('~/site\.php$~i', $reqPath) && in_array(($_SERVER['REQUEST_METHOD'] ?? 'GET'), ['GET', 'HEAD'], true)) {
    $pretty = ['leaderboard' => 'leaderboard', 'search' => 'history/search', 'about' => 'company/about', 'careers' => 'company/careers'];
    $to = site_base() . '/' . ($pretty[$P] ?? $P);
    if ($P === 'leaderboard' && !empty($_GET['board'])) { $to .= '/' . rawurlencode((string)$_GET['board']); }
    if ($P === 'blog' && !empty($_GET['post'])) { $to .= '/' . rawurlencode((string)$_GET['post']); }
    $q = $_GET; unset($q['p'], $q['board'], $q['post']);
    header('Location: ' . $to . ($q ? '?' . http_build_query($q) : ''), true, 301);
    exit;
}
header('Cache-Control: private, no-cache');

function site_404(): void {
    http_response_code(404);
    site_head('Page not found', '');
    echo '<div class="hero sm"><span class="eyebrow">404</span><h1>Page not found</h1><p class="lead">This page does not exist or was moved.</p><p><a class="btn primary" href="./">Go to chat</a></p></div>';
    site_foot();
    exit;
}

function site_ago(int $t): string {
    $d = max(0, time() - $t);
    if ($d < 60) { return 'just now'; }
    if ($d < 3600) { return floor($d / 60) . 'm ago'; }
    if ($d < 86400) { return floor($d / 3600) . 'h ago'; }
    if ($d < 86400 * 30) { return floor($d / 86400) . 'd ago'; }
    return date('M j, Y', $t);
}

function site_model_ico(string $ic): string {
    if (in_array($ic, ['zap', 'sparkles', 'crown', 'layers'], true)) { return '<span class="mico">' . icon($ic, 14) . '</span>'; }
    return '<span class="mico"><img src="assets/logo.svg" alt=""></span>';
}

function site_api(): void {
    if (!defined('DEVIL_API_AS_LIB')) { define('DEVIL_API_AS_LIB', true); }
    require_once __DIR__ . '/api.php';
}

/* ─────────────────────────── LEADERBOARD ─────────────────────────── */
function page_leaderboard(string $board): void {
    site_api();
    $me = site_me();
    $lb = battle_leaderboard($me ? (string)$me['id'] : '');
    $models = $lb['models'];
    $ranked = array_values(array_filter($models, function ($m) { return $m['votes'] > 0; }));
    $total = (int)$lb['total'];
    $raw = is_readable(battle_votes_path()) ? json_decode((string)file_get_contents(battle_votes_path()), true) : [];
    $raw = is_array($raw) ? $raw : [];
    $today = 0; foreach ($raw as $v) { if ((int)($v['t'] ?? 0) >= time() - 86400) { $today++; } }
    $labels = []; foreach ($models as $m) { $labels[$m['id']] = $m; }

    $boards = ['' => 'Overview', 'text' => 'Text Battle', 'my-votes' => 'My votes'];
    if (!isset($boards[$board])) { site_404(); }
    site_head(($board === '' ? 'Leaderboard' : $boards[$board] . ' Leaderboard'), 'leaderboard', 'Devil AI Leaderboard: AI models ranked by anonymous head-to-head Battle votes.');
    echo '<div class="hero lbhero"><span class="eyebrow">' . icon('trophy', 13) . ' Leaderboard</span><h1>Which model wins?</h1><p class="lead">Models ranked by <b>' . number_format($total) . '</b> anonymous Battle vote' . ($total === 1 ? '' : 's') . '. Names stay hidden until after the vote.</p>'
        . '<div class="hero-acts"><a class="btn primary" href="battle">' . icon('swords', 15) . ' Vote in Battle</a><a class="btn ghost" href="how-it-works">How the ranking works</a></div></div>';
    echo '<nav class="spills" aria-label="Leaderboards">';
    foreach ($boards as $id => $label) { echo '<a class="spill' . ($id === $board ? ' on' : '') . '" href="leaderboard' . ($id !== '' ? '/' . $id : '') . '">' . h($label) . '</a>'; }
    echo '</nav>';

    $table = function (array $rows, bool $full) {
        if (!$rows) {
            return '<div class="empty-card"><span class="sic">' . icon('swords', 22) . '</span><b>No votes yet</b><p>Be the first: start a Battle, compare two anonymous answers and vote.</p><a class="btn primary" href="battle">Start a Battle</a></div>';
        }
        $o = '<div class="tblwrap"><table class="tbl lbtbl"><thead><tr><th class="r">Rank</th><th>Model</th><th class="r">Score</th>' . ($full ? '<th class="r">Wins</th><th class="r">Losses</th><th class="r">Ties</th>' : '') . '<th class="r">Votes</th><th>Win rate</th></tr></thead><tbody>';
        foreach ($rows as $i => $r) {
            $wr = $r['win_rate'] === null ? 0 : (int)$r['win_rate'];
            $o .= '<tr><td class="r rank">' . ($i < 3 ? '<span class="medal m' . ($i + 1) . '">' . ($i + 1) . '</span>' : ($i + 1)) . '</td>'
                . '<td class="mname">' . site_model_ico((string)$r['icon']) . h((string)$r['label']) . '</td>'
                . '<td class="r score">' . (int)$r['score'] . '</td>'
                . ($full ? '<td class="r">' . (int)$r['wins'] . '</td><td class="r">' . (int)$r['losses'] . '</td><td class="r">' . (int)$r['ties'] . '</td>' : '')
                . '<td class="r">' . number_format((int)$r['votes']) . '</td>'
                . '<td><span class="wr"><span class="sbar"><i style="width:' . $wr . '%"></i></span>' . ($r['win_rate'] === null ? '—' : $wr . '%') . '</span></td></tr>';
        }
        return $o . '</tbody></table></div>';
    };

    if ($board === 'text') {
        echo '<section class="scard"><div class="scard-h"><h2>' . icon('message', 16) . ' Text Battle</h2><span class="muted">Elo score · updated live</span></div>' . $table($ranked, true);
        $unranked = array_values(array_filter($models, function ($m) { return $m['votes'] === 0; }));
        if ($unranked) {
            echo '<p class="muted small">Waiting for their first vote: ';
            echo implode(', ', array_map(function ($m) { return h((string)$m['label']); }, $unranked));
            echo '</p>';
        }
        echo '</section>';
        echo '<section class="scard prose"><h2>Methodology</h2><p>Every anonymous Battle vote is a match between two models. Scores use the Elo system (start 1000, K = 32): beating a higher-rated model earns more points; ties and "both are bad" votes pull the two scores together. Side by Side votes are not counted, because the voter knew the names.</p></section>';
        site_foot();
        return;
    }

    if ($board === 'my-votes') {
        if (!$me) {
            echo '<div class="empty-card"><span class="sic">' . icon('lock', 22) . '</span><b>Log in to see your votes</b><p>Your personal voting history and favourite models appear here.</p><a class="btn primary" href="login.php?next=' . rawurlencode('/leaderboard/my-votes') . '">Log in</a></div>';
            site_foot();
            return;
        }
        $mine = $lb['mine'];
        echo '<div class="stats">'
            . '<div class="stat"><b>' . (int)$mine['votes'] . '</b><span>Your votes</span></div>'
            . '<div class="stat"><b>' . (int)$mine['a'] . '</b><span>Picked A</span></div>'
            . '<div class="stat"><b>' . (int)$mine['b'] . '</b><span>Picked B</span></div>'
            . '<div class="stat"><b>' . ((int)$mine['tie'] + (int)$mine['bad']) . '</b><span>Ties / both bad</span></div></div>';
        echo '<section class="scard"><div class="scard-h"><h2>' . icon('crown', 16) . ' Your favourite models</h2></div>';
        if (!$mine['picks']) {
            echo '<div class="empty-card flat"><b>No picks yet</b><p>Vote in a few Battles and your favourites show up here.</p><a class="btn primary" href="battle">Start a Battle</a></div>';
        } else {
            $max = max(array_map(function ($p) { return (int)$p['count']; }, $mine['picks']));
            echo '<div class="bars">';
            foreach ($mine['picks'] as $pk) {
                $w = $max ? (int)round(100 * $pk['count'] / $max) : 0;
                echo '<div class="brow"><span class="bl">' . h((string)$pk['label']) . '</span><span class="sbar"><i style="width:' . $w . '%"></i></span><b>' . (int)$pk['count'] . '</b></div>';
            }
            echo '</div>';
        }
        echo '</section>';
        site_foot();
        return;
    }

    /* overview */
    echo '<div class="stats">'
        . '<div class="stat"><b>' . number_format($total) . '</b><span>Total votes</span></div>'
        . '<div class="stat"><b>' . number_format($today) . '</b><span>Votes in the last 24h</span></div>'
        . '<div class="stat"><b>' . count($ranked) . '</b><span>Ranked models</span></div>'
        . '<div class="stat"><b>' . count($models) . '</b><span>Models in Battle</span></div></div>';
    echo '<div class="grid2">';
    echo '<section class="scard"><div class="scard-h"><h2>' . icon('trophy', 16) . ' Top models · Text</h2><a class="more" href="leaderboard/text">View all ' . icon('chevron-right', 13) . '</a></div>' . $table(array_slice($ranked, 0, 10), false) . '</section>';
    /* live battles = the latest votes (names are public once a vote is cast) */
    echo '<section class="scard"><div class="scard-h"><h2><span class="live"></span> Live battles</h2><a class="more" href="battle">Start a battle ' . icon('chevron-right', 13) . '</a></div>';
    $recent = array_slice(array_reverse($raw), 0, 8);
    if (!$recent) {
        echo '<div class="empty-card flat"><b>Quiet right now</b><p>No battles yet — yours could be the first.</p></div>';
    } else {
        echo '<ul class="feed">';
        foreach ($recent as $v) {
            $a = (string)($v['a'] ?? ''); $b = (string)($v['b'] ?? ''); $r = (string)($v['v'] ?? '');
            $la = isset($labels[$a]) ? (string)$labels[$a]['label'] : model_label($a);
            $lbb = isset($labels[$b]) ? (string)$labels[$b]['label'] : model_label($b);
            if ($r === 'a') { $txt = '<b>' . h($la) . '</b> beat ' . h($lbb); $tag = 'win'; }
            elseif ($r === 'b') { $txt = '<b>' . h($lbb) . '</b> beat ' . h($la); $tag = 'win'; }
            elseif ($r === 'bad') { $txt = h($la) . ' vs ' . h($lbb) . ' — both bad'; $tag = 'bad'; }
            else { $txt = h($la) . ' vs ' . h($lbb) . ' — tie'; $tag = 'tie'; }
            echo '<li><span class="dot ' . $tag . '"></span><span class="sft">' . $txt . '</span><span class="muted small">' . h(site_ago((int)($v['t'] ?? 0))) . '</span></li>';
        }
        echo '</ul>';
    }
    echo '</section></div>';
    /* win rate chart */
    if ($ranked) {
        echo '<section class="scard"><div class="scard-h"><h2>' . icon('gauge', 16) . ' Win rate</h2><span class="muted">Share of battles won (ties count half)</span></div><div class="bars">';
        foreach (array_slice($ranked, 0, 10) as $r) {
            $wr = (int)($r['win_rate'] ?? 0);
            echo '<div class="brow"><span class="bl">' . site_model_ico((string)$r['icon']) . h((string)$r['label']) . '</span><span class="sbar"><i style="width:' . $wr . '%"></i></span><b>' . $wr . '%</b></div>';
        }
        echo '</div></section>';
    }
    /* news */
    echo '<div class="sect-h"><h2 class="sect">Devil AI News</h2><a class="more" href="blog">All posts ' . icon('chevron-right', 13) . '</a></div>';
    echo site_blog_cards(3);
    site_foot();
}

function site_blog_cards(int $limit = 0): string {
    $posts = site_blog_posts();
    if ($limit) { $posts = array_slice($posts, 0, $limit, true); }
    $o = '<div class="posts">';
    foreach ($posts as $slug => $p) {
        $o .= '<a class="post" href="blog/' . h($slug) . '"><span class="cover ' . h($p['cover'][0]) . '">' . icon($p['cover'][1], 34) . '</span>'
            . '<span class="pmeta"><span class="tag">' . h($p['tag']) . '</span>' . h(date('F j, Y', strtotime($p['date']))) . '</span>'
            . '<b>' . h($p['title']) . '</b><span class="psum">' . h($p['summary']) . '</span></a>';
    }
    return $o . '</div>';
}

/* ─────────────────────────── SEARCH ─────────────────────────── */
function site_chat_url(array $c): string {
    $slug = rawurlencode((string)($c['slug'] ?: $c['id']));
    $seg = function ($s, $fb) { $s = trim((string)preg_replace('/[^a-z0-9-]+/', '-', strtolower((string)$s)), '-'); return $s !== '' ? $s : $fb; };
    $modes = ['battle' => 'battle', 'agent' => 'agent', 'sbs' => 'side-by-side'];
    if (isset($modes[$c['mode'] ?? ''])) { return $modes[$c['mode']] . '/' . $slug; }
    return 'chat/' . rawurlencode($seg($c['url_model'] ?? 'flash', 'flash')) . '/' . rawurlencode($seg($c['url_type'] ?? 'chat', 'chat')) . '/' . $slug;
}

function site_collect_text($v, array &$out, int &$len): void {
    if ($len > 400000) { return; }
    if (is_string($v)) {
        if (strlen($v) > 60000 || strncmp($v, 'data:', 5) === 0) { return; }
        $out[] = $v; $len += strlen($v); return;
    }
    if (is_array($v)) {
        foreach ($v as $k => $x) {
            if (in_array($k, ['data', 'image', 'ts', 'model_id', 'sandbox', 'trace'], true)) { continue; }
            site_collect_text($x, $out, $len);
        }
    }
}

function site_snippet(string $text, string $q): string {
    $text = trim((string)preg_replace('/\s+/u', ' ', strip_tags((string)preg_replace('/[*_`#>|]+/', ' ', $text))));
    $pos = mb_stripos($text, $q);
    if ($pos === false) { return h(mb_substr($text, 0, 160)) . (mb_strlen($text) > 160 ? '…' : ''); }
    $start = max(0, $pos - 60);
    $s = mb_substr($text, $start, 180);
    $out = ($start > 0 ? '…' : '') . h($s) . (mb_strlen($text) > $start + 180 ? '…' : '');
    return (string)preg_replace('/(' . preg_quote(h($q), '/') . ')/iu', '<mark>$1</mark>', $out);
}

function page_search(): void {
    $me = site_me();
    $q = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 120));
    $mode = (string)($_GET['mode'] ?? 'all');
    $modes = ['all' => ['All', 'list'], 'ai' => ['Direct', 'message'], 'agent' => ['Agent', 'spark'], 'battle' => ['Battle', 'swords'], 'sbs' => ['Side by Side', 'columns']];
    if (!isset($modes[$mode])) { $mode = 'all'; }
    site_head($q !== '' ? 'Search: ' . $q : 'Search', 'search');
    echo '<div class="hero sm"><span class="eyebrow">' . icon('search', 13) . ' Search</span><h1>Search your chats</h1><p class="lead">Find any conversation by its title or by words inside it.</p></div>';
    if (!$me) {
        echo '<div class="empty-card"><span class="sic">' . icon('lock', 22) . '</span><b>Log in to search your chats</b><p>Your chat history is private to your account.</p><a class="btn primary" href="login.php?next=' . rawurlencode('/history/search') . '">Log in</a></div>';
        site_foot();
        return;
    }
    echo '<form class="searchbox" method="get" action="history/search" role="search">' . icon('search', 18)
        . '<input type="search" name="q" value="' . h($q) . '" placeholder="Search chats…" autocomplete="off" autofocus aria-label="Search chats">'
        . ($mode !== 'all' ? '<input type="hidden" name="mode" value="' . h($mode) . '">' : '')
        . '<button class="btn primary" type="submit">Search</button></form>';
    echo '<nav class="spills" aria-label="Filter by mode">';
    foreach ($modes as $id => $m) {
        $qs = http_build_query(array_filter(['q' => $q, 'mode' => $id === 'all' ? '' : $id]));
        echo '<a class="spill' . ($id === $mode ? ' on' : '') . '" href="history/search' . ($qs ? '?' . $qs : '') . '">' . icon($m[1], 13) . ' ' . h($m[0]) . '</a>';
    }
    echo '</nav>';

    site_api();
    $uid = (string)$me['id'];
    $chats = list_chats($uid);
    if ($mode !== 'all') { $chats = array_values(array_filter($chats, function ($c) use ($mode) { return ($c['mode'] ?? 'ai') === $mode; })); }
    $hits = [];
    if ($q === '') {
        foreach (array_slice($chats, 0, 30) as $c) { $hits[] = [$c, '']; }
    } else {
        foreach ($chats as $c) {
            $inTitle = mb_stripos((string)$c['title'], $q) !== false;
            $snip = '';
            $full = load_chat($uid, (string)$c['id']);
            if ($full) {
                $parts = []; $len = 0;
                site_collect_text($full['messages'] ?? [], $parts, $len);
                foreach ($parts as $t) { if (mb_stripos($t, $q) !== false) { $snip = site_snippet($t, $q); break; } }
            }
            if ($inTitle || $snip !== '') { $hits[] = [$c, $snip, $inTitle]; }
            if (count($hits) >= 60) { break; }
        }
        usort($hits, function ($x, $y) { return [!empty($y[2]), $y[0]['updated']] <=> [!empty($x[2]), $x[0]['updated']]; });
    }
    echo '<p class="muted small">' . ($q === '' ? 'Recent chats' : count($hits) . ' result' . (count($hits) === 1 ? '' : 's') . ' for “' . h($q) . '”') . '</p>';
    if (!$hits) {
        echo '<div class="empty-card"><span class="sic">' . icon('search', 22) . '</span><b>' . ($q === '' ? 'No chats yet' : 'Nothing found') . '</b><p>' . ($q === '' ? 'Your conversations will show up here.' : 'Try another word, or a different mode filter.') . '</p><a class="btn primary" href="./">New chat</a></div>';
    } else {
        echo '<ul class="results">';
        foreach ($hits as $hit) {
            $c = $hit[0];
            $ic = $modes[$c['mode'] ?? 'ai'][1] ?? 'message';
            $title = h((string)$c['title']);
            if ($q !== '') { $title = (string)preg_replace('/(' . preg_quote(h($q), '/') . ')/iu', '<mark>$1</mark>', $title); }
            echo '<li><a href="' . h(site_chat_url($c)) . '"><span class="ric">' . icon($ic, 15) . '</span><span class="rbody"><b>' . $title . '</b>'
                . ($hit[1] !== '' ? '<span class="rsnip">' . $hit[1] . '</span>' : '')
                . '</span><span class="muted small">' . h(site_ago((int)$c['updated'])) . '</span></a></li>';
        }
        echo '</ul>';
    }
    site_foot();
}

/* ─────────────────────────── BLOG ─────────────────────────── */
function page_blog(string $post): void {
    $posts = site_blog_posts();
    if ($post === '') {
        site_head('Blog', 'blog', 'News and product updates from the Devil AI team.');
        echo '<div class="hero sm"><span class="eyebrow">Blog</span><h1>Devil AI News</h1><p class="lead">Product updates, how-tos and notes from the team.</p></div>';
        echo site_blog_cards();
        site_foot();
        return;
    }
    if (!isset($posts[$post])) { site_404(); }
    $p = $posts[$post];
    site_head($p['title'], 'blog', $p['summary']);
    echo '<article class="article"><a class="back" href="blog">' . icon('arrow-left', 14) . ' All posts</a>'
        . '<span class="cover big ' . h($p['cover'][0]) . '">' . icon($p['cover'][1], 46) . '</span>'
        . '<span class="pmeta"><span class="tag">' . h($p['tag']) . '</span>' . h(date('F j, Y', strtotime($p['date']))) . ' · Devil AI Team</span>'
        . '<h1>' . h($p['title']) . '</h1><p class="lead">' . h($p['summary']) . '</p><div class="prose">' . $p['body'] . '</div></article>';
    $more = array_slice(array_diff_key($posts, [$post => 1]), 0, 3, true);
    echo '<div class="sect-h"><h2 class="sect">More posts</h2></div><div class="posts">';
    foreach ($more as $slug => $m) {
        echo '<a class="post" href="blog/' . h($slug) . '"><span class="cover ' . h($m['cover'][0]) . '">' . icon($m['cover'][1], 34) . '</span><span class="pmeta"><span class="tag">' . h($m['tag']) . '</span>' . h(date('F j, Y', strtotime($m['date']))) . '</span><b>' . h($m['title']) . '</b></a>';
    }
    echo '</div>';
    site_foot();
}

/* ─────────────────────────── ROUTER ─────────────────────────── */
switch ($P) {
    case 'leaderboard':
        page_leaderboard(preg_replace('/[^a-z0-9-]/', '', strtolower((string)($_GET['board'] ?? ''))));
        break;
    case 'search':
        page_search();
        break;
    case 'blog':
        page_blog(preg_replace('/[^a-z0-9-]/', '', strtolower((string)($_GET['post'] ?? ''))));
        break;
    default:
        $c = site_page_content($P);
        if (!$c) { site_404(); }
        $active = in_array($P, ['how-it-works', 'faq', 'about', 'careers'], true) ? $P : 'legal';
        site_head($c['title'], $active, (string)($c['desc'] ?? ''));
        echo '<div class="page-' . h($P) . (!empty($c['legal']) ? ' legalpage' : '') . '">' . $c['html'] . '</div>';
        site_foot();
}
