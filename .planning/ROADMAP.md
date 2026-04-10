# Milestone v2.0 Roadmap

**Milestone:** v2.0 — Enhancement & Analytics  
**Version:** 2.0  
**Phase Range:** 9–10  
**Status:** Active  
**Coverage:** 39/39 requirements mapped ✓  
**Last Updated:** 2026-04-10

---

## Phases

- [x] **Phase 9: Travel Domain** - Complete trip planning, itineraries, and travel expense tracking ✓ COMPLETE
- [ ] **Phase 10: Home Management & Quality** - Complete property, maintenance, utilities, inventory management + cross-phase testing

---

## Phase Details

### Phase 9: Travel Domain
**Goal:** Users can plan and manage trips end-to-end with itineraries, budgets, and participant tracking.

**Depends on:** Phase 8 (Relationships established)

**Requirements:** TRAVEL-01, TRAVEL-02, TRAVEL-03, TRAVEL-04, TRAVEL-05, TRAVEL-06, TRAVEL-07, TRAVEL-08, TRAVEL-09, TRAVEL-10, TRAVEL-11, TRAVEL-12, TRAVEL-13

**Success Criteria** (what must be TRUE):
1. User can create a trip with title, description, dates, and see it in the admin panel
2. User can define trip destinations with location data and view them in trip details
3. User can build detailed itineraries with activities, schedule, and timing
4. User can attach a budget to trips, categorize activities, and track remaining budget
5. User can add trip participants and track shared expenses by person
6. User can export trip itineraries as PDF documents
7. User receives notifications before trip start dates
8. System alerts user to itinerary conflicts or scheduling gaps

**Plans:** TBD

**UI hint:** yes

---

### Phase 10: Home Management & Quality
**Goal:** Users can manage properties, maintenance schedules, utilities, and valuations; all travel/home code is testable and validated.

**Depends on:** Phase 9

**Requirements:** HOME-01, HOME-02, HOME-03, HOME-04, HOME-05, HOME-06, HOME-07, HOME-08, HOME-09, HOME-10, HOME-11, HOME-12, HOME-13, HOME-14, HOME-15, HOME-16, HOME-17, HOME-18, HOME-19, HOME-20, QA-01, QA-02, QA-03, QA-04, QA-05, QA-06

**Success Criteria** (what must be TRUE):
1. User can register properties with address, type, and lease/purchase dates
2. User can define maintenance tasks with type, frequency, and status tracking
3. User can log maintenance records with date, cost, contractor, and attach documents
4. User can set up maintenance schedules with automated reminders
5. User can track utility accounts with billing cycles and log readings/bills with cost trends
6. User can create inventory of valuable items with location, value, depreciation report for insurance
7. User sees property dashboard with maintenance history, next due dates, and utility trends
8. Travel service layer is thoroughly tested (trip creation, budget calculations, participant expense tracking)
9. Home service layer is thoroughly tested (maintenance scheduling, depreciation, utility analytics)
10. All Filament CRUD workflows for Travel and Home pass feature tests with proper authorization
11. All Travel and Home database migrations validate schema integrity
12. All Travel and Home input validation works end-to-end for edge cases (date ranges, costs, etc.)

**Plans:** TBD

**UI hint:** yes

---

## Dependencies

```
Phase 9 (Travel)
  ↓ depends on v1.0 completion
  
Phase 10 (Home Management & Quality)
  ↓ depends on Phase 9 (related domain models established)
```

---

## Progress Tracking

| Phase | Goal | Requirements | Success Criteria | Status |
|-------|------|--------------|------------------|--------|
| 9 | Travel Domain | 13 | 8 | ✅ COMPLETE (71 tests) |
| 10 | Home Management & Quality | 26 | 12 | Ready for planning |

---

## Traceability

### Phase 9: Travel (13 requirements)
- **TRAVEL-01:** Trip creation with metadata ↔ Success Criteria #1
- **TRAVEL-02:** Destination location data ↔ Success Criteria #2
- **TRAVEL-03:** Itinerary with activities ↔ Success Criteria #3
- **TRAVEL-04:** Trip budget tracking ↔ Success Criteria #4
- **TRAVEL-05:** Activity categorization ↔ Success Criteria #4
- **TRAVEL-06:** Participant tracking ↔ Success Criteria #5
- **TRAVEL-07:** PDF export ↔ Success Criteria #6
- **TRAVEL-08:** Filament Trip resource ↔ Success Criteria #1
- **TRAVEL-09:** Filament Itinerary resource ↔ Success Criteria #3
- **TRAVEL-10:** Location picker/map ↔ Success Criteria #2
- **TRAVEL-11:** Trip dashboard ↔ Success Criteria #4
- **TRAVEL-12:** Trip start notifications ↔ Success Criteria #7
- **TRAVEL-13:** Itinerary conflict alerts ↔ Success Criteria #8

