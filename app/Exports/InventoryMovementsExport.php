<?php

namespace App\Exports;

use App\Models\InventoryMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class InventoryMovementsExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private readonly ?string $search = null,
        private readonly ?string $type = null,
        private readonly ?string $from = null,
        private readonly ?string $to = null,
    ) {}

    /**
     * @return Collection<int, InventoryMovement>
     */
    public function collection(): Collection
    {
        return InventoryMovement::query()
            ->with(['product:id,name,sku', 'user:id,name'])
            ->when($this->search, fn (Builder $query) => $query->whereHas(
                'product',
                fn (Builder $product) => $product->search($this->search)
            ))
            ->when($this->type === InventoryMovement::TYPE_IN, fn (Builder $query) => $query->where('type', InventoryMovement::TYPE_IN))
            ->when($this->type === InventoryMovement::TYPE_OUT, fn (Builder $query) => $query->where('type', InventoryMovement::TYPE_OUT))
            ->when($this->from, fn (Builder $query) => $query->whereDate('created_at', '>=', $this->from))
            ->when($this->to, fn (Builder $query) => $query->whereDate('created_at', '<=', $this->to))
            ->latest('id')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            __('app.labels.date'),
            __('app.labels.name'),
            __('app.labels.sku'),
            __('app.labels.type'),
            __('app.labels.quantity'),
            __('app.labels.reason'),
            __('app.labels.reference'),
            __('app.dashboard.seller'),
        ];
    }

    /**
     * @param  InventoryMovement  $movement
     * @return array<int, mixed>
     */
    public function map($movement): array
    {
        return [
            $movement->created_at->format('d/m/Y H:i'),
            $movement->product?->name ?? __('app.labels.none'),
            $movement->product?->sku,
            $movement->isIn() ? __('app.inventory.type_in') : __('app.inventory.type_out'),
            ($movement->isIn() ? '+' : '-').$movement->quantity,
            $movement->reason,
            $movement->reference,
            $movement->user?->name ?? __('app.labels.none'),
        ];
    }
}
