# Phase 17: Custom read-only finance chatbot engine - Research

**Researched:** 2026-08-05
**Domain:** Custom stateless intent-router / guided-flow conversational engine over an existing Laravel 12 + Vue 3 SPA finance app
**Confidence:** HIGH (backend structure, service reuse, routing, auth) / MEDIUM (frontend widget mount point, exact Pinia store shape) / LOW (nothing — no unverifiable external library claims needed since this is a no-dependency, hand-rolled feature)

## Summary

Phase 17 is a hand-rolled feature with no new third-party dependency to evaluate — the engineering questions are entirely about *where in this specific codebase* to put a stateless intent router, how to wire its three intents to already-existing, already-tested backend logic without duplicating it, and how to mount a global floating widget in the existing Vue 3 SPA. All findings below come directly from reading the current repository (`[VERIFIED: codebase read]`), not from external docs — there is no framework/library research needed because this phase deliberately avoids BotMan/NLU libraries per D-02/CONTEXT.md.

The most important correction to CONTEXT.md's own `code_context` section: **`AccountBalanceService` does not read/report balances** — it only mutates `Account.balance` as a side effect of transaction create/update/delete (`handleCreated`, `handleUpdated`, `handleDeleted`). The actual "account balances" read path is `Account` model's `balance` column, exposed today via `AccountController::index`/`AccountResource`. The chatbot's balance intent should query `Account::where(...)->get()` (scoped via `HasUserScoping`) directly, or reuse `AccountResource` for shaping the response — it should NOT call `AccountBalanceService`, whose public methods only accept a `Transaction` and mutate state. This is flagged as a correction, not an assumption, because it is directly verified from the service's source.

