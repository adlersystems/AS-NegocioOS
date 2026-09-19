<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['client_id', 'client_name', 'client_nit', 'seller_id', 'subtotal', 'tax_amount', 'total', 'paid', 'notes'])]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory, RecordsActivity;

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'paid' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function isPaid(): bool
    {
        return (bool) $this->paid;
    }

    /**
     * The client name recorded at the moment of the sale.
     *
     * Falls back to the live relation only when the sale carries no snapshot,
     * which keeps legacy rows readable without rewriting their past.
     */
    public function buyerName(): ?string
    {
        return $this->client_name ?? $this->client?->name;
    }

    /**
     * The client tax id recorded at the moment of the sale.
     */
    public function buyerNit(): ?string
    {
        return $this->client_nit ?? $this->client?->nit;
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('paid', true);
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where('paid', false);
    }

    /**
     * Restrict the query to the sales a user is allowed to see.
     *
     * Only vendedores are scoped to their own sales; admins and encargados
     * keep global visibility (F-INTEG-02, Rule B).
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        return $query->when($user?->isSeller(), fn (Builder $q) => $q->where('seller_id', $user->id));
    }

    public function scopeBetweenDates(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn (Builder $q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('created_at', '<=', $to));
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function (Builder $q) use ($search) {
            $q->where(function (Builder $inner) use ($search) {
                $digits = preg_replace('/\D/', '', $search);

                $inner->when($digits !== '', fn (Builder $s) => $s->where('id', (int) $digits))
                    ->orWhereHas('client', function (Builder $c) use ($search) {
                        $c->where('name', 'like', "%{$search}%")
                            ->orWhere('nit', 'like', "%{$search}%");
                    })
                    ->orWhere('client_name', 'like', "%{$search}%")
                    ->orWhere('client_nit', 'like', "%{$search}%");
            });
        });
    }

    public function invoiceNumber(): string
    {
        return 'V-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}
