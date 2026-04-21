---
phase: 7
title: "Mobile-Friendly Frontend SPA"
context_created: "2026-04-22"
---

# Phase 7 Context — Mobile-Friendly Frontend SPA

## Canonical Refs

- `.planning/PROJECT.md` — project principles and architecture decisions
- `.planning/ROADMAP.md` — phase goal and scope
- `graphql/schema.graphql` — GraphQL schema (types, queries, mutations) — primary data contract
- `routes/api.php` — REST routes (for auth login/logout which use REST, not GraphQL)
- `resources/js/` — Vue SPA location
- `vite.config.js` — Vite config (laravel-vite-plugin already configured)
- `tailwind.config.js` — Tailwind config (already configured)

---

## Phase Goal

Build a mobile-friendly Vue 3 SPA served from within the existing Laravel project (`/resources/js/`). The app consumes the v2.0 API (GraphQL for data, REST for auth). Users can consult and insert financial data across all 5 finance domains.

---

## Locked Decisions

### Platform & Architecture
- **Type:** Web SPA (Progressive Web App potential, but no PWA requirement for this phase)
- **Location:** `/resources/js/` — served by Vite + `laravel-vite-plugin`, same Laravel project
- **Routing:** Vue Router 4 — SPA routing via catch-all Laravel route
- **Entry point:** Single Blade view that mounts the Vue app (separate from Filament admin)
- **Admin vs SPA:** Filament admin stays at `/admin`, Vue SPA at `/app` (or `/`)

### Frontend Stack
- **Framework:** Vue 3 (Composition API + `<script setup>`)
- **Build tool:** Vite (already installed, `laravel-vite-plugin`)
- **Styling:** Tailwind CSS only — NO external UI component library. All components custom-built.
- **State management:** Pinia (official Vue 3 state management)
- **GraphQL client:** Apollo Client v3 + `@vue/apollo-composable`
- **HTTP (auth):** Axios or `fetch` for REST auth endpoints (login/logout/refresh)

