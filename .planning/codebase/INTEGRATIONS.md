# External Integrations

**Analysis Date:** 2025-04-21

## APIs & External Services

**Email Services:**
- Postmark - Optional email delivery service
  - SDK/Client: Built-in via `config/services.php`
  - Auth: `POSTMARK_API_KEY` environment variable
  - Usage: Alternative to log-based mail for production

- Resend - Optional email delivery service
  - SDK/Client: Built-in via `config/services.php`
  - Auth: `RESEND_API_KEY` environment variable
  - Usage: Alternative email provider

**Cloud Messaging:**
- Slack - Notifications capability
  - SDK/Client: Built-in via `config/services.php`
  - Auth: `SLACK_BOT_USER_OAUTH_TOKEN`, `SLACK_BOT_USER_DEFAULT_CHANNEL`
  - Usage: Optional integration for notifications

**Cloud Storage (Conditional):**
- AWS S3 - File storage option
  - SDK/Client: AWS SDK via Laravel's S3 filesystem driver
  - Auth: `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`
  - Region: `AWS_DEFAULT_REGION` (default: us-east-1)
  - Bucket: `AWS_BUCKET`
  - Note: Currently `FILESYSTEM_DISK=local` in default config

## Data Storage

**Databases:**
- SQLite (default development)
  - Connection: `DB_CONNECTION=sqlite`
  - File: `database/database.sqlite`
  - Client: Laravel Eloquent ORM
  - Foreign key constraints: Enabled

- MySQL 8.0+ (production option)
  - Connection: `DB_CONNECTION=mysql`
  - Client: Laravel Eloquent ORM via PDO
  - Configuration: `config/database.php`

- MariaDB (production option)
  - Connection: `DB_CONNECTION=mariadb`
  - Client: Laravel Eloquent ORM via PDO
  - Configuration: `config/database.php`

- PostgreSQL (production option)
  - Connection: `DB_CONNECTION=pgsql`
  - Client: Laravel Eloquent ORM via PDO
  - Configuration: `config/database.php`

- SQL Server (production option)
  - Connection: `DB_CONNECTION=sqlsrv`
  - Client: Laravel Eloquent ORM via PDO
  - Configuration: `config/database.php`

**File Storage:**
- Local filesystem (default: `storage/app/`)
  - `FILESYSTEM_DISK=local`
  - Can be switched to AWS S3 via environment configuration

**Caching:**
- Database cache (default)
  - `CACHE_STORE=database`
  - Stores cache in database table
  - Optional: Redis, Memcached configured but not default

- Redis (optional)
  - `REDIS_HOST=127.0.0.1`
  - `REDIS_PORT=6379`
  - `REDIS_PASSWORD=null` (default)
  - Client: phpredis
  - Separate cache database: `REDIS_CACHE_DB=1`
  - Default database: `REDIS_DB=0`

- Memcached (optional)
  - `MEMCACHED_HOST=127.0.0.1`
  - Not enabled by default

## Authentication & Identity

**Auth Provider:**
- Laravel Session-based Authentication (custom)
  - Implementation: Filament's built-in authentication with Laravel Sanctum
  - Guards: `web` (session-based)
  - User model: `App\Models\User`
  - Provider: Eloquent with users table

**Authorization:**
- Spatie/Laravel-Permission
  - Roles and permissions stored in database
  - Tables: `roles`, `permissions`, `role_has_permissions`, `model_has_permissions`, `model_has_roles`
  - Policies: `app/Policies/` for resource authorization

**Session Management:**
- Driver: Database (`SESSION_DRIVER=database`)
- Lifetime: 120 minutes (`SESSION_LIFETIME=120`)
- Encryption: Disabled (`SESSION_ENCRYPT=false`)
- Storage table: `sessions`

**API Authentication:**
- Laravel Sanctum (installed but not actively used in web routes)
  - Available for token-based API authentication if needed
  - Can issue API tokens for external API consumers

## Monitoring & Observability

**Error Tracking:**
- Not configured/integrated
- Application relies on Laravel's default exception handling

**Logs:**
- Driver: Stack (`LOG_CHANNEL=stack`)
- Configuration: `config/logging.php`
- Default: Single log file with debug level
- Available: Syslog, errorlog, and other drivers configurable

**Debug Tools (Development Only):**
- Laravel Pail - Console log monitoring
  - Run: `php artisan pail --timeout=0`
  - Shows real-time logs and exceptions

