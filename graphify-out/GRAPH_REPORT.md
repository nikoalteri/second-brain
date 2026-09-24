# Graph Report - second-brain  (2026-09-24)

## Corpus Check
- Large corpus: 742 files · ~341,098 words. Semantic extraction will be expensive (many Claude tokens). Consider running on a subfolder.

## Summary
- 4697 nodes · 10570 edges · 261 communities (131 shown, 130 thin omitted)
- Extraction: 98% EXTRACTED · 2% INFERRED · 0% AMBIGUOUS · INFERRED: 171 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- User & 2FA Model
- Credit Card Enums
- Accounts Filament Table
- CreditCard Model
- Vue App Shell & Chat
- Transactions & Categories
- Filament Forms
- CreditCard Controller
- Account Model
- Accounts Resource
- Auth Controller & Tokens
- User Settings Controller
- ListAccounts & Cycles
- Transaction Model
- Credit Card Detail View
- Dashboard View
- Permission Migrations
- Loan Payments Job
- Loan Model & Repository
- Finance Report View
- Account Controllers
- Shared Vue Components
- Data Integrity Audit
- Sensitive Vault Panel
- Phase 19 Credit Card Cycles
- Category Creation Modal
- Filament Dashboard
- Filament List Pages
- Create Accounts Page
- Edit Accounts Page
- Community 30
- Community 31
- Community 32
- Community 33
- Community 34
- Community 35
- Community 36
- Community 37
- Community 38
- Community 39
- Community 40
- Community 41
- Community 42
- Community 43
- Community 44
- Community 45
- Community 46
- Community 47
- Community 48
- Community 49
- Community 50
- Community 51
- Community 52
- Community 53
- Community 54
- Community 55
- Community 56
- Community 57
- Community 58
- Community 59
- Community 60
- Community 61
- Community 62
- Community 63
- Community 64
- Community 65
- Community 66
- Community 67
- Community 68
- Community 69
- Community 70
- Community 71
- Community 72
- Community 73
- Community 74
- Community 75
- Community 76
- Community 77
- Community 78
- Community 79
- Community 80
- Community 81
- Community 82
- Community 83
- Community 84
- Community 85
- Community 86
- Community 87
- Community 88
- Community 89
- Community 90
- Community 91
- Community 92
- Community 93
- Community 94
- Community 95
- Community 96
- Community 97
- Community 98
- Community 99
- Community 100
- Community 101
- Community 102
- Community 103
- Community 104
- Community 105
- Community 106
- Community 107
- Community 108
- Community 109
- Community 110
- Community 111
- Community 112
- Community 113
- Community 114
- Community 115
- Community 116
- Community 117
- Community 118
- Community 119
- Community 120
- Community 121
- Community 122
- Community 124
- Community 125
- Community 126
- Community 127
- Community 128
- Community 129
- Community 130
- Community 131
- Community 132
- Community 133
- Community 134
- Community 135
- Community 136
- Community 137
- Community 138
- Community 139
- Community 140
- Community 141
- Community 142
- Community 143
- Community 144
- Community 145
- Community 146
- Community 147
- Community 148
- Community 149
- Community 150
- Community 151
- Community 152
- Community 153
- Community 154
- Community 155
- Community 156
- Community 157
- Community 158
- Community 159
- Community 160
- Community 161
- Community 162
- Community 163
- Community 164
- Community 165
- Community 166
- Community 167
- Community 168
- Community 169
- Community 170
- Community 171
- Community 172
- Community 173
- Community 174
- Community 175
- Community 176
- Community 177
- Community 178
- Community 179
- Community 180
- Community 181
- Community 182
- Community 183
- Community 184
- Community 185
- Community 186
- Community 187
- Community 188
- Community 189
- Community 190
- Community 191
- Community 192
- Community 193
- Community 194
- Community 195
- Community 196
- Community 197
- Community 198
- Community 199
- Community 200
- Community 201
- Community 202
- Community 203
- Community 204
- Community 205
- Community 206
- Community 207
- Community 208
- Community 209
- Community 210
- Community 211
- Community 212
- Community 213
- Community 214
- Community 215
- Community 227
- Community 228
- Community 230

## God Nodes (most connected - your core abstractions)
1. `User` - 510 edges
2. `Account` - 349 edges
3. `CreditCard` - 275 edges
4. `TestCase` - 207 edges
5. `Transaction` - 174 edges
6. `CreditCardCycle` - 109 edges
7. `Loan` - 105 edges
8. `Subscription` - 96 edges
9. `TransactionType` - 94 edges
10. `CreditCardExpense` - 88 edges

## Surprising Connections (you probably didn't know these)
- `lighthouse:clear-cache on deploy` --references--> `graphql/schema.graphql finance schema`  [INFERRED]
  .github/workflows/deploy-uat.yml → .planning/phases/06-rest-graphql-api/06-05-graphql-PLAN.md
- `Token expiry and refresh rotation pitfall` --semantically_similar_to--> `Single-use refresh token rotation`  [INFERRED] [semantically similar]
  .planning/research/PITFALLS.md → docs/API.md
- `AGENTS.md project instructions` --semantically_similar_to--> `CLAUDE.md project instructions`  [INFERRED] [semantically similar]
  AGENTS.md → CLAUDE.md
- `{closure#5}()` --references--> `CreditCard`  [EXTRACTED]
  routes/console.php → app/Models/CreditCard.php