### Phase 10: Home Management & Quality (26 requirements)
**Home Domain (20 requirements):**
- **HOME-01:** Property registration ↔ Success Criteria #1
- **HOME-02:** Maintenance task definition ↔ Success Criteria #2
- **HOME-03:** Maintenance record logging ↔ Success Criteria #3
- **HOME-04:** Maintenance scheduling ↔ Success Criteria #4
- **HOME-05:** Document attachment ↔ Success Criteria #4
- **HOME-06:** Utility account tracking ↔ Success Criteria #5
- **HOME-07:** Utility readings & bills ↔ Success Criteria #5
- **HOME-08:** Inventory creation ↔ Success Criteria #6
- **HOME-09:** Inventory categorization ↔ Success Criteria #6
- **HOME-10:** Depreciation reporting ↔ Success Criteria #6
- **HOME-11:** Property dashboard ↔ Success Criteria #7
- **HOME-12:** Utility cost trends ↔ Success Criteria #7
- **HOME-13:** Maintenance cost forecasting ↔ Success Criteria #7
- **HOME-14:** Maintenance checklist ↔ Success Criteria #2
- **HOME-15:** Filament Property resource ↔ Success Criteria #1
- **HOME-16:** Filament Maintenance resource ↔ Success Criteria #3
- **HOME-17:** Filament Utilities resource ↔ Success Criteria #5
- **HOME-18:** Filament Inventory resource ↔ Success Criteria #6
- **HOME-19:** Maintenance reminders ↔ Success Criteria #4
- **HOME-20:** Utility consumption alerts ↔ Success Criteria #7

**Quality & Testing (6 requirements):**
- **QA-01:** Travel service unit tests ↔ Success Criteria #8
- **QA-02:** Home service unit tests ↔ Success Criteria #9
- **QA-03:** Travel Filament feature tests ↔ Success Criteria #10
- **QA-04:** Home Filament feature tests ↔ Success Criteria #10
- **QA-05:** Travel/Home migration tests ↔ Success Criteria #11
- **QA-06:** Travel/Home validation tests ↔ Success Criteria #12

---

## Architecture Notes

**Phase 9 Implementation Pattern (Travel):**
- **Models:** Trip, Destination, Itinerary, Activity, TripBudget, TripParticipant, TripExpense
- **Services:** TravelService, ItineraryService, TravelBudgetCalculator, NotificationService
- **Observers:** TripObserver (status changes, notifications), ItineraryObserver (conflict detection)
- **Policies:** TripPolicy, ItineraryPolicy (user-scoped via HasUserScoping trait)
- **Filament Resources:** TravelResource, DestinationResource, ItineraryResource with Forms, Tables, RelationManagers
- **Tests:** TravelServiceTest, ItineraryServiceTest, TravelFeatureTest (Filament workflows)

**Phase 10 Implementation Pattern (Home Management):**
- **Models:** Property, MaintenanceTask, MaintenanceRecord, Utility, UtilityBill, Inventory, InventoryCategory
- **Services:** PropertyService, MaintenanceService, DeprecationCalculator, UtilityAnalytics, NotificationService
- **Observers:** MaintenanceObserver (scheduling, reminders), PropertyObserver (status tracking)
- **Policies:** PropertyPolicy, MaintenancePolicy, UtilityPolicy, InventoryPolicy
- **Filament Resources:** PropertyResource, MaintenanceResource, UtilityResource, InventoryResource with full CRUD
- **Tests:** PropertyServiceTest, MaintenanceServiceTest, DeprecationTest, HomeFeatureTest, MigrationTest, ValidationTest

**Cross-Cutting Patterns:**
- Notification orchestration via NotificationService + Job queue
- User scoping enforced via HasUserScoping trait on all models
- Authorization checks via Policies integrated into Filament resources
- Date/time handling via Carbon with timezone awareness
- Cost calculations via service calculators (budget remaining, depreciation, utility trends)
- Soft deletes for non-destructive operations

---

## Quality Gates

✓ **100% Requirement Coverage:** 39/39 requirements mapped  
✓ **No Orphans:** Every requirement assigned to exactly one phase  
✓ **Observable Success Criteria:** 8 for Phase 9, 12 for Phase 10 — all testable from user perspective  
✓ **Dependency Clarity:** Phase 10 depends on Phase 9; both follow v1.0 foundation  
✓ **Granularity:** 2 phases balances Travel (distinct domain) + Home (larger, includes QA)

---

## Next Steps

1. **Phase 9 Planning** → `/gsd-plan-phase 9` to decompose travel requirements into executable plans
2. **Phase 10 Planning** → `/gsd-plan-phase 10` to decompose home + quality requirements
3. **Iteration:** User feedback → update ROADMAP.md via `/gsd-revise-roadmap`

---

**Created:** 2026-04-10  
**Milestone:** v2.0  
**Status:** Ready for Planning
