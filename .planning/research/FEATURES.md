# Mobile APIs & Health Integration: Features Research

**Project:** Second Brain v3.0  
**Date:** 2026-04-11  
**Status:** Research & Planning  
**Scope:** Mobile API Layer (REST/GraphQL) + Health Integration Expansion

---

## Executive Summary

Second Brain is evolving from an admin-first platform to a multi-channel system with off-admin REST/GraphQL APIs and enriched health management. This document outlines table stakes, differentiators, anti-features, complexity considerations, and dependencies for:

1. **Mobile API Layer** — REST endpoints + GraphQL schema for Travel, Finance, Home domains
2. **Health Integration** — Medical records, appointments, vitals, provider management, Apple HealthKit/Google Fit integration

---

# PART 1: MOBILE API LAYER

## 1.1 Table Stakes (Must-Have)

### 1.1.1 REST Endpoints — CRUD Operations

#### Finance Domain (Accounts, Transactions, Subscriptions)
| Resource | Endpoints | Priority | Complexity |
|----------|-----------|----------|-----------|
| **Accounts** | `GET /accounts`, `GET /accounts/{id}`, `POST /accounts`, `PATCH /accounts/{id}`, `DELETE /accounts/{id}` | 🔴 Critical | Low |
| **Transactions** | `GET /transactions`, `GET /transactions/{id}`, `POST /transactions`, `PATCH /transactions/{id}`, `DELETE /transactions/{id}` | 🔴 Critical | Medium |
| **Subscriptions** | `GET /subscriptions`, `GET /subscriptions/{id}`, `POST /subscriptions`, `PATCH /subscriptions/{id}`, `DELETE /subscriptions/{id}` | 🟠 High | Low |
| **Credit Cards** | `GET /credit-cards`, `GET /credit-cards/{id}`, `POST /credit-cards`, `PATCH /credit-cards/{id}` | 🟠 High | Medium |
| **Loans** | `GET /loans`, `GET /loans/{id}`, `POST /loans`, `PATCH /loans/{id}` | 🟠 High | Medium |

**Why table stakes:**
- Users need off-admin mobile access to check balances and recent transactions (core use case)
- CRUD on accounts drives all other features (subscriptions, credit cards, loans)
- Read-heavy (users pull data far more than they create transactions via API)

**Notes:**
- Existing `Account`, `Transaction`, `Subscription`, `CreditCard`, `Loan` models in place ✅
- Service layer (`TravelService`, `AccountBalanceService`) provides business logic ✅
- Policies exist for authorization, need API middleware bridge

---

#### Travel Domain (Trips, Itineraries, Budgets, Participants)
| Resource | Endpoints | Priority | Complexity |
|----------|-----------|----------|-----------|
| **Trips** | `GET /trips`, `GET /trips/{id}`, `POST /trips`, `PATCH /trips/{id}`, `DELETE /trips/{id}` | 🔴 Critical | Low |
| **Itineraries** | `GET /trips/{id}/itineraries`, `POST /trips/{id}/itineraries`, `PATCH /itineraries/{id}`, `DELETE /itineraries/{id}` | 🟠 High | Medium |
| **Destinations** | `GET /trips/{id}/destinations`, `POST /trips/{id}/destinations` | 🟠 High | Low |
| **Trip Budgets** | `GET /trips/{id}/budget`, `PATCH /trips/{id}/budget` | 🟠 High | Medium |
| **Trip Participants** | `GET /trips/{id}/participants`, `POST /trips/{id}/participants`, `DELETE /trips/{id}/participants/{participant_id}` | 🟠 High | Medium |
| **Trip Expenses** | `GET /trips/{id}/expenses`, `POST /trips/{id}/expenses`, `DELETE /expenses/{id}` | 🟠 High | Medium |

**Why table stakes:**
- Users plan trips from mobile; need to create, update, view trips and itineraries
- Budget tracking is key decision point (can we afford this trip?)
- Participant expense splitting is high-friction on web (perfect for mobile)

**Notes:**
- Trip, Itinerary, Destination, TripBudget, TripParticipant, TripExpense models exist ✅
- `TravelService` + `TravelBudgetCalculator` provide business logic ✅
- Conflict detection built-in (TripItineraryConflict model exists)

---

#### Home Domain (Properties, Maintenance, Utilities, Inventory)
| Resource | Endpoints | Priority | Complexity |
|----------|-----------|----------|-----------|
| **Properties** | `GET /properties`, `GET /properties/{id}`, `POST /properties`, `PATCH /properties/{id}` | 🟠 High | Low |
| **Maintenance Tasks** | `GET /properties/{id}/maintenance-tasks`, `POST /properties/{id}/maintenance-tasks`, `PATCH /maintenance-tasks/{id}` | 🟠 High | Low |
| **Maintenance Records** | `GET /properties/{id}/maintenance-records`, `POST /properties/{id}/maintenance-records` | 🟠 High | Medium |
| **Utilities** | `GET /properties/{id}/utilities`, `GET /properties/{id}/utility-bills`, `POST /properties/{id}/utility-bills` | 🟠 High | Low |
| **Inventory** | `GET /properties/{id}/inventory`, `POST /properties/{id}/inventory`, `PATCH /inventory/{id}` | 🟠 High | Low |