**Primary recommendation:** Build a single new `App\Services\Chatbot` namespace containing an `IntentRouter` plus one handler class per intent (`AccountBalancesIntent`, `UpcomingPaymentsIntent`, `MonthlySpendingIntent`), each a thin adapter that calls existing scoped queries/services and shapes a small response payload consumed by one new stateless `POST /api/v1/chatbot/ask` endpoint (or `GET` with a query param, see Architecture Patterns) behind `auth:sanctum` + `throttle:api-read`. Frontend: one new Pinia store (`resources/js/stores/chatbot.js`, session-only, no persistence) and one new floating widget component mounted once in `AppLayout.vue` (the single layout wrapper already used by every authenticated route), following the exact Tailwind/heroicons/copy contract already locked in `17-UI-SPEC.md`.

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Intent routing / guided flow state (which step, which quick-replies to show) | Browser / Client (Pinia store + widget component) | — | D-04 makes the backend stateless; the *conversation flow* (which screen/step is showing) is pure client-side UI state, not a backend concern |
| Free-text input parsing for the few flow steps that need it (date range, amount, category/merchant term) | API / Backend | Browser / Client (basic format validation before submit) | Business rule of "is this a valid date range for this user's data" belongs server-side per existing FormRequest convention; client does only UX-level input shape validation |
| Account balances data | API / Backend (`Account` model, `HasUserScoping`) | — | Existing REST/service layer already owns this; chatbot must read through it, not duplicate |
| Upcoming payments data | API / Backend (`DashboardController::upcomingPayments` logic) | — | Existing aggregation across Loan/CreditCard/Subscription payments; must be reused, not reimplemented (see Don't Hand-Roll) |
| Monthly spending/cashflow data | API / Backend (`FinanceReportService`) | — | Existing report aggregation; chatbot must call into the service, not re-derive SQL |
| Chat message history (session) | Browser / Client (Pinia store, D-05) | — | Explicitly no backend persistence in v1 |
| Auth/user scoping | API / Backend (Sanctum + `HasUserScoping` + Policies) | — | Must not be reimplemented; chatbot endpoint sits behind the same middleware stack as every other read endpoint |
| Widget visibility/placement (global floating icon) | Browser / Client (`AppLayout.vue`) | — | D-06; single mount point covers every authenticated SPA page |

## Standard Stack

This phase intentionally introduces **no new package** (backend or frontend) — CONTEXT.md D-02 explicitly rejects BotMan/vendor NLU, and the UI spec confirms no new npm/composer dependency for the widget. `[VERIFIED: 17-UI-SPEC.md, 17-CONTEXT.md]`

### Core (existing, reused — no install needed)
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel | 12.56.0 | Backend framework, routing, validation | `[VERIFIED: composer show laravel/framework]` |
| Laravel Sanctum | 4.0 | API token auth (`auth:sanctum`) | Already gates every other `/api/v1/*` route |
| Vue 3 | 3.5.33 | Frontend SPA framework, `<script setup>` widget component | Existing convention in `resources/js/components` |
| Pinia | 3.0.4 | Session-only chat store | Existing convention (`stores/auth.js`) |
| `@heroicons/vue` 2.2.0 | icon set | Widget trigger/quick-reply icons | Locked by UI spec, already a dependency |
| Tailwind CSS 3.2.1 | styling | Widget visual styling | Locked by UI spec, already a dependency |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Hand-rolled `IntentRouter` class | BotMan / a chatbot framework | Rejected by D-02 — vendor dependency + multi-driver overhead not needed for a single web widget |
| Hand-rolled Pinia store | `pinia-plugin-persistedstate` | Rejected implicitly by D-05 — history must NOT persist across refresh; adding a persistence plugin would work against the decision |

**Installation:** None required — no new `composer.json` or `package.json` entries.

## Architecture Patterns

### System Architecture Diagram

```
[Vue SPA — any authenticated page]
        │
        ▼
[AppLayout.vue]  ──renders──▶  [ChatWidget.vue] (new, global floating trigger + panel)
        │                              │
        │                              ▼
        │                     [useChatbotStore (Pinia, new)]
        │                       - messages: [] (session-only, D-05)
        │                       - currentStep / activeIntent (client-side flow state)
        │                       - actions: selectIntent(), submitFreeText(), reset()
        │                              │
        │                              │ axios/fetch POST /api/v1/chatbot/ask
        │                              │ { intent: 'account_balances' | 'upcoming_payments' | 'monthly_spending',
        │                              │   params: {...free-text step values...} }
        │                              ▼
[routes/api.php]  auth:sanctum + throttle:api-read
        │
        ▼
[ChatbotController::ask()] (new, thin — validates intent+params, delegates)
        │
        ▼
[App\Services\Chatbot\IntentRouter] (new)
   - resolves intent string → intent handler class
   - throws / returns structured "unsupported intent" for anything outside D-07's 3 intents
        │
        ├──▶ [AccountBalancesIntent]      → Account::query() scoped by HasUserScoping → shape response
        ├──▶ [UpcomingPaymentsIntent]     → same query logic as DashboardController::upcomingPayments()
        │                                    (extracted to a shared private method or a small service, see Don't Hand-Roll)
        └──▶ [MonthlySpendingIntent]      → FinanceReportService::getTable()/getPivotData() → shape response
        │
        ▼
[JSON response] { intent, answer: {...}, quick_replies: [...] } ──▶ back to ChatWidget.vue, appended to Pinia messages[]
```

Every arrow after `AppLayout.vue` down to a handler class stays within the existing REST/service-layer architecture (`[VERIFIED: .planning/codebase/ARCHITECTURE.md]`) — no new architectural layer is introduced, only a new thin routing layer inside `app/Services/`.

### Recommended Project Structure
```
app/
├── Http/
│   ├── Controllers/Api/V1/
│   │   └── ChatbotController.php          # new — single ask() action, thin, delegates to IntentRouter
│   └── Requests/Api/
│       └── AskChatbotRequest.php          # new — validates `intent` enum + per-intent `params`
├── Services/
│   └── Chatbot/                           # new namespace, mirrors existing App\Services\* flat convention
│       ├── IntentRouter.php               # maps intent string -> handler, single entry point
│       ├── Contracts/
│       │   └── ChatIntent.php             # interface: handle(User $user, array $params): array
│       ├── Intents/
│       │   ├── AccountBalancesIntent.php
│       │   ├── UpcomingPaymentsIntent.php
│       │   └── MonthlySpendingIntent.php
│       └── Exceptions/
│           └── UnsupportedIntentException.php
resources/js/
├── stores/
│   └── chatbot.js                         # new Pinia store, session-only (D-05)
├── components/
│   └── chatbot/                           # new subdirectory, mirrors existing components/{ui,layout,reports}
│       ├── ChatWidget.vue                 # floating trigger + panel container, mounted once
│       ├── ChatMessageBubble.vue          # bot/user message bubble (per UI-SPEC color contract)
│       ├── ChatQuickReplies.vue           # button/chip row (D-02 primary nav)
│       └── ChatFreeTextInput.vue          # free-text step input (D-03, used only at specific steps)
```

**Namespace note:** `App\Services\Chatbot\*` (subdirectory under `Services/`) is chosen over a flat `App\Services\ChatbotService` because this phase needs multiple cooperating classes (router + 3 intent handlers + contract), unlike the codebase's other services which are single flat classes. This is the one place Claude's Discretion (per CONTEXT.md) applies — no existing precedent for a sub-namespaced service group exists in this repo today, so this is a new but consistent pattern (matches `Http/Requests/Api/`, `Http/Resources/Api/` which do use one level of subdirectory).

### Pattern 1: Stateless Intent Handler Contract
**What:** Every intent is a class implementing a single-method interface; the router resolves by string key and never holds conversation state.
**When to use:** For all three v1 intents; extensible for future intents without touching the router's core logic.
**Example:**
```php
// New file: app/Services/Chatbot/Contracts/ChatIntent.php
namespace App\Services\Chatbot\Contracts;

use App\Models\User;

interface ChatIntent
{
    public function key(): string;

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function handle(User $user, array $params): array;
}
```
```php
// New file: app/Services/Chatbot/Intents/AccountBalancesIntent.php
namespace App\Services\Chatbot\Intents;

use App\Models\Account;
use App\Models\User;
use App\Services\Chatbot\Contracts\ChatIntent;

class AccountBalancesIntent implements ChatIntent
{
    public function key(): string
    {
        return 'account_balances';
    }

    public function handle(User $user, array $params): array
    {
        // HasUserScoping global scope on Account already restricts to auth()->id()
        // unless user is superadmin — same behavior as AccountController::index.
        $accounts = Account::query()->where('is_active', true)->get(['id', 'name', 'type', 'balance', 'currency']);

        return [
            'accounts' => $accounts->map(fn (Account $a) => [
                'name' => $a->name,
                'balance' => (float) $a->balance,
                'currency' => $a->currency,
            ])->all(),
        ];
    }
}
```
**Source:** Pattern synthesized from existing `AccountController::index` query shape + `HasUserScoping` behavior `[VERIFIED: app/Http/Controllers/Api/V1/AccountController.php, app/Traits/HasUserScoping.php]`.

### Pattern 2: Router as a switch over a bound collection (avoid a service container tag/array-binding pattern the codebase doesn't otherwise use)
**What:** `IntentRouter` is constructed with an array of `ChatIntent` instances (or resolves them via `app()->tagged()` if the team wants extensibility), keyed by `->key()`.
**When to use:** Single point of "is this intent one of the 3 supported ones" — this is also where D-01's scope boundary is enforced (reject anything not in the allow-list, matching the UI-SPEC's out-of-scope copy: *"I can only help with balances, upcoming payments, and monthly spending right now."*).
```php
// New file: app/Services/Chatbot/IntentRouter.php
namespace App\Services\Chatbot;

use App\Models\User;
use App\Services\Chatbot\Contracts\ChatIntent;
use App\Services\Chatbot\Exceptions\UnsupportedIntentException;
use Illuminate\Support\Collection;

class IntentRouter
{
    /** @var Collection<string, ChatIntent> */
    private Collection $intents;

    /**
     * @param ChatIntent[] $intents
     */
    public function __construct(array $intents)
    {
        $this->intents = collect($intents)->keyBy(fn (ChatIntent $intent) => $intent->key());
    }

    public function route(User $user, string $intentKey, array $params): array
    {
        $intent = $this->intents->get($intentKey);

        if (! $intent) {
            throw new UnsupportedIntentException($intentKey);
        }

        return $intent->handle($user, $params);
    }
}
```
Bind in `AppServiceProvider::register()` (currently empty, `[VERIFIED: app/Providers/AppServiceProvider.php:30-33]`):
```php
$this->app->singleton(IntentRouter::class, fn () => new IntentRouter([
    new AccountBalancesIntent(),
    new UpcomingPaymentsIntent(app(SubscriptionService::class)),
    new MonthlySpendingIntent(app(FinanceReportService::class)),
]));
```

