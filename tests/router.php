<?php
declare(strict_types=1);

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$root = dirname(__DIR__);
$routes = [
    '/' => '/frontend/index.html',
    '/admin' => '/admin/admin.html',
    '/admin/' => '/admin/admin.html',
    '/styles.css' => '/frontend/styles.css',
    '/app.js' => '/frontend/app.js',
    '/admin.css' => '/admin/admin.css',
    '/admin.js' => '/admin/admin.js',
];

if ($requestPath === '/healthz' || preg_match('#^/api(?:/|$)#', $requestPath)) {
    require $root . '/backend/api.php';
}

if (isset($routes[$requestPath])) {
    $file = $root . $routes[$requestPath];
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($extension === 'css' ? 'text/css' : ($extension === 'js' ? 'text/javascript' : 'text/html')) . '; charset=utf-8');
    readfile($file);
    return true;
}

if (preg_match('#^/uploads/[A-Za-z0-9_-]+\.(jpg|jpeg|png|webp|gif)$#i', $requestPath)) {
    return false;
}

http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['error' => 'Not found.']);
