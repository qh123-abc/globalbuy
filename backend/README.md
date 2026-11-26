# PHP backend

`api.php` implements the existing JSON API routes for catalog reads, admin login/session, product CRUD, and image uploads. `database.php` initializes SQLite and the demo catalog through PDO_SQLITE. `config.php` reads the admin password and data path from environment variables or the untracked `config.local.php` file.

Required PHP extensions: PDO_SQLITE, Fileinfo, and sessions. The PHP process must be able to write to `data/` and the root `uploads/` directory. `data/.htaccess` blocks direct HTTP access to the SQLite database on Apache.
