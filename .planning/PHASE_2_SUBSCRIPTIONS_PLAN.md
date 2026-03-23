# 📋 Phase 2: Subscriptions – Detailed Implementation Plan

**Goal:** Manage recurring subscriptions with frequency-based cost calculations.

---

## 📊 Data Model & Frequency Logic

### Core Concept
**Monthly Cost Calculation:**
```
IF frequency = MONTHLY:
  monthly_cost = user_input
  annual_cost = monthly_cost × 12

IF frequency = ANNUAL:
  annual_cost = user_input
  monthly_cost = annual_cost ÷ 12

IF frequency = BIENNIAL (every 2 years):
  annual_cost = user_input
  monthly_cost = annual_cost ÷ 24
```

### Database Table

```sql
CREATE TABLE subscriptions (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT NOT NULL,
  name VARCHAR(255) NOT NULL,
  
  -- Cost fields (one is input, other is calculated)
  monthly_cost DECIMAL(10,2),
  annual_cost DECIMAL(10,2),
  
  -- Frequency determines cost calculation
  frequency ENUM('monthly','annual','biennial') NOT NULL,
  
  -- Renewal tracking
  day_of_month INT,
  next_renewal_date DATE,
  
  -- Debit account & category
  account_id BIGINT,
  category_id BIGINT,
  
  -- Automation
  auto_create_transaction BOOLEAN DEFAULT false,
  
  -- Status
  status ENUM('active','inactive','cancelled') NOT NULL,
  
  -- Metadata
  notes TEXT,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  deleted_at TIMESTAMP,
  
  -- Foreign keys
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (account_id) REFERENCES accounts(id),
  FOREIGN KEY (category_id) REFERENCES transaction_categories(id),
  
  -- Indexes
  INDEX idx_user_id (user_id),
  INDEX idx_status (status),
  INDEX idx_next_renewal (next_renewal_date)
);
```

---

## 🔧 Enums (Consistent with Project Pattern)

### 1. `SubscriptionFrequency` Enum
**File:** `app/Enums/SubscriptionFrequency.php`

```php
<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SubscriptionFrequency: string implements HasLabel, HasColor
{
    case MONTHLY = 'monthly';
    case ANNUAL = 'annual';
    case BIENNIAL = 'biennial';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::MONTHLY => 'Monthly',
            self::ANNUAL => 'Annual',
            self::BIENNIAL => 'Every 2 Years',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::MONTHLY => 'info',
            self::ANNUAL => 'warning',
            self::BIENNIAL => 'success',
        };
    }

    /**
     * Get divisor for monthly cost calculation.
     * Used to convert annual_cost to monthly_cost
     */
    public function getMonthlyDivisor(): int
    {
        return match ($this) {
            self::MONTHLY => 1,
            self::ANNUAL => 12,
            self::BIENNIAL => 24,
        };
    }
}
```

### 2. `SubscriptionStatus` Enum
**File:** `app/Enums/SubscriptionStatus.php`

```php
<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SubscriptionStatus: string implements HasLabel, HasColor
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case CANCELLED = 'cancelled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::INACTIVE => 'gray',
            self::CANCELLED => 'danger',
        };
    }
}
```

---

## 📦 Implementation Tasks

### [ ] **Task 1: Create Enums**

**Files to create:**
- `app/Enums/SubscriptionFrequency.php`
- `app/Enums/SubscriptionStatus.php`

**Notes:**
- Follow pattern from `CreditCardType.php` and `CreditCardStatus.php`
- Implement `HasLabel` and `HasColor` for Filament integration
- Add `getMonthlyDivisor()` method to SubscriptionFrequency for cost calculations

---

### [ ] **Task 2: Create Migration**

**File:** `database/migrations/2026_03_23_create_subscriptions_table.php`

**Key points:**
- Use string enum for `frequency` and `status`
- Add `monthly_cost` and `annual_cost` as DECIMAL(10,2)
- Add `day_of_month` (nullable, 1-31 default)
- Add soft deletes (use `$table->softDeletes()`)
- Foreign keys for user, account, category
- Indexes on user_id, status, next_renewal_date

---

### [ ] **Task 3: Create Model**

**File:** `app/Models/Subscription.php`

