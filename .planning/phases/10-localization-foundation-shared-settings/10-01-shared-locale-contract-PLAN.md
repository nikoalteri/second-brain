---
phase: 10-localization-foundation-shared-settings
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - tests/Feature/Api/AuthApiTest.php
  - tests/Feature/Localization/FallbackBehaviorTest.php
  - app/Support/Localization/SupportedLocales.php
  - app/Services/UserSettingsService.php
  - app/Models/UserSetting.php
  - app/Models/User.php
  - app/Http/Requests/Api/UpdateUserSettingsRequest.php
  - app/Http/Controllers/Api/V1/UserSettingsController.php
autonomous: true
requirements: [I18N-01, I18N-02, I18N-03]
must_haves:
  truths:
    - Existing users without a saved language resolve to English automatically
    - Persisted language values are normalized to exactly `en` or `it`
    - Frontend and backend callers share one settings write path for `user_settings.language`
  artifacts:
    - path: app/Support/Localization/SupportedLocales.php
      provides: one canonical whitelist and locale-mapping helper
    - path: app/Services/UserSettingsService.php
      provides: shared persistence path for per-user settings updates
    - path: tests/Feature/Localization/FallbackBehaviorTest.php
      provides: regression coverage for whitelist and English fallback behavior
  key_links:
    - from: app/Models/User.php
      to: app/Support/Localization/SupportedLocales.php
      via: resolved settings normalization
      pattern: SupportedLocales|language
    - from: app/Http/Controllers/Api/V1/UserSettingsController.php
      to: app/Services/UserSettingsService.php
      via: controller delegation
      pattern: UserSettingsService
    - from: app/Http/Requests/Api/UpdateUserSettingsRequest.php
      to: app/Support/Localization/SupportedLocales.php
      via: validation whitelist
      pattern: en|it|SupportedLocales
---

<objective>
Freeze the canonical locale contract and shared settings write path before any middleware or UI wiring.

Purpose: make `user_settings.language` the only persisted preference source for both surfaces, with safe English fallback for old or invalid data.
Output: locale helper, shared settings service, hardened auth/settings contract, and fallback tests.
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
@app/Models/UserSetting.php
@app/Models/User.php
@app/Http/Controllers/Api/V1/UserSettingsController.php
@app/Http/Requests/Api/UpdateUserSettingsRequest.php
@tests/Feature/Api/AuthApiTest.php

<interfaces>
From app/Models/UserSetting.php:
- KEY_LANGUAGE = 'language'
- DEFAULTS['language'] = 'en'
- optionsFor(string $key): array
- normalizeValue(string $key, ?string $value): string

From app/Models/User.php:
- resolvedSettings(): array
- toFrontendPayload(): array

