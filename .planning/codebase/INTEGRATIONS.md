# External Integrations

**Analysis Date:** 2024-12-19

## APIs & External Services

**Payment Processing:**
- No payment gateway integration detected (Stripe, PayPal not configured)
- Application models finances locally (credit cards, loans, transactions)
- PDF export: `barryvdh/laravel-dompdf` 3.1 available but not implemented (TODO in code)

**Authentication:**
- No OAuth/social login detected
- Standard Laravel authentication with email/password
- API tokens via Laravel Sanctum (configured but not actively used)

**GraphQL API:**
- Lighthouse 6.65 - GraphQL server at `/graphql` endpoint
- Schema: `graphql/schema.graphql` (41 lines, minimal implementation)
- Current queries: User lookup by ID or email, paginated user lists
- Status: Configured but not heavily integrated with frontend

## Data Storage

**Databases:**

**Primary:**
- Type/Provider: SQLite (default, configurable)
- Default Path: `database/database.sqlite`
- Connection: `sqlite` driver in `config/database.php`
- Client: Laravel Eloquent ORM
- Migrations: 63 migrations in `database/migrations/`

**Alternative Databases (configured but not active):**
- MySQL 5.7+ support (connection defined in `config/database.php`)
- PostgreSQL support (connection defined)
- Configure via `.env` file: `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD`, `DB_DATABASE`

**File Storage:**
- Default: Local filesystem only
- Location: `storage/app/` directory
- Configuration: `FILESYSTEM_DISK=local` in `.env`
- AWS S3 support configured but not enabled (`AWS_*` env vars optional)
- No file upload/retrieval UI integrated yet

**Caching:**
- Driver: Database-backed (`CACHE_STORE=database`)
- Connection: Uses default database connection
- Configuration: `config/cache.php`
- Cache table: `cache` and `cache_locks` created by migrations
- TTL: Configurable per cache operation

**Sessions:**
- Driver: Database-backed (`SESSION_DRIVER=database`)
- Connection: Uses default database connection
- Lifetime: 120 minutes (`SESSION_LIFETIME=120`)
- Table: `sessions` created by migration
- Configuration: `config/session.php`

**Queue:**
- Driver: Database-backed (`QUEUE_CONNECTION=database`)
- Connection: Uses default database connection
- Table: `jobs` and `failed_jobs` created by migrations
- Job example: `GenerateLoanPaymentsJob`
- Execution: Via `php artisan queue:listen`
- Configuration: `config/queue.php`

## Authentication & Identity

