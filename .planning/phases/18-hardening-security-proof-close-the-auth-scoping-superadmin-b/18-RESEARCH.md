# Phase 18: Hardening & Security Proof - Research

**Researched:** 2026-08-06
**Domain:** Laravel 12 auth-scoping proof, non-HTTP job context testing, credit-card lifecycle race-condition testing
**Confidence:** HIGH (all major claims verified against current code, not training knowledge)

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Proof breadth**
- **D-01:** Cover all three risk areas (scoping/superadmin bypass, cross-user permission tests, credit-card lifecycle race conditions) within this single phase rather than splitting into a smaller first slice — they are distinct, bounded areas that together form one coherent hardening pass, not an open-ended sweep.

**Proof-then-fix policy**
- **D-02:** When a test confirms a real bug, fix severity determines action:
  - **High severity** (cross-user data disclosure, corrupted/drifted balances, broken money math) → fix in this same phase, with a regression test proving the fix.
  - **Lower severity** (cosmetic, narrow edge case, no data-integrity or disclosure impact) → document the gap (mirroring the `deferred-items.md` pattern already used in Phase 17) rather than fixing here, to keep scope from ballooning.
- **D-03:** This is NOT a proof-only phase — unlike Phase 16's discovery-first stance, Phase 18 explicitly allows and expects fixes for the high-severity class defined in D-02.

**Non-HTTP auth context**
- **D-04:** Explicitly test `HasUserScoping`'s `auth()->check()` behavior in non-HTTP contexts — specifically the scheduled jobs already shipped (credit-card cycle issuing, subscription renewal posting, loan payment posting reminders). If scoping silently no-ops or misbehaves outside an HTTP request lifecycle, that is exactly the kind of proof gap this phase exists to close.

**`withoutGlobalScopes()` proof strategy**
- **D-05:** Use both a generic/pattern-based regression test (asserting no authenticated route leaks cross-user data) AND targeted tests on the specific high-risk call sites already identified in this discussion:
  - `app/Http/Controllers/Api/V1/DashboardController.php:94`
  - `app/GraphQL/Queries/TotalByCategory.php:19`
  - `app/GraphQL/Queries/TransactionCategories.php:13,15`
  - `app/Services/FinanceReportService.php:236`
  - `app/Models/Transaction.php:72`
  - The 7 Filament Resource classes using `withoutGlobalScopes()` (Roles, Permissions, AuditLogs, UserSettings, Accounts, Backups, Notifications) — these are already gated by the admin panel's own access control, but should still get an explicit cross-user assertion, not just an assumption that panel-level auth is sufficient.

  > **Research correction:** This research re-verified the count and gating of these 7 Filament sites. All 7 bypass only `SoftDeletingScope` (not `user` scope) in `getRecordRouteBindingEloquentQuery()`; the actual `user` scoping is separately enforced in each resource's `getEloquentQuery()` via `where('user_id', auth()->id())` for non-superadmins. See Pitfall 2 below — this lowers, but does not eliminate, the need for the explicit cross-user assertion D-05 already calls for.

### Claude's Discretion
- Exact test file organization and naming (e.g., one `ScopingSecurityTest.php` vs. per-domain test additions to existing Feature test files)
- Whether the credit-card cycle race-condition proof uses a true concurrency test (parallel requests) or a sequenced test that reproduces the exact interleaving described in `.planning/codebase/CONCERNS.md`
- Whether `CreditCardExpenseObserver`'s previously-flagged static-state leak (from `.planning/codebase/CONCERNS.md`, dated 2024-12-19) is still present in current code — this must be re-verified against current code during research/planning, not assumed from the (possibly stale) codebase map

  > **Research finding:** Confirmed still present (see Pitfall 4) — and a second, previously undocumented instance found in `CreditCardPaymentObserver`.
- Exact scope of "cross-user permission test expansion" beyond the specific `withoutGlobalScopes()` sites already listed above

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope. (The narrower "start with scoping only" option was considered and explicitly rejected per D-01.)
</user_constraints>

<phase_requirements>
## Phase Requirements

No formal requirement IDs are assigned to this phase in `.planning/REQUIREMENTS.md` or `ROADMAP.md` — the phase description explicitly states coverage should be derived from CONTEXT.md decisions D-01 through D-05 instead of REQ-IDs. The table below maps each decision to the research support that enables planning.

