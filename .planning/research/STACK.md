# Stack Research: v3.0 Mobile APIs & Health Integration

**Analysis Date:** 2026-04-12  
**Target Version:** Laravel 12 + Filament 4 → v3.0  
**Focus:** GraphQL/REST APIs for Mobile + Health Integration (Medical Records)

---

## Executive Summary

v3.0 requires adding:
1. **Mobile API Layer** (GraphQL + REST) for Travel, Home, Finance domains
2. **Health Integration** (Medical Records, Appointments, Prescriptions)
3. **JWT Authentication** with refresh tokens (replacing/augmenting Sanctum tokens)
4. **API Documentation** (OpenAPI/Swagger)
5. **Rate Limiting** and caching strategies
6. **Health Data Standards** (FHIR, HL7 for medical records interoperability)

**Good News:** You already have Lighthouse GraphQL. The foundation is solid.  
**Bad News:** Sanctum + Session guards don't fully support mobile JWT workflows. We need augmentation.

---

## 1. GraphQL Implementation (Lighthouse — KEEP & EXTEND)

### Current State
✅ **Lighthouse 6.65** already installed and configured  
✅ Basic GraphQL schema in `graphql/schema.graphql`  
✅ Minimal but functional setup

### What's Working
- Schema-driven API design (type-safe contracts)
- Directives for authorization (`@can`, `@auth`)
- Pagination support (`@paginate`)

### What Needs Enhancement

#### A. Extend GraphQL Schema Coverage

**Required Additions:**
```
Domains to expose:
- Travel: Trip, Itinerary, TripExpense, TripParticipant, Destination
- Home: Property, MaintenanceRecord, UtilityBill, Inventory
- Finance: Account, Transaction, CreditCard, Subscription, Loan
- Health: HealthRecord, MedicalRecord, Medication, BloodTest, Appointment, Provider
```

**Challenge:** Current schema is bare (only User, query by id/email). Need to:
1. Add type definitions for all entities
2. Add mutations for mobile mutations (create, update, delete)
3. Add subscription support (for real-time appointment reminders, health alerts)
4. Define custom scalars (Date, DateTime, JSON for medical records)

**Rationale for Lighthouse:**
- ✅ Excellent for Laravel ecosystem
- ✅ Works seamlessly with Eloquent models
- ✅ Directives for auth/validation (`@rules`, `@can`)
- ⚠️ Learning curve for subscriptions (requires WebSocket setup)
- ⚠️ Not the fastest resolver for deeply nested queries (but acceptable for your scale)

