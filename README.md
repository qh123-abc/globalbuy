# globalbuy · GlobalFind

GlobalFind is a product-discovery site with a public catalog, password-protected admin panel, image uploads, and a SQLite product database. The backend is implemented in PHP for shared hosting.

## Project layout

```text
frontend/   Storefront HTML, CSS, and JavaScript
admin/      Product-management HTML, CSS, and JavaScript
backend/    PHP API, SQLite database layer, and configuration
uploads/    Uploaded product images
.htaccess   Routes /, /admin, and /api/* on Apache
```

## Hosting requirements

- PHP 7.4+ with PDO_SQLITE, Fileinfo, and sessions enabled
- Apache with `mod_rewrite` and `.htaccess` support, or equivalent URL rewrites
- PHP write permission for `backend/data/` and `uploads/`
- PHP upload limit of at least 5 MB

The first API request creates `backend/data/products.sqlite` and seeds the demo catalog if the products table is empty. The database and uploaded images are ignored by Git. Back them up separately.

Each product can store separate links for Kakobuy, Litbuy, USFans, LoveGoBuy, HipoBuy, Oopbuy, Acbuy, Sugargoo, Hoobuy, Fishgoo, Joyagoo, BBDBuy, CNFans, Mulebuy, and AllChinaBuy. The storefront's **GET LINKS** button opens the agent chooser. Add the product-specific URLs in the product editor; unset links remain visible but disabled.

## Configure the admin password

On the server, copy `backend/config.local.php.example` to `backend/config.local.php` and replace the example password with a unique long password. This local config file is excluded from Git. If your host supports environment variables, set `GLOBALFIND_ADMIN_PASSWORD` instead.

Upload the project files into the site's document root with the included `.htaccess`. The storefront is at `/`, the admin panel is at `/admin`, and the API remains under `/api/*`.

## Checks

Run `sh tests/php-smoke.sh` on a machine with PHP CLI, PDO_SQLite, Fileinfo, and `curl`. The check starts a temporary PHP server and exercises storefront routing, seeded reads, login/session, product create/update/delete, upload handling, and database-file protection. GitHub Actions runs the same check on pushes and pull requests to `main`.
