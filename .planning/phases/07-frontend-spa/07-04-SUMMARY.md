# 07-04 Summary — Loans + Credit Cards

## GraphQL queries used

### Loans

- `loans(first: 20, page: $page)` selecting:
  - `id`
  - `name`
  - `total_amount`
  - `remaining_amount`
  - `monthly_payment`
  - `paid_installments`
  - `total_installments`
  - `status`
  - `start_date`
  - `end_date`
  - `paginatorInfo { currentPage, lastPage, total }`

- `loan(id: $id)` selecting:
  - `id`
  - `name`
  - `total_amount`
  - `remaining_amount`
  - `monthly_payment`
  - `interest_rate`
  - `is_variable_rate`
  - `paid_installments`
  - `total_installments`
  - `status`
  - `start_date`
  - `end_date`
  - `payments { id, due_date, actual_date, amount, status, notes }`

- Loan mutations:
  - `createLoan(input: CreateLoanInput!)`
  - `updateLoan(id: ID!, input: UpdateLoanInput!)`
  - `deleteLoan(id: ID!)`

### Credit cards

- `creditCards(first: 20, page: $page)` selecting:
  - `id`
  - `name`
  - `type`
  - `credit_limit`
  - `current_balance`
  - `available_credit`
  - `status`
  - `statement_day`
  - `due_day`
  - `paginatorInfo { currentPage, lastPage, total }`

- `creditCard(id: $id)` selecting:
  - `id`
  - `name`
  - `type`
  - `credit_limit`
  - `current_balance`
  - `available_credit`
  - `interest_rate`
  - `status`
  - `statement_day`
  - `due_day`
  - `start_date`
  - `cycles { id, period_month, period_start_date, statement_date, due_date, total_spent, total_due, interest_amount, status, expenses { id, amount, description, spent_at } }`

- Credit-card mutations:
  - `createCreditCard(input: CreateCreditCardInput!)`
  - `updateCreditCard(id: ID!, input: UpdateCreditCardInput!)`
  - `deleteCreditCard(id: ID!)`

## Loan status values used in badge styling

- `active`
- `paid`
- `defaulted`

## Credit card cycle status values used

- `open`
- `closed`
- `paid`

## What shipped

- Loans list with amber progress bars
- Loan detail with payment schedule table and mobile card list
- Loan create/edit form with delete flow
- Credit-card list with purple available-credit bars
- Credit-card detail with expandable billing-cycle accordion and nested expenses
- Credit-card create/edit form with delete flow

## Deviations from plan

- None. The nested GraphQL queries, badge values, progress bars, and accordion behavior match the plan.