**Why table stakes:**
- Users log maintenance (e.g., "HVAC service done today") from mobile
- Quick utility bill photo + entry from mobile faster than desktop
- Emergency: "Which properties do I own? Who's the contractor?"

**Notes:**
- Property, MaintenanceTask, MaintenanceRecord, Utility, UtilityBill, Inventory models exist ✅
- `MaintenanceService` provides scheduling + reminders ✅

---

### 1.1.2 Authentication & Authorization

| Feature | Requirement | Complexity |
|---------|-------------|-----------|
| **JWT Auth** | `POST /auth/login` → access token + refresh token | Medium |
| **Refresh Token Rotation** | `POST /auth/refresh` → new access + refresh tokens | Medium |
| **Logout** | `POST /auth/logout` → invalidate refresh tokens | Low |
| **API Token Scoping** | Tokens respect existing Spatie roles/permissions | Medium |
| **Rate Limiting** | Per-user rate limits (e.g., 100 req/min) | Low |

**Why table stakes:**
- Mobile clients cannot use session cookies (stateless requirement)
- JWT is industry standard for mobile APIs
- Existing `ApiRateLimitMiddleware` + Spatie permission system in place ✅

**Implementation notes:**
- Extend existing `ApiRateLimitMiddleware` for JWT
- Leverage `User::permission()` policy checks in controllers
- Store refresh tokens in `api_tokens` table with rotation strategy

---

### 1.1.3 Pagination, Filtering, Sorting

| Feature | Requirement | Examples |
|---------|-------------|----------|
| **Cursor Pagination** | Efficient for large lists; support `first`, `after`, `before` | `GET /transactions?first=20&after=MTAw` |
| **Offset Pagination** | Legacy support; default `page=1&per_page=20` | `GET /accounts?page=2&per_page=25` |
| **Sorting** | Multi-field sort; `sort=-created_at,balance` | `GET /transactions?sort=-date,amount` |
| **Filtering** | Column filters; range queries for dates/amounts | `GET /transactions?date_from=2026-01-01&date_to=2026-12-31&type=expense` |
| **Search** | Full-text search on name/description | `GET /trips?search=Paris` |

**Why table stakes:**
- Users have 100+ transactions, need to find one specific
- Cursor pagination scales better than offset for mobile
- Filtering reduces payload (critical on cellular)

**Implementation:**
- Use Laravel `paginate()` for offset; build cursor pagination helper
- Add filter scopes to models (e.g., `scopeByDateRange()`)
- Implement search via database LIKE or full-text indexes

---

### 1.1.4 Response Formats & Envelopes

**Standard Response Envelope:**
```json
{
  "data": { /* resource or array */ },
  "meta": {
    "pagination": {
      "total": 150,
      "per_page": 20,
      "current_page": 1,
      "last_page": 8
    },
    "timestamp": "2026-04-11T10:30:00Z"
  },
  "errors": null
}
```

**Error Response:**
```json
{
  "data": null,
  "errors": [
    {
      "code": "VALIDATION_FAILED",
      "message": "Validation failed",
      "details": {
        "amount": ["Amount must be positive"]
      }
    }
  ]
}
```

**Why table stakes:**
- Consistent error handling across all endpoints
- `meta` includes pagination for list endpoints
- Mobile clients need predictable response structure

---

### 1.1.5 GraphQL Schema — Core Types & Queries

**Core Types (must-have):**
```graphql
type Query {
  # Finance
  accounts(first: Int, after: String, filter: AccountFilter): [Account!]!
  account(id: ID!): Account
  
  transactions(first: Int, after: String, filter: TransactionFilter): [Transaction!]!
  transaction(id: ID!): Transaction
  
  # Travel
  trips(first: Int, after: String): [Trip!]!
  trip(id: ID!): Trip
  
  # Home
  properties(first: Int, after: String): [Property!]!
  property(id: ID!): Property
}

type Account {
  id: ID!
  name: String!
  type: AccountType!
  balance: Float!
  currency: String!
  transactions(first: Int, after: String): [Transaction!]!
  created_at: DateTime!
  updated_at: DateTime!
}

type Transaction {
  id: ID!
  account: Account!
  amount: Float!
  type: TransactionType!
  category: String
  description: String
  date: DateTime!
  created_at: DateTime!
}
```

**Why table stakes:**
- Nested queries reduce N+1 problems (e.g., `trip { budget { remaining } }`)
- Single endpoint vs. multiple REST calls improves mobile UX
- Existing Lighthouse GraphQL setup ✅

---

### 1.1.6 Mutations — Write Operations

**Core Mutations (must-have):**
```graphql
type Mutation {
  # Accounts
  createAccount(input: CreateAccountInput!): AccountPayload!
  updateAccount(id: ID!, input: UpdateAccountInput!): AccountPayload!
  deleteAccount(id: ID!): DeletePayload!
  
  # Transactions
  createTransaction(input: CreateTransactionInput!): TransactionPayload!
  updateTransaction(id: ID!, input: UpdateTransactionInput!): TransactionPayload!
  
  # Trips
  createTrip(input: CreateTripInput!): TripPayload!
  updateTrip(id: ID!, input: UpdateTripInput!): TripPayload!
  addTripParticipant(tripId: ID!, participantEmail: String!): TripParticipantPayload!
  
  # Authentication
  login(email: String!, password: String!): AuthPayload!
  refreshToken(token: String!): AuthPayload!
}

type AuthPayload {
  token: String!
  refreshToken: String!
  user: User!
}
```

