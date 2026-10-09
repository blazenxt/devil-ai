<?php
/**
 * ONE-TIME data migration (temporary file — removed again once it has run on the live server).
 * Renames the stored compare-mode data to the new "battle" names:
 *   data/battle_votes.json → data/battle_votes.json (voter hashes re-keyed to the new salt)
 *   chat files: "battle_models" → "battle_models"
 * Runs once (marker file), under a lock, and never deletes data (the old votes file is kept as *.bak).
 */
function devil_migrate_battle_v1(): void {
    $d = data_dir();
    $mark = $d . '/.migrated_battle_v1';
    if (is_file($mark) || !is_dir($d)) { return; }
    $lock = @fopen($d . '/.migrate_battle.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { return; }
    try {
        if (is_file($mark)) { return; }
        /* 1) votes */
        $old = $d . '/battle_votes.json';
        if (is_file($old)) {
            $users = json_decode((string)@file_get_contents($d . '/users.json'), true);
            $map = [];
            foreach (array_keys(is_array($users) ? $users : []) as $uid) {
                $map[substr(hash('sha256', 'battle-voter|' . $uid), 0, 16)] = battle_user_hash((string)$uid);
            }
            $ov = json_decode((string)@file_get_contents($old), true);
            $ov = is_array($ov) ? $ov : [];
            foreach ($ov as &$v) { if (is_array($v) && isset($v['u'], $map[$v['u']])) { $v['u'] = $map[$v['u']]; } }
            unset($v);
            $new = battle_votes_path();
            $nv = is_file($new) ? json_decode((string)@file_get_contents($new), true) : [];
            $all = array_merge($ov, is_array($nv) ? $nv : []);
            usort($all, function ($a, $b) { return (int)($a['t'] ?? 0) <=> (int)($b['t'] ?? 0); });
            if (!save_json_atomic($new, $all)) { return; }
            @rename($old, $d . '/votes_before_rename.json.bak');
            @unlink($d . '/.battle_votes.lock');
        }
        /* 2) chats */
        foreach (glob($d . '/chats/*/*.json') ?: [] as $f) {
            $raw = (string)@file_get_contents($f);
            if (strpos($raw, '"battle_models"') === false) { continue; }
            $j = json_decode($raw, true);
            if (!is_array($j)) { continue; }
            if (isset($j['battle_models']) && !isset($j['battle_models'])) { $j['battle_models'] = $j['battle_models']; }
            unset($j['battle_models']);
            save_json_atomic($f, $j);
        }
        @file_put_contents($mark, gmdate('c'));
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
