# AIMM on the Mac Mini

Install guide for running AIMM on a Mac Mini, reachable from other machines over Tailscale.

This differs from a laptop/desktop install in two ways:

1. **The web app is exposed over the tailnet** so other machines can use it.
2. **The database stays on loopback** — only the app needs to be reachable, never MySQL.

> Verified against `main` (commit `5ca2829`). All stack images — `php:8.5-fpm`, `mysql:8.0`,
> `nginx:1.27`, `gotenberg/gotenberg:8`, `node:22-alpine`, and the `composer:2` build stage —
> publish **arm64** manifests, so this runs natively on Apple Silicon. No Rosetta, no
> `platform: linux/amd64` overrides.

---

## 1. Prerequisites

| Requirement | Check | Install |
|---|---|---|
| Docker Desktop (Compose v2) | `docker compose version` | https://docker.com/products/docker-desktop |
| Tailscale | `tailscale status` | https://tailscale.com/download/mac |
| Git | `git --version` | Xcode CLT: `xcode-select --install` |

In **Docker Desktop → Settings**:

- **General → Start Docker Desktop when you sign in** — required, or the stack won't come back after a reboot.
- **General → Virtual Machine Options → VirtioFS** — noticeably faster bind mounts than gRPC FUSE.
- **Resources** — give it at least 4 GB RAM and 30 GB disk. The images total roughly 3 GB
  (Gotenberg alone bundles Chromium and LibreOffice), plus ~250 MB of `node_modules` if you build the docs.

### Unattended operation — read this before deploying headless

**Docker Desktop is a user application, not a system daemon.** It starts when a user *signs in*, not
when the machine boots. On a headless, always-on Mac Mini that means after a power cut the machine
sits at the login window with AIMM completely down — `restart: unless-stopped` cannot help, because
the Docker engine itself isn't running.

To survive an unattended reboot you need **both**:

1. **System Settings → Users & Groups → Automatic login** set to the account that runs Docker.
2. **FileVault disabled** — with FileVault on, macOS requires the disk to be unlocked at the login
   screen before any user can auto-log-in, so automatic login is unavailable. This is a real
   trade-off: disk encryption versus unattended restart. Decide deliberately.

If you must keep FileVault, plan on someone unlocking the Mac after every power loss, or use a
`launchd` agent plus a Docker CE / Colima setup that runs without Docker Desktop's GUI session.

Verify it actually works before you rely on it: reboot the Mac Mini, wait, then from another machine
check that the app answers.

Install Tailscale from the **standalone package**, not the Mac App Store version, if you want
`tailscale` on your `PATH`. Otherwise the CLI lives at:

```bash
alias tailscale="/Applications/Tailscale.app/Contents/MacOS/Tailscale"
```

---

## 2. Pick host ports

Two host ports are needed: one for the web app, one for MySQL. Check what's free first:

```bash
lsof -nP -iTCP -sTCP:LISTEN | awk '{print $1, $9}' | sort -u
```

Defaults below are `8510` (web) and `3308` (MySQL). Adjust in `.env` if either is taken.

macOS-specific things to watch for:

- **AirPlay Receiver** binds port **5000** and **7000**. Not used here, but it breaks other stacks.
- A **Homebrew MySQL** would already hold **3306**. The stack publishes 3308, so no clash.

---

## 3. Clone

```bash
mkdir -p ~/dev/aim
git clone --branch main https://github.com/efhsg/aimm.git ~/dev/aim/aimm
cd ~/dev/aim/aimm
```

**Keep the checkout under `/Users`.** Docker Desktop only shares `/Users`, `/Volumes`, `/private`,
and `/tmp` with the VM by default. Cloning to `/opt/dev/aim/aimm` — mirroring a Linux host — makes
every bind mount fail or silently mount empty. If you need a path outside those roots, add it under
**Docker Desktop → Settings → Resources → File sharing** first.

---

