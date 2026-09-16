<?php

namespace App\Http\Controllers;

use App\Exports\ClientsExport;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $clients = Client::query()
            ->withCount('sales')
            ->withSum('sales', 'total')
            ->withSum(['sales as unpaid_total' => fn (Builder $query) => $query->unpaid()], 'total')
            ->search($request->string('search')?->toString())
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('clients.index', ['clients' => $clients]);
    }

    public function create(): View
    {
        return view('clients.form', ['client' => new Client]);
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        $client = Client::create($request->validated());

        return redirect()
            ->route('clients.show', $client)
            ->with('success', __('app.flash.created', ['entity' => __('app.clients.singular')]));
    }

    public function show(Client $client): View
    {
        $client->loadSum(['sales as unpaid_total' => fn (Builder $query) => $query->unpaid()], 'total');

        $sales = $client->sales()
            ->with('seller:id,name')
            ->latest()
            ->get();

        return view('clients.show', [
            'client' => $client,
            'sales' => $sales,
        ]);
    }

    public function edit(Client $client): View
    {
        return view('clients.form', ['client' => $client]);
    }

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        abort_unless(auth()->user()?->canManageClients(), 403);

        $client->update($request->validated());

        return redirect()
            ->route('clients.show', $client)
            ->with('success', __('app.flash.updated', ['entity' => __('app.clients.singular')]));
    }

    public function destroy(Client $client): RedirectResponse
    {
        abort_unless(auth()->user()?->canManageClients(), 403);

        if ($client->sales()->exists()) {
            return redirect()
                ->route('clients.index')
                ->withErrors(['delete' => __('app.clients.delete_blocked_sales')]);
        }

        $client->delete();

        return redirect()
            ->route('clients.index')
            ->with('success', __('app.flash.deleted', ['entity' => __('app.clients.singular')]));
    }

    public function exportPdf(Request $request): Response
    {
        abort_unless(auth()->user()?->canManageClients(), 403);

        $clients = Client::query()
            ->withCount('sales')
            ->withSum('sales', 'total')
            ->withSum(['sales as unpaid_total' => fn (Builder $query) => $query->unpaid()], 'total')
            ->search($request->string('search')?->toString())
            ->latest('id')
            ->get();

        $pdf = Pdf::loadView('clients.export-pdf', [
            'clients' => $clients,
            'company' => Setting::get('company_name', 'AS-NegocioOS'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('clientes-'.now()->format('Y-m-d').'.pdf');
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        abort_unless(auth()->user()?->canManageClients(), 403);

        $search = $request->string('search')?->toString();

        return Excel::download(
            new ClientsExport($search),
            'clientes-'.now()->format('Y-m-d').'.xlsx'
        );
    }
}