**Structure:**
```php
<?php

namespace App\Models;

use App\Enums\SubscriptionFrequency;
use App\Enums\SubscriptionStatus;
use App\Traits\HasUserScoping;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use SoftDeletes, HasUserScoping;

    protected $fillable = [
        'user_id',
        'name',
        'monthly_cost',
        'annual_cost',
        'frequency',
        'day_of_month',
        'next_renewal_date',
        'account_id',
        'category_id',
        'auto_create_transaction',
        'status',
        'notes',
    ];

    protected $casts = [
        'frequency' => SubscriptionFrequency::class,
        'status' => SubscriptionStatus::class,
        'monthly_cost' => 'decimal:2',
        'annual_cost' => 'decimal:2',
        'next_renewal_date' => 'date',
        'auto_create_transaction' => 'boolean',
    ];

    // Relationships
    public function user() { return $this->belongsTo(User::class); }
    public function account() { return $this->belongsTo(Account::class); }
    public function category() { return $this->belongsTo(TransactionCategory::class); }

    // Scopes
    public function scopeActive($query) { return $query->where('status', SubscriptionStatus::ACTIVE); }
    public function scopeForRenewal($query, int $days = 7) {
        return $query->whereBetween('next_renewal_date', [now(), now()->addDays($days)]);
    }
}
```

**Key features:**
- Use `HasUserScoping` trait (consistent with project)
- Cast enums to SubscriptionFrequency/SubscriptionStatus
- Cast costs as `decimal:2`
- Add `active()` and `forRenewal()` scopes
- Soft deletes for cancelled subscriptions

---

### [ ] **Task 4: Create Service**

**File:** `app/Services/SubscriptionService.php`

**Methods:**
```php
<?php

namespace App\Services;

use App\Enums\SubscriptionFrequency;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class SubscriptionService
{
    /**
     * Calculate monthly cost from annual cost and frequency
     */
    public function calculateMonthlyCost(
        float $annualCost,
        SubscriptionFrequency $frequency
    ): float {
        $divisor = $frequency->getMonthlyDivisor();
        return round($annualCost / $divisor, 2);
    }

    /**
     * Calculate annual cost from monthly cost and frequency
     */
    public function calculateAnnualCost(
        float $monthlyCost,
        SubscriptionFrequency $frequency
    ): float {
        $multiplier = $frequency->getMonthlyDivisor();
        return round($monthlyCost * $multiplier, 2);
    }

    /**
     * Get total monthly cost for active subscriptions
     */
    public function getMonthlyTotal(?int $userId = null): float
    {
        $userId ??= Auth::id();
        
        return Subscription::where('user_id', $userId)
            ->where('status', SubscriptionStatus::ACTIVE)
            ->get()
            ->sum(fn($sub) => $this->calculateMonthlyCost(
                $sub->annual_cost ?? 0,
                $sub->frequency
            ));
    }

    /**
     * Get upcoming renewals within N days
     */
    public function getUpcomingRenewals(
        int $days = 7,
        ?int $userId = null
    ): Collection {
        $userId ??= Auth::id();
        
        return Subscription::where('user_id', $userId)
            ->forRenewal($days)
            ->orderBy('next_renewal_date')
            ->get();
    }

    /**
     * Calculate next renewal date based on frequency
     */
    public function calculateNextRenewalDate(
        Subscription $sub,
        ?Carbon $from = null
    ): Carbon {
        $from ??= now();
        
        return match ($sub->frequency) {
            SubscriptionFrequency::MONTHLY => 
                $from->copy()->addMonth()->day($sub->day_of_month ?? 1),
            SubscriptionFrequency::ANNUAL => 
                $from->copy()->addYear()->day($sub->day_of_month ?? 1),
            SubscriptionFrequency::BIENNIAL => 
                $from->copy()->addYears(2)->day($sub->day_of_month ?? 1),
        };
    }

    /**
     * Process renewal: create transaction if enabled
     */
    public function processRenewal(Subscription $sub): void
    {
        if (!$sub->auto_create_transaction || !$sub->account_id) {
            return;
        }

        // Create expense transaction
        // Implementation: delegate to TransactionService
        // or create directly if simpler
    }
}
```

---

### [ ] **Task 5: Create Observer**

**File:** `app/Observers/SubscriptionObserver.php`

