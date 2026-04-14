# v3.0 Requirements — Mobile API + Health Integration

**Milestone:** v3.0 — Mobile API Layer + Health Integration  
**Phase Range:** 11–12  
**Status:** Active  
**Total Requirements:** 40 (20 API + 20 Health)  
**Coverage:** 100%  
**Date Created:** 2026-04-10

---

## Phase 11: Mobile API Layer — 20 Requirements

**Goal:** Enable off-admin access via REST/GraphQL APIs for Travel, Finance, and Home domains.

### Authentication & Authorization (API-01 to API-05)

- [ ] **API-01:** User can authenticate via email/password and receive a JWT access token valid for 30 minutes
- [ ] **API-02:** User can refresh an expired access token using a valid refresh token without re-authenticating
- [ ] **API-03:** User can logout and invalidate all tokens immediately; subsequent requests with old tokens are rejected
- [ ] **API-04:** API enforces user_id scoping; user cannot access another user's data even with valid JWT token
- [ ] **API-05:** API enforces rate limiting: 100 read/min, 20 write/min per user; requests above threshold return 429

### REST API — Finance & Accounts (API-06 to API-07)

- [ ] **API-06:** User can perform CRUD operations on Accounts via REST (/api/v1/accounts) with balance tracking
- [ ] **API-07:** User can perform CRUD operations on Transactions via REST (/api/v1/transactions) with filtering by category, date range, amount

### REST API — Travel Domain (API-08 to API-09)

- [ ] **API-08:** User can perform CRUD on Trips via REST (/api/v1/trips) with nested access to Itineraries, Budgets, Participants
- [ ] **API-09:** User can perform CRUD on Itineraries via REST with Activity sequencing and conflict detection

### REST API — Home Domain (API-10)

- [ ] **API-10:** User can perform CRUD on Properties via REST (/api/v1/properties) with nested Maintenance tasks and Utilities

### REST API — Pagination, Sorting, Filtering (API-11 to API-13)

- [ ] **API-11:** API supports cursor-based pagination with configurable page size (default 20 items)
- [ ] **API-12:** API supports sorting by any indexed column (created_at, name, amount) via query parameter
- [ ] **API-13:** API supports filtering by multiple criteria (status, category, date range) with logical operators (AND, OR)

### REST API — Error Handling & Validation (API-14)

- [ ] **API-14:** API returns consistent error responses: 400 (bad request), 422 (validation failed with field details), 403 (unauthorized), 404 (not found)

### GraphQL API (API-15 to API-18)

- [ ] **API-15:** User can query core types (Account, Transaction, Trip, Property) via GraphQL with nested relationships
- [ ] **API-16:** User can create/update/delete resources via GraphQL mutations with input type validation
- [ ] **API-17:** User can fetch aggregated data (total by category, upcoming events) via GraphQL without N+1 queries
- [ ] **API-18:** GraphQL schema is fully documented with descriptions; introspection enabled for client code generation

### API Documentation & Performance (API-19 to API-20)

- [ ] **API-19:** API documentation exists as OpenAPI 3.0 spec and is viewable via Swagger UI (/api/docs)
- [ ] **API-20:** All API endpoints respond in < 500ms for paginated requests; query optimization via eager loading verified

---

## Phase 12: Health Integration — 20 Requirements

**Goal:** Enable users to manage health records, appointments, medications, and vitals with automated reminders and privacy protections.

### Doctor & Appointment Management (HEALTH-01 to HEALTH-04)

- [ ] **HEALTH-01:** User can register a doctor/provider with name, specialty, contact phone, email, and office address
- [ ] **HEALTH-02:** User can create medical appointments with date, time, duration, doctor, visit reason, and location
- [ ] **HEALTH-03:** User can view upcoming appointments (next 30 days) with full details and past appointment history
- [ ] **HEALTH-04:** System prevents double-booking: overlapping appointments for same user return 409 conflict error

### Medical Records & Documentation (HEALTH-05)

- [ ] **HEALTH-05:** User can log medical records (lab results, imaging reports, discharge summaries) with date and document attachments

