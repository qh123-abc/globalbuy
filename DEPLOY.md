# PHP shared-hosting deployment

This version does not require Python or a continuously running application process. Upload the repository contents to the hosting account's web root.

## Requirements

- PHP 7.4 or newer
- PDO_SQLITE, Fileinfo, and PHP sessions
- Apache `mod_rewrite`/`.htaccess`, or equivalent route mappings:
  - `/` -> `frontend/index.html`
  - `/admin` -> `admin/admin.html`
  - `/api/*` -> `backend/api.php`
  - `/styles.css`, `/app.js`, `/admin.css`, `/admin.js` -> their files under `frontend/` and `admin/`
- Writable `backend/data/` and `uploads/` directories
- PHP `upload_max_filesize` and `post_max_size` set to at least 6 MB

## Setup

1. Copy `backend/config.local.php.example` to `backend/config.local.php` on the server and set a unique admin password. The local config is excluded from Git.
2. Upload the project files. Keep the root and `backend/data/.htaccess` files in place so routes work and database files are not served publicly.
3. Give the PHP/web-server user write access to `backend/data/` and `uploads/`.
4. Open `/` for the storefront and `/admin` for product management. The first API request creates the SQLite database and seeds demo products if the table is empty.

If your host does not provide PDO_SQLITE, Fileinfo, or Apache-compatible URL rewrites, check its PHP extensions/rewrite settings. A MySQL adapter or host-specific rewrite rules can be supplied once the hosting provider is known.

The database file is `backend/data/products.sqlite`; uploaded product images are stored in `uploads/`. Back up both directories outside Git.
