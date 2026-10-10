<?php
declare(strict_types=1);

/** Detect common credentials before a conversation is published as a public share. */
function devil_share_contains_secret(string $text): bool {
    $patterns = [
        '/^\s*(?:passwordd?|passwd|passcode|pwd|pin|otp|secret|token|api[\s_-]*key|access[\s_-]*token|access[\s_-]*key|client[\s_-]*secret|private[\s_-]*key|username|user[\s_-]*(?:name|id)|login[\s_-]*(?:user[\s_-]*(?:name|id)|id))\s*[:=]\s*\S.*$/imu',
        '/\b(?:password|passwd|passcode|secret|api[\s_-]*key|access[\s_-]*token)\s+(?:is|equals?)\s+\S+/iu',
        '/\b(?:https?|ftp|sftp):\/\/[^\s\/:@]+:[^\s\/@]+@/i',
        '/\bgh[pousr]_[A-Za-z0-9_]{20,}\b|\bgithub_pat_[A-Za-z0-9_]{20,}\b/i',
        '/\bAIza[0-9A-Za-z_-]{20,}\b|\bAKIA[0-9A-Z]{16}\b|\bsk-[A-Za-z0-9]{20,}\b/i',
        '/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/i',
        '/\bAuthorization\s*:\s*(?:Bearer|Basic)\s+\S+/i',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text)) { return true; }
    }
    return false;
}

/** Hide the whole message (and any attached media) rather than trying to guess where a secret ends. */
function devil_share_public_message(array $message): array {
    if (devil_share_contains_secret((string)($message['content'] ?? ''))) {
        $message['content'] = '[Hidden in public share because this message contains account or secret details.]';
        unset($message['img'], $message['attachments']);
        $message['share_redacted'] = true;
    }
    return $message;
}