### Prescription & Medication Management (HEALTH-06 to HEALTH-10)

- [ ] **HEALTH-06:** User can log prescriptions with medication name, dosage, frequency, start date, end date, and prescribing doctor
- [ ] **HEALTH-07:** User can track medication adherence by logging taken/missed doses for active prescriptions
- [ ] **HEALTH-08:** System alerts user 7 days before prescription end date for refill coordination
- [ ] **HEALTH-09:** System prevents dangerous drug interactions: warns user if two conflicting medications are active simultaneously
- [ ] **HEALTH-10:** Past prescriptions are immutable; user cannot modify or delete recorded prescriptions (audit trail protection)

### Health Metrics & Vital Signs (HEALTH-11 to HEALTH-15)

- [ ] **HEALTH-11:** User can log vital signs (blood pressure, heart rate, temperature, weight, sleep hours) with timestamps
- [ ] **HEALTH-12:** System validates vital sign ranges and rejects invalid values (e.g., BP > 200 returns 422 error)
- [ ] **HEALTH-13:** User can view health metrics trends over 1, 3, 6, 12 month periods with graphical charts
- [ ] **HEALTH-14:** User can set personal health goals (target weight, daily steps, sleep hours) and track progress
- [ ] **HEALTH-15:** System stores all health data in UTC; displays in user's local timezone; handles DST transitions correctly

### Health Dashboard & Insights (HEALTH-16)

- [ ] **HEALTH-16:** User sees health dashboard with: upcoming appointments (next 7 days), active prescriptions, vital trends, and goals progress

### Notifications & Reminders (HEALTH-17 to HEALTH-19)

- [ ] **HEALTH-17:** User receives appointment reminders: push notification 24 hours before and SMS 1 hour before
- [ ] **HEALTH-18:** User receives medication reminders at prescribed dosage times (e.g., 8am, 2pm, 8pm for 3x daily)
- [ ] **HEALTH-19:** User receives alerts when vital signs fall outside normal ranges (severity: info/warning/critical)

### Data Export & Privacy (HEALTH-20)

- [ ] **HEALTH-20:** User can export health records as PDF including: vitals history, active/past prescriptions, appointments, lab results

---

## Cross-Domain Quality Requirements (Apply to Both Phases)

- [ ] **QA-01:** All endpoints enforce user_id scoping via HasUserScoping trait; no cross-user data leaks
- [ ] **QA-02:** All data mutations logged in audit trail with: timestamp, user_id, action, model, old/new values
- [ ] **QA-03:** All health data encrypted at rest using Laravel's encrypted() casting
- [ ] **QA-04:** All requests/responses validated via Form Requests (REST) or GraphQL input validation
- [ ] **QA-05:** All API responses include: X-Total-Count header, X-Rate-Limit-Remaining header
- [ ] **QA-06:** JWT tokens expire in 30 minutes; refresh tokens rotate on each use; old refresh tokens invalidated
- [ ] **QA-07:** CORS configured securely: whitelist specific origins (no wildcard with credentials)
- [ ] **QA-08:** All endpoints return appropriate HTTP status codes (200 ok, 201 created, 400 bad req, 403 forbidden, 404 not found, 409 conflict, 422 unprocessable, 429 too many requests)

---

## Future Requirements (Deferred)

### External Integrations (v3.1+)
- **EXT-01:** Sync with Apple HealthKit: read vitals, steps, sleep
- **EXT-02:** Sync with Google Fit: read/write health metrics
- **EXT-03:** Wearable integration: Fitbit, Garmin, Apple Watch sync

### Advanced Health Features (v3.1+)
- **EXT-04:** Medication delivery/pharmacy integration
- **EXT-05:** Health insights: AI-powered trend analysis and recommendations
- **EXT-06:** Telehealth video consultation scheduling
- **EXT-07:** Health insurance claim submission workflow

---

## Out of Scope (Explicitly Excluded)

These features are **NOT** part of v3.0 due to compliance, liability, or scope constraints:

