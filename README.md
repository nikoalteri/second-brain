<p align="center">
  <h1>💸 Fluxa</h1>
  <p><strong>Personal Finance Tracker</strong></p>
  <p>Accounts · Transactions · Loans · Credit Cards · Subscriptions</p>
</p>

---

## 📋 Project Overview

**Fluxa** is a Laravel-based personal finance tracker designed to help users monitor and manage all aspects of their financial life in one unified system.

### Current Status: Backend Foundation Complete ✅

- **Phases Implemented:** 0-5 (Finance Backend)
- **Models:** 16 finance entities
- **Filament Resources:** 14 CRUD interfaces
- **Tests:** 80+ passing

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

### Subscriptions
- Recurring payment tracking (monthly, yearly, etc.)
- Automatic renewal reminders
- Active/paused/cancelled status management

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

### Next: API Layer (Coming Soon)
```
API:            GraphQL (Lighthouse) + REST fallback
Auth:           JWT with token refresh
Docs:           API documentation
```

### Database Architecture
- **Tables:** 15 finance tables
- **User Scoping:** HasUserScoping trait on all user-owned models
- **Soft Deletes:** Enabled on all entities
- **Relationships:** 16 models with proper foreign keys and cascading deletes

---

## 🚀 Quick Start

### Prerequisites
- PHP 8.5+
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

5. **Access admin panel:**
   - URL: http://localhost:8000/admin
   - Email: `admin@fluxa.local`
   - Password: `password`

### Database Statistics

| Category | Count |
|----------|-------|
| Models | 16 |
| Resources | 14 |
| Database Tables | 15 |
| Test Cases | 80+ |

---

## 📦 Project Structure

```
app/
├── Models/              (16 finance models)
├── Filament/Resources/  (14 CRUD interfaces)
├── Services/            (finance business logic)
├── Enums/               (finance type definitions)
├── Observers/           (event handling)
├── Policies/            (authorization)
└── Traits/              (HasUserScoping, etc)

database/
├── migrations/          (15 finance tables)
├── seeders/             (roles, permissions, transaction types)
└── factories/

tests/
├── Feature/             (authorization & integration tests)
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
php artisan test tests/Unit/CreditCardBalanceServiceTest.php
php artisan test tests/Unit/LoanScheduleServiceTest.php
```

---

## 📖 Documentation

| Document | Purpose |
|----------|---------|
| [ARCHITECTURE.md](docs/ARCHITECTURE.md) | System design and patterns |
| [PROJECT_ROADMAP_EN.md](docs/PROJECT_ROADMAP_EN.md) | Project phases and timeline |
| [API.md](docs/API.md) | GraphQL API documentation |
| [CONTRIBUTING.md](docs/CONTRIBUTING.md) | Contribution guidelines |

---

## 🔐 Security Features

- **User Data Isolation:** Global scopes ensure users only see their own data
- **Authentication:** Laravel Sanctum / JWT
- **Authorization:** Role-based access control (RBAC) via Spatie Permission
- **Soft Deletes:** No permanent data loss
- **Database Constraints:** Cascading deletes, unique indexes
- **CSRF Protection:** Built-in Laravel protection

---

## 🛣️ Roadmap

### Phase 1 — Finance Backend ✅
- Core infrastructure and authentication
- Accounts & Transactions (dual-entry bookkeeping)
- Loans with amortization schedules
- Credit cards with cycle management
- Subscriptions with renewal tracking
- Filament admin panel

### Phase 2 — API Layer (Next)
- GraphQL schema + REST endpoints
- JWT authentication with token refresh
- Rate limiting and API documentation

### Phase 3+ — Enhancements
- Mobile-friendly frontend (Vue.js or React Native)
- Advanced analytics and reporting
- CSV/PDF export
- Budget planning & alerts
- Integrations (bank feeds, etc.)

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

**Last Updated:** 2026-04-21  
**Version:** 1.0.0
