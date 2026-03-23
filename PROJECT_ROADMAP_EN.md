# 🧠 Second Brain – Project Roadmap 2026

> **Stack:** Laravel 12 + Filament v3 (admin), Vue/Inertia (frontend), MySQL  
> **Initial Focus:** Complete **Finance** module, then **Second Brain** modules  
> **Last Updated:** 2026-03-23

---

## 📋 Status Legend

| Badge | Meaning |
|-------|---------|
| ✅ | Complete & working |
| 🟡 | In progress / Partially done |
| ⏳ | To do |
| 🔧 | Needs bug fix / refinement |

---

## 🌱 Phase 0 – Setup & Architecture

**Goal:** Solid application foundation, roles, and admin panel.

### Implementation

- ✅ Project setup: Laravel 12, Filament v3, Vue/Inertia, MySQL, Auth
- ✅ Spatie Permissions integrated
- ✅ Role definitions: `superadmin`, `admin`, `user`
- ✅ Admin panel as single Filament panel (user management + configuration)
- ✅ Module structure (flag `enabled_modules` on `users` table)
- ✅ Shared traits:
  - `HasWorkdayCalculation` for weekend/holiday skip (reused in Loans, CreditCards)
  - `HasUserScoping` for global vs per-user entries

**Status:** ✅ **COMPLETE & WORKING**

**Key Files:**
- `app/Traits/HasWorkdayCalculation.php`
- `app/Traits/HasUserScoping.php`
- `app/Providers/AppServiceProvider.php`

---

## 💰 Phase 1 – Finance Core: Accounts & Transactions

**Goal:** Base model for daily financial operations.

### Implementation

- ✅ Core tables:
  - `accounts` (types: bank, cash, investment, emergency_fund, debt)
  - `transaction_types` (Income, Expense, Transfer, Cashback)
  - `transaction_categories` with hierarchy (categories + subcategories)
  - `transactions` with `is_transfer` and `transfer_pair_id`
  
- ✅ Transfer logic:
  - dual IN/OUT rows linked by UUID
  - excluded from cashflow/report categories
  - included in balances and net worth

- ✅ CRUD + Filament Resources:
  - `AccountResource.php`
  - `TransactionResource.php`
  - `TransactionCategoryResource.php`
  - `TransactionTypeResource.php`

- ✅ Service layer:
  - `TransactionService.php` (transfer creation)

**Status:** ✅ **IMPLEMENTED & WORKING**

**Key Files:**
- Models: `app/Models/Account.php`, `Transaction.php`, `TransactionCategory.php`, `TransactionType.php`
- Resources: `app/Filament/Resources/Accounts/`, `Transactions/`, etc.
- Service: `app/Services/TransactionService.php`

---

## 📦 Phase 2 – Subscriptions

**Goal:** Manage recurring subscriptions and total monthly cost.

### ✅ Implementation Complete

- ✅ `subscriptions` table:
  - `name`, `monthly_cost`, `annual_cost` (frequency-based calculation)
  - `frequency` enum (MONTHLY, ANNUAL, BIENNIAL)
  - `status` enum (ACTIVE, INACTIVE, CANCELLED)
  - `day_of_month`, `next_renewal_date`
  - `account_id`, `category_id`, `user_id` (user-scoped)
  - soft deletes, timestamps

- ✅ Models & Enums:
  - `Subscription.php` with HasFactory, HasUserScoping, SoftDeletes
  - `SubscriptionFrequency.php` enum with `getMonthlyDivisor()` (1, 12, 24)
  - `SubscriptionStatus.php` enum (ACTIVE, INACTIVE, CANCELLED)

- ✅ Business Logic:
  - `SubscriptionService.php` with cost calculations and renewal logic
  - `SubscriptionObserver.php` for auto-calculation on create/update
  - Frequency-based monthly divisor: ANNUAL (÷12), BIENNIAL (÷24)

- ✅ Admin CRUD + Filament Resource:
  - `SubscriptionResource.php` in dedicated `Subscriptions/` folder structure
  - Form schema: `Schemas/SubscriptionForm.php` (name, frequency, costs, renewal, account, category)
  - Table config: `Tables/SubscriptionsTable.php` with CRUD actions
  - Pages: `Pages/ListSubscriptions.php`, `CreateSubscription.php`, `EditSubscription.php`

- ✅ Dashboard Widgets:
  - `SubscriptionsStatsWidget.php` – Shows annual cost total + monthly cost total + active count
  - `UpcomingRenewalsWidget.php` – Lists next 5 renewals with dates
  - Both widgets registered in `AdminPanelProvider.php`