### Anti-Patterns to Avoid
- **Calling `AccountBalanceService` for balance reads:** it only has `handleCreated/Updated/Deleted(Transaction $t): void` — none of these read or return a balance. Confirmed by direct source read.
- **Reimplementing the upcoming-payments query:** `DashboardController::upcomingPayments()` currently has all the loan/credit-card/subscription-payment aggregation inline in the controller (not extracted to a service). Don't copy-paste this logic into the chatbot intent — extract it first (see Don't Hand-Roll below), or at minimum call the same models/scopes with identical filters so results never diverge from the dashboard widget the CONTEXT.md explicitly says to reuse.
- **Persisting chat history server-side "just in case":** explicitly rejected by D-05; do not create a `chat_messages` migration.
- **Building a pattern-matching/NLU layer for routing:** explicitly rejected by D-02; only free-text at specific guided-flow steps (D-03), never as the primary router input.
- **New GraphQL exposure for chat:** GraphQL is "configured but not actively used" `[VERIFIED: ARCHITECTURE.md]`; there is no reason to add a chatbot resolver there — REST-only, consistent with D-07's data sources which are all REST-backed today.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|--------------|-----|
| Upcoming payments aggregation (loan + credit-card + subscription payments merged, sorted, mapped) | A second copy of this ~70-line aggregation inside a chatbot intent | Extract `DashboardController::upcomingPayments()`'s body into a small new method on a service (e.g. `UpcomingPaymentsService::forUser(User $user, int $days): array`) callable from BOTH `DashboardController` and the new `UpcomingPaymentsIntent` | CONTEXT.md explicitly says "reusing the same data the dashboard's upcoming-payments widget already surfaces" — the current code has this logic living directly in the controller, not a service, so a literal "call the existing service" is not yet possible without a small refactor extraction first. This extraction should be a Phase 17 task, not a new hand-rolled duplicate. |
| Monthly spending/cashflow numbers | New SQL aggregation queries for "this month's income/expenses/net" | `FinanceReportService::getTable($year, $userId)` (already returns per-month earnings/expenses/net for the whole year — the chatbot intent just picks the current month's row) OR `DashboardController`'s private `getMonthlyCashflowChartData()` shape (income/expenses/payments/net) if that richer breakdown is preferred — also currently controller-private and needs the same small extraction treatment as upcoming payments if reused as-is | Both exist and are tested via `FinanceReportApiTest`/`DashboardApiTest`; hand-rolling a third aggregation risks numbers disagreeing with the dashboard/report the user already trusts |
| User scoping / cross-user leak prevention | Custom `where('user_id', ...)` checks inside chatbot intents | `HasUserScoping` global scope (already applied to `Account`, and to the models `Transaction`/`Loan`/`CreditCard`/`Subscription` that back the other two intents) | This is the exact reuse CONTEXT.md's "Claude's Discretion" section requires — "as long as existing user-scoping is reused, not reimplemented" |
| Auth / token validation | Custom middleware or header parsing | `auth:sanctum` middleware, identical to every other read route | No reason to deviate; Sanctum guard already resolves `$request->user()` |
| Rate limiting | Custom throttling for the chat endpoint | `throttle:api-read` (100 req/min per user/IP, `[VERIFIED: app/Providers/AppServiceProvider.php:40-42]`) | The chat endpoint is read-only per D-01; it belongs in the existing read-limiter group, not a new bespoke limiter |

**Key insight:** Every one of D-07's three intents already has a proven, tested data path in this codebase. The actual engineering work of Phase 17 is *extraction and thin adaptation*, not new query/business logic — the two "reuse" targets that are currently controller-embedded (upcoming payments, and optionally the richer monthly-cashflow numbers) need a small "extract private method to service" refactor task before the chatbot can call them without duplicating code. Plan for this refactor explicitly rather than letting the chatbot intent silently re-derive the same SQL.

## Common Pitfalls

### Pitfall 1: Silently duplicating upcoming-payments logic instead of extracting it
**What goes wrong:** A chatbot intent re-writes its own version of the loan/credit-card/subscription merge query. Weeks later the dashboard widget's business rules change (e.g. a new payment type is added, or the `days` default changes) and the chatbot's answer silently disagrees with the dashboard.
**Why it happens:** The existing logic lives inline in `DashboardController::upcomingPayments()`, not in a reusable service, so the path of least resistance for the chatbot intent is to copy it rather than extract it.
**How to avoid:** Plan an explicit extraction task (move the query/mapping logic to a small service method) as a Wave 0/1 task, then have both the controller and the chatbot intent call it.
**Warning signs:** A `UpcomingPaymentsIntent.php` with its own `LoanPayment::query()->...` block that looks "similar but not identical" to `DashboardController`.

### Pitfall 2: Treating `AccountBalanceService` as a balance-read API
**What goes wrong:** Someone injects `AccountBalanceService` into `AccountBalancesIntent` expecting a `getBalance($account)` method; it doesn't exist. The service is a `Transaction`-event listener, not a query service.
**Why it happens:** The name is misleading, and CONTEXT.md's own `code_context` section describes it as backing the intent — that description is imprecise (this research corrects it).
**How to avoid:** Read balances directly from the `Account` model (`balance` column), scoped by `HasUserScoping`, exactly as `AccountController::index` already does.
**Warning signs:** A constructor type-hinting `AccountBalanceService` inside an intent handler that never receives a `Transaction`.

### Pitfall 3: Free-text step validation leaking cross-user data via loose filters
**What goes wrong:** D-03's free-text steps (custom date range, amount filter, category/merchant search) get passed straight into a query without the same `HasUserScoping`/ownership checks the rest of the app enforces, because they're "just a chat filter."
**Why it happens:** Chat input feels lower-stakes than a form, so validation gets skipped.
**How to avoid:** Route every free-text param through a proper `AskChatbotRequest` FormRequest with `rules()` per intent (date format, numeric bounds, string length) — same discipline as `StoreTransactionRequest` etc. — and never bypass model global scopes for chatbot queries.
**Warning signs:** Raw `$request->input('params')` passed unvalidated into a query builder.

### Pitfall 4: Chatbot answers implying confidence the project doesn't currently claim (violates D-01)
**What goes wrong:** A free-text step lets a user type "credit card" or "loans" and the intent router (or a future loosening of scope) tries to answer using structural-only data, misrepresenting the Phase 13/16 confidence boundary.
**Why it happens:** Natural temptation to "just answer" rather than redirect, especially once the free-text input exists for other purposes.
**How to avoid:** The `IntentRouter`'s allow-list of exactly 3 keys is the enforcement point — anything else (including free-text that *names* an out-of-scope domain) must hit the UI-SPEC's locked out-of-scope copy path, not a best-effort guess. This is explicitly a UI-SPEC requirement already (`"I can only help with balances, upcoming payments, and monthly spending right now."`).
**Warning signs:** Any code path where a free-text string is matched against domain keywords and used to fetch loan/credit-card/subscription/budget data.

### Pitfall 5: Widget mounted per-view instead of once in the shared layout
**What goes wrong:** `ChatWidget.vue` gets imported into `DashboardView.vue` "to start" and then forgotten in other views, breaking D-06's "every page" requirement, or worse, mounted multiple times causing duplicate floating triggers if later added to more than one place.
**Why it happens:** Views are per-domain; there's no existing precedent in this codebase for a cross-cutting global UI element (toasts are the closest precedent, and they're already correctly placed once in `AppLayout.vue`).
**How to avoid:** Mount `<ChatWidget />` exactly once, inside `AppLayout.vue`, next to the existing toast `<transition-group>` block — this is the layout every authenticated route renders through (`[VERIFIED: resources/js/components/layout/AppLayout.vue, resources/js/router/index.js — all authenticated routes have meta.requiresAuth: true and no route bypasses AppLayout]`). Note: verify whether `AppLayout.vue` is used by ALL authenticated views or if some views (e.g. auth-adjacent ones) use `AuthLayout.vue` instead — `AuthLayout.vue` is presumably for login/register (unauthenticated), so it correctly should NOT show the widget.

