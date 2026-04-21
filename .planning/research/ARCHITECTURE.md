# Mobile API & Health Domain Integration Architecture
**v3.0 Design Document**

---

## Executive Summary

This document outlines the architecture for integrating a **Mobile REST/GraphQL API layer** with the existing Second Brain application (Laravel 12 + Filament 4) and adding a new **Health Domain** for medical records and health metrics.

**Key Design Principle:** Extend, don't replace. The existing service layer, policies, and observer patterns provide a solid foundation. The API layer wraps these services; the health domain follows established patterns.

---

## 1. Current Architecture Foundation

### 1.1 Existing Patterns (Proven & Working)

The Second Brain application has a well-established layered architecture:

| Layer | Purpose | Location | Pattern |
|-------|---------|----------|---------|
| **Models** | ORM + relationships | `app/Models/` | Eloquent, traits (HasUserScoping, SoftDeletes) |
| **Services** | Business logic | `app/Services/` | Constructor injection, exception handling, logging |
| **Policies** | Authorization | `app/Policies/` | User ID matching (`$user->id === $model->user_id`) |
| **Observers** | Side effects | `app/Observers/` | Model lifecycle hooks → Jobs → async work |
| **Enums** | Type safety | `app/Enums/` | Backed enums (string/int) for domain values |
| **Filament** | Admin UI | `app/Filament/` | Resource-based CRUD with Forms, Tables, Relations |

### 1.2 User Scoping Pattern

All user-owned entities use `HasUserScoping` trait:

```php
trait HasUserScoping
{
    protected static function bootHasUserScoping(): void
    {
        // Global scope: auto-filter by auth()->id()
        static::addGlobalScope('user', function (Builder $query) {
            if (auth()->check()) {
                $query->where('user_id', auth()->id());
            }
        });

        // Auto-populate user_id on create
        static::creating(function ($model) {
            if (auth()->check()) {
                $model->user_id = auth()->id();
            }
        });
    }
}
```

Applied to: Property, Travel, Trip, Account, Subscription, Document, HealthRecord, MedicalRecord, BloodTest, etc.

---

## 2. API Architecture Design

### 2.1 API Entry Points (New)

**Location:** `routes/api.php` + `app/Http/Controllers/Api/`

#### REST API Routes

```php
// routes/api.php
Route::middleware(['api', 'auth:sanctum'])
    ->prefix('v1')
    ->group(function () {
        // Properties
        Route::apiResource('properties', PropertyController::class);
        Route::get('properties/{property}/metrics', [PropertyController::class, 'metrics']);
        
        // Health Records
        Route::apiResource('health/records', HealthRecordController::class);
        Route::apiResource('health/appointments', AppointmentController::class);
        Route::apiResource('health/metrics', HealthMetricController::class);
        
        // Medical Records
        Route::apiResource('medical-records', MedicalRecordController::class);
        
        // Travel
        Route::apiResource('trips', TripController::class);
        Route::apiResource('trips/{trip}/itineraries', ItineraryController::class);
    });
```

#### Authentication Routes

```php
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});
```

### 2.2 REST Controller Pattern

Controllers follow Laravel ResourceController pattern, reusing existing services:

```php
namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StorePropertyRequest;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use App\Services\PropertyService;

class PropertyController extends Controller
{
    public function __construct(private PropertyService \$service) {}

    // GET /api/v1/properties
    public function index()
    {
        return PropertyResource::collection(
            auth()->user()->properties()->paginate(15)
        );
    }

    // POST /api/v1/properties
    public function store(StorePropertyRequest \$request)
    {
        \$property = \$this->service->create(
            auth()->user(),
            \$request->validated()
        );
        return response()->json(new PropertyResource(\$property), 201);
    }

    // GET /api/v1/properties/{property}
    public function show(Property \$property)
    {
        \$this->authorize('view', \$property);
        return new PropertyResource(\$property);
    }

    // PUT /api/v1/properties/{property}
    public function update(UpdatePropertyRequest \$request, Property \$property)
    {
        \$this->authorize('update', \$property);
        \$updated = \$this->service->update(\$property, \$request->validated());
        return new PropertyResource(\$updated);
    }

    // DELETE /api/v1/properties/{property}
    public function destroy(Property \$property)
    {
        \$this->authorize('delete', \$property);
        \$this->service->delete(\$property);
        return response()->json(status: 204);
    }
}
```

### 2.3 Authorization with Policies