**Why table stakes:**
- Users create transactions, trips, properties from mobile
- Mutations validate input + enforce business rules
- Errors bubble up cleanly

---

### 1.1.7 Input Validation

**Requirements:**
- Validate all inputs server-side (never trust client)
- Return structured error responses with field-level detail
- Enforce business rules (e.g., trip end_date > start_date)
- Validate enum values (e.g., AccountType, TransactionType)

**Example validation rules:**
```php
// Create Transaction validation
'amount' => ['required', 'numeric', 'gt:0'],
'type' => ['required', 'in:expense,income,transfer'],
'date' => ['required', 'date', 'before_or_equal:today'],
'account_id' => ['required', 'exists:accounts,id', new UserOwnsAccount($user)],

// Create Trip validation
'title' => ['required', 'string', 'max:255'],
'start_date' => ['required', 'date'],
'end_date' => ['required', 'date', 'after:start_date'],
'budget_limit' => ['nullable', 'numeric', 'gt:0'],
```

**Why table stakes:**
- Bad data → broken dashboards + calculations
- Existing Filament validation patterns transfer to API

---

### 1.1.8 API Documentation

**Requirement:**
- Auto-generated OpenAPI 3.0 spec from GraphQL + REST endpoints
- Postman collection for easy testing
- Swagger UI for browsing endpoints

**Tools:**
- `laravel-openapi` package for REST documentation
- GraphQL schema itself serves as documentation (introspection)
- ApiDoc or Stoplight for visual docs

**Why table stakes:**
- Mobile teams need clear contracts
- Third-party integrations require spec

---

## 1.2 Differentiators (Competitive Advantage)

### 1.2.1 Real-Time Subscriptions (GraphQL Subscriptions)

**Feature:**
```graphql
subscription OnTransactionCreated {
  transactionCreated {
    id
    amount
    type
    account { balance }
  }
}
```

**Use cases:**
- Expense tracked on one device → reflected on all devices instantly
- Dashboard auto-updates without refresh (trip budget spent by participant)
- Real-time notifications for significant events (low balance alert)

**Complexity:** Medium-High
- Requires WebSocket upgrade (Lighthouse supports via `lighthouse-subscriptions`)
- Database polling or event broadcasting (Redis/Pusher)
- Mobile client libraries support (less standard than REST)

**Dependency:** Existing Lighthouse GraphQL setup ✅

---

### 1.2.2 Batch Operations

**Feature:**
```graphql
mutation BatchCreateTransactions($inputs: [CreateTransactionInput!]!) {
  batchCreateTransactions(inputs: $inputs) {
    success
    results {
      id
      amount
    }
    errors {
      index
      message
    }
  }
}
```

**Use cases:**
- Import 50 transactions from CSV in one request
- Create multi-day itinerary in atomic operation
- Bulk update maintenance task statuses

**Complexity:** Low-Medium
- Wrap service logic in transaction
- Return partial success feedback

**Dependency:** Service layer already transactional ✅

---

### 1.2.3 Offline-First Sync (Conflict Resolution)

**Feature:**
```json
POST /sync/merge
{
  "client_version": 5,
  "server_version": 8,
  "local_changes": [
    { "op": "create", "resource": "transaction", "data": {...} },
    { "op": "update", "resource": "account", "id": 1, "data": {...} }
  ]
}
```

**Use cases:**
- User creates transaction offline (airplane)
- Reconnects → app syncs changes while showing merge conflicts
- Resolve by timestamp, user choice, or server wins

**Complexity:** High
- Requires client-side SQLite/Realm database
- Needs version tracking (timestamps, hashes)
- Merge strategy definition (per-resource)

**Dependency:** Significant client-side engineering; backend needs conflict tracking

**Worth it?** Nice-to-have; most users have connectivity. Defer to v3.1+

---

### 1.2.4 Smart Filters & Recommendations

**Feature:**
```graphql
query {
  smartFilters {
    recentlyViewed
    topExpensesThisMonth
    upcomingTrips
    maintenanceDue
  }
}

query {
  recommendations {
    reduceSpendCategories
    unusualExpenses
    savingsOpportunities
  }
}
```

**Use cases:**
- "You spent 3x usual on dining this month"
- "HVAC maintenance due in 7 days"
- "Recommend canceling unused subscriptions"

**Complexity:** Medium
- Requires analytics service (aggregation, trend detection)
- Existing `UtilityAnalytics`, `MaintenanceService` provide foundation ✅

---

### 1.2.5 Mobile-Optimized Dashboards

**Feature:**
```graphql
query {
  mobileDashboard {
    summaryCards {
      totalBalance
      monthlySpent
      upcomingTrips(count: 3)
      maintenanceDue(count: 3)
    }
    charts {
      expensesByCategory(days: 30)
      trendLine(metric: "balance", days: 90)
    }
  }
}
```

**Why differentiator:**
- Existing dashboard is Filament (desktop-first)
- Mobile dashboard is compact, scrollable, prioritized
- One query → all data needed (no waterfall requests)