## Code Examples

### REST endpoint wiring (routes/api.php)
```php
// Add to app/Http/Controllers/Api/V1/, imported at top of routes/api.php
use App\Http\Controllers\Api\V1\ChatbotController;

// Inside the existing "Read endpoints — 100 req/min" group:
Route::post('chatbot/ask', [ChatbotController::class, 'ask']);
```
**Source:** Matches exact existing convention in `routes/api.php` read-group (`auth:sanctum` + `throttle:api-read`) `[VERIFIED: routes/api.php]`. POST is used (not GET) because the request carries a structured `intent` + `params` body, consistent with how other "read with complex input" endpoints in this app are still sometimes POST-shaped when params are non-trivial — however GET with query params (e.g. `?intent=account_balances&params[...]`) is also viable and arguably more RESTfully "read"; **this is a discretionary decision for the planner** — either is consistent with the read-throttle group since it's still non-mutating. Recommend POST for simplicity of nested `params` payloads.

### Controller (thin, matches existing pattern)
```php
// New: app/Http/Controllers/Api/V1/ChatbotController.php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AskChatbotRequest;
use App\Services\Chatbot\IntentRouter;
use Illuminate\Http\JsonResponse;

class ChatbotController extends Controller
{
    public function __construct(private readonly IntentRouter $router) {}

    public function ask(AskChatbotRequest $request): JsonResponse
    {
        $answer = $this->router->route(
            $request->user(),
            $request->validated('intent'),
            $request->validated('params', []),
        );

        return response()->json(['data' => $answer]);
    }
}
```
**Source:** Mirrors thin-controller pattern from `app/Http/Controllers/Api/V1/AccountController.php` and `FinanceReportController.php` `[VERIFIED: codebase read]`.

