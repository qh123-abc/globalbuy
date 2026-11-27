<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

function gf_json(int $status, $data): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function gf_request_json(): array
{
    $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($length > 1000000) {
        gf_json(413, ['error' => 'Request is too large.']);
    }
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw === false ? '' : $raw, true);
    if (!is_array($decoded)) {
        gf_json(400, ['error' => 'Invalid JSON request.']);
    }
    return $decoded;
}

function gf_require_admin(): void
{
    if (empty($_SESSION['gf_admin'])) {
        gf_json(401, ['error' => 'Sign in to manage products.']);
    }
}

function gf_products_with_links(array $rows): array
{
    foreach ($rows as &$row) {
        $links = json_decode((string) ($row['agent_links'] ?? '{}'), true);
        $row['agent_links'] = is_array($links) ? $links : [];
        $row['id'] = (int) $row['id'];
        if (array_key_exists('active', $row)) {
            $row['active'] = (int) $row['active'];
        }
    }
    unset($row);
    return $rows;
}

function gf_site_settings(): array
{
    $settings = ['whatsapp_url' => '', 'telegram_url' => '', 'banner_image' => ''];
    $rows = gf_db()->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll();
    foreach ($rows as $row) {
        if (array_key_exists($row['setting_key'], $settings)) {
            $settings[$row['setting_key']] = (string) $row['setting_value'];
        }
    }
    return $settings;
}

function gf_save_site_settings(array $data): array
{
    $settings = [
        'whatsapp_url' => trim((string) ($data['whatsapp_url'] ?? '')),
        'telegram_url' => trim((string) ($data['telegram_url'] ?? '')),
        'banner_image' => trim((string) ($data['banner_image'] ?? '')),
    ];
    foreach (['whatsapp_url', 'telegram_url'] as $key) {
        if ($settings[$key] !== '' && (!filter_var($settings[$key], FILTER_VALIDATE_URL) || !in_array(strtolower((string) parse_url($settings[$key], PHP_URL_SCHEME)), ['http', 'https'], true))) {
            gf_json(400, ['error' => 'WhatsApp and Telegram links must be valid HTTPS/HTTP URLs.']);
        }
    }
    if ($settings['banner_image'] !== '' && !preg_match('#^(https?://|/(?:[A-Za-z0-9_-]+/)*uploads/[A-Za-z0-9_-]+\.(jpg|jpeg|png|webp|gif)$)#i', $settings['banner_image'])) {
        gf_json(400, ['error' => 'Choose a valid banner image URL or upload an image.']);
    }
    $save = gf_db()->prepare('INSERT INTO site_settings(setting_key, setting_value) VALUES(?, ?) ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value');
    foreach ($settings as $key => $value) {
        $save->execute([$key, substr($value, 0, 4000)]);
    }
    return $settings;
}

function gf_product_data(array $data): array
{
    $name = trim((string) ($data['name'] ?? ''));
    $category = trim((string) ($data['category'] ?? ''));
    $price = filter_var($data['price'] ?? null, FILTER_VALIDATE_FLOAT);
    $allowedCategories = ['top-quality', 'shoes', 'hoodies-sweaters', 'tracksuits', 'jeans-pants', 'jackets-puffers', 't-shirts-longsleeves', 'shorts', 'accessories'];

    if ($name === '' || strlen($name) > 720 || !in_array($category, $allowedCategories, true) || $price === false || $price < 0) {
        gf_json(400, ['error' => 'Enter a product name, category, and valid price.']);
    }

    $image = trim((string) ($data['image'] ?? ''));
    if ($image !== '' && !preg_match('#^(https?://|/(?:[A-Za-z0-9_-]+/)*uploads/[A-Za-z0-9_-]+\.(jpg|jpeg|png|webp|gif)$)#i', $image)) {
        gf_json(400, ['error' => 'The product image must use an HTTPS/HTTP URL or uploaded image path.']);
    }

    $agentKeys = ['kakobuy', 'litbuy', 'usfans', 'lovegobuy', 'hipobuy', 'oopbuy', 'acbuy', 'sugargoo', 'hoobuy', 'fishgoo', 'joyagoo', 'bbdbuy', 'cnfans', 'mulebuy', 'allchinabuy'];
    $inputLinks = $data['agent_links'] ?? [];
    if (!is_array($inputLinks)) {
        gf_json(400, ['error' => 'Agent links must be a list of URLs.']);
    }
    $links = [];
    foreach ($agentKeys as $key) {
        $url = trim((string) ($inputLinks[$key] ?? ''));
        if ($url === '' && $key === 'kakobuy') {
            $url = trim((string) ($data['supplier_url'] ?? ''));
        }
        if ($url === '') {
            continue;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            gf_json(400, ['error' => 'Each agent link must be a valid HTTPS/HTTP URL.']);
        }
        $links[$key] = substr($url, 0, 4000);
    }

    return [
        'name' => $name,
        'category' => $category,
        'price' => (float) $price,
        'badge' => substr(trim((string) ($data['badge'] ?? '')), 0, 320),
        'image' => substr($image, 0, 4000),
        'supplier_url' => $links['kakobuy'] ?? '',
        'agent_links' => json_encode($links, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
        'active' => !empty($data['active']) ? 1 : 0,
    ];
}

function gf_upload_image(string $basePath): void
{
    if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
        gf_json(400, ['error' => 'Choose an image first.']);
    }
    $file = $_FILES['image'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
        gf_json(400, ['error' => 'The image upload did not complete.']);
    }
    if ((int) $file['size'] > 5 * 1024 * 1024) {
        gf_json(413, ['error' => 'Image must be 5 MB or smaller.']);
    }
    if (function_exists('finfo_open')) {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    } elseif (function_exists('getimagesize')) {
        $imageInfo = @getimagesize($file['tmp_name']);
        $mime = is_array($imageInfo) ? (string) ($imageInfo['mime'] ?? '') : '';
    } else {
        gf_json(500, ['error' => 'The server cannot verify image files. Enable PHP Fileinfo or getimagesize.']);
    }
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($extensions[$mime])) {
        gf_json(400, ['error' => 'Use JPG, PNG, WEBP, or GIF.']);
    }

    $uploadDirectory = dirname(__DIR__) . '/uploads';
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
        gf_json(500, ['error' => 'Could not create the image upload directory.']);
    }
    if (!is_writable($uploadDirectory)) {
        gf_json(500, ['error' => 'The image upload directory is not writable by PHP.']);
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $uploadDirectory . '/' . $filename)) {
        gf_json(500, ['error' => 'Could not save the uploaded image.']);
    }
    gf_json(201, ['url' => $basePath . '/uploads/' . $filename]);
}

