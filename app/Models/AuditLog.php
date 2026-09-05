<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['user_id', 'action', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'ip_address', 'user_agent'])]
class AuditLog extends Model
{
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isUpdate(): bool
    {
        return $this->action === 'updated';
    }

    public function isDelete(): bool
    {
        return $this->action === 'deleted';
    }

    /**
     * A short, human-friendly label for the related record.
     */
    public function contextLabel(): string
    {
        $model = $this->auditable;

        return match (true) {
            $model instanceof Client => $model->name,
            $model instanceof Product => $model->name.($model->sku ? " ({$model->sku})" : ''),
            $model instanceof Sale => $model->invoiceNumber(),
            $model instanceof InventoryMovement => $model->product?->name ?? '#'.$model->product_id,
            $model instanceof Setting => (string) $model->key,
            $model instanceof User => $model->name,
            $model instanceof Model => '#'.$model->getKey(),
            default => $this->snapshotLabel(),
        };
    }

    /**
     * Fallback label from the recorded values, usable after the record
     * itself has been deleted.
     */
    private function snapshotLabel(): string
    {
        $values = array_replace($this->old_values ?? [], $this->new_values ?? []);

        foreach (['name', 'sku', 'email', 'invoice_number', 'key'] as $key) {
            if (array_key_exists($key, $values) && $values[$key] !== null) {
                return (string) $values[$key];
            }
        }

        return '#'.$this->auditable_id;
    }

    /**
     * The short model class name (e.g. "Client", "SaleItem") for display.
     */
    public function modelShortName(): string
    {
        return class_basename($this->auditable_type ?? '');
    }
}
