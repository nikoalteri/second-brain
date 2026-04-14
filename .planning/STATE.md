---
gsd_state_version: 1.0
milestone: v3.0
milestone_name: Mobile API Layer + Health Integration
current_phase: 11
status: planning
last_updated: "2026-04-12T12:00:00.000Z"
last_activity: 2026-04-12
progress:
  total_phases: 2
  completed_phases: 0
  total_plans: 0
  completed_plans: 0
  percent: 0
---

# v3.0 Project State

**Project:** Second Brain — Personal Operations Platform  
**Milestone:** v3.0 (Mobile API Layer + Health Integration)  
**Phases:** 11–12  
**Status:** Roadmap Created → Planning Phase 11  
**Updated:** 2026-04-12

---

## Project Reference

**Core Value:**
Centralize personal operations (finance, health, productivity, relationships) in one admin-first + API-first system. v3.0 adds mobile-first API access and comprehensive health tracking.

**Current Focus:**
- Enable mobile app development via REST/GraphQL APIs
- Add health domain: appointments, medications, vitals, medical records
- Maintain security via JWT auth, user_id scoping, encryption, audit logging

**Constraints:**
- Single-user system (personal ops platform)
- Admin-first design (Filament for data entry); API-second (mobile consumption)
- No third-party integrations in v3.0 (defer to v3.1+)
- HIPAA-like privacy for health data (encryption, audit logging, no diagnosis)

---

## Current Position

**Milestone:** v3.0  
**Current Phase:** Phase 11 (Mobile API Layer) — Ready to Plan  
**Previous Phases:** 
- Phase 10 (Home Management & Quality) ✅ Completed  
- v1.0: 8 phases, v2.0: 2 phases = 10 phases delivered

**What's Done:**
- v1.0: 8 phases, Finance/Health/Productivity/Relationships domains
- v2.0: Phase 9 (Travel), Phase 10 (Home Management)
  - Total: 39 requirements, 143 tests passing
  - Architecture validated; service layer pattern working

**What's Next:**
- Phase 11: Mobile API Layer (20 requirements: API-01 to API-20)
- Phase 12: Health Integration (20 requirements: HEALTH-01 to HEALTH-20)
- Quality requirements (QA-01 to QA-08) apply to both phases

**Progress Bar (Roadmap Level):**
```
v1.0 [==============] 100%
v2.0 [==============] 100%
v3.0 [              ] 0% (planning Phase 11)
```

---

## Phase 11 Overview

**Goal:** Enable mobile clients to access Finance, Travel, and Home data via secure REST and GraphQL APIs.

**Deliverables:**
1. JWT authentication (login, refresh, logout)
2. REST controllers for Accounts, Transactions, Trips, Itineraries, Properties
3. GraphQL schema with nested relationships
4. OpenAPI documentation + Swagger UI
5. Rate limiting (100 read/min, 20 write/min)
6. Pagination, sorting, filtering
7. Error handling (consistent 400/422/403/404/429 responses)

**Success Criteria:**
1. User can authenticate and get JWT token
2. User can CRUD Finance, Travel, Home resources via REST
3. User can query data via GraphQL
4. API docs available at /api/docs
5. All endpoints enforce user_id scoping
6. Rate limiting active (429 after threshold)
7. All endpoints < 500ms response time

**Requirements Mapped:** 28 (API-01 to API-20 + QA-01 to QA-08)

**Dependencies:** None (foundation)

---

## Phase 12 Overview

**Goal:** Enable health tracking with appointments, medications, vitals, and reminders.

**Deliverables:**
1. Health models: Appointment, Doctor, Prescription, LabResult, HealthMetric
2. Health services: AppointmentService, HealthMetricsService, PrescriptionService
3. Health validation: vital ranges, interaction checking, timezone handling
4. Health encryption: encrypted() casting for sensitive fields
5. Reminder jobs: SendAppointmentReminder, SendMedicationReminder
6. Health dashboard: upcoming appointments, prescriptions, trends, goals
7. Health API controllers + GraphQL endpoints

