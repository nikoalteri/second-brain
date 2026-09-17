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
`composer install` (see note below on why **not** `--no-dev`), `npm ci && npm run build`,
`php artisan migrate --force`, and `config:cache`/`route:cache`/`view:cache`. The backup is
deleted automatically only if every step succeeds — if the deploy fails partway (e.g., a bad
migration), the backup is left in `~/db-backups` on the VM for manual recovery. No PHP-FPM
reload is needed — `opcache.enable=On` with `opcache.validate_timestamps=On` (revalidate every
2s) already picks up changed files without a restart.

**Monitoring:** every run's status, logs, and history are on GitHub —
https://github.com/nikoalteri/second-brain/actions — and GitHub emails the repo owner
automatically on a failed run.

**Not yet automated / known gaps:**
- The scheduler (`loans:sync-installments`, `subscriptions:sync-renewals`,
  `credit-cards:generate-cycles --issue-ready`) has no cron entry calling
  `php artisan schedule:run` yet on the VM — see `routes/console.php` for the full list.
- No queue worker service is running on the VM (`QUEUE_CONNECTION=database`) — only relevant if
  something starts dispatching queued jobs.
- No `main`/production deploy workflow yet — production isn't set up as its own environment
  (see above). Once it exists, the same self-hosted-runner pattern applies: a second runner on
  the prod host, labeled e.g. `fluxa-prod`, with a `deploy-prod.yml` watching `main`.
- **`composer install --no-dev` currently breaks the app.** `config/scribe.php` (published by
  the `require-dev`-only `knuckleswtf/scribe` package) references Scribe's classes
  unconditionally; removing dev packages leaves those classes undefined, which crashes
  Laravel's config loading on every request (confirmed live on 2026-09-17: the first real
  pipeline run took UAT down with a 500 until `composer install` — full deps — was re-run
  manually). Until `config/scribe.php` is made to tolerate the package being absent (or scribe
  is moved out of `require-dev`), deploys install full dependencies, dev tools included.

## Manual deploy (fallback, if the pipeline is down)

On the `fluxa-uat` VM, from `/var/www/fluxa`, the same steps the workflow runs:

```bash
git pull origin uat
composer install --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
