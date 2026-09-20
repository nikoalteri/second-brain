<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditTrail
{
    public const REDACTED = '[redacted]';

    private const ALWAYS_IGNORED = ['id', 'created_at', 'updated_at', 'deleted_at', 'user_id'];

    public function recordCreated(Model $model): void
    {
        $this->write($model, 'create', $this->snapshot($model));
    }

    public function recordUpdated(Model $model): void
    {
        $changes = [];

        foreach ($model->getChanges() as $column => $new) {
            if ($this->isIgnored($model, $column)) {
                continue;
            }

            $changes[$column] = $this->isRedacted($model, $column)
                ? self::REDACTED
                : ['old' => $model->getRawOriginal($column), 'new' => $new];
        }

        if ($changes !== []) {
            $this->write($model, 'update', $changes);
        }
    }

    public function recordDeleted(Model $model): void
    {
        $this->write($model, 'delete', $this->snapshot($model));
    }

    public function recordRestored(Model $model): void
    {
        $this->write($model, 'update', ['restored' => true]);
    }

    /** @return array<string, mixed> */
    private function snapshot(Model $model): array
    {
        $snapshot = [];

        foreach ($model->getAttributes() as $column => $value) {
            if ($this->isIgnored($model, $column) || $value === null) {
                continue;
            }

            $snapshot[$column] = $this->isRedacted($model, $column) ? self::REDACTED : $value;
        }

        return $snapshot;
    }

    /** @param array<string, mixed> $changes */
    private function write(Model $model, string $action, array $changes): void
    {
        $ownerId = $model->getAttribute('user_id');

        if (! $ownerId) {
            return;
        }

        $actorId = auth()->id();

        AuditLog::query()->withoutGlobalScopes()->create([
            'user_id' => $ownerId,
            'actor_id' => $actorId,
            'action' => $action,
            'model_name' => class_basename($model),
            'model_id' => $model->getKey(),
            'changes' => $changes,
            'ip_address' => $actorId ? request()->ip() : null,
        ]);
    }

    private function isIgnored(Model $model, string $column): bool
    {
        return in_array($column, self::ALWAYS_IGNORED, true)
            || in_array($column, $model->auditIgnoredColumns(), true);
    }

    private function isRedacted(Model $model, string $column): bool
    {
        return in_array($column, $model->auditRedactedColumns(), true);
    }
}
