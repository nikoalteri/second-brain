---
phase: 10-localization-foundation-shared-settings
plan: 04
type: execute
wave: 3
depends_on: [10-01, 10-02]
files_modified:
  - tests/Feature/Filament/ProfileLocaleSettingsTest.php
  - app/Filament/Pages/Auth/EditProfile.php
  - app/Providers/Filament/AdminPanelProvider.php
  - lang/en.json
  - lang/it.json
autonomous: true
requirements: [I18N-12, I18N-13]
must_haves:
  truths:
    - An authenticated backend user can change language from a useful current-user settings surface
    - Saving language from the backend updates the same `user_settings.language` row used by the SPA
    - The backend settings surface exposes only English and Italian choices
  artifacts:
    - path: app/Filament/Pages/Auth/EditProfile.php
      provides: current-user profile page with shared language selector
    - path: tests/Feature/Filament/ProfileLocaleSettingsTest.php
      provides: regression coverage for backend settings rendering and persistence
    - path: lang/it.json
      provides: minimal Phase 10 translation keys for the new settings surface
  key_links:
    - from: app/Filament/Pages/Auth/EditProfile.php
      to: app/Services/UserSettingsService.php
      via: shared settings save call
      pattern: UserSettingsService
    - from: app/Providers/Filament/AdminPanelProvider.php
      to: app/Filament/Pages/Auth/EditProfile.php
      via: profile page registration
      pattern: profile
    - from: app/Filament/Pages/Auth/EditProfile.php
      to: user_settings.language
      via: normalized save payload
      pattern: language
---

<objective>
Expose backend language editing on a useful per-user profile surface that reuses the shared setting.

Purpose: satisfy the backend-settings part of the milestone without creating duplicate admin CRUD or backend-only preference storage.
Output: custom Filament profile page, minimal translation keys for that surface, and profile settings regression tests.
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
@app/Services/UserSettingsService.php
@app/Models/User.php
@app/Providers/Filament/AdminPanelProvider.php
@tests/Feature/Filament/UserSettingResourceTest.php

<interfaces>
From app/Services/UserSettingsService.php (created in 10-01):
- update(User $user, array $settings): User

From app/Models/User.php:
- resolvedSettings(): array

From app/Providers/Filament/AdminPanelProvider.php:
- panel(Panel $panel): Panel
</interfaces>
</context>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Write failing Filament profile locale settings tests</name>
  <files>tests/Feature/Filament/ProfileLocaleSettingsTest.php</files>
  <read_first>
    - .planning/phases/10-localization-foundation-shared-settings/10-VALIDATION.md
    - tests/Feature/Filament/UserSettingResourceTest.php
    - app/Providers/Filament/AdminPanelProvider.php
  </read_first>
  <behavior>
    - Test 1: the authenticated user can load the backend profile/settings page and see a language field with `en` and `it`.
    - Test 2: saving the backend profile language updates `user_settings.language` for the current user only.
    - Test 3: the profile page does not rely on `UserSettingResource` CRUD routes.
  </behavior>
  <action>Create the missing Wave 0 test file. Prefer Filament/Livewire component assertions if they are already available through the installed stack; otherwise use authenticated HTTP assertions plus database checks. Keep the test targeted at a current-user profile flow, not arbitrary record CRUD.</action>
  <acceptance_criteria>
    - `grep -q "class ProfileLocaleSettingsTest" tests/Feature/Filament/ProfileLocaleSettingsTest.php`
    - `grep -q "language" tests/Feature/Filament/ProfileLocaleSettingsTest.php`
    - `grep -q "it" tests/Feature/Filament/ProfileLocaleSettingsTest.php`
  </acceptance_criteria>
  <verify>
    <automated>php artisan test tests/Feature/Filament/ProfileLocaleSettingsTest.php --stop-on-failure</automated>
  </verify>
  <done>The backend profile settings contract is captured in failing automated tests before implementation.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: Implement the backend profile language selector using the shared settings service</name>
  <files>app/Filament/Pages/Auth/EditProfile.php, app/Providers/Filament/AdminPanelProvider.php, lang/en.json, lang/it.json</files>
  <read_first>
    - app/Providers/Filament/AdminPanelProvider.php
    - app/Services/UserSettingsService.php
    - app/Models/User.php
    - .planning/phases/10-localization-foundation-shared-settings/10-RESEARCH.md
  </read_first>
  <action>Create `App\\Filament\\Pages\\Auth\\EditProfile` as a current-user profile/settings surface that preserves normal profile behavior and adds a language select backed by `UserSettingsService`. Register it with `->profile(...)` in `AdminPanelProvider`, source the select options from the canonical locale contract, and save only `user_settings.language` for the authenticated user. Add just the minimal `lang/en.json` and `lang/it.json` keys needed by this page; do not begin a broad backend translation sweep and do not reuse `UserSettingResource` as the user-facing settings surface.</action>
  <acceptance_criteria>
    - `grep -q "class EditProfile" app/Filament/Pages/Auth/EditProfile.php`
    - `grep -q "profile(" app/Providers/Filament/AdminPanelProvider.php`
    - `grep -q "\"Language\"" lang/en.json`
    - `grep -q "\"Language\"" lang/it.json`
  </acceptance_criteria>
  <verify>
    <automated>php artisan test tests/Feature/Filament/ProfileLocaleSettingsTest.php --stop-on-failure</automated>
  </verify>
  <done>The backend exposes a useful per-user language control that writes the same shared preference row used by the SPA.</done>
</task>

</tasks>

<verification>
Run `php artisan test tests/Feature/Filament/ProfileLocaleSettingsTest.php --stop-on-failure`.
</verification>

<success_criteria>
- Backend users have a dedicated profile/settings language selector
- Saving backend language updates `user_settings.language` instead of a new field
- The new surface offers only English and Italian
</success_criteria>

<output>
After completion, create `.planning/phases/10-localization-foundation-shared-settings/10-04-SUMMARY.md`
</output>
