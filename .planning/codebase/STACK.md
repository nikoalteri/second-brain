# Technology Stack

**Analysis Date:** 2026-03-23

## Languages

**Primary:**
- PHP 8.2+ - Server-side application logic, models, services, and business rules
- JavaScript (ES Module) - Frontend with Vue 3, CSS preprocessing with PostCSS

**Secondary:**
- GraphQL - API query language via Lighthouse
- SQL - Database queries and schema management
- Blade - Template language for server-side rendering (minimal use, mostly Filament)

## Runtime

**Environment:**
- PHP 8.2+ (Laravel 12 compatible)

**Package Manager:**
- Composer - PHP dependency management
- npm - JavaScript dependency management
- Lockfiles: `composer.lock` (present), `package-lock.json` (present)

## Frameworks

**Core:**
- Laravel 12.x - Web application framework with routing, ORM, migrations
- Filament 4.x - Admin dashboard and resource management system built on Laravel
- Nuwave Lighthouse 6.65+ - GraphQL server for Laravel

**Frontend:**
- Vue 3 (via Vite) - SPA components and reactive UI
- Tailwind CSS 3.2.1 - Utility-first CSS framework
- @tailwindcss/forms 0.5.3 - Form styling plugin

**Testing:**
- PHPUnit 11.5.3 - PHP unit and feature testing
- Mockery 1.6+ - Mocking library for PHPUnit

**Build/Dev:**
- Vite 7.0.7+ - Frontend asset bundler with hot module replacement
- laravel-vite-plugin 2.0+ - Laravel integration for Vite
- PostCSS 8.4.31 - CSS transformations (autoprefixer, Tailwind)

## Key Dependencies

**Critical:**
- `laravel/framework` 12.x - Core Laravel framework
- `filament/filament` 4.x - Admin UI and resource generation
- `nuwave/lighthouse` 6.65+ - GraphQL implementation (replaces traditional REST API)
- `laravel/sanctum` 4.x - API authentication and token-based requests
- `spatie/laravel-permission` 7.2+ - Role-based access control (RBAC)

**Infrastructure:**
- `laravel/tinker` 2.10.1 - REPL for debugging
- `tightenco/ziggy` 2.x - JavaScript route helpers from Laravel routes
- `fakerphp/faker` 1.23+ - Test data generation
- `laravel/sail` 1.41+ - Docker development environment
- `laravel/pail` 1.2.2 - Log viewer utility

## Configuration

**Environment:**
- `.env` file (not committed) - Runtime configuration
- `.env.example` - Template for required configuration variables
- 12 config files in `config/` directory controlling application behavior

**Build:**
- `vite.config.js` - Vite configuration with Laravel plugin integration
- `postcss.config.js` - PostCSS plugin configuration (autoprefixer, Tailwind)
- `tailwind.config.js` - Tailwind CSS theme and content configuration
- `jsconfig.json` - JavaScript path aliases and compiler options

## Platform Requirements

**Development:**
- PHP 8.2 or higher
- Composer installed globally
- Node.js + npm for frontend tooling
- SQLite (default) or MySQL/PostgreSQL
- Git for version control

**Production:**
- PHP 8.2+ web server (Apache, Nginx, etc.)
- Database: SQLite, MySQL, or PostgreSQL
- npm build artifacts for static assets
- Laravel configured for production (config caching, optimized autoloader)

---

*Stack analysis: 2026-03-23*