**Hooks:**
```php
<?php

namespace App\Observers;

use App\Enums\SubscriptionFrequency;
use App\Models\Subscription;
use App\Services\SubscriptionService;

class SubscriptionObserver
{
    public function __construct(private SubscriptionService $service) {}

    /**
     * Calculate costs and next renewal date on creation
     */
    public function creating(Subscription $subscription): void
    {
        // Calculate missing cost based on frequency
        if (!$subscription->annual_cost && $subscription->monthly_cost) {
            $subscription->annual_cost = $this->service->calculateAnnualCost(
                $subscription->monthly_cost,
                $subscription->frequency
            );
        } elseif (!$subscription->monthly_cost && $subscription->annual_cost) {
            $subscription->monthly_cost = $this->service->calculateMonthlyCost(
                $subscription->annual_cost,
                $subscription->frequency
            );
        }

        // Set default day_of_month
        $subscription->day_of_month ??= 1;

        // Calculate next renewal date
        $subscription->next_renewal_date = $this->service->calculateNextRenewalDate($subscription);
    }

    /**
     * Recalculate on update if frequency or costs change
     */
    public function updating(Subscription $subscription): void
    {
        $this->creating($subscription);
    }
}
```

**Register in `AppServiceProvider`:**
```php
public function boot(): void
{
    Subscription::observe(SubscriptionObserver::class);
}
```

---

### [ ] **Task 6: Create Filament Resource**

**File:** `app/Filament/Resources/SubscriptionResource.php`

**Key form fields:**
```php
public static function form(Form $form): Form
{
    return $form
        ->schema([
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            
            Select::make('frequency')
                ->options(SubscriptionFrequency::class)
                ->required()
                ->reactive(),
            
            // Show either monthly or annual input based on frequency
            TextInput::make('monthly_cost')
                ->numeric()
                ->prefix('€')
                ->step(0.01)
                ->visible(fn(callable $get) => $get('frequency') === SubscriptionFrequency::MONTHLY->value)
                ->reactive(),
            
            TextInput::make('annual_cost')
                ->numeric()
                ->prefix('€')
                ->step(0.01)
                ->visible(fn(callable $get) => $get('frequency') !== SubscriptionFrequency::MONTHLY->value)
                ->reactive(),
            
            // Display calculated monthly cost (read-only)
            TextInput::make('calculated_monthly')
                ->disabled()
                ->dehydrated(false)
                ->formatStateUsing(fn($record) => $record?->monthly_cost ?? '—'),
            
            Select::make('account_id')
                ->relationship('account', 'name')
                ->searchable()
                ->preload(),
            
            Select::make('category_id')
                ->relationship('category', 'name')
                ->searchable()
                ->preload(),
            
            TextInput::make('day_of_month')
                ->numeric()
                ->minValue(1)
                ->maxValue(31)
                ->default(1),
            
            DatePicker::make('next_renewal_date')
                ->required(),
            
            Select::make('status')
                ->options(SubscriptionStatus::class)
                ->required()
                ->default(SubscriptionStatus::ACTIVE),
            
            Toggle::make('auto_create_transaction')
                ->default(false),
            
            Textarea::make('notes'),
        ]);
}
```

**Table columns:**
```php
public static function table(Table $table): Table
{
    return $table
        ->columns([
            TextColumn::make('name')
                ->searchable(),
            
            TextColumn::make('frequency')
                ->badge(),
            
            TextColumn::make('monthly_cost')
                ->money('EUR')
                ->sortable(),
            
            TextColumn::make('next_renewal_date')
                ->date()
                ->sortable(),
            
            TextColumn::make('status')
                ->badge(),
        ])
        ->filters([
            SelectFilter::make('status')
                ->options(SubscriptionStatus::class),
            SelectFilter::make('frequency')
                ->options(SubscriptionFrequency::class),
        ]);
}
```

---

### [ ] **Task 7: Create Tests**

**File:** `tests/Unit/SubscriptionServiceTest.php`