## Queue & Job Processing

**Queue Connection:**
- Default: Database (`QUEUE_CONNECTION=database`)
- Storage table: `jobs` (configurable via `DB_QUEUE_TABLE`)
- Retry after: 90 seconds (configurable via `DB_QUEUE_RETRY_AFTER`)

**Implemented Jobs:**
- `App\Jobs\GenerateLoanPaymentsJob` - Generates scheduled loan payment records
  - Dispatched for loan management workflows
  - Implements `ShouldQueue` interface

**Async Operations:**
- Model observers trigger on create/update/delete:
  - `App\Observers\TransactionObserver` - Transaction lifecycle
  - `App\Observers\LoanPaymentObserver` - Loan payment lifecycle
  - `App\Observers\CreditCardCycleObserver` - Credit card cycle lifecycle
  - `App\Observers\CreditCardPaymentObserver` - Credit card payment lifecycle
  - `App\Observers\CreditCardExpenseObserver` - Credit card expense lifecycle
  - `App\Observers\SubscriptionObserver` - Subscription lifecycle

**Queue Workers (Development):**
- Run via: `php artisan queue:listen --tries=1 --timeout=0`
- Part of dev server startup in composer script

## Broadcasting & Real-time

**Broadcast Driver:**
- Log driver (`BROADCAST_CONNECTION=log`)
- No real-time WebSocket implementation configured

## Environment Configuration

**Required env vars (from `.env.example`):**
- `APP_NAME=Fluxa` - Application name
- `APP_ENV=local` - Environment (local/production)
- `APP_KEY` - Laravel encryption key (generated on setup)
- `APP_DEBUG=true` - Debug mode (false in production)
- `APP_URL=http://localhost` - Application URL
- `DB_CONNECTION=sqlite` - Database driver
- `SESSION_DRIVER=database` - Session storage
- `QUEUE_CONNECTION=database` - Queue driver
- `CACHE_STORE=database` - Cache driver
- `MAIL_MAILER=log` - Mail driver (log in development)
- `FILESYSTEM_DISK=local` - File storage disk

**Email Configuration (if using external services):**
- `MAIL_FROM_ADDRESS` - Sender email address
- `MAIL_FROM_NAME` - Sender name
- Optional: `POSTMARK_API_KEY`, `RESEND_API_KEY`, `SLACK_BOT_USER_OAUTH_TOKEN`

**Cloud Storage (if using S3):**
- `AWS_ACCESS_KEY_ID`
- `AWS_SECRET_ACCESS_KEY`
- `AWS_DEFAULT_REGION` (default: us-east-1)
- `AWS_BUCKET`

**Secrets location:**
- `.env` file (Git-ignored, created from `.env.example` during setup)
- Environment variables for deployed instances
- Set via deployment platform (Heroku, Laravel Forge, etc.) in production

## Webhooks & Callbacks

**Incoming:**
- API routes available at `/api/*` (see `routes/api.php`)
- Rate limited via `ApiRateLimitMiddleware`
- Currently no specific webhook endpoints implemented for external services

**Outgoing:**
- No configured outgoing webhooks to external services
- Email notifications via configured mail driver (Postmark/Resend optional)
- Slack notifications capability available if configured

## GraphQL API

**Schema:**
- Location: `graphql/schema.graphql`
- Framework: Lighthouse 6.65
- Endpoint: `/graphql` (configured in `config/lighthouse.php`)
- Authentication: Supports token/session-based auth via Lighthouse middleware

**Middleware:**
- `Nuwave\Lighthouse\Http\Middleware\AcceptJson` - Ensures JSON responses
- `Nuwave\Lighthouse\Http\Middleware\AttemptAuthentication` - Attempts to authenticate user
- CORS and XHR middleware available but disabled by default

**Client Library:**
- Axios 1.11.0 - JavaScript HTTP client available for frontend GraphQL queries

## Module/Feature Flags

**Checked Modules:**
- Permissions system (Spatie/Laravel-Permission) - ENABLED
- Filament admin interface - ENABLED
- Queue processing - DATABASE-BACKED
- Session management - DATABASE-BACKED

**Middleware:**
- `App\Http\Middleware\CheckModuleEnabled` - Module availability checking
- `App\Http\Middleware\ApiRateLimitMiddleware` - API rate limiting
- Filament-specific middleware: Authentication, CSRF token, session handling

---

*Integration audit: 2025-04-21*
