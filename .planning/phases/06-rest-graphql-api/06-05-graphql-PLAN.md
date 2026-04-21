---
plan: 5
phase: 6
title: "GraphQL Schema — Types, Queries, Mutations, Aggregated Resolvers"
wave: 3
depends_on: [1, 2, 3, 4]
requirements: [API-15, API-16, API-17, API-18]
files_modified:
  - graphql/schema.graphql
  - app/GraphQL/Queries/MonthlyCashflow.php
  - app/GraphQL/Queries/TotalByCategory.php
  - app/Models/Account.php
  - app/Models/Transaction.php
  - app/Models/Loan.php
  - app/Models/CreditCard.php
  - app/Models/Subscription.php
autonomous: true

must_haves:
  truths:
    - "GraphQL query { accounts { data { id name balance } } } returns only authenticated user's accounts"
    - "GraphQL mutation createAccount returns the created account object"
    - "GraphQL query { monthlyCashflow(year: 2026, month: 1) { total_income total_expense } } returns aggregated data"
    - "GraphQL query { transactions { data { id account { name } } } } does NOT produce N+1 queries (uses @with)"
    - "GraphQL introspection query returns all defined types with descriptions"
    - "Bearer token is required; unauthenticated requests return Unauthenticated error"
  artifacts:
    - path: "graphql/schema.graphql"
      provides: "All finance types, queries, mutations with @guard @paginate @with directives"
      contains: "type Account"
    - path: "graphql/schema.graphql"
      provides: "All mutation definitions"
      contains: "createAccount"
    - path: "app/GraphQL/Queries/MonthlyCashflow.php"
      provides: "Custom resolver for monthly income/expense aggregation"
      contains: "class MonthlyCashflow"
    - path: "app/GraphQL/Queries/TotalByCategory.php"
      provides: "Custom resolver for per-category totals"
      contains: "class TotalByCategory"
  key_links:
    - from: "graphql/schema.graphql"
      to: "config/lighthouse.php"
      via: "@guard directive uses sanctum guard"
      pattern: "@guard"
    - from: "app/GraphQL/Queries/MonthlyCashflow.php"
      to: "app/Models/Transaction.php"
      via: "Transaction::query()->where('user_id', $context->user()->id)"
      pattern: "where\\('user_id'"
---

## Objective

Expand the nearly-empty `graphql/schema.graphql` with full finance type definitions, paginated queries with N+1 prevention, CRUD mutations using Lighthouse directives, and custom resolvers for aggregated financial data queries.

**Purpose:** Delivers API-15 (N+1-safe queries), API-16 (mutations), API-17 (aggregated queries), API-18 (documented schema with introspection).

**Output:** Complete schema.graphql, two custom resolver classes.

## Tasks

<task id="T1" wave="2">
  <title>Define All Finance Types and Queries in schema.graphql</title>
  <read_first>
    - graphql/schema.graphql
    - config/lighthouse.php
    - app/Models/Account.php
    - app/Models/Transaction.php
    - app/Models/Loan.php
    - app/Models/CreditCard.php
    - app/Models/Subscription.php
    - app/Models/LoanPayment.php
    - app/Models/CreditCardCycle.php
  </read_first>
  <action>
**Replace the content of `graphql/schema.graphql` entirely** with the following. Preserve only the `DateTime` scalar at the top — do NOT keep the existing `user` / `users` queries (they are placeholder defaults not suited for the finance API):