Policies are called in controllers via `\$this->authorize()`. Same policies used by Filament:

```php
// In any API controller
\$this->authorize('view', \$property);  // Calls PropertyPolicy::view(\$user, \$property)
\$this->authorize('update', \$property); // Calls PropertyPolicy::update()
\$this->authorize('delete', \$property); // Calls PropertyPolicy::delete()

// Returns 403 if user->id !== property->user_id
```

### 2.4 Form Request Validation

All API inputs validated via Form Requests:

```php
namespace App\Http\Requests;

class StorePropertyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'address' => 'required|string|max:255',
            'property_type' => 'required|in:house,apartment,condo,other',
            'lease_start_date' => 'nullable|date|before:lease_end_date',
            'lease_end_date' => 'nullable|date|after:lease_start_date',
            'estimated_value' => 'nullable|numeric|min:0',
        ];
    }
}
```

Invalid input returns 422 Unprocessable Entity with error details.

### 2.5 Response Transformation (API Resources)

All responses transformed to DTOs via Resource classes:

```php
namespace App\Http\Resources;

class PropertyResource extends JsonResource
{
    public function toArray(\$request): array
    {
        return [
            'id' => \$this->id,
            'address' => \$this->address,
            'property_type' => \$this->property_type,
            'lease_dates' => [
                'start' => \$this->lease_start_date?->format('Y-m-d'),
                'end' => \$this->lease_end_date?->format('Y-m-d'),
            ],
            'estimated_value' => (string) \$this->estimated_value,
            'created_at' => \$this->created_at,
            'links' => [
                'self' => url("/api/v1/properties/{\$this->id}"),
            ],
        ];
    }
}
```

### 2.6 GraphQL Integration

GraphQL endpoint at `POST /graphql` (Lighthouse handles):

```graphql
type Query {
    healthRecords(first: Int = 10): [HealthRecord!]! @paginate
    healthRecord(id: ID!): HealthRecord @find @can(ability: "view")
    appointments(first: Int = 10): [Appointment!]! @paginate
    appointment(id: ID!): Appointment @find @can(ability: "view")
}

type Mutation {
    createHealthRecord(input: CreateHealthRecordInput!): HealthRecord!
    updateHealthRecord(id: ID!, input: UpdateHealthRecordInput!): HealthRecord!
    deleteHealthRecord(id: ID!): Boolean!
}

type HealthRecord {
    id: ID!
    user: User!
    date: Date!
    weight: Decimal
    heartRate: Int
    temperature: Decimal
    notes: String
    createdAt: DateTime!
}
```

---

## 3. Health Domain Architecture

### 3.1 Health Models (New)

All follow `HasUserScoping` + `SoftDeletes` pattern:

```php
// app/Models/HealthMetric.php
class HealthMetric extends Model
{
    use HasFactory, SoftDeletes, HasUserScoping;

    protected \$fillable = ['user_id', 'date', 'metric_type', 'value', 'unit', 'notes'];
    protected \$casts = ['date' => 'date', 'value' => 'decimal:2'];
    
    public function user(): BelongsTo { return \$this->belongsTo(User::class); }
}

// app/Models/Appointment.php
class Appointment extends Model
{
    use HasFactory, SoftDeletes, HasUserScoping;

    protected \$fillable = ['user_id', 'date_time', 'doctor_name', 'status', 'description'];
    protected \$casts = ['date_time' => 'datetime', 'status' => AppointmentStatus::class];
    
    public function user(): BelongsTo { return \$this->belongsTo(User::class); }
    public function prescriptions(): HasMany { return \$this->hasMany(Prescription::class); }
}

// app/Models/Prescription.php
class Prescription extends Model
{
    use HasFactory, SoftDeletes, HasUserScoping;

    protected \$fillable = ['user_id', 'appointment_id', 'medication_name', 'frequency', 'start_date', 'end_date'];
    protected \$casts = ['start_date' => 'date', 'frequency' => PrescriptionFrequency::class];
    
    public function user(): BelongsTo { return \$this->belongsTo(User::class); }
}

// app/Models/LabResult.php
class LabResult extends Model
{
    use HasFactory, SoftDeletes, HasUserScoping;

    protected \$fillable = ['user_id', 'test_name', 'test_date', 'result_status', 'file_path', 'notes'];
    protected \$casts = ['test_date' => 'date', 'result_status' => LabResultStatus::class];
    
    public function user(): BelongsTo { return \$this->belongsTo(User::class); }
}
```