### Pinia store skeleton (session-only, D-05)
```javascript
// New: resources/js/stores/chatbot.js
import { ref } from 'vue';
import { defineStore } from 'pinia';

export const useChatbotStore = defineStore('chatbot', () => {
    const isOpen = ref(false);
    const messages = ref([]); // session-only — intentionally NOT persisted to localStorage (D-05)
    const activeStep = ref('intent_select'); // client-side flow state only (D-04)

    function open() { isOpen.value = true; }
    function close() { isOpen.value = false; }
    function reset() { messages.value = []; activeStep.value = 'intent_select'; }

    function pushMessage(message) { messages.value.push(message); }

    return { isOpen, messages, activeStep, open, close, reset, pushMessage };
});
```
**Source:** Follows exact Composition-API Pinia pattern from `resources/js/stores/auth.js` (`ref`/`computed` inside `defineStore('name', () => {...})`), minus the `localStorage` persistence calls that `auth.js` uses — deliberately omitted per D-05 `[VERIFIED: resources/js/stores/auth.js]`.

### Widget mount point
```vue
<!-- resources/js/components/layout/AppLayout.vue — add one line -->
<script setup>
import AppNavbar from './AppNavbar.vue';
import ChatWidget from '@/components/chatbot/ChatWidget.vue'; // new
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
        <!-- existing toast transition-group ... -->
        <ChatWidget />
    </div>
</template>
```
**Source:** `[VERIFIED: resources/js/components/layout/AppLayout.vue — full file read]`.

## State of the Art

