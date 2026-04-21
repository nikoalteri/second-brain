# v3.0 Implementation Roadmap: Mobile APIs & Health Integration

**Created:** 2026-04-12  
**Scope:** 3 weeks of backend development + 2-3 weeks parallel mobile app  
**Risk Level:** 🟢 LOW (additive, no breaking changes)

---

## Quick Reference: What Needs to Happen

### Before You Start
```
✅ Review STACK.md for detailed technical decisions
✅ Review existing architecture (services already work well)
✅ Discuss API contract design with mobile team (critical!)
```

### New Packages Required
```
composer require tymon/jwt-auth:^2.1
composer require vyuldashev/laravel-openapi:^3.0 --dev  # or prod if docs need to be public
```

### Skip These (Don't Add)
```
❌ laravel/passport
❌ Custom JWT code
❌ Separate GraphQL library
❌ Additional HIPAA/GDPR packages (v3.0 scope: structure only)
```

---

## Implementation Timeline

### Week 1: JWT Foundation + Auth

**Duration:** 2 days (4 half-days)
**Deliverable:** Mobile login/refresh flow working

**Tasks:**
1. Install & configure `tymon/jwt-auth`
   - Run: `php artisan vendor:publish --provider="Tymon\JWTAuth\Providers\LaravelServiceProvider"`
   - Generate secret: `php artisan jwt:secret`
   - Config: `config/auth.php` (add api guard with jwt)
   - Time: 30 min

2. Create `AuthController` (app/Http/Controllers/Api/V1/AuthController.php)
   - login(LoginRequest): returns access_token + refresh_token
   - refresh(): returns new access_token
   - logout(): blacklists current token
   - Time: 1 hour

3. Create API routes (routes/api.php + routes/api/v1/auth.php)
   - POST /api/v1/auth/login
   - POST /api/v1/auth/refresh
   - POST /api/v1/auth/logout
   - Time: 30 min

4. Create JWT middleware + exceptions
   - Validate token before route execution
   - Return 401 for invalid/expired tokens
   - Time: 1 hour

5. Test JWT flow
   - Test login returns tokens
   - Test refresh works
   - Test logout blacklists
   - Test 401 on invalid token
   - Time: 2 hours

**Tests to Write:**
```php
test('user can login with email and password');
test('login returns access_token and refresh_token');
test('user can refresh token before expiry');
test('refresh returns new access_token');
test('user cannot access protected route without token');
test('user cannot access protected route with expired token');
test('logout blacklists token');
```

**Go/No-Go Criteria:**
- ✅ POST /api/v1/auth/login returns tokens
- ✅ Tokens decode correctly
- ✅ POST /api/v1/auth/refresh works
- ✅ Protected routes return 401 without token
- ✅ Tests pass

---

### Week 1-2: REST Endpoints Foundation

**Duration:** 5 days (1 week)
**Deliverable:** All REST endpoints for Finance, Travel, Home functional

**Daily Breakdown:**

**Day 1: Finance Domain (Accounts, Transactions)**
- Create AccountController (list, show, store)
- Create TransactionController (list, show, store)
- Create form requests with validation
- Add routes: GET/POST /api/v1/accounts, /api/v1/transactions
- Time: 1 day (8 hours)

**Day 2: Travel Domain (Trips)**
- Create TripController (list, show, store, update)
- Add nested routes: /api/v1/trips/{id}/itineraries, /api/v1/trips/{id}/expenses
- Time: 8 hours

**Day 3: Home Domain (Properties, Maintenance)**
- Create PropertyController
- Create MaintenanceController
- Routes: /api/v1/properties, /api/v1/properties/{id}/maintenance
- Time: 6-8 hours

**Day 4: Pagination + Filtering**
- Add pagination helper to base ApiController
- Add filtering (by date range, category, type)
- Add sorting (by created_at, updated_at)
- Time: 4-6 hours

**Day 5: Error Handling + Response Formatting**
- Extend exception handler for JSON responses
- Create consistent response format (data, meta, links)
- Test all error scenarios (404, 422, 500)
- Time: 4-6 hours

**Tests to Write:** ~100 tests
```php
// For each endpoint:
test('authenticated user can list {resource}');
test('authenticated user can show {resource}');
test('authenticated user can create {resource}');
test('authenticated user can update {resource}');
test('authenticated user can delete {resource}');
test('unauthenticated user gets 401');
test('user cannot access another user\'s {resource}');
test('validation error returns 422 with details');
test('pagination works with per_page and page params');
test('filtering by {field} works');
```

