<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait RecordsActivity
{
    /** Attributes never persisted to the audit trail. */
    protected $auditExclude = ['created_at', 'updated_at'];

    /** @var array{old: array<string, mixed>, new: array<string, mixed>}|null */
    public $auditSnapshot;

    public static function bootRecordsActivity(): void
    {
        static::created(fn (Model $model) => $model->writeAuditLog('created'));
        static::updating(function (Model $model) {
            $dirty = $model->getDirty();

            if ($dirty === []) {
                return;
            }

            $model->auditSnapshot = [
                'old' => array_intersect_key($model->getOriginal(), $dirty),
                'new' => array_intersect_key($model->getAttributes(), $dirty),
            ];
        });
        static::updated(fn (Model $model) => $model->flushAuditLogSnapshot('updated'));
        static::deleted(fn (Model $model) => $model->writeAuditLog('deleted'));
    }

    public function audits(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    protected function flushAuditLogSnapshot(string $action): void
    {
        if (isset($this->auditSnapshot) && is_array($this->auditSnapshot)) {
            $this->writeAuditLog($action, $this->auditSnapshot);
            $this->auditSnapshot = null;
        }
    }

    protected function writeAuditLog(string $action, ?array $snapshot = null): void
    {
        if (! ($user = auth()->user())) {
            return;
        }

        if ($snapshot !== null) {
            ['old' => $old, 'new' => $new] = $snapshot;
        } elseif ($action === 'deleted') {
            $old = $this->getAttributes();
            $new = [];
        } else {
            $old = $action === 'created' ? [] : $this->getOriginal();
            $new = $this->getAttributes();
        }

        AuditLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'auditable_type' => $this->getMorphClass(),
            'auditable_id' => $this->getKey(),
            'old_values' => $this->trimAuditPayload($old),
            'new_values' => $this->trimAuditPayload($new),
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
        ]);
    }

    protected function trimAuditPayload(array $attributes): array
    {
        foreach ($this->auditExclude as $excluded) {
            unset($attributes[$excluded]);
        }

        return $attributes;
    }
}