**Complexity:** Low-Medium
- Aggregate existing service logic
- Add mobile-specific presentation layer

---

### 1.2.6 File Upload & Media Management

**Feature:**
```graphql
mutation {
  uploadReceiptImage(input: {
    file: File!
    transactionId: ID!
  }) {
    transaction {
      id
      receiptUrl
      receiptThumbnail
    }
  }
}
```

**Use cases:**
- Attach receipt photo to transaction
- Upload document to maintenance record
- Photo of property for inventory

**Complexity:** Medium
- File storage (S3, disk)
- Image optimization (thumbnails, resize)
- Virus scanning for security

**Existing code:** `MedicalRecord.file_path` suggests some storage logic exists

---

## 1.3 Anti-Features (What NOT to Build)

### 1.3.1 ❌ Real-Time Analytics Queries
- **Why avoid:** High compute cost; leads to slow API
- **Alternative:** Pre-compute dashboards, serve from cache
- **Exception:** Read-only dashboard queries (not real-time calculations)

### 1.3.2 ❌ Custom Report Builder via API
- **Why avoid:** UX belongs in app; API-driven UI is maintenance nightmare
- **Alternative:** Pre-built reports, export endpoints for data

### 1.3.3 ❌ Third-Party Data Sync Without Limits
- **Why avoid:** Becomes compliance nightmare; regulatory burden
- **Alternative:** Explicit integration endpoints (Plaid for banks, Apple HealthKit)

### 1.3.4 ❌ Unlimited File Upload
- **Why avoid:** Storage costs + security (malware, spam)
- **Alternative:** Quota per user; file type validation; virus scanning

### 1.3.5 ❌ API-First Account Lifecycle Management
- **Why avoid:** Account creation/deletion needs careful UX (confirmation, data retention)
- **Alternative:** Onboarding flow in mobile app; API for modification only

### 1.3.6 ❌ Fine-Grained Permission Tokens
- **Why avoid:** Token explosion; hard to revoke; maintenance burden
- **Alternative:** Single API token per user; revoke via admin portal

---

## 1.4 Complexity & Implementation Order

### Phase 1: Foundation (Sprint 1-2, Est. 10 pts)
```
REST CRUD Endpoints
├─ Account, Transaction, Subscription
├─ Trip, Itinerary, Destination
└─ Property, MaintenanceTask, Utility

JWT Auth + Refresh
Rate Limiting Middleware
Pagination & Filtering
```

**Why first:**
- Unblocks mobile app development
- Builds on existing models + services

---

### Phase 2: Completeness (Sprint 3-4, Est. 13 pts)
```
GraphQL Schema (Core Types & Queries)
├─ Pagination support
├─ Nested relationships
└─ Filter types

Mutations (Create, Update, Delete)
Input Validation + Error Responses
API Documentation (OpenAPI spec)
```

**Why second:**
- Complements REST with query efficiency
- Validates design before optimization

---

### Phase 3: Polish (Sprint 5-6, Est. 8 pts)
```
Batch Operations
Smart Filters & Recommendations
Mobile Dashboard Endpoint
File Upload + Media Management
E2E Testing (REST + GraphQL)
```

**Why third:**
- Value-add; not blocking
- Requires cross-domain testing

---

### Phase 4: Advanced (Sprint 7+, Est. TBD)
```
GraphQL Subscriptions (WebSocket)
Offline-First Sync (v3.1+)
Third-Party Integrations (optional)
```

**Why later:**
- Higher complexity; architectural decisions
- Nice-to-have; not blocking

---

## 1.5 Dependencies on Existing Code

| Component | Status | Notes |
|-----------|--------|-------|
| Models (Account, Trip, Property, etc) | ✅ Complete | Ready for API layer |
| Service Layer (TravelService, MaintenanceService) | ✅ Complete | Reuse for business logic |
| Policies & Authorization | ✅ Complete | Extend for API resource authorization |
| Lighthouse GraphQL Setup | ✅ Complete | Build schema on top |
| Rate Limiting Middleware | ✅ Partial | Extend for JWT rate limits |
| Validation (Filament requests) | ✅ Complete | Reuse validation rules in API |
| Database Migrations | ✅ Complete | No schema changes needed for v1 API |

**No blockers; ready to start API layer immediately.**

---

# PART 2: HEALTH INTEGRATION EXPANSION

## 2.1 Table Stakes (Must-Have)

### 2.1.1 Medical Records Management

#### Core Data Model
| Field | Type | Purpose | Notes |
|-------|------|---------|-------|
| **Appointment** | Entity | Schedule visit | Date, time, provider, notes |
| **Prescription** | Entity | Track medications | Drug name, dosage, frequency, refills |
| **Lab Result** | Entity | Vital data | Test name, value, ref range, date |
| **Vital Signs** | Entity | Daily metrics | BP, heart rate, weight, temperature |
| **Doctor/Provider** | Entity | Relationship | Name, specialty, clinic, phone |

**Existing models:**
- `MedicalRecord` (basic: date, type, doctor, notes) ✅
- `HealthRecord` (metrics: type, value, unit) ✅
- `Medication` (partial: only title exists)