**Success Criteria:**
1. User can manage doctors, appointments, prescriptions, vitals
2. System prevents appointment conflicts (409) and invalid vitals (422)
3. User receives reminders 24h and 1h before appointments, at medication times
4. Health dashboard displays trends, goals, upcoming appointments
5. All health data encrypted at rest
6. Audit trail tracks all health data mutations
7. Timezone handling correct (UTC storage, local display)
8. Drug interaction detection prevents conflicting medications

**Requirements Mapped:** 28 (HEALTH-01 to HEALTH-20 + QA-01 to QA-08)

**Dependencies:** Phase 11 (requires JWT auth, rate limiting, policies, user scoping)

---

## Technical Stack (v3.0)

| Layer | Technology | Purpose |
|---|---|---|
| **Backend Framework** | Laravel 12 | API foundation |
| **Authentication** | Sanctum + tymon/jwt-auth | Hybrid (admin + mobile) |
| **Authorization** | Spatie Permission + Policies | Role-based + model-level |
| **GraphQL** | Lighthouse 6.65+ | Schema-driven API |
| **API Docs** | OpenAPI 3.0 + Swagger UI | Auto-generated documentation |
| **Caching** | Redis (prod) + File (dev) | Rate limiting, token caching |
| **Encryption** | Laravel encrypted() casting | Health data at rest |
| **Jobs/Queue** | Laravel Queue | Reminder dispatch, async tasks |
| **Database** | MySQL/SQLite | Persistent storage |
| **Frontend (Admin)** | Filament 4 + Vue 3 | Admin CRUD interface |
| **Testing** | PHPUnit + Pest | Unit/Feature tests |

---

## Key Files & Locations

```
.planning/
├── PROJECT.md                 ← Milestone overview
├── REQUIREMENTS.md            ← 40 domain + 8 quality requirements
├── ROADMAP.md                 ← Phase structure
├── STATE.md                   ← THIS FILE (project context + decisions)
└── research/
    └── SUMMARY.md             ← Architecture + pitfalls

app/
├── Http/Controllers/Api/
│   ├── AuthController.php     ← JWT login/refresh/logout
│   └── V1/
│       ├── AccountController.php
│       ├── TransactionController.php
│       ├── TripController.php
│       ├── ItineraryController.php
│       ├── PropertyController.php
│       ├── AppointmentController.php (NEW)
│       ├── PrescriptionController.php (NEW)
│       ├── HealthMetricController.php (NEW)
│       └── ... 12+ more controllers
│
├── Models/
│   ├── Appointment.php (NEW)
│   ├── Prescription.php (NEW)
│   ├── LabResult.php (NEW)
│   ├── Doctor.php (NEW)
│   └── ... existing models
│
├── Services/
│   ├── AppointmentService.php (NEW)
│   ├── HealthMetricsService.php (NEW)
│   ├── PrescriptionService.php (NEW)
│   └── ... existing services
│
├── Jobs/
│   ├── SendAppointmentReminder.php (NEW)
│   └── SendMedicationReminder.php (NEW)
│
├── Policies/
│   ├── AppointmentPolicy.php (NEW)
│   ├── PrescriptionPolicy.php (NEW)
│   └── ... existing policies
│
└── Http/Requests/
    ├── StoreAppointmentRequest.php (NEW)
    ├── UpdatePrescriptionRequest.php (NEW)
    └── ... 15+ new form requests

graphql/
└── schema.graphql              ← Extended with health types

routes/
└── api.php                      ← API routes (v1)

database/migrations/
├── create_appointments_table.php (NEW)
├── create_prescriptions_table.php (NEW)
├── create_lab_results_table.php (NEW)
└── create_doctors_table.php (NEW)
```

---

## Quality & Testing Strategy

**Phase 11 Tests (API Foundation):**
- Authentication: login, refresh, logout, token expiry
- Authorization: user isolation, 403 on cross-user access
- CRUD: create, read, update, delete for all resources
- Pagination: cursor-based, offset, page size validation
- Sorting: all indexed columns, default order
- Filtering: multi-criteria, AND/OR operators
- Errors: 400, 422, 403, 404, 429 responses
- Rate Limiting: read/write thresholds, reset behavior
- GraphQL: queries, mutations, nested relationships, N+1 prevention
- Performance: < 500ms response time, eager loading

