# v3.0 Roadmap: Mobile API Layer + Health Integration

**Milestone:** v3.0  
**Phase Range:** Phase 11–12  
**Status:** In Progress  
**Total Requirements:** 48 (40 domain + 8 quality)  
**Coverage:** 100% mapped  
**Date Created:** 2026-04-12

---

## Phases

- [ ] **Phase 11: Mobile API Layer** — Enable off-admin REST/GraphQL access to Finance, Travel, Home domains
- [ ] **Phase 12: Health Integration** — Add medical records, appointments, medications, vitals, reminders

---

## Phase Details

### Phase 11: Mobile API Layer

**Goal:** Enable mobile clients to access Finance, Travel, and Home data via secure REST and GraphQL APIs with JWT authentication, rate limiting, and comprehensive documentation.

**Depends on:** None (foundation phase for v3.0)

**Requirements:**
API-01, API-02, API-03, API-04, API-05, API-06, API-07, API-08, API-09, API-10, API-11, API-12, API-13, API-14, API-15, API-16, API-17, API-18, API-19, API-20, QA-01, QA-02, QA-03, QA-04, QA-05, QA-06, QA-07, QA-08

**Success Criteria** (what must be TRUE when Phase 11 completes):

1. **User can authenticate and receive valid JWT token** — User sends email/password to POST /api/v1/auth/login, receives access_token (30 min expiry) and refresh_token (7 days, rotating). Token can be used in Bearer header for subsequent requests.

2. **User can perform CRUD on Finance, Travel, Home resources via REST** — User can GET /api/v1/accounts, POST /api/v1/accounts, PUT /api/v1/accounts/{id}, DELETE /api/v1/accounts/{id} (and equivalent for Transactions, Trips, Itineraries, Properties). All operations respect user_id scoping.

3. **User can query data via GraphQL with nested relationships** — User sends GraphQL query requesting Account with nested Transactions, or Trip with nested Itineraries. Response contains requested relationships without N+1 queries. Query count verified ≤ 5 per request.

4. **API documentation is available and accurate** — Developer can visit /api/docs and see OpenAPI 3.0 spec with all endpoints, request/response schemas, authentication requirements. Spec is machine-readable and can generate mobile client code.

5. **All endpoints enforce user_id scoping (no cross-user data)** — User A cannot access User B's Accounts, Transactions, Trips, or Properties even with valid JWT token. All models include HasUserScoping trait; queries auto-filter by auth()->id().

6. **Rate limiting prevents abuse** — User making 101 read requests within 60 seconds receives 429 Too Many Requests. Rate limits reset after 1 minute. Different limits apply: 100 read/min, 20 write/min, verified via test making 101+ requests.

7. **All endpoints respond < 500ms for paginated requests** — GET /api/v1/accounts with limit=20 completes in < 500ms. Verified via load testing or performance assertions in feature tests using assertQueryDurationLessThan(500).

**Plans:** TBD

---

### Phase 12: Health Integration

**Goal:** Enable users to manage comprehensive health records including doctors, appointments, medications, vitals, and lab results. System provides automated reminders, conflict detection, validation, and audit-logged privacy protection.

**Depends on:** Phase 11 (API foundation required; health endpoints use same auth, pagination, rate limiting)

**Requirements:**
HEALTH-01, HEALTH-02, HEALTH-03, HEALTH-04, HEALTH-05, HEALTH-06, HEALTH-07, HEALTH-08, HEALTH-09, HEALTH-10, HEALTH-11, HEALTH-12, HEALTH-13, HEALTH-14, HEALTH-15, HEALTH-16, HEALTH-17, HEALTH-18, HEALTH-19, HEALTH-20, QA-01, QA-02, QA-03, QA-04, QA-05, QA-06, QA-07, QA-08

**Success Criteria** (what must be TRUE when Phase 12 completes):

1. **User can manage doctors, appointments, prescriptions, vitals** — User can POST /api/v1/doctors, /api/v1/appointments, /api/v1/prescriptions, /api/v1/vitals with valid data. All CRUD operations work (GET list, GET single, POST create, PUT update, DELETE soft-delete where applicable).

2. **System prevents appointment conflicts and invalid vital ranges** — User attempting to create overlapping appointments receives 409 Conflict. User logging BP of 250 receives 422 Unprocessable Entity with validation error. System validates: BP (60–200), HR (40–200), weight (20–500 kg).

3. **User receives appointment and medication reminders at correct times** — For appointment on 2026-04-15 10:00 AM, user receives push notification at 2026-04-14 10:00 AM and SMS at 2026-04-15 09:00 AM. For prescription "3x daily at 8am, 2pm, 8pm", user receives reminders at each time.

4. **User can view health dashboard with trends and progress** — Dashboard shows: upcoming appointments (next 7 days), active prescriptions, vital trends (weight over 3 months, BP history), goal progress (target weight vs current). Charts generated using aggregated health metrics.