## 4. Configure `.env`

```bash
cp .env.example .env
```

Edit `.env`:

| Key | Value | Notes |
|---|---|---|
| `USER_ID` | `1000` | **Leave the default on macOS.** Docker Desktop maps bind-mount ownership transparently. Only change this if you hit permission errors (see Troubleshooting). |
| `USER_NAME` | `appuser` | Leave the default. |
| `NGINX_PORT` | `8510` | Or whatever you picked above. |
| `DB_PORT` | `3308` | **Must be set explicitly** — the compose file falls back to `3307` when unset. |
| `COOKIE_VALIDATION_KEY` | `openssl rand -hex 32` | Replace `dev-only-change-me`. |
| `ADMIN_USERNAME` | your choice | Admin UI login. |
| `ADMIN_PASSWORD` | **strong, generated** | See warning below. |
| `DB_ROOT_PASSWORD`, `DB_PASSWORD`, `DB_ADMIN_PASSWORD` | **change from defaults** | |
| `FMP_API_KEY` | your key | Data collection fails without a real key. Everything else works. |

Generate the secrets:

```bash
echo "COOKIE_VALIDATION_KEY=$(openssl rand -hex 32)"
echo "ADMIN_PASSWORD=$(openssl rand -base64 18 | tr -d '/+=' | cut -c1-24)"
```

> **Change `ADMIN_PASSWORD` from `changeme`.** Unlike a localhost-only install, this box will be
> reachable by every machine on your tailnet. AIMM's admin auth is HTTP Basic against these env
> vars — there is no account lockout, no rate limiting, and no second factor. The password is the
> entire access control.

`.env` is gitignored. Never commit it.

---

## 5. Local overrides: Tailscale exposure + auto-restart

Create `docker-compose.override.yml` in the repo root. Compose loads it automatically on top of
`docker-compose.yml`, so the tracked file stays pristine and survives `git pull`.

```yaml
# Local-only overrides for the Mac Mini. Not committed.
services:
  aimm_nginx:
    # Loopback only. Tailscale exposure is handled by `tailscale serve` (step 7),
    # which terminates HTTPS and proxies here.
    ports: !override
      - "127.0.0.1:${NGINX_PORT:-8510}:80"
    restart: unless-stopped

  aimm_mysql:
    # Never expose the database beyond this machine.
    ports: !override
      - "127.0.0.1:${DB_PORT:-3308}:3306"
    restart: unless-stopped

  aimm_yii:
    restart: unless-stopped
```

Two things to know about this file:

- **`!override` is required.** Compose *appends* to list fields like `ports` by default, so without
  the tag you'd end up with both `0.0.0.0:8510` and `127.0.0.1:8510` published — a port collision.
  `!override` replaces the list instead.
- **`restart: unless-stopped`** makes the stack survive reboots. Upstream sets this only on
  `gotenberg`; without it the other three stay down after a restart.

Keep it out of git without touching the tracked `.gitignore`:

```bash
echo 'docker-compose.override.yml' >> .git/info/exclude
```

Verify the merge resolved correctly — you should see exactly **one** port entry per service, each with
`host_ip: 127.0.0.1`:

```bash
docker compose config | grep -A5 'ports:'
```

---

## 6. Build and start

```bash
mkdir -p data/db/mysql yii/runtime/{logs,datapacks,reports}
chmod +x docker/init-scripts/init-databases.sh yii/yii

docker compose up -d --build      # first build takes several minutes
docker compose ps                 # wait for aimm_mysql + aimm_gotenberg = healthy

docker compose exec -T aimm_yii composer install
docker compose exec -T aimm_yii ./yii migrate --interactive=0
docker compose exec -T -e YII_ENV=test aimm_yii ./yii migrate --interactive=0
```

The `aimm_yii` container gates on both MySQL and Gotenberg being healthy, so the stack sequences
itself. `composer.lock` is committed, so `composer install` is reproducible.

