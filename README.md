<p align="center">
  <h1>💸 Fluxa</h1>
  <p><strong>Personal Finance Tracker</strong></p>
  <p>Accounts · Transactions · Loans · Credit Cards · Subscriptions</p>
</p>

---

## 📋 Project Overview

**Fluxa** is a Laravel-based personal finance tracker designed to help users monitor and manage all aspects of their financial life in one unified system.

### Current Status: Evidence-First Milestone (v5.1) — Chatbot Engine Shipped

The project follows a **proof-first confidence boundary**: a capability only counts as validated once current code *and* current tests prove it. See [`.planning/ROADMAP.md`](.planning/ROADMAP.md) for the authoritative, up-to-date boundary and roadmap — the sections below give a high-level snapshot.

- **Validated:** auth/settings, account CRUD & scoping, dashboard/report APIs & exports, admin finance-report rendering, admin access control, plus the proven credit-card REST access/scoping and issue-to-mark-paid lifecycle slice
- **Structural-only (present in code, lower confidence until proven):** transactions, loans, broader credit-card depth, subscriptions, monthly budgets, GraphQL
- **Newest capability:** a self-built, read-only finance chatbot ("Ask Fluxa") — a stateless intent router answering account-balance, upcoming-payment, and monthly-spending questions from already-validated data, with a floating widget on every authenticated page
- **Auth:** self-service registration, login, password reset, and profile management via Laravel Sanctum
- **Tests:** 150+ backend Feature/Unit tests passing (`php artisan test`)

---

## 🎯 Key Features

### Accounts
- Multiple account types (bank, cash, investment, emergency fund)
- Real-time balance tracking with opening balance support
- Soft deletes to preserve history

### Transactions
- Income, expense, transfer and cashback types
- Hierarchical category system (categories + subcategories)
- Transfer pairs with automatic dual-entry bookkeeping

### Loans
- Loan schedule generation with fixed and variable rates
- Payment posting with automatic transaction creation
- Interest calculation (simple, compound, French amortization)

### Credit Cards
- Cycle-based expense tracking
- Credit limit and available credit management
- Payment posting with revolving credit support
- KPI widgets (utilization, debt ratio, daily balance)
- Automatic cycle generation and issuing through the scheduler
- SPA support for cycles, expenses, and payments with backend parity

### Subscriptions
- Recurring payment tracking with backend-managed frequency settings
- Payment source can be either an account or a credit card
- Automatic 3-day renewal reminders on the dashboard
- Scheduled renewal posting to transactions or credit-card expenses
- Active/inactive/cancelled status management

### Dashboard
- SPA dashboard mirrors the Filament finance overview with graph-based summaries
- Monthly cashflow separates income, spending, and payment outflows
- Spending highlights only include real expense categories, excluding transfers and payment transactions
- Net-worth trend uses month-by-month account balance reconstruction so newly created test/demo data stays at zero before the first active month
- Upcoming payments merge loans, credit cards, and subscriptions with posting-state context

### Authentication
- Self-service registration, login, and profile management (name, phone, date of birth)
- Password reset flow
- Token-based auth via Laravel Sanctum, with role-based default assignment

### Finance Chatbot ("Ask Fluxa")
- Floating widget available on every authenticated SPA page
- Guided, button-driven flow (no open-ended NLU) with free text only where a button can't cover the value (e.g. a specific month)
- Answers three read-only questions from already-validated data: account balances, upcoming payments, and monthly spending
- Stateless per question and session-only history — nothing is persisted server-side
- Structural-only domains (credit cards, loans, subscriptions, budgets) are explicitly out of scope until those domains themselves gain stronger proof

### Settings & Admin
- User preference management
- Notification center
- Audit logging
- Backup management
- Role-based access control

---

## 🏗️ Architecture

### Backend Stack
```
Framework:      Laravel 12
Database:       MySQL 8.0+
ORM:            Eloquent
Admin UI:       Filament 4
Testing:        PHPUnit + Pest
```

### API and Frontend
```
API:            REST for SPA-critical finance flows + GraphQL where still retained
Auth:           Laravel Sanctum (token-based)
Docs:           Scribe API documentation
Frontend:       Vue 3 SPA (Pinia, Vue Router, Apollo/GraphQL client, Chart.js, Tailwind)
```

### Database Architecture
- **User Scoping:** HasUserScoping trait on all user-owned models
- **Soft Deletes:** Enabled on all entities
- **Relationships:** foreign keys with cascading deletes throughout

---

## 🚀 Quick Start

### Prerequisites
- PHP 8.2+
- Composer
- Node.js 18+
- MySQL 8.0+

### Installation

1. **Clone and install dependencies:**
   ```bash
   git clone <repository>
   cd fluxa
   composer install
   npm install
   ```

