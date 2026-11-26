# GlobalFind

GlobalFind is a small product-discovery site with a storefront, password-protected admin panel, image uploads, and a SQLite catalog.

## Project layout

```text
frontend/   Public storefront page, styles, and browser interactions
admin/      Product management page, styles, and browser interactions
backend/    Python HTTP server and SQLite/API logic
uploads/    Product images uploaded through the admin panel (local only)
products.db Local SQLite database, created on first server start (not committed)
run.sh      Local server launcher
```

## Run locally

Requires Python 3.9 or newer. No third-party Python packages are needed.

```sh
./run.sh
```

Choose an admin password when prompted. Open `http://127.0.0.1:8000/` for the storefront or `http://127.0.0.1:8000/admin` to manage products. The first run seeds the local database with demo entries. Admin uploads accept JPG, PNG, WEBP, and GIF images up to 5 MB.

## Data and deployment

The local `products.db` and uploaded image files are intentionally excluded from Git so private catalog data and uploads are not published with source code. Back them up separately. This Python server is for local preview; Netlify static hosting cannot run it or persist its SQLite and local upload files. A hosted deployment needs a persistent database and object storage, plus production authentication and HTTPS.