The MySQL init script (creating `aimm_test` and the scoped grants) runs **only on first
initialization**. If you later change DB names or passwords, you must wipe `data/db/mysql` to re-run it.

Confirm it works locally before exposing it:

```bash
curl -sS -o /dev/null -w '%{http_code}\n' http://localhost:8510/      # 200
docker compose exec -T aimm_yii php yii test/db                       # DB OK: 1
```

---

## 7. Expose over Tailscale

Use **`tailscale serve`**. It proxies the tailnet to your loopback port, so Docker keeps its safe
binding and you get real HTTPS with a valid certificate.

```bash
tailscale serve --bg --https=443 http://127.0.0.1:8510
tailscale serve status
```

That publishes the app at:

```
https://<machine-name>.<your-tailnet>.ts.net/
```

`tailscale serve status` prints the exact URL. The explicit `--https=443 http://...` form above is
the one in Tailscale's documentation and works across versions. Recent releases also accept a
shorthand (`tailscale serve --bg 8510`), but don't rely on it — check `tailscale serve --help` for
what your version supports.

Requirements: **MagicDNS** and **HTTPS Certificates** must be enabled in the Tailscale admin console
(DNS page). Both are on by default for new tailnets.

`--bg` stores the mapping in tailscaled's own configuration, so it is restored when the daemon
restarts — you should not need to re-run it after a reboot. Confirm with `tailscale serve status`
once after your first restart rather than assuming it.

### Known wrinkle: the `/docs` redirect downgrades to HTTP

`tailscale serve` terminates TLS and forwards **plain HTTP** to nginx, so nginx's `$scheme` is
`http` even though the browser is on HTTPS. One rule in `nginx.conf.template` builds an absolute
redirect from it:

```nginx
location = /docs {
    return 301 $scheme://$http_host$uri/;
}
```

So `https://<machine>.<tailnet>.ts.net/docs` (no trailing slash) redirects to
`http://<machine>.<tailnet>.ts.net/docs/` — a protocol downgrade to a port nothing is listening on.
Verified: the response is `Location: http://.../docs/`.

**Workaround:** always use the trailing slash — `…/docs/` skips the redirect entirely and works fine.

**Proper fix**, if you want the bare `/docs` link to work: copy the template, patch it, and mount the
copy from your override so the tracked file stays clean.

```bash
cp nginx.conf.template nginx.macmini.conf.template
echo 'nginx.macmini.conf.template' >> .git/info/exclude
```

In the copy, add this inside the `http { }` block, above `server {`:

```nginx
map $http_x_forwarded_proto $fwd_scheme {
    default $scheme;
    https   https;
}
```

change the redirect to use it:

```nginx
return 301 $fwd_scheme://$http_host$uri/;
```

then mount it in `docker-compose.override.yml` under `aimm_nginx`:

```yaml
    volumes:
      - ./nginx.macmini.conf.template:/etc/nginx/nginx.conf.template:ro
```

This affects only that one convenience redirect. Every other route uses relative URLs and is
unaffected by the proxy.

> **Never use `tailscale funnel`.** Funnel publishes to the *public internet*. `serve` stays inside
> your tailnet. AIMM has no rate limiting and parts of it are unauthenticated (see Security notes) —
> it is not built to face the open internet.

### Why this over binding to the Tailscale IP

You could instead publish directly on the tailnet address:

```yaml
    ports: !override
      - "127.0.0.1:${NGINX_PORT:-8510}:80"
      - "${TAILSCALE_IP}:${NGINX_PORT:-8510}:80"     # e.g. 100.x.y.z, add to .env
```

It works, but has two real drawbacks on an always-on Mac Mini:

- **Startup race.** If Docker starts before the Tailscale daemon has assigned the interface address,
  the bind fails with `cannot assign requested address` and the container won't start. On an
  unattended reboot that means the stack is silently down.
