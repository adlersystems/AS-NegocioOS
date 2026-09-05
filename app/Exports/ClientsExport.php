<?php

namespace App\Exports;

use App\Models\Client;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ClientsExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly ?string $search = null) {}

    /**
     * @return Collection<int, Client>
     */
    public function collection(): Collection
    {
        return Client::query()
            ->withCount('sales')
            ->withSum('sales', 'total')
            ->withSum(['sales as unpaid_total' => fn ($query) => $query->unpaid()], 'total')
            ->search($this->search)
            ->latest()
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            __('app.labels.name'),
            __('app.labels.nit'),
            __('app.labels.email'),
            __('app.labels.phone'),
            __('app.labels.address'),
            __('app.clients.sales_count'),
            __('app.clients.total_purchases'),
            __('app.clients.pending_balance'),
        ];
    }

    /**
     * @param  Client  $client
     * @return array<int, mixed>
     */
    public function map($client): array
    {
        return [
            $client->name,
            $client->nit,
            $client->email,
            $client->phone,
            $client->address,
            $client->sales_count,
            (float) $client->sales_sum_total,
            (float) $client->pending_balance,
        ];
    }
}
