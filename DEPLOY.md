# Self-hosting notes

The project can run on a Linux VPS with Python 3.9+ and no Python package installation. Run it as a dedicated unprivileged service account. Keep `HOST=127.0.0.1` when exposing it through a local reverse proxy or Cloudflare Tunnel.

Set these environment variables through the service manager, not in Git:

```text
GLOBALFIND_ADMIN_PASSWORD=<a unique strong password>
GLOBALFIND_DATA_DIR=/var/lib/globalfind
GLOBALFIND_COOKIE_SECURE=1
HOST=127.0.0.1
PORT=8000
```

Create `/var/lib/globalfind` and give write access to the service account. This directory holds `products.db` and uploaded product images. Back it up independently of the code repository.

For a temporary public HTTPS URL without a domain, Cloudflare Quick Tunnel can forward to `http://127.0.0.1:8000`. Its generated URL is temporary and stops working when the tunnel process stops. Treat the URL as public; keep the admin password private. For a permanent deployment, use a named tunnel or a domain with HTTPS and a regular service manager such as systemd.

The local development launcher `./run.sh` prompts for a password and starts on port 8000. For a VPS, configure the environment above and run `python3 backend/server.py` under the server's process manager instead.