5. **All health data encrypted at rest and audit-logged** — Database inspection confirms encrypted fields (medications, vitals, lab results) are stored as cipher text. Audit log contains entry for every health data read: timestamp, user_id, action (view/create/update/delete), model, old/new values.

6. **Timezone handling correct (UTC storage, local display)** — All appointments and vitals stored in UTC in database. User in America/New_York sees appointment at "10:00 AM EST" when stored UTC time is 3:00 PM. DST transition (Nov 1 2026) handled correctly; no duplicate/missing times.

7. **Drug interaction detection prevents conflicting medications** — User with active Warfarin cannot add Aspirin without 422 error with message "Interaction detected: Warfarin + Aspirin contraindicated". System maintains interaction matrix; checks before save.

8. **Health data export produces valid PDF** — User clicks "Export Health Records", receives PDF with: vital signs table (last 3 months), active prescriptions list, appointment history, lab results. PDF is valid, searchable, and complete.

**Plans:** TBD

---

## Phase Mapping Table

| Requirement ID | Phase | Category | Status |
|---|---|---|---|
| API-01 | Phase 11 | Authentication | Pending |
| API-02 | Phase 11 | Authentication | Pending |
| API-03 | Phase 11 | Authentication | Pending |
| API-04 | Phase 11 | Authorization | Pending |
| API-05 | Phase 11 | Rate Limiting | Pending |
| API-06 | Phase 11 | REST CRUD | Pending |
| API-07 | Phase 11 | REST CRUD | Pending |
| API-08 | Phase 11 | REST CRUD | Pending |
| API-09 | Phase 11 | REST CRUD | Pending |
| API-10 | Phase 11 | REST CRUD | Pending |
| API-11 | Phase 11 | Pagination | Pending |
| API-12 | Phase 11 | Sorting | Pending |
| API-13 | Phase 11 | Filtering | Pending |
| API-14 | Phase 11 | Error Handling | Pending |
| API-15 | Phase 11 | GraphQL | Pending |
| API-16 | Phase 11 | GraphQL | Pending |
| API-17 | Phase 11 | GraphQL | Pending |
| API-18 | Phase 11 | GraphQL | Pending |
| API-19 | Phase 11 | Documentation | Pending |
| API-20 | Phase 11 | Performance | Pending |
| HEALTH-01 | Phase 12 | Medical Records | Pending |
| HEALTH-02 | Phase 12 | Appointments | Pending |
| HEALTH-03 | Phase 12 | Appointments | Pending |
| HEALTH-04 | Phase 12 | Appointments | Pending |
| HEALTH-05 | Phase 12 | Medical Records | Pending |
| HEALTH-06 | Phase 12 | Medications | Pending |
| HEALTH-07 | Phase 12 | Medications | Pending |
| HEALTH-08 | Phase 12 | Medications | Pending |
| HEALTH-09 | Phase 12 | Medications | Pending |
| HEALTH-10 | Phase 12 | Medications | Pending |
| HEALTH-11 | Phase 12 | Vitals | Pending |
| HEALTH-12 | Phase 12 | Vitals | Pending |
| HEALTH-13 | Phase 12 | Dashboard | Pending |
| HEALTH-14 | Phase 12 | Goals | Pending |
| HEALTH-15 | Phase 12 | Timezone | Pending |
| HEALTH-16 | Phase 12 | Dashboard | Pending |
| HEALTH-17 | Phase 12 | Reminders | Pending |
| HEALTH-18 | Phase 12 | Reminders | Pending |
| HEALTH-19 | Phase 12 | Alerts | Pending |
| HEALTH-20 | Phase 12 | Export | Pending |
| QA-01 | Phase 11, 12 | Security | Pending |
| QA-02 | Phase 11, 12 | Audit | Pending |
| QA-03 | Phase 11, 12 | Encryption | Pending |
| QA-04 | Phase 11, 12 | Validation | Pending |
| QA-05 | Phase 11, 12 | API | Pending |
| QA-06 | Phase 11, 12 | Authentication | Pending |
| QA-07 | Phase 11, 12 | Security | Pending |
| QA-08 | Phase 11, 12 | API | Pending |

**Coverage Summary:**
- API-01 to API-20: Phase 11 ✓ (20 requirements)
- HEALTH-01 to HEALTH-20: Phase 12 ✓ (20 requirements)
- QA-01 to QA-08: Phase 11 + Phase 12 ✓ (8 requirements, applies to both)
- **Total Mapped:** 48 requirements
- **Orphaned:** 0
- **Coverage:** 100% ✓

---

## Progress Tracking

| Phase | Goal | Requirements Mapped | Status | Completion |
|---|---|---|---|---|
| **Phase 11** | Mobile API Layer | 28 (API-01 to API-20 + QA-01 to QA-08) | Not Started | 0% |
| **Phase 12** | Health Integration | 28 (HEALTH-01 to HEALTH-20 + QA-01 to QA-08) | Not Started | 0% |