2. **Configure environment:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Setup database:**
   ```bash
   php artisan migrate --seed
   ```

4. **Run development servers:**
    ```bash
    php artisan serve          # Laravel server on http://localhost:8000
    npm run dev                # Vite server for assets
    ```

5. **Run the scheduler for automation:**
   ```bash
   php artisan schedule:work
   ```

   This is required for:
   - loan installment sync and posting
   - subscription renewal sync and posting
   - automatic credit-card cycle issuing

6. **Access admin panel:**
    - URL: http://localhost:8000/admin
    - Email: `admin@secondbrain.local`
    - Password: `password`
    - Seeded by `database/seeders/SuperAdminSeeder.php` — change this password before any non-local deployment.

### Database Statistics

| Category | Count |
|----------|-------|
| Models | 18 |
| Filament Resources | 15 |
| Migrations | 50+ |
| Test Cases | 150+ |

---

## 📦 Project Structure

```
app/
├── Models/              (finance + auth models)
├── Filament/Resources/  (admin CRUD interfaces)
├── Services/            (finance business logic, incl. Services/Chatbot/)
├── Enums/               (finance type definitions)
├── Observers/           (event handling)
├── Policies/            (authorization)
└── Traits/              (HasUserScoping, etc)

database/
├── migrations/
├── seeders/             (roles, permissions, transaction types)
└── factories/

resources/js/
├── stores/              (Pinia stores, incl. chatbot.js)
├── components/          (incl. components/chatbot/)
└── views/

tests/
├── Feature/             (authorization & API integration tests)
└── Unit/                (service & model unit tests)
```

---

## 🧪 Testing

### Run All Tests
```bash
php artisan test
```

### Run Specific Test Suite
```bash
php artisan test tests/Feature/AccountAuthorizationTest.php
php artisan test tests/Feature/TransactionAuthorizationTest.php
php artisan test tests/Feature/Api/ChatbotApiTest.php
php artisan test tests/Unit/CreditCardBalanceServiceTest.php
php artisan test tests/Unit/LoanScheduleServiceTest.php
```

---

## 📖 Documentation

| Document | Purpose |
|----------|---------|
| [.planning/ROADMAP.md](.planning/ROADMAP.md) | **Authoritative** current roadmap, validated vs. structural-only confidence boundary, and deferred concerns |
| [.planning/PROJECT.md](.planning/PROJECT.md) | Current project framing and core value |
| [ARCHITECTURE.md](docs/ARCHITECTURE.md) | System design and patterns |
| [API.md](docs/API.md) | GraphQL API documentation |
| [SECURITY_CHECKLIST.md](docs/SECURITY_CHECKLIST.md) | Security review checklist |
| [CONTRIBUTING.md](docs/CONTRIBUTING.md) | Contribution guidelines |

`docs/PROJECT_ROADMAP_EN.md` and `docs/PHASE7_CLOSEOUT.md` describe superseded, pre-v5.1 planning history — kept for context, but `.planning/ROADMAP.md` is the source of truth for what's actually validated today.

## ⏭️ Immediate Next Steps

See the "Committed Near-Term Roadmap" and "Direct Next Command" sections of [.planning/ROADMAP.md](.planning/ROADMAP.md) for the current, up-to-date next step — this section is intentionally not duplicated here to avoid drifting out of sync.

---

## 🔐 Security Features

- **User Data Isolation:** Global scopes ensure users only see their own data
- **Authentication:** Laravel Sanctum (token-based, self-service registration/login/password-reset)
- **Authorization:** Role-based access control (RBAC) via Spatie Permission
- **Soft Deletes:** No permanent data loss
- **Database Constraints:** Cascading deletes, unique indexes
- **CSRF Protection:** Built-in Laravel protection

---

## 🛣️ Roadmap

Full delivered history, the current validated/structural-only confidence boundary, committed near-term work, and deferred concerns all live in [.planning/ROADMAP.md](.planning/ROADMAP.md) — that document is updated as part of every planning cycle and is the only roadmap kept current. In short: the finance backend, API layer, and SPA shipped in earlier milestones (v1.0–v3.0), the v5.1 milestone re-validated the shipped surface against real tests, proved the highest-risk credit-card boundary, and shipped a read-only finance chatbot — see the roadmap doc for what's next.

---

## 🤝 Contributing

Contributions are welcome! Please follow these guidelines:

1. Read [CONTRIBUTING.md](docs/CONTRIBUTING.md)
2. Follow the [ARCHITECTURE.md](docs/ARCHITECTURE.md) conventions
3. Ensure all tests pass: `php artisan test`
4. Add tests for new features
5. Update documentation

---

## 📝 License

This project is open source and available under the [MIT license](LICENSE).

---

**Last Updated:** 2026-08-06  
**Milestone:** v5.1 — Planning Realignment