**Go/No-Go Criteria:**
- ✅ All CRUD endpoints respond with 200/201/422
- ✅ Pagination works (page, per_page, total)
- ✅ Filtering by common fields works
- ✅ Authorization checks in place (user can't access other's data)
- ✅ 80 tests passing
- ✅ Response format consistent across all endpoints

---

### Week 2-3: GraphQL Mutations + Health

**Duration:** 3 days
**Deliverable:** GraphQL mutations for health domain, schema extended

**Tasks:**

**Day 1: GraphQL Schema Extension**
- Extend graphql/schema.graphql with new types:
  - Trip, Itinerary, TripExpense
  - HealthRecord, MedicalRecord, Medication
  - Appointment, Provider
  - Account, Transaction, CreditCard
- Add query resolvers (all with pagination)
- Time: 6-8 hours

**Day 2: GraphQL Mutations**
- Add mutations for health records
  - createHealthRecord(input): HealthRecord
  - updateMedicalRecord(id, input): MedicalRecord
  - scheduleAppointment(input): Appointment
- Add mutations for auth
  - login(email, password): AuthPayload
  - logout: Boolean
- Time: 6 hours

**Day 3: GraphQL Tests**
- Test each mutation with valid + invalid inputs
- Test authorization (user can't access other's data)
- Test nested queries (Trip with Itineraries)
- Time: 4 hours

**Tests to Write:** ~50 tests
```php
test('authenticated user can query trips');
test('mutation createHealthRecord creates record');
test('mutation returns validation errors');
test('query with nested relationships works');
test('paginated query returns meta + links');
```

**Go/No-Go Criteria:**
- ✅ All entities have GraphQL types
- ✅ Mutations work end-to-end
- ✅ Authorization enforced in resolvers
- ✅ Nested queries resolve correctly
- ✅ 50+ tests passing

---

### Week 3: API Documentation + Polish

**Duration:** 2 days
**Deliverable:** OpenAPI docs at /api/docs, all tests passing

**Tasks:**

**Day 1: OpenAPI Setup**
- Install vyuldashev/laravel-openapi
- Add OpenAPI annotations to all controllers:
  ```php
  /**
   * @OA\Get(
   *     path="/api/v1/trips",
   *     summary="List trips",
   *     @OA\Response(response=200, description="List of trips")
   * )
   */
  public function index() { ... }
  ```
- Generate OpenAPI spec: `php artisan openapi:generate`
- Deploy Swagger UI (package includes)
- Time: 4-6 hours

**Day 2: Polish**
- Rate limit tuning (test with load generator)
- Cache optimization (add redis or file cache)
- Error message consistency
- Documentation for mobile team
- Time: 4 hours

**Go/No-Go Criteria:**
- ✅ /api/docs endpoint returns Swagger UI
- ✅ /api/v1/openapi.json spec is valid
- ✅ Mobile team can import spec into Postman/Insomnia
- ✅ All endpoints documented with examples

---

### Week 3-4: Testing Sprint

**Duration:** 1 week (4-5 days)
**Deliverable:** 150+ tests passing, 80%+ API coverage

**Focus Areas:**

1. **API Endpoint Tests** (100 tests)
   - CRUD for all resources
   - Authorization checks
   - Validation error handling
   - Pagination edge cases
   - Rate limiting

2. **GraphQL Tests** (50 tests)
   - Query resolution
   - Mutation handling
   - Nested relationships
   - Authorization

3. **Integration Tests** (20 tests)
   - Login → Create Trip → Logout flow
   - Health metric tracking end-to-end
   - Medical record upload + FHIR export

4. **Load Tests** (manual, not automated)
   - Rate limit kicks in at 60 req/min
   - Cache prevents duplicate DB queries
   - Response time < 200ms for simple queries

**Test Command:**
```bash
php artisan test tests/Feature/Api
php artisan test tests/Feature/GraphQL
php artisan test --coverage --min=80
```

**Go/No-Go Criteria:**
- ✅ 150+ tests passing
- ✅ Coverage ≥ 80% on API layer
- ✅ All tests pass in CI/CD (GitHub Actions)
- ✅ Load test shows reasonable response times

---

### Week 4: Health Integration (FHIR)