- **Plain HTTP.** Traffic is still encrypted by WireGuard inside the tailnet, so credentials aren't
  exposed — but you get no browser TLS, no certificate, and a `http://100.x.y.z:8510` URL instead of
  a stable name.

`tailscale serve` has neither problem. Prefer it unless you specifically need raw TCP.

### Restrict who can reach it (optional)

Tailnet-wide access is the default. To limit AIMM to specific users or tagged devices, add an ACL
in the Tailscale admin console:

```jsonc
{
  "acls": [
    { "action": "accept", "src": ["group:aimm-users"], "dst": ["tag:aimm-server:443"] }
  ]
}
```

---

## 8. Verify

Run on the Mac Mini:

```bash
docker compose ps                                                     # 4 running, 2 healthy
docker compose exec -T aimm_yii php yii test/index                    # AIMM is ready. / PHP 8.5.x
docker compose exec -T aimm_yii php yii test/db                       # DB OK: 1
docker compose exec -T gotenberg curl -sf localhost:3000/health       # {"status":"up",...}
curl -sS -o /dev/null -w '%{http_code}\n' http://localhost:8510/      # 200
docker compose exec -T aimm_yii php -d register_argc_argv=1 vendor/bin/codecept run unit
```

The unit suite should report **OK (572 tests, 1882 assertions)**.

Confirm the database is *not* reachable from elsewhere — run this from **another** machine, where
`<tailscale-ip>` is the Mac Mini's:

```bash
nc -zv <tailscale-ip> 3308     # must FAIL — connection refused
curl -sS -o /dev/null -w '%{http_code}\n' https://<machine>.<tailnet>.ts.net/   # 200
```

If port 3308 answers from another machine, your override didn't apply. Re-check `docker compose config`.

---

## 9. Optional: build the docs site

The repo ships a VitePress site that nginx already serves at `/docs/` — it just needs building.
Do it in a container so you don't need Node on the Mac:

```bash
docker compose --profile tools run --rm --user "$(id -u):$(id -g)" \
  -e HOME=/tmp -e npm_config_cache=/tmp/.npm -w /app/site npm npm ci

docker compose --profile tools run --rm --user "$(id -u):$(id -g)" \
  -e HOME=/tmp -e npm_config_cache=/tmp/.npm -w /app/site npm npm run build
```

Then `https://<machine>.<tailnet>.ts.net/docs/` serves it. The `--user` flag keeps `node_modules`
owned by you rather than root; `HOME`/`npm_config_cache` are needed because a non-root user in
`node:22-alpine` has no writable home for npm's cache.

Adds about 250 MB. `site/node_modules` and `site/.vitepress/dist` are already gitignored.

---

## 10. Security notes

Read these before letting others onto the box.

**Everything rides on `ADMIN_PASSWORD`.** Auth is HTTP Basic checked against env vars
(`yii/src/filters/AdminAuthFilter.php`). There is no user table, no roles, no RBAC, no lockout, and
no rate limiting — access is binary. It is fail-closed: blank credentials deny all admin routes
rather than opening them.

**Not everything is behind auth:**

| Route | Auth |
|---|---|
| `/data-source/*`, `/industry/*`, `/collection-policy/*`, `/collection-run/*` | admin required |
| `/` and `/dashboard/*` | **public** |
| `/health/*` | **public** |
| `/report/*` — includes `preview`, `generate`, `download` | **public** |

`ReportController` has no auth filter, so generated reports are downloadable by anyone who can reach
the app. On a localhost-only install that's academic; **on a tailnet-exposed Mac Mini it means every
device on your tailnet can read generated reports.** If that matters, add the filter to
`yii/src/controllers/ReportController.php`, mirroring the four protected controllers:

```php
use app\filters\AdminAuthFilter;

public function behaviors(): array
{
    return ['auth' => ['class' => AdminAuthFilter::class]];
}
```