```graphql
"A datetime string with format `Y-m-d H:i:s`, e.g. `2018-05-23 13:43:32`."
scalar DateTime @scalar(class: "Nuwave\\Lighthouse\\Schema\\Types\\Scalars\\DateTime")

"A date string with format `Y-m-d`, e.g. `2019-01-01`."
scalar Date @scalar(class: "Nuwave\\Lighthouse\\Schema\\Types\\Scalars\\Date")

# ─── FINANCE TYPES ────────────────────────────────────────────────────────────

"A bank account belonging to the authenticated user."
type Account {
    "Unique identifier."
    id: ID!
    "Display name of the account."
    name: String!
    "Account type (e.g. checking, savings, investment)."
    type: String!
    "Current balance in the account's currency."
    balance: Float!
    "Balance when the account was first created."
    opening_balance: Float!
    "ISO 4217 currency code, e.g. EUR."
    currency: String!
    "Whether the account is currently active."
    is_active: Boolean!
    "Transactions linked to this account (N+1 safe via @hasMany)."
    transactions: [Transaction!]! @hasMany
    created_at: DateTime!
    updated_at: DateTime!
}

"A financial transaction."
type Transaction {
    id: ID!
    account_id: ID!
    amount: Float!
    date: Date!
    description: String
    notes: String
    is_transfer: Boolean!
    "The account this transaction belongs to."
    account: Account! @belongsTo
    "Optional spending category."
    category: TransactionCategory @belongsTo(relation: "category")
    created_at: DateTime!
    updated_at: DateTime!
}

"A transaction spending category."
type TransactionCategory {
    id: ID!
    name: String!
    color: String
}

"A loan with amortization schedule."
type Loan {
    id: ID!
    name: String!
    account_id: ID!
    total_amount: Float!
    monthly_payment: Float!
    interest_rate: Float!
    is_variable_rate: Boolean!
    remaining_amount: Float!
    total_installments: Int!
    paid_installments: Int!
    status: String!
    start_date: Date
    end_date: Date
    "Scheduled payment installments."
    payments: [LoanPayment!]! @hasMany
    created_at: DateTime!
    updated_at: DateTime!
}

"A single loan payment installment."
type LoanPayment {
    id: ID!
    loan_id: ID!
    due_date: Date
    actual_date: Date
    amount: Float!
    interest_rate: Float
    status: String!
    notes: String
}

"A credit card with revolving or charge-card billing."
type CreditCard {
    id: ID!
    account_id: ID!
    name: String!
    "Card type: revolving or charge."
    type: String!
    credit_limit: Float
    fixed_payment: Float!
    interest_rate: Float!
    stamp_duty_amount: Float!
    statement_day: Int!
    due_day: Int!
    skip_weekends: Boolean!
    current_balance: Float!
    available_credit: Float
    status: String!
    start_date: Date
    interest_calculation_method: String
    "Billing cycles for this card."
    cycles: [CreditCardCycle!]! @hasMany
    created_at: DateTime!
    updated_at: DateTime!
}

"A monthly billing cycle for a credit card."
type CreditCardCycle {
    id: ID!
    credit_card_id: ID!
    period_month: String
    period_start_date: Date
    statement_date: Date
    due_date: Date
    total_spent: Float!
    total_due: Float!
    interest_amount: Float!
    principal_amount: Float!
    status: String!
    expenses: [CreditCardExpense!]! @hasMany
}

"A single expense charged to a credit card."
type CreditCardExpense {
    id: ID!
    credit_card_id: ID!
    credit_card_cycle_id: ID
    amount: Float!
    description: String
    notes: String
    spent_at: Date
    posted_at: Date
}

"A recurring subscription (software, services, etc.)."
type Subscription {
    id: ID!
    name: String!
    account_id: ID!
    monthly_cost: Float!
    annual_cost: Float!
    "Billing frequency: monthly, annual, or biennial."
    frequency: String!
    day_of_month: Int!
    next_renewal_date: Date
    auto_create_transaction: Boolean!
    status: String!
    notes: String
    created_at: DateTime!
    updated_at: DateTime!
}

"Monthly income vs expense summary for a given year/month."
type MonthlyCashflow {
    "Year of the cashflow period."
    year: Int!
    "Month of the cashflow period (1–12)."
    month: Int!
    "Total income transactions amount."
    total_income: Float!
    "Total expense transactions amount."
    total_expense: Float!
    "Net cashflow (income minus expense)."
    net: Float!
}

"Spending total for a single category."
type CategoryTotal {
    "Category name."
    category: String!
    "Total amount spent in this category."
    total: Float!
    "Number of transactions in this category."
    count: Int!
}

# ─── QUERIES ──────────────────────────────────────────────────────────────────

type Query {
    "List the authenticated user's accounts. Requires Bearer token."
    accounts(
        "Filter by active status."
        is_active: Boolean @eq
        "Filter by account type."
        type: String @eq
    ): [Account!]! @paginate(defaultCount: 20) @guard @scope(name: "belongsToAuthUser")

    "Get a single account by ID."
    account(id: ID! @eq): Account @find @guard @can(ability: "view", find: "id", model: "App\\Models\\Account")

    "List transactions for the authenticated user."
    transactions(
        account_id: ID @eq
        is_transfer: Boolean @eq
    ): [Transaction!]! @paginate(defaultCount: 20) @guard @with(["account", "category"]) @scope(name: "belongsToAuthUser")

    "Get a single transaction by ID."
    transaction(id: ID! @eq): Transaction @find @guard

    "List loans for the authenticated user."
    loans(
        status: String @eq
    ): [Loan!]! @paginate(defaultCount: 20) @guard @with(["payments"]) @scope(name: "belongsToAuthUser")

    "Get a single loan by ID."
    loan(id: ID! @eq): Loan @find @guard

    "List credit cards for the authenticated user."
    creditCards(
        status: String @eq
        type: String @eq
    ): [CreditCard!]! @paginate(defaultCount: 20) @guard @scope(name: "belongsToAuthUser")

    "Get a single credit card by ID."
    creditCard(id: ID! @eq): CreditCard @find @guard

    "List subscriptions for the authenticated user."
    subscriptions(
        status: String @eq
        frequency: String @eq
    ): [Subscription!]! @paginate(defaultCount: 20) @guard @scope(name: "belongsToAuthUser")

    "Get a single subscription by ID."
    subscription(id: ID! @eq): Subscription @find @guard

    "Monthly cashflow summary: total income and expense for the given year/month."
    monthlyCashflow(year: Int!, month: Int!): MonthlyCashflow!
        @guard
        @field(resolver: "App\\GraphQL\\Queries\\MonthlyCashflow")

    "Total spending grouped by category for the given year/month."
    totalByCategory(year: Int!, month: Int!): [CategoryTotal!]!
        @guard
        @field(resolver: "App\\GraphQL\\Queries\\TotalByCategory")
}
```
  </action>
  <acceptance_criteria>
  - `graphql/schema.graphql` contains `type Account`
  - `graphql/schema.graphql` contains `type Transaction`
  - `graphql/schema.graphql` contains `type Loan`
  - `graphql/schema.graphql` contains `type CreditCard`
  - `graphql/schema.graphql` contains `type Subscription`
  - `graphql/schema.graphql` contains `type MonthlyCashflow`
  - `graphql/schema.graphql` contains `type CategoryTotal`
  - `graphql/schema.graphql` contains `@guard` (at least 8 occurrences for the query fields)
  - `graphql/schema.graphql` contains `@scope(name: "belongsToAuthUser")` on ALL 5 list queries (accounts, transactions, loans, creditCards, subscriptions)
  - `graphql/schema.graphql` contains `@with(["account", "category"])` on transactions query
  - `graphql/schema.graphql` contains `@paginate(defaultCount: 20)`
  - `graphql/schema.graphql` contains `monthlyCashflow(year: Int!, month: Int!)`
  - `php artisan lighthouse:validate-schema` exits 0 (if Lighthouse provides this command; otherwise check for syntax errors)
  </acceptance_criteria>
