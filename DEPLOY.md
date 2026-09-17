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

## Manual deploy steps (until automated)

On the `fluxa-uat` VM, from `/var/www/fluxa`:

```bash
git pull origin uat
composer install --optimize-autoloader --no-dev
npm install && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo systemctl reload php8.4-fpm
```

If the scheduler (`loans:sync-installments`, `subscriptions:sync-renewals`,
`credit-cards:generate-cycles --issue-ready`) isn't already wired into a system cron entry
calling `php artisan schedule:run` every minute, that needs setting up separately — see
`routes/console.php` for the full list of scheduled commands.

## Planned: automated deploy pipeline

Not yet implemented. Since the UAT VM is only reachable on the LAN (`192.168.0.20`, not exposed
to the internet for SSH), a GitHub-hosted Actions runner cannot reach it directly. The
likely approach is a **self-hosted GitHub Actions runner** installed on the homelab network
(e.g., on the `fluxa-uat` VM itself), with two workflows:

- `uat` branch push → self-hosted runner pulls, runs the steps above
- `main` branch push → deploys to production once a production environment exists

This needs to be scoped and built as its own piece of work — see the project's `.planning/`
GSD workflow for how new work gets discussed/planned here.
