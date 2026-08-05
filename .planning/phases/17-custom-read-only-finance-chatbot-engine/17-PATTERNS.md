# Phase 17: Custom read-only finance chatbot engine - Pattern Map

**Mapped:** 2026-08-05
**Files analyzed:** 13 (new) + 2 (modified)
**Analogs found:** 11 / 13

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|---|---|---|---|---|
| `app/Services/Chatbot/Contracts/ChatIntent.php` | contract/interface | request-response | *(no existing interface precedent — see No Analog Found)* | no-analog |
| `app/Services/Chatbot/IntentRouter.php` | service (router) | request-response | `app/Services/FinanceReportService.php` (constructor-injected, stateless service with public query methods) | role-match |
| `app/Services/Chatbot/Intents/AccountBalancesIntent.php` | service (read adapter) | CRUD (read) | `app/Http/Controllers/Api/V1/AccountController.php::index` (superadmin-aware scoped query shape) | role-match |
| `app/Services/Chatbot/Intents/UpcomingPaymentsIntent.php` | service (read adapter) | CRUD (read, multi-source merge) | `app/Http/Controllers/Api/V1/DashboardController.php::upcomingPayments` | exact (logic source) |
| `app/Services/Chatbot/Intents/MonthlySpendingIntent.php` | service (read adapter) | CRUD (read) | `app/Services/FinanceReportService.php::getTable` + `FinanceReportController.php::summary` | exact |
| `app/Services/Chatbot/Exceptions/UnsupportedIntentException.php` | exception | error-signal | *(no custom Exception class exists in repo — see No Analog Found)* | no-analog |
| `app/Http/Controllers/Api/V1/ChatbotController.php` | controller | request-response | `app/Http/Controllers/Api/V1/FinanceReportController.php` (thin controller, constructor-injected service, `response()->json()`) | exact |
| `app/Http/Requests/Api/AskChatbotRequest.php` | request/validation | request-response | `app/Http/Requests/Api/StoreTransactionRequest.php` | role-match |
| `routes/api.php` (modify) | route | request-response | existing `Route::middleware(['auth:sanctum', 'throttle:api-read'])->group(...)` block | exact |
| `app/Providers/AppServiceProvider.php` (modify) | config/DI wiring | — | `boot()`/`register()` method (currently empty `register()`) | exact |
| `resources/js/stores/chatbot.js` | store (Pinia) | client-state | `resources/js/stores/auth.js` | role-match (minus persistence) |
| `resources/js/components/chatbot/ChatWidget.vue` | component (overlay panel) | request-response (fetch on demand) | `resources/js/components/ui/ConfirmModal.vue` (Teleport-based fixed overlay) + `resources/js/views/DashboardView.vue` (fetch-with-Bearer-token pattern) | role-match |
| `resources/js/components/layout/AppLayout.vue` (modify) | layout mount point | — | itself — existing toast `<transition-group>` mount block | exact |

## Pattern Assignments

### `app/Services/Chatbot/IntentRouter.php` (service, request-response)

**Analog:** `app/Services/FinanceReportService.php` (constructor style) + RESEARCH.md's own worked example (already codebase-verified against `AppServiceProvider.php`)

**Constructor/DI pattern** (mirrors `FinanceReportController.php` lines 15-20, thin constructor-promoted properties):
```php
public function __construct(
    private readonly FinanceReportService $financeReportService,
    private readonly FinanceReportSnapshotService $financeReportSnapshotService,
    private readonly FinanceReportExportService $financeReportExportService,
) {
}
```
Apply the same `private readonly` constructor-promotion style to `IntentRouter` and each `*Intent` class.

**Binding pattern** — `app/Providers/AppServiceProvider.php` currently has an **empty** `register()` (lines 30-33):
```php
public function register(): void
{
    //
}
```
This is the exact insertion point for `$this->app->singleton(IntentRouter::class, fn () => new IntentRouter([...]));` — no existing singleton-binding precedent exists in this file today (it currently only does rate limiter/observer wiring in `boot()`), so this is a new but idiomatic addition to the already-present-but-unused `register()` method.

---

### `app/Services/Chatbot/Intents/AccountBalancesIntent.php` (service, CRUD read)

**Analog:** `app/Http/Controllers/Api/V1/AccountController.php::index` (lines 36-53)

