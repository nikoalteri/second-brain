# v3.0 Research Synthesis: Mobile APIs & Health Integration

**Project:** Second Brain v3.0  
**Date:** 2026-04-12  
**Status:** Research Complete → Ready for Implementation  
**Phases:** Phase 11 (Mobile API) + Phase 12 (Health Integration)

---

## 1. Stack Summary

### Core Technology Decisions

| Technology | Decision | Rationale |
|-----------|----------|-----------|
| **GraphQL** | Keep & Extend Lighthouse 6.65 | Already installed; seamless Eloquent integration; strong for mobile queries |
| **REST API** | Laravel ResourceController pattern | Familiar; proven; pairs well with Lighthouse for different use cases |
| **Auth (Mobile)** | Sanctum tokens (session) + tymon/jwt-auth (mobile) | Sanctum for backward compat; JWT for stateless mobile flows with refresh rotation |
| **Health Data** | FHIR-compatible JSON storage (no full FHIR library) | Enables interoperability; simple structure; export-on-demand without overhead |
| **Caching** | Redis (production) + File cache (dev) | Rate limiting, token caching; lazy-load protection on APIs |
| **API Docs** | Laravel OpenAPI 3.0 + Swagger UI | Auto-generated from routes; mobile teams self-serve docs |

**Key Additions:**
- `tymon/jwt-auth` (^2.1) — Mobile JWT refresh flow
- `vyuldashev/laravel-openapi` (^3.0) — API documentation

---

## 2. Feature Table Stakes (MVP)

### Must-Have for v3.0 Release

#### API Layer (Phase 11)
| Feature | Endpoint(s) | Complexity | Effort |
|---------|-----------|-----------|--------|
| **REST CRUD** | /api/v1/{resource} | Low | 10 pts |
| **JWT Auth** | POST /auth/login, /refresh, /logout | Medium | 5 pts |
| **Pagination** | Cursor + offset; default 20/page | Low | 3 pts |
| **Rate Limiting** | 100 req/min (default); tiered by endpoint | Low | 3 pts |
| **GraphQL Schema** | Finance, Travel, Home types + queries | Medium | 8 pts |
| **API Docs** | OpenAPI spec + Swagger UI | Low | 3 pts |

**Domains in MVP:**
- **Finance:** Accounts, Transactions, Subscriptions
- **Travel:** Trips, Itineraries, Expenses, Budgets
- **Home:** Properties, Maintenance, Utilities

#### Health Domain (Phase 12)
| Feature | Endpoints | Complexity | Effort |
|---------|-----------|-----------|--------|
| **Medical Records** | /api/v1/medical-records (CRUD) | Low | 5 pts |
| **Appointments** | /api/v1/appointments (scheduling, conflict detection) | Low | 5 pts |
| **Prescriptions** | /api/v1/prescriptions (immutable tracking) | Medium | 5 pts |
| **Lab Results** | /api/v1/lab-results (validation against ranges) | Low | 3 pts |
| **Health Metrics** | /api/v1/health-metrics (vitals: BP, weight, sleep) | Low | 3 pts |
| **Health Dashboard** | Aggregated view: trends + upcoming | Medium | 5 pts |
| **Appointment Reminders** | 24h + 1h before (push/SMS) | Medium | 5 pts |
| **Medication Reminders** | Adherence tracking + refill alerts | Medium | 5 pts |

---

## 3. Architecture Highlights

### Design Principles

1. **Extend, Don't Replace**
   - Existing services (PropertyService, TravelService) + models reused by API
   - Health domain follows same patterns: Models → Services → Policies → Observers

2. **Authorization via Policies** (Everywhere)
   - Same policies used by Filament + API (e.g., PropertyPolicy::view)
   - Policy checks: `user->id === model->user_id` (HasUserScoping trait)
   - Controllers call `$this->authorize('view', $model)` before returning data

3. **Multi-Tenancy via Global Scopes**
   - `HasUserScoping` trait auto-filters queries by `auth()->id()`
   - Applied to: Property, Trip, Account, HealthRecord, Appointment, etc.
   - No manual whereUserId() needed; automatic at ORM level