</task>

<task id="T2" wave="2">
  <title>Add All Mutations + Create Custom Aggregate Resolvers</title>
  <read_first>
    - graphql/schema.graphql
    - app/Models/Account.php
    - app/Models/Transaction.php
    - app/Models/Loan.php
    - app/Models/CreditCard.php
    - app/Models/Subscription.php
  </read_first>
  <action>
**Step 1 — Append mutations to `graphql/schema.graphql`:**
Add the following AFTER the `type Query` block:

```graphql
# ─── INPUT TYPES ──────────────────────────────────────────────────────────────

input CreateAccountInput {
    name: String!
    type: String!
    opening_balance: Float
    currency: String!
    is_active: Boolean
}

input UpdateAccountInput {
    name: String
    type: String
    currency: String
    is_active: Boolean
}

input CreateTransactionInput {
    account_id: ID!
    transaction_type_id: ID!
    transaction_category_id: ID
    amount: Float!
    date: Date!
    description: String!
    notes: String
    is_transfer: Boolean
    to_account_id: ID
}

input UpdateTransactionInput {
    account_id: ID
    transaction_type_id: ID
    transaction_category_id: ID
    amount: Float
    date: Date
    description: String
    notes: String
}

input CreateLoanInput {
    account_id: ID!
    name: String!
    total_amount: Float!
    monthly_payment: Float!
    interest_rate: Float
    is_variable_rate: Boolean
    withdrawal_day: Int!
    skip_weekends: Boolean
    start_date: Date!
    end_date: Date
    total_installments: Int!
    paid_installments: Int!
    remaining_amount: Float
    status: String!
}

input UpdateLoanInput {
    name: String
    total_amount: Float
    monthly_payment: Float
    interest_rate: Float
    is_variable_rate: Boolean
    status: String
}

input CreateCreditCardInput {
    account_id: ID!
    name: String!
    type: String!
    credit_limit: Float
    fixed_payment: Float
    interest_rate: Float
    stamp_duty_amount: Float
    statement_day: Int!
    due_day: Int!
    skip_weekends: Boolean
    status: String!
    start_date: Date!
    interest_calculation_method: String
}

input UpdateCreditCardInput {
    name: String
    credit_limit: Float
    fixed_payment: Float
    interest_rate: Float
    statement_day: Int
    due_day: Int
    skip_weekends: Boolean
    status: String
}

input CreateSubscriptionInput {
    account_id: ID!
    name: String!
    monthly_cost: Float
    annual_cost: Float
    frequency: String!
    day_of_month: Int!
    next_renewal_date: Date!
    category_id: ID
    auto_create_transaction: Boolean
    status: String!
    notes: String
}

input UpdateSubscriptionInput {
    name: String
    monthly_cost: Float
    annual_cost: Float
    frequency: String
    day_of_month: Int
    next_renewal_date: Date
    status: String
    notes: String
}

# ─── MUTATIONS ────────────────────────────────────────────────────────────────

type Mutation {
    "Create a new bank account."
    createAccount(input: CreateAccountInput! @spread): Account!
        @create @guard @inject(context: "user.id", name: "user_id")

    "Update an existing account."
    updateAccount(id: ID!, input: UpdateAccountInput! @spread): Account!
        @update @guard

    "Delete an account by ID."
    deleteAccount(id: ID!): Account
        @delete @guard

    "Create a new transaction."
    createTransaction(input: CreateTransactionInput! @spread): Transaction!
        @create @guard @inject(context: "user.id", name: "user_id")

    "Update an existing transaction."
    updateTransaction(id: ID!, input: UpdateTransactionInput! @spread): Transaction!
        @update @guard

    "Delete a transaction by ID."
    deleteTransaction(id: ID!): Transaction
        @delete @guard

    "Create a new loan."
    createLoan(input: CreateLoanInput! @spread): Loan!
        @create @guard @inject(context: "user.id", name: "user_id")

    "Update an existing loan."
    updateLoan(id: ID!, input: UpdateLoanInput! @spread): Loan!
        @update @guard

    "Delete a loan by ID."
    deleteLoan(id: ID!): Loan
        @delete @guard

    "Create a new credit card."
    createCreditCard(input: CreateCreditCardInput! @spread): CreditCard!
        @create @guard @inject(context: "user.id", name: "user_id")

    "Update an existing credit card."
    updateCreditCard(id: ID!, input: UpdateCreditCardInput! @spread): CreditCard!
        @update @guard

    "Delete a credit card by ID."
    deleteCreditCard(id: ID!): CreditCard
        @delete @guard

    "Create a new subscription."
    createSubscription(input: CreateSubscriptionInput! @spread): Subscription!
        @create @guard @inject(context: "user.id", name: "user_id")

    "Update an existing subscription."
    updateSubscription(id: ID!, input: UpdateSubscriptionInput! @spread): Subscription!
        @update @guard

    "Delete a subscription by ID."
    deleteSubscription(id: ID!): Subscription
        @delete @guard
}
```

