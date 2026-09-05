<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    private const MODELS = [
        'client' => Client::class,
        'product' => Product::class,
        'sale' => Sale::class,
        'sale_item' => SaleItem::class,
        'inventory_movement' => InventoryMovement::class,
        'setting' => Setting::class,
        'user' => User::class,
    ];

    public function index(Request $request): View
    {
        $logs = AuditLog::query()
            ->with(['user:id,name', 'auditable'])
            ->when(
                $request->string('model')?->toString(),
                fn (Builder $query, string $model) => $query->when(
                    isset(self::MODELS[$model]),
                    fn (Builder $inner) => $inner->where('auditable_type', self::MODELS[$model])
                )
            )
            ->when($request->string('action')?->toString(), fn (Builder $query, string $action) => $query->where('action', $action))
            ->when($request->string('user_id')?->toString(), fn (Builder $query, string $userId) => $query->where('user_id', $userId))
            ->when($request->string('from')?->toString(), fn (Builder $query, string $from) => $query->whereDate('created_at', '>=', $from))
            ->when($request->string('to')?->toString(), fn (Builder $query, string $to) => $query->whereDate('created_at', '<=', $to))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('audit.index', [
            'logs' => $logs,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'actions' => AuditLog::query()
                ->select('action')
                ->distinct()
                ->orderBy('action')
                ->pluck('action'),
            'models' => array_keys(self::MODELS),
            'filters' => [
                'model' => $request->string('model')?->toString(),
                'action' => $request->string('action')?->toString(),
                'user_id' => $request->string('user_id')?->toString(),
                'from' => $request->string('from')?->toString(),
                'to' => $request->string('to')?->toString(),
            ],
        ]);
    }

    public function show(AuditLog $log): View
    {
        $log->load(['user:id,name', 'auditable']);

        return view('audit.show', ['log' => $log]);
    }
}