**Core scoped-query pattern to copy** (superadmin-aware manual scoping — note `Account` uses `QueryBuilder` + explicit `when(...)` rather than relying solely on a global scope trait):
```php
$accounts = QueryBuilder::for(Account::class)
    ->when(
        ! $request->user()->hasRole('superadmin'),
        fn ($query) => $query->where('user_id', $request->user()->id)
    )
    ->allowedFilters(
        AllowedFilter::exact('type'),
        AllowedFilter::exact('is_active'),
        AllowedFilter::exact('currency'),
    )
    ->allowedSorts('name', 'balance', 'opening_balance', 'created_at')
    ->defaultSort('-created_at')
    ->cursorPaginate($request->integer('per_page', 20));
```
For the chatbot intent, drop `QueryBuilder`/pagination (not needed for a chat answer) but **keep the identical `when(! $user->hasRole('superadmin'), ...)` ownership guard** — this is the project's standard manual-scoping idiom used everywhere balances are read directly from `Account`.

**Correction carried from RESEARCH.md:** do NOT inject `AccountBalanceService` — verified its only public methods are `handleCreated/Updated/Deleted(Transaction $t): void` (mutation listeners), not a balance-read API.

---

### `app/Services/Chatbot/Intents/UpcomingPaymentsIntent.php` (service, CRUD read / multi-source merge)

**Analog:** `app/Http/Controllers/Api/V1/DashboardController.php::upcomingPayments` (full method, lines 47-136)

**Core pattern to copy verbatim (ownership-check idiom per model)** — these three models do NOT use `HasUserScoping` directly; ownership is enforced via `whereHas` on the parent relation:
```php
// LoanPayment (lines 58-66)
$loanPayments = LoanPayment::query()
    ->with(['loan', 'postingTransaction'])
    ->whereBetween('due_date', [$today, $until])
    ->where('status', '!=', 'paid')
    ->when(
        ! $user->hasRole('superadmin'),
        fn ($query) => $query->whereHas('loan', fn ($loanQuery) => $loanQuery->where('user_id', $user->id))
    )
    ->get()
    ->map(fn (LoanPayment $payment) => [ /* ... shape ... */ ]);

// CreditCardPayment (lines 79-98) — same whereHas('creditCard', ...) idiom
// Subscription (lines 100-127) — uses ->active() scope + direct ->where('user_id', $user->id) (Subscription DOES carry user_id directly)
```
**Merge/sort pattern** (lines 129-135):
```php
return response()->json([
    'data' => collect($loanPayments)
        ->merge($creditCardPayments)
        ->merge($subscriptions)
        ->sortBy('due_date')
        ->values(),
]);
```

**Refactor required (per RESEARCH.md "Don't Hand-Roll"):** this logic is currently controller-inline, not in a service. Extract the body of `upcomingPayments()` into a small shared method (e.g. on a new/existing service) callable from both `DashboardController::upcomingPayments()` and `UpcomingPaymentsIntent::handle()` — do not duplicate this ~90-line block. `SubscriptionService` is already injected into `DashboardController` (`__construct(private readonly SubscriptionService $subscriptionService)`, line 18) for `getBillingAmount()`/`hasPostingForRenewal()` calls used inside the `Subscription` map closure — the intent will need the same dependency.

---

### `app/Services/Chatbot/Intents/MonthlySpendingIntent.php` (service, CRUD read)

**Analog:** `app/Http/Controllers/Api/V1/FinanceReportController.php::summary` (lines 22-49) calling `app/Services/FinanceReportService.php`

**Controller→service call pattern to copy:**
```php
$userId = $request->user()->hasRole('superadmin')
    ? null
    : $request->user()->id;

$years = $this->financeReportService->loadYears($userId);
$year = (int) ($validated['year'] ?? ($years[0] ?? now()->year));
// ...
'table' => $this->financeReportService->getTable($year, $userId),
```
Note the **inverted null-vs-id scoping idiom** here (`$userId = ... ? null : $request->user()->id`) is different from `AccountController`'s `when(...)` idiom — `FinanceReportService` methods accept a nullable `$userId` and presumably apply their own internal scoping/`whereNull` logic when `null` (superadmin = see-all). Follow whichever idiom matches the specific service/model being called; do not mix them.

`FinanceReportService::getTable(int $year, ?int $userId = null): array` (confirmed present at line 56) is the exact method to call for "current month's row" per RESEARCH.md's Don't-Hand-Roll guidance — do not re-derive SQL aggregates.