No "old vs new approach" table applies here — this is greenfield hand-rolled code within a stable existing stack (Laravel 12, Vue 3.5), not a migration off a deprecated pattern.

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | POST `/api/v1/chatbot/ask` (single endpoint, body-driven intent+params) is the right endpoint shape vs. GET or per-intent endpoints (`/chatbot/balances`, `/chatbot/upcoming-payments`, etc.) | Code Examples / REST endpoint wiring | Low risk — either shape fits existing REST conventions and the `throttle:api-read` group; if the planner prefers 3 separate GET endpoints instead of 1 POST router endpoint, the `IntentRouter` class still works internally, only the controller/route layer changes. Flagging so the planner explicitly decides rather than defaulting silently. |
| A2 | `App\Services\Chatbot\*` (new subdirectory under `Services/`) is an acceptable structural departure from the codebase's flat `Services/{Name}Service.php` convention | Architecture Patterns / Recommended Project Structure | Low-medium — CONTEXT.md explicitly leaves "exact backend structure... naming, file layout" to Claude's Discretion, so this isn't really a risk, but a reviewer unfamiliar with the rationale might question the new subdirectory pattern; the research note explaining the parallel to `Http/Requests/Api/`, `Http/Resources/Api/` addresses this. |
| A3 | `AppLayout.vue` is rendered by every authenticated route with no exceptions (i.e., no authenticated view opts out of the shared layout) | Common Pitfalls / Pitfall 5 | Medium — if some authenticated view does NOT go through `AppLayout.vue`, the widget would silently be missing on that page, violating D-06. Recommend the planner add a quick verification task (grep all Views for direct layout usage, or confirm layout is applied at router/App.vue level) before finalizing the mount point — this research read `router/index.js`'s meta flags and `AppLayout.vue`'s content but did not exhaustively trace how layout selection happens (e.g., via router meta + a layout-switching wrapper vs. each view importing its own layout). |

**If this table is empty:** N/A — see entries above. All three are implementation-shape decisions/verification gaps, not speculative technical claims; nothing here is presented as fact that wasn't read directly from the repo.

## Open Questions

1. **Does every authenticated Vue view actually render through `AppLayout.vue`?**
   - What we know: `AppLayout.vue` exists, contains navbar + slot + toast overlay, and is clearly the intended "authenticated shell." `App.vue` was not read in full during this research pass.
   - What's unclear: Whether view components each explicitly wrap themselves in `<AppLayout>`, or whether a router-level/App.vue-level mechanism applies it automatically to all `requiresAuth: true` routes.
   - Recommendation: Planner should include a quick verification task (read `App.vue` and 1-2 view files, e.g. `DashboardView.vue`, `AccountsView.vue`) to confirm the layout-application mechanism before writing the widget-mount task, to avoid a false assumption that one edit in `AppLayout.vue` is sufficient.

2. **Should the chatbot endpoint be one POST router endpoint or three intent-specific GET endpoints?**
   - What we know: Both fit existing `throttle:api-read` + `auth:sanctum` conventions equally well.
   - What's unclear: No CONTEXT.md decision locks this; it's within Claude's Discretion per "exact backend structure... naming, file layout."
   - Recommendation: Default to the single POST `chatbot/ask` shape recommended above (simpler frontend call, single throttle bucket, matches the "router" framing from the phase name) unless the planner has a reason to prefer REST-per-intent purity.

3. **Should upcoming-payments and monthly-cashflow extraction (moving controller-inline logic to a service method) be its own Wave 0 task, or done inline while building the chatbot intent?**
   - What we know: Both `DashboardController::upcomingPayments()` and its private `getMonthlyCashflowChartData()` helper currently hold business logic directly in the controller, not in `app/Services/`.
   - What's unclear: Whether extracting this is considered "in scope" for Phase 17 (small refactor to avoid duplication) or should be deferred/treated as tech debt the phase works around (e.g. by having both places call a duplicated-but-isolated private method, accepting temporary duplication).
   - Recommendation: Treat the extraction as an explicit, separately-testable Wave 0/1 task — it is required to satisfy CONTEXT.md's "reuse, don't reimplement" instruction and the Don't-Hand-Roll table above; skipping it means either a silent duplicate or a chatbot that call the controller class directly (an anti-pattern in this codebase's layering).

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP | Backend intent router, controller, tests | Yes | 8.4.23 `[VERIFIED: php -v]` | — |
| Laravel | Routing, Sanctum, service container | Yes | 12.56.0 `[VERIFIED: composer show]` | — |
| Node/npm | Frontend build (Vite) for widget component | Assumed yes (existing project builds today) | not re-verified this session | — |
| JS test runner (Vitest/Jest) | Frontend automated tests for widget/store | **No** — `package.json` has no test script, no `vitest`/`jest` devDependency, no `*.test.js` files found anywhere in repo `[VERIFIED: package.json scripts block, repo-wide find for *.test.js]` | — | See Validation Architecture / Wave 0 Gaps below — this blocks automated frontend test coverage unless a framework is installed first |
| PHPUnit | Backend Feature/Unit tests for chatbot endpoint/intents | Yes | 11.5.3 `[VERIFIED: STACK.md, existing tests/Feature/Api/*Test.php]` | — |