From app/Http/Controllers/Api/V1/UserSettingsController.php:
- update(UpdateUserSettingsRequest $request): JsonResponse
</interfaces>
</context>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Write failing locale-contract and fallback tests</name>
  <files>tests/Feature/Localization/FallbackBehaviorTest.php, tests/Feature/Api/AuthApiTest.php</files>
  <read_first>
    - .planning/phases/10-localization-foundation-shared-settings/10-RESEARCH.md
    - .planning/phases/10-localization-foundation-shared-settings/10-VALIDATION.md
    - app/Models/UserSetting.php
    - app/Models/User.php
    - tests/Feature/Api/AuthApiTest.php
  </read_first>
  <behavior>
    - Test 1: a user with no saved language row still receives `settings.language = en` from the auth payload.
    - Test 2: a legacy or invalid stored language value is normalized back to `en`, not echoed through the API.
    - Test 3: `/api/v1/auth/settings` rejects any language outside `en` and `it`.
  </behavior>
  <action>Create the missing Wave 0 file `FallbackBehaviorTest.php` and extend `AuthApiTest.php` so Phase 10 has explicit coverage for I18N-01/I18N-02/I18N-03. Keep the persistence contract on short app locale codes only; do not introduce `en-US`, `it-IT`, session-only values, or any second preference store.</action>
  <acceptance_criteria>
    - `grep -q "class FallbackBehaviorTest" tests/Feature/Localization/FallbackBehaviorTest.php`
    - `grep -q "settings.language" tests/Feature/Api/AuthApiTest.php`
    - `grep -q "en" tests/Feature/Localization/FallbackBehaviorTest.php`
    - `grep -q "it" tests/Feature/Localization/FallbackBehaviorTest.php`
  </acceptance_criteria>
  <verify>
    <automated>php artisan test tests/Feature/Localization/FallbackBehaviorTest.php --stop-on-failure</automated>
  </verify>
  <done>Fallback and whitelist expectations are captured in failing automated tests before implementation starts.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: Implement the canonical supported-locale contract</name>
  <files>app/Support/Localization/SupportedLocales.php, app/Models/UserSetting.php, app/Models/User.php, app/Http/Requests/Api/UpdateUserSettingsRequest.php</files>
  <read_first>
    - app/Models/UserSetting.php
    - app/Models/User.php
    - app/Http/Requests/Api/UpdateUserSettingsRequest.php
    - .planning/phases/10-localization-foundation-shared-settings/10-RESEARCH.md
  </read_first>
  <action>Create `SupportedLocales` as the one canonical source for `en`/`it`, English fallback, and browser-locale mapping. Update `UserSetting` so language options and normalization delegate to that helper, keep `UserSetting::DEFAULTS['language'] = 'en'`, and ensure `User::resolvedSettings()` always returns normalized values for auth payloads. Update request validation to whitelist only `en` and `it`. Do not persist browser locales or create backend-only locale fields.</action>
  <acceptance_criteria>
    - `grep -q "class SupportedLocales" app/Support/Localization/SupportedLocales.php`
    - `grep -q "EN = 'en'" app/Support/Localization/SupportedLocales.php`
    - `grep -q "IT = 'it'" app/Support/Localization/SupportedLocales.php`
    - `grep -q "DEFAULTS" app/Models/UserSetting.php`
  </acceptance_criteria>
  <verify>
    <automated>php artisan test tests/Feature/Localization/FallbackBehaviorTest.php --stop-on-failure && php artisan test tests/Feature/Api/AuthApiTest.php --filter=language --stop-on-failure</automated>
  </verify>
  <done>The codebase has one reusable locale whitelist and every resolved/persisted language value is constrained to `en|it` with English fallback.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 3: Extract one shared user-settings write service</name>
  <files>app/Services/UserSettingsService.php, app/Http/Controllers/Api/V1/UserSettingsController.php, tests/Feature/Api/AuthApiTest.php</files>
  <read_first>
    - app/Http/Controllers/Api/V1/UserSettingsController.php
    - app/Models/User.php
    - app/Models/UserSetting.php
    - tests/Feature/Api/AuthApiTest.php
  </read_first>
  <action>Extract the `user_settings` upsert/restore loop into `UserSettingsService` so both the SPA controller and the upcoming Filament profile page use the same persistence path for I18N-03. Keep the existing `/api/v1/auth/settings` route and JSON payload shape unchanged. The service should normalize values before save, reload the user's settings, and avoid any duplicate locale storage.</action>
  <acceptance_criteria>
    - `grep -q "class UserSettingsService" app/Services/UserSettingsService.php`
    - `grep -q "UserSettingsService" app/Http/Controllers/Api/V1/UserSettingsController.php`
    - `grep -q "auth/settings" tests/Feature/Api/AuthApiTest.php`
  </acceptance_criteria>
  <verify>
    <automated>php artisan test tests/Feature/Api/AuthApiTest.php --stop-on-failure && php artisan test tests/Feature/Localization/FallbackBehaviorTest.php --stop-on-failure</automated>
  </verify>
  <done>One service owns settings persistence and the existing SPA API still returns the normalized shared language preference.</done>
</task>

</tasks>

<verification>
Run `php artisan test tests/Feature/Localization/FallbackBehaviorTest.php --stop-on-failure` and `php artisan test tests/Feature/Api/AuthApiTest.php --stop-on-failure`.
</verification>

<success_criteria>
- `user_settings.language` remains the only persisted language preference
- Invalid or missing language values always resolve to `en`
- API validation and payloads only expose `en` or `it`
</success_criteria>

<output>
After completion, create `.planning/phases/10-localization-foundation-shared-settings/10-01-SUMMARY.md`
</output>