- ✅ Testing:
  - `tests/Unit/SubscriptionServiceTest.php` – 12 unit tests, 100% passing
  - Tests cover all frequencies, edge cases, monthly totals, renewal calculations, observer logic

- ✅ Authorization:
  - `SubscriptionPolicy.php` with superadmin bypass via Gate::before()
  - User-scoped queries via `HasUserScoping` trait

**Status:** ✅ **COMPLETE & WORKING**

**Key Commits:**
- `6acfe61` – Remove redundant MonthlySubscriptionCostWidget
- `4bc42bb` – Register SubscriptionsStatsWidget on dashboard
- `62160b6` – Add SubscriptionsStatsWidget with annual/monthly costs
- `40a4cbd` – Implement Phase 2 with frequency-based calculations
- `b2eee2d` – Document Phase 2 implementation plan

**Key Files:**
- Models: `app/Models/Subscription.php`, Enums: `app/Enums/Subscription*.php`
- Service: `app/Services/SubscriptionService.php`
- Observer: `app/Observers/SubscriptionObserver.php`
- Resource: `app/Filament/Resources/Subscriptions/`
- Widgets: `app/Filament/Widgets/SubscriptionsStatsWidget.php`, `UpcomingRenewalsWidget.php`
- Tests: `tests/Unit/SubscriptionServiceTest.php`

---

## 🧾 Phase 3 – Loans

**Goal:** Manage loans, payment schedule, interest, and remaining balance.

### Implementation

#### ✅ Already Done:
- ✅ Tables:
  - `loans` with fields: `name`, `account_id`, `total_amount`, `monthly_payment`, `withdrawal_day`, `skip_weekends`, `start_date`, `end_date`, `total_installments`, `paid_installments`, `remaining_amount`, `status`
  - `loan_payments` with: `loan_id`, `due_date`, `actual_date`, `amount`, `status`, `notes`
  - `interest_rate`, `variable_rate_columns` (for variable rate support)
  - `loan_payment_id` in `transactions` (linking)

- ✅ CRUD + Filament Resources:
  - `LoanResource.php` with `LoanPaymentsRelationManager`
  - Auto payment schedule generation on save

- ✅ Logic:
  - Auto payment schedule generation with weekend skip via `HasWorkdayCalculation`
  - Auto sync `paid_installments` + `remaining_amount`
  - Variable rate support (different amounts per installment)

- ✅ Service layer:
  - `LoanPaymentService.php` (payment management)
  - Auto transaction generation


**Status:** ✅ **COMPLETE & WORKING**

**Key Files:**
- Models: `app/Models/Loan.php`, `LoanPayment.php`
- Resources: `app/Filament/Resources/Loans/`
- Service: `app/Services/LoanPaymentService.php`
- Database: Migrations `2026_03_17_*` and later


---

## 💳 Phase 4 – Credit Cards

**Goal:** Manage credit cards, monthly expenses, and future debits.

### Implementation

#### ✅ Already Done:
- ✅ Tables:
  - `credit_cards` with: `user_id`, `name`, `account_id`, `type` (charge/revolving), `credit_limit`, `current_balance`, `fixed_payment`, `interest_rate`, `stamp_duty_amount`, `status`, `start_date`, `statement_day`, `due_day`, `skip_weekends`
  - `credit_card_expenses` with: `credit_card_id`, `amount`, `description`, `date`, `category_id`
  - `credit_card_cycles` with: `credit_card_id`, `period_start_date`, `statement_date`, `due_date`, `total_spent`, `total_due`, `interest_amount`, `paid_amount`, `status`
  - `credit_card_payments` with: `credit_card_id`, `cycle_id`, `principal_amount`, `interest_amount`, `stamp_duty_amount`, `total_amount`, `status`, `due_date`
  - `credit_card_payment_id` in `transactions`

- ✅ CRUD + Filament Resources:
  - `CreditCardResource.php` with relation managers: `CyclesRelationManager`, `ExpensesRelationManager`, `PaymentsRelationManager`
  - Cycle date validation form

- ✅ Service layer:
  - `CreditCardCycleService.php` (issueCycle, cycle calculations)
  - `CreditCardExpenseService.php` (expense management)
  - `RevolvingCreditCalculator.php` (daily balance interest calculations)
  - `CreditCardBalanceService.php` (debt tracking)

- ✅ Logic:
  - `withdrawal_date` calculation with `HasWorkdayCalculation`
  - Card residual = sum of unpaid amounts
  - **REVOLVING** support:
    - expense → increases current_balance
    - issue cycle → calculates interest + payment with fixed installment
    - payment PAID → reduces balance via observer
  - **CHARGE** support:
    - expense → residual
    - issue cycle → creates full amount payment
    - payment PAID → reduces balance