---

## Quality Gates

### Phase 11 Quality Acceptance Criteria

✓ **Authentication:** JWT login/refresh/logout working; tokens expire correctly; refresh rotation functional  
✓ **User Isolation:** All endpoints scoped by user_id; cross-user access attempts return 403  
✓ **Rate Limiting:** Read limit (100/min) and write limit (20/min) enforced; 429 returned above threshold  
✓ **API Documentation:** OpenAPI spec generated; Swagger UI accessible at /api/docs; schemas accurate  
✓ **Performance:** All endpoints < 500ms response time; eager loading eliminates N+1 queries (≤ 5 queries per request)  
✓ **Error Handling:** Consistent responses; 400/422/403/404/429 codes correct; error messages descriptive  
✓ **GraphQL:** Schema introspection working; queries resolve without duplicates; mutations validate inputs  

### Phase 12 Quality Acceptance Criteria

✓ **Health Data Privacy:** All sensitive fields encrypted; audit log entry for every access; no unencrypted vitals in database  
✓ **Appointment Scheduling:** Double-booking prevented via pessimistic locking; UTC storage verified; conflicts return 409  
✓ **Vital Validation:** Range validation enforced; invalid BP/HR/weight rejected with 422; validation logic tested with edge cases  
✓ **Medication Safety:** Interaction matrix checked before save; conflicting meds return 422; immutability enforced on past prescriptions  
✓ **Reminders:** Appointment reminders sent 24h and 1h before (verified via job queue inspection); medication reminders at prescribed times  
✓ **Timezone Handling:** UTC storage verified; local display correct in user's timezone; DST transition handled  
✓ **Audit Trail:** Every health mutation logged; export/view actions auditable  
✓ **Export Functionality:** PDF generation tested; contains vitals, prescriptions, appointments, lab results; valid PDF format  

---

## Dependencies & Build Order

```
Phase 11 (Foundation)
  ├─ JWT authentication + Sanctum config
  ├─ Rate limiting middleware
  ├─ Policy-based authorization (HasUserScoping)
  ├─ REST controllers (Finance, Travel, Home)
  ├─ GraphQL schema extension
  ├─ OpenAPI documentation
  └─ Feature tests (80%+ coverage)
       ↓
Phase 12 (Health Domain)
  ├─ Health models (Appointment, Prescription, LabResult, Doctor)
  ├─ Health services (AppointmentService, HealthMetricsService)
  ├─ Health validation + encryption
  ├─ Health observers (reminders, mutations)
  ├─ Health controllers (REST endpoints)
  ├─ Health dashboard
  └─ Reminder jobs + notification dispatch
```

---

## Key Technical Decisions

| Decision | Rationale | Phase(s) |
|---|---|---|
| **JWT Tokens (tymon/jwt-auth)** | Stateless auth for mobile; no session storage needed | 11 |
| **Sanctuary + JWT (hybrid)** | Backward compatibility with admin Sanctum; mobile gets JWT | 11 |
| **Cursor-based Pagination** | Stable pagination for mobile; handles deletions correctly | 11 |
| **Lighthouse GraphQL** | Existing; seamless Eloquent integration; strong for mobile queries | 11 |
| **Policy-based Authorization** | Single source of truth; reused by Filament + API | 11, 12 |
| **HasUserScoping Trait** | Automatic query filtering; prevents accidental cross-user leaks | 11, 12 |
| **Encrypted Health Data** | HIPAA-like compliance; sensitive fields encrypted at rest | 12 |
| **Audit Trail for Health** | Every access logged; compliance + forensics | 12 |
| **Pessimistic Locking (Appointments)** | Prevent race conditions in concurrent bookings | 12 |
| **Immutable Past Prescriptions** | Audit trail protection; dosage/drug cannot change retroactively | 12 |

---

## Deferred Requirements (v3.1+)

**External Integrations:**
- EXT-01: Sync with Apple HealthKit
- EXT-02: Sync with Google Fit
- EXT-03: Wearable integration (Fitbit, Garmin, Apple Watch)

**Advanced Features:**
- EXT-04: Pharmacy/medication delivery integration
- EXT-05: AI-powered health insights
- EXT-06: Telehealth video consultation scheduling
- EXT-07: Health insurance claim submission

**Out of Scope (Never):**
- OUT-01: Medical diagnosis or clinical recommendations (data tracking only)
- OUT-02: Automated prescription refills (DEA licensing required)
- OUT-03: Telemedicine without licensed providers (regulatory requirement)
- OUT-04: PHI sharing without HIPAA (data protection compliance)

---

**Footer**  
Created: 2026-04-12  
Milestone: v3.0  
Next Step: Phase 11 Planning (`/gsd-plan-phase 11`)