**Gaps:**
- No `Appointment` model
- No `LabResult` model
- `Doctor` (provider) is loose string; should be entity
- Prescription details minimal

**Implementation:**
```php
// New models needed
class Appointment extends Model {
    protected $fillable = ['user_id', 'provider_id', 'date', 'time', 'title', 'location', 'notes'];
    public function provider() { return $this->belongsTo(Doctor::class); }
}

class Doctor extends Model {
    protected $fillable = ['user_id', 'name', 'specialty', 'clinic', 'phone', 'email'];
    public function appointments() { return $this->hasMany(Appointment::class); }
}

class Prescription extends Model {
    protected $fillable = ['user_id', 'drug_name', 'dosage', 'frequency', 'start_date', 'end_date', 'refills'];
}

class LabResult extends Model {
    protected $fillable = ['user_id', 'test_name', 'value', 'unit', 'ref_range_min', 'ref_range_max', 'date'];
}
```

**Complexity:** Low-Medium
- Straightforward CRUD; no complex business logic
- Extend existing `HealthRecord` for vital signs ✅

---

### 2.1.2 Health Metrics & Vitals

**Core metrics tracked:**
| Metric | Unit | Frequency | Notes |
|--------|------|-----------|-------|
| **Weight** | kg/lbs | Daily optional | Trend tracking important |
| **Blood Pressure** | mmHg | Daily-weekly | Systolic/diastolic |
| **Heart Rate** | bpm | Daily-weekly | Resting |
| **Sleep Duration** | hours | Daily | Start, end, quality |
| **Temperature** | °C/°F | As needed | For illness tracking |
| **Blood Sugar** | mg/dL | Daily (diabetics) | Pre/post meal |

**Existing:** `HealthRecord` model covers this ✅

**Tables stakes:**
- User logs weight daily (trend dashboard)
- Logs BP when checking (historical chart)
- Sleep log: "I slept 6.5 hours last night, poor quality"

**Implementation:**
```php
// Extend HealthRecord
class HealthRecord extends Model {
    protected $fillable = ['user_id', 'date', 'type', 'value', 'unit', 'notes', 'reading_time'];
    
    public static function logWeight($user, $kg, $date = null) { /* ... */ }
    public static function logBP($user, $systolic, $diastolic) { /* ... */ }
    public static function logSleep($user, $hours, $quality = null) { /* ... */ }
}
```

**Complexity:** Low
- Existing HealthRecord model ✅
- Simple date/value storage

---

### 2.1.3 Appointments & Reminders

**Core features:**
1. **Create appointment** with provider, date/time, reason
2. **List upcoming** appointments (next 7, 30 days)
3. **Appointment reminders:**
   - 24 hours before: push notification
   - 1 hour before: SMS (optional)
   - Post-appointment: prompt for notes/outcome

**Implementation:**
```php
// Appointment model
class Appointment extends Model {
    protected $fillable = ['user_id', 'provider_id', 'date', 'time', 'title', 'location', 'notes', 'status'];
    
    public function provider() { return $this->belongsTo(Doctor::class); }
    
    public function scopeUpcoming($query) {
        return $query->where('date', '>=', today())->orderBy('date');
    }
}

// Observer/Job for reminders
class AppointmentReminderJob implements ShouldQueue {
    public function handle(Appointment $appointment) {
        // Send push notification 24 hours before
        // Send SMS reminder 1 hour before
    }
}
```

**Complexity:** Low-Medium
- Model + Job pattern (existing in codebase) ✅
- Leverage existing notification system

---

### 2.1.4 Doctor/Provider Management

**Core features:**
1. **Add doctor** with specialty, clinic, contact info
2. **Link to appointments** + prescriptions
3. **Contact quick action** (call, email, message)

**Implementation:**
```php
class Doctor extends Model {
    protected $fillable = ['user_id', 'name', 'specialty', 'clinic', 'phone', 'email', 'address'];
    
    public function appointments() { return $this->hasMany(Appointment::class, 'provider_id'); }
    public function prescriptions() { return $this->hasMany(Prescription::class, 'provider_id'); }
}
```

**Complexity:** Low
- Simple entity management
- No complex relationships

---

### 2.1.5 Health Dashboard

**Core dashboard cards:**
```
┌─────────────────────────────────────────────┐
│ HEALTH DASHBOARD                             │
├─────────────────────────────────────────────┤
│ 📊 VITAL TRENDS (30-day)                     │
│  • Weight: 75kg (↓0.5 kg)                    │
│  • BP: 120/80 (normal)                       │
│  • Sleep: 7h avg (↑0.5h)                     │
├─────────────────────────────────────────────┤
│ 📋 UPCOMING APPOINTMENTS                     │
│  • Dr. Smith (Cardiologist) - Fri, 3pm      │
│  • Dentist - Next Tue, 10am                 │
├─────────────────────────────────────────────┤
│ 💊 ACTIVE PRESCRIPTIONS                      │
│  • Lisinopril 10mg - Daily                   │
│  • Omeprazole 20mg - As needed               │
├─────────────────────────────────────────────┤
│ 🧪 RECENT LAB RESULTS                        │
│  • Cholesterol (3/1) - Elevated              │
│  • Glucose (3/1) - Normal                    │
└─────────────────────────────────────────────┘
```