| ID | Description | Research Support |
|----|-------------|------------------|
| D-01 | Cover all 3 risk areas (scoping/bypass, non-HTTP scoping, credit-card race conditions) in one phase | Full re-verification of all 3 areas completed below — see Summary, State of the Art, Re-mapped line numbers |
| D-02 | Fix high-severity confirmed bugs with regression test; document lower-severity gaps | Two concrete high-severity candidates identified and reproduction paths documented: Pitfall 5 (CreditCardExpenseService missing-target-card validation) and Open Question 2 (TransactionObserver transfer-pair deletion not transaction-wrapped) |
| D-03 | Phase allows fixes, not proof-only | N/A — process decision, no research needed |
| D-04 | Test `HasUserScoping` in non-HTTP contexts (3 scheduled commands) | Exact command source, schedule times, and query patterns documented in Code Examples; existing `$this->artisan(...)` test idiom identified in `CreditCardLifecycleIntegrationTest.php` |
| D-05 | Generic + targeted `withoutGlobalScopes()` proof (13 call sites, re-counted from CONTEXT.md's 14) | Full re-verification of all 13 sites' gating status — see corrected count note above, Pitfall 2, Pitfall 3, and State of the Art table |
</phase_requirements>

## Summary

This phase closes three previously-identified but unproven hardening gaps. Research re-verified every specific claim from `.planning/codebase/CONCERNS.md` (dated 2024-12-19) against the current codebase and found the doc is **partially stale**: some claims are confirmed accurate, one is confirmed via a live grep contradiction from CONTEXT.md that turned out to be a false negative (grep escaping issue during discussion), and the `withoutGlobalScopes()` site count in CONTEXT.md (14) is **overcounted by one** — the actual, re-verified count is **13** call sites, and 7 of those (the Filament Resources) bypass only `SoftDeletingScope`, not the `user` scope, making them lower risk than CONTEXT.md's framing implied.

The most significant new finding from this research: `app/GraphQL/Queries/TransactionCategories.php` performs `withoutGlobalScopes()` with **zero** user/role gating (no `$context->user()` check at all), returning every user's categories to any authenticated caller. This is *already* covered by a passing test (`GraphQLApiTest::test_graphql_transaction_categories_query_returns_shared_categories`) that explicitly names and asserts this as **intended shared behavior**, not a leak. The planner must treat this as a locked existing-intentional-design fact (already proven, do not "fix"), but flag it as an Open Question for the user to explicitly confirm, since no code comment documents the intent — only the test name implies it.

The credit-card cycle status race condition described in CONCERNS.md is real code (current `CreditCardCycleService.php` lines 139-291), and the exact interleaving windows are re-located below. The `CreditCardExpenseObserver` static-state leak is **still present** (confirmed on second, corrected grep — the discussion's negative finding was a false negative from a broken grep escape), and a second, undocumented instance of the same pattern exists in `CreditCardPaymentObserver`. No Laravel Octane/Swoole is used (confirmed via `composer.json`), so the static-array leak is scoped to per-request lifetime only under PHP-FPM/CLI — it does NOT persist across HTTP requests, but it CAN misbehave within a single request if the same model ID is touched twice in one request lifecycle (e.g., bulk update loops), which is the actual risk, not cross-request leakage as CONCERNS.md implied.

**Primary recommendation:** Use Feature-level HTTP/GraphQL/Command tests (RefreshDatabase + Sanctum::actingAs, matching the project's existing `tests/Feature/Api/*Test.php` and `CreditCardLifecycleIntegrationTest.php` conventions) for all three risk areas; do not attempt true parallel-connection concurrency testing (SQLite in-memory test DB makes this impractical) — use sequenced/interleaved calls that reproduce the documented race window instead.

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| User-scoping enforcement (`HasUserScoping`) | API / Backend (Eloquent model layer) | — | Global scope lives on the model, enforced regardless of caller (HTTP, GraphQL, Console) |
| Superadmin bypass gating | API / Backend (Controller/Resolver/Resource) | Database / Storage (model global scope) | Each `withoutGlobalScopes()` call site is a controller/resolver/resource method that must re-apply its own gate |
| Scheduled job/command data access | API / Backend (Artisan Command in `routes/console.php`) | Database / Storage | No HTTP request context exists; `auth()->check()` is false by design in this tier |
| Credit-card cycle/payment state transitions | API / Backend (`CreditCardCycleService`, Observers) | Database / Storage (`DB::transaction()`) | Mutation logic and race-window are entirely server-side; no client tier involvement |
| Regression test coverage | Test / CI tier | — | New tests are Feature-level (Laravel `RefreshDatabase` + `Sanctum::actingAs` / `$this->artisan()`), not client-tier |

## Standard Stack

### Core (already in use — do not introduce alternatives)
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel Framework | 12.56.0 [VERIFIED: `php artisan --version`] | App framework, scheduler, Eloquent global scopes | Existing stack |
| laravel/sanctum | v4.3.1 [VERIFIED: `composer show`] | API auth used by all existing Feature tests via `Sanctum::actingAs()` | Existing stack |
| spatie/laravel-permission | 7.3.0 [VERIFIED: `composer show`] | `hasRole('superadmin')` checks throughout the codebase | Existing stack |
| PHPUnit (Laravel test runner) | bundled with Laravel 12 | Test framework; existing `tests/Feature`, `tests/Unit` suites | Existing stack |
| SQLite (`:memory:`) | — | Test DB driver, confirmed in `phpunit.xml` | Existing stack — **not** the mysql `second_brain` DB used at runtime |

**No new packages are needed for this phase.** All proof work uses the existing Sanctum/PHPUnit/RefreshDatabase toolchain already established by `tests/Feature/Api/*Test.php`, `GraphQLApiTest.php`, and `CreditCardLifecycleIntegrationTest.php`.

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Sequenced/interleaved PHPUnit test for the credit-card race condition | True parallel DB connections (e.g., `pcntl_fork` + separate PDO connections) | SQLite `:memory:` is per-connection/per-process; true concurrency is not observable in the current test DB without switching to a file-based or MySQL test DB, which would be a test-infrastructure change out of this phase's scope. Sequenced test is the only practical option under current `phpunit.xml`. |

## Architecture Patterns

### System Architecture Diagram — auth-scoping proof surfaces

```
HTTP request (Sanctum token)              GraphQL request (Sanctum token)         Console (no HTTP context)
        |                                          |                                       |
        v                                          v                                       v
  Controller / Filament Resource         GraphQL Resolver (__invoke)          Artisan Command (routes/console.php)
        |                                          |                                       |
        | auth()->check() = true                   | $context->user() available            | auth()->check() = FALSE
        | HasUserScoping global scope APPLIES       | some resolvers apply user filter       | HasUserScoping global scope
        | UNLESS withoutGlobalScopes() called       | manually, some don't (see below)       | SILENTLY NO-OPS (returns all
        |   -> then explicit gate REQUIRED           |                                       |  users' rows) — this is the
        v                                          v                                       |  by-design behavior batch
  13 withoutGlobalScopes() call sites    TransactionCategories.php: NO gate                 |  jobs rely on (they must
  (see table below) — each must be        (intentional per existing test name)              |  touch every user's cards/
  individually re-gated or proven          TotalByCategory.php: gated via                    |  loans), but is UNTESTED
  already-gated                            $user->hasRole('superadmin')                      |  as an explicit contract
                                                                                              v
                                                                            3 scheduled commands query
                                                                            CreditCard::query() / Loan::query()
                                                                            with NO explicit user filter —
                                                                            relies entirely on scope no-op
```

### Recommended Test File Organization
Follow existing per-domain convention (Claude's Discretion per CONTEXT.md — recommend, don't force):
```
tests/Feature/
├── ScopingSecurityTest.php          # NEW — generic pattern-based cross-user-leak test (D-05) across all HasUserScoping models
├── Api/DashboardApiTest.php         # EXTEND — add cross-user leak assertions for charts() (currently has none)
├── Api/GraphQLApiTest.php           # EXTEND — add explicit assertion documenting TotalByCategory + TransactionCategories gating
├── Api/FinanceReportApiTest.php     # EXTEND — add cross-user leak assertion for /reports/finance/details
├── ConsoleScopingTest.php           # NEW — D-04 non-HTTP scoping proof, uses $this->artisan(...) pattern already established
├── CreditCardLifecycleIntegrationTest.php  # EXTEND — add sequenced race-condition regression test
tests/Unit/
├── CreditCardCycleServiceTest.php   # EXTEND — add status-oscillation unit tests for syncCycleAndCardFromPayment/syncIssuedCycle
├── Observers/CreditCardExpenseObserverTest.php  # NEW (optional) — static-state isolation test if fix is made
```

### Pattern 1: Generic cross-user-leak regression test (D-05)
**What:** A data provider (or loop) that creates two users, seeds one owned record per `HasUserScoping` model for each, then asserts every authenticated read/list endpoint never returns the other user's records.
**When to use:** As the catch-all safety net alongside the targeted per-site tests.
**Models currently using `HasUserScoping`** [VERIFIED: `grep -l HasUserScoping app/Models/*.php`]: `Account`, `AuditLog`, `Backup`, `CategoryBudget`, `CreditCard`, `Loan`, `Notification`, `Subscription`, `Transaction`, `TransactionCategory`, `UserSetting` (11 models).
**Example (idiom to extend, not replace — matches existing `AccountAuthorizationTest.php` style but at HTTP level):**
```php
// Source: existing tests/Feature/Api/*Test.php pattern (Sanctum::actingAs)
public function test_user_cannot_see_other_users_accounts_via_index_route(): void
{
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $ownAccount = Account::factory()->create(['user_id' => $userA->id]);
    $otherAccount = Account::factory()->create(['user_id' => $userB->id]);

    Sanctum::actingAs($userA);
    $response = $this->getJson('/api/v1/accounts');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    $this->assertContains($ownAccount->id, $ids);
    $this->assertNotContains($otherAccount->id, $ids);
}
```

### Pattern 2: Non-HTTP (console) scoping proof (D-04)
**What:** Invoke the scheduled Artisan command directly via `$this->artisan(...)` (already the established idiom — see `CreditCardLifecycleIntegrationTest::scheduled_command_creates_month_cycle_for_active_cards`), seed data for two users, and assert the command processes **both** users' records (proving the scope correctly no-ops rather than silently filtering to one user or throwing).
**When to use:** For each of the 3 shipped scheduled commands.
**Example:**
```php
// Source: existing test file, extended pattern
public function test_generate_cycles_command_processes_all_users_active_cards(): void
{
    // No Sanctum::actingAs() call — deliberately unauthenticated, matching cron context
    $cardA = CreditCard::create(['user_id' => $userA->id, /* ... */ 'status' => CreditCardStatus::ACTIVE]);
    $cardB = CreditCard::create(['user_id' => $userB->id, /* ... */ 'status' => CreditCardStatus::ACTIVE]);

    $this->artisan('credit-cards:generate-cycles --month=2026-03')->assertExitCode(0);

    $this->assertDatabaseHas('credit_card_cycles', ['credit_card_id' => $cardA->id]);
    $this->assertDatabaseHas('credit_card_cycles', ['credit_card_id' => $cardB->id]);
}
```

### Pattern 3: Sequenced race-condition proof (credit-card cycle status)
**What:** Since true parallel DB connections aren't practical under SQLite `:memory:`, manually interleave the two code paths that the race window spans: call `CreditCardCycleService::syncCycleAndCardFromPayment()` twice in immediate succession with stale `$previousStatus`/`$currentStatus` arguments to simulate two "concurrent" payment updates racing to update the same cycle, then assert the final status is deterministic (not oscillating).
**When to use:** For `app/Services/CreditCardCycleService.php` — see exact current line numbers below (CONCERNS.md's line numbers 164-166/256-267/313-322 are stale; re-mapped below).

### Anti-Patterns to Avoid
- **True parallel DB connections in PHPUnit against `:memory:` SQLite:** Each connection to `:memory:` SQLite is isolated per-connection; a second "concurrent" connection sees an *empty* database, not the same state. Do not attempt this — it will produce false-negative "no race found" results.
- **Testing `HasUserScoping` in isolation via Unit tests only:** The trait's behavior depends on `auth()->check()`, which is a global/request-bound function. Feature-level tests (which properly set up or omit Sanctum auth) are required to get meaningful coverage of both branches.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Simulating concurrent user auth in tests | Custom multi-process test harness | Laravel's `Sanctum::actingAs()` + sequential calls within one test method | Laravel already isolates auth state per `actingAs()` call within a single test; no need for real concurrency to prove the scoping logic branches |
| Console-context auth simulation | Mocking `auth()->check()` | Just don't call `Sanctum::actingAs()` before `$this->artisan(...)` | `auth()->check()` naturally returns `false` in a test that never authenticates — this is the real-world console behavior, no mock needed |

**Key insight:** This phase is proof work on top of an already-correct-by-convention trait/pattern (`HasUserScoping` + `Sanctum::actingAs`/`$this->artisan`). No new abstraction is needed — the gap is coverage, not architecture.

## Runtime State Inventory

Not applicable — this phase does not rename, refactor, or migrate identifiers. Omitted per template guidance for non-rename phases.

## Common Pitfalls

### Pitfall 1: Trusting CONCERNS.md line numbers
**What goes wrong:** CONCERNS.md (dated 2024-12-19) cites specific line numbers that no longer match current code — e.g., it describes the race condition at "lines 164-166, 256-267, 313-322" but current `CreditCardCycleService.php` is 385 lines with materially different method boundaries (see re-mapped table below).
**Why it happens:** The codebase has evolved (observer chain, balance service split) since the doc was written.
**How to avoid:** Always re-grep/re-read the current file before writing a task that references a specific line number.
**Warning signs:** Any plan task that copies a CONCERNS.md line number verbatim without a fresh `Read` in this session.

### Pitfall 2: Assuming `withoutGlobalScopes()` == "user-scope bypass"
**What goes wrong:** All 7 Filament Resource occurrences are `withoutGlobalScopes([SoftDeletingScope::class])` — an **array-scoped** call that removes only the soft-delete scope, not the `user` scope. Treating these as equivalent to the ungated `Transaction::withoutGlobalScopes()` (no array, removes ALL scopes) call sites overstates their risk and could lead to redundant/misdirected test effort.
**Why it happens:** Both patterns share the method name `withoutGlobalScopes()`.
**How to avoid:** For each call site, check whether an array argument is passed (selective) or omitted (removes everything, including `user`).
**Warning signs:** A test written against a Filament Resource for "cross-user leak via withoutGlobalScopes" that doesn't first confirm `getEloquentQuery()` still applies `where('user_id', ...)` for non-superadmins (it does, in all 7 resources — confirmed above the `getRecordRouteBindingEloquentQuery()` override).

### Pitfall 3: `TransactionCategories.php` GraphQL query has zero gating — but is already tested as "intended"
**What goes wrong:** A naive read of this file looks like an unambiguous cross-user data leak (no `$context->user()` check at all, `withoutGlobalScopes()` on a `HasUserScoping` model). But `tests/Feature/Api/GraphQLApiTest.php::test_graphql_transaction_categories_query_returns_shared_categories` already asserts userA can see userB's categories, and the test's own name declares this "shared" behavior as intended.
**Why it happens:** Category taxonomy may be a deliberately shared/global concept even though the model has a `user_id` column (for ownership/audit purposes) — a legitimate but undocumented design choice.
**How to avoid:** Do NOT silently "fix" this in the phase (would break an existing, deliberately-named passing test and change confirmed production behavior). Flag as an Open Question requiring explicit user confirmation before the planner decides whether this is in-scope for D-02's fix path or should be left alone and documented.
**Warning signs:** A plan task titled "fix TransactionCategories leak" without first confirming with the user whether shared categories are intended product behavior.

### Pitfall 4: Static-state leak in Observers is real, and exists in TWO files, not one
**What goes wrong:** CONTEXT.md's discussion cited a live grep that found "no `static $` declarations in `app/Observers/*.php`," contradicting CONCERNS.md. Re-running the grep in this research session with correct regex escaping found `private static array $originalPointers` in `CreditCardExpenseObserver.php:15` AND `private static array $previousStatuses` in `CreditCardPaymentObserver.php:16` (the second one is a new finding not in the original CONCERNS.md).
**Why it happens:** The discussion's grep likely had a shell-escaping issue with the `$` character in the search pattern, producing a false negative.
**How to avoid:** Do not treat CONTEXT.md's negative finding as authoritative without independently re-verifying in the planning/research session, which was done here.
**Warning signs:** N/A — this is now corrected in this document; the planner should proceed with the assumption that the static-state pattern is real and present in 2 files.

**Actual risk assessment (verified):** No Octane/Swoole is used [VERIFIED: `composer.json` has no `laravel/octane` or swoole-related package], so PHP-FPM/CLI process-per-request means these statics do NOT leak across separate HTTP requests. The real risk is **intra-request** reentrancy: if a single request processes multiple `CreditCardExpense` or `CreditCardPayment` records with the same ID twice in one PHP process lifetime in unusual ways (e.g., a queued job processing a batch, or nested observer calls), stale entries could be read. Given `unset()` calls immediately after use in both observers, the actual exploitable window is narrow — but it is untested, matching the phase's stated goal.

### Pitfall 5: `CreditCardExpenseService::validateExpenseChange()` silently no-ops on missing target card
**What goes wrong:** Current code (`app/Services/CreditCardExpenseService.php:32-34`) does `$currentCard = CreditCard::query()->lockForUpdate()->find(...); if (! $currentCard) { return; }` — if the expense's `credit_card_id` points to a non-existent card, validation silently passes (returns without throwing), rather than rejecting the mutation. This differs from CONCERNS.md's exact framing but confirms the same underlying gap: **moving an expense to a non-existent card ID is not rejected.**
**Why it happens:** The early-return was likely intended as a defensive null-guard, not a validation bypass, but has the side effect of allowing invalid `credit_card_id` values through.
**How to avoid:** A regression test should assert that updating `credit_card_id` to a non-existent ID throws (or is otherwise rejected) — current behavior fails this assertion, confirming the bug is real and should be fixed per D-02 (high severity: data-integrity risk, orphaned expense records).
**Warning signs:** N/A — recommend planner include this in the "fix" bucket per D-02, since it's a confirmed real bug with a concrete reproduction path.

## Code Examples

### Current `HasUserScoping` implementation (exact code under test)
```php
// Source: app/Traits/HasUserScoping.php (read in full during research)
protected static function bootHasUserScoping(): void
{
    static::addGlobalScope('user', function (Builder $query) {
        if (auth()->check() && ! auth()->user()?->hasRole('superadmin')) {
            $query->where('user_id', auth()->id());
        }
    });

    static::creating(function ($model) {
        if (auth()->check() && empty($model->user_id)) {
            $model->user_id = auth()->id();
        }
    });
}

public function scopeWithoutUserScope(Builder $query)
{
    return $query->withoutGlobalScope('user');
}
```
Any test of the console-context behavior (D-04) exercises the `auth()->check()` branch directly — no mocking needed, since console tests simply never call `Sanctum::actingAs()`.

### The 3 shipped scheduled commands (exact source, D-04 target)
```php
// Source: routes/console.php (read in full during research)
Artisan::command('credit-cards:generate-cycles {--month=} {--issue-ready}', function () { /* ... */ })
    ->purpose('Create monthly credit card cycles and optionally issue ready cycles');

Artisan::command('loans:sync-installments {--date=}', function () { /* ... */ })
    ->purpose('Generate missing loan installments and post due ones to transactions');

Artisan::command('subscriptions:sync-renewals {--date=}', function () { /* ... */ })
    ->purpose('Post due subscription renewals to transactions or credit card expenses');

Schedule::command('loans:sync-installments')->dailyAt('01:50');
Schedule::command('subscriptions:sync-renewals')->dailyAt('01:55');
Schedule::command('credit-cards:generate-cycles --issue-ready')->dailyAt('02:00');
```
All three internally do `CreditCard::query()->where('status', ...)->get()` / `Loan::query()->where('status', 'active')->...->get()` with **no explicit `user_id` filter** — they rely entirely on the `HasUserScoping` global scope no-op (since `auth()->check()` is false in this context) to see all users' records. This is confirmed correct/intended (batch jobs must process every user), but currently has zero test coverage proving it, which is exactly D-04's ask.

## State of the Art (re-verified CONCERNS.md claims vs. current code)

| CONCERNS.md Claim (2024-12-19) | Current Status (verified 2026-08-06) | Current Location |
|---|---|---|
| `CreditCardExpenseObserver` static-state leak | **STILL PRESENT** (CONTEXT.md's negative grep finding was a false negative from a shell-escaping bug) | `app/Observers/CreditCardExpenseObserver.php:15` (`private static array $originalPointers`) |
| — (not in original doc) | **NEW: same pattern also in `CreditCardPaymentObserver`** | `app/Observers/CreditCardPaymentObserver.php:16` (`private static array $previousStatuses`) |
| Credit Card Cycle Status Race Condition (old lines 164-166, 256-267, 313-322) | **STILL PRESENT**, re-located (see below) | `app/Services/CreditCardCycleService.php` — `syncIssuedCycle()` lines 160-166, `syncCycleAndCardFromPayment()` lines 253-267, `handleDeletedPayment()` lines 310-322 |
| Transaction Observer Recursion on Transfer Deletion | **Guard still present, real residual risk unchanged** — `deleted()` handler deletes the paired transaction without wrapping in `DB::transaction()`, so a partial failure (pair delete fails after outer commit) can still orphan a transfer pair | `app/Observers/TransactionObserver.php:49-58` |
| Missing Validation on CreditCardExpense Move | **STILL PRESENT, confirmed via new mechanism** — early-return on missing target card silently skips validation instead of throwing | `app/Services/CreditCardExpenseService.php:32-34` |

## Re-mapped race-condition line numbers (for planner task precision)

`app/Services/CreditCardCycleService.php` (385 lines total):
- `issueCycle()` — lines 92-137, wraps mutation in `DB::transaction()` (line 100)
- `syncIssuedCycle()` — lines 139-195; status decision logic at **lines 160-166**; wraps in `DB::transaction()` (line 147)
- `syncCycleAndCardFromPayment()` — lines 236-291; status decision logic at **lines 253-267**; wraps in `DB::transaction()` (line 247); this is the method invoked by `CreditCardPaymentObserver::updated()` on every payment status change — the primary race-condition surface
- `handleDeletedPayment()` — lines 293-338; status decision logic at **lines 310-322**; wraps in `DB::transaction()` (line 301)
- `refreshCycleStatuses()` — lines 340-368, a **non-transactional** batch reconciliation loop (no `DB::transaction()` wrapper around the whole loop, though individual `$cycle->update()` calls are atomic per-row) — worth noting as a secondary but lower-priority fragility point

The actual race window: two near-simultaneous payment status updates on the *same* `CreditCardCycle` both read `$paidAmount` via `sum('total_amount')` before either writes back `status`, so the second write can compute `$status` from stale `$paidAmount` data, producing the oscillation CONCERNS.md describes. `DB::transaction()` reduces but does not eliminate this window (SQLite/MySQL default isolation is not `SERIALIZABLE` in this app's config — not separately re-verified in this session, flagged as `[ASSUMED]`).

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | Default DB transaction isolation level (not `SERIALIZABLE`) is what allows the race window to remain open despite `DB::transaction()` wrapping | State of the Art / race-condition mapping | If isolation is actually stricter than assumed, the sequenced-test approach may not reproduce a real race, and the "still present" classification could be too strong — recommend the planner's test task explicitly proves (not assumes) the oscillation via the sequenced-call reproduction, and downgrades the claim if the test can't reproduce it |
| A2 | `TransactionCategories.php`'s ungated cross-user visibility is intentional "shared taxonomy" design, not an oversight | Pitfall 3 | If actually unintentional, this is a real cross-tenant data-disclosure bug that should move to D-02's "high severity, fix" bucket instead of being left alone — this MUST be confirmed with the user before the planner locks a decision either way |

## Open Questions

1. **Is `TransactionCategories.php`'s lack of user gating intentional shared-category design, or an unfixed oversight?**
   - What we know: An existing, deliberately-named test (`test_graphql_transaction_categories_query_returns_shared_categories`) already asserts and locks in this cross-user-visible behavior. The model itself (`TransactionCategory`) uses `HasUserScoping` and has a `user_id` column with an eager-loaded `user()` relation, suggesting per-user ownership was originally intended for at least the write path.
   - What's unclear: No code comment or design doc explains why reads are intentionally unscoped while writes (via `HasUserScoping`'s `creating` hook) are user-attributed.
   - Recommendation: Planner should present this to the user as a locked-vs-flagged decision before writing tasks — either (a) confirm shared taxonomy is intended and write a task that only adds an explicit code comment + keeps the existing test as documentation, or (b) confirm it's a bug and scope a fix (would require a product decision on whether categories become per-user, which is a bigger change than "add a gate").

2. **Should the `TransactionObserver` transfer-pair deletion be wrapped in `DB::transaction()` as part of this phase's D-02 fix bucket?**
   - What we know: The delete-pair logic (`app/Observers/TransactionObserver.php:49-58`) is not wrapped in an explicit transaction; CONCERNS.md flags this as a residual risk (orphaned transfer pairs if pair-delete silently fails).
   - What's unclear: Whether this qualifies as "high severity" per D-02 (it's a data-integrity risk affecting report accuracy, not a security/disclosure issue) — the planner should assess severity against D-02's exact wording ("corrupted/drifted balances, broken money math") to decide fix-vs-document.
   - Recommendation: Likely qualifies as high severity (orphaned transfer pairs = broken money math in reports) — recommend the planner include a `DB::transaction()` wrap + regression test as an in-scope fix, but this is the planner's call per D-02's criteria, not a locked research conclusion.

## Environment Availability

Not applicable — this phase has no new external dependencies. All tooling (PHPUnit, Sanctum, SQLite in-memory test DB, Artisan) is already installed and verified working via the existing test suite.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit (Laravel 12 test runner), attributes-based (`#[Test]`) mixed with `test_` method-name convention — both styles coexist in the codebase |
| Config file | `phpunit.xml` (project root) |
| Quick run command | `php artisan test --filter=<TestClass>` or `vendor/bin/phpunit --filter=<method>` |
| Full suite command | `php artisan test` (runs both `tests/Unit` and `tests/Feature` suites per `phpunit.xml`) |

### Phase Requirements → Test Map
No formal REQ-IDs exist for this phase (per phase description: "none assigned yet in ROADMAP.md — derive coverage from CONTEXT.md decisions D-01 through D-05"). Mapping instead to CONTEXT.md decisions:

| Decision | Behavior | Test Type | Automated Command | File Exists? |
|----------|----------|-----------|-------------------|-------------|
| D-04 | Non-HTTP scoping in scheduled commands | feature | `php artisan test --filter=ConsoleScopingTest` | ❌ Wave 0 (new file) |
| D-05 (generic) | No authenticated route/query leaks cross-user data | feature | `php artisan test --filter=ScopingSecurityTest` | ❌ Wave 0 (new file) |
| D-05 (targeted, 6 non-Filament sites) | Each specific `withoutGlobalScopes()` site correctly gated | feature | `php artisan test --filter=DashboardApiTest\|GraphQLApiTest\|FinanceReportApiTest` | Partial — files exist, new test methods needed |
| D-05 (Filament, 7 sites) | Panel-level access control + explicit cross-user assertion | feature | `php artisan test --filter=Filament` | ❌ — check `tests/Feature/Filament/` directory contents before planning (exists but content not enumerated in this research pass) |
| Race condition proof | Sequenced interleaving reproduces/refutes oscillation | unit or feature | `php artisan test --filter=CreditCardCycleServiceTest\|CreditCardLifecycleIntegrationTest` | Partial — files exist, new test methods needed |
| Observer static-state proof | Intra-request reentrancy safety | unit | new test class needed | ❌ Wave 0 (new file, optional per severity assessment) |

### Sampling Rate
- **Per task commit:** targeted `--filter` run for the file(s) touched
- **Per wave merge:** `php artisan test` (full suite; suite is currently fast — SQLite in-memory, no external services)
- **Phase gate:** Full suite green before `/gsd-verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/ScopingSecurityTest.php` — new file for D-05 generic cross-user-leak sweep
- [ ] `tests/Feature/ConsoleScopingTest.php` — new file for D-04 non-HTTP scoping proof
- [ ] Check `tests/Feature/Filament/` directory contents (exists but not enumerated in this research pass) before planning Filament-resource-specific tasks — confirm whether panel-access tests already exist there
- [ ] No framework/config install needed — existing PHPUnit + Laravel test kernel covers all phase requirements

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | No (out of scope — Sanctum auth itself is not under test in this phase) | — |
| V3 Session Management | No | — |
| V4 Access Control | Yes | `HasUserScoping` global scope + `hasRole('superadmin')` gates — this IS the subject of the phase |
| V5 Input Validation | Partial | `CreditCardExpenseService::validateExpenseChange()` target-card existence check (Pitfall 5) |
| V6 Cryptography | No | — |

### Known Threat Patterns for this stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Insecure Direct Object Reference via `withoutGlobalScopes()` without re-gating | Elevation of Privilege / Information Disclosure | Explicit `hasRole('superadmin')` or `where('user_id', ...)` re-application at every bypass site (already the pattern in 12 of 13 sites; `TransactionCategories.php` is the one exception, flagged as Open Question) |
| Race condition on concurrent state-mutating writes (TOCTOU) | Tampering | `DB::transaction()` wrapping (already used) + row locking (`lockForUpdate()`, already used in `CreditCardExpenseService`) — the gap is proof, not missing mitigation infrastructure |
| Static class-property state bleeding across model instances within one process | Tampering / Information Disclosure (narrow, intra-request only given no Octane) | Prefer instance-scoped state or `Model::$attributes`-diffing instead of `private static array` keyed by ID; low urgency given no Octane, but should be tested per D-02 |

## Sources

### Primary (HIGH confidence — all verified via direct code reads/greps in this session)
- `app/Traits/HasUserScoping.php` — full read
- `routes/console.php` — full read
- `app/Services/CreditCardCycleService.php` — full read (385 lines)
- `app/Services/CreditCardExpenseService.php` — partial read (lines 1-60)
- `app/Observers/CreditCardExpenseObserver.php`, `CreditCardPaymentObserver.php`, `TransactionObserver.php` — full reads
- `app/Http/Controllers/Api/V1/DashboardController.php` — full read (211 lines)
- `app/GraphQL/Queries/TotalByCategory.php`, `TransactionCategories.php` — full reads
- `app/Filament/Resources/{Accounts,AuditLogs,Backups,Notifications,Permissions,Roles,UserSettings}/*Resource.php` — grepped for `withoutGlobalScopes` context in all 7
- `tests/Feature/Api/GraphQLApiTest.php` — full read (503 lines)
- `tests/Feature/Api/DashboardApiTest.php`, `FinanceReportApiTest.php` — grepped for test method names and cross-user assertions
- `tests/Feature/CreditCardLifecycleIntegrationTest.php`, `tests/Unit/CreditCardCycleServiceTest.php` — read/grepped
- `tests/Feature/AccountAuthorizationTest.php`, `TransactionAuthorizationTest.php` — full reads
- `phpunit.xml`, `composer.json` — full/partial reads for DB driver and package versions
- `composer show laravel/sanctum`, `composer show spatie/laravel-permission`, `php artisan --version` — verified via Bash

### Secondary (MEDIUM confidence)
- None — all findings in this research were directly verified against current code, not inferred from external sources.

### Tertiary (LOW confidence)
- None.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — no new packages needed, all versions verified via `composer show`
- Architecture: HIGH — all patterns read directly from current source
- Pitfalls: HIGH — each pitfall backed by a direct code read/grep in this session, not training-data assumptions
- Race-condition root cause (isolation level): MEDIUM — flagged `[ASSUMED]` as A1, not independently verified against actual DB transaction isolation config

**Research date:** 2026-08-06
**Valid until:** 14 days (fast-moving — this is proof-of-currently-shipped-code, any subsequent commits to the touched files invalidate line-number references)
