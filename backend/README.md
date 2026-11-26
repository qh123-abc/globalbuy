# Backend

`server.py` uses Python's standard library, SQLite, and a small JSON API. It creates the local `products.db` on startup, seeds demo entries into an empty database, and writes uploaded images to `../uploads/`. Set the admin password with `run.sh`; product images and database files are local development data and are excluded from version control.
