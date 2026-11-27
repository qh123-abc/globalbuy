# PHP shared-hosting deployment

This version does not require Python or a continuously running application process. Upload the repository contents to the hosting account's web root.

## Requirements

- PHP 7.4 or newer
- PDO_SQLITE, PHP sessions, and either Fileinfo or `getimagesize` for image validation
- Apache `mod_rewrite`/`.htaccess`, or equivalent route mappings:
  - `/` -> `frontend/index.html`
  - `/admin` -> `admin/admin.html`
  - `/api/*` -> `backend/api.php`
  - `/styles.css`, `/app.js`, `/admin.css`, `/admin.js` -> their files under `frontend/` and `admin/`
- Writable `backend/data/` and `uploads/` directories
- PHP `upload_max_filesize` and `post_max_size` set to at least 6 MB

## Setup

1. Copy `backend/config.local.php.example` to `backend/config.local.php` on the server and set a unique admin password. The local config is excluded from Git.
2. Upload the project files. If the host honors `.htaccess`, the configured routes can serve the files from their separated folders. If it ignores rewrites, copy `frontend/index.html`, `frontend/styles.css`, and `frontend/app.js` to the web root as `index.html`, `styles.css`, and `app.js`; copy the three files under `admin/` to the web root as `admin.html`, `admin.css`, and `admin.js`. Keep the root `api.php` wrapper and `backend/data/.htaccess` in place.
3. Give the PHP/web-server user write access to `backend/data/` and `uploads/`.
4. Open `/` for the storefront and `/admin` for product management. The first API request creates the SQLite database and seeds demo products if the table is empty.

The frontend and admin call the API through `api.php?path=...`, so API requests do not depend on rewrite rules. If your host does not provide PDO_SQLITE, check its PHP extensions/settings. Image validation falls back to `getimagesize` when Fileinfo is unavailable.

The database file is `backend/data/products.sqlite`; uploaded product images are stored in `uploads/`. Back up both directories outside Git.
