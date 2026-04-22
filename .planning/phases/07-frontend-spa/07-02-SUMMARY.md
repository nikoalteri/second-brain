# 07-02 Summary — Dashboard + Accounts

## GraphQL queries used

### `DashboardView.vue`

- `monthlyCashflow(year: $year, month: $month)` selecting:
  - `year`
  - `month`
  - `total_income`
  - `total_expense`
  - `net`
- `totalByCategory(year: $year, month: $month)` selecting:
  - `category`
  - `total`
  - `count`
- `accounts(first: 100)` selecting:
  - `id`
  - `balance`
  - `currency`

### `AccountsView.vue`

- `accounts(first: 20, page: $page)` selecting:
  - `id`
  - `name`
  - `type`
  - `balance`
  - `currency`
  - `is_active`
  - `paginatorInfo { currentPage, lastPage, total }`

### `AccountDetailView.vue`

- `account(id: $id)` selecting:
  - `id`
  - `name`
  - `type`
  - `balance`
  - `currency`
  - `is_active`
  - `transactions { id, amount, date, description, is_transfer, category { id, name } }`

### `AccountFormView.vue`

- Query: `account(id: $id)` selecting `id`, `name`, `type`, `opening_balance`, `currency`, `is_active`
- Mutations:
  - `createAccount(input: CreateAccountInput!)`
  - `updateAccount(id: ID!, input: UpdateAccountInput!)`
  - `deleteAccount(id: ID!)`

## Chart.js plugins registered

- `CategoryScale`
- `LinearScale`
- `BarElement`
- `Title`
- `Tooltip`
- `Legend`

## Account form options

- Account types: `bank`, `cash`, `investment`, `emergency_fund`, `debt`
- Currencies: `EUR`, `USD`, `GBP`, `CHF`

## What shipped

- Dashboard with 4 KPI cards, grouped bar chart, and category total grid
- Accounts grid with pagination and active/inactive badges
- Account detail page with recent transactions table
- Shared create/edit account form with delete confirmation flow

## Deviations from plan

1. The account-type options were aligned to the existing backend enum/Filament resource values (`bank`, `cash`, `investment`, `emergency_fund`, `debt`) so the SPA create/edit form can submit valid account records.
