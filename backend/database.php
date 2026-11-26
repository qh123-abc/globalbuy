<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function gf_db(): PDO
{
    static $db = null;
    if ($db instanceof PDO) {
        return $db;
    }

    if (!extension_loaded('pdo_sqlite')) {
        throw new RuntimeException('PHP PDO_SQLITE is required for the product database.');
    }

    $databaseFile = gf_data_directory() . DIRECTORY_SEPARATOR . 'products.sqlite';
    $legacyDatabase = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'products.db';
    if (!is_file($databaseFile) && is_file($legacyDatabase)) {
        if (!copy($legacyDatabase, $databaseFile)) {
            throw new RuntimeException('Could not migrate the existing local product database.');
        }
    }
    $db = new PDO('sqlite:' . $databaseFile, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec('PRAGMA busy_timeout = 5000');
    $db->exec('CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        category TEXT NOT NULL,
        price REAL NOT NULL DEFAULT 0,
        badge TEXT NOT NULL DEFAULT \'\',
        image TEXT NOT NULL DEFAULT \'\',
        supplier_url TEXT NOT NULL DEFAULT \'\',
        agent_links TEXT NOT NULL DEFAULT \'{}\',
        active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');

    $columns = array_column($db->query('PRAGMA table_info(products)')->fetchAll(), 'name');
    if (!in_array('agent_links', $columns, true)) {
        $db->exec("ALTER TABLE products ADD COLUMN agent_links TEXT NOT NULL DEFAULT '{}'");
    }
    $legacyLinks = $db->query("SELECT id, supplier_url FROM products WHERE supplier_url <> '' AND agent_links = '{}'")->fetchAll();
    if ($legacyLinks) {
        $migrateLink = $db->prepare('UPDATE products SET agent_links = ? WHERE id = ?');
        foreach ($legacyLinks as $legacy) {
            $encoded = json_encode(['kakobuy' => $legacy['supplier_url']], JSON_UNESCAPED_SLASHES);
            $migrateLink->execute([$encoded, $legacy['id']]);
        }
    }

    $count = (int) $db->query('SELECT COUNT(*) FROM products')->fetchColumn();
    if ($count === 0) {
        $seed = [
            ['Vintage Runner Sneakers', 'shoes', 34.9, 'TOP PICK', 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=700&q=80'],
            ['Everyday Zip Hoodie', 'hoodies-sweaters', 28.5, 'POPULAR', 'https://images.unsplash.com/photo-1556821840-3a63f95609a7?auto=format&fit=crop&w=700&q=80'],
            ['Classic Straight Denim', 'jeans-pants', 25.2, '', 'https://images.unsplash.com/photo-1542272604-787c3835535d?auto=format&fit=crop&w=700&q=80'],
            ['Retro Court Low', 'top-quality', 42, 'TOP QUALITY', 'https://images.unsplash.com/photo-1600185365483-26d7a4cc7519?auto=format&fit=crop&w=700&q=80'],
            ['Lightweight Puffer Jacket', 'jackets-puffers', 49.8, 'NEW FIND', 'https://images.unsplash.com/photo-1544923246-77307dd654cb?auto=format&fit=crop&w=700&q=80'],
            ['Relaxed Fit Track Set', 'tracksuits', 38.6, '', 'https://images.unsplash.com/photo-1552902865-b72c031ac5ea?auto=format&fit=crop&w=700&q=80'],
            ['Everyday Cotton Tee', 't-shirts-longsleeves', 12.9, 'UNDER $15', 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?auto=format&fit=crop&w=700&q=80'],
            ['Minimal Crossbody Bag', 'accessories', 18.4, '', 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&w=700&q=80'],
            ['Retro High Top Canvas', 'shoes', 31.5, '', 'https://images.unsplash.com/photo-1525966222134-fcfa99b8ae77?auto=format&fit=crop&w=700&q=80'],
            ['Soft Knit Crewneck', 'hoodies-sweaters', 32, '', 'https://images.unsplash.com/photo-1576566588028-4147f3842f27?auto=format&fit=crop&w=700&q=80'],
            ['Utility Cargo Pants', 'jeans-pants', 27.9, 'COMMUNITY PICK', 'https://images.unsplash.com/photo-1517445312882-bc9910d016b7?auto=format&fit=crop&w=700&q=80'],
            ['Everyday Running Shoes', 'top-quality', 46, '', 'https://images.unsplash.com/photo-1552346154-21d32810aba3?auto=format&fit=crop&w=700&q=80'],
            ['Classic Logo Cap', 'accessories', 11.8, '', 'https://images.unsplash.com/photo-1588850561407-ed78c282e89b?auto=format&fit=crop&w=700&q=80'],
            ['Relaxed Summer Shorts', 'shorts', 16.5, '', 'https://images.unsplash.com/photo-1591195853828-11db59a44f6b?auto=format&fit=crop&w=700&q=80'],
            ['Waterproof Shell Jacket', 'jackets-puffers', 54, 'NEW FIND', 'https://images.unsplash.com/photo-1545594861-3bef43ff2fc8?auto=format&fit=crop&w=700&q=80'],
            ['Long Sleeve Heavy Tee', 't-shirts-longsleeves', 19.4, '', 'https://images.unsplash.com/photo-1581655353564-df123a1eb820?auto=format&fit=crop&w=700&q=80'],
            ['Everyday Track Pants', 'tracksuits', 22.5, '', 'https://images.unsplash.com/photo-1552902865-b72c031ac5ea?auto=format&fit=crop&w=700&q=80'],
            ['Retro Skate Sneakers', 'shoes', 36.8, '', 'https://images.unsplash.com/photo-1525966222134-fcfa99b8ae77?auto=format&fit=crop&w=700&q=80'],
            ['Warm Fleece Pullover', 'hoodies-sweaters', 29.5, '', 'https://images.unsplash.com/photo-1620799140408-edc6dcb6d633?auto=format&fit=crop&w=700&q=80'],
            ['Classic Denim Shorts', 'shorts', 18, '', 'https://images.unsplash.com/photo-1598522325074-042db73aa4e6?auto=format&fit=crop&w=700&q=80'],
        ];
        $insert = $db->prepare('INSERT INTO products(name, category, price, badge, image) VALUES(?, ?, ?, ?, ?)');
        $db->beginTransaction();
        try {
            foreach ($seed as $product) {
                $insert->execute($product);
            }
            $db->commit();
        } catch (Throwable $error) {
            $db->rollBack();
            throw $error;
        }
    }

    return $db;
}