### 3.2 Health Services (New)

```php
// app/Services/HealthMetricsService.php
class HealthMetricsService
{
    public function recordMetric(User \$user, array \$data): HealthMetric
    {
        \$metric = new HealthMetric(\$data);
        \$metric->user_id = \$user->id;
        \$metric->save();
        return \$metric;
    }

    public function getMetricsForPeriod(User \$user, Carbon \$start, Carbon \$end): Collection
    {
        return \$user->healthMetrics()
            ->whereBetween('date', [\$start, \$end])
            ->orderBy('date', 'desc')
            ->get();
    }

    public function calculateTrends(User \$user, string \$type, int \$days = 30): array
    {
        \$metrics = \$user->healthMetrics()
            ->where('metric_type', \$type)
            ->where('date', '>=', now()->subDays(\$days))
            ->orderBy('date')
            ->get();

        return [
            'metric_type' => \$type,
            'count' => \$metrics->count(),
            'average' => \$metrics->avg('value'),
            'min' => \$metrics->min('value'),
            'max' => \$metrics->max('value'),
        ];
    }
}

// app/Services/AppointmentService.php
class AppointmentService
{
    public function scheduleAppointment(User \$user, array \$data): Appointment
    {
        \$appointment = new Appointment(\$data + ['user_id' => \$user->id, 'status' => 'scheduled']);
        \$appointment->save();
        return \$appointment;
    }

    public function getUpcomingAppointments(User \$user, int \$days = 30): Collection
    {
        return \$user->appointments()
            ->where('date_time', '>=', now())
            ->where('date_time', '<=', now()->addDays(\$days))
            ->orderBy('date_time')
            ->get();
    }
}
```

### 3.3 Health Policies (New)

All policies check `user->id === model->user_id`:

```php
class HealthMetricPolicy
{
    public function view(User \$user, HealthMetric \$metric): bool
    {
        return \$metric->user_id === \$user->id;
    }

    public function update(User \$user, HealthMetric \$metric): bool
    {
        return \$metric->user_id === \$user->id;
    }

    public function delete(User \$user, HealthMetric \$metric): bool
    {
        return \$metric->user_id === \$user->id;
    }
}

// Duplicate for: AppointmentPolicy, PrescriptionPolicy, LabResultPolicy
```

### 3.4 Health Observers & Jobs (New)

```php
// app/Observers/AppointmentObserver.php
class AppointmentObserver
{
    public function created(Appointment \$appointment): void
    {
        if (\$appointment->date_time) {
            \$reminderTime = \$appointment->date_time->clone()->subDay();
            if (\$reminderTime->isFuture()) {
                SendAppointmentReminder::dispatch(\$appointment)
                    ->delay(\$reminderTime);
            }
        }
    }
}

// app/Jobs/SendAppointmentReminder.php
class SendAppointmentReminder implements ShouldQueue
{
    public function __construct(private Appointment \$appointment) {}

    public function handle(): void
    {
        Notification::send(\$this->appointment->user, new AppointmentReminderNotification(\$this->appointment));
    }
}
```

### 3.5 Health Enums (New)

```php
enum HealthMetricType: string
{
    case WEIGHT = 'weight';
    case HEIGHT = 'height';
    case BLOOD_PRESSURE = 'blood_pressure';
    case HEART_RATE = 'heart_rate';
    case GLUCOSE = 'glucose';
    case TEMPERATURE = 'temperature';
}

enum AppointmentStatus: string
{
    case SCHEDULED = 'scheduled';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}

enum PrescriptionFrequency: string
{
    case ONCE_DAILY = 'once_daily';
    case TWICE_DAILY = 'twice_daily';
    case AS_NEEDED = 'as_needed';
}

enum LabResultStatus: string
{
    case PENDING = 'pending';
    case NORMAL = 'normal';
    case ABNORMAL = 'abnormal';
}
```

---

## 4. Data Flow Architecture

### 4.1 Request → Response Flow

```
Mobile Client
    ↓
POST /api/v1/health/metrics
Authorization: Bearer {sanctum_token}
    ↓
routes/api.php → auth:sanctum middleware
    ↓ (token valid)
HealthMetricController@store
    ↓
StoreHealthMetricRequest::rules() validation
    ↓ (passed)
\$this->authorize('create') → HealthMetricPolicy::create()
    ↓ (authorized)
HealthMetricsService::recordMetric()
    ↓ (execute business logic)
\$metric->save()
    ↓ (Eloquent hooks fire)
AppointmentObserver (if exists)
Dispatch Jobs (reminders, notifications)
    ↓
HealthMetricResource::toArray() (DTO transformation)
    ↓
JSON 201 Created Response
    ↓
Mobile Client displays metric
```