**Duration:** 2-3 days
**Deliverable:** Medical records exportable as FHIR, appointments integrated

**Tasks:**

1. **Extend Medical Records Model**
   - Add FHIR fields (fhir_type, fhir_code, fhir_payload, is_external)
   - Migration: `php artisan make:migration add_fhir_to_medical_records`
   - Time: 1 hour

2. **Add Appointment Model** (if not exists)
   - date, time, provider_id, status, notes
   - Relationships: User, Provider
   - Time: 2 hours

3. **FHIR Export**
   - Create FhirExporter service
   - Convert MedicalRecord → FHIR Observation
   - Convert Appointment → FHIR Encounter
   - Endpoint: GET /api/v1/medical-records/{id}/fhir
   - Time: 4 hours

4. **Health Metrics Enums**
   - Map HealthRecord types to FHIR LOINC codes
   - Document standard codes in enum comments
   - Time: 2 hours

5. **Tests**
   - FHIR export produces valid JSON
   - FHIR codes map correctly
   - Can import FHIR data (reverse)
   - Time: 3 hours

**Go/No-Go Criteria:**
- ✅ Medical records have FHIR payload
- ✅ GET /api/v1/medical-records/{id}/fhir returns valid FHIR JSON
- ✅ LOINC codes documented for all health metrics
- ✅ Tests validate FHIR structure

---

## Parallel: Mobile App Development

### Timeline
- **After Week 1:** Mobile team can start with login flow
- **After Week 1-2:** Full REST API available, can build main screens
- **After Week 3:** Full API + docs ready, mobile app can complete integration

### Mobile Team Checklist
```
Week 1: Use AuthController endpoints
  [ ] Implement login flow
  [ ] Store access + refresh tokens (Keychain/Keystore)
  [ ] Refresh token before expiry
  [ ] Handle 401 → prompt login

Week 2: Integrate REST endpoints
  [ ] Implement Trip list screen
  [ ] Implement Account list screen
  [ ] Implement Health record input
  [ ] Test pagination

Week 3: Polish + Launch
  [ ] Add offline support (cache last fetch)
  [ ] Add error handling for network failures
  [ ] Performance testing
  [ ] Beta launch
```

---

## Infrastructure & Deployment

### Before v3.0 Release

**1. Environment Setup**
```env
JWT_SECRET=<generated by: openssl rand -hex 32>
JWT_ALGORITHM=HS256
JWT_EXPIRATION=3600          # 1 hour
JWT_REFRESH_EXPIRATION=2592000  # 30 days

CACHE_DRIVER=redis           # or file for dev
CACHE_REDIS_HOST=127.0.0.1
CACHE_REDIS_PASSWORD=null
CACHE_REDIS_PORT=6379

API_RATE_LIMIT=60
API_RATE_LIMIT_WINDOW=60     # seconds

FHIR_EXPORT_ENABLED=true
MEDICAL_RECORD_ENCRYPTION=true
```

**2. Database Migrations**
```bash
php artisan make:migration create_api_token_blacklist_table
php artisan make:migration add_fhir_to_medical_records
php artisan migrate
```

**3. GitHub Actions (CI/CD)**
```yaml
# .github/workflows/api-tests.yml
on: [push, pull_request]
jobs:
  api-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Run API tests
        run: php artisan test tests/Feature/Api --coverage --min=80
      - name: Validate OpenAPI spec
        run: php artisan openapi:generate --validate
```

**4. Deployment Checklist**
```
[ ] All tests passing (150+)
[ ] OpenAPI spec valid
[ ] Rate limiting tested
[ ] CORS configured for mobile domain
[ ] JWT secret rotated in production
[ ] Redis instance provisioned (if production)
[ ] Database backups enabled
[ ] Logging configured (API requests)
[ ] Monitoring set up (response times, errors)
[ ] Mobile team has API docs (link to /api/docs)
```

---

## Team Coordination

### Kickoff Meeting (Before Week 1)
**Duration:** 1 hour
**Attendees:** Backend lead, mobile lead, product

**Agenda:**
1. Review STACK.md findings (15 min)
2. API contract design discussion (20 min)
   - Endpoint naming conventions
   - Pagination style (offset vs cursor)
   - Error response format
   - Authentication flow details
3. Timeline + milestones (10 min)
4. Dependencies + blockers (10 min)
5. Q&A (5 min)

