# Technology Stack

**Analysis Date:** 2025-04-21

## Languages

**Primary:**
- PHP 8.2+ (actual: 8.5.4) - Backend application logic and framework

**Secondary:**
- JavaScript (ES Module) - Frontend asset compilation and build tooling
- GraphQL - API schema definition for Lighthouse
- SQL - Database queries through Laravel ORM

## Runtime

**Environment:**
- PHP CLI - Command execution and Artisan commands
- Node.js 22.22.0 - Frontend build tooling and development server

**Package Manager:**
- Composer (PHP) - Installed, lockfile present (`composer.lock`)
- NPM 11.10.1 - Installed, lockfile present (`package-lock.json`)

## Frameworks

**Core:**
- Laravel 12.0 - Web application framework
- Filament 4.0 - Admin panel and UI framework for data management
- Lighthouse 6.65 - GraphQL server for API layer

**Frontend Utilities:**
- Blade UI Kit (Blade Heroicons 2.0) - Icon components
- Tailwind CSS 3.2.1 - Utility-first CSS framework
- Vite 7.0.7 - Frontend build tool and dev server

**Testing:**
- PHPUnit 11.5.3 - Unit and feature testing framework
- Mockery 1.6 - Mocking library for PHP tests

**Development:**
- Laravel Pail 1.2.2 - Log monitoring in console
- Laravel Pint 1.24 - PHP code style formatter
- Laravel Breeze 2.3 - Authentication scaffolding
- Laravel Sail 1.41 - Docker-based local development environment
- Laravel Tinker 2.10.1 - Interactive REPL for PHP code

## Key Dependencies

**Critical:**
- Laravel Framework 12.0 - Application foundation, routing, ORM, migrations
- Filament 4.0 - Admin UI, form builders, table components, resource management
- Lighthouse 6.65 - GraphQL query execution, schema validation, directive support

**Authorization & Permissions:**
- Spatie/Laravel-Permission 7.2 - Role-based access control (RBAC), permission management

**API & Documentation:**
- Sanctum 4.0 - API token authentication and session management
- Ziggy 2.0 - Frontend JavaScript routes matching backend routes
- Nuwave/Lighthouse 6.65 - GraphQL server implementation

**Utilities:**
- Nikic/PHP-Parser 5.7 - PHP code parsing and AST manipulation
- Barry vdh/Laravel-DomPDF 3.1 - PDF generation for financial reports
- FakerPHP/Faker 1.23 - Test data generation (dev only)
- Nunomaduro/Collision 8.6 - Better error display in console

**Frontend Build:**
- Laravel Vite Plugin 2.0.0 - Vite integration for Laravel
- Tailwind CSS 3.2.1 - CSS framework
- Tailwind Forms Plugin 0.5.3 - Form component styling
- Tailwind Vite Plugin 4.0.0 - Vite integration for Tailwind
- Autoprefixer 10.4.12 - CSS vendor prefixing
- PostCSS 8.4.31 - CSS transformation tool
- Axios 1.11.0 - HTTP client for frontend requests
- Concurrently 9.0.1 - Run multiple npm commands simultaneously

## Configuration

**Environment:**
- Configuration driven by `.env` file (see `.env.example`)
- Key variables: `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL`
- Locale configuration: `APP_LOCALE=en`, `APP_FALLBACK_LOCALE=en`
- Timezone: UTC (hardcoded in `config/app.php`)

**Build:**
- `vite.config.js` - Vite configuration with Laravel plugin
- `postcss.config.js` - PostCSS configuration with Tailwind and Autoprefixer
- `tailwind.config.js` - Tailwind theme customization (Figtree font, Tailwind Forms)
- `jsconfig.json` - JavaScript module aliases and configuration
- `phpunit.xml` - Test framework configuration

**Database:**
- Primary: SQLite (`database/database.sqlite`) - Default development database
- Supported: MySQL, MariaDB, PostgreSQL, SQL Server (configured in `config/database.php`)
- Foreign key constraints enabled by default
- Transaction mode: DEFERRED

**Storage:**
- Session: Database (`SESSION_DRIVER=database`)
- Queue: Database (`QUEUE_CONNECTION=database`)
- Cache: Database (`CACHE_STORE=database`)
- File system: Local (`FILESYSTEM_DISK=local`)
- Mail: Log driver in local dev (`MAIL_MAILER=log`)

## Platform Requirements

**Development:**
- PHP 8.2 or higher
- Node.js 20+ (running on 22.22.0)
- Composer (dependency manager)
- NPM or Yarn (package manager)
- SQLite or MySQL/PostgreSQL for database
- Redis (optional, for caching and queue)
- Docker (optional, via Laravel Sail)

**Production:**
- PHP 8.2+ FPM server (Apache/Nginx)
- MySQL 8.0+ or PostgreSQL 10+
- Redis (recommended for caching and sessions)
- Node.js for asset compilation
- Supervisor or similar for queue workers
- File storage (local or cloud S3-compatible)

## Notable Architecture Decisions

**Application Tier:**
- Single application entry point via `routes/web.php` redirecting to Filament admin
- API routes available at `routes/api.php` with rate limiting middleware
- Database-backed sessions, cache, and queue for simplicity

**Model Layer:**
- Eloquent ORM for database interactions
- Event observers for model lifecycle hooks (`app/Observers/`)
- Services layer for business logic (`app/Services/`)
- Repositories for data access patterns (`app/Repositories/`)

**Admin Interface:**
- Fully Filament-based with no traditional Blade views for public routes
- GraphQL schema in `graphql/schema.graphql` for optional API consumers

---

*Stack analysis: 2025-04-21*