**Data needed:**
- Vital trends (weight, BP, sleep over 30 days)
- Next 3-5 appointments
- Active prescriptions
- Lab results from last 90 days

**Implementation:**
```php
class HealthDashboardService {
    public function vitalTrends($user, $days = 30) { /* weight, bp, sleep charts */ }
    public function upcomingAppointments($user, $count = 5) { /* sorted by date */ }
    public function activePrescriptions($user) { /* current only */ }
    public function recentLabResults($user, $days = 90) { /* sorted by date */ }
}
```

**Complexity:** Low-Medium
- Leverage `UtilityAnalytics` pattern for aggregation ✅
- Chart data in mobile app

---

## 2.2 Differentiators (Competitive Advantage)

### 2.2.1 Apple HealthKit / Google Fit Integration

**Feature:**
- Read: weight, heart rate, steps, sleep from native health apps
- Write: log health metrics → native health app
- Sync: bidirectional, daily refresh

**Use cases:**
- User's Fitbit syncs to Apple Health → Second Brain reads it
- User logs weight in Second Brain → shows up in Health.app
- Running metrics from Strava → visible in dashboard

**Implementation:**
```php
// HealthKitSync service
class HealthKitSyncService {
    public function syncFromAppleHealth($user) {
        // Read via HealthKit API
        // Store in HealthRecord model
    }
    
    public function syncToAppleHealth($user, HealthRecord $record) {
        // Write metric back to HealthKit
    }
}
```

**Complexity:** High
- Requires iOS/Android native integration (not backend)
- Backend: FHIR data mapping, sync scheduling
- Existing models support this ✅

**Worth it?** Yes, strong differentiator; but app-dependent

**Timeline:** v3.1+ (after core health features)

---

### 2.2.2 Health Insights & Analytics

**Features:**
```
Weight Trend Analysis
├─ Weight loss velocity (kg/week)
├─ Trend line (past 90 days)
└─ Goals & alerts (gained 3 lbs, below goal)

Blood Pressure Trends
├─ Systolic trend line
├─ Diastolic trend line
└─ Risk alerts (consistently high)

Sleep Quality Analysis
├─ Duration trends
├─ Quality correlation (mood, weight, exercise)
└─ Recommendations (sleep more, consistent bedtime)

Lab Result Tracking
├─ Out-of-range alerts
├─ Historical trends (cholesterol trending up)
└─ Doctor notifications (abnormal result, call doctor)
```

**Complexity:** Medium-High
- Statistical analysis (trending, correlation)
- Machine learning (optional: predictive alerts)
- Requires analytics infrastructure

**Timeline:** v3.2+ (lower priority)

---

### 2.2.3 Medication Reminders & Adherence

**Features:**
- Create prescription with schedule (e.g., "10mg at 8am + 8pm")
- Daily/weekly reminders (push, SMS)
- Track adherence (did you take it?)
- Refill alerts (only 2 pills left)

**Implementation:**
```php
class MedicationReminder implements ShouldQueue {
    public function handle(Prescription $prescription) {
        // Send reminder at scheduled time
        $prescription->logReminder('sent');
    }
}

class Prescription extends Model {
    public function adherenceLog() { return $this->hasMany(MedicationAdherence::class); }
    
    public function logTaken() { $this->adherenceLog()->create(['taken_at' => now()]); }
}
```

**Complexity:** Medium
- Scheduling (CRON-based or queue-based)
- Multi-channel notifications (push, SMS, in-app)

**Timeline:** v3.1

---

### 2.2.4 Provider Communication

**Features:**
- Secure messaging with doctor
- Document sharing (test results, images)
- Request appointment from app
- Refill prescription requests

**Complexity:** High
- Requires HIPAA compliance (if regulated)
- Secure document storage
- Provider authentication/authorization

**Timeline:** v3.2+ (regulatory consideration)

---

### 2.2.5 Medical Records Export

**Features:**
- Export health history as PDF/HL7
- Share with new doctor (printable summary)
- Backup to cloud

**Complexity:** Medium
- PDF generation (existing `TravelPdfExporter` pattern) ✅
- HL7/FHIR format support
- Cloud backup integration

---

## 2.3 Anti-Features (What NOT to Build)

### 2.3.1 ❌ Diagnosis or Medical Advice
- **Why avoid:** Liability + regulatory (FDA, medical boards)
- **Alternative:** Link to UpToDate/WebMD for info; always defer to doctor

### 2.3.2 ❌ Automated Medication Interaction Checking
- **Why avoid:** High liability if wrong; pharmacist job
- **Alternative:** Link to pill checker; suggest user confirm with pharmacist

### 2.3.3 ❌ Third-Party Pharmacy Integration
- **Why avoid:** Licensing + DEA compliance; pharmacy APIs are restricted
- **Alternative:** Patient prints refill request; submits manually

### 2.3.4 ❌ Telemedicine Without Regulation
- **Why avoid:** State medical board requirements; licensing per state
- **Alternative:** Link to existing platforms (Teladoc, MDLive)

### 2.3.5 ❌ Health Data Monetization
- **Why avoid:** User trust killer; regulatory nightmare (HIPAA)
- **Alternative:** Explicit privacy policy; no data sharing

