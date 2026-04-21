# Second Brain: API & Health Integration Pitfalls — Quick Reference

**Created:** 2026-04-11  
**For:** Phase 11 (Mobile API Layer) & Phase 12 (Health Integration)  
**Full Document:** `PITFALLS.md`

---

## Critical Pitfalls to Avoid (🔴 Phase 11)

### API Security (Phase 11)

1. **N+1 Queries** — Load relationships eagerly; test query count ≤ 5
2. **Data Scoping** — Use `HasUserScoping` + `auth('sanctum')` guards
3. **Token Security** — 30-min access tokens, refresh rotation, logout revocation
4. **CORS** — Whitelist origins only; no `*` with credentials
5. **Auth Conflicts** — Explicit `auth:sanctum` middleware on all API routes

### Integration Pitfalls (Phase 11)

6. **Observer Side Effects** — Skip heavy work for API; check `request()->expectsJson()`
7. **Long Transactions** — Bulk ops return 202 + job ID; async not sync
8. **Soft Deletes** — API soft-deletes; 404 when accessed; 30-day restore grace period

---

## Critical Pitfalls to Avoid (🔴 Phase 12)

### Health Data Privacy (Phase 12)

1. **Encryption** — `protected $casts = ['vital_value' => 'encrypted']`
2. **Audit Trail** — Log every health access: user, timestamp, IP
3. **Data Export/Deletion** — Commands: `health:export-user-data`, `health:delete-user-data`

### Health Validation (Phase 12)

4. **Vital Ranges** — Validation: `systolic: between:60,200`, `weight: between:30,300`
5. **Medication Interactions** — Lookup table; critical interactions block save (422)
6. **Appointments** — UTC storage; timezone-aware display; conflict detection
7. **External APIs** — Encrypt tokens in `.env`; proactive refresh; revocation detection
8. **Prescriptions** — Immutable fields; refill limits enforced; controlled substance flagging

---

## Quick QA Checklist

### Phase 11 Pre-Gate Approval

- [ ] All API endpoints pass query count tests (≤ 5 per request)
- [ ] 100% of endpoints return 403 for unauthorized users
- [ ] Rate limits: read 100/min, write 20/min, search 5/min
- [ ] CORS whitelist configured; test unknown.com → 403
- [ ] Token refresh rotates; logout revokes all tokens
- [ ] Bulk ops return 202 with job ID; < 500ms response time
- [ ] Soft deletes only; hard delete admin-only

### Phase 12 Pre-Gate Approval

- [ ] Health data columns encrypted at rest
- [ ] Audit log created for all health access
- [ ] Vital signs validation in request rules
- [ ] Medication interaction lookup table exists
- [ ] Appointments stored in UTC; timezone handled separately
- [ ] External API tokens stored in `.env`, never hardcoded
- [ ] Prescription fields immutable; refill limits enforced

---

## Prevention Patterns (Copy-Paste Ready)

### Guard-Aware Global Scope (Phase 11)

```php
protected static function booted(): void {
    static::addGlobalScope(function (Builder $query) {
        $userId = auth('sanctum')->id() ?? auth('web')->id();
        if ($userId) {
            $query->where('user_id', $userId);
        }
    });
}
```

### Observer Skip for API (Phase 11)

```php
public function created(Trip $trip): void {
    if (request()->expectsJson()) {
        return;  // Skip heavy side effects
    }
    // Filament-only work here
}
```

### Async Bulk Import (Phase 11)

```php
Route::post('/api/v1/trips/bulk-import', function (Request $request) {
    $job = BulkImportTripsJob::dispatch(auth()->user(), $file);
    return response()->json(['job_id' => $job->id], 202);
});
```

### Encrypted Health Record (Phase 12)

```php
class HealthRecord extends Model {
    protected $casts = [
        'vital_value' => 'encrypted',  // Auto-encrypt/decrypt
        'recorded_at' => 'datetime',
    ];
}
```

### Medication Interaction Check (Phase 12)

```php
Route::post('/api/v1/medications', function (Request $request) {
    $result = medicationService()->checkInteractions(
        auth()->user(),
        $request->medication_id
    );
    
    if ($result->isCritical) {
        return response()->json(['error' => 'Dangerous interaction'], 422);
    }
    
    return Medication::create($request->all());
});
```

---

## Phase Assignments

| Pitfall | Phase | Severity | When to Implement |
|---------|-------|----------|-------------------|
| N+1 Queries | 11 | 🔴 | API design phase |
| Data Scoping | 11 | 🔴 | Before routes defined |
| Token Security | 11 | 🔴 | Auth implementation |
| CORS | 11 | 🔴 | Route registration |
| Rate Limiting | 11 | 🟠 | Middleware setup |
| Observer Skipping | 11 | 🟠 | Before API launch |
| Transactions | 11 | 🟠 | Job implementation |
| Soft Deletes | 11 | 🟠 | Deletion feature |
| Health Privacy | 12 | 🔴 | Before health model |
| Vital Validation | 12 | 🔴 | Request validation |
| Appointments | 12 | 🟠 | Scheduling feature |
| External APIs | 12 | 🔴 | Integration setup |
| Prescriptions | 12 | 🔴 | Before prescription model |

---

## Key Metrics to Track

- **API Response Time:** 99th percentile < 500ms (bulk ops < 2s)
- **Query Efficiency:** Median queries per request ≤ 3
- **Auth Failures:** 403 rate on unauthorized access = 100%
- **Rate Limit Accuracy:** No legitimate requests rejected when under limit
- **Health Data:** 100% encrypted at rest, 100% audited on access
- **Validation Coverage:** 100% of invalid inputs rejected with 422

---

## External References

- **HIPAA:** 45 CFR 164 § Safeguards & Privacy Rule
- **GDPR:** Right to Data Portability (Article 20) & Right to Be Forgotten (Article 17)
- **OWASP:** Top 10 API Security Risks
- **Laravel Sanctum:** Token-based authentication for APIs
- **Lighthouse GraphQL:** Schema-driven API for optional future integration

---

**Next Steps:**
1. Review full `PITFALLS.md` document
2. Create Phase 11 & 12 detailed planning documents
3. Allocate pitfall prevention to specific tasks
4. Define quality gate tests before development starts

