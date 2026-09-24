# Deploy Fluxa — Self-hosted homelab

This is the current, actual deployment setup for Fluxa. `DEPLOY_RAILWAY.md` describes an
earlier/alternative Railway-based setup and is kept only as a legacy reference — it does not
reflect how UAT or production are actually run today.

## Current environments

### UAT

```
FLUXA UAT
├─ Hyper-V on a Windows PC
├─ VM: fluxa-uat (Ubuntu)
├─ LAN IP: 192.168.0.20
├─ Project path: /var/www/fluxa
├─ Nginx + PHP 8.4-FPM
├─ MySQL (local to the VM)
└─ https://fluxauat.nikoalteri.com
```

Deploys from the `uat` branch.

### Production

Not yet set up as a separate environment. When it exists, it should run on its own VM/service
with its own database — production must never share a database with UAT, especially given this
project handles real personal financial data. Deploys from the `main` branch once established.

## Automated deploy pipeline (UAT)

Every push to `uat` deploys automatically via `.github/workflows/deploy-uat.yml`, run by a
**self-hosted GitHub Actions runner installed on the `fluxa-uat` VM itself** (registered as
`fluxa-uat-runner`, systemd service `actions.runner.nikoalteri-second-brain.fluxa-uat-runner`,
started automatically on boot). It runs entirely inside the homelab LAN — a GitHub-hosted
runner can't reach `192.168.0.20` directly, so this is the only viable approach without exposing
SSH to the internet.

The workflow: a `mysqldump` backup of the UAT database (gzipped, saved to `~/db-backups` on the
VM) runs first, then `git fetch` + `git reset --hard origin/uat` in `/var/www/fluxa`,
`composer install --no-dev`, `npm ci && npm run build`, `php artisan migrate --force`, and
`config:cache`/`route:cache`/`view:cache`. The backup is deleted automatically only if every
step succeeds — if the deploy fails partway (e.g., a bad migration), the backup is left in
`~/db-backups` on the VM for manual recovery. No PHP-FPM reload is needed —
`opcache.enable=On` with `opcache.validate_timestamps=On` (revalidate every 2s) already picks
up changed files without a restart.

The scheduler (`loans:sync-installments` 01:50, `subscriptions:sync-renewals` 01:55,
`credit-cards:generate-cycles --issue-ready` 02:00 — see `routes/console.php` for the full
list) runs via a per-minute cron entry on the VM: `* * * * * cd /var/www/fluxa && php artisan
schedule:run >> /dev/null 2>&1`, installed in the `niko-server` user's crontab (no `sudo`
needed — per-user crontabs don't require it).

**Monitoring:** every run's status, logs, and history are on GitHub —
https://github.com/nikoalteri/second-brain/actions — and GitHub emails the repo owner
automatically on a failed run.

**Not yet automated / known gaps:**
- No queue worker service is running on the VM (`QUEUE_CONNECTION=database`) — only relevant if
  something starts dispatching queued jobs.
- No `main`/production deploy workflow yet — production isn't set up as its own environment
  (see above). Once it exists, the same self-hosted-runner pattern applies: a second runner on
  the prod host, labeled e.g. `fluxa-prod`, with a `deploy-prod.yml` watching `main`.

**Resolved incident (2026-09-17):** the first real pipeline run used `composer install
--no-dev`, which removed the `require-dev`-only `knuckleswtf/scribe` package. Its published
config, `config/scribe.php`, referenced Scribe's classes unconditionally, so Laravel's config
loading crashed on every request (UAT was down with a 500 until manually restored). Fixed at
the source: `config/scribe.php` now early-returns an empty array via a `class_exists()` guard
when the package isn't installed, verified directly on the VM (both with and without the dev
package present) before re-enabling `--no-dev` in the pipeline.

**Files kept off the server:** the deploy is a git checkout, so everything tracked would land on
the VM. `.github/workflows/deploy-uat.yml` therefore runs a `git sparse-checkout` that excludes
`graphify-out/`, `.graphifyignore`, `CLAUDE.md`, `AGENTS.md`, `.claude/`, `.planning/`,
`openspec/` and `.scribe/` (AI tooling, planning docs and API-doc caches). They stay in the
repository. Any future production workflow must apply the same exclusion.

## Manual deploy (fallback, if the pipeline is down)

On the `fluxa-uat` VM, from `/var/www/fluxa`, the same steps the workflow runs:

```bash
git pull origin uat
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