**Auth Provider:**
- Type: Custom (Laravel's built-in)
- Implementation:
  - Guard: `web` (session-based for Filament)
  - Guard: `api` (token-based via Sanctum, not used)
  - Provider: `users` (Eloquent model provider)
  - User model: `App\Models\User` extends `Illuminate\Foundation\Auth\User`

**User Roles & Permissions:**
- Package: `spatie/laravel-permission` 7.2
- Implementation: Role-based access control (RBAC)
- Models: User, Role, Permission with pivot tables
- Service: `PermissionService` (62 lines) - Role and permission management
- Trait: User model uses `HasRoles` from Spatie
- Configuration: `config/permission.php`

**Filament Authentication:**
- Built-in: Filament admin panel includes user/password login
- Redirect: Root path `/` redirects to `filament.admin.auth.login`
- Database-backed user accounts
- Password hashing: bcrypt (BCRYPT_ROUNDS=12, or 4 in tests)

## Monitoring & Observability

**Error Tracking:**
- Not integrated (no Sentry, Rollbar, etc.)
- Default: PHP errors logged to files
- Configuration: `config/logging.php`
- Channels: Stack driver (combines multiple loggers)

**Logs:**
- Approach: File-based logging
- Location: `storage/logs/` directory
- Driver: Single channel `single` (one log file per day)
- Level: `debug` in development (can be adjusted)
- Stack configuration: Combines multiple log channels
- Audit logging: `AuditLog` model tracks important events

**Blade Template Caching:**
- Compiled: `storage/framework/views/`
- Configuration: `config/view.php`

**Model Events:**
- Observed: 6 model observers track create/update/delete events
- Logged: `AuditLog` model captures model changes
- Side effects: Observers trigger service logic (balance updates, postings)

## CI/CD & Deployment

**Hosting:**
- Not configured (no .github/workflows, no CI pipeline)
- Manual deployment approach
- Docker support: Laravel Sail available (`laravel/sail` 1.41)
- Deployment target: Any server supporting PHP 8.2+ with web server (Apache/Nginx)

**CI Pipeline:**
- Not implemented
- No GitHub Actions workflows
- Manual testing via `php artisan test` command

**Deployment Checklist (inferred from composer.json):**
1. Clone repository
2. `composer install --no-dev` (production)
3. `.env` configuration (APP_KEY, DB_*, MAIL_*, etc.)
4. `php artisan key:generate` (if APP_KEY not set)
5. `php artisan migrate` (apply database migrations)
6. `npm install && npm run build` (build assets)
7. Point web server to `public/` directory
8. Set permissions: `storage/` and `bootstrap/cache/` writable

## Environment Configuration

**Required env vars:**
- `APP_NAME` - Application name (default: Laravel)
- `APP_ENV` - Environment: local, testing, production
- `APP_DEBUG` - Debug mode: true or false
- `APP_KEY` - 32-character encryption key (generated via `php artisan key:generate`)
- `APP_URL` - Application URL for link generation
- `DB_CONNECTION` - Database driver: sqlite, mysql, pgsql
- `DB_DATABASE` - Database name (for mysql/pgsql)
- `DB_USERNAME` - Database user (for mysql/pgsql)
- `DB_PASSWORD` - Database password (for mysql/pgsql)

**Optional env vars:**
- `DB_HOST`, `DB_PORT` - Database connection details
- `MAIL_MAILER` - Mail driver: log, smtp, sendmail, etc.
- `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` - Email sender
- `AWS_*` - AWS S3 credentials (if using file storage)
- `QUEUE_CONNECTION` - Queue driver: database, redis, sync
- `CACHE_STORE` - Cache driver: database, redis, array, file
- `SESSION_DRIVER` - Session driver: database, cookie, array
- `BROADCAST_CONNECTION` - Broadcast driver: log, null, redis
- `REDIS_HOST`, `REDIS_PORT` - Redis connection (if using Redis)

**Secrets location:**
- `.env` file (git-ignored, never committed)
- Environment variables on production server
- No `.env.production` file - same template everywhere, different values
- Database: `APP_KEY` most critical (encrypts cookies, cache, etc.)

## Webhooks & Callbacks

**Incoming:**
- Filament resources handle form submissions (no third-party webhooks)
- GraphQL endpoint at `/graphql` accepts queries (minimal use)
- No payment gateway webhooks configured
- No external API callbacks implemented

**Outgoing:**
- No third-party API calls detected (no HTTP client usage in code)
- Email notifications: Via mail driver (local logging in dev)
- Potential: Subscription service may send emails (not confirmed)

## Email Configuration

**Provider:**
- Driver: Log (saves to `storage/logs/` in dev)
- Configurable: SMTP, Sendmail, native, etc. via `MAIL_MAILER` env var
- From address: `MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME`

**Notifications:**
- No notification classes found in codebase
- Models have `Notifiable` trait available
- Potential: Subscription renewals, loan payment reminders (not implemented)

## API Rate Limiting

**Middleware:** `ApiRateLimitMiddleware` in `app/Http/Middleware/`
- Applied to API routes group in `routes/api.php`
- Implementation: Custom rate limiter (throttle rules)
- Config: Likely in middleware or Laravel's `ThrottleRequests` class

## Storage & CDN

**Local:**
- Path: `storage/app/` (configurable via Filesystem config)
- No CDN integration
- No image resizing or optimization
- File upload UI: Not visible in admin (likely manual seeding)

**Public Assets:**
- Built by Vite: `public/build/` directory
- Generated from: `resources/css/app.css`, `resources/js/` (if any)
- Manifest: `public/build/manifest.json` generated by Vite
- Served: Via web server directly from `public/`

## Third-Party Libraries

**Web Framework:**
- Laravel 12.0 - Application framework
- Filament 4.0 - Admin UI builder

**Database:**
- Eloquent ORM - Laravel's ORM
- Illuminate Database - Query builder

**GraphQL:**
- Lighthouse 6.65 - GraphQL server

**Authorization:**
- Spatie Laravel Permission 7.2 - RBAC

**Utilities:**
- Ziggy 2.0 - JavaScript route generation from Laravel routes
- Faker 1.23 - Fake data generation for testing
- Mockery 1.6 - Mocking for tests

**PDF:**
- Barryvdh Laravel DomPDF 3.1 - PDF generation (not yet implemented)

---

*Integration audit: 2024-12-19*