4. **REST + GraphQL Coexist**
   - REST: ResourceController + Form Requests + Resources
   - GraphQL: Lighthouse schema with same resolvers
   - Both call identical services; no logic duplication

### Data Flow (Request → Response)

```
Mobile Client
  ↓ (JWT token)
routes/api.php (auth:sanctum middleware)
  ↓ (validate token)
Controller@action
  ↓ (Form Request validation)
  ↓ (Policy authorization)
Service@method (business logic)
  ↓ (call Eloquent model)
Model + Observers (side effects dispatch as Jobs)
  ↓ (JSON Resource transformation)
Response 200/201/422/403
```

### Integration Pattern

```
Existing Services         API Layer              New Health Domain
├─ PropertyService        ├─ PropertyController  ├─ HealthMetricsService
├─ TravelService          ├─ TripController      ├─ AppointmentService
├─ CreditCardService      ├─ AccountController   ├─ PrescriptionService
└─ MaintenanceService     └─ MaintenanceCtrl     └─ LabResultService

All routes: /api/v1/*
All auth: JWT token (Bearer)
All pagination: JSON envelope + meta/links
All errors: Structured 422/403/401 responses
```

---

## 4. Top Pitfalls to Avoid

### API Security (Phase 11)

| Pitfall | Prevention Strategy |
|---------|-------------------|
| **N+1 Queries** | Eager load all relationships; add `assertQueryCount()` to tests (≤5 queries/endpoint) |
| **Auth Failures** | Sanctum + Policy checks at EVERY controller; test both 401 (missing token) + 403 (wrong user) |
| **CORS Breach** | Whitelist origins only; no wildcard + credentials combo; separate middleware for /api vs /admin |
| **Token Expiry** | Access: 15-30 min; Refresh: 7 days with rotation; blacklist on logout |
| **Rate Limiting Abuse** | Tiered limits: read (100/min), write (20/min), health (30/min); burst protection (5 req/sec) |

### Health Data (Phase 12)

| Pitfall | Prevention Strategy |
|---------|-------------------|
| **Privacy Breach** | HTTPS only; encrypt sensitive fields (medications, vitals); audit log ALL access |
| **Appointment Conflicts** | Store all datetimes as UTC; conflict detection before save; doctor availability slots |
| **Medication Validation** | Interaction checking (contraindications = 422); dosage ranges; immutable after creation |
| **Vital Sign Errors** | Validation ranges: BP (60–200), HR (40–200), weight (20–500 kg); flag out-of-range |
| **Prescription Refills** | Immutable drug/dosage; track refills vs original count; expire after 1 year |
| **External API Failures** | Token encryption in .env; proactive refresh; sync failures queue retry; sync status visible |

---

## 5. Phase Decomposition

### Phase 11: Mobile API Layer (Weeks 1-2)

**Goal:** REST + GraphQL foundation ready for mobile app development

**Deliverables:**
1. ✅ Authentication foundation
   - `AuthController` with JWT login/refresh/logout
   - Sanctum tokens + optional JWT for mobile
   - Rate limiting middleware (60–100 req/min)

2. ✅ REST endpoints (Finance, Travel, Home)
   - PropertyController, TripController, AccountController, etc.
   - Form Requests for validation
   - API Resources (DTOs) for response transformation
   - Total: 15+ controllers covering all CRUD operations

3. ✅ GraphQL schema extension
   - Types: Account, Trip, Property, Transaction, Itinerary
   - Queries: paginated lists + single-item fetch
   - Mutations: create, update, delete (authorized via policies)

4. ✅ API documentation
   - OpenAPI 3.0 spec auto-generated
   - Swagger UI at `/api/docs`
   - Postman collection downloadable

5. ✅ Testing
   - Feature tests for all endpoints (80%+ coverage)
   - Auth + authorization tests
   - Rate limiting tests
   - Query count assertions (N+1 prevention)

### Phase 12: Health Integration (Weeks 3-4)

**Goal:** Medical records, appointments, vitals, reminders production-ready