### 2.3.6 ❌ Real-Time Health Monitoring
- **Why avoid:** Not FDA-cleared; liability for false alerts
- **Alternative:** Manual logging only; historical trend display

---

## 2.4 Complexity & Implementation Order

### Phase 1: Foundation (Sprint 1-2, Est. 8 pts)
```
Medical Records Management
├─ Appointment model + CRUD
├─ Doctor model + CRUD
├─ Prescription model + CRUD
└─ LabResult model + CRUD

Health Metrics
├─ Extend HealthRecord for vitals
└─ Logging service

Health Dashboard
├─ Aggregate service
└─ REST/GraphQL endpoint
```

**Why first:**
- Unblocks mobile app
- Reuses existing patterns (HealthRecord) ✅

---

### Phase 2: Notifications (Sprint 3, Est. 5 pts)
```
Appointment Reminders
├─ Scheduled notifications
├─ Push + SMS support
└─ Observer pattern

Medication Reminders
├─ Prescription adherence tracking
└─ Refill alerts
```

**Why second:**
- Builds on Phase 1 models
- High UX impact (users need alerts)

---

### Phase 3: Integration (Sprint 4+, Est. TBD)
```
Apple HealthKit / Google Fit Sync
├─ Sync service (read/write)
└─ Daily refresh job

Health Analytics & Insights
├─ Trend analysis
└─ Risk alerts
```

**Why later:**
- Depends on mobile app native implementation
- Nice-to-have; not blocking core health features

---

## 2.5 Dependencies on Existing Code

| Component | Status | Notes |
|-----------|--------|-------|
| HealthRecord Model | ✅ Complete | Extend for vitals |
| MedicalRecord Model | ✅ Partial | Rename to Appointment? Or extend? |
| Medication Model | ✅ Partial | Extend with dosage, frequency, schedule |
| Notification System | ✅ Complete | Use for reminders |
| Service Layer Pattern | ✅ Complete | Build HealthDashboardService, etc. |
| Policies & Authorization | ✅ Complete | Extend for health record access |
| Database | ✅ Complete | Add Appointment, Doctor, LabResult tables |

**Gaps to fill:**
- Add `Appointment`, `Doctor`, `LabResult` models
- Extend `Medication` with schedule details
- Create `HealthDashboardService`
- Add appointment + prescription reminder jobs

---

# PART 3: INTEGRATION PATTERNS

## 3.1 Mobile API ↔ Health Integration

### Real-World User Flow

```
Mobile App (Finance User)
  ├─ REST: GET /accounts → $10k total balance
  ├─ GraphQL: query { trips { budget { remaining } } } → $500 left for Europe trip
  └─ REST: GET /health/dashboard → Weight ↓ 2 lbs, 3 appointments coming up

Mobile App (Health User)
  ├─ REST: POST /health/metrics → Log weight 75kg
  ├─ REST: GET /health/appointments?upcoming=7 → Next 3 appointments
  ├─ GraphQL subscription { appointmentReminder } → Push notification (realtime)
  └─ REST: POST /health/prescriptions/1/take → Log medication adherence

System Interactions
  ├─ Appointment reminder job triggers → sends push notification
  ├─ Lab result arrives → adds HealthRecord + sends alert
  ├─ Prescription refill needed → creates alert in dashboard
  └─ Weight loss milestone → sends congratulations notification
```

---

## 3.2 Cross-Domain Queries

**Use case:** User checks travel + health before trip

```graphql
query PreTravelCheckup {
  # Finance
  accounts { id balance }
  
  # Travel
  trips(first: 1, filter: { status: UPCOMING }) {
    id title startDate budget { spent remaining }
    participants { email }
  }
  
  # Health
  upcomingAppointments(days: 14) { date provider }
  activePrescriptions { drugName frequency }
  recentVitals(days: 7) { type value date }
}
```

**Response includes:** All 3 domains in one query → mobile app renders "Ready for trip?" dashboard

---

## 3.3 Notification Orchestration

**Decision tree:**
```
Event: AppointmentReminder
  if (appointment.time - now() == 24 hours)
    → Send push notification (high priority)
  if (appointment.time - now() == 1 hour)
    → Send SMS (if opted-in)
  if (appointment.time == past && no outcome)
    → Send reminder to log appointment outcome

Event: LabResultAbnormal
  → Send push + email (medical urgency)
  → Log AuditLog (HIPAA compliance)
  → Optional: Alert doctor

Event: PrescriptionRefillNeeded
  → In-app alert + push notification
  → Optional: SMS reminder
```

---

# PART 4: SECURITY & COMPLIANCE

## 4.1 API Security (Mobile APIs)

| Control | Requirement | Implementation |
|---------|-------------|-----------------|
| **JWT Expiration** | Access tokens: 15min, Refresh: 7 days | Encode in token payload |
| **HTTPS Only** | All API traffic over TLS 1.3+ | Enforce in middleware |
| **CORS** | Allow mobile app origin only | Configure in `config/cors.php` |
| **Rate Limiting** | 100 req/min per user | Existing `ApiRateLimitMiddleware` ✅ |
| **Input Validation** | Server-side always | Existing validation ✅ |
| **Authorization** | Policy-based (Spatie) | Extend for API resources ✅ |
| **Audit Logging** | All mutations logged | Extend `AuditLog` model ✅ |