**Phase 12 Tests (Health Domain):**
- Medical Records: create, read, attach files
- Appointments: create, conflict detection (409), history
- Prescriptions: create (immutable), refill alerts, adherence tracking
- Vitals: log, validate ranges (422 on invalid), trends
- Health Metrics: aggregation, goal progress, charts
- Drug Interactions: checking, prevention (422 on conflict)
- Encryption: verified via DB inspection, encrypted fields
- Audit Trail: logged for all health mutations
- Timezone: UTC storage, local display, DST handling
- Reminders: appointment (24h, 1h), medication (dosage times)

**Coverage Target:** 80%+ (feature + unit tests combined)

---

## Performance Targets

| Metric | Target | Verification |
|---|---|---|
| **API Response Time** | < 500ms (p95) | Load testing, assertQueryDurationLessThan() |
| **Query Count** | ≤ 5 per endpoint | assertQueryCount() in feature tests |
| **Pagination Size** | Default 20, max 100 | Form Request validation |
| **Rate Limit Accuracy** | 100 read/min, 20 write/min | Load testing with burst, 429 response |
| **JWT Expiry** | Access 30 min, Refresh 7 days | Token lifecycle tests |
| **Encryption** | AES-256 at rest | DB inspection for cipher text |
| **Reminder Dispatch** | < 60s after scheduled time | Job queue inspection, timestamps |

---

## Risk Mitigation

### Phase 11 Risks

| Risk | Mitigation |
|---|---|
| **N+1 Queries** | Eager load all relationships; assertQueryCount() in tests ≤ 5 |
| **Token Leakage** | HTTPS only, short expiry (30 min), rotation on refresh, HTTP-only flag |
| **Cross-User Access** | HasUserScoping trait, policy checks at every controller, test matrix (user A vs B) |
| **Rate Limit Bypass** | Tiered limits, IP tracking, burst protection (max 5 req/sec) |
| **GraphQL N+1** | DataLoader batching, eager loading verification |

### Phase 12 Risks

| Risk | Mitigation |
|---|---|
| **Appointment Conflicts** | Pessimistic locking, unique constraint (doctor_id, datetime), test concurrency |
| **Vital Validation Failure** | Range validation (BP 60–200, HR 40–200), flag out-of-range, 422 response |
| **Medication Interactions** | Maintain interaction matrix, check before save, 422 on conflict |
| **Health Data Breach** | Encryption at rest, HTTPS + HSTS, audit logs, 30-day grace before hard delete |
| **Reminder Failure** | Job queue retry, notification status tracking, manual reminder option |
| **Timezone Errors** | Store all times in UTC, verify display in user TZ, test DST transitions |

---

## Decisions Log

### D-006: JWT + Sanctum Hybrid Authentication
**Decided:** 2026-04-12  
**Rationale:** Sanctum provides backward compatibility with existing admin sessions. JWT added for stateless mobile flows with refresh rotation. Both coexist; middleware routes requests to correct guard.  
**Phases:** 11, 12  
**Impact:** Two auth guards configured; tests cover both paths.

### D-007: Cursor-Based Pagination
**Decided:** 2026-04-12  
**Rationale:** Cursor pagination stable for mobile; handles deletions correctly. Offset pagination causes skips. Cursor approach: encode timestamp+id; mobile can request next page via cursor.  
**Phases:** 11  
**Impact:** API responses include cursor for next_page; clients parse and reuse.

### D-008: HasUserScoping Trait for Health Privacy
**Decided:** 2026-04-12  
**Rationale:** Automatic query filtering prevents accidental cross-user leaks. Every health model includes trait; queries auto-add whereUserId(auth()->id()). Single source of truth for scoping.  
**Phases:** 11, 12  
**Impact:** Trait applied to Appointment, Prescription, LabResult, HealthMetric models. No manual whereUserId() needed.

### D-009: Encrypted Health Data at Rest
**Decided:** 2026-04-12  
**Rationale:** HIPAA-like compliance. Sensitive fields (medications, vitals, lab results) encrypted using Laravel encrypted() casting. AES-256 cipher; keys rotated via .env.  
**Phases:** 12  
**Impact:** Fields defined in model: $hidden, $casts['medications'] = 'encrypted'; DB stores cipher text; decrypts on retrieval.