### 4.2 Authorization Applies Everywhere

```
API:
    \$this->authorize('view', \$property) → PropertyPolicy::view(\$user, \$property)

Filament:
    if (\$user->can('view', \$property)) → PropertyPolicy::view(\$user, \$property)

Both enforce: user->id === property->user_id
```

### 4.3 Observers Fire in API Context

When API calls `\$model->save()`:

```
1. Creating/Updating event fires
2. HasUserScoping trait auto-sets user_id
3. Observers registered for that model fire
4. Jobs dispatched to queue
5. Same as Filament behavior
```

---

## 5. Integration Points

### 5.1 API Reuses Existing Services

| Service | Existing Users | New API Users |
|---------|---|---|
| PropertyService | Filament | API PropertyController |
| ItineraryService | Filament Relations | API ItineraryController |
| CreditCardBalanceService | Dashboard | API FinanceController |
| HealthMetricsService | — | API HealthMetricController (NEW) |
| AppointmentService | — | API AppointmentController (NEW) |

**No changes needed to existing services. APIs wrap them in JSON.**

### 5.2 Health Domain Alongside Existing

```
app/Services/
├── PropertyService (existing → used by API)
├── ItineraryService (existing → used by API)
├── HealthMetricsService (NEW → used by API)
├── AppointmentService (NEW → used by API)
└── PrescriptionService (NEW → used by API)

All follow: Constructor injection → validate → execute → return model
```

---

## 6. Build Order & Phasing

### Phase 1: API Foundation (Week 1)
- ✓ `routes/api.php` with Sanctum middleware
- ✓ `AuthController` (login/logout with Sanctum tokens)
- ✓ `PropertyController` (demonstrate REST pattern)
- ✓ `StorePropertyRequest`, `PropertyResource`
- ✓ Test: Properties API endpoints

### Phase 2: Existing Domain APIs (Week 2-3)
- ✓ `TripController`, `ItineraryController`
- ✓ `AccountController`, `CreditCardController`
- ✓ `HealthRecordController`, `BloodTestController`, `MedicalRecordController`
- ✓ Form Requests + Resources for each

### Phase 3: Health Domain Models & Services (Week 3)
- ✓ Migrations: health_metrics, appointments, prescriptions, lab_results
- ✓ Models: HealthMetric, Appointment, Prescription, LabResult
- ✓ Enums: HealthMetricType, AppointmentStatus, etc.
- ✓ Services: HealthMetricsService, AppointmentService, PrescriptionService
- ✓ Policies: All health policies
- ✓ Observers: AppointmentObserver, PrescriptionObserver
- ✓ Jobs: Reminder + Notification jobs

### Phase 4: Health API Controllers (Week 4)
- ✓ HealthMetricController, AppointmentController, etc.
- ✓ Form Requests + Resources
- ✓ Special endpoints: /trends, /upcoming, /active-prescriptions

### Phase 5: GraphQL Schema (Week 4-5)
- ✓ Extend graphql/schema.graphql with Health types
- ✓ Test queries/mutations

### Phase 6: Mobile Client Integration (Week 5-6)
- ✓ Frontend integration (iOS/Android)
- ✓ E2E testing, performance testing

---

## 7. File Structure Summary

### New Files