$https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || (getenv('GLOBALFIND_COOKIE_SECURE') === '1');
session_set_cookie_params([
    'lifetime' => 8 * 60 * 60,
    'path' => '/',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$apiPosition = strpos($path, '/api/');
$basePath = '';
if ($apiPosition !== false) {
    $basePath = substr($path, 0, $apiPosition);
    $path = substr($path, $apiPosition);
}
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    if ($path === '/healthz' && $method === 'GET') {
        gf_json(200, ['ok' => true]);
    }
    if ($path === '/api/session' && $method === 'GET') {
        gf_json(200, ['authenticated' => !empty($_SESSION['gf_admin'])]);
    }
    if ($path === '/api/products' && $method === 'GET') {
        $rows = gf_db()->query('SELECT id, name, category, price, badge, image, supplier_url, agent_links FROM products WHERE active = 1 ORDER BY id DESC')->fetchAll();
        gf_json(200, gf_products_with_links($rows));
    }
    if ($path === '/api/settings' && $method === 'GET') {
        gf_json(200, gf_site_settings());
    }
    if ($path === '/api/login' && $method === 'POST') {
        $password = gf_admin_password();
        if ($password === '') {
            gf_json(503, ['error' => 'Configure GLOBALFIND_ADMIN_PASSWORD or backend/config.local.php.']);
        }
        $data = gf_request_json();
        if (!hash_equals($password, (string) ($data['password'] ?? ''))) {
            gf_json(401, ['error' => 'Password is incorrect.']);
        }
        session_regenerate_id(true);
        $_SESSION['gf_admin'] = true;
        gf_json(200, ['ok' => true]);
    }
    if ($path === '/api/logout' && $method === 'DELETE') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => 'Strict',
            ]);
        }
        session_destroy();
        gf_json(200, ['ok' => true]);
    }
    if ($path === '/api/admin/products' && $method === 'GET') {
        gf_require_admin();
        $rows = gf_db()->query('SELECT id, name, category, price, badge, image, supplier_url, agent_links, active FROM products ORDER BY id DESC')->fetchAll();
        gf_json(200, gf_products_with_links($rows));
    }
    if ($path === '/api/admin/settings' && $method === 'GET') {
        gf_require_admin();
        gf_json(200, gf_site_settings());
    }
    if ($path === '/api/admin/settings' && $method === 'PUT') {
        gf_require_admin();
        gf_json(200, gf_save_site_settings(gf_request_json()));
    }
    if ($path === '/api/admin/upload' && $method === 'POST') {
        gf_require_admin();
        gf_upload_image($basePath);
    }
    if ($path === '/api/admin/products' && $method === 'POST') {
        gf_require_admin();
        $product = gf_product_data(gf_request_json());
        $insert = gf_db()->prepare('INSERT INTO products(name, category, price, badge, image, supplier_url, agent_links, active) VALUES(:name, :category, :price, :badge, :image, :supplier_url, :agent_links, :active)');
        $insert->execute($product);
        gf_json(201, ['id' => (int) gf_db()->lastInsertId()]);
    }
    if (preg_match('#^/api/admin/products/(\d+)$#', $path, $matches) && $method === 'PUT') {
        gf_require_admin();
        $product = gf_product_data(gf_request_json());
        $product['id'] = (int) $matches[1];
        $update = gf_db()->prepare('UPDATE products SET name=:name, category=:category, price=:price, badge=:badge, image=:image, supplier_url=:supplier_url, agent_links=:agent_links, active=:active WHERE id=:id');
        $update->execute($product);
        gf_json($update->rowCount() ? 200 : 404, ['ok' => $update->rowCount() > 0]);
    }
    if (preg_match('#^/api/admin/products/(\d+)$#', $path, $matches) && $method === 'DELETE') {
        gf_require_admin();
        $delete = gf_db()->prepare('DELETE FROM products WHERE id = ?');
        $delete->execute([(int) $matches[1]]);
        gf_json($delete->rowCount() ? 200 : 404, ['ok' => $delete->rowCount() > 0]);
    }

    gf_json(404, ['error' => 'Not found.']);
} catch (Throwable $error) {
    error_log('GlobalFind API error: ' . $error->getMessage());
    gf_json(500, ['error' => 'The server could not complete the request. Check the PHP error log.']);
}