**Deliverables:**
1. ✅ Health data models
   - Appointment, Prescription, LabResult, Doctor
   - Extend HealthRecord, MedicalRecord models
   - Migrations with user_id scoping

2. ✅ Health services
   - HealthMetricsService (vitals aggregation)
   - AppointmentService (scheduling + conflict detection)
   - PrescriptionService (dosage validation, refill tracking)

3. ✅ Health policies + observers
   - Policies: User can only see own health records
   - Observers: Appointment → Reminder Job dispatch
   - Jobs: SendAppointmentReminder, SendMedicationReminder

4. ✅ Health API controllers
   - AppointmentController (/api/v1/appointments)
   - HealthMetricController (/api/v1/health/metrics)
   - PrescriptionController (/api/v1/prescriptions)
   - Special endpoints: /upcoming, /trends, /active

5. ✅ Health validation + encryption
   - Vital range validation (BP, HR, weight, etc.)
   - Medication interaction checking
   - Sensitive field encryption at rest
   - Audit logging for HIPAA-like compliance

6. ✅ Reminders + notifications
   - Appointment reminder: 24h before + 1h before (push/SMS)
   - Medication adherence tracking
   - Refill alerts (30 days before expiry)

### Dependencies

```
Phase 11 (API Foundation) → Phase 12 (Health Integration)
  ↓ Must complete before starting:
  ✓ JWT auth working
  ✓ Rate limiting active
  ✓ API documentation generated
  ✓ Policy-based authorization tested
  ↓
Phase 12 can then build health-specific features on proven API layer
```

---

## 6. Implementation Checklist

### Pre-Development
- [ ] Review architecture with team
- [ ] Approve data models (Appointment, Doctor, LabResult, etc.)
- [ ] Finalize API response envelope format
- [ ] Decide pagination strategy (cursor vs offset)
- [ ] Plan notification channels (push, SMS, in-app)
- [ ] HIPAA/GDPR compliance assessment

### Phase 11: API Foundation
- [ ] JWT setup + Sanctum config
- [ ] AuthController + login/refresh/logout
- [ ] PropertyController + Form Requests + Resources
- [ ] TripController + ItineraryController
- [ ] AccountController + TransactionController
- [ ] HealthRecordController + MedicalRecordController
- [ ] GraphQL schema extension (all entity types)
- [ ] OpenAPI documentation generation
- [ ] Feature tests (80%+ coverage)
- [ ] Rate limiting tuning

### Phase 12: Health Integration
- [ ] Appointment model + migration
- [ ] Doctor model + migration
- [ ] Prescription model + migration
- [ ] LabResult model + migration
- [ ] HealthMetricsService + tests
- [ ] AppointmentService + conflict detection
- [ ] PrescriptionService + refill validation
- [ ] Policies (HealthMetric, Appointment, Prescription)
- [ ] Observers (AppointmentObserver, PrescriptionObserver)
- [ ] Reminder jobs (SendAppointmentReminder, etc.)
- [ ] Health validation rules (vitals, dosages, interactions)
- [ ] Encryption setup (sensitive fields)
- [ ] Audit logging (health data access)
- [ ] Health API controllers (all CRUD + special endpoints)
- [ ] Integration tests (privacy, conflicts, validation)

### Testing & QA
- [ ] Unit tests for services
- [ ] Feature tests for endpoints (auth, authorization, validation)
- [ ] N+1 query detection (`assertQueryCount()`)
- [ ] Rate limiting tests
- [ ] Privacy tests (user isolation)
- [ ] Soft delete tests
- [ ] Observer side effect tests
- [ ] Load testing (rate limit tuning)

### Deployment
- [ ] Migration strategy (zero-downtime)
- [ ] Rate limits tuned for production
- [ ] Encryption keys rotated
- [ ] Audit logging enabled
- [ ] Monitoring + alerting configured
- [ ] API documentation published
- [ ] Mobile team onboarded on endpoints

---

## 7. Risk Mitigation

### API Risks