- **OUT-01:** Medical diagnosis or clinical recommendations (system tracks data only; no advice)
- **OUT-02:** Automated prescription refill to pharmacies (DEA licensing required)
- **OUT-03:** Telemedicine practice without licensed providers (regulatory requirement)
- **OUT-04:** PHI sharing without HIPAA infrastructure (data protection compliance)
- **OUT-05:** Emergency services integration (not a 911 app)
- **OUT-06:** Fitness tracking data (separate from health metrics)
- **OUT-07:** Mental health assessments (liability)

---

## Traceability Matrix

| Requirement | Phase | Status | Notes |
|---|---|---|---|
| API-01 | Phase 11 | Pending | JWT access token (30 min) |
| API-02 | Phase 11 | Pending | Refresh token rotation |
| API-03 | Phase 11 | Pending | Logout + token invalidation |
| API-04 | Phase 11 | Pending | User_id scoping enforcement |
| API-05 | Phase 11 | Pending | Rate limiting (100 read/min, 20 write/min) |
| API-06 | Phase 11 | Pending | CRUD Accounts |
| API-07 | Phase 11 | Pending | CRUD Transactions with filtering |
| API-08 | Phase 11 | Pending | CRUD Trips with nested Itineraries |
| API-09 | Phase 11 | Pending | CRUD Itineraries with conflict detection |
| API-10 | Phase 11 | Pending | CRUD Properties with nested Maintenance/Utilities |
| API-11 | Phase 11 | Pending | Cursor-based pagination |
| API-12 | Phase 11 | Pending | Sorting by indexed columns |
| API-13 | Phase 11 | Pending | Multi-criteria filtering (AND/OR) |
| API-14 | Phase 11 | Pending | Consistent error responses (400/422/403/404/429) |
| API-15 | Phase 11 | Pending | GraphQL queries with nested relationships |
| API-16 | Phase 11 | Pending | GraphQL mutations (create/update/delete) |
| API-17 | Phase 11 | Pending | GraphQL aggregation (no N+1) |
| API-18 | Phase 11 | Pending | GraphQL schema documentation + introspection |
| API-19 | Phase 11 | Pending | OpenAPI 3.0 spec + Swagger UI |
| API-20 | Phase 11 | Pending | < 500ms response time (paginated requests) |
| HEALTH-01 | Phase 12 | Pending | Doctor/provider registration |
| HEALTH-02 | Phase 12 | Pending | Appointment creation (date, time, duration, doctor, reason, location) |
| HEALTH-03 | Phase 12 | Pending | View upcoming (30d) + past appointments |
| HEALTH-04 | Phase 12 | Pending | Prevent double-booking (409 Conflict) |
| HEALTH-05 | Phase 12 | Pending | Log medical records (lab results, imaging, discharge summaries) |
| HEALTH-06 | Phase 12 | Pending | Log prescriptions (name, dosage, frequency, dates, doctor) |
| HEALTH-07 | Phase 12 | Pending | Track medication adherence (taken/missed doses) |
| HEALTH-08 | Phase 12 | Pending | Alert 7 days before prescription expiry |
| HEALTH-09 | Phase 12 | Pending | Prevent drug interactions (422 on conflict) |
| HEALTH-10 | Phase 12 | Pending | Immutable past prescriptions (audit trail) |
| HEALTH-11 | Phase 12 | Pending | Log vitals (BP, HR, temp, weight, sleep) |
| HEALTH-12 | Phase 12 | Pending | Validate vital ranges (422 on invalid) |
| HEALTH-13 | Phase 12 | Pending | Health trends (1/3/6/12 month charts) |
| HEALTH-14 | Phase 12 | Pending | Set health goals + track progress |
| HEALTH-15 | Phase 12 | Pending | UTC storage + local timezone display + DST handling |
| HEALTH-16 | Phase 12 | Pending | Health dashboard (appointments, prescriptions, trends, goals) |
| HEALTH-17 | Phase 12 | Pending | Appointment reminders (push 24h before, SMS 1h before) |
| HEALTH-18 | Phase 12 | Pending | Medication reminders (at dosage times) |
| HEALTH-19 | Phase 12 | Pending | Vital alerts (info/warning/critical) |
| HEALTH-20 | Phase 12 | Pending | Export health records as PDF |
| QA-01 | Phase 11, 12 | Pending | User_id scoping via HasUserScoping trait |
| QA-02 | Phase 11, 12 | Pending | Audit trail (timestamp, user_id, action, model, old/new values) |
| QA-03 | Phase 11, 12 | Pending | Health data encrypted at rest (Laravel encrypted casting) |
| QA-04 | Phase 11, 12 | Pending | Form Requests (REST) + GraphQL input validation |
| QA-05 | Phase 11, 12 | Pending | X-Total-Count + X-Rate-Limit-Remaining headers |
| QA-06 | Phase 11, 12 | Pending | JWT expiry (30 min) + refresh rotation + blacklist |
| QA-07 | Phase 11, 12 | Pending | CORS whitelist (specific origins, no wildcard) |
| QA-08 | Phase 11, 12 | Pending | HTTP status codes (200/201/400/403/404/409/422/429) |

