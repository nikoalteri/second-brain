# 💸 Fluxa – Project Roadmap 2026

> **Stack:** Laravel 12 + Filament 4 (admin), REST + GraphQL, MySQL  
> **Focus:** Personal finance tracking  
> **Last Updated:** 2026-04-23

---

## 📋 Status Legend

| Badge | Meaning |
|-------|---------|
| ✅ | Complete & working |
| 🟡 | In progress / Partially done |
| ⏳ | To do |

---

## 🌱 Phase 0 – Setup & Architecture ✅

**Goal:** Solid application foundation, roles, and admin panel.

- ✅ Laravel 12, Filament 4, MySQL, Auth
- ✅ Spatie Permissions — roles: `superadmin`, `admin`, `user`
- ✅ Shared traits: `HasWorkdayCalculation`, `HasUserScoping`

---

## 💰 Phase 1 – Accounts & Transactions ✅

**Goal:** Base model for daily financial operations.

- ✅ Tables: `accounts`, `transaction_types`, `transaction_categories`, `transactions`
- ✅ Transfer logic: dual IN/OUT rows linked by UUID
- ✅ Filament Resources: Account, Transaction, TransactionCategory, TransactionType
- ✅ Service: `AccountBalanceService`

---

## 📦 Phase 2 – Subscriptions ✅

**Goal:** Manage recurring subscriptions and total monthly cost.

- ✅ Subscriptions with backend-managed frequency records
- ✅ `SubscriptionService` with cost calculations, renewal logic, and posting support
- ✅ `SubscriptionObserver` for auto-calculation on create/update
- ✅ Dashboard reminders and admin widgets for upcoming renewals
- ✅ Account-backed and credit-card-backed subscription payments

---

## 🧾 Phase 3 – Loans ✅

**Goal:** Manage loans, payment schedules, interest, and remaining balance.

- ✅ Tables: `loans`, `loan_payments`
- ✅ Auto payment schedule generation with weekend skip
- ✅ Variable rate support
- ✅ Services: `LoanScheduleService`, `LoanPaymentPostingService`
- ✅ Auto transaction creation on payment posting

---

## 💳 Phase 4 – Credit Cards ✅

**Goal:** Manage credit cards, monthly expenses, cycles, and payments.

- ✅ Tables: `credit_cards`, `credit_card_expenses`, `credit_card_cycles`, `credit_card_payments`
- ✅ Charge and revolving card support
- ✅ Cycle issuance with interest calculation (daily balance / direct monthly)
- ✅ Services: `CreditCardCycleService`, `CreditCardExpenseService`, `RevolvingCreditCalculator`, `CreditCardBalanceService`
- ✅ KPI widgets: utilization, debt ratio, daily balance

---

## 📊 Phase 5 – Finance Dashboard & Reports ✅

**Goal:** Dashboards, KPIs, and report export.

- ✅ `FinanceReportService` for cashflow and net worth calculations
- ✅ Filament dashboard with KPI stat cards and finance widgets
- ✅ SPA dashboard with overview cards, reminders, cashflow graph, expense breakdown, and net-worth trend

---

## 🔌 Phase 6 – API Layer ✅

**Goal:** Enable off-admin access via REST/GraphQL APIs.

- ✅ REST endpoints for SPA-critical finance entities
- ✅ GraphQL coverage retained for finance aggregates and legacy flows still in use
- ✅ Bearer-token auth with login/me/refresh/logout flow
- ✅ Rate limiting for read/write API groups
- ✅ Scribe-generated API documentation and OpenAPI output

---

## 📱 Phase 7 – Frontend / Mobile ✅

**Goal:** Mobile-friendly interface for on-the-go finance tracking.

- ✅ Vue 3 SPA for finance flows
- ✅ SPA parity for accounts, transactions, loans, credit cards, and subscriptions
- ✅ Dashboard reminders for loans, credit cards, and subscriptions
- ✅ Cashflow, spending, and net-worth charts in the SPA dashboard
- ✅ Scheduler-backed finance automation visible through dashboard and posting flows

---

## 📈 Phase 8 – Advanced Analytics 🟡

**Goal:** Rich insights and reporting.

- ✅ Monthly/yearly spending reports
- ✅ Net-worth trend charts
- ⏳ PDF export hardening and broader report polish
- ⏳ Budget planning with alerts
- ⏳ Cashflow forecasting

---

## ✅ Current conclusion

Phases **0 through 7** are functionally complete. The remaining product work is no longer core finance parity; it is follow-up work in analytics, budgeting, forecasting, and future milestone definition.

**Last Updated:** 2026-04-23
