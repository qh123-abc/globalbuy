#!/usr/bin/env python3
"""Small local GlobalFind catalog server (Python standard library only)."""
from http.server import ThreadingHTTPServer, SimpleHTTPRequestHandler
from urllib.parse import urlparse
import cgi
import hashlib
import hmac
import json
import mimetypes
import os
from pathlib import Path
import secrets
import sqlite3
import time

ROOT = Path(__file__).resolve().parent.parent
DATA_DIR = Path(os.environ.get("GLOBALFIND_DATA_DIR", str(ROOT))).resolve()
DB_PATH = DATA_DIR / "products.db"
UPLOAD_DIR = DATA_DIR / "uploads"
UPLOAD_DIR.mkdir(exist_ok=True)
ADMIN_PASSWORD = os.environ.get("GLOBALFIND_ADMIN_PASSWORD", "")
SESSIONS = {}
MAX_IMAGE_BYTES = 5 * 1024 * 1024
ALLOWED_IMAGES = {"image/jpeg": ".jpg", "image/png": ".png", "image/webp": ".webp", "image/gif": ".gif"}

def connect():
    db = sqlite3.connect(DB_PATH)
    db.row_factory = sqlite3.Row
    return db

def initialize():
    with connect() as db:
        db.execute("""CREATE TABLE IF NOT EXISTS products (
          id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL,
          category TEXT NOT NULL, price REAL NOT NULL DEFAULT 0,
          badge TEXT NOT NULL DEFAULT '', image TEXT NOT NULL DEFAULT '',
          supplier_url TEXT NOT NULL DEFAULT '', active INTEGER NOT NULL DEFAULT 1,
          created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)""")
        if db.execute("SELECT COUNT(*) FROM products").fetchone()[0] == 0:
            seed = [
              ("Vintage Runner Sneakers", "shoes", 34.9, "TOP PICK", "https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=700&q=80"),
              ("Everyday Zip Hoodie", "hoodies-sweaters", 28.5, "POPULAR", "https://images.unsplash.com/photo-1556821840-3a63f95609a7?auto=format&fit=crop&w=700&q=80"),
              ("Classic Straight Denim", "jeans-pants", 25.2, "", "https://images.unsplash.com/photo-1542272604-787c3835535d?auto=format&fit=crop&w=700&q=80"),
              ("Retro Court Low", "top-quality", 42, "TOP QUALITY", "https://images.unsplash.com/photo-1600185365483-26d7a4cc7519?auto=format&fit=crop&w=700&q=80"),
              ("Lightweight Puffer Jacket", "jackets-puffers", 49.8, "NEW FIND", "https://images.unsplash.com/photo-1544923246-77307dd654cb?auto=format&fit=crop&w=700&q=80"),
              ("Relaxed Fit Track Set", "tracksuits", 38.6, "", "https://images.unsplash.com/photo-1552902865-b72c031ac5ea?auto=format&fit=crop&w=700&q=80"),
              ("Everyday Cotton Tee", "t-shirts-longsleeves", 12.9, "UNDER $15", "https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?auto=format&fit=crop&w=700&q=80"),
              ("Minimal Crossbody Bag", "accessories", 18.4, "", "https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&w=700&q=80"),
              ("Retro High Top Canvas", "shoes", 31.5, "", "https://images.unsplash.com/photo-1525966222134-fcfa99b8ae77?auto=format&fit=crop&w=700&q=80"),
              ("Soft Knit Crewneck", "hoodies-sweaters", 32, "", "https://images.unsplash.com/photo-1576566588028-4147f3842f27?auto=format&fit=crop&w=700&q=80"),
              ("Utility Cargo Pants", "jeans-pants", 27.9, "COMMUNITY PICK", "https://images.unsplash.com/photo-1517445312882-bc9910d016b7?auto=format&fit=crop&w=700&q=80"),
              ("Everyday Running Shoes", "top-quality", 46, "", "https://images.unsplash.com/photo-1552346154-21d32810aba3?auto=format&fit=crop&w=700&q=80"),
              ("Classic Logo Cap", "accessories", 11.8, "", "https://images.unsplash.com/photo-1588850561407-ed78c282e89b?auto=format&fit=crop&w=700&q=80"),
              ("Relaxed Summer Shorts", "shorts", 16.5, "", "https://images.unsplash.com/photo-1591195853828-11db59a44f6b?auto=format&fit=crop&w=700&q=80"),
              ("Waterproof Shell Jacket", "jackets-puffers", 54, "NEW FIND", "https://images.unsplash.com/photo-1545594861-3bef43ff2fc8?auto=format&fit=crop&w=700&q=80"),
              ("Long Sleeve Heavy Tee", "t-shirts-longsleeves", 19.4, "", "https://images.unsplash.com/photo-1581655353564-df123a1eb820?auto=format&fit=crop&w=700&q=80"),
              ("Everyday Track Pants", "tracksuits", 22.5, "", "https://images.unsplash.com/photo-1552902865-b72c031ac5ea?auto=format&fit=crop&w=700&q=80"),
              ("Retro Skate Sneakers", "shoes", 36.8, "", "https://images.unsplash.com/photo-1525966222134-fcfa99b8ae77?auto=format&fit=crop&w=700&q=80"),
              ("Warm Fleece Pullover", "hoodies-sweaters", 29.5, "", "https://images.unsplash.com/photo-1620799140408-edc6dcb6d633?auto=format&fit=crop&w=700&q=80"),
              ("Classic Denim Shorts", "shorts", 18, "", "https://images.unsplash.com/photo-1598522325074-042db73aa4e6?auto=format&fit=crop&w=700&q=80")]
            db.executemany("INSERT INTO products(name,category,price,badge,image) VALUES(?,?,?,?,?)", seed)