**Alternative Considered:** `rebing/graphql-laravel`
- Simpler setup, more manual
- **Decision:** Stick with Lighthouse (you've already invested)

---

## 2. REST API Standards & Best Practices

### Current State
❌ `routes/api.php` exists but empty  
❌ No REST endpoints defined  
❌ Only Lighthouse GraphQL available

### What to Add

#### A. REST Endpoints for Mobile Consumption
Target endpoints for v3.0:

```
Finance Domain:
  GET    /api/v1/accounts               # List accounts (paginated)
  GET    /api/v1/accounts/{id}          # Account details + transactions
  POST   /api/v1/accounts               # Create account
  GET    /api/v1/transactions           # List user transactions (filtered, paginated)
  POST   /api/v1/transactions           # Create transaction
  GET    /api/v1/subscriptions          # List subscriptions

Travel Domain:
  GET    /api/v1/trips                  # List trips
  POST   /api/v1/trips                  # Create trip
  GET    /api/v1/trips/{id}             # Trip with itineraries
  POST   /api/v1/trips/{id}/itineraries # Add itinerary
  GET    /api/v1/trips/{id}/expenses    # Trip expenses

Home Domain:
  GET    /api/v1/properties             # List properties
  GET    /api/v1/properties/{id}        # Property with maintenance history
  POST   /api/v1/properties/{id}/maintenance # Log maintenance record

Health Domain:
  GET    /api/v1/health-records         # List health metrics
  POST   /api/v1/health-records         # Log health metric
  GET    /api/v1/medical-records        # List medical records
  POST   /api/v1/medical-records        # Upload medical record
  GET    /api/v1/appointments           # List appointments
  POST   /api/v1/appointments           # Schedule appointment
  GET    /api/v1/medications            # List medications
```

**Key REST Principles:**
- **Versioning:** Use `/api/v1/` prefix (allows v2 without breaking mobile apps)
- **Resource-centric:** Nouns, not verbs (✓ `/api/v1/trips`, ✗ `/api/v1/getTips`)
- **HTTP Methods:** POST (create), GET (read), PATCH (partial update), DELETE (remove)
- **Status Codes:** 200 (OK), 201 (Created), 400 (Bad Request), 401 (Unauthorized), 403 (Forbidden), 404 (Not Found), 429 (Rate Limit), 500 (Server Error)
- **Consistent Response Format:**
```json
{
  "data": { ... },
  "meta": {
    "page": 1,
    "per_page": 20,
    "total": 100,
    "last_page": 5
  },
  "links": {
    "first": "...",
    "next": "...",
    "prev": "...",
    "last": "..."
  }
}
```

**Package to Add:** `spatie/laravel-fractal` (optional)
- Transforms Eloquent models to JSON consistently
- Handles pagination, includes, sparse fieldsets
- **Version:** ^6.1
- **Cost:** Minimal (lightweight, widely used)
- **Alternative:** Raw response with manual formatting (totally fine for your scale)

---

## 3. Authentication & Authorization for Mobile

### Current State
✅ **Laravel Sanctum** 4.0 installed  
✅ **Spatie Permission** 7.2 for RBAC  
✅ Filament integration with policies  
❌ No JWT flow (tokens are opaque, not JWT)
❌ No refresh token rotation mechanism

### Problem with Sanctum for Mobile
- Sanctum tokens are **opaque strings** (random UUIDs), not JWTs
- No built-in refresh token rotation
- Not ideal for long-lived mobile sessions (you need refresh tokens)

### What to Add

#### A. JWT Authentication Layer

**Package:** `tymon/jwt-auth` (Laravel JWT)
- **Version:** ^2.1
- **Rationale:** 
  - ✅ Purpose-built for mobile/SPA JWT workflows
  - ✅ Token refresh cycles with blacklist support
  - ✅ Guards integration (`auth:api` can use JWT)
  - ✅ Well-documented, battle-tested
  - ✅ Works alongside Sanctum (they don't conflict)
- **Learning Curve:** Medium
- **Setup Time:** 30 minutes

**Alternative Considered:** `firebase/jwt` (raw library)
- Too low-level, would reinvent the wheel
- **Decision:** Use `tymon/jwt-auth`

**Alternative Considered:** Custom JWT implementation
- High risk of security bugs (token validation, timing attacks)
- **Decision:** Use battle-tested library

#### B. OAuth2 for Third-Party Mobile Apps (Optional in v3.0)

**If needed later:**
- Package: `laravel/passport` (OAuth 2.0)
- **Not required for v3.0** (single-user personal app)
- Deferred to future versions if you add sharing features

#### C. Mobile Auth Flow

```
1. Login Request:
   POST /api/v1/auth/login
   { email, password }
   
   Response:
   {
     "access_token": "eyJ0eXAi...",
     "refresh_token": "eyJ0eXAi...",
     "expires_in": 3600
   }

2. Access Protected Resource:
   GET /api/v1/trips
   Authorization: Bearer {access_token}

3. Refresh Token (before expiry):
   POST /api/v1/auth/refresh
   Authorization: Bearer {refresh_token}
   
   Response:
   {
     "access_token": "eyJ0eXAi...",
     "refresh_token": "eyJ0eXAi...",
     "expires_in": 3600
   }

4. Logout:
   POST /api/v1/auth/logout
   Authorization: Bearer {access_token}
   
   Response: { "message": "Successfully logged out" }
```

**Token Lifecycle:**
- **Access Token:** 1 hour (short-lived, safe to leak)
- **Refresh Token:** 30 days (stored securely on mobile, never exposed)
- **Rotation:** Mobile stores tokens in secure storage (Keychain/Keystore)

**Scoping (Permissions):**
```
Mobile JWT can include scopes:
{
  "sub": user_id,
  "scopes": ["read:trips", "write:trips", "read:health", "read:finance"],
  "iat": 1234567890,
  "exp": 1234571490
}

In your policies, verify:
@can('read', Trip::class)  // Checks both role AND JWT scope
```

**No Need for Extra Package:**
- Spatie Permission + Policy integration already handles scoping
- Just validate JWT scope in middleware

---

## 4. Health Data Standards (FHIR & HL7)

### Current Models
✅ `MedicalRecord` model exists  
✅ `HealthRecord` model exists  
✅ `Medication` model exists  
⚠️ No FHIR/HL7 compliance layer

### The Challenge
Medical records have legal implications:
- HIPAA (USA) — Protected health information (PHI)
- GDPR (EU) — Health data is special category personal data
- Liability: Storing medical records requires compliance framework

### Decision: FHIR Support (Not HIPAA/GDPR Compliance in v3.0)

**What's FHIR?**
- Fast Healthcare Interoperability Resources (standard by HL7)
- RESTful, JSON-based medical data format
- Enables export/import with hospitals, EHR systems, health insurers
- Industry-standard for interoperability

**What's NOT FHIR:**
- Encryption standards
- HIPAA audit logging
- Data retention policies
- Compliance checking

### Your Approach for v3.0:

1. **Store medical data in extended schema** (not raw FHIR in DB)
2. **Export as FHIR on demand** (for sharing with doctors)
3. **Use FHIR as API contract** (when importing from external systems)

**Package:** `omg/fhirserver-php` or `dcarbone/php-fhir` (optional)
- **Version:** Latest stable
- **Cost:** Use simple JSON serialization first
- **When needed:** If integrating with hospital EHR systems

**For v3.0, recommend:**
- Extend `MedicalRecord` to include FHIR-compatible fields:
  ```php
  class MedicalRecord extends Model {
      protected $fillable = [
          'user_id', 'date', 'type', 'doctor_name', 'clinic_hospital',
          'description', 'notes', 'file_path',
          // NEW for FHIR
          'fhir_type',        // e.g., 'Observation', 'Encounter', 'Condition'
          'fhir_code',        // LOINC/SNOMED code for interoperability
          'fhir_payload',     // JSON FHIR representation
          'is_external',      // Did it come from external EHR?
      ];
  }
  ```

**Health Metrics Enums:**
```php
enum HealthMetricType {
    // Vitals (can map to FHIR Observation codes)
    case BloodPressure;      // LOINC: 55284-4
    case HeartRate;          // LOINC: 8867-4
    case BodyTemperature;    // LOINC: 8310-5
    case RespiratoryRate;    // LOINC: 9279-1
    case BloodGlucose;       // LOINC: 2345-7
    case Cholesterol;        // LOINC: 2093-3
    
    // Lifestyle
    case Weight;
    case Height;
    case BMI;
    case SleepDuration;
    case ExerciseMinutes;
}
```

**No extra packages needed for v3.0.** Just structured data with optional FHIR export.

---

## 5. Rate Limiting, Caching, Pagination

### A. Rate Limiting

**Current State:**
✅ `ApiRateLimitMiddleware` exists (60 requests/minute)

**What to enhance:**

1. **Per-endpoint rate limits:**
```php
// Heavy endpoints get stricter limits
Route::post('/api/v1/medical-records', MedicalRecordController@store)
    ->middleware('throttle:5,1');  // 5 per minute

// Read endpoints are looser
Route::get('/api/v1/trips', TripController@index)
    ->middleware('throttle:100,1'); // 100 per minute
```

2. **User-based vs IP-based:**
```php
// Authenticated: per-user
$key = auth()->user()->id;  // from your ApiRateLimitMiddleware

// Anonymous: per-IP
$key = request()->ip();
```

**Package:** Built-in Laravel throttle middleware
- No new package needed
- Highly configurable per route

### B. Caching Strategy

```php
// Cache expensive queries for 1 hour
Route::get('/api/v1/health-records', function () {
    return cache()->remember('user:' . auth()->id() . ':health-records', 3600, function () {
        return auth()->user()->healthRecords()->paginate(20);
    });
});

// Invalidate on writes
MedicalRecord::creating(function ($record) {
    cache()->forget('user:' . $record->user_id . ':health-records');
});
```

**Package:** Built-in `cache()` helper + Redis/Memcached
- ✅ Laravel cache is excellent
- Use Redis for distributed caching (production)
- Use file cache for development (simpler)

### C. Pagination

**Current Schema:**
✅ Lighthouse paginate directive works  
✅ API already supports `?page=1&per_page=20`

**Enhance for mobile:**

```php
// Cursor-based pagination (better for large datasets)
Route::get('/api/v1/transactions', function () {
    return auth()->user()
        ->transactions()
        ->orderBy('created_at', 'desc')
        ->cursorPaginate(20);  // Mobile can use `cursor` from response
});

// Response:
{
  "data": [...],
  "path": "...",
  "per_page": 20,
  "next_page_url": "...?cursor=...",
  "prev_page_url": "...?cursor=..."
}
```

**Why cursor pagination for mobile?**
- Immutable (no offset drift if data changes)
- Efficient for large datasets
- Better for real-time feeds (transactions, health logs)

**No new package needed** — Built into Laravel 12

---

## 6. Validation & Error Handling

### Current State
✅ Form requests exist (`app/Http/Requests/`)  
✅ Custom validation rules in place  
❌ No centralized API error response format

### What to Add

#### A. API Error Response Standard

```php
// Global error handler in app/Exceptions/Handler.php
public function render($request, Throwable $exception) {
    if ($request->wantsJson()) {
        return response()->json([
            'error' => true,
            'message' => $exception->getMessage(),
            'code' => $exception->getCode() ?: 500,
            'details' => [  // Only in dev
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ],
        ], $exception->getStatusCode() ?? 500);
    }
    return parent::render($request, $exception);
}
```

#### B. Validation Error Format

```php
// Instead of default Laravel response:
// {
//   "message": "...",
//   "errors": { "email": ["..."], "password": ["..."] }
// }

// Return:
{
  "error": true,
  "message": "Validation failed",
  "code": 422,
  "details": {
    "email": ["Must be a valid email"],
    "password": ["Must be at least 8 characters"]
  }
}
```

#### C. Custom Form Requests for API

```php
namespace App\Http\Requests\Api;

class CreateTransactionRequest extends FormRequest {
    public function rules() {
        return [
            'account_id' => 'required|exists:accounts,id|authorize:update',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date|before_or_equal:today',
        ];
    }
    
    public function failedValidation($validator) {
        throw new ValidationException($validator);
    }
}
```

**No new package needed** — Built into Laravel validation

---

## 7. API Documentation (OpenAPI/Swagger)

### Current State
❌ No API documentation  
❌ No OpenAPI spec  
⚠️ Lighthouse schema is the only "docs"

### What to Add

**Package:** `vyuldashev/laravel-openapi`
- **Version:** ^3.0
- **Why:** 
  - ✅ Generates OpenAPI 3.0 spec from routes + Laravel annotations
  - ✅ Integrates with Swagger UI for interactive docs
  - ✅ Works alongside Lighthouse (they don't conflict)
  - ✅ Mobile dev teams can self-serve from `/api/docs`

**Alternative:** `swagger-php/swagger-php` (lower-level)
- More manual, but fine for smaller APIs
- **Decision:** Use `vyuldashev/laravel-openapi` (automatic)

**Setup Example:**

```php
// In your API controller
/**
 * @OA\Get(
 *     path="/api/v1/trips",
 *     summary="List user trips",
 *     @OA\Response(
 *         response=200,
 *         description="List of trips",
 *         @OA\JsonContent(
 *             type="object",
 *             properties={
 *                 @OA\Property(property="data", type="array", items={"$ref": "#/components/schemas/Trip"}),
 *                 @OA\Property(property="meta", type="object")
 *             }
 *         )
 *     )
 * )
 */
public function index() { ... }
```

**Accessible at:** `http://localhost:8000/api/docs`  
**Mobile devs use:** `/api/v1/openapi.json` (feed into Postman, Insomnia, etc.)

---

## 8. Testing Frameworks for APIs

### Current State
✅ PHPUnit 11.5.3 installed  
✅ Test directories exist  
✅ Feature tests for Filament resources exist  
❌ No API integration tests  
❌ No GraphQL mutation tests

### What to Test

#### A. API Endpoint Tests

```php
namespace Tests\Feature\Api;

class TripApiTest extends TestCase {
    
    /** @test */
    public function authenticated_user_can_list_trips() {
        $user = User::factory()->create();
        $trips = Trip::factory(3)->create(['user_id' => $user->id]);
        
        $response = $this->actingAs($user, 'api')
            ->getJson('/api/v1/trips');
        
        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.title', $trips->first()->title);
    }
    
    /** @test */
    public function unauthenticated_user_cannot_list_trips() {
        $response = $this->getJson('/api/v1/trips');
        $response->assertStatus(401);
    }
}
```

#### B. GraphQL Mutation Tests

```php
namespace Tests\Feature\GraphQL;

class CreateMedicalRecordMutationTest extends TestCase {
    
    /** @test */
    public function authenticated_user_can_create_medical_record() {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)
            ->graphQL(<<<'GQL'
                mutation {
                    createMedicalRecord(input: {
                        date: "2026-04-12"
                        doctor_name: "Dr. Smith"
                        type: "Checkup"
                    }) {
                        id
                        date
                        doctor_name
                    }
                }
            GQL);
        
        $response->assertJsonPath('data.createMedicalRecord.doctor_name', 'Dr. Smith');
    }
}
```

#### C. Rate Limiting Tests

```php
/** @test */
public function user_is_rate_limited_after_exceeding_threshold() {
    $user = User::factory()->create();
    
    for ($i = 0; $i < 61; $i++) {
        $this->actingAs($user)->getJson('/api/v1/trips');
    }
    
    $response = $this->actingAs($user)->getJson('/api/v1/trips');
    $response->assertStatus(429);
}
```

**Testing Packages:**

| Package | Version | Purpose | Required? |
|---------|---------|---------|-----------|
| phpunit/phpunit | 11.5.3 | Unit & feature tests | ✅ Have it |
| mockery/mockery | 1.6 | Mocking | ✅ Have it |
| laravel/tinker | 2.10.1 | REPL | ✅ Have it |

**No additional packages needed** — Your setup is solid

**Recommendation:**
- Add `Tests/Feature/Api/` directory for REST tests
- Add `Tests/Feature/GraphQL/` directory for GraphQL tests
- Aim for 80%+ coverage on API layer (critical for mobile)

---

## Required Packages Summary

| Package | Version | Purpose | Add in v3.0? | Rationale |
|---------|---------|---------|--------------|-----------|
| **tymon/jwt-auth** | ^2.1 | JWT tokens for mobile | ✅ REQUIRED | Mobile refresh token flow |
| **vyuldashev/laravel-openapi** | ^3.0 | API documentation | ✅ RECOMMENDED | Mobile devs need `/api/docs` |
| **spatie/laravel-fractal** | ^6.1 | JSON transformation | ⚠️ OPTIONAL | Nice-to-have for consistent responses |
| **predis/predis** | ^2.0 | Redis client (if using Redis for cache) | ⚠️ OPTIONAL | For distributed caching (production) |
| nuwave/lighthouse | 6.65 | GraphQL | ✅ KEEP | Already have it |
| laravel/sanctum | 4.0 | Token auth | ✅ KEEP | Still useful alongside JWT |
| spatie/laravel-permission | 7.2 | RBAC | ✅ KEEP | Already using well |

**Not needed (avoid):**
- ❌ `laravel/passport` — Overkill for single-user app
- ❌ `barryvdh/laravel-cors` — Laravel 12 has built-in CORS support
- ❌ Custom JWT library — Use `tymon/jwt-auth` instead
- ❌ Separate GraphQL library like `rebing/graphql` — Lighthouse is better

---

## Integration Points with Existing Code

### A. Database Migrations

**Existing traits/scoping:**
- ✅ `HasUserScoping` trait (automatic user filtering)
- ✅ SoftDeletes on health models

**What to add for v3.0:**

```php
// New migration: create_api_token_blacklist_table (for JWT blacklist)
Schema::create('api_token_blacklist', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->text('jti');  // JWT ID claim
    $table->timestamp('blacklisted_at')->useCurrent();
    $table->timestamp('expires_at');
    
    $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
    $table->index(['user_id', 'expires_at']);
});

// Extend medical_records migration
Schema::table('medical_records', function (Blueprint $table) {
    $table->string('fhir_type')->nullable()->comment('FHIR resource type');
    $table->string('fhir_code')->nullable()->comment('FHIR/SNOMED code');
    $table->json('fhir_payload')->nullable()->comment('Raw FHIR representation');
    $table->boolean('is_external')->default(false);
});
```

### B. Route Structure

**Current:** `/routes/api.php` (empty)  
**New structure:**

```
routes/
├── api.php                          # API middleware group
├── api/
│   ├── v1/                          # API v1 endpoints
│   │   ├── auth.php                 # Login, refresh, logout
│   │   ├── finance.php              # Accounts, transactions, credit cards
│   │   ├── travel.php               # Trips, itineraries, expenses
│   │   ├── health.php               # Health records, medical, medications
│   │   └── home.php                 # Properties, maintenance
```

### C. Controllers

**New structure:**

```
app/Http/Controllers/
├── Api/
│   └── V1/
│       ├── AuthController.php       # login, refresh, logout
│       ├── TripController.php       # REST endpoints for trips
│       ├── HealthController.php     # Health metrics & medical records
│       ├── TransactionController.php # Financial data
│       └── ...
```

**Key pattern:** Extend base API controller with common logic:

```php
namespace App\Http\Controllers\Api;

class ApiController extends Controller {
    protected function respondWithPaginated($items, $message = null) {
        return response()->json([
            'data' => $items->items(),
            'meta' => [
                'page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'last_page' => $items->lastPage(),
            ],
            'links' => [
                'first' => $items->url(1),
                'next' => $items->nextPageUrl(),
                'prev' => $items->previousPageUrl(),
                'last' => $items->url($items->lastPage()),
            ],
        ]);
    }
}
```

### D. GraphQL Schema Extensions

**Current:** Bare schema with only User query  
**To add:**

```graphql
type Query {
    user(id: ID, email: String): User @find
    users(name: String): [User!]! @paginate
    
    # NEW: Travel Domain
    trips: [Trip!]! @paginate
    trip(id: ID!): Trip @find
    
    # NEW: Health Domain
    healthRecords: [HealthRecord!]! @paginate
    medicalRecords: [MedicalRecord!]! @paginate
    medications: [Medication!]! @paginate
    
    # NEW: Finance Domain
    accounts: [Account!]! @paginate
    transactions: [Transaction!]! @paginate
}

type Mutation {
    # Auth
    login(email: String!, password: String!): AuthPayload
    logout: Boolean
    refreshToken: AuthPayload
    
    # Health (NEW)
    createHealthRecord(input: CreateHealthRecordInput!): HealthRecord
    updateMedicalRecord(id: ID!, input: UpdateMedicalRecordInput!): MedicalRecord
    scheduleAppointment(input: CreateAppointmentInput!): Appointment
}

type AuthPayload {
    access_token: String!
    refresh_token: String!
    expires_in: Int!
}
```

### E. Policies & Authorization

**Existing:** Policies for Filament resources  
**Reuse for API:**

```php
// app/Policies/TripPolicy.php (works for both Filament & API)
public function view(User $user, Trip $trip): bool {
    return $user->id === $trip->user_id;
}

// In API controller:
$trip = Trip::findOrFail($id);
$this->authorize('view', $trip);  // Uses same policy
```

### F. Services & Business Logic

**Existing:** Service layer already in use  
**Reuse in API:**

```php
// app/Services/TripService.php (works for admin & API)
public function createTrip(User $user, array $data): Trip {
    return $user->trips()->create($data);
}

// In API controller:
public function store(StoreTripRequest $request) {
    $trip = app(TripService::class)
        ->createTrip(auth()->user(), $request->validated());
    return $this->respondWithData($trip);
}

// In Filament resource:
$trip = app(TripService::class)->createTrip($user, $data);
```

---

## What NOT to Add (Anti-Patterns)

### ❌ 1. Don't Use Sanctum Tokens for Mobile JWT Flow
**Why:** Sanctum tokens are opaque UUIDs, not JWTs. No standard refresh cycle.  
**What to do:** Pair Sanctum with `tymon/jwt-auth` for mobile, keep Sanctum for web.

### ❌ 2. Don't Expose Raw Eloquent Models in API Responses
**Why:** Security leaks (hidden attributes, relationships expose internal structure)  
**What to do:** Use resources/transformers (`ApiResource` or Fractal)
```php
// ❌ BAD
return response()->json($user);  // Serializes all attrs

// ✅ GOOD
return response()->json(new UserResource($user));  // Only exposed attrs
```

### ❌ 3. Don't Store Medical Records as Binary Files Without Encryption
**Why:** HIPAA requires encryption at rest  
**What to do:** 
```php
// Encrypt on upload
$encrypted = encrypt(file_get_contents($file));
$record->file_path = Storage::putFileAs('medical-records', ..., encrypt());

// Decrypt on download
return decrypt(Storage::get($record->file_path));
```

### ❌ 4. Don't Build Custom JWT Implementation
**Why:** Timing attacks, signature validation bugs, token format errors  
**What to do:** Use `tymon/jwt-auth` (battle-tested)

### ❌ 5. Don't Commit API Keys/Secrets to Repo
**Why:** Immediate security breach  
**What to do:**
```php
// .env
JWT_SECRET=your_secret_key_here
API_RATE_LIMIT=60

// .gitignore (already has)
.env
.env.*.local
```

### ❌ 6. Don't Skip Rate Limiting on Write Endpoints
**Why:** Enables abuse (spam medical records, fake expenses)  
**What to do:** Throttle writes more aggressively
```php
Route::post('/api/v1/medical-records', ...)
    ->middleware('throttle:10,1');  // 10 per minute (stricter than reads)
```

### ❌ 7. Don't Return Paginated Data Without Explicit Structure
**Why:** Mobile client can't reliably parse pagination  
**What to do:** Always include `meta` and `links`
```json
{
  "data": [...],
  "meta": { "page": 1, "total": 100 },
  "links": { "next": "...", "prev": "..." }
}
```

### ❌ 8. Don't Skip API Authentication Tests
**Why:** APIs are invisible to manual testing; only tests catch auth bugs  
**What to do:** Test every endpoint with/without token, with wrong user, expired token
```php
test('unauthenticated user cannot create medical record');
test('user cannot access another user\'s data');
test('expired token returns 401');
```

### ❌ 9. Don't Ignore CORS for Web + Mobile
**Why:** Mobile apps will make cross-origin requests  
**What to do:** Configure CORS in `config/cors.php`
```php
'allowed_origins' => [
    'http://localhost:3000',  // React dev
    'https://app.secondbrain.io',  // Mobile app domain
],
'allowed_methods' => ['GET', 'POST', 'PATCH', 'DELETE'],
```

### ❌ 10. Don't Create Separate Health Domain Models
**Why:** You already have `MedicalRecord`, `HealthRecord`, `Medication`  
**What to do:** Extend existing models with API-required fields, don't duplicate

---

## Setup Complexity & Migration Effort

### Phase Timeline for v3.0

| Phase | Task | Effort | Duration | Dependencies |
|-------|------|--------|----------|--------------|
| **0** | JWT setup, API scaffold | 2 days | 2 days | None |
| **1** | REST endpoints (Finance, Travel, Home) | 5 days | 1 week | Phase 0 |
| **2** | GraphQL mutations for health | 3 days | 3-4 days | Phase 1 |
| **3** | OpenAPI docs generation | 2 days | 2 days | Phases 1-2 |
| **4** | API integration tests | 4 days | 1 week | Phases 1-3 |
| **5** | Health integration (FHIR export) | 2 days | 2-3 days | Phase 4 |
| **6** | Rate limiting tuning, caching | 1 day | 1 day | All |
| **7** | Mobile app build & testing | TBD | 2-3 weeks | All above |

**Total Backend Effort:** ~19 days (3 weeks)  
**Parallel Mobile App:** Can start after Phase 1 (REST endpoints)

### Migration Effort from v2.0 → v3.0

**Database:**
- ✅ No breaking changes to existing tables
- ✅ Add new columns to `medical_records` (backward-compatible)
- ✅ Add new `api_token_blacklist` table

**Code:**
- ✅ Existing services/policies/models don't change
- ✅ New API layer sits alongside Filament
- ✅ GraphQL schema additions (backward-compatible)

**Testing:**
- New API tests (100-150 tests estimated)
- No changes to existing 143 passing tests

**Risk Level:** 🟢 LOW
- Additive (no deletions)
- Existing code unaffected
- Can deploy incrementally (API v1 routes separately)

---

## Deployment & Infrastructure Changes

### Server Requirements
- ✅ PHP 8.2+ (already have)
- ✅ Laravel 12 (already have)
- ⚠️ Redis (optional but recommended for caching & rate limiting)
  - Development: File cache (simpler)
  - Production: Redis (distributed, fast)

### Environment Variables to Add
```env
# JWT Configuration
JWT_SECRET=your_secret_key_generated_by_openssl
JWT_ALGORITHM=HS256
JWT_EXPIRATION=3600
JWT_REFRESH_EXPIRATION=2592000  # 30 days

# API Configuration
API_RATE_LIMIT=60
API_RATE_LIMIT_WINDOW=60  # seconds
API_VERSION=v1

# Health Integration
FHIR_EXPORT_ENABLED=true
MEDICAL_RECORD_ENCRYPTION=true
```

### CI/CD Considerations
- Add API tests to GitHub Actions
- Add OpenAPI spec validation
- Add GraphQL schema validation

---

## Recommended Implementation Order

### Week 1: Foundation
1. Install `tymon/jwt-auth` + configure guards
2. Create `AuthController` with login/refresh/logout
3. Scaffold REST routes structure
4. Write JWT middleware

### Week 2: Core APIs
1. Build REST endpoints for Finance (accounts, transactions)
2. Build REST endpoints for Travel (trips, itineraries)
3. Add form request validation
4. Test 80% coverage on endpoints

### Week 3: Health + Docs
1. Extend health models with FHIR fields
2. Build GraphQL mutations for health
3. Add OpenAPI documentation
4. Deploy v3.0-beta to staging

### Week 4+: Polish & Mobile
1. Tune rate limits based on usage
2. Add caching optimization
3. Mobile app team integrates APIs
4. Load testing and optimization

---

## Conclusion

**v3.0 is achievable with:**
- ✅ 2 new packages (`tymon/jwt-auth`, `vyuldashev/laravel-openapi`)
- ✅ No breaking changes to existing code
- ✅ Reuse of existing services/policies/models
- ✅ ~3 weeks backend work
- ⚠️ Requires thoughtful API design (once, mobile devs depend on it)

**Biggest risk:** Poor API contract design (leads to breaking changes later)  
**Mitigation:** Invest time in Week 0 (API planning + spec-first design)

**Biggest win:** Standardized REST + GraphQL means both web apps and mobile can coexist seamlessly.

