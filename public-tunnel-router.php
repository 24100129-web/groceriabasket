<?php
$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (!is_string($uriPath)) {
    http_response_code(404);
    exit;
}

$path = str_replace('\\', '/', rawurldecode($uriPath));
$segments = explode('/', trim($path, '/'));
foreach ($segments as $segment) {
    if ($segment === '..' || ($segment !== '' && $segment[0] === '.')) {
        http_response_code(404);
        exit;
    }
}

$lowerPath = strtolower($path);
if (
    str_starts_with($lowerPath, '/database/')
    || str_starts_with($lowerPath, '/partials/')
    || in_array($lowerPath, ['/database', '/app_state.php', '/app_db_check.php', '/db_test.php', '/public-tunnel-router.php', '/share-setup.php'], true)
) {
    http_response_code(404);
    exit;
}

$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$publicExtensions = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico', 'woff', 'woff2', 'ttf', 'eot', 'php'];
if ($path !== '/' && !in_array($extension, $publicExtensions, true)) {
    http_response_code(404);
    exit;
}

return false;
