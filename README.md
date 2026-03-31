<p align="center">
  <h1>🧠 Second Brain</h1>
  <p><strong>Comprehensive Personal Life Management Platform</strong></p>
  <p>Finance · Health · Productivity · Relationships · Home · Cooking · Travel</p>
</p>

---

## 📋 Project Overview

**Second Brain** is a comprehensive Laravel-based personal life management platform designed to help users organize and track all aspects of their life in one unified system.

### Current Status: Phase 1 Complete ✅

- **Phases Implemented:** 0-9 (Backend Foundation)
- **Models:** 39 user-owned entities
- **Filament Resources:** 43 CRUD interfaces  
- **Tests:** 157 passing (80/80 Phase 6-9 = 100%)
- **Dummy Data:** 299 records across all modules

### Next: Phase 2 (Frontend + GraphQL + i18n)

- Vue.js 3 Single Page Application
- GraphQL API integration
- Multi-language support (English + Italian)
- Form enhancements with richer data capture

---

## 🎯 Key Features

### Finance Module
- Account management with balance tracking
- Transaction categorization and tagging
- Subscription and recurring payment tracking
- Loan management with interest calculations
- Credit card expense tracking
- Financial dashboards with KPI widgets
- Reports and export functionality

### Health Module
- Health record tracking (BP, cholesterol, weight)
- Workout logging with intensity and duration
- Medical record storage
- Medication tracking with reminders
- Blood test results management

### Productivity Module
- Habit tracking with frequency monitoring
- Goal setting and progress tracking
- Project management
- Journal entries with mood tracking
- Note-taking with pinning and archiving

### Relationships Module
- Contact management with relationship types
- Message organization by importance
- Event tracking (birthdays, anniversaries)

### Home Module
- Vehicle tracking and maintenance logs
- Document storage and management
- Property/home records

### Cooking Module
- Recipe collection by cuisine
- Meal planning and tracking
- Ingredient management

### Travel Module
- Trip planning and itinerary management
- Flight booking tracker
- Hotel reservation management

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
Admin UI:       Filament 3 (Phase 1)
Testing:        PHPUnit + Pest
```

### Phase 2 Stack (Coming Soon)
```
Frontend:       Vue.js 3 (Composition API)
Routing:        Vue Router 4
State:          Pinia
API Layer:      GraphQL (+ REST fallback)
Build Tool:     Vite
Styling:        Tailwind CSS
i18n:           vue-i18n
```

### Database Architecture
- **Tables:** 50+
- **User Scoping:** HasUserScoping trait on all user-owned models
- **Soft Deletes:** Enabled on all entities
- **Relationships:** 39 models with proper foreign keys and cascading deletes

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
   cd second-brain
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
   - Email: `admin@secondbrain.local`
   - Password: `password`

### Database Statistics

| Category | Count |
|----------|-------|
| Models | 39 |
| Resources | 43 |
| Database Tables | 50+ |
| Dummy Records | 299 |
| Test Cases | 157 (80 Phase 6-9) |
| Assertions | 361+ |

---

## 📦 Project Structure

```
app/
├── Models/              (39 models with scoping)
├── Filament/Resources/  (43 CRUD interfaces)
├── Http/
│   ├── Requests/        (validation)
│   └── Middleware/
├── Traits/              (HasUserScoping, etc)
└── Providers/

database/
├── migrations/          (50+ tables)
├── seeders/             (8 data seeders)
└── factories/

tests/
├── Feature/             (80+ tests)
│   ├── Health/          (11 tests)
│   ├── Productivity/    (16 tests)
│   ├── Relationships/   (34 tests)
│   └── Settings/        (19 tests)
└── Unit/

resources/
├── lang/                (i18n - Phase 2)
│   ├── en/
│   └── it/
└── js/                  (Vue.js - Phase 2)
```

---

## 🧪 Testing

### Run All Tests
```bash
php artisan test
```

### Run Specific Test Suite
```bash
php artisan test tests/Feature/Health/HealthModuleTest.php
php artisan test tests/Feature/Productivity/ProductivityModuleTest.php
php artisan test tests/Feature/Relationships/RelationshipsModuleTest.php
php artisan test tests/Feature/Settings/SettingsModuleTest.php
```

### Test Results (Current)
- **Total:** 157 passing ✅
- **Phase 6-9:** 80/80 (100% pass rate)
- **Assertions:** 361+

---

## 📖 Documentation

| Document | Purpose |
|----------|---------|
| [ARCHITECTURE.md](docs/ARCHITECTURE.md) | System design and patterns |
| [PROJECT_ROADMAP_EN.md](docs/PROJECT_ROADMAP_EN.md) | Project phases and timeline |
| [PHASE_2_PLAN.md](.planning/PHASE_2_PLAN.md) | Frontend + GraphQL implementation plan |
| [DATABASE_SCHEMA.md](docs/DATABASE_SCHEMA.md) | Database entity relationships |
| [API.md](docs/API.md) | GraphQL API documentation (Phase 2) |
| [I18N.md](docs/I18N.md) | Internationalization guide (Phase 2) |
| [CONTRIBUTING.md](docs/CONTRIBUTING.md) | Contribution guidelines |

---

## 🔐 Security Features

- **User Data Isolation:** Global scopes ensure users only see their own data
- **Authentication:** JWT-based token system
- **Authorization:** Role-based access control (RBAC)
- **Soft Deletes:** No permanent data loss
- **Database Constraints:** Cascading deletes, unique indexes
- **CSRF Protection:** Built-in Laravel protection

---

## 🌍 Internationalization

Current Phase 1 is in English. Phase 2 will add:
- Complete Italian (it) translation
- Easy language switching in UI
- Per-user language preference
- Ready for future language additions

---

## 🛣️ Roadmap

### Phase 1 - Backend Foundation ✅
- Core infrastructure and authentication
- Finance module (5 entities)
- Health module (5 entities)
- Productivity module (5 entities)
- Relationships/Home/Cooking/Travel (12 entities)
- Settings/Notifications (4 entities)

### Phase 2 - Frontend & GraphQL (Current)
- Vue.js 3 SPA
- GraphQL API
- Form enhancements
- Internationalization (EN + IT)
- Dashboard & advanced features

### Phase 3+ - Enhancements
- Mobile app (React Native)
- Advanced analytics
- Collaboration features
- Integrations (Gmail, Google Calendar, etc)
- Cloud sync

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

## 👤 Author

Created as a comprehensive personal life management platform.

---

## 📞 Support

- 📖 See [documentation](docs/) for detailed guides
- 🐛 Report bugs via GitHub Issues
- 💬 Join discussions for feature requests

---

**Last Updated:** 2026-03-31  
**Version:** 1.0.0 (Phase 1 Complete)