**Coverage Summary:**
- Phase 11: API-01 to API-20 (20 requirements) + QA-01 to QA-08 (8 quality) = 28 total
- Phase 12: HEALTH-01 to HEALTH-20 (20 requirements) + QA-01 to QA-08 (8 quality) = 28 total
- **Total Mapped:** 48 requirements (note: QA items apply to both phases but listed once)
- **Orphaned:** 0
- **Coverage:** 100% ✓

---

## Requirement Mapping to Phases

### Phase 11: Mobile API Layer

**Implements:** API-01 through API-20 + QA-01 through QA-08

**Dependencies:**
- None (foundation phase)

**Success Criteria:**
1. User can authenticate and receive valid JWT token
2. User can perform full CRUD on Finance, Travel, Home resources via REST
3. User can query data via GraphQL with nested relationships
4. User can access API documentation via Swagger UI
5. All endpoints respect user_id scoping (no cross-user data access)
6. All endpoints return < 500ms response time
7. Rate limiting prevents abuse (429 after threshold)

---

### Phase 12: Health Integration

**Implements:** HEALTH-01 through HEALTH-20 + QA-01 through QA-08

**Dependencies:**
- Phase 11 (API foundation required for health endpoints)

**Success Criteria:**
1. User can manage doctors, appointments, prescriptions, vitals
2. System prevents appointment conflicts and invalid vital ranges
3. User receives appointment and medication reminders at correct times
4. User can view health dashboard with trends and progress
5. All health data encrypted at rest (verified via DB inspection)
6. 100% of health access logged in audit trail
7. Timezone handling correct (UTC storage, local display)
8. Drug interaction detection prevents conflicting medications

---

## Verification Strategy

### Phase 11 (API) Acceptance Tests
```gherkin
Feature: Mobile API Authentication
  Scenario: User logs in with valid credentials
    Given user has email and password
    When user sends POST /api/v1/auth/login
    Then user receives access_token and refresh_token

Feature: Rate Limiting
  Scenario: User exceeds read limit
    Given user has valid token
    When user makes 101 read requests within 1 minute
    Then response 429 is returned
```

### Phase 12 (Health) Acceptance Tests
```gherkin
Feature: Appointment Conflict Detection
  Scenario: Prevent overlapping appointments
    Given user has appointment on 2026-04-15 10:00-11:00
    When user creates appointment on 2026-04-15 10:30-11:30
    Then system returns 409 Conflict error

Feature: Medication Reminders
  Scenario: User receives reminder at dosage time
    Given user has prescription: 3x daily at 8am, 2pm, 8pm
    When system reaches 8:00 AM
    Then user receives reminder notification
```

---

## Notes

- **Phase Numbering:** Continues from v2.0 Phase 10 → v3.0 starts at Phase 11
- **Research:** Completed Apr 10, 2026 (see `.planning/research/SUMMARY.md`)
- **Timeline:** 3-4 weeks estimated based on complexity
- **Tech Stack:** Sanctum + tymon/jwt-auth, Lighthouse GraphQL, OpenAPI
- **Status:** Ready for Roadmap Creation

---

**Created:** 2026-04-10  
**Next Step:** Create ROADMAP.md with phase structure and success criteria
