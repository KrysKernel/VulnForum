<?php
function vf_avatar_hue(string $name): int {
    return abs(crc32(strtolower($name))) % 360;
}

function vf_avatar(string $name, string $size = '', ?string $avatar = null): string {
    $cls = 'avatar' . ($size !== '' ? ' avatar-' . $size : '');
    if ($avatar !== null && $avatar !== '') {
        $fsPath = __DIR__ . '/../uploads/' . $avatar;
        if (is_file($fsPath)) {
            $url = '/uploads/' . rawurlencode($avatar) . '?v=' . (@filemtime($fsPath) ?: 1);
            return '<img src="' . htmlspecialchars($url, ENT_QUOTES) . '" alt="' . htmlspecialchars($name, ENT_QUOTES) . '" class="' . $cls . ' avatar-img">';
        }
    }
    $hue = vf_avatar_hue($name);
    $hue2 = ($hue + 42) % 360;
    $initial = htmlspecialchars(strtoupper(substr($name !== '' ? $name : '?', 0, 1)));
    return '<span class="' . $cls . '" aria-hidden="true" style="--av-h1:' . $hue . ';--av-h2:' . $hue2 . ';">' . $initial . '</span>';
}

function vf_format_date(?string $ts): string {
    if (!$ts) return '';
    $time = strtotime($ts);
    if ($time === false) return htmlspecialchars($ts);
    $diff = time() - $time;
    if ($diff >= 0 && $diff < 60) return 'just now';
    if ($diff >= 60 && $diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff >= 3600 && $diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff >= 86400 && $diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', $time);
}

function vf_excerpt(?string $html, int $limit = 140): string {
    $plain = trim(preg_replace('/\s+/', ' ', strip_tags((string)$html)));
    if (strlen($plain) <= $limit) return $plain;
    return substr($plain, 0, $limit) . '...';
}

function vf_icon(string $name, int $size = 16, string $extraClass = '', string $extraStyle = ''): string {
    $paths = [
        'shield'   => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9.5 12.5l1.7 1.7 3.8-4.2"/>',
        'search'   => '<circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'message'  => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>',
        'plus'     => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'user'     => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'lock'     => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'mail'     => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
        'key'      => '<circle cx="7.5" cy="15.5" r="5.5"/><path d="M21 2l-9.6 9.6"/><path d="M15.5 7.5l3 3L22 7l-3-3"/>',
        'upload'   => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
        'clock'    => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'chart'    => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
        'users'    => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'link'     => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'logout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'sun'      => '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>',
        'moon'     => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
        'eye'      => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'bolt'     => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
        'terminal' => '<polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/>',
        'check'    => '<polyline points="20 6 9 17 4 12"/>',
        'trash'    => '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>',
    ];

    $inner = $paths[$name] ?? $paths['bolt'];
    $cls = trim('icon ' . $extraClass);
    $style = "width:{$size}px;height:{$size}px;flex-shrink:0;" . $extraStyle;

    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . htmlspecialchars($cls) . '" style="' . htmlspecialchars($style) . '" aria-hidden="true">' . $inner . '</svg>';
}
