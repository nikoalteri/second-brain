# API & Health Integration Pitfalls

> **Document Purpose:** Catalog of known pitfalls and prevention strategies for Phase 11 (Mobile API Layer) and Phase 12 (Health Integration) in Second Brain.
>
> **Last Updated:** 2026-04-11  
> **Applies to:** Laravel 12 + Filament 4 admin system with Travel/Home/Finance domains

---

## Table of Contents

1. [API Security Pitfalls](#api-security-pitfalls)
2. [Health Data Pitfalls](#health-data-pitfalls)
3. [Integration Pitfalls](#integration-pitfalls)
4. [Prevention Checklist](#prevention-checklist)
5. [Phase Assignment](#phase-assignment)
6. [Quality Gates](#quality-gates)

---

## API Security Pitfalls

### 1.1 N+1 Query Problems with Nested Resources

**Problem:**
- API endpoints returning nested resources without eager loading cause exponential queries
- Each activity loops and queries related participants, expenses separately
- Filament resources mitigate this via admin UI, but APIs lack lazy-load protection
- Example: Getting 100 trips with participants = 1 query (trips) + 100 queries (participants) = 101 total

**Prevention Strategy:**

| Control | Implementation |
|---------|-----------------|
| **Eager Load by Default** | Create `ApiResource` trait: `public static array $with = ['participants', 'expenses'];` |
| **Query Analysis Middleware** | Add debugbar in dev; log query counts per endpoint in production |
| **Repository Pattern** | Use repositories for API queries; force `.with()` at query builder level |
| **Request Validation** | Limit `include` param depth: `'include' => 'sometimes\|in:activities,participants'` |
| **Test Coverage** | Add `assertQueryCount()` assertions in feature tests for each API endpoint |

**Phase Assignment:** **Phase 11**  
**Severity:** 🔴 Critical  
**QA Gate:** All API endpoints must have `assertQueryCount()` ≤ 5 queries for paginated lists

---

### 1.2 Over-Exposing User Data (Scoping Failures)

**Problem:**
- API exposes data without proper `whereUserId()` scoping
- Admin/Filament enforces scoping via policies, but APIs skip them
- Example: `/api/trips/1` returns user A's trip even if user B makes request
- Health data especially sensitive: vitals, appointments, medications visible to wrong users

**Prevention Strategy:**

| Control | Implementation |
|---------|-----------------|
| **Automatic Scoping** | Use `HasUserScoping` trait; apply to all API model queries |
| **Policy Enforcement in API** | Wrap API endpoints with `authorize()` call before loading; mirror Filament policies |
| **Global Scope on Models** | Add `->where('user_id', auth()->id())` via global scope or repository |
| **API Route Middleware** | Middleware: verify token owns resource before returning |
| **Explicit Relationship Filtering** | Never return nested relationships without scope |

**Phase Assignment:** **Phase 11**  
**Severity:** 🔴 Critical — Privacy/compliance risk  
**QA Gate:** 100% of API endpoints must pass `auth()->user() != resource.user_id → 403` test

---

### 1.3 Token Expiration and Refresh Token Vulnerabilities

**Problem:**
- JWT or session tokens expire without clear refresh mechanism
- Clients store refresh tokens insecurely (plaintext, no expiration)
- Token rotation not implemented: same token valid indefinitely
- No token revocation on logout; stale tokens still work

**Prevention Strategy:**

| Control | Implementation |
|---------|-----------------|
| **Short Expiry on Access Tokens** | 15–30 min expiry; refresh forced frequently |
| **Rotation on Refresh** | New refresh token issued with each access token refresh |
| **Token Blacklist on Logout** | Add token to `invalidated_tokens` table with timestamp |
| **Blacklist Cleanup Job** | Nightly: purge expired tokens from blacklist |
| **Refresh Token Constraints** | Max 1 refresh token per device/IP; revoke old on new login |
| **HTTP-Only Cookies** | Use `httpOnly` flag on refresh tokens |
| **Signed Tokens** | Use Laravel Sanctum; sign tokens with app key |

**Phase Assignment:** **Phase 11**  
**Severity:** 🔴 Critical  
**QA Gate:** 
- Access tokens expire in ≤ 30 min
- Refresh tokens rotate on each use
- Logout revokes all tokens immediately

---

### 1.4 Rate Limiting Abuse

**Problem:**
- Existing `ApiRateLimitMiddleware` hardcodes 60 requests/minute per IP/user
- No distinction between expensive (search) vs cheap (read) operations
- No burst protection; attackers hammer endpoint at start of minute
- Health endpoints need stricter limits than travel reads

**Prevention Strategy:**

| Control | Implementation |
|---------|-----------------|
| **Tiered Rate Limits** | Different limits per endpoint class (read: 100/min, write: 20/min, search: 10/min) |
| **Burst Protection** | Allow 5 requests/second max, then throttle |
| **Health Endpoints Stricter** | Vitals, appointments: 10/min; finance reports: 20/min |
| **Cost-Based Weighting** | Expensive queries: 5 req/min; simple reads: 100 req/min |
| **Graceful Degradation** | Return `Retry-After` header; queue expensive requests instead of rejecting |
| **Monitoring & Alerts** | Log when user hits 80% of limit |

**Phase Assignment:** **Phase 11**  
**Severity:** 🟠 High  
**QA Gate:** 
- Health endpoints: ≤ 30 req/min
- Write endpoints: ≤ 20 req/min
- Search endpoints: ≤ 5 req/min

---

### 1.5 CORS Misconfiguration

**Problem:**
- Filament expects same-domain admin access; CORS open to all origins
- Mobile API needs specific origin (mobile app domain), not `*`
- Setting `Access-Control-Allow-Origin: *` + credentials = broken security
- Health data especially sensitive; CORS breach = data leak

**Prevention Strategy:**

| Control | Implementation |
|---------|-----------------|
| **Whitelist Origins** | Only allow known mobile app domains, not `*` |
| **No Credentials with Wildcard** | If using `*`, never include `credentials: include` |
| **Separate Middleware** | Different CORS rules for `/api` vs `/admin` |
| **Environment-Specific** | Dev: localhost; Prod: app.second-brain.com only |
| **Preflight Caching** | Allow browsers to cache preflight responses (8-24 hours) |
| **Audit CORS Headers** | Log any CORS rejections |

**Phase Assignment:** **Phase 11**  
**Severity:** 🔴 Critical  
**QA Gate:** 
- API fails requests from unauthorized origins
- Test: Curl from unknown.com → 403
- Test: Curl from app.second-brain.com → 200

---

## Health Data Pitfalls

### 2.1 Medical Data Privacy (HIPAA-like Compliance)

**Problem:**
- Health data (vitals, appointments, medications) subject to stricter privacy rules
- No explicit encryption at rest or in transit
- Audit trails missing: can't prove who accessed what when
- Data retention policy undefined
- No data export/deletion mechanisms for GDPR/HIPAA

**Prevention Strategy:**

| Control | Implementation |
|---------|-----------------|
| **Database Encryption** | Encrypt `sensitive_value` column via `encrypted()` casting |
| **Transport Layer** | HTTPS only; API enforces `Strict-Transport-Security` header |
| **Audit Logging** | Every health record read/write logged: user, timestamp, action, IP |
| **Data Retention Policy** | Health records > 7 years auto-archived; > 10 years deleted |
| **Data Export/Deletion** | Commands: `health:export-user-data` and `health:delete-user-data` |
| **Access Logging** | Sensitive endpoints logged; admin audit trail in Filament |
| **Encryption Key Rotation** | Annual key rotation |

**Phase Assignment:** **Phase 12**  
**Severity:** 🔴 Critical  
**QA Gate:** 
- All health endpoints log audit trail
- Sensitive columns encrypted at rest
- Data export command produces valid JSON
- Data deletion command removes all records within 24 hours
- HTTPS enforced; HSTS header present

---

### 2.2 Data Validation (Vital Signs Ranges, Med Interactions)

**Problem:**
- Vital signs stored without validation: blood pressure 999/999 accepted
- Medications entered without interaction checking
- Weight recorded as -50 kg without range validation
- No dosage validation; dangerous combinations not flagged

**Prevention Strategy:**

| Control | Implementation |
|---------|-----------------|
| **Vital Range Validation** | Request validation rules: `systolic: between:60,200` |
| **Medical Interaction DB** | Lookup table: `medication_interactions` with severity levels |
| **Dosage Ranges** | Validation per medication |
| **Drug-Drug Interaction Checks** | Before saving medication, check current meds |
| **Warning vs Error** | Interactions flagged as warnings; contraindications as errors |
| **Professional Review** | Flagged records marked for doctor review |
| **Lab Result Validation** | Results compared against normal ranges |

**Phase Assignment:** **Phase 12**  
**Severity:** 🔴 Critical  
**QA Gate:** 
- Vitals outside valid ranges rejected (422)
- Critical medication interactions block save (422)
- Non-critical interactions save but flag `flagged_for_review = true`
- Lab results with abnormal values auto-flagged

---

### 2.3 Appointment Scheduling Conflicts

**Problem:**
- Two appointments booked at same time (overbooking)
- Cascading deletes without canceling doctor's calendar
- No timezone handling: appointment "2 PM" ambiguous
- Recurring appointments not synchronized
- Double-booking of doctor resources

**Prevention Strategy:**

| Control | Implementation |
|---------|-----------------|
| **Timezone-Aware Timestamps** | Store all appointments as UTC; convert on display |
| **Conflict Detection** | Before save, check no existing appointments in time range for doctor |
| **Doctor Availability** | Doctor sets available time slots; appointments must fit |
| **Appointment Lock** | Lock during modification to prevent race conditions |
| **Sync with Calendars** | Apple Calendar, Google Calendar sync |
| **Cancellation Cascade** | Cancel user appointment → notify doctor |
| **Recurring Rule Validation** | RFC 5545 `rrule` format |

**Phase Assignment:** **Phase 12**  
**Severity:** 🟠 High  
**QA Gate:** 
- Appointments stored in UTC with user timezone separate
- Conflicting appointments return 422
- Recurring appointments correctly generate instances
- Doctor unavailable times block creation

---

### 2.4 Integration with External Health APIs (Apple HealthKit, Google Fit)

**Problem:**
- API credentials stored unencrypted in config/DB
- Token refresh fails silently; stale tokens cause data gaps
- Apple HealthKit/Google Fit API changes break integration without fallback
- Data sync direction ambiguous
- User revokes app access; our tokens still "valid"
- Rate limiting from external API not handled

**Prevention Strategy:**

| Control | Implementation |
|---------|-----------------|
| **Credential Encryption** | Store API tokens in `.env` or encrypted config; never in DB |
| **Token Refresh Strategy** | Refresh proactively before expiry; handle 401 gracefully |
| **Sync Direction** | Clearly define: vitals entered in app → sync TO Apple? Or FROM Apple? |
| **Revocation Detection** | Periodic auth check to verify token still valid |
| **Rate Limit Handling** | Exponential backoff; queue failed syncs for retry |
| **Fallback Mode** | If sync fails, app still works locally |
| **Sync Status Visibility** | Show user: "Last synced X hours ago" |
| **API Versioning** | Pin to specific API version; deprecation planning |

**Phase Assignment:** **Phase 12**  
**Severity:** 🔴 Critical  
**QA Gate:** 
- Health integration tokens stored encrypted
- Token refresh succeeds before expiry
- Sync failures queue retry job
- Sync status visible to user
- Revoking integration blocks future syncs

---

### 2.5 Prescription Management Security

**Problem:**
- Prescriptions stored without encryption
- Dosage/frequency/refills editable without validation
- No refill tracking: user refills 3 times when only 1 allowed
- No consent tracking
- Controlled substances not flagged for monitoring

**Prevention Strategy:**

| Control | Implementation |
|---------|-----------------|
| **Prescription Encryption** | Encrypted storage for medication name, strength, dosage |
| **Immutable Fields** | Once created, medication_name, strength, dosage are read-only |
| **Refill Tracking** | Track each refill; can't exceed original count |
| **Controlled Substance Flagging** | Flag opioids, benzodiazepines for monitoring |
| **Consent Audit Trail** | When shared with provider, log: date, patient consent |
| **Pharmacy Verification** | Validate refills against pharmacy records |
| **Expiration Rules** | Prescriptions expire after 1 year |

**Phase Assignment:** **Phase 12**  
**Severity:** 🔴 Critical  
**QA Gate:** 
- Prescription fields immutable after creation (PATCH → 422)
- Refill request rejected if exceeds original quantity
- Refill rejected if prescription expired
- Controlled substance refills trigger notification
- Sharing requires explicit consent; audit log created

---

## Integration Pitfalls

### 3.1 API Auth Mechanism Conflicts with Filament Auth

**Problem:**
- Filament uses session-based auth (`web` guard); API needs token auth (`sanctum` guard)
- Same `User` model but different guards; `auth()->user()` returns different results per route
- Filament admin logged in but API token invalid
- Shared models accessed via both paths; scoping rules differ
- Global scopes apply to both, but policies might not

**Prevention Strategy:**

| Control | Implementation |
|---------|-----------------|
| **Explicit Guard in Scoping** | Use `auth('sanctum')->id()` not `auth()->id()` |
| **Dedicated API Resources** | Separate `ApiResource` classes using `auth('sanctum')->user()` |
| **Policy Awareness** | Policies check correct guard |
| **Test Both Auth Paths** | Feature tests for session AND token auth |
| **Auth Middleware** | API routes use `auth:sanctum`; admin uses `auth:web` |
| **Guard Fallback** | If `auth('sanctum')->id()` is null, try `auth('web')->id()` |

**Phase Assignment:** **Phase 11**  
**Severity:** 🔴 Critical  
**QA Gate:** 
- API endpoints return 401 if token missing
- API endpoints return 403 if user not resource owner
- Filament returns 302 if session missing
- Global scopes work with both guards
- Feature tests cover both paths for shared models

---

### 3.2 Observer Behavior Differs Between Admin and API (Side Effects!)

**Problem:**
- Observers trigger on model save regardless of source
- API creates trip → observer sends notification → API response includes queued job
- Observers assume UI context; API requests don't need all side effects
- Race conditions: observer fires before API response sent

**Prevention Strategy:**

| Control | Implementation |
|---------|-----------------|
| **Source Detection in Observer** | Check `request()->expectsJson()` and skip heavy side effects |
| **Conditional Observers** | Use `skipIf` condition |
| **Explicit Side Effects via Job** | API creates model, then explicitly dispatches job if needed |
| **Observer Timing** | Move expensive work to queued jobs |
| **Response Includes Metadata** | API response indicates: `"pending_notifications": 1` |
| **Separate Observer for API** | Create `ApiTripObserver` with fewer side effects |

**Phase Assignment:** **Phase 11**  
**Severity:** 🟠 High  
**QA Gate:** 
- Observers skip heavy operations when `request()->expectsJson() === true`
- API response includes `_meta.pending_actions`
- Filament triggers notifications, API doesn't (unless requested)
- API POST response time < 1s

---

### 3.3 Transaction Handling in Long-Running API Requests

**Problem:**
- Long-running tasks hold database transactions open
- API caller gets response before transaction commits
- Transaction rolled back due to error; response already sent
- Concurrent requests conflict

**Prevention Strategy:**

| Control | Implementation |
|---------|-----------------|
| **Async Long-Running Tasks** | Use queued jobs, not synchronous requests |
| **Immediate Feedback** | Return 202 Accepted with job ID |
| **Status Check Endpoint** | Client polls `/api/v1/jobs/{id}/status` |
| **Bulk Operations as Jobs** | `BulkImportTripsJob` etc. |
| **No Transactions for Async** | Jobs handle own transactions per batch |
| **Failure Handling** | Job updates status to `failed` with error details |

**Phase Assignment:** **Phase 11**  
**Severity:** 🟠 High  
**QA Gate:** 
- Bulk operations (>100 items) return 202 with job ID
- Job status endpoint returns accurate progress
- Concurrent operations for same user return 409 or queue with retry
- API response time for bulk ops < 500ms
- Job status shows errors/warnings on partial failure

---

### 3.4 Soft Deletes vs API Visibility

**Problem:**
- Models use `SoftDeletes`; deleted records hidden from queries by default
- But API might return soft-deleted records in some cases
- User deletes trip; API still shows it
- Health records deleted; restored by mistake; privacy breach

**Prevention Strategy:**

| Control | Implementation |
|---------|-----------------|
| **Explicit Policy on Soft Deletes** | All deletions via API = soft delete; only admins hard-delete |
| **API Always Excludes Soft Deleted** | Use `.withoutTrashed()` |
| **Restore Requires Authorization** | Only admins can restore |
| **Consistency Between Admin & API** | Same deletion behavior |
| **Audit Trail for Deletes** | Log soft delete + hard delete separately |
| **Grace Period for Restoration** | 30-day grace before hard delete |

**Phase Assignment:** **Phase 11**  
**Severity:** 🟠 High  
**QA Gate:** 
- Soft-deleted records return 404 (not 200)
- API deletion uses soft delete, not hard delete
- Hard-delete endpoint requires admin role
- Restore enforces 30-day grace period
- Deletion logged with user, timestamp, reason

---

## Prevention Checklist

### Phase 11: Mobile API Layer

**Query Optimization**
- [ ] All API resources have eager-loaded relationships
- [ ] Feature tests assert query count ≤ 5 per endpoint
- [ ] Pagination limits set (25–100 items max)

**Authentication & Scoping**
- [ ] API guard is `sanctum`, explicit in route middleware
- [ ] All routes use `auth:sanctum` middleware
- [ ] Global scopes work with both `auth('sanctum')` and `auth('web')`
- [ ] 100% of endpoints pass ownership checks

**Rate Limiting**
- [ ] Tiered limits: read (100/min), write (20/min), search (5/min)
- [ ] Health endpoints capped at 30/min
- [ ] Burst protection: max 5 requests/second
- [ ] 429 responses include `Retry-After` header

**CORS Configuration**
- [ ] Only whitelisted origins allowed
- [ ] No wildcard + credentials combination
- [ ] Separate middleware for API vs admin
- [ ] CORS rejections logged

**Transactions**
- [ ] No operations > 5s in synchronous endpoints
- [ ] Bulk operations use async jobs
- [ ] Job status endpoint returns progress
- [ ] `WithoutOverlapping` prevents concurrent ops

**Soft Deletes**
- [ ] API uses soft delete only
- [ ] Deleted records return 404
- [ ] Restore enforces 30-day grace period
- [ ] Deletions audited

**Observers**
- [ ] Observers skip heavy side effects for API
- [ ] API response includes `_meta` on pending actions
- [ ] Tests verify: Filament triggers notifications, API doesn't

### Phase 12: Health Integration

**Privacy & Compliance**
- [ ] Sensitive health data encrypted at rest
- [ ] HTTPS enforced; HSTS header present
- [ ] Audit trail for all health access
- [ ] Data export command works
- [ ] Data deletion command works

**Validation**
- [ ] Vital signs validated against ranges
- [ ] Medication interactions checked
- [ ] Critical interactions block save (422)
- [ ] Lab results validated against normal ranges
- [ ] Dosage ranges enforced

**Appointments**
- [ ] All appointments stored in UTC
- [ ] User timezone stored separately
- [ ] Conflict detection prevents double-booking
- [ ] Doctor availability slots enforced
- [ ] Recurring appointments use RFC 5545 rrule
- [ ] Cancellation cascades to provider

**External Health APIs**
- [ ] API credentials encrypted, in `.env` only
- [ ] Token refresh proactive
- [ ] Sync failures queue retry job
- [ ] Sync status visible to user
- [ ] Revocation detection works
- [ ] API version pinned

**Prescriptions**
- [ ] Prescription fields immutable after creation
- [ ] Refill validation enforced
- [ ] Refill blocked if expired
- [ ] Controlled substance refills trigger notification
- [ ] Sharing requires explicit consent
- [ ] Consent audit trail exists

---

## Phase Assignment

| Pitfall | Phase | Severity |
|---------|-------|----------|
| N+1 Queries | 11 | 🔴 Critical |
| Over-Exposed Data | 11 | 🔴 Critical |
| Token Expiration | 11 | 🔴 Critical |
| Rate Limiting | 11 | 🟠 High |
| CORS Misconfiguration | 11 | 🔴 Critical |
| Health Privacy | 12 | 🔴 Critical |
| Vital Validation | 12 | 🔴 Critical |
| Appointments | 12 | 🟠 High |
| External APIs | 12 | 🔴 Critical |
| Prescriptions | 12 | 🔴 Critical |
| Auth Conflicts | 11 | 🔴 Critical |
| Observer Side Effects | 11 | 🟠 High |
| Transaction Handling | 11 | 🟠 High |
| Soft Deletes | 11 | 🟠 High |

---

## Quality Gates

### Pre-Phase 11 Gate

✓ All API resources have eager-loading  
✓ Authentication scoping passes ownership checks (100%)  
✓ Rate limiting tiers defined (≥3 distinct limits)  
✓ CORS configuration whitelists only known origins  
✓ No long-running synchronous operations  
✓ Soft delete policy documented  

### Pre-Phase 12 Gate

✓ Encryption migration exists for health data  
✓ Audit logging configured for health endpoints  
✓ ≥10 validation tests passing  
✓ Medication interaction database designed  
✓ Appointment timezone handling planned  
✓ External API credential strategy approved  

---

**Document Status:** Ready for Phase 11 Planning  
**Last Review:** 2026-04-11  
**Next Update:** After Phase 11 completion  
