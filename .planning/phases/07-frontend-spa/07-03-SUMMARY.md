# 07-03 Summary — Transactions

## Exact schema additions

Added to `graphql/schema.graphql`:

- `transaction_type_id: ID!` on the `Transaction` type
- `transaction_category_id: ID` on the `Transaction` type
- `type TransactionType { id, name, is_income }`
- `transactionTypes: [TransactionType!]! @all @guard`
- `transactionCategories: [TransactionCategory!]! @all @guard`

## Transaction type lookup data

Current database values:

| ID | Name | is_income |
|---|---|---|
| 1 | Earnings | true |
| 2 | Expenses | false |
| 3 | Transferm | false |
| 4 | Cashback | true |
| 5 | Income | true |
| 6 | Expense | false |
| 7 | Payment | false |

## Transactions feature wiring

- `TransactionsView.vue`
  - queries `accounts(first: 100)` for the filter selector
  - queries `transactionCategories { id, name }` for local category labels
  - queries `transactions(first: 20, page: $page, account_id: $account_id)`
  - renders desktop/mobile rows via `DataTable`
  - supports account filtering plus local date-from/date-to filtering
  - resolves account/category labels from lookup maps to stay under the GraphQL complexity limit

- `TransactionFormView.vue`
  - queries `transactionTypes { id, name, is_income }`
  - queries `transactionCategories { id, name }`
  - queries `accounts(first: 100) { data { id, name } }`
  - queries `transaction(id: $id)` with `transaction_type_id` for edit-mode prefill
  - uses:
    - `createTransaction(input: CreateTransactionInput!)`
    - `updateTransaction(id: ID!, input: UpdateTransactionInput!)`
    - `deleteTransaction(id: ID!)`

## Deviations from plan

1. The plan assumed `transaction_type_id` could not be read back from GraphQL and expected edit mode to leave the type blank. The schema now exposes `transaction_type_id`, so edit mode pre-fills the selector properly.
2. The transactions list uses scalar `account_id` / `transaction_category_id` plus lookup queries instead of nested `account` / `category` selections to stay below Lighthouse's query complexity cap.
3. The lookup table currently contains `Transferm` in the live database for ID `3`, while the seeder source lists `Transfer`. This summary records the live value because that is what the form will render today.