**Yii does not trust proxy headers.** `yii/config/web.php` leaves `request.trustedHosts` at its
default (empty), so `X-Forwarded-Proto: https` from `tailscale serve` is ignored and
`isSecureConnection()` reports false. Harmless as the app stands — it generates only relative URLs.
But if you later add absolute-URL generation, scheme-aware redirects, or `secure` cookies, set
`trustedHosts` on the request component or they'll emit `http://` links on an HTTPS page.

The nginx → PHP-FPM hop passes the `Authorization` header correctly (verified on a Linux install:
401 without credentials, 200 with). The extra `tailscale serve` → nginx hop has **not** been tested
on real hardware — confirm the admin login prompt works over the tailnet URL as part of step 8.

**The console has no auth at all.** Anyone with shell or SSH access to the Mac Mini can run
`docker compose exec aimm_yii ./yii ...` and drive the entire pipeline. Shell access *is* full
admin access — secure the Mac Mini account accordingly.

---

## 11. Day-to-day

```bash
docker compose up -d                  # start
docker compose down                   # stop
docker compose logs -f aimm_yii       # logs
docker compose exec aimm_yii bash     # shell

tailscale serve status                # check tailnet exposure
tailscale serve reset                 # stop exposing (clears all serve config)

# Update to latest main
git pull && docker compose up -d --build
docker compose exec -T aimm_yii composer install
docker compose exec -T aimm_yii ./yii migrate --interactive=0

# Destructive DB reset (wipes all data, re-runs init scripts)
docker compose down -v && rm -rf data/db/mysql && docker compose up -d
```

Full pipeline from the CLI (no web login needed):

```bash
docker compose exec -T aimm_yii ./yii seed/us-tech-giants
docker compose exec -T aimm_yii ./yii collect/industry us-tech-giants
docker compose exec -T aimm_yii ./yii analyze/industry us-tech-giants
```

---

## 12. Troubleshooting

| Symptom | Cause / fix |
|---|---|
| `service "aimm_gotenberg" is not running` | The repo docs use the *container* name. The compose **service** is `gotenberg`. Use `docker compose exec -T gotenberg ...` or `docker exec aimm_gotenberg ...`. |
| `Bind for 0.0.0.0:3307 failed: port is already allocated` | `DB_PORT` isn't set in `.env`, so compose fell back to `3307`. Set it explicitly. |
| Both `0.0.0.0` and `127.0.0.1` published | You omitted `!override`; compose appended instead of replacing. |
| `cannot assign requested address` on start | You bound directly to the Tailscale IP and Docker started before Tailscale. Use `tailscale serve` instead. |
| Permission denied writing `yii/vendor` | Rare on macOS. Set `USER_ID=$(id -u)` and `USER_NAME=$(id -un)` in `.env`, then `docker compose build --no-cache aimm_yii`. |
| MySQL init changes ignored | Init scripts run only on first boot. `docker compose down -v && rm -rf data/db/mysql`. |
| Stack gone after reboot | Docker Desktop isn't set to start at login, or `restart: unless-stopped` is missing from the override. On a headless box also check automatic login / FileVault — see "Unattended operation" in step 1. |
| Bind mounts empty, or `mounts denied` | Checkout is outside Docker Desktop's shared roots. Keep it under `/Users`, or add the path in Settings → Resources → File sharing. |
| Tailnet URL doesn't resolve | Enable MagicDNS + HTTPS Certificates in the Tailscale admin console. |
| `https://…/docs` redirects to `http://` and hangs | Known nginx `$scheme` issue behind a TLS-terminating proxy. Use the trailing slash `…/docs/`, or apply the patched template in step 7. |
| `tailscale serve` rejects the command | Version differences. Use the explicit `--https=443 http://127.0.0.1:8510` form; check `tailscale serve --help`. |
| Slow file access / high CPU | Switch Docker Desktop to VirtioFS. |
| `/docs/` returns 404 | The VitePress site isn't built — see step 9. |