### Design System
- **Style:** Minimal / dark theme — dark background, colored accents for numeric values (green for income, red for expenses, blue for balances)
- **Navigation:** Top navbar + hamburger menu on mobile (responsive)
- **Typography:** System font stack (no custom font import required)
- **Spacing:** Tailwind defaults (no custom spacing scale)
- **Color accents:** Green (#22c55e) for positive/income, Red (#ef4444) for negative/expense, Blue (#3b82f6) for neutral balances, Purple (#a855f7) for credit cards, Amber (#f59e0b) for subscriptions/loans

### Data Layer
- **Primary data source:** GraphQL via Apollo Client (`/graphql` endpoint)
- **Authentication:** REST endpoints — `POST /api/v1/auth/login`, `POST /api/v1/auth/refresh`, `POST /api/v1/auth/logout`
- **Token storage:** localStorage (access_token + refresh_token)
- **Token refresh:** Automatic — Apollo link intercepts 401 and calls refresh before retrying
- **GraphQL endpoint:** `/graphql` (same origin, served by Lighthouse)

---

## Pages & Routes

### Authentication
| Route | Component | Description |
|---|---|---|
| `/login` | `LoginView.vue` | Email + password form, calls REST login, stores tokens |

### Dashboard
| Route | Component | Description |
|---|---|---|
| `/` or `/dashboard` | `DashboardView.vue` | KPI cards: total balance, monthly cashflow (income vs expense), top spending categories. Uses `monthlyCashflow` + `totalByCategory` GraphQL queries. |

### Accounts
| Route | Component | Description |
|---|---|---|
| `/accounts` | `AccountsView.vue` | List of user accounts (cards with balance, currency, type). GraphQL `accounts` query. |
| `/accounts/new` | `AccountFormView.vue` | Create account form. `createAccount` mutation. |
| `/accounts/:id` | `AccountDetailView.vue` | Account detail with recent transactions list. `account` query. |
| `/accounts/:id/edit` | `AccountFormView.vue` | Edit account. `updateAccount` mutation. |

### Transactions
| Route | Component | Description |
|---|---|---|
| `/transactions` | `TransactionsView.vue` | Paginated list with filters (date from/to, account). GraphQL `transactions` query. |
| `/transactions/new` | `TransactionFormView.vue` | Create transaction (account selector, amount, date, category). `createTransaction` mutation. |
| `/transactions/:id/edit` | `TransactionFormView.vue` | Edit transaction. `updateTransaction` mutation. |

### Loans
| Route | Component | Description |
|---|---|---|
| `/loans` | `LoansView.vue` | List of loans (progress bar for paid/total installments). GraphQL `loans` query. |
| `/loans/new` | `LoanFormView.vue` | Create loan. `createLoan` mutation. |
| `/loans/:id` | `LoanDetailView.vue` | Loan detail with payment schedule. `loan` query with payments. |
| `/loans/:id/edit` | `LoanFormView.vue` | Edit loan. `updateLoan` mutation. |

### Credit Cards
| Route | Component | Description |
|---|---|---|
| `/credit-cards` | `CreditCardsView.vue` | List of cards with current balance and available credit. GraphQL `creditCards` query. |
| `/credit-cards/new` | `CreditCardFormView.vue` | Create credit card. `createCreditCard` mutation. |
| `/credit-cards/:id` | `CreditCardDetailView.vue` | Card detail with billing cycles. `creditCard` query with cycles. |
| `/credit-cards/:id/edit` | `CreditCardFormView.vue` | Edit card. `updateCreditCard` mutation. |

### Subscriptions
| Route | Component | Description |
|---|---|---|
| `/subscriptions` | `SubscriptionsView.vue` | List of subscriptions with next renewal and monthly cost. GraphQL `subscriptions` query (note: type is `ServiceSubscription` in schema). |
| `/subscriptions/new` | `SubscriptionFormView.vue` | Create subscription. `createSubscription` mutation. |
| `/subscriptions/:id/edit` | `SubscriptionFormView.vue` | Edit subscription. `updateSubscription` mutation. |

---

## Component Architecture

### Shared Components (reusable)
- `AppNavbar.vue` — top navbar with hamburger on mobile, auth user display, logout button
- `AppSidebar.vue` / mobile menu — collapsible links to all sections
- `KpiCard.vue` — stat card with label, value, optional delta/trend indicator
- `DataTable.vue` — generic paginated list with slot for row template
- `FormInput.vue` / `FormSelect.vue` / `FormDatePicker.vue` — form field wrappers
- `ConfirmModal.vue` — delete confirmation dialog
- `LoadingSpinner.vue` — async state indicator
- `EmptyState.vue` — "no items yet" placeholder with CTA

### Layout
- `AuthLayout.vue` — centered card layout for login
- `AppLayout.vue` — navbar + main content area for authenticated views

---

## Auth Flow

1. User visits any protected route → redirected to `/login` (Vue Router guard)
2. Login form calls `POST /api/v1/auth/login` (REST)
3. Tokens stored in localStorage: `fluxa_access_token`, `fluxa_refresh_token`
4. Apollo Client `authLink` adds `Authorization: Bearer {token}` to every GraphQL request
5. On 401 response → Apollo `errorLink` calls `POST /api/v1/auth/refresh`, updates token, retries original query
6. Logout calls `POST /api/v1/auth/logout`, clears localStorage, redirects to `/login`

---

## GraphQL Schema Notes (for downstream agents)

- **Important:** The Subscription type in GraphQL is `ServiceSubscription` (renamed to avoid conflict with GraphQL built-in Subscription root type). The model is still `App\Models\Subscription`.
- All paginated queries return `{ data: [...], paginatorInfo: { ... } }` (Lighthouse `@paginate` format)
- `monthlyCashflow(year: Int!, month: Int!)` → `MonthlyCashflow` type
- `totalByCategory(year: Int!, month: Int!)` → `[CategoryTotal!]!`
- Mutations use `@inject(context: "user.id", name: "user_id")` — client never sends `user_id`

---

## File Structure (target)

```
resources/js/
├── app.js                  # Vue app entry point + Apollo setup
├── router/
│   └── index.js           # Vue Router routes + auth guard
├── stores/
│   ├── auth.js            # Pinia: token storage, user state
│   └── index.js           # Pinia instance
├── apollo/
│   ├── client.js          # Apollo Client setup (authLink, errorLink)
│   └── queries/           # .graphql query files
├── views/
│   ├── auth/
│   │   └── LoginView.vue
│   ├── DashboardView.vue
│   ├── accounts/
│   ├── transactions/
│   ├── loans/
│   ├── credit-cards/
│   └── subscriptions/
├── components/
│   ├── layout/
│   │   ├── AppNavbar.vue
│   │   ├── AppLayout.vue
│   │   └── AuthLayout.vue
│   └── ui/
│       ├── KpiCard.vue
│       ├── DataTable.vue
│       ├── FormInput.vue
│       ├── ConfirmModal.vue
│       ├── LoadingSpinner.vue
│       └── EmptyState.vue
└── assets/
    └── css/
        └── app.css       # Tailwind directives
```

---

## Deferred Ideas

- PWA manifest + offline support (future phase)
- Push notifications for renewal reminders (future phase)
- Dark/light mode toggle (could add, but dark is default for this phase)
- Transaction import from CSV (future phase)
- Charts/graphs beyond dashboard KPIs (future phase)

---

## Downstream Agent Notes

- **Researcher:** Investigate Apollo Client v3 setup with Vue 3 Composition API (`@vue/apollo-composable`). Check `vite.config.js` to understand current build setup. Check if `vue`, `vue-router`, `pinia` are already in `package.json`. Investigate Tailwind dark theme setup (`dark:` classes vs CSS variables approach).
- **Planner:** Phase should be split into waves: (1) setup + auth + layout, (2) dashboard + accounts, (3) transactions + loans, (4) credit cards + subscriptions. Each wave should be independently deployable and testable.