---

### `app/Http/Controllers/Api/V1/ChatbotController.php` (controller, request-response)

**Analog:** `app/Http/Controllers/Api/V1/FinanceReportController.php` (whole file, 116 lines) — closest exact thin-controller shape: constructor-injected service(s), `Request` → `FormRequest`/`$request->validate()` → service call → `response()->json([...])`.

**Imports pattern** (lines 1-11):
```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\FinanceReportExportService;
use App\Services\FinanceReportSnapshotService;
use App\Services\FinanceReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
```

**Core action pattern** (lines 22-49, adapt to a single `ask()` action per RESEARCH.md's Code Examples section):
```php
public function summary(Request $request): JsonResponse
{
    $validated = $request->validate([...]);
    // ...
    return response()->json([
        'years' => $years,
        // ...
    ]);
}
```
No `try/catch` block exists in this controller or in `AccountController` — errors are handled by the global `bootstrap/app.php` `withExceptions()` renderers (see Shared Patterns below), not per-controller try/catch. Follow this: let `UnsupportedIntentException` bubble and register a renderer for it rather than catching it in `ChatbotController::ask()`.

---

### `app/Http/Requests/Api/AskChatbotRequest.php` (request/validation)

**Analog:** `app/Http/Requests/Api/StoreTransactionRequest.php` (full file, 28 lines)

**Full pattern to copy:**
```php
<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id'              => ['required', 'integer', 'exists:accounts,id'],
            // ...
        ];
    }
}
```
For `AskChatbotRequest`, use `Rule::in([...three intent keys...])` for the `intent` field per RESEARCH.md's V5 Input Validation guidance — this is the hard reject-by-default boundary enforcing D-01's scope. Every existing `Store*Request`/`Update*Request` in this directory uses `authorize(): bool { return true; }` (auth is handled by route middleware, not the FormRequest) — follow this exact convention rather than adding per-field ownership checks in `authorize()`.

---

### `routes/api.php` (modify)

**Analog:** existing read-endpoints group (lines 36-59)

**Exact insertion pattern** — add inside the existing `Route::middleware(['auth:sanctum', 'throttle:api-read'])->group(function () { ... })` block (do not create a new middleware group):
```php
Route::get('accounts', [AccountController::class, 'index']);
Route::get('dashboard/upcoming-payments', [DashboardController::class, 'upcomingPayments']);
Route::get('reports/finance', [FinanceReportController::class, 'summary']);
```
New line to add alongside these: `Route::post('chatbot/ask', [ChatbotController::class, 'ask']);` plus `use App\Http\Controllers\Api\V1\ChatbotController;` in the top-of-file `use` block (alphabetically ordered with the other 13 controller imports, lines 3-16).

---

### `resources/js/stores/chatbot.js` (Pinia store, client-state)

**Analog:** `resources/js/stores/auth.js` (full file, 293 lines)

**Composition-API store shape to copy** (lines 1-16):
```javascript
import { computed, ref } from 'vue';
import { defineStore } from 'pinia';

export const useAuthStore = defineStore('auth', () => {
    const accessToken = ref(localStorage.getItem('fluxa_access_token'));
    // ...
    const isAuthenticated = computed(() => !!accessToken.value);

    function setUser(value) { /* ... */ }

    return { accessToken, /* ... */, setUser, /* ... */ };
});
```
**Deliberate deviation for `chatbot.js`:** omit ALL `localStorage.getItem/setItem/removeItem` calls that `auth.js` uses for persistence (D-05 requires session-only, lost-on-refresh state) — every `ref` in `chatbot.js` should be plain in-memory (`ref([])`, `ref(false)`, `ref('intent_select')`), never backed by `localStorage`.

**Fetch pattern to copy for the store's `askChatbot()` action** — this app does NOT use axios for authenticated calls; every store/view uses native `fetch()` with a manually-attached `Authorization: Bearer` header. Confirmed in `resources/js/views/DashboardView.vue` lines 365-391:
```javascript
async function fetchUpcomingPayments() {
    if (!auth.accessToken) {
        upcomingPayments.value = [];
        return;
    }

    upcomingLoading.value = true;

    try {
        const response = await fetch('/api/v1/dashboard/upcoming-payments?days=3', {
            headers: {
                Authorization: `Bearer ${auth.accessToken}`,
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            upcomingPayments.value = [];
            return;
        }

        const data = await response.json();
        upcomingPayments.value = data.data ?? [];
    } finally {
        upcomingLoading.value = false;
    }
}
```
For a POST body (chatbot `ask`), mirror `auth.js`'s `login()` shape instead (lines 103-132, `method: 'POST'`, `'Content-Type': 'application/json'`, `body: JSON.stringify(payload)`, `try/catch/finally` with a `loading` ref and error fallback string) — combine the Bearer-header-from-`useAuthStore()` idiom (DashboardView) with the POST/error-handling idiom (auth.js `login`).

---

### `resources/js/components/chatbot/ChatWidget.vue` (component, overlay panel)

**Analog 1 — overlay/panel structure:** `resources/js/components/ui/ConfirmModal.vue` (full file, 53 lines)

**Teleport + fixed-overlay pattern to copy** (lines 1-23):
```vue
<script setup>
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline';

defineProps({ open: Boolean, /* ... */ });
defineEmits(['confirm', 'cancel']);
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/30 p-4 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            @click.self="$emit('cancel')"
        >
            <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-6 shadow-xl">
                ...
            </div>
        </div>
    </Teleport>
</template>
```
`ChatWidget.vue` should use `Teleport to="body"` the same way for the panel, but per D-06 (floating trigger button always visible, panel toggled via the Pinia store's `isOpen`) — not a modal-with-backdrop-dismiss pattern, since the trigger icon itself must remain visible/clickable even when the panel is closed. Use `<script setup>` + `@heroicons/vue/24/outline` import convention exactly as shown (already a project dependency, no new import style needed).

**Analog 2 — authenticated data fetch inside a component:** see `resources/js/stores/chatbot.js` section above (DashboardView.vue fetch pattern) — `ChatWidget.vue` should call the Pinia store's action rather than `fetch()` directly, consistent with how `DashboardView.vue` centralizes fetch logic in `<script setup>` functions rather than the store (note: this app's existing convention actually keeps fetch logic in the *view*, not the store, for dashboard data — `chatbot.js` deliberately centralizes it in the *store* instead, per RESEARCH.md's Architecture Patterns diagram, since the widget is reused across the whole layout, not one view).

---

### `resources/js/components/layout/AppLayout.vue` (modify)

**Analog:** itself, existing toast mount block (lines 1-34, full file — already read in full, no re-read needed)

**Exact insertion pattern:**
```vue
<script setup>
import AppNavbar from './AppNavbar.vue';
import { useToast } from '@/composables/useToast.js';

const { toasts, removeToast } = useToast();
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <AppNavbar />
        <main class="pt-14">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <slot />
            </div>
        </main>
        <div class="fixed bottom-4 right-4 z-50 flex flex-col gap-2">
            <transition-group name="toast">...</transition-group>
        </div>
    </div>
</template>
```
Add `import ChatWidget from '@/components/chatbot/ChatWidget.vue';` to the `<script setup>` imports and `<ChatWidget />` as a new sibling element after the toast `<div>`, still inside the root `<div class="min-h-screen ...">`. This is the single shared authenticated shell (confirmed via full-file read) — matches the RESEARCH.md Code Examples section exactly. **Open item flagged by RESEARCH.md (Pitfall 5 / Open Question 1):** verify `AppLayout.vue` is applied to every `requiresAuth: true` route before relying on one mount point — not independently re-verified in this pattern-mapping pass; treat as a Wave 0 confirmation task.

---

## Shared Patterns

### Authentication / Route Middleware
**Source:** `routes/api.php` lines 36-59 (`Route::middleware(['auth:sanctum', 'throttle:api-read'])->group(...)`)
**Apply to:** `ChatbotController::ask()` route registration — add inside this exact existing group, do not create a new middleware group or a looser/separate rate limiter.

### Per-user data scoping
**Source:** Two idioms coexist in this codebase — pick per-model:
1. `HasUserScoping` trait (`app/Traits/HasUserScoping.php`, global scope on `user_id`) — used by `Account` and other trait-using models.
2. Manual `when(! $user->hasRole('superadmin'), fn ($q) => $q->where(...))` / `whereHas('parent', fn ($q) => $q->where('user_id', ...))` — used in `AccountController::index` and `DashboardController::upcomingPayments` for models/relations without the trait.
**Apply to:** All three intent classes — `AccountBalancesIntent` should rely on `Account`'s existing scoping (confirm whether `Account` uses `HasUserScoping` or manual `when()`; `AccountController::index` uses manual `when()` even though the model may also carry the trait — follow whichever the model's other read paths use to avoid double-scoping bugs). `UpcomingPaymentsIntent` must replicate `DashboardController`'s exact `whereHas`/`where('user_id', ...)` idiom per model (`LoanPayment`, `CreditCardPayment` via relation; `Subscription` directly).

### Error handling / exception rendering
**Source:** `bootstrap/app.php` lines 27-65 (`->withExceptions(function (Exceptions $exceptions) { $exceptions->render(fn (SomeException $e, Request $request) => ...); })`)
**Apply to:** `UnsupportedIntentException` — register a new `$exceptions->render(function (UnsupportedIntentException $e, Request $request) { if ($request->is('api/*')) { return response()->json(['message' => '...'], 422 or 400); } })` block here, following the exact shape of the existing `ValidationException`/`ModelNotFoundException` renderers, rather than a try/catch inside `ChatbotController`. No prior custom `App\Exceptions\*` class exists in this repo (verified — `app/Exceptions` directory does not exist), so `UnsupportedIntentException` will be the first hand-rolled domain exception; keep it minimal (extend base `\Exception`, no special properties needed beyond the constructor arg already shown in RESEARCH.md's code example).

### Input validation (FormRequest)
**Source:** `app/Http/Requests/Api/StoreTransactionRequest.php` (whole file) — every `Store*`/`Update*` request in `app/Http/Requests/Api/` uses `authorize(): bool { return true; }` + a flat `rules(): array` returning Laravel validation rule arrays.
**Apply to:** `AskChatbotRequest` — `intent` field via `Rule::in([...])`, `params.*` per-intent rules following the same array-rule-string convention (no custom Rule objects elsewhere in this directory to mirror beyond `Rule::in`/`exists`/`different`).

### Response shaping
**Source:** `FinanceReportController::summary` / `DashboardController::upcomingPayments` — both return `response()->json(['data' => ...])` or a flat top-level keyed array (`['years' => ..., 'table' => ...]`); no single `ApiResponse` wrapper class exists in this codebase (confirmed no shared response-formatting helper found during controller reads).
**Apply to:** `ChatbotController::ask()` — use `response()->json(['data' => $answer])`, matching `DashboardController::upcomingPayments()`'s `['data' => ...]` envelope shape exactly (RESEARCH.md's own Code Examples section already proposes this).

## No Analog Found

| File | Role | Data Flow | Reason |
|---|---|---|---|
| `app/Services/Chatbot/Contracts/ChatIntent.php` | contract/interface | request-response | No PHP `interface` exists anywhere under `app/` today (all services/controllers in this codebase are concrete classes with no contract layer) — RESEARCH.md's own worked example is the only available pattern; follow it directly (single-method `handle(User $user, array $params): array` + a `key(): string` discriminator). |
| `app/Services/Chatbot/Exceptions/UnsupportedIntentException.php` | exception | error-signal | No `app/Exceptions/*` custom exception class exists in this repo (confirmed via search) — the closest precedent is the *handling* side (`bootstrap/app.php` renderers for framework exceptions), not a custom-exception-definition example. Keep the class minimal per RESEARCH.md's example; there is nothing else in-repo to mirror for the class body itself. |

## Metadata

**Analog search scope:** `app/Http/Controllers/Api/V1/`, `app/Http/Requests/Api/`, `app/Services/`, `app/Traits/`, `app/Providers/`, `routes/api.php`, `bootstrap/app.php`, `resources/js/stores/`, `resources/js/components/{ui,layout,reports}/`, `resources/js/views/DashboardView.vue`, `tests/Feature/Api/DashboardApiTest.php`
**Files scanned:** ~15 direct reads (AccountController, DashboardController, FinanceReportController, HasUserScoping, StoreTransactionRequest, AppServiceProvider, routes/api.php, bootstrap/app.php, auth.js, AppLayout.vue, ConfirmModal.vue, DashboardView.vue excerpt, DashboardApiTest.php excerpt) + directory listings for `Http/Requests/Api/` and `resources/js/components/`
**Pattern extraction date:** 2026-08-05

---

*Phase: 17-custom-read-only-finance-chatbot-engine*
