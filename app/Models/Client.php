<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'email', 'phone', 'address', 'nit', 'pending_balance', 'preferred_language', 'notes'])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory, RecordsActivity;

    protected function casts(): array
    {
        return [
            'pending_balance' => 'decimal:2',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * The amount this client owes, computed as the sum of their unpaid sales.
     * Replaces the stored static value so receivables reflect real invoices.
     */
    public function getPendingBalanceAttribute(): float
    {
        return (float) ($this->unpaid_total ?? $this->sales()->unpaid()->sum('total'));
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->where('name', 'like', "%{$term}%")
            ->orWhere('email', 'like', "%{$term}%")
            ->orWhere('nit', 'like', "%{$term}%"));
    }
}
