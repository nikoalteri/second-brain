# Technology Stack

**Analysis Date:** 2024-12-19

## Languages

**Primary:**
- PHP 8.2 - Server-side application logic, Eloquent ORM, services

**Secondary:**
- PHP 8.3 (current runtime) - Slightly ahead of declared requirement
- JavaScript (ES modules) - Frontend asset bundling via Vite
- GraphQL - Schema definition for query API (minimal usage)
- SQL - Database queries via Eloquent

## Runtime

**Environment:**
- PHP 8.3.30 (declared requirement: PHP 8.2+)
- Laravel 12.x framework
- Composer for dependency management

**Package Manager:**
- Composer 2.x (PHP dependency manager)
- NPM (JavaScript dependency manager)
- Lockfiles: `composer.lock`, `package-lock.json` present

## Frameworks

**Core:**
- Laravel 12.0 - Web framework, routing, ORM, authentication, validation
- Filament 4.0 - Admin panel and CRUD resource generator
- Lighthouse 6.65 - GraphQL server integration

**Frontend:**
- Vite 7.0.7 - Asset bundler and dev server
- Tailwind CSS 3.2.1 - Utility-first CSS framework
- Tailwind Forms - Form styling plugin
- Tailwind Vite - Tailwind integration with Vite
- Laravel Vite Plugin 2.0.0 - Laravel-specific Vite plugin

**Testing:**
- PHPUnit 11.5.3 - PHP testing framework
- Mockery 1.6 - Mocking library for PHPUnit

**Development Tools:**
- Laravel Pint 1.24 - Code style fixer (PHP equivalent of Prettier)
- Laravel Sail 1.41 - Docker development environment (optional)
- Laravel Tinker 2.10.1 - Interactive REPL for Laravel
- Laravel Pail 1.2.2 - Log viewer for Laravel

## Key Dependencies

**Critical:**

- `laravel/framework` 12.0 - Core web framework
- `filament/filament` 4.0 - Admin panel builder (heavy use: 36 resources)
- `nuwave/lighthouse` 6.65 - GraphQL server for schema-based queries
- `laravel/sanctum` 4.0 - API authentication (token-based)

**Infrastructure:**

- `spatie/laravel-permission` 7.2 - Role-based access control (RBAC)
- `barryvdh/laravel-dompdf` 3.1 - PDF generation (for finance reports, TODO: not yet implemented)
- `tightenco/ziggy` 2.0 - JavaScript route generation from Laravel routes
- `fakerphp/faker` 1.23 - Fake data generation for testing

**Build & Asset:**

- `laravel-vite-plugin` 2.0.0 - Vite plugin for Laravel asset handling
- `vite` 7.0.7 - Modern bundler and dev server
- `tailwindcss` 3.2.1 - CSS framework
- `postcss` 8.4.31 - CSS transformation pipeline
- `autoprefixer` 10.4.12 - Browser prefix automation
- `@tailwindcss/forms` 0.5.3 - Pre-styled form components

## Configuration

**Environment:**
- `.env` file configuration (not committed)
- `.env.example` template shows all required variables
- Key configs:
  - `APP_ENV=local|testing|production`
  - `APP_DEBUG=true|false`
  - `DB_CONNECTION=sqlite` (default, switchable to mysql, pgsql)
  - `SESSION_DRIVER=database`
  - `CACHE_STORE=database`
  - `QUEUE_CONNECTION=database`
  - `MAIL_MAILER=log` (logs to file in dev, switchable to SMTP)

**Build:**
- `vite.config.js` - Vite configuration
  - Input: `resources/css/app.css`
  - Refresh: enabled for hot reload
  - Output: `public/build/` with manifest
- `tailwind.config.js` - Tailwind configuration
  - Content paths: Blade views, Vue components, app resources
  - Extended theme with custom fonts (Figtree)
  - Plugins: Forms plugin
- `postcss.config.js` - PostCSS configuration
- `.editorconfig` - Editor settings (4-space indent, UTF-8, LF line endings)

**Code Quality:**
- Laravel Pint configuration (code style fixing)
- PHPUnit configuration in `phpunit.xml`
- No ESLint or Prettier config found (JavaScript not linted)

## Platform Requirements

**Development:**
- PHP 8.2+ with extensions: PDO, SQLite (or MySQL), OpenSSL, Ctype, cURL, DOM, JSON, Mbstring, OpenSSL, PCRE, PDO, Tokenizer, XML
- Composer 2.x
- Node.js 18+ (for npm)
- SQLite (included with most PHP installations) or MySQL/PostgreSQL
- Git for version control

**Production:**
- PHP 8.2+ (tested with 8.3)
- Web server: Apache with mod_rewrite or Nginx with PHP-FPM
- Database: SQLite, MySQL 5.7+, PostgreSQL 10+
- Deployment: Laravel Sail (Docker) or traditional server
- Environment variables: APP_KEY, DB_* credentials, API keys (if applicable)

## Key Configuration Files

**composer.json:**
- Declares PHP 8.2+ requirement
- Defines 8 production dependencies
- Defines 8 development dependencies
- Autoloading: PSR-4 for `App\`, `Database\Factories\`, `Database\Seeders\`, `Tests\`
- Scripts: `dev` (concurrent Vite, Artisan, queue, logs), `test` (PHPUnit), `setup`

**package.json:**
- Private package
- ES modules (`"type": "module"`)
- Build scripts: `npm run build` (Vite build), `npm run dev` (Vite dev)
- 10 dev dependencies, no production dependencies

**.env.example:**
- Default SQLite connection (commented: MySQL config)
- Session stored in database
- Cache stored in database
- Queue stored in database
- Mail via logging (dev-friendly)
- AWS S3 configuration (optional)
- GraphQL endpoint at `/graphql`
- Vite app name

**Config Files (config/ directory):**
- `app.php` - Application name, timezone, locale
- `auth.php` - Guards (web, api), providers
- `database.php` - Connection configurations
- `lighthouse.php` - GraphQL route, schema path, directives
- `permission.php` - Spatie permissions settings
- `session.php` - Session driver, lifetime
- `cache.php` - Cache driver, TTL
- `queue.php` - Queue driver, retry logic
- `mail.php` - Mail driver, from address
- `logging.php` - Log channels, error reporting

## Deployment & CI/CD

**Deployment:**
- Laravel Sail support (Docker-based local development)
- No CI/CD pipeline found (no GitHub Actions workflows)
- Manual deployment support via Composer and npm scripts

**Development Server:**
- `php artisan serve` - Built-in PHP server (port 8000)
- `npm run dev` - Vite dev server (port 5173, HMR enabled)
- Concurrent execution: `composer run dev` runs all together

## Dependencies Version Strategy

- Stable versions locked in `composer.lock` and `package-lock.json`
- Laravel 12.x (latest)
- Filament 4.x (latest major)
- Lighthouse 6.65 (recent stable)
- Tailwind CSS 3.x (v4 available but not used)
- Vite 7.x (latest)

## Licensing

- Laravel framework: MIT
- Filament: MIT
- Lighthouse: MIT
- Tailwind CSS: MIT
- Spatie packages: MIT
- Application: Inherited from Laravel skeleton (MIT)

---

*Stack analysis: 2024-12-19*