### D-010: Immutable Past Prescriptions
**Decided:** 2026-04-12  
**Rationale:** Audit trail protection. Past prescriptions cannot be modified or deleted. Status field (active/expired/archived) tracks lifecycle. Policy enforces: can only update if status = 'active'.  
**Phases:** 12  
**Impact:** Soft deletes + status column. Policy: $policy->update() checks 'active' status.

### D-011: Pessimistic Locking for Appointment Scheduling
**Decided:** 2026-04-12  
**Rationale:** Prevent race conditions when two requests create overlapping appointments simultaneously. Pessimistic lock: SELECT ... FOR UPDATE during conflict detection.  
**Phases:** 12  
**Impact:** AppointmentService uses lockForUpdate(); conflict detection runs atomically.

---

## Accumulated Context

### From Research (SUMMARY.md)

**Architecture Highlights:**
- Extend existing services (PropertyService, TravelService); don't replace
- Authorization via policies (same for Filament + API)
- Multi-tenancy via HasUserScoping global scope
- REST + GraphQL coexist; both call identical services

**Tech Stack Decisions:**
- GraphQL: Lighthouse 6.65 (already installed)
- Auth: Sanctum (session) + tymon/jwt-auth (mobile)
- API Docs: OpenAPI 3.0 + Swagger UI
- Health Data: FHIR-compatible JSON storage (simple, export-on-demand)
- Caching: Redis (prod) + File (dev)

**Pitfalls to Avoid:**
- N+1 queries (eager load, assertQueryCount ≤ 5)
- Token leakage (HTTPS, short expiry, rotation)
- Cross-user access (HasUserScoping, policies, test matrix)
- Appointment conflicts (pessimistic locking, unique constraint)
- Vital validation (range checking, 422 on invalid)
- Prescription errors (immutable, interaction checking)

### From Requirements (REQUIREMENTS.md)

**Phase 11: 20 API requirements**
- Authentication: JWT login/refresh/logout (API-01 to API-03)
- User Isolation: scoping enforcement (API-04)
- Rate Limiting: 100 read/min, 20 write/min (API-05)
- REST CRUD: Accounts, Transactions, Trips, Itineraries, Properties (API-06 to API-10)
- Pagination: cursor-based with size config (API-11)
- Sorting: indexed columns (API-12)
- Filtering: multi-criteria with AND/OR (API-13)
- Errors: consistent responses (API-14)
- GraphQL: queries, mutations, aggregation, introspection (API-15 to API-18)
- Documentation: OpenAPI 3.0 + Swagger UI (API-19)
- Performance: < 500ms response (API-20)

**Phase 12: 20 Health requirements**
- Medical Records: doctors, appointments, lab results (HEALTH-01 to HEALTH-05)
- Prescriptions: logging, adherence, refill alerts, interactions (HEALTH-06 to HEALTH-10)
- Vitals: logging, validation, trends, goals (HEALTH-11 to HEALTH-15)
- Dashboard: appointments, prescriptions, trends, goals (HEALTH-16)
- Reminders: appointments (24h, 1h), medications (dosage times), alerts (HEALTH-17 to HEALTH-19)
- Export: PDF with vitals, prescriptions, appointments, lab results (HEALTH-20)

**Quality Requirements: 8 (QA-01 to QA-08)**
- User scoping, audit trail, encryption, validation, headers, JWT expiry, CORS, HTTP status codes

---

## Session Continuity

**Last Session:** 2026-04-12 (Roadmap Created)  
**Next Session:** Phase 11 Planning  
**Handoff Notes:**
- ROADMAP.md complete with Phase 11 & 12 structure
- All 40 domain + 8 quality requirements mapped
- 100% coverage verified; no orphaned requirements
- Phase 11 ready for `/gsd-plan-phase 11` command
- Research completed; architecture decisions documented

---

**Footer**  
Created: 2026-04-12  
Updated: 2026-04-12  
Milestone: v3.0 (Phase 11–12)  
Status: Roadmap Complete → Ready for Phase 11 Planning