**Step 2 — Create `app/GraphQL/Queries/MonthlyCashflow.php`:**
```bash
mkdir -p app/GraphQL/Queries
```

```php
<?php

namespace App\GraphQL\Queries;

use App\Models\Transaction;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class MonthlyCashflow
{
    public function __invoke(mixed $root, array $args, GraphQLContext $context, ResolveInfo $info): array
    {
        $user  = $context->user();
        $year  = (int) $args['year'];
        $month = (int) $args['month'];

        $result = Transaction::query()
            ->where('user_id', $user->id)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->selectRaw('
                SUM(CASE WHEN t.type = "income" OR tt.name = "income" THEN amount ELSE 0 END) as total_income,
                SUM(CASE WHEN t.type = "expense" OR tt.name = "expense" THEN amount ELSE 0 END) as total_expense
            ')
            ->from('transactions as t')
            ->leftJoin('transaction_types as tt', 't.transaction_type_id', '=', 'tt.id')
            ->first();

        $income  = (float) ($result->total_income ?? 0);
        $expense = (float) ($result->total_expense ?? 0);

        return [
            'year'          => $year,
            'month'         => $month,
            'total_income'  => $income,
            'total_expense' => $expense,
            'net'           => $income - $expense,
        ];
    }
}
```

**IMPORTANT:** Before finalizing `MonthlyCashflow.php`, read `app/Models/Transaction.php` and `app/Models/TransactionType.php` to understand the actual column and enum structure. The query above uses a join to `transaction_types` — adjust the `CASE WHEN` logic based on how income/expense distinction actually works in the data model (by `transaction_type_id` join or a direct `type` column on transactions). If transactions don't have a direct income/expense type, use `transaction_type_id` in a subquery for user's own transaction types.

**Step 3 — Create `app/GraphQL/Queries/TotalByCategory.php`:**
```php
<?php

namespace App\GraphQL\Queries;

use App\Models\Transaction;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Illuminate\Support\Facades\DB;

class TotalByCategory
{
    public function __invoke(mixed $root, array $args, GraphQLContext $context, ResolveInfo $info): array
    {
        $user  = $context->user();
        $year  = (int) $args['year'];
        $month = (int) $args['month'];

        return Transaction::query()
            ->selectRaw('COALESCE(tc.name, "Uncategorised") as category, SUM(t.amount) as total, COUNT(*) as count')
            ->from('transactions as t')
            ->leftJoin('transaction_categories as tc', 't.transaction_category_id', '=', 'tc.id')
            ->where('t.user_id', $user->id)
            ->whereYear('t.date', $year)
            ->whereMonth('t.date', $month)
            ->groupBy('t.transaction_category_id', 'tc.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category,
                'total'    => (float) $row->total,
                'count'    => (int) $row->count,
            ])
            ->toArray();
    }
}
```
  </action>
  <acceptance_criteria>
  - `graphql/schema.graphql` contains `type Mutation`
  - `graphql/schema.graphql` contains `createAccount(input: CreateAccountInput! @spread): Account!`
  - `graphql/schema.graphql` contains `@create @guard @inject(context: "user.id", name: "user_id")` for ALL 5 create mutations (createAccount, createTransaction, createLoan, createCreditCard, createSubscription)
  - `graphql/schema.graphql` contains `@delete @guard`
  - `graphql/schema.graphql` contains `input CreateAccountInput`
  - `graphql/schema.graphql` contains `input CreateTransactionInput`
  - `graphql/schema.graphql` contains `input CreateLoanInput`
  - `graphql/schema.graphql` contains `input CreateCreditCardInput`
  - `graphql/schema.graphql` contains `input CreateSubscriptionInput`
  - `app/GraphQL/Queries/MonthlyCashflow.php` exists and contains `$context->user()`
  - `app/GraphQL/Queries/MonthlyCashflow.php` contains `where('user_id', $user->id)`
  - `app/GraphQL/Queries/TotalByCategory.php` exists and contains `groupBy(`
  - `app/GraphQL/Queries/TotalByCategory.php` contains `where('t.user_id', $user->id)`
  - `php artisan config:clear` exits 0
  </acceptance_criteria>
</task>

<task id="T2b" wave="2">
  <title>Add scopeBelongsToAuthUser to All 5 Finance Models</title>
  <read_first>
    - app/Models/Account.php
    - app/Models/Transaction.php
    - app/Models/Loan.php
    - app/Models/CreditCard.php
    - app/Models/Subscription.php
  </read_first>
  <action>
**WHY:** The GraphQL schema uses `@scope(name: "belongsToAuthUser")` on all 5 `@paginate` queries. Lighthouse resolves this directive by calling the named scope on the model's Eloquent builder. Without this scope defined on each model, all 5 list queries will throw a `BadMethodCallException` at runtime and the security isolation (API-04) will be broken.

For each of the 5 models, open the file and add the following scope method inside the class body (after the existing fillable/casts properties, before any existing scope methods):

```php
/**
 * Scope: filter to records belonging to the authenticated user.
 * Used by Lighthouse @scope(name: "belongsToAuthUser") on GraphQL paginated queries.
 */
public function scopeBelongsToAuthUser($query): \Illuminate\Database\Eloquent\Builder
{
    return $query->where('user_id', auth()->id());
}
```

Apply this to:
1. `app/Models/Account.php`
2. `app/Models/Transaction.php`
3. `app/Models/Loan.php`
4. `app/Models/CreditCard.php`
5. `app/Models/Subscription.php`

**IMPORTANT:** Do NOT add this scope if a model already defines `scopeBelongsToAuthUser`. Check first with `grep -n "scopeBelongsToAuthUser"` on each file.
  </action>
  <acceptance_criteria>
  - `grep -n "scopeBelongsToAuthUser" app/Models/Account.php` finds a match
  - `grep -n "scopeBelongsToAuthUser" app/Models/Transaction.php` finds a match
  - `grep -n "scopeBelongsToAuthUser" app/Models/Loan.php` finds a match
  - `grep -n "scopeBelongsToAuthUser" app/Models/CreditCard.php` finds a match
  - `grep -n "scopeBelongsToAuthUser" app/Models/Subscription.php` finds a match
  - Each scope contains `where('user_id', auth()->id())`
  - `php artisan inspire` exits 0 (no syntax errors in any model)
  </acceptance_criteria>
