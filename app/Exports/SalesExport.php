<?php

namespace App\Exports;

use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SalesExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private readonly ?string $search = null,
        private readonly ?string $sellerId = null,
        private readonly ?string $from = null,
        private readonly ?string $to = null,
    ) {}

    /**
     * @return Collection<int, Sale>
     */
    public function collection(): Collection
    {
        return Sale::query()
            ->with(['client:id,name,nit', 'seller:id,name'])
            ->search($this->search)
            ->when($this->sellerId, fn (Builder $query) => $query->where('seller_id', $this->sellerId))
            ->betweenDates($this->from, $this->to)
            ->latest('id')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            __('app.labels.invoice_number'),
            __('app.labels.date'),
            __('app.labels.name'),
            __('app.labels.nit'),
            __('app.labels.seller'),
            __('app.labels.subtotal'),
            __('app.labels.tax'),
            __('app.labels.total'),
            __('app.labels.status'),
            __('app.labels.notes'),
        ];
    }

    /**
     * @param  Sale  $sale
     * @return array<int, mixed>
     */
    public function map($sale): array
    {
        return [
            $sale->invoiceNumber(),
            $sale->created_at->format('d/m/Y H:i'),
            $sale->buyerName() ?? __('app.sales.no_invoice_client'),
            $sale->buyerNit(),
            $sale->seller?->name,
            (float) $sale->subtotal,
            (float) $sale->tax_amount,
            (float) $sale->total,
            $sale->isPaid() ? __('app.sales.status_paid') : __('app.sales.status_unpaid'),
            $sale->notes,
        ];
    }
}