**Outputs:**
- ✅ Agreed API specification (OpenAPI draft)
- ✅ Mobile team can start design/setup
- ✅ Backend team starts Phase 0 (JWT)

### Weekly Syncs (15 min standups)
- What's done?
- What's next?
- Any blockers?
- API contract changes?

### Phase Gate Meetings
- **After Phase 0:** Demo login flow
- **After Phase 1:** Demo REST endpoints + Swagger UI
- **After Phase 3:** Demo full API + mobile app integration
- **After Phase 4:** Launch v3.0-beta

---

## Success Criteria for v3.0 Release

### Backend Readiness
- [ ] 150+ tests passing (REST + GraphQL)
- [ ] 80%+ code coverage on API layer
- [ ] OpenAPI spec valid + accessible at /api/docs
- [ ] All CRUD endpoints for Finance/Travel/Home working
- [ ] JWT login/refresh/logout tested
- [ ] Rate limiting tuned + tested
- [ ] Medical records have FHIR export
- [ ] All environment variables documented
- [ ] Database migrations clean + reversible
- [ ] GitHub Actions green (no failing builds)

### Mobile App Readiness
- [ ] All REST endpoints integrated
- [ ] Token refresh flow working
- [ ] Pagination implemented
- [ ] Error handling + retry logic
- [ ] Offline mode (if required)
- [ ] Performance acceptable (<200ms per request)
- [ ] Tested on iOS + Android (if native)

### Documentation Readiness
- [ ] API docs at /api/docs (Swagger UI)
- [ ] OpenAPI spec exportable (Postman, Insomnia)
- [ ] Lighthouse GraphQL schema documented
- [ ] FHIR export documented (for integrations)
- [ ] Mobile team has integration guide
- [ ] Rate limiting limits documented
- [ ] Error codes documented

### Security Checklist
- [ ] JWT secret rotated in production
- [ ] CORS configured correctly
- [ ] Medical records encrypted (optional v3.0, required v3.1)
- [ ] Authorization checks on all endpoints
- [ ] Input validation on all endpoints
- [ ] Rate limiting enforced
- [ ] Secrets not in .env.example
- [ ] HTTPS enforced (production)

---

## What NOT to Do (Pitfalls)

❌ **Don't skip the API design meeting**
- Will cause breaking changes later
- Invest 1 hour upfront to save 5 days later

❌ **Don't expose raw Eloquent models**
- Use ApiResource or Fractal transformers
- Prevents accidental secret leaks

❌ **Don't build custom JWT**
- Use tymon/jwt-auth (battle-tested)
- Custom code has security bugs

❌ **Don't skip tests**
- APIs are invisible without tests
- Aim for 80%+ coverage

❌ **Don't add HIPAA/GDPR compliance in v3.0**
- Scope medical records as structured data first
- Add compliance layer in v3.1 if needed

❌ **Don't ignore rate limiting**
- Protect against abuse (spam health records, etc.)
- Test it works

---

## Next Steps (Today)

1. ✅ Review STACK.md (full technical details)
2. ✅ Review STACK_SUMMARY.txt (quick reference)
3. ⏭️ Schedule kickoff meeting (1 hour)
4. ⏭️ Draft API specification (OpenAPI format)
5. ⏭️ Assign Phase 0 owner (JWT setup)
6. ⏭️ Assign Phase 1 owner (REST endpoints)

---

## FAQ

**Q: Why JWT instead of Sanctum?**
A: Sanctum tokens are opaque UUIDs. JWT enables standard refresh token cycles (access + refresh). Mobile apps need both for security.

**Q: Why REST + GraphQL?**
A: REST for simple CRUD (mobile prefers). GraphQL for complex queries (solves N+1). Offer both, let mobile team choose.

**Q: Do we need Redis for caching?**
A: No for dev. Yes for production (distributed cache). Start with file cache, add Redis later.

**Q: When do we add HIPAA compliance?**
A: v3.1 (post-launch). v3.0 focuses on structure + data import/export capability.

**Q: Can mobile team start before REST is done?**
A: Yes, after Phase 0 (JWT). They can build login screen while backend builds REST endpoints.

**Q: How long does JWT setup take?**
A: 30 min install + 2 hours build AuthController + tests = ~2.5 hours.

**Q: Do I need a GraphQL subscription server?**
A: Not for v3.0. Add WebSocket support in v3.1 if real-time appointments needed.