```
routes/api.php

app/Http/Controllers/Api/
├── AuthController.php (login/logout)
└── V1/
    ├── PropertyController.php
    ├── TripController.php
    ├── HealthMetricController.php (NEW)
    ├── AppointmentController.php (NEW)
    └── ... 15+ controllers

app/Http/Requests/
├── StorePropertyRequest.php
├── StoreHealthMetricRequest.php (NEW)
├── StoreAppointmentRequest.php (NEW)
└── ... 30+ form requests

app/Http/Resources/
├── PropertyResource.php
├── HealthMetricResource.php (NEW)
├── AppointmentResource.php (NEW)
└── ... 20+ resources

app/Models/
├── HealthMetric.php (NEW)
├── Appointment.php (NEW)
├── Prescription.php (NEW)
├── LabResult.php (NEW)

app/Services/
├── HealthMetricsService.php (NEW)
├── AppointmentService.php (NEW)
├── PrescriptionService.php (NEW)

app/Policies/
├── HealthMetricPolicy.php (NEW)
├── AppointmentPolicy.php (NEW)
├── PrescriptionPolicy.php (NEW)
├── LabResultPolicy.php (NEW)

app/Observers/
├── AppointmentObserver.php (NEW)
├── PrescriptionObserver.php (NEW)

app/Jobs/
├── SendAppointmentReminder.php (NEW)
├── SendPrescriptionExpiryReminder.php (NEW)

app/Enums/
├── HealthMetricType.php (NEW)
├── AppointmentStatus.php (NEW)
├── PrescriptionFrequency.php (NEW)
├── LabResultStatus.php (NEW)

database/migrations/
├── 2026_04_11_create_health_metrics_table.php (NEW)
├── 2026_04_11_create_appointments_table.php (NEW)
├── 2026_04_11_create_prescriptions_table.php (NEW)
├── 2026_04_11_create_lab_results_table.php (NEW)

graphql/schema.graphql (updated with Health types)
```

---

## 8. Key Design Principles

1. **Reuse Services:** APIs call same services as Filament (no duplication)
2. **Consistent Authorization:** Policies enforce at all layers (API, Filament, internal)
3. **Async Jobs:** Observers trigger jobs for side effects (notifications, reminders)
4. **Type Safety:** Enums everywhere, no magic strings
5. **Multi-tenancy:** HasUserScoping ensures automatic user data isolation
6. **Layered:** Clean separation (Controller → Service → Model → DB)
7. **REST + GraphQL:** Both use same services and policies (no separate logic)

---

## 9. API Response Examples

### Create Health Metric (REST)

```json
POST /api/v1/health/metrics
Authorization: Bearer {token}
Content-Type: application/json

{
    "date": "2026-04-11",
    "metric_type": "weight",
    "value": 75.5,
    "unit": "kg",
    "notes": "Morning measurement"
}

Response 201:
{
    "data": {
        "id": 123,
        "date": "2026-04-11",
        "metric_type": "weight",
        "value": "75.50",
        "unit": "kg",
        "notes": "Morning measurement",
        "created_at": "2026-04-11T10:30:00Z"
    }
}
```

### Get Health Trends (REST)

```json
GET /api/v1/health/metrics/trends?metric_type=weight&days=30
Authorization: Bearer {token}

Response 200:
{
    "data": {
        "metric_type": "weight",
        "count": 15,
        "average": "74.50",
        "min": "73.00",
        "max": "76.00",
        "trend": "stable"
    }
}
```

### Get Upcoming Appointments (REST)

```json
GET /api/v1/health/appointments/upcoming?days=30
Authorization: Bearer {token}

Response 200:
{
    "data": [
        {
            "id": 456,
            "date_time": "2026-04-15T14:00:00Z",
            "doctor_name": "Dr. Smith",
            "appointment_type": "checkup",
            "status": "scheduled"
        }
    ]
}
```

---

## 10. Testing Strategy

### Unit Tests
- Service methods (calculations, filtering)
- Policy authorization logic
- Enum validation

### Feature Tests
- API endpoints (CRUD, status codes)
- Authorization (403 for denied, 200 for allowed)
- Validation (422 for invalid input)
- Observer side effects (jobs dispatched)

### Integration Tests
- Multiple domains together
- User data isolation (HasUserScoping)
- Transaction handling

---

## 11. Summary

| Aspect | Details |
|--------|---------|
| **API Pattern** | REST (ResourceController) + GraphQL (Lighthouse) |
| **Auth** | Laravel Sanctum tokens |
| **Authorization** | Policies (user->id === model->user_id) |
| **Services** | Reuse existing (PropertyService, etc) + new (HealthMetricsService) |
| **Models** | 40+ existing + 4 new (Health domain) |
| **Policies** | 11 existing + 4 new (Health domain) |
| **Observers** | 6 existing + 2 new (Health domain) |
| **Database** | MySQL with migrations (user_id foreign keys, indexes) |
| **Async** | Jobs dispatched by observers (reminders, notifications) |

---

## 12. Next Steps

1. Review architecture with team
2. Approve build order and phasing
3. Create GitHub issues for Phase 1
4. Begin implementation of API foundation
5. Establish testing requirements
6. Plan mobile client integration

---

**Document Version:** 1.0  
**Date Created:** 2026-04-11  
**Status:** Ready for Implementation