- ✅ Observers:
  - `CreditCardCycleObserver.php` (auto-creates payment when cycle PAID)
  - `CreditCardPaymentObserver.php` (reduces balance when payment PAID)

#### 🔧 Recently Fixed:
- 🔧 Card config validation before cycle issue (fixed_payment + interest_rate required)
- 🔧 Form fields always visible (removed problematic conditional visibility)
- 🔧 Live form updates when changing type

#### ⏳ To Test / Refine:
- ⏳ Cross-field date cycle validation (period_start_date <= statement_date <= due_date)
- ⏳ Full testing with user's real data

**Status:** 🟡 **IN PROGRESS – 85% COMPLETE**

**Key Files:**
- Models: `app/Models/CreditCard.php`, `CreditCardCycle.php`, `CreditCardExpense.php`, `CreditCardPayment.php`
- Resources: `app/Filament/Resources/CreditCards/`
- Services: `app/Services/CreditCardCycleService.php`, `CreditCardExpenseService.php`, `RevolvingCreditCalculator.php`, `CreditCardBalanceService.php`
- Observers: `app/Observers/CreditCardCycleObserver.php`, `CreditCardPaymentObserver.php`
- Database: Migrations `2026_03_18_*` and later

**Estimate for test + refinement:** 1–2 days

---

## 📊 Phase 5 – Finance Dashboard & Reports

**Goal:** Unified and historical view of all finances.

### Dashboard Widgets Planned

#### ⏳ To Implement:

- ⏳ **Total Net Worth**  
  Sum of active accounts, excluding debt accounts

- ⏳ **Total Debts**  
  Sum of Loan remaining_amount + unpaid card expenses + revolving balance

- ⏳ **Monthly Subscription Cost**  
  Sum of active subscriptions (linked to Phase 2)

- ⏳ **Upcoming Renewals**  
  Subscriptions + cards (renewals) with badges: danger (≤3d), warning (≤7d), success

- ⏳ **Due Payments**  
  Loans + Credit Cards next 7 days

- ⏳ **Current Month Cashflow**  
  Income vs Expenses vs Payments, excluding transfers

- ⏳ **Expenses by Category**  
  Pie chart with drill-down to subcategories

- ⏳ **Net Worth Over Time**  
  Line chart month by month

### Reports / Pivot

- ⏳ Monthly pivot table:
  - Income, Expenses, Payments, Total Expenses, Difference
  - Month-over-month comparison
  - Year filter

- ⏳ CSV/PDF export

- ⏳ Import from Excel/CSV with column mapping and preview

**Status:** ⏳ **TO DO**

**Estimate:** 3–5 days

**Tasks:**
- [ ] Create DashboardPage in Filament
- [ ] Implement base Widget (Filament Widget)
- [ ] Create Stat cards for KPIs
- [ ] Create Chart widgets (Apex Charts / Chart.js)
- [ ] Implement Report page with pivot table
- [ ] Add CSV/PDF export

---

## 🏋️ Phase 6 – Health & Fitness Module

**Goal:** Track physical health, medical visits, and key metrics.

### Required Implementation

- ⏳ Tables:
  - `health_metrics` (weight, height, BMI, body fat %, date)
  - `workouts` (dynamic type, duration, calories, notes, date)
  - `medical_records` (visit type, doctor, notes, PDF attachment, date)
  - `medications` (name, dosage, frequency, start/end dates)
  - `blood_tests` (test name, value, unit, range, date)

- ⏳ UI:
  - registration panels
  - health overview (weight trend, BMI, workout streak)

**Status:** ⏳ **TO DO**

**Estimate:** 5–7 days

---

## 🎯 Phase 7 – Productivity & Habit Tracker

**Goal:** Build a mini-Second Brain for productivity.

### Required Implementation

- ⏳ Tables:
  - `habits` (name, frequency, streak, heatmap)
  - `goals` (title, description, deadline, % completion, area)
  - `projects` (name, milestone, status, deadline)
  - `journal` (text, mood 1–5, date)
  - `notes` (title, body, dynamic tags, date)

**Status:** ⏳ **TO DO**

**Estimate:** 5–7 days

---

## 👥🏠🍝✈️ Phase 8 – Relationships, Home, Cooking, Travel

**Goal:** Complete the non-financial "Second Brain" side.

### Required Implementation

- ⏳ **Relationships & Home**:
  - `contacts` (contact info, birthdays, groups, gift ideas, last interaction)
  - `documents` (home documents, deadlines, attachments)
  - `vehicles` (vehicles, renewal deadlines for tax/insurance/service)
  - `home_expenses` (recurring home expenses)

