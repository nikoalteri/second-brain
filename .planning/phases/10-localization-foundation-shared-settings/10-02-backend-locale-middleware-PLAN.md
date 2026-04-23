---
phase: 10-localization-foundation-shared-settings
plan: 02
type: execute
wave: 2
depends_on: [10-01]
files_modified:
  - tests/Feature/Localization/LocaleMiddlewareTest.php
  - app/Http/Middleware/SetLocaleFromUserPreference.php
  - bootstrap/app.php
  - routes/api.php
  - app/Providers/Filament/AdminPanelProvider.php
autonomous: true
requirements: [I18N-02, I18N-04]
must_haves:
  truths:
    - Authenticated API requests pick up the saved user language automatically
    - Missing or invalid saved values fall back to English without breaking request handling
    - Filament request handling is wired for persistent locale resolution instead of config-only defaults
  artifacts:
    - path: app/Http/Middleware/SetLocaleFromUserPreference.php
      provides: request-time locale resolution from the shared user setting
    - path: tests/Feature/Localization/LocaleMiddlewareTest.php
      provides: regression coverage for locale resolution and fallback behavior
    - path: app/Providers/Filament/AdminPanelProvider.php
      provides: persistent middleware hook for backend requests
  key_links:
    - from: routes/api.php
      to: app/Http/Middleware/SetLocaleFromUserPreference.php
      via: authenticated middleware groups
      pattern: set.locale|SetLocaleFromUserPreference
    - from: app/Providers/Filament/AdminPanelProvider.php
      to: app/Http/Middleware/SetLocaleFromUserPreference.php
      via: persistent panel middleware
      pattern: isPersistent
    - from: app/Http/Middleware/SetLocaleFromUserPreference.php
      to: app/Support/Localization/SupportedLocales.php
      via: locale normalization
      pattern: SupportedLocales
---

<objective>
Apply the shared per-user language to backend request handling with safe English fallback.

Purpose: make authenticated API and Filament requests honor the saved locale before Phase 11/12 translation rollout begins.
Output: locale middleware, route/panel registration, and middleware regression tests.
</objective>

<execution_context>
@~/.copilot/get-shit-done/workflows/execute-plan.md
@~/.copilot/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/ROADMAP.md
@.planning/STATE.md
@.planning/REQUIREMENTS.md
@.planning/phases/10-localization-foundation-shared-settings/10-RESEARCH.md
@.planning/phases/10-localization-foundation-shared-settings/10-VALIDATION.md
@app/Support/Localization/SupportedLocales.php
@app/Providers/Filament/AdminPanelProvider.php
@bootstrap/app.php
@routes/api.php

<interfaces>
From app/Support/Localization/SupportedLocales.php (created in 10-01):
- appLocale(?string $value): string
- browserLocale(string $appLocale): string

From app/Models/User.php:
- resolvedSettings(): array
</interfaces>
</context>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Write failing backend locale middleware tests</name>
  <files>tests/Feature/Localization/LocaleMiddlewareTest.php</files>
  <read_first>
    - .planning/phases/10-localization-foundation-shared-settings/10-VALIDATION.md
    - bootstrap/app.php
    - routes/api.php
    - app/Providers/Filament/AdminPanelProvider.php
  </read_first>
  <behavior>
    - Test 1: an authenticated user with `language=it` sees `app()->getLocale()` resolve to `it` inside a middleware-protected request.
    - Test 2: missing or invalid saved values resolve to `en`.
    - Test 3: number/date helpers used inside the same request do not throw and use the resolved locale path.
  </behavior>
  <action>Create the missing Wave 0 file `tests/Feature/Localization/LocaleMiddlewareTest.php`. Register a test-only JSON route inside the test class that returns the current locale plus one formatted number/date sample from inside the middleware pipeline so the behavior is observable without adding debug routes to production.</action>
  <acceptance_criteria>
    - `grep -q "class LocaleMiddlewareTest" tests/Feature/Localization/LocaleMiddlewareTest.php`
    - `grep -q "app()->getLocale" tests/Feature/Localization/LocaleMiddlewareTest.php`
    - `grep -q "language" tests/Feature/Localization/LocaleMiddlewareTest.php`
  </acceptance_criteria>
  <verify>
    <automated>php artisan test tests/Feature/Localization/LocaleMiddlewareTest.php --stop-on-failure</automated>
  </verify>
  <done>The middleware contract is captured in failing automated tests before implementation.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: Implement locale middleware and wire it into API + Filament</name>
  <files>app/Http/Middleware/SetLocaleFromUserPreference.php, bootstrap/app.php, routes/api.php, app/Providers/Filament/AdminPanelProvider.php</files>
  <read_first>
    - app/Providers/Filament/AdminPanelProvider.php
    - bootstrap/app.php
    - routes/api.php
    - app/Support/Localization/SupportedLocales.php
    - .planning/phases/10-localization-foundation-shared-settings/10-RESEARCH.md
  </read_first>
  <action>Create `SetLocaleFromUserPreference` to resolve the authenticated user's saved language through `SupportedLocales::appLocale()`, then call `App::setLocale()`, `Number::useLocale()`, and `Carbon::setLocale()`. Register a middleware alias in `bootstrap/app.php`, apply it after `auth:sanctum` on auth/read/write API groups, and add the middleware to the Filament panel with `isPersistent: true` so Livewire requests keep the same locale. Do not add a second session-only locale source and do not run the middleware before authentication.</action>
  <acceptance_criteria>
    - `grep -q "class SetLocaleFromUserPreference" app/Http/Middleware/SetLocaleFromUserPreference.php`
    - `grep -q "set.locale" bootstrap/app.php`
    - `grep -q "set.locale" routes/api.php`
    - `grep -q "SetLocaleFromUserPreference" app/Providers/Filament/AdminPanelProvider.php`
    - `grep -q "isPersistent" app/Providers/Filament/AdminPanelProvider.php`
  </acceptance_criteria>
  <verify>
    <automated>php artisan test tests/Feature/Localization/LocaleMiddlewareTest.php --stop-on-failure</automated>
  </verify>
  <done>Authenticated backend request pipelines now resolve locale from the shared user preference and safely fall back to English.</done>
</task>

</tasks>

<verification>
Run `php artisan test tests/Feature/Localization/LocaleMiddlewareTest.php --stop-on-failure`.
</verification>

<success_criteria>
- API requests use the saved locale after authentication
- English fallback still works for users without a valid saved preference
- Filament is wired to keep locale resolution across persistent requests
</success_criteria>

<output>
After completion, create `.planning/phases/10-localization-foundation-shared-settings/10-02-SUMMARY.md`
</output>
