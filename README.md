# 💸 Fluxa

Personal finance tracker built with Laravel, a Vue SPA, and a Filament admin panel. Manage accounts, transactions, transfers, loans, credit cards, subscriptions, and account-linked savings goals.

## Current project status

**Snapshot: 2026-09-20 — implementation complete through Phase 21, plus an ad hoc Transactions/Categories UX round.** Outside the numbered phase roadmap, `uat` also carries a September 2026 round (PRs #36–#48): transactions sorted by date with collapsible month groups and sortable/filterable columns; transaction categories creatable inline from the transaction and subscription forms, removing the need for Hub access for that workflow; subscription-only categories via a dedicated `scope` column on `transaction_categories`; monthly-actual, monthly-weighted, and annual subscription totals; sortable/filterable list tables on Accounts, Loans, Credit Cards, Saving Goals, and Subscriptions; and transaction types locked to a fixed, non-editable lookup (including in the Hub). Live verification on UAT surfaced and fixed five further defects in the same round: a GraphQL query-complexity regression that broke the transactions list, a category-picker bug that dropped children of an out-of-scope parent, a systemic bug where an expired Sanctum access token silently broke every GraphQL mutation and REST write instead of refreshing, a missing `category_id` field in the subscription API response that emptied the category on reload, new accounts not inheriting `opening_balance` into `balance`, and all five GraphQL delete mutations (`deleteAccount`, `deleteTransaction`, `deleteLoan`, `deleteCreditCard`, `deleteSubscription`) failing outright because none of them had a Lighthouse filter directive on their `id` argument.

Before that round, the latest phase-numbered work corrects credit-card balance recomputation, including opening debt, payment deletion, creation defaults, and immediate recomputation after opening-balance edits.

The public `/cookie-policy` page documents cookies and browser storage, with links from the frontend and administration panel. Set `LEGAL_OPERATOR_NAME` and `LEGAL_CONTACT_EMAIL` for the instance before publication. Session cookie name and lifetime reflect Laravel configuration. Recheck the policy against the deployed site, including proxy-added cookies, when changing authentication, remember-me duration or adding external services. The current application includes no analytics or advertising trackers; optional tracking would require reviewing consent before activation.

The latest local backend verification passed **537 tests and 1,962 assertions**. This verifies the current working tree; it does not establish that these changes have been deployed.

The project uses an evidence-first approach. Backend tests cover authentication/settings, ownership boundaries, account operations, reports/exports, chatbot intents, credit-card workflows and calculations, 2FA, vault access, transfers, and savings goals. Broader SPA behavior, GraphQL finance operations, and all combinations of financial workflows still require targeted validation; a passing backend suite is not full end-to-end UI coverage.

Phases 22–28 have discussion context but are **not implemented**. The next planning step is Phase 22 (proactive notifications). Historical production/UAT credit-card balances still need the manual reconciliation described below.

## Features in the current codebase

### Accounts, transactions, and transfers

- Account types include bank, cash, investment, and emergency fund, with opening balances and transaction-based balance tracking. New accounts initialize `balance` from `opening_balance`.
- Income, expense, transfer, and cashback transactions with hierarchical categories. Categories are creatable inline from the transaction and subscription forms; transaction types are a fixed, non-editable lookup (not creatable, including in the Hub).
- The transactions list sorts by transaction date (not insertion order), groups into collapsible month sections, and supports column sorting and filtering. Accounts, Loans, Credit Cards, Saving Goals, and Subscriptions list views are sortable and filterable too.
- Subscription-only categories are separated from generic categories via a dedicated `scope` column (`generic` | `subscription`) on `transaction_categories`.
- Transfers between accounts use paired entries.
- Authenticated APIs enforce ownership and policy checks, with intended superadmin cross-user access.

### Loans and subscriptions

- Loan schedules, payment posting, and finance calculations for simple interest, compound interest, and French amortization.
- Subscriptions with backend-managed frequencies, account or credit-card payment sources, and renewal posting. The Subscriptions list shows monthly-actual, monthly-weighted (normalized across billing frequencies), and annual cost totals.
- Upcoming commitments appear on the dashboard; scheduled commands process installments and renewals.

### Credit cards

- Charge and revolving cards, card brands, statement cycles, expenses, payments, credit limits, and available-credit KPIs.
- Revolving calculations account for cycle boundaries, principal payments during the cycle, and configurable inclusion of stamp duty in the fixed installment.
- Daily-balance interest and a direct-monthly method using one twelfth of the annual rate.
- A persisted, editable `opening_balance` represents debt not backed by tracked expenses. The authoritative recomputation is:

  ```text
  current_balance = round(max(0, opening_balance + expenses − paid principal), 2)
  ```

- Payment creation, updates, and deletion use the same balance recomputation. New cards initialize their balance consistently, and opening-balance edits trigger an immediate recomputation.
- Filament and REST expose the opening balance; broader SPA/GraphQL field parity should not be assumed.

### Savings goals

- Goals link to an actual account and have a target amount, optional target date, status, and notes.
- Progress follows the linked account's live balance rather than a separate manual contribution total.
- Available through the SPA, REST API, and Filament.

### Authentication and vault

- Registration, login, password reset, profile management, and Sanctum token refresh/revocation.
- TOTP two-factor authentication with recovery codes.
- A dedicated vault for sensitive account/card data, including debit and prepaid cards.
- Sensitive fields use encryption at rest. Vault access requires enabled 2FA and a separate, short-lived unlock session (10 minutes).
- Revealing CVV, PIN, and security-code fields additionally requires a vault PIN, with attempt limits and lockout.
- Role-based access control through Spatie Permission and Filament access policies.

### Dashboard, reports, and Ask Fluxa

- Dashboard summaries for accounts, cash flow, net worth, spending, and upcoming payments.
- Finance reports and CSV, XLSX, and PDF exports, including annual category distribution, monthly category detail, and cash-flow summaries.
- A floating **Ask Fluxa** widget answers account-balance, upcoming-payment, and monthly-spending questions.
- The chatbot uses a self-built stateless intent router, guided quick replies, and session-only history. It reads finance data without making mutations.

### Preferences and administration

- Per-user theme, toast-notification, privacy, and display-currency preferences.
- Filament currency formatting supports EUR, CZK, USD, GBP, and CHF. This changes symbols and number formatting only: stored amounts and financial calculations remain in EUR, with **no FX conversion**.
- The SPA currently uses English messages; the i18n infrastructure does not imply complete multilingual support.
- Filament includes management surfaces for users, roles, permissions, settings, budgets, notifications, audit logs, and backup records. Their presence does not imply proactive financial notifications or a complete backup/recovery workflow.

## Stack and architecture

| Area | Current implementation |
|------|------------------------|
| Backend | Laravel 12, PHP 8.2+ |
| Database | MySQL for the local finance environment; SQLite configured in `.env.example` and backend tests |
| Admin | Filament 4, panel at `/hub` |
| APIs | REST under `/api/v1`; Lighthouse GraphQL at `/graphql` |
| Authentication | Laravel Sanctum, Spatie Permission, TOTP |
| Frontend | Vue 3, Pinia, Vue Router, Apollo, Chart.js, Tailwind CSS, Vite 7 |
| Exports | Laravel Excel and Dompdf |
| API documentation | Scribe |
| Tests | PHPUnit 11, in-memory SQLite |

Finance services contain calculation and posting logic. Eloquent observers keep dependent transactions, cycles, and balances synchronized. User-owned models use ownership scoping and policies; scheduled commands deliberately operate across users. Soft deletion is used by finance models where implemented.

## Local setup

### Requirements

- PHP 8.2+ with the extensions required by Composer and your database driver.
- Composer.
- Node.js **20.19+ on the 20.x line, or 22.12+**, compatible with the installed Vite 7 toolchain.
- MySQL for the MySQL setup below, or SQLite for the example environment.

### Install and configure

From your repository checkout:

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Set `APP_URL=http://localhost:8000` when using `php artisan serve`. The example environment defaults to SQLite. For MySQL, create the database and configure your local `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=second_brain
DB_USERNAME=your_local_user
DB_PASSWORD=your_local_password
```

For SQLite, keep `DB_CONNECTION=sqlite` and create its database file if it does not exist:

```bash
touch database/database.sqlite
```

Initialize a fresh local database:

```bash
php artisan migrate --seed
```

The seeders create roles/permissions, transaction types, the superadmin account, and a test user. On an existing database, use `php artisan migrate` to apply pending migrations.

### Run locally

```bash
composer run dev
```

This starts the Laravel server, queue listener, log viewer, and Vite. Run the scheduler in another terminal:

```bash
php artisan schedule:work
```

Alternatively, start `php artisan serve` and `npm run dev` separately. The Vite server serves assets; open the application on the Laravel server.

| Surface | Local URL |
|---------|-----------|
| SPA login | http://localhost:8000/login |
| SPA dashboard | http://localhost:8000/home |
| Filament login | http://localhost:8000/hub/login |
| REST API | http://localhost:8000/api/v1 |
| GraphQL | http://localhost:8000/graphql |
| Generated API docs | http://localhost:8000/docs |

### Local superadmin

- **Email:** `admin@secondbrain.local`
- **Default password:** `password`
- **Role:** `superadmin`

[`SuperAdminSeeder.php`](database/seeders/SuperAdminSeeder.php) creates the account if absent and assigns its role. It does not reset an existing account's password. These are local development defaults; change the password before non-local deployment.

## Scheduled finance processing

The schedules in [`routes/console.php`](routes/console.php) use the application's configured timezone:

| Time | Command | Purpose |
|------|---------|---------|
| 01:50 | `loans:sync-installments` | Generate missing installments and post due payments |
| 01:55 | `subscriptions:sync-renewals` | Post due renewals to transactions or card expenses |
| 02:00 | `credit-cards:generate-cycles --issue-ready` | Ensure cycles, issue ready cycles, and refresh statuses/balances |

For production scheduler configuration and deployment, see [DEPLOY_RAILWAY.md](DEPLOY_RAILWAY.md).

## Phase 21: reconcile existing card balances

The opening-balance migration adds the column with a default of `0`. It does not reconstruct historical opening debt or recover balances already overwritten by the old recomputation.

After deploying and migrating the target environment, run:

```bash
php artisan credit-cards:balance-audit
```

This read-only, manual command lists **all active cards across users, oldest first**, with owner, creation date, current/opening balance, expense totals, and paid principal. It does not use a date cutoff to declare cards safe and is not scheduled.

Compare each card against its real statement and correct `Opening balance` in Filament as needed, accounting for the tracked expenses and paid principal in the formula above. Opening balance is the untracked starting debt, not necessarily the current statement balance. This production/UAT reconciliation remains an outstanding manual follow-up.

## Verification

Run the backend suite with enough memory for the current tests:

```bash
php -d memory_limit=512M vendor/bin/phpunit --no-progress
```

Latest verified result on 2026-09-20: **537 tests, 1,962 assertions, no failures**. The suite uses SQLite in memory through [`phpunit.xml`](phpunit.xml); it does not migrate the local MySQL database. A 128 MB PHP memory limit is insufficient for the full suite; use `php -d memory_limit=-1 vendor/bin/phpunit` if 512 MB is not enough.

Run a targeted group:

```bash
php -d memory_limit=512M vendor/bin/phpunit --filter CreditCardOpeningBalance
php -d memory_limit=512M vendor/bin/phpunit --filter Vault
php -d memory_limit=512M vendor/bin/phpunit --filter SavingGoal
```

Build frontend assets:

```bash
npm run build
```

## Roadmap

Completed work includes the finance backend, REST/GraphQL APIs, Vue SPA, capability audit, read-only chatbot, security hardening, revolving-interest fixes, display-currency preferences, the Phase 21 opening-balance correction, and the September 2026 ad hoc Transactions/Categories UX round described above.

The next phases have discussion context, but research, implementation plans, and execution are still pending:

| Phase | Planned work | Dependencies |
|-------|--------------|--------------|
| 22 | Proactive in-app finance notifications and daily digest | 21 |
| 23 | Subscription price-hike detection | 22 |
| 24 | Cash-flow forecast / safe-to-spend projection | 21 |
| 25 | CSV/Excel statement import, draft review, mapping, and deduplication | 21 |
| 26 | Debt and subscription totals in chatbot and dashboard | 17, 24 |
| 27 | Automated reconciliation against imported statement balances | 21, 22, 25 |
| 28 | Transaction categorization using user rules and history | 25 |

Start with Phase 22. Import planning in Phase 25 establishes the draft model needed by Phases 27–28; Phases 24 and 26 should be coordinated around dashboard aggregates. Live bank feeds/Open Banking and PDF statement parsing are outside the planned file-import phase.

See [`.planning/ROADMAP.md`](.planning/ROADMAP.md) for phase details and [`.planning/STATE.md`](.planning/STATE.md) for handoff context. Some historical headers and confidence notes in the planning files predate the later phases; current source and tests take precedence.

## Project layout and documentation

```text
app/
├── Models/              Finance, authentication, vault, and savings models
├── Filament/            Admin resources, pages, and widgets
├── Http/                REST controllers, requests, resources, and middleware
├── Services/            Finance calculations, posting, chatbot, and security
├── Observers/           Synchronization after model changes
├── Policies/            Access rules
└── Traits/              Shared ownership and calculation behavior

database/                Migrations, seeders, and factories
resources/js/            Vue views, stores, components, routing, and English messages
graphql/                 Lighthouse schema
routes/                  Web, API, and scheduled commands
tests/                   Backend tests and frontend scripts
.planning/               Roadmap, phase context, plans, and completion summaries
graphify-out/            Generated project knowledge graph
```

| Document | Purpose |
|----------|---------|
| [.planning/ROADMAP.md](.planning/ROADMAP.md) | Delivered phases and planned follow-ups |
| [.planning/STATE.md](.planning/STATE.md) | Planning handoff and outstanding work |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Architecture reference |
| [docs/API.md](docs/API.md) | API reference; generated REST docs are available at `/docs` |
| [docs/SECURITY_CHECKLIST.md](docs/SECURITY_CHECKLIST.md) | Security guidance |
| [docs/CONTRIBUTING.md](docs/CONTRIBUTING.md) | Contribution guidelines |
| [DEPLOY_RAILWAY.md](DEPLOY_RAILWAY.md) | Deployment instructions |
| [AGENTS.md](AGENTS.md) / [CLAUDE.md](CLAUDE.md) | Agent instructions and project-knowledge usage |

The generated Obsidian knowledge base is at `~/Documents/DevKnowledge/second-brain`. Consult relevant notes when investigating architecture and call flows, then verify against source. Personal financial sample files excluded by `.graphifyignore` must remain outside graph extraction and publication.

`docs/PROJECT_ROADMAP_EN.md` and `docs/PHASE7_CLOSEOUT.md` preserve earlier planning history. Composer declares the project license as MIT.

**Last updated:** 2026-09-20