**Missing dependencies with no fallback:**
- Frontend JS test framework (Vitest recommended for Vite projects) — there is currently zero automated frontend test coverage in this repo for ANY existing component, not just chatbot-specific. This is a pre-existing gap, not something Phase 17 introduces, but per this project's proof-first culture (Phase 13/16 precedent: untested code gets downgraded to "structural-only"), the Pinia store and widget component logic should not be left entirely untested. Backend intent logic (the part with actual business rules — data shaping, scope enforcement, out-of-scope rejection) CAN be fully covered by PHPUnit Feature tests against the new endpoint, which does not require a new dependency. Frontend coverage is a secondary, discretionary concern the planner should explicitly decide on (install Vitest now vs. defer, with the tradeoff being self-built widget interaction logic going untested).

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework (backend) | PHPUnit 11.5.3, Laravel Feature test helpers (`Sanctum::actingAs`, `RefreshDatabase`) `[VERIFIED: phpunit.xml, tests/Feature/Api/*Test.php]` |
| Framework (frontend) | None configured — see Environment Availability gap above |
| Config file | `phpunit.xml` (backend) |
| Quick run command | `php artisan test --filter=Chatbot` |
| Full suite command | `php artisan test` |

### Phase Requirements → Test Map
No formal REQ-IDs exist for this phase (per phase description: "predates formal requirement-ID mapping"). Test map below is derived from CONTEXT.md decisions (D-01 through D-08) instead.