- ⏳ **Cooking**:
  - `recipes` (ingredients JSON, portions, procedure, photo, rating, tags)
  - `meal_plans` (weekly, daily, shopping list)

- ⏳ **Travel**:
  - `trips` (destination, dates, budget, status)
  - `trip_wishlist` (destinations, priority, notes)

**Status:** ⏳ **TO DO**

**Estimate:** 8–12 days

---

## ⚙️ Phase 9 – Settings, Notifications, Backup, Import

**Goal:** Polish and "production-ready" operations.

### Required Implementation

- ⏳ Dynamic settings:
  - document types, vehicle types, global categories, context tags

- ⏳ Notifications:
  - financial deadlines, subscriptions, medical visits, documents

- ⏳ Backup and restore:
  - DB/app backup strategies
  - optional JSON/YAML settings export

- ⏳ Excel/CSV import:
  - import wizard for Budget_2026.xlsx and other templates

**Status:** ⏳ **TO DO**

**Estimate:** 3–5 days

---

## 🎯 Suggested Milestones

| Milestone | Phases | Status | Estimate |
|-----------|--------|--------|----------|
| **Finance Core Complete** | 1–5 | 🟡 78% | 4–6 days |
| **Health + Productivity** | 6–7 | ⏳ 0% | 10–14 days |
| **Full Second Brain** | 8 | ⏳ 0% | 8–12 days |
| **Hardening & SaaS-ready** | 9 | ⏳ 0% | 3–5 days |

---

## 📊 Project Status Summary

| Phase | Description | Status | % Complete | Next Steps |
|-------|-------------|--------|------------|-----------|
| 0 | Setup & Architecture | ✅ | 100% | N/A |
| 1 | Finance Core: Accounts & Transactions | ✅ | 100% | N/A |
| 2 | Subscriptions | ✅ | 100% | N/A |
| 3 | Loans | ✅ | 100% | N/A |
| 4 | Credit Cards | 🟡 | 85% | Fix revolving interest (daily balance method) |
| 5 | Finance Dashboard | ⏳ | 0% | Create widgets + reports |
| 6 | Health & Fitness | ⏳ | 0% | DB design + UI |
| 7 | Productivity & Habit | ⏳ | 0% | DB design + UI |
| 8 | Relationships, Home, Cooking, Travel | ⏳ | 0% | DB design + UI |
| 9 | Settings, Notifications, Backup | ⏳ | 0% | Design + UI |

---

## 🚀 Recommended Next Actions

### Short Term (1 week)
1. **Fix Credit Cards Revolving Interest** (Phase 4):
   - Implement daily balance method (Metodo del Saldo Medio Giornaliero)
   - Calculate `∑(daily_balance × TAN/365)` instead of annualized rate
   - Validate against real bank statements
   - Fix `CreditCardCycleService.php` + `RevolvingCreditCalculator.php`
   - Update tests in `tests/Unit/CreditCardCycleServiceTest.php`

2. **Refine Loans** (Phase 3):
   - Add revolving loan support (Amex-style)
   - Refine payment schedule regeneration (future-only vs full rebuild)
   - UX improvements (Livewire refresh on payment change)

### Medium Term (2–4 weeks)
3. **Phase 5 – Finance Dashboard** (Finance Core consolidation)
   - KPI widgets: Net Worth, Total Debts, Monthly Subscription Cost
   - Charts: Expenses by Category, Net Worth Over Time
   - Reports: Monthly pivot table, CSV/PDF export
   - Status widgets: Due Payments, Upcoming Renewals

### Long Term (post-Finance)
4. **Phase 6–7 – Health + Productivity** (Second Brain productivity)
5. **Phase 8–9 – Full integration** (Relationships, Settings, Backup)

---

## 🔗 Resources

**Recent Git Commits (Credit Cards refactoring):**
- `6d2933e` - fix: validate card configuration before allowing cycle issue
- `166a20b` - fix: add live update trigger for conditional fields in credit card form
- `248f0c2` - fix: use correct callable Set signature for Filament Schemas
- `2799c4d` - fix: add live() to conditional fields for proper reactivity
- `9f84ef2` - fix: make conditional fields always visible for revolving cards

**Test Suite:**
- `tests/Unit/RevolvingCreditCalculatorTest.php` (8 tests)
- `tests/Unit/CreditCardBalanceServiceTest.php` (15 tests)
- `tests/Unit/CreditCardCycleServiceTest.php` (6+ tests)
- `scripts/validate-credit-card-calculation.php` (validation tool)

---

**Last Updated:** 2026-03-23 23:55  
**Owner:** Copilot AI  
**Contact:** [user feedback]