- `{closure#2}()` --references--> `CreditCard`  [EXTRACTED]
  tests/Feature/ScheduledCommandErrorIsolationTest.php → app/Models/CreditCard.php

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Phase 6 REST and GraphQL API plan sequence** — planning_phases_06_rest_graphql_api_06_01_foundation_plan, planning_phases_06_rest_graphql_api_06_02_auth_error_handling_plan, planning_phases_06_rest_graphql_api_06_03_accounts_transactions_plan, planning_phases_06_rest_graphql_api_06_04_loans_creditcards_subscriptions_plan, planning_phases_06_rest_graphql_api_06_05_graphql_plan, planning_phases_06_rest_graphql_api_06_06_api_docs_plan, planning_phases_06_rest_graphql_api_06_07_tests_plan [EXTRACTED 1.00]
- **REST resource controllers using explicit user scoping and QueryBuilder** — planning_phases_06_rest_graphql_api_06_03_accounts_transactions_plan_account_controller, planning_phases_06_rest_graphql_api_06_03_accounts_transactions_plan_transaction_controller, planning_phases_06_rest_graphql_api_06_04_loans_creditcards_subscriptions_plan_loan_controller, planning_phases_06_rest_graphql_api_06_04_loans_creditcards_subscriptions_plan_credit_card_controller, planning_phases_06_rest_graphql_api_06_04_loans_creditcards_subscriptions_plan_subscription_controller [EXTRACTED 1.00]
- **UAT deploy safety flow (backup, deploy, smoke test, cleanup)** — github_workflows_deploy_uat_backup_database, github_workflows_deploy_uat_deploy_step, github_workflows_deploy_uat_smoke_test, github_workflows_deploy_uat_remove_backup [EXTRACTED 1.00]
- **Shared locale flow across backend and SPA** — locale_contract, locale_mw, vue_i18n, filament_profile [EXTRACTED 1.00]
- **SPA foundation stack** — vue_spa, apollo, pinia_auth, vue_router, vite_cfg [EXTRACTED 1.00]
- **Chatbot intent handlers implementing ChatIntent** — planning_phases_17_custom_read_only_finance_chatbot_engine_17_03_summary_accountbalancesintent, planning_phases_17_custom_read_only_finance_chatbot_engine_17_03_summary_upcomingpaymentsintent, planning_phases_17_custom_read_only_finance_chatbot_engine_17_03_summary_monthlyspendingintent, planning_phases_17_custom_read_only_finance_chatbot_engine_17_02_plan_chatintent [EXTRACTED 1.00]
- **Chatbot frontend widget composition** — planning_phases_17_custom_read_only_finance_chatbot_engine_17_06_summary_chatwidget, planning_phases_17_custom_read_only_finance_chatbot_engine_17_05_summary_chatbot_store, resources_js_components_chatbot_chatmessagebubble, resources_js_components_chatbot_chatquickreplies, resources_js_components_chatbot_chatfreetextinput [EXTRACTED 1.00]
- **Phase 18 scoping proof tests** — planning_phases_18_hardening_security_proof_close_the_auth_scoping_superadmin_b_18_02_summary_scopingsecuritytest, planning_phases_18_hardening_security_proof_close_the_auth_scoping_superadmin_b_18_02_summary_adminpanelscopingtest, planning_phases_18_hardening_security_proof_close_the_auth_scoping_superadmin_b_18_03_summary_consolescopingtest, planning_phases_18_hardening_security_proof_close_the_auth_scoping_superadmin_b_18_05_summary_observerstaticstatetest [INFERRED 0.85]
- **Phase 19 interest-engine defect fixes** — app_services_creditcardcycleservice_ensurecurrentmonthcycle, app_services_revolvingcreditcalculator_calculatedailybalances, app_services_revolvingcreditcalculator_calculatepaymentbreakdown, app_services_revolvingcreditcalculator_calculateinterestdirectmonthly [EXTRACTED 1.00]
- **Credit-card observer to cycle-service balance chain** — app_observers_creditcardpaymentobserver, app_observers_creditcardexpenseobserver, app_services_creditcardexpenseservice_validateexpensechange, app_services_creditcardcycleservice_synccycleandcardfrompayment, app_services_creditcardcycleservice_synccardbalance [INFERRED 0.85]
- **Phases reusing Notification delivery** — _planning_phases_22_proactive_notifications_wire_the_existing_notification_model_to_real_financial_triggers_22_context_daily_digest, _planning_phases_23_subscription_price_hike_detection_23_context_price_hike, _planning_phases_27_automated_statement_reconciliation_27_context_auto_reconciliation [EXTRACTED 1.00]
- **Dashboard aggregate figures** — _planning_phases_24_cash_flow_forecast_safe_to_spend_24_context_safe_to_spend, _planning_phases_26_debt_and_subscription_totals_chatbot_and_dashboard_26_context_debt_totals, _planning_phases_24_cash_flow_forecast_safe_to_spend_24_context_dashboardcontroller [INFERRED 0.85]
- **v3.0 Mobile API and Health research document set** — planning_research_architecture, planning_research_features, planning_research_implementation_roadmap, planning_research_pitfalls, planning_research_stack, planning_research_summary [INFERRED 0.85]
- **Phase 28 categorization precedence and application rules** — planning_phases_28_automatic_transaction_categorization_28_context_categorizationrule, planning_phases_28_automatic_transaction_categorization_28_context_historical_match, planning_phases_28_automatic_transaction_categorization_28_context_silent_assign_vs_suggest, planning_phases_28_automatic_transaction_categorization_28_context_going_forward_only [EXTRACTED 1.00]

## Communities (261 total, 130 thin omitted)

### Community 0 - "User & 2FA Model"
Cohesion: 0.02
Nodes (21): User, AccountPolicy, CreditCardPolicy, LoanPolicy, TransactionCategoryPolicy, TransactionTypePolicy, UserPolicy, AuthServiceProvider (+13 more)

### Community 1 - "Credit Card Enums"
Cohesion: 0.04
Nodes (38): CreditCardPaymentStatus, CreditCardStatus, CreditCardType, CreditCardKpiService, Carbon\Carbon, Filament\Auth\Pages\Login, Filament\Facades\Filament, Illuminate\Foundation\Testing\RefreshDatabase (+30 more)