</task>

<task id="T3" wave="2">
  <title>Validate GraphQL Schema and Test Basic Introspection</title>
  <read_first>
    - graphql/schema.graphql
    - config/lighthouse.php
  </read_first>
  <action>
**Step 1 — Clear caches and validate Lighthouse can parse the schema:**
```bash
php artisan config:clear
php artisan cache:clear
```

**Step 2 — Check if Lighthouse provides a validation command:**
```bash
php artisan list | grep lighthouse
```

If `lighthouse:validate-schema` is available:
```bash
php artisan lighthouse:validate-schema
```

If not available, test by running a simple introspection request via curl (requires the development server running):
```bash
php artisan serve &
sleep 2

# Get a test token first (requires an existing user in the database)
# If no users, create one via tinker:
php artisan tinker --execute="
\$user = \App\Models\User::factory()->create(['email'=>'api_test@test.com','password'=>bcrypt('password')]);
echo \$user->createToken('test')->plainTextToken;
"
```

**Step 3 — Test introspection:**
```bash
curl -s -X POST http://localhost:8000/graphql \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {token_from_step_2}" \
  -d '{"query":"{ __schema { types { name } } }"}' | jq '.data.__schema.types | map(.name) | sort'
```

Expected output: includes `Account`, `Transaction`, `Loan`, `CreditCard`, `Subscription`, `MonthlyCashflow`, `CategoryTotal`.

**Step 4 — Fix any schema validation errors** that appear. Common issues:
- Missing `@belongsTo` relation name when it differs from field name (e.g., `category` relation in Transaction model)
- Enum values in `Rule::in()` that don't match actual PHP enum cases
- `@with` directive syntax errors

**Step 5 — Verify @guard works with Bearer token:**
```bash
# Without token — should get Unauthenticated error
curl -s -X POST http://localhost:8000/graphql \
  -H "Content-Type: application/json" \
  -d '{"query":"{ accounts { data { id } } }"}' | jq .errors
```
  </action>
  <acceptance_criteria>
  - `graphql/schema.graphql` has no syntax errors (Lighthouse parses successfully)
  - `php artisan config:clear && php artisan cache:clear` exits 0
  - Introspection returns type names including `Account`, `Transaction`, `Loan`, `CreditCard`, `Subscription`
  - Unauthenticated GraphQL request to `accounts` returns `Unauthenticated` error (not a PHP exception)
  - `app/GraphQL/Queries/MonthlyCashflow.php` and `TotalByCategory.php` are autoloaded: `php artisan inspire` exits 0
  </acceptance_criteria>
</task>

## Verification

```bash
php artisan config:clear && php artisan cache:clear

# List all GraphQL types via introspection
curl -s -X POST http://localhost:8000/graphql \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {valid_token}" \
  -d '{"query":"{ __schema { types { name description } } }"}' | jq '.data.__schema.types[] | select(.name | test("^(Account|Transaction|Loan|CreditCard|Subscription|MonthlyCashflow|CategoryTotal)$"))'

# Test accounts query
curl -s -X POST http://localhost:8000/graphql \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {valid_token}" \
  -d '{"query":"{ accounts { data { id name balance } } }"}' | jq .

# Test monthlyCashflow
curl -s -X POST http://localhost:8000/graphql \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {valid_token}" \
  -d '{"query":"{ monthlyCashflow(year: 2026, month: 1) { total_income total_expense net } }"}' | jq .
```

## Success Criteria

- All 5 finance types + 2 aggregate types defined in schema with descriptions
- All queries return data for authenticated user only
- All mutations work (`createAccount`, `createTransaction`, etc.) via Lighthouse `@create`
- `monthlyCashflow` and `totalByCategory` return correctly aggregated data
- GraphQL introspection shows all types with field descriptions (API-18)
- Unauthenticated request returns `{"errors": [{"message": "Unauthenticated."}]}` (API-15 guard)
- No N+1 queries on transactions (uses `@with(["account", "category"])`)

## Output

After completion, create `.planning/phases/06-rest-graphql-api/06-5-SUMMARY.md`.