| Risk | Mitigation |
|------|-----------|
| **Token leakage** | HTTPS only, short expiry (15 min), rotation on refresh, HTTP-only flag |
| **Health data exposure** | Audit logs, encryption at rest, policies enforce user scoping, test every endpoint |
| **API abuse** | Rate limiting (tiered), IP blocklisting, burst protection (max 5 req/sec) |
| **N+1 queries** | Eager load all relationships, assertQueryCount() in tests ≤ 5 |
| **Appointment conflicts** | Pessimistic locking, unique constraint on (doctor_id, date_time), validate before save |

### Health Risks

| Risk | Mitigation |
|------|-----------|
| **Vital validation failure** | Range validation (BP 60–200), flag out-of-range, doctor review |
| **Prescription errors** | Immutable fields, interaction checking, dosage validation, 422 on critical issues |
| **Appointment overboking** | UTC storage, conflict detection, doctor availability slots |
| **External API downtime** | Graceful fallback, manual data entry option, sync status visible |
| **Data breach** | Encryption at rest, HTTPS + HSTS, audit logs, 30-day grace period before hard delete |

---

## 8. Phase Timeline Estimate

| Phase | Tasks | Duration | Effort |
|-------|-------|----------|--------|
| **Phase 11** | API foundation (auth, REST, GraphQL, docs) | 2 weeks | ~35 pts |
| **Phase 12** | Health models, services, APIs, reminders | 2 weeks | ~30 pts |
| **Phase 13** | Integration tests, mobile app integration | 1 week | ~20 pts |
| **Phase 14** | Load testing, optimization, deployment | 1 week | ~15 pts |
| **Total** | | 6 weeks | ~100 pts |

**Parallel Work:** Mobile app can begin Phase 11 REST integration after week 1

---

## 9. Success Metrics

### Phase 11 (API Foundation)
✅ All CRUD endpoints implemented + tested (80%+ coverage)  
✅ Authentication working (JWT + Sanctum)  
✅ Rate limiting active (tiered limits per endpoint)  
✅ API documentation auto-generated + accessible  
✅ N+1 queries eliminated (assertQueryCount ≤ 5)  
✅ Authorization working (100% of endpoints check policies)  

### Phase 12 (Health Integration)
✅ Health models + migrations created  
✅ Appointment scheduling with conflict detection  
✅ Vital sign validation enforced  
✅ Medication interactions checked  
✅ Reminders working (24h + 1h before)  
✅ Encryption + audit logging enabled  
✅ Privacy tests passing (user isolation verified)  

---

## 10. Next Steps

1. **Team Review** → Approve architecture, data models, phasing
2. **Phase 11 Kickoff** → Start API foundation development
3. **Mobile Team Onboarding** → Provide API spec, set up integration
4. **Continuous Testing** → Each PR includes feature + unit tests
5. **Phase 12 Planning** → Design health validation rules, encryption strategy

---

## Appendix: Quick Reference

### Key Files Created
```
routes/api.php
app/Http/Controllers/Api/
  ├── AuthController.php
  └── V1/
      ├── PropertyController.php
      ├── TripController.php
      ├── AppointmentController.php (NEW)
      └── ... 15+ controllers

app/Http/Requests/
  ├── StorePropertyRequest.php
  └── ... 30+ form requests

app/Http/Resources/
  ├── PropertyResource.php
  └── ... 20+ resources

app/Models/
  ├── Appointment.php (NEW)
  ├── Prescription.php (NEW)
  ├── LabResult.php (NEW)

app/Services/
  ├── HealthMetricsService.php (NEW)
  ├── AppointmentService.php (NEW)

graphql/schema.graphql (extended)
database/migrations/ (4 new)
```

### Key Commands (Post-Implementation)
```bash
# Generate API docs
php artisan openapi:generate

# Run API tests
php artisan test tests/Feature/Api/

# Create health migration
php artisan make:model Appointment -m

# Queue jobs for reminders
php artisan queue:work
```

---

**Document Status:** ✅ Ready for Implementation  
**Prepared by:** Research Team  
**Reviewed:** Pending Team Sign-off  
**Next Review:** Post-Phase 11 Completion