**Test cases:**
```php
public function testMonthlyToAnnualConversion(): void
{
    $service = new SubscriptionService();
    
    $monthly = 10.00;
    $annual = $service->calculateAnnualCost($monthly, SubscriptionFrequency::MONTHLY);
    $this->assertEquals(120.00, $annual);
    
    $annual = $service->calculateAnnualCost($monthly, SubscriptionFrequency::BIENNIAL);
    $this->assertEquals(240.00, $annual);
}

public function testAnnualToMonthlyConversion(): void
{
    $service = new SubscriptionService();
    
    $annual = 120.00;
    $monthly = $service->calculateMonthlyCost($annual, SubscriptionFrequency::ANNUAL);
    $this->assertEquals(10.00, $monthly);
    
    $monthly = $service->calculateMonthlyCost($annual, SubscriptionFrequency::BIENNIAL);
    $this->assertEquals(5.00, $monthly);
}

public function testMonthlyTotalCalculation(): void
{
    // Create subscriptions with different frequencies
    $sub1 = Subscription::factory()->state([
        'frequency' => SubscriptionFrequency::MONTHLY,
        'monthly_cost' => 10.00,
        'user_id' => Auth::id(),
    ])->create();
    
    $sub2 = Subscription::factory()->state([
        'frequency' => SubscriptionFrequency::ANNUAL,
        'annual_cost' => 120.00,
        'user_id' => Auth::id(),
    ])->create();
    
    $service = new SubscriptionService();
    $total = $service->getMonthlyTotal();
    
    // 10 + (120/12) = 10 + 10 = 20
    $this->assertEquals(20.00, $total);
}

public function testUpcomingRenewals(): void
{
    $sub = Subscription::factory()->state([
        'next_renewal_date' => now()->addDays(5),
        'user_id' => Auth::id(),
    ])->create();
    
    $service = new SubscriptionService();
    $upcoming = $service->getUpcomingRenewals(7);
    
    $this->assertCount(1, $upcoming);
    $this->assertTrue($upcoming->first()->is($sub));
}

public function testNextRenewalDateCalculation(): void
{
    $service = new SubscriptionService();
    
    $sub = new Subscription([
        'frequency' => SubscriptionFrequency::MONTHLY,
        'day_of_month' => 15,
    ]);
    
    $nextDate = $service->calculateNextRenewalDate($sub, now());
    $this->assertEquals(15, $nextDate->day);
}
```

---

### [ ] **Task 8: Create Dashboard Widget**

**File:** `app/Filament/Widgets/MonthlySubscriptionCostWidget.php`

```php
<?php

namespace App\Filament\Widgets;

use App\Services\SubscriptionService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MonthlySubscriptionCostWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $service = new SubscriptionService();
        $total = $service->getMonthlyTotal();

        return [
            Stat::make('Monthly Subscription Cost', '€' . number_format($total, 2))
                ->description('Total active subscriptions')
                ->color('info'),
        ];
    }
}
```

**File:** `app/Filament/Widgets/UpcomingRenewalsWidget.php`

```php
<?php

namespace App\Filament\Widgets;

use App\Services\SubscriptionService;
use App\Models\Subscription;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UpcomingRenewalsWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $service = new SubscriptionService();
        $upcoming = $service->getUpcomingRenewals(7);

        return $table
            ->query(Subscription::whereIn('id', $upcoming->pluck('id')))
            ->columns([
                // name, next_renewal_date, frequency, monthly_cost, status
            ]);
    }
}
```

**Register in Dashboard page** (`app/Filament/Pages/Dashboard.php`):
```php
public function getWidgets(): array
{
    return [
        MonthlySubscriptionCostWidget::class,
        UpcomingRenewalsWidget::class,
    ];
}
```

---

## 🧪 Acceptance Criteria

- [ ] Create, edit, delete subscriptions via Filament
- [ ] Frequency selector changes form layout (monthly vs annual input)
- [ ] Missing cost auto-calculated on save (observer)
- [ ] Monthly total correctly sums all frequencies:
  - Monthly: use as-is
  - Annual: divide by 12
  - Biennial: divide by 24
- [ ] Next renewal date auto-calculated (observer)
- [ ] Dashboard widget shows total monthly cost
- [ ] Dashboard widget shows upcoming renewals (next 7 days)
- [ ] All tests passing
- [ ] User scoping works (each user sees only their subscriptions)
- [ ] Soft deletes work (status=cancelled also via delete)

---

## 📈 Implementation Order

1. **Enums** (SubscriptionFrequency, SubscriptionStatus)
2. **Migration + Model**
3. **Service** (cost calculations)
4. **Observer** (auto-calculations)
5. **Tests** (validate all scenarios)
6. **Filament Resource** (CRUD UI)
7. **Dashboard Widgets** (display)

---

## ⏱️ Time Estimate

- **Enums + Migration + Model:** 1 day
- **Service + Observer + Tests:** 1 day
- **Filament Resource:** 1 day
- **Dashboard Widgets:** 0.5 day
- **Integration Testing:** 0.5 day

**Total:** 2–3 days

---

## 🎯 Key Patterns from Project

**Followed patterns:**
✅ Enums with `HasLabel` + `HasColor` (like `CreditCardType.php`)  
✅ Service layer for business logic (like `RevolvingCreditCalculator.php`)  
✅ Observer for auto-calculations (like `CreditCardCycleObserver.php`)  
✅ Model scopes for filtering (like `.active()` in loans)  
✅ `HasUserScoping` trait for multi-user isolation  
✅ Filament resources with reactive form fields  

---

## 🚀 Ready to Implement?

Shall I generate all files following this plan?