---

## 4.2 Health Data Security (HIPAA/GDPR)

| Control | Requirement | Notes |
|---------|-------------|-------|
| **Data Encryption** | At-rest (AES-256) + in-transit (TLS) | Use Laravel encryption |
| **Access Control** | User can only see own health records | Policies ✅ |
| **Audit Trail** | Who accessed what, when | Expand AuditLog for HIPAA |
| **Data Retention** | Respect HIPAA retention rules | Add deletion policies |
| **Right to Delete** | User can request data deletion | Implement GDPR compliance |
| **Vendor Compliance** | Cloud storage (S3) meets HIPAA/GDPR | Verify with AWS/provider |

---

## 4.3 Recommendations

1. **Add HIPAA compliance checklist** to `.planning/`
2. **Implement audit logging** for all health mutations
3. **Require explicit consent** before health feature use
4. **Encrypt PII at rest** (doctor names, prescription details)
5. **Regular security audit** before production health release

---

# PART 5: SUMMARY TABLE

## Feature Priorities & Timeline

| Feature | Category | Priority | Complexity | Est. Effort | Timeline |
|---------|----------|----------|-----------|------------|----------|
| **REST CRUD** | API | 🔴 Critical | Low | 10 pts | Sprint 1-2 |
| **JWT Auth** | API | 🔴 Critical | Medium | 5 pts | Sprint 1 |
| **GraphQL Schema** | API | 🔴 Critical | Medium | 8 pts | Sprint 2-3 |
| **Pagination & Filtering** | API | 🔴 Critical | Low | 3 pts | Sprint 1-2 |
| **API Documentation** | API | 🟠 High | Low | 3 pts | Sprint 3 |
| **Medical Records** | Health | 🔴 Critical | Low | 8 pts | Sprint 1-2 |
| **Health Metrics** | Health | 🔴 Critical | Low | 3 pts | Sprint 1 |
| **Appointments** | Health | 🔴 Critical | Low | 5 pts | Sprint 1-2 |
| **Health Dashboard** | Health | 🔴 Critical | Medium | 5 pts | Sprint 2-3 |
| **Appointment Reminders** | Health | 🟠 High | Medium | 5 pts | Sprint 3 |
| **Medication Reminders** | Health | 🟠 High | Medium | 5 pts | Sprint 3 |
| **HealthKit/Google Fit** | Health | 🟡 Nice-to-Have | High | TBD | v3.1+ |
| **Health Analytics** | Health | 🟡 Nice-to-Have | High | TBD | v3.2+ |
| **Subscriptions (GraphQL)** | API | 🟡 Nice-to-Have | Medium | 5 pts | v3.1+ |
| **Batch Operations** | API | 🟡 Nice-to-Have | Low | 3 pts | v3.1+ |

---

## Quick Reference: What to Build First

### V3.0 Deliverables (MVP)
✅ REST CRUD (Finance, Travel, Home)  
✅ JWT Auth + Rate Limiting  
✅ GraphQL Schema (core types + queries)  
✅ Medical Records (Appointment, Doctor, Prescription, LabResult)  
✅ Health Metrics & Dashboard  
✅ Appointment + Medication Reminders  
✅ API Documentation  

### V3.1+ Enhancements
🟡 GraphQL Subscriptions  
🟡 Batch Operations  
🟡 HealthKit/Google Fit Sync  
🟡 Health Analytics  
🟡 Offline-First Sync  

---

## Implementation Checklist

### Before Coding
- [ ] Finalize data models (Appointment, Doctor, LabResult)
- [ ] Design API response envelope (pagination, errors)
- [ ] Design GraphQL schema (input types, filters)
- [ ] Decide on pagination strategy (cursor vs offset)
- [ ] Plan notification architecture (jobs, channels)
- [ ] HIPAA compliance review (if regulated)

### Development
- [ ] Build REST CRUD endpoints
- [ ] Add JWT auth + refresh logic
- [ ] Build GraphQL schema
- [ ] Add health models + migrations
- [ ] Create appointment + medication jobs
- [ ] Add integration tests (REST + GraphQL)
- [ ] Generate API documentation

### Testing
- [ ] Unit tests for services
- [ ] Feature tests for endpoints
- [ ] Authentication + authorization tests
- [ ] Rate limiting tests
- [ ] Health record privacy tests

### Deployment
- [ ] API documentation published
- [ ] Rate limits tuned for production
- [ ] Health data encryption enabled
- [ ] Audit logging enabled
- [ ] Monitoring + alerting configured

---

## References

**Industry Standards:**
- REST: RESTful API Design Best Practices (Google, Microsoft)
- GraphQL: Apollo Server Best Practices
- Mobile APIs: OWASP Mobile Security Top 10
- Health Data: HIPAA Compliance Guide, FHIR Standard (HL7)

**Tools:**
- JWT: tymon/jwt-auth
- GraphQL: Lighthouse (existing ✅)
- API Docs: Laravel OpenAPI, Swagger UI
- Health Integration: Apple HealthKit SDK, Google Fit API

---

**Document prepared for:** v3.0 Planning  
**Next steps:** Review with team, prioritize features, begin Phase 1 development