| Decision | Behavior | Test Type | Automated Command | File Exists? |
|----------|----------|-----------|-------------------|-------------|
| D-01 | Chatbot rejects any intent outside the 3 validated ones (returns out-of-scope response, never queries structural-only domains) | feature | `php artisan test --filter=test_chatbot_rejects_unsupported_intent` | ❌ Wave 0 |
| D-07.1 | Account balances intent returns only the authenticated user's own accounts, correct balance values | feature | `php artisan test --filter=test_chatbot_account_balances_intent_returns_scoped_accounts` | ❌ Wave 0 |
| D-07.1 (cross-user) | Account balances intent never leaks another user's account data | feature | `php artisan test --filter=test_chatbot_account_balances_intent_is_user_scoped` | ❌ Wave 0 |
| D-07.2 | Upcoming payments intent returns the same data shape/values as `DashboardController::upcomingPayments` for an identical fixture | feature | `php artisan test --filter=test_chatbot_upcoming_payments_matches_dashboard` | ❌ Wave 0 |
| D-07.3 | Monthly spending intent returns correct current-month earnings/expenses/net figures | feature | `php artisan test --filter=test_chatbot_monthly_spending_intent_returns_correct_totals` | ❌ Wave 0 |
| Auth boundary | Unauthenticated request to `/api/v1/chatbot/ask` returns 401 | feature | `php artisan test --filter=test_chatbot_ask_requires_authentication` | ❌ Wave 0 |
| Rate limiting | `chatbot/ask` is subject to `throttle:api-read`, not a separate/looser limiter | feature (or manual route-list assertion) | `php artisan route:list --path=chatbot` (manual verification) + optional throttle test | ❌ Wave 0 |
| IntentRouter unit behavior | Router throws `UnsupportedIntentException` for unknown keys; dispatches to correct handler for known keys | unit | `php artisan test --filter=IntentRouterTest` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --filter=Chatbot` (backend) — no frontend equivalent exists yet
- **Per wave merge:** `php artisan test` (full backend suite, ensures no regression in `DashboardApiTest`/`FinanceReportApiTest` if extraction refactor touches shared logic)
- **Phase gate:** Full PHPUnit suite green before `/gsd-verify-work`. Frontend widget/store: manual verification checklist against `17-UI-SPEC.md` copy/color/spacing contract (no automated frontend gate exists project-wide).

### Wave 0 Gaps
- [ ] `tests/Feature/Api/ChatbotApiTest.php` — covers D-01, D-07.1, D-07.2, D-07.3, auth boundary above
- [ ] `tests/Unit/Services/Chatbot/IntentRouterTest.php` — covers router dispatch/unsupported-intent behavior in isolation
- [ ] Extraction refactor tests: if `UpcomingPaymentsIntent` and `DashboardController::upcomingPayments()` are unified behind a shared service method, add/extend `tests/Feature/Api/DashboardApiTest.php` assertions to confirm no regression, and mirror equivalent coverage in the new chatbot test file
- [ ] Frontend test framework: none installed — planner should explicitly decide whether to introduce Vitest for this phase's new Pinia store/widget or explicitly descope frontend automated tests as a known, documented gap (consistent with the rest of this project's current frontend test coverage, which is also zero)

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | Yes | `auth:sanctum` middleware, identical to every other protected `/api/v1/*` route — no new auth mechanism |
| V3 Session Management | Yes (frontend-only) | D-05's session-only Pinia store holds no auth material, only chat message text/answers — no session token handling introduced here beyond what Sanctum already does |
| V4 Access Control | Yes | `HasUserScoping` global scope + existing Policies enforce per-user data isolation; chatbot intents must query through scoped models, never `withoutGlobalScopes()` unless replicating an already-audited superadmin bypass pattern (and even then, only if a chatbot-for-superadmin use case is in scope, which D-01/D-07 do not mention) |
| V5 Input Validation | Yes | New `AskChatbotRequest` FormRequest must validate `intent` against `Rule::in([...the 3 keys...])` and per-intent `params` (date format for date-range free-text, numeric bounds for amount free-text, string length/allowed-chars for category/merchant search) — same FormRequest discipline as `StoreCreditCardRequest` etc. |
| V6 Cryptography | No | No new secrets, tokens, or crypto operations introduced by this phase |

### Known Threat Patterns for this stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Cross-user data disclosure via a chatbot intent that forgets `HasUserScoping` (e.g. queries `LoanPayment`/`CreditCardPayment`/`Subscription` directly without the same `whereHas('loan', fn ($q) => $q->where('user_id', ...))` guard that `DashboardController::upcomingPayments()` currently applies manually — note these particular models do NOT appear to use `HasUserScoping` directly, ownership is checked via `whereHas` on the parent) | Information Disclosure | Reuse the exact ownership-check pattern from `DashboardController::upcomingPayments()` (or the extracted shared service) rather than re-deriving; add an explicit cross-user Feature test (D-07.2 test above) |
| Free-text injection into raw SQL (e.g. category/merchant search term interpolated unsafely) | Tampering | Use Eloquent/query-builder parameter binding as the rest of the app does (`FinanceReportService` already parameterizes via `DB::table()->where(...)` with bound values, not string concatenation) — never build raw SQL strings from chat free-text |
| Chat endpoint used as a scope-escalation vector by naming a structural-only or another-user's-data intent via crafted `intent` string | Elevation of Privilege / Information Disclosure | `IntentRouter`'s allow-list (`Rule::in()` at the FormRequest layer + the router's own `Collection::get()` returning null for unknown keys) is a hard boundary — reject-by-default, not pattern-match/best-effort |
| Rate-limit bypass / abuse of a chat endpoint to enumerate data via repeated free-text queries | Denial of Service | `throttle:api-read` (100/min per user/IP) already applies once the route is added to the existing read group — no additional action needed, but confirm the route is placed inside that middleware group, not left ungrouped |

## Sources

### Primary (HIGH confidence — direct codebase reads this session)
- `app/Services/AccountBalanceService.php` — confirmed this is a mutation-only service, not a balance-read service
- `app/Traits/HasUserScoping.php` — confirmed global scope + auto-fill behavior
- `routes/api.php` — confirmed route group structure, throttle middleware naming
- `app/Http/Controllers/Api/V1/AccountController.php` — confirmed balance-read pattern via `Account` model
- `app/Http/Controllers/Api/V1/DashboardController.php` — confirmed upcoming-payments aggregation is controller-inline, not service-extracted
- `app/Services/FinanceReportService.php` — confirmed `getTable()`/`getPivotData()` as the monthly spending/cashflow data source
- `app/Http/Controllers/Api/V1/FinanceReportController.php` — confirmed controller→service call pattern
- `app/Providers/AppServiceProvider.php` — confirmed `api-read` (100/min) and `api-write` (20/min) rate limiter definitions
- `resources/js/app.js`, `resources/js/router/index.js`, `resources/js/components/layout/AppLayout.vue` — confirmed SPA bootstrap, route meta flags, and the single shared authenticated layout
- `resources/js/stores/auth.js` — confirmed Pinia Composition-API store pattern to mirror
- `tests/Feature/Api/DashboardApiTest.php` — confirmed PHPUnit Feature test conventions (`Sanctum::actingAs`, `RefreshDatabase`, `getJson`/`assertJsonPath`)
- `package.json` scripts block + repo-wide search — confirmed no frontend JS test framework is configured anywhere in this project
- `composer show laravel/framework`, `php -v` — confirmed Laravel 12.56.0 and PHP 8.4.23 runtime versions
- `.planning/phases/17-custom-read-only-finance-chatbot-engine/17-CONTEXT.md`, `17-UI-SPEC.md` — user-locked decisions and UI contract
- `.planning/codebase/ARCHITECTURE.md`, `STACK.md`, `CONVENTIONS.md`, `STRUCTURE.md`, `CONCERNS.md` — codebase maps

### Secondary / Tertiary
None used — no external library research was needed for this phase (deliberately no vendor dependency per D-02).

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — no new dependencies; all reused libraries verified via `composer show`/existing `package.json`
- Architecture: HIGH for backend service reuse targets and REST conventions (directly read source); MEDIUM for the exact widget-mount-point completeness (Open Question 1 — not exhaustively traced whether ALL authenticated views route through `AppLayout.vue`)
- Pitfalls: HIGH — derived from direct contradictions found in source code during this session (e.g. `AccountBalanceService` misconception), not speculative
- Security: HIGH for reuse of existing auth/scoping mechanisms; MEDIUM for the specific ownership-check pattern needed for loan/credit-card/subscription payment models since they use `whereHas` ownership checks rather than `HasUserScoping` directly — planner/implementer should re-verify this per-model when writing `UpcomingPaymentsIntent`

**Research date:** 2026-08-05
**Valid until:** 30 days (stable internal codebase, no external dependency drift risk since no new packages are introduced)