### Community 2 - "Accounts Filament Table"
Cohesion: 0.03
Nodes (42): AccountsTable, {closure#3}(), {closure#5}(), AuditLogsTable, BackupsTable, ExpensesRelationManager, CreditCardsTable, {closure#1}() (+34 more)

### Community 3 - "CreditCard Model"
Cohesion: 0.03
Nodes (15): CreditCard, CreditCardBalanceService, {closure#1}(), {closure#2}(), {closure#3}(), Illuminate\Validation\ValidationException, CreditCardGraphQLOwnershipTest, CreditCardGraphQLSchemaTest (+7 more)

### Community 4 - "Vue App Shell & Chat"
Cohesion: 0.04
Nodes (65): AppLayout.vue (single mount point), ChatWidget.vue (Ask Fluxa floating widget), pinia, vue, vue-router, apolloClient, authLink, clearApolloCache() (+57 more)

### Community 5 - "Transactions & Categories"
Cohesion: 0.04
Nodes (14): TransactionCategory, TransactionType, {closure#1}(), Illuminate\Testing\TestResponse, PhpOffice\PhpSpreadsheet\IOFactory, BudgetApiTest, DashboardApiTest, ExportFormulaInjectionTest (+6 more)

### Community 6 - "Filament Forms"
Cohesion: 0.04
Nodes (36): {closure#1}(), BackupForm, {closure#1}(), {closure#2}(), CreditCardForm, {closure#1}(), {closure#2}(), NotificationForm (+28 more)

### Community 7 - "CreditCard Controller"
Cohesion: 0.04
Nodes (28): Action, {closure#4}(), CreditCardController, {closure#3}(), LoanController, AccountResource, AccountVaultResource, CreditCardExpenseResource (+20 more)

### Community 8 - "Account Model"
Cohesion: 0.04
Nodes (11): Account, Builder, SavingGoalFactory, AccountApiTest, ChatbotApiTest, OwnershipValidationTest, TransferApiTest, ConsoleScopingTest (+3 more)

### Community 9 - "Accounts Resource"
Cohesion: 0.07
Nodes (22): AccountsResource, AccountsForm, AuditLogResource, CreditCardResource, CreateCreditCard, EditCreditCard, NotificationResource, PermissionResource (+14 more)

### Community 10 - "Auth Controller & Tokens"
Cohesion: 0.04
Nodes (18): AuthController, {closure#1}(), {closure#2}(), {closure#3}(), SchedulerHealthController, LimitGraphQLRequest, ForgotPasswordRequest, LoginRequest (+10 more)

### Community 11 - "User Settings Controller"
Cohesion: 0.04
Nodes (18): UserSettingsController, RefreshRequest, StoreAccountRequest, StoreCreditCardCycleRequest, StoreCreditCardRequest, StoreSavingGoalRequest, UpdateAccountRequest, UpdateCreditCardCycleRequest (+10 more)

### Community 12 - "ListAccounts & Cycles"
Cohesion: 0.04
Nodes (41): {closure#1}(), {closure#2}(), {closure#3}(), {closure#10}(), {closure#14}(), {closure#2}(), {closure#3}(), {closure#4}() (+33 more)

### Community 13 - "Transaction Model"
Cohesion: 0.05
Nodes (15): {closure#1}(), Transaction, TransactionPolicy, AccountBalanceService, {closure#1}(), {closure#1}(), {closure#2}(), Illuminate\Auth\Access\Response (+7 more)

### Community 14 - "Credit Card Detail View"
Cohesion: 0.05
Nodes (56): addToast(), removeToast(), allowsToast(), { addToast }, auth, authHeaders(), cancelConfirmInterest(), card (+48 more)

### Community 15 - "Dashboard View"
Cohesion: 0.03
Nodes (53): accountCount, accounts, accountsLoading, { addToast }, auth, budgetAlerts, budgetAlertsLoading, budgetMonthLabel (+45 more)

### Community 16 - "Permission Migrations"
Cohesion: 0.05
Nodes (37): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#1}(), {closure#3}(), {closure#5}() (+29 more)

### Community 17 - "Loan Payments Job"
Cohesion: 0.05
Nodes (19): GenerateLoanPaymentsJob, {closure#1}(), {closure#2}(), LoanPayment, LoanPaymentObserver, LoanPaymentPolicy, LoanPaymentPostingService, {closure#1}() (+11 more)

### Community 18 - "Loan Model & Repository"
Cohesion: 0.05
Nodes (12): Loan, LoanRepository, LoanApiTest, LoanDefaultRemainingAmountTest, LoanWriteAtomicityTest, {closure#2}(), {closure#4}(), {closure#6}() (+4 more)

### Community 19 - "Finance Report View"
Cohesion: 0.05
Nodes (51): { addToast }, auth, authHeaders(), budgetAlerts, budgetCategories, budgetInputs, budgetLoading, budgetMonthLabel (+43 more)

### Community 20 - "Account Controllers"
Cohesion: 0.07
Nodes (14): AccountController, CreditCardPaymentController, SavingGoalController, SubscriptionController, SubscriptionFrequencyController, {closure#3}(), TransactionController, Controller (+6 more)

### Community 21 - "Shared Vue Components"
Cohesion: 0.04
Nodes (41): graphql-tag, @heroicons/vue, sizeMap, accountTypeIcons, account, ACCOUNT_DETAIL_QUERY, { formatCurrency, colorClass, formatSigned }, { result, loading, error } (+33 more)

### Community 22 - "Data Integrity Audit"
Cohesion: 0.05
Nodes (14): AuditDataIntegrity, SmokeTest, DataIntegrityAuditor, Illuminate\Console\Command, Illuminate\Foundation\Inspiring, Illuminate\Support\Facades\Artisan, Illuminate\Support\Facades\Cache, Illuminate\Support\Facades\Schedule (+6 more)

### Community 23 - "Sensitive Vault Panel"
Cohesion: 0.05
Nodes (45): { addToast }, checkingPin, editForm, editing, enteredPin, handleReveal(), handleSetPin(), hasVaultPin (+37 more)

### Community 24 - "Phase 19 Credit Card Cycles"
Cohesion: 0.06
Nodes (51): Phase 19 Discussion Log, CreditCardCycleService (Phase 19 fixes), Statement-day-derived billing-cycle period start, direct_monthly interest mode (12x overstatement), Phase 19 Research, RevolvingCreditCalculator, Per-card stamp-duty inclusion setting, Phase 19 Deferred Items (+43 more)

### Community 25 - "Category Creation Modal"
Cohesion: 0.04
Nodes (46): @vue/apollo-composable, { addToast }, CREATE_CATEGORY, emit, error, handleSubmit(), { mutate: createCategory, loading: saving }, name (+38 more)

### Community 26 - "Filament Dashboard"
Cohesion: 0.05
Nodes (33): Dashboard, {closure#1}(), {closure#2}(), {closure#3}(), self, AdminPanelProvider, Filament\Auth\MultiFactor\App\AppAuthentication, Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication (+25 more)

### Community 27 - "Filament List Pages"
Cohesion: 0.06
Nodes (16): ListAuditLogs, ListBackups, ListCreditCards, ListNotifications, ListPermissions, ListRoles, ListSavingGoals, ListSubscriptionFrequencies (+8 more)

### Community 28 - "Create Accounts Page"
Cohesion: 0.05
Nodes (16): CreateAccounts, ListAccounts, BackupResource, CreateBackup, CreateNotification, CreatePermission, CreateRole, CreateSavingGoal (+8 more)

### Community 29 - "Edit Accounts Page"
Cohesion: 0.07
Nodes (15): EditAccounts, EditBackup, EditNotification, EditPermission, EditRole, EditSavingGoal, EditSubscriptionFrequency, EditSubscription (+7 more)

### Community 30 - "Community 30"
Cohesion: 0.06
Nodes (30): { toasts, removeToast }, useCurrency(), formatCurrency(), formatSigned(), toasts, useToast(), defaults, useUserPreferences() (+22 more)

### Community 31 - "Community 31"
Cohesion: 0.05
Nodes (32): { formatCurrency }, hasAlerts, props, { t }, { translateCategoryName, translateOptionalCategory }, budgetAlertStatusIcons, CATEGORY_FALLBACK_ICON, categoryIcons (+24 more)

### Community 32 - "Community 32"
Cohesion: 0.08
Nodes (9): CardBrand, CreditCardCycleStatus, InterestCalculationMethod, LoanPaymentStatus, SavingGoalStatus, UITheme, VaultCardType, Filament\Support\Contracts\HasColor (+1 more)

### Community 33 - "Community 33"
Cohesion: 0.08
Nodes (8): AccountVaultController, VaultController, VaultPinController, EnsureVaultUnlocked, SetVaultPinRequest, UpdateAccountVaultRequest, VaultUnlockRequest, VaultService

### Community 34 - "Community 34"
Cohesion: 0.06
Nodes (12): {closure#1}(), {closure#3}(), {closure#4}(), {closure#6}(), {closure#1}(), {closure#3}(), {closure#1}(), down() (+4 more)

### Community 35 - "Community 35"
Cohesion: 0.06
Nodes (39): useLocalizedLabels(), translateAccountType(), translateCategoryName(), translateCategoryPath(), translateOptionalCategory(), translateTransactionType(), accountTypeTranslationKeys, categoryTranslationKeys (+31 more)

### Community 36 - "Community 36"
Cohesion: 0.05
Nodes (34): categoryIcon(), selectedCategoryIcon, accountNameById, accountOptions, accounts, auth, categories, CATEGORIES_QUERY (+26 more)

### Community 37 - "Community 37"
Cohesion: 0.05
Nodes (11): {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}() (+3 more)

### Community 38 - "Community 38"
Cohesion: 0.06
Nodes (11): StoreLoanRequest, StoreSubscriptionRequest, StoreTransactionRequest, UpdateTransactionRequest, StoreTransactionRequest, OwnedAccount, OwnedByAuthenticatedUser, self (+3 more)

### Community 39 - "Community 39"
Cohesion: 0.06
Nodes (7): TwoFactorAuthController, ConfirmTwoFactorRequest, DisableTwoFactorRequest, EnableTwoFactorRequest, RegenerateRecoveryCodesRequest, TwoFactorAuthService, TwoFactorHardeningTest

### Community 40 - "Community 40"
Cohesion: 0.06
Nodes (39): DEPLOY.md self-hosted homelab deploy, Laravel scheduler via per-minute cron, Scribe config crash incident (composer --no-dev), Self-hosted GitHub Actions runner deploy pipeline, Fluxa UAT environment (Hyper-V VM), Fluxa API Documentation, Cursor pagination with per_page cap, Read/write API rate limits (+31 more)

### Community 41 - "Community 41"
Cohesion: 0.07
Nodes (30): ref_node_assert, vue-i18n, createAppI18n(), defaultLocale, messages, resolveAppLocale(), { addToast }, auth (+22 more)

### Community 42 - "Community 42"
Cohesion: 0.09
Nodes (10): SubscriptionStatus, CategoryBudget, {closure#1}(), self, Auditable, HasUserScoping, Illuminate\Database\Eloquent\Factories\HasFactory, Illuminate\Database\Eloquent\SoftDeletes (+2 more)

### Community 43 - "Community 43"
Cohesion: 0.08
Nodes (4): Builder, Subscription, SubscriptionPolicy, SubscriptionServiceTest

### Community 44 - "Community 44"
Cohesion: 0.07
Nodes (32): accountOptions, accounts, { addToast }, amountLabel, auth, CATEGORIES_QUERY, categoryOptions, categoryParentOptions (+24 more)

### Community 45 - "Community 45"
Cohesion: 0.07
Nodes (6): {closure#1}(), {closure#2}(), {closure#3}(), FinanceReport, Collection, Filament\Pages\Page

### Community 46 - "Community 46"
Cohesion: 0.07
Nodes (32): DashboardController::upcomingPayments (thinned), UpcomingPaymentsService::forUser, ChatIntent interface, IntentRouter (stateless allow-list dispatcher), UnsupportedIntentException, AccountBalancesIntent, FinanceReportService::getTable, MonthlySpendingIntent (+24 more)

### Community 47 - "Community 47"
Cohesion: 0.09
Nodes (7): {closure#11}(), {closure#13}(), {closure#15}(), Backup, Notification, ScopingSecurityTest, SettingsModuleTest

### Community 48 - "Community 48"
Cohesion: 0.09
Nodes (12): ChatbotController, AskChatbotRequest, {closure#1}(), IntentRouter, Illuminate\Support\Facades\Route, {closure#1}(), {closure#2}(), {closure#3}() (+4 more)

### Community 49 - "Community 49"
Cohesion: 0.06
Nodes (5): Builder, Builder, Builder, Illuminate\Database\Eloquent\Relations\BelongsTo, Illuminate\Database\Eloquent\Relations\HasOne

### Community 50 - "Community 50"
Cohesion: 0.08
Nodes (11): {closure#2}(), {closure#3}(), PermissionService, DatabaseSeeder, RolesAndPermissionsSeeder, SuperAdminSeeder, TransactionTypeSeeder, Illuminate\Database\Console\Seeds\WithoutModelEvents (+3 more)

### Community 51 - "Community 51"
Cohesion: 0.07
Nodes (25): private, $schema, scripts, build, dev, type, @apollo/client, autoprefixer (+17 more)

### Community 52 - "Community 52"
Cohesion: 0.09
Nodes (3): CreditCardObserver, CreditCardCycleService, CreditCardCycleServiceTest

### Community 53 - "Community 53"
Cohesion: 0.08
Nodes (26): accountOptions, accounts, { addToast }, auth, calcMonthlyPayment(), calcOutstandingPrincipal(), CREATE_LOAN, DELETE_LOAN (+18 more)

### Community 54 - "Community 54"
Cohesion: 0.10
Nodes (12): {closure#10}(), {closure#11}(), {closure#13}(), {closure#14}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}() (+4 more)

### Community 55 - "Community 55"
Cohesion: 0.11
Nodes (4): CreditCardCycle, CreditCardCyclePolicy, CreditCardExpenseFactory, RevolvingCreditCalculatorTest

### Community 56 - "Community 56"
Cohesion: 0.08
Nodes (25): accountOptions, accounts, { addToast }, auth, brandOptions, CARD_QUERY, CREATE_CARD, deleting (+17 more)

### Community 57 - "Community 57"
Cohesion: 0.10
Nodes (26): spatie/laravel-query-builder package, Plan 06-03 Accounts and Transactions REST Controllers, AccountController (REST), AccountResource, Explicit where user_id scoping pattern, TransactionController (REST), Transaction dateFrom/dateTo scopes, TransactionResource (+18 more)

### Community 58 - "Community 58"
Cohesion: 0.10
Nodes (5): LoanResource, CreateLoan, EditLoan, ListLoans, LoanForm

### Community 59 - "Community 59"
Cohesion: 0.13
Nodes (20): UnsupportedIntentException, {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}() (+12 more)

### Community 61 - "Community 61"
Cohesion: 0.08
Nodes (24): dependencies, @apollo/client, chart.js, graphql, graphql-tag, @heroicons/vue, pinia, simple-icons (+16 more)

### Community 62 - "Community 62"
Cohesion: 0.17
Nodes (3): VaultCard, VaultCardPolicy, VaultCardApiTest

### Community 63 - "Community 63"
Cohesion: 0.14
Nodes (23): Requirements: Fluxa Planning Realignment, ALIGN-01 PROJECT.md accurate, ALIGN-02 REQUIREMENTS.md without stale scope, ALIGN-03 ROADMAP.md grounded in reality, ALIGN-04 STATE.md handoff, ALIGN-05 Validated capabilities from codebase map, Fluxa ROADMAP.md, credit-cards:balance-audit command (+15 more)

### Community 64 - "Community 64"
Cohesion: 0.09
Nodes (18): colorMap, props, activeSubscriptions, annualTotal, auth, filterStatus, { formatCurrency }, isRenewingSoon() (+10 more)

### Community 65 - "Community 65"
Cohesion: 0.13
Nodes (5): MonthlyCashflow, TotalByCategory, TransactionCategories, GraphQL\Type\Definition\ResolveInfo, Nuwave\Lighthouse\Support\Contracts\GraphQLContext

### Community 67 - "Community 67"
Cohesion: 0.15
Nodes (5): CreditCardExpense, CreditCardExpenseObserver, CreditCardExpenseIntegrationTest, CreditCardLifecycleIntegrationTest, {closure#1}()

### Community 68 - "Community 68"
Cohesion: 0.13
Nodes (5): CreditCardPayment, {closure#1}(), CreditCardCycleObserver, CreditCardPaymentObserver, CreditCardPaymentPolicy

### Community 69 - "Community 69"
Cohesion: 0.11
Nodes (5): {closure#1}(), {closure#2}(), {closure#2}(), UserSetting, AdminPanelScopingTest

### Community 70 - "Community 70"
Cohesion: 0.15
Nodes (3): SubscriptionFrequency, SubscriptionApiTest, SubscriptionOwnershipTest

### Community 71 - "Community 71"
Cohesion: 0.17
Nodes (6): {closure#1}(), {closure#2}(), SubscriptionService, Carbon\CarbonInterface, Illuminate\Database\Eloquent\Collection, Illuminate\Database\UniqueConstraintViolationException

### Community 72 - "Community 72"
Cohesion: 0.15
Nodes (20): Codebase Concerns (CONCERNS.md), No backup/recovery mechanism, CreditCardBalanceService, CreditCardCycleService, CreditCardExpenseService, Credit card cycle status race condition, Dashboard net-worth chart query volume, Observer chain dependency and static state (+12 more)

### Community 73 - "Community 73"
Cohesion: 0.11
Nodes (17): adminLinkLabel, auth, closeMobileMenu(), closeUserMenu(), handleLogout(), isUserMenuRoute, mobileMenuOpen, navLinks (+9 more)

### Community 74 - "Community 74"
Cohesion: 0.12
Nodes (19): accountOptions, accounts, { addToast }, auth, authHeaders(), deleting, errors, fetchAccounts() (+11 more)

### Community 76 - "Community 76"
Cohesion: 0.13
Nodes (4): VaultCardController, StoreVaultCardRequest, UpdateVaultCardRequest, VaultCardResource

### Community 77 - "Community 77"
Cohesion: 0.26
Nodes (6): AuditTrail, {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), Illuminate\Database\Eloquent\Model

### Community 78 - "Community 78"
Cohesion: 0.16
Nodes (6): ResolvesUserCurrency, ChatIntent, {closure#1}(), AccountBalancesIntent, {closure#2}(), MonthlySpendingIntent

### Community 79 - "Community 79"
Cohesion: 0.19
Nodes (4): {closure#1}(), {closure#2}(), {closure#4}(), CreditCardExpenseService

### Community 80 - "Community 80"
Cohesion: 0.14
Nodes (18): Phase 13 Current State Audit, Phase 13 Validated Capabilities Ledger, Plan 10-01: Shared Locale Contract, Plan 10-02: Backend Locale Middleware, Plan 10-03: SPA i18n Foundation, Plan 10-04: Backend Profile Locale Settings, Plan 10-05: Manual Verification, Plan 15-01: Roadmap Reset and Concern Triage (+10 more)

### Community 81 - "Community 81"
Cohesion: 0.13
Nodes (16): { addToast }, data, editing, emptyForm(), fetchData(), form, handleUnlock(), loadingData (+8 more)

### Community 82 - "Community 82"
Cohesion: 0.15
Nodes (4): {closure#2}(), CreditCardExpenseController, StoreCreditCardExpenseRequest, UpdateCreditCardExpenseRequest

### Community 83 - "Community 83"
Cohesion: 0.15
Nodes (6): ApiRateLimitMiddleware, CheckModuleEnabled, EnforceTokenType, EnsureUserIsActive, Closure, Illuminate\Cache\RateLimiter

### Community 84 - "Community 84"
Cohesion: 0.18
Nodes (15): CreditCardCycleService::handleDeletedPayment, CreditCardCycleService::syncCardBalance, CreditCardCycleService::syncCycleAndCardFromPayment, CreditCardExpenseService::validateExpenseChange, RevolvingCreditCalculator::calculateDailyBalances, Plan 18-04: Credit-card cycle/payment race fix, Authoritative recompute instead of delta application, Non-idempotent delta race on payment-status sync (+7 more)

### Community 85 - "Community 85"
Cohesion: 0.16
Nodes (17): API rate limiting gap, Plan 06-01 API Foundation, Lighthouse guards config set to sanctum, Named rate limiters api-read (100/min) and api-write (20/min), Sanctum guard and 30-min expiry config, knuckleswtf/scribe package, Summary 06-01 API Foundation, Plan 06-02 Auth Endpoints and Error Handling (+9 more)

### Community 86 - "Community 86"
Cohesion: 0.20
Nodes (3): BudgetController, BudgetService, Carbon\CarbonImmutable

### Community 87 - "Community 87"
Cohesion: 0.16
Nodes (4): CreditCardSensitiveVaultController, RevealSensitiveVaultRequest, UpdateSensitiveVaultRequest, CreditCardSensitiveVaultResource

### Community 88 - "Community 88"
Cohesion: 0.16
Nodes (4): VaultCardSensitiveController, RevealVaultCardSensitiveRequest, UpdateVaultCardSensitiveRequest, VaultCardSensitiveResource

### Community 90 - "Community 90"
Cohesion: 0.18
Nodes (7): FinanceReportSectionSheet, Maatwebsite\Excel\Concerns\FromArray, Maatwebsite\Excel\Concerns\WithCustomValueBinder, Maatwebsite\Excel\Concerns\WithTitle, PhpOffice\PhpSpreadsheet\Cell\Cell, PhpOffice\PhpSpreadsheet\Cell\DataType, PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder

### Community 91 - "Community 91"
Cohesion: 0.13
Nodes (15): require, barryvdh/laravel-dompdf, blade-ui-kit/blade-heroicons, filament/filament, laravel/framework, laravel/sanctum, laravel/tinker, maatwebsite/excel (+7 more)

### Community 93 - "Community 93"
Cohesion: 0.15
Nodes (14): Apollo Client (authLink, errorLink, httpLink), Chart.js dashboard charts, Phase 7 Context: Mobile-Friendly Frontend SPA, Phase 7-01 Summary: Infrastructure + Auth, Phase 7-02 Summary: Dashboard + Accounts, Phase 7-03 Summary: Transactions, Phase 7-04 Summary: Loans + Credit Cards, Phase 7-05 Summary: Subscriptions + Final Polish (+6 more)

### Community 94 - "Community 94"
Cohesion: 0.26
Nodes (3): FinanceReportController, FinanceReportExportService, Symfony\Component\HttpFoundation\Response

### Community 95 - "Community 95"
Cohesion: 0.12
Nodes (5): {closure#1}(), {closure#2}(), {closure#1}(), {closure#1}(), {closure#2}()

### Community 98 - "Community 98"
Cohesion: 0.19
Nodes (3): CreditCardVaultController, UpdateCreditCardVaultRequest, CreditCardVaultResource

### Community 99 - "Community 99"
Cohesion: 0.17
Nodes (13): CreditCardCycleService::calculateRevolvingPaymentBreakdown (legacy), CreditCardCycleService::ensureCurrentMonthCycle, RevolvingCreditCalculator::calculateInterestDirectMonthly, D-02 proof-then-fix severity policy, Plan 19-02: Cycle period anchoring and legacy rate fix, First-cycle period anchor (interest forced to 0), Plan 19-04: Direct monthly formula fix, Flat twelfth monthly rate (annual/12) (+5 more)

### Community 100 - "Community 100"
Cohesion: 0.20
Nodes (3): CreateTransactionCategory, EditTransactionCategory, TransactionCategoryResource

### Community 101 - "Community 101"
Cohesion: 0.17
Nodes (11): autoload-dev, psr-4, description, keywords, license, minimum-stability, name, prefer-stable (+3 more)

### Community 105 - "Community 105"
Cohesion: 0.24
Nodes (3): {closure#1}(), {closure#2}(), CreditCardPaymentPostingService

### Community 106 - "Community 106"
Cohesion: 0.22
Nodes (5): AccountFactory, static, CategoryBudgetFactory, SubscriptionFactory, Illuminate\Database\Eloquent\Factories\Factory

### Community 107 - "Community 107"
Cohesion: 0.24
Nodes (11): Console command ambient-authentication narrowing, HasUserScoping trait, SubscriptionService auto-posting, Broad withoutGlobalScopes usage, MonthlyCashflow GraphQL resolver, graphql/schema.graphql finance schema, scopeBelongsToAuthUser model scope, TotalByCategory GraphQL resolver (+3 more)

### Community 108 - "Community 108"
Cohesion: 0.22
Nodes (5): FinanceReportWorkbookExport, Barryvdh\DomPDF\Facade\Pdf, Maatwebsite\Excel\Concerns\WithMultipleSheets, Maatwebsite\Excel\Excel, Maatwebsite\Excel\Facades\Excel

### Community 111 - "Community 111"
Cohesion: 0.27
Nodes (3): TransferController, StoreTransferRequest, AccountTransferService

### Community 112 - "Community 112"
Cohesion: 0.20
Nodes (9): CreditCardCycleService::refreshCycleStatuses, Illuminate\Support\Facades\Log, Plan 18-06: Deferred register and CONCERNS.md re-grounding, Summary 18-06, CONCERNS.md re-grounding (2026-08-06), Phase 18 Deferred Items, Ambient-authentication narrowing of scheduled commands (fixed in-phase), FinanceReportPageTest pre-existing failure (missing Carbon::setTestNow) (+1 more)

### Community 113 - "Community 113"
Cohesion: 0.24
Nodes (8): {closure#1}(), Phase 18 Context, D-04 non-HTTP auth-context scoping proof, D-05 withoutGlobalScopes() proof strategy, Phase 18 Discussion Log, Phase 18 Research, TransactionCategories GraphQL cross-user leak, Phase 18 Validation Strategy

### Community 114 - "Community 114"
Cohesion: 0.20
Nodes (10): require-dev, fakerphp/faker, knuckleswtf/scribe, laravel/breeze, laravel/pail, laravel/pint, laravel/sail, mockery/mockery (+2 more)

### Community 116 - "Community 116"
Cohesion: 0.29
Nodes (10): Mobile APIs & Health Integration Features Research, v3.0 Implementation Roadmap, JWT foundation and auth flow (tymon/jwt-auth), v3.0 Stack Research Index, Stack Research v3.0 Mobile APIs & Health, Lighthouse GraphQL (keep and extend), Stack Research Summary (txt), JWT over Sanctum for mobile decision (+2 more)

### Community 118 - "Community 118"
Cohesion: 0.25
Nodes (8): {closure#1}(), {closure#2}(), RevolvingCreditCalculator::calculatePaymentBreakdown, Plan 19-01: Stamp duty inclusion flag, Summary 19-01, Plan 19-03: Payment-aware daily balance and stamp duty breakdown, Summary 19-03, D-03 per-card fixed_payment_includes_stamp_duty flag

### Community 120 - "Community 120"
Cohesion: 0.39
Nodes (6): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}()

### Community 124 - "Community 124"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 127 - "Community 127"
Cohesion: 0.25
Nodes (3): {closure#4}(), {closure#5}(), Illuminate\Database\Query\Builder

### Community 129 - "Community 129"
Cohesion: 0.25
Nodes (3): Illuminate\Console\Scheduling\Schedule, SanctumPruneScheduleTest, SchedulerOverlapTest

### Community 130 - "Community 130"
Cohesion: 0.32
Nodes (8): Fluxa PROJECT.md, Core Value: one shared source of truth across surfaces, Phase 13 validated capabilities ledger, Structural-only lower-confidence context, Superseded localization planning, Milestone v5.1 Planning Realignment, Validated scope (auth, accounts, dashboard/reports, admin), Roadmap confidence boundary (validated vs structural-only)

### Community 135 - "Community 135"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 138 - "Community 138"
Cohesion: 0.29
Nodes (4): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}()

### Community 139 - "Community 139"
Cohesion: 0.29
Nodes (4): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}()

### Community 140 - "Community 140"
Cohesion: 0.33
Nodes (7): Deploy to UAT workflow, Backup database step (mysqldump), Deploy to /var/www/fluxa step, lighthouse:clear-cache on deploy, Remove backup on success step, Smoke test step (php artisan app:smoke), php artisan app:smoke command

### Community 141 - "Community 141"
Cohesion: 0.29
Nodes (7): Plan 06-04 Loans, Credit Cards, Subscriptions REST, CreditCardController (REST), CreditCardResource with cycles, SubscriptionController (REST), Summary 06-04 Loans, Credit Cards, Subscriptions, Plan 06-05 GraphQL Schema, Summary 06-05 GraphQL Schema

### Community 146 - "Community 146"
Cohesion: 0.33
Nodes (5): configurestrategy, Knuckles\Scribe\Config\AuthIn, Knuckles\Scribe\Config\Defaults, Knuckles\Scribe\Extracting\Strategies, removestrategies

### Community 148 - "Community 148"
Cohesion: 0.33
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 149 - "Community 149"
Cohesion: 0.33
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 150 - "Community 150"
Cohesion: 0.33
Nodes (5): compilerOptions, baseUrl, paths, exclude, ziggy-js

### Community 151 - "Community 151"
Cohesion: 0.40
Nodes (6): Gated withoutGlobalScopes bypass idiom, TotalByCategory GraphQL resolver, TransactionCategories GraphQL resolver (owner-scoped fix), AdminPanelScopingTest (Filament binding), CONCERNS.md re-grounded, 14 withoutGlobalScopes() call sites

### Community 152 - "Community 152"
Cohesion: 0.47
Nodes (6): Currency decision needed before Phases 24-27, IntentRouter (chatbot allow-list router), Phase 17 Read-only finance chatbot engine, Phase 24 Cash-flow forecast (safe to spend), Phase 26 Debt and subscription totals, UpcomingPaymentsService

### Community 153 - "Community 153"
Cohesion: 0.47
Nodes (3): ReflectionClass, CreateUserRedirectTest, redirectUrlFor()

### Community 157 - "Community 157"
Cohesion: 0.40
Nodes (5): Credit card API proof tests, Phase 16 Plan 01 Summary: Credit Card Security Proof, Phase 16 Plan 02 Summary: Credit Card Lifecycle Proof, Phase 16 Validation Strategy, Proof-first validation

### Community 158 - "Community 158"
Cohesion: 0.40
Nodes (4): clearBudget({{ $category[, closeDetail, saveBudget({{ $category[, toggleExpand(

### Community 159 - "Community 159"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 160 - "Community 160"
Cohesion: 0.40
Nodes (4): Monolog\Handler\NullHandler, Monolog\Handler\StreamHandler, Monolog\Handler\SyslogUdpHandler, Monolog\Processor\PsrLogMessageProcessor

### Community 189 - "Community 189"
Cohesion: 0.67
Nodes (4): Read-only finance chatbot engine, DashboardController::upcomingPayments, Plan 17-01: Extract UpcomingPaymentsService, UpcomingPaymentsService

### Community 205 - "Community 205"
Cohesion: 1.00
Nodes (3): AGENTS.md project instructions, CLAUDE.md project instructions, Graphify knowledge graph and Obsidian KB

### Community 207 - "Community 207"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 208 - "Community 208"
Cohesion: 0.67
Nodes (3): CreditCardCycleService::syncCardBalance (idempotent recompute), Credit-card payment sync balance race condition, CreditCardExpenseService fail-closed validation

### Community 209 - "Community 209"
Cohesion: 0.67
Nodes (3): Missing PHPUnit Test attribute import silently drops tests, Plan 19-05: End-to-end regression and closeout, Summary 19-05

## Knowledge Gaps
- **698 isolated node(s):** `$schema`, `name`, `type`, `description`, `keywords` (+693 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 1583 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **130 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `User & 2FA Model` to `Credit Card Enums`, `CreditCard Model`, `Community 131`, `Transactions & Categories`, `Filament Forms`, `CreditCard Controller`, `Account Model`, `Accounts Resource`, `Auth Controller & Tokens`, `Community 137`, `Community 132`, `Transaction Model`, `Community 142`, `Community 143`, `Community 144`, `Loan Payments Job`, `Loan Model & Repository`, `Data Integrity Audit`, `Community 153`, `Filament Dashboard`, `Community 154`, `Community 155`, `Community 33`, `Community 39`, `Community 42`, `Community 43`, `Community 47`, `Community 48`, `Community 50`, `Community 55`, `Community 186`, `Community 187`, `Community 60`, `Community 62`, `Community 66`, `Community 68`, `Community 69`, `Community 70`, `Community 78`, `Community 89`, `Community 92`, `Community 96`, `Community 97`, `Community 102`, `Community 103`, `Community 104`, `Community 106`, `Community 110`, `Community 117`, `Community 126`?**
  _High betweenness centrality (0.111) - this node is a cross-community bridge._
- **Why does `Account` connect `Account Model` to `User & 2FA Model`, `Credit Card Enums`, `CreditCard Model`, `Community 131`, `Transactions & Categories`, `Filament Forms`, `CreditCard Controller`, `Community 132`, `Accounts Resource`, `User Settings Controller`, `ListAccounts & Cycles`, `Transaction Model`, `Community 143`, `Community 144`, `Community 145`, `Loan Model & Repository`, `Account Controllers`, `Community 154`, `Create Accounts Page`, `Community 33`, `Community 42`, `Community 43`, `Community 47`, `Community 52`, `Community 54`, `Community 186`, `Community 187`, `Community 60`, `Community 188`, `Community 62`, `Community 66`, `Community 67`, `Community 69`, `Community 70`, `Community 77`, `Community 78`, `Community 96`, `Community 97`, `Community 102`, `Community 104`, `Community 106`, `Community 111`, `Community 117`, `Community 126`?**
  _High betweenness centrality (0.061) - this node is a cross-community bridge._
- **Why does `CreditCard` connect `CreditCard Model` to `User & 2FA Model`, `Community 128`, `Credit Card Enums`, `Community 132`, `Transactions & Categories`, `CreditCard Controller`, `Account Model`, `Accounts Resource`, `User Settings Controller`, `Transaction Model`, `Community 143`, `Community 144`, `Community 145`, `Loan Model & Repository`, `Account Controllers`, `Data Integrity Audit`, `Community 33`, `Community 42`, `Community 43`, `Community 47`, `Community 49`, `Community 52`, `Community 55`, `Community 186`, `Community 60`, `Community 188`, `Community 66`, `Community 67`, `Community 70`, `Community 75`, `Community 77`, `Community 79`, `Community 82`, `Community 87`, `Community 96`, `Community 97`, `Community 98`, `Community 102`, `Community 104`, `Community 109`, `Community 117`, `Community 118`, `Community 120`, `Community 123`, `Community 125`?**
  _High betweenness centrality (0.051) - this node is a cross-community bridge._
- **What connects `$schema`, `name`, `type` to the rest of the system?**
  _698 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `User & 2FA Model` be split into smaller, more focused modules?**
  _Cohesion score 0.021622026036211283 - nodes in this community are weakly interconnected._
- **Should `Credit Card Enums` be split into smaller, more focused modules?**
  _Cohesion score 0.0410171365395246 - nodes in this community are weakly interconnected._
- **Should `Accounts Filament Table` be split into smaller, more focused modules?**
  _Cohesion score 0.03492414664981037 - nodes in this community are weakly interconnected._