<?php

namespace App\Traits;

use App\Services\AuditTrail;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes an audit_logs row for every create, update and delete of the model.
 *
 * Models can declare:
 *  - $auditIgnored:  columns maintained by the system (running balances, counters). They are
 *                    never recorded, and a save that only touches them writes no row.
 *  - $auditRedacted: sensitive columns. A change is recorded as "[redacted]", never the value.
 *
 * Only Eloquent events are seen: saveQuietly(), updateQuietly() and query-builder writes are not
 * audited, which is what keeps system-maintained columns out of the trail.
 */
trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(fn (Model $model) => app(AuditTrail::class)->recordCreated($model));
        static::updated(fn (Model $model) => app(AuditTrail::class)->recordUpdated($model));
        static::deleted(fn (Model $model) => app(AuditTrail::class)->recordDeleted($model));

        if (method_exists(static::class, 'restored')) {
            static::restored(fn (Model $model) => app(AuditTrail::class)->recordRestored($model));
        }
    }

    /** @return list<string> */
    public function auditIgnoredColumns(): array
    {
        return property_exists($this, 'auditIgnored') ? $this->auditIgnored : [];
    }

    /** @return list<string> */
    public function auditRedactedColumns(): array
    {
        return property_exists($this, 'auditRedacted') ? $this->auditRedacted : [];
    }
}