def safe_json(handler, status, value):
    body = json.dumps(value, ensure_ascii=False).encode()
    handler.send_response(status)
    handler.send_header("Content-Type", "application/json; charset=utf-8")
    handler.send_header("Content-Length", str(len(body)))
    handler.send_header("Cache-Control", "no-store")
    handler.end_headers()
    handler.wfile.write(body)

class Handler(SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=str(ROOT), **kwargs)

    def translate_path(self, path):
        request_path = urlparse(path).path
        if request_path.startswith("/uploads/"):
            name = request_path[len("/uploads/"):]
            target = (UPLOAD_DIR / name).resolve()
            if name and Path(name).name == name and target.parent == UPLOAD_DIR.resolve():
                return str(target)
        return super().translate_path(path)

    def log_message(self, fmt, *args):
        print("%s - %s" % (self.address_string(), fmt % args))

    def body_json(self):
        length = min(int(self.headers.get("Content-Length", "0")), 1000000)
        return json.loads(self.rfile.read(length) or b"{}")

    def authenticated(self):
        token = self.cookies().get("gf_admin", "")
        expiry = SESSIONS.get(token, 0)
        return bool(token and expiry > time.time())

    def cookies(self):
        result = {}
        for part in self.headers.get("Cookie", "").split(";"):
            if "=" in part:
                k, v = part.strip().split("=", 1)
                result[k] = v
        return result

    def require_admin(self):
        if not self.authenticated():
            safe_json(self, 401, {"error": "Sign in to manage products."})
            return False
        return True

    def do_GET(self):
        path = urlparse(self.path).path
        if path == "/api/products":
            with connect() as db:
                rows = db.execute("SELECT id,name,category,price,badge,image,supplier_url FROM products WHERE active=1 ORDER BY id DESC").fetchall()
            return safe_json(self, 200, [dict(row) for row in rows])
        if path == "/api/admin/products":
            if not self.require_admin(): return
            with connect() as db:
                rows = db.execute("SELECT id,name,category,price,badge,image,supplier_url,active FROM products ORDER BY id DESC").fetchall()
            return safe_json(self, 200, [dict(row) for row in rows])
        if path == "/api/session":
            return safe_json(self, 200, {"authenticated": self.authenticated()})
        if path == "/healthz":
            return safe_json(self, 200, {"ok": True})
        aliases = {
            "/": "/frontend/index.html",
            "/index.html": "/frontend/index.html",
            "/admin": "/admin/admin.html",
            "/admin/": "/admin/admin.html",
            "/admin.html": "/admin/admin.html",
            "/styles.css": "/frontend/styles.css",
            "/app.js": "/frontend/app.js",
            "/admin.css": "/admin/admin.css",
            "/admin.js": "/admin/admin.js",
        }
        if path in aliases:
            self.path = aliases[path]
            return super().do_GET()
        if path.startswith("/uploads/"):
            name = path[len("/uploads/"):]
            target = (UPLOAD_DIR / name).resolve()
            if name and Path(name).name == name and target.parent == UPLOAD_DIR.resolve() and target.is_file():
                return super().do_GET()
        return safe_json(self, 404, {"error": "Not found"})

    def do_POST(self):
        path = urlparse(self.path).path
        if path == "/api/login":
            if not ADMIN_PASSWORD:
                return safe_json(self, 503, {"error": "Set GLOBALFIND_ADMIN_PASSWORD before starting the server."})
            try: password = self.body_json().get("password", "")
            except Exception: password = ""
            if not hmac.compare_digest(str(password), ADMIN_PASSWORD):
                return safe_json(self, 401, {"error": "Password is incorrect."})
            token = secrets.token_urlsafe(32)
            SESSIONS[token] = time.time() + 8 * 60 * 60
            self._login_token = token
            safe_json(self, 200, {"ok": True})
            return
        if path == "/api/admin/upload":
            if not self.require_admin(): return
            ctype = self.headers.get("Content-Type", "")
            if "multipart/form-data" not in ctype:
                return safe_json(self, 400, {"error": "Upload an image file."})
            form = cgi.FieldStorage(fp=self.rfile, headers=self.headers, environ={"REQUEST_METHOD":"POST","CONTENT_TYPE":ctype})
            field = form["image"] if "image" in form else None
            if not field or not getattr(field, "file", None):
                return safe_json(self, 400, {"error": "Choose an image first."})
            content_type = field.type or ""
            if content_type not in ALLOWED_IMAGES:
                return safe_json(self, 400, {"error": "Use JPG, PNG, WEBP, or GIF."})
            data = field.file.read(MAX_IMAGE_BYTES + 1)
            if len(data) > MAX_IMAGE_BYTES:
                return safe_json(self, 413, {"error": "Image must be 5 MB or smaller."})
            name = secrets.token_hex(16) + ALLOWED_IMAGES[content_type]
            (UPLOAD_DIR / name).write_bytes(data)
            return safe_json(self, 201, {"url": "/uploads/" + name})
        if path == "/api/admin/products":
            if not self.require_admin(): return
            try:
                data = self.body_json()
                name = str(data.get("name", "")).strip()[:180]
                category = str(data.get("category", "")).strip()[:50]
                price = float(data.get("price", 0))
                if not name or not category or price < 0: raise ValueError()
                values = (name, category, price, str(data.get("badge", ""))[:80], str(data.get("image", ""))[:1000], str(data.get("supplier_url", ""))[:1000], int(bool(data.get("active", True))))
                with connect() as db:
                    cursor = db.execute("INSERT INTO products(name,category,price,badge,image,supplier_url,active) VALUES(?,?,?,?,?,?,?)", values)
                    pid = cursor.lastrowid
                return safe_json(self, 201, {"id": pid})
            except Exception:
                return safe_json(self, 400, {"error": "Enter a product name, category, and valid price."})
        return safe_json(self, 404, {"error": "Not found"})

    def do_PUT(self):
        if urlparse(self.path).path.startswith("/api/admin/products/"):
            if not self.require_admin(): return
            try:
                pid = int(urlparse(self.path).path.rsplit("/", 1)[1]); d = self.body_json()
                vals = (str(d.get("name", "")).strip()[:180], str(d.get("category", "")).strip()[:50], float(d.get("price", 0)), str(d.get("badge", ""))[:80], str(d.get("image", ""))[:1000], str(d.get("supplier_url", ""))[:1000], int(bool(d.get("active", True))), pid)
                if not vals[0] or not vals[1] or vals[2] < 0: raise ValueError()
                with connect() as db: cur = db.execute("UPDATE products SET name=?,category=?,price=?,badge=?,image=?,supplier_url=?,active=? WHERE id=?", vals)
                return safe_json(self, 200 if cur.rowcount else 404, {"ok": bool(cur.rowcount)})
            except Exception: return safe_json(self, 400, {"error": "Invalid product data."})
        return safe_json(self, 404, {"error":"Not found"})

    def do_DELETE(self):
        path = urlparse(self.path).path
        if path == "/api/logout":
            token = self.cookies().get("gf_admin", ""); SESSIONS.pop(token, None)
            self.send_response(200); self.send_header("Set-Cookie", "gf_admin=; Path=/; Max-Age=0; HttpOnly; SameSite=Strict"); self.end_headers(); return
        if path.startswith("/api/admin/products/"):
            if not self.require_admin(): return
            try: pid = int(path.rsplit("/",1)[1])
            except ValueError: return safe_json(self,400,{"error":"Invalid product id"})
            with connect() as db: cur = db.execute("DELETE FROM products WHERE id=?", (pid,))
            return safe_json(self, 200 if cur.rowcount else 404, {"ok": bool(cur.rowcount)})
        return safe_json(self, 404, {"error":"Not found"})

    def end_headers(self):
        # Attach session cookie only to successful login responses.
        if self.path == "/api/login" and getattr(self, "_login_token", None):
            secure = "; Secure" if os.environ.get("GLOBALFIND_COOKIE_SECURE", "0") == "1" else ""
            self.send_header("Set-Cookie", "gf_admin=%s; Path=/; HttpOnly; SameSite=Strict; Max-Age=28800%s" % (self._login_token, secure))
        super().end_headers()

    def do_OPTIONS(self):
        self.send_response(204); self.end_headers()

initialize()
if not ADMIN_PASSWORD:
    print("Set GLOBALFIND_ADMIN_PASSWORD to enable admin login.")
host = os.environ.get("HOST", "127.0.0.1")
server = ThreadingHTTPServer((host, int(os.environ.get("PORT", "8000"))), Handler)
print("GlobalFind running at http://127.0.0.1:%s" % server.server_port)
server.serve_forever()
