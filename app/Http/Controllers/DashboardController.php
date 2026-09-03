<?php

namespace App\Http\Controllers;

use App\Models\InventoryTransaction;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Services\AlertService;
use App\TransactionType;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(AlertService $alertService): View
    {
        $alertService->syncExpirations();
        $medicines = Medicine::query()->where('status', 'active')->withInventory()->get();
        $todayTransactions = InventoryTransaction::query()->whereDate('transacted_at', today());

        $statistics = [
            'total_medicines' => $medicines->count(),
            'total_stock' => $medicines->sum('current_stock'),
            'low_stock' => $medicines->filter(fn (Medicine $medicine) => in_array($medicine->stockStatus(), ['low', 'critical'], true))->count(),
            'out_of_stock' => $medicines->filter(fn (Medicine $medicine) => $medicine->stockStatus() === 'out')->count(),
            'expiring_soon' => MedicineBatch::query()->where('quantity', '>', 0)->whereBetween('expiration_date', [today(), today()->addDays(90)])->count(),
            'expired' => MedicineBatch::query()->where('quantity', '>', 0)->whereDate('expiration_date', '<', today())->count(),
            'today_transactions' => (clone $todayTransactions)->count(),
            'dispensed_today' => (clone $todayTransactions)->where('type', TransactionType::Dispensed)->sum('quantity'),
            'received_today' => (clone $todayTransactions)->where('type', TransactionType::StockIn)->sum('quantity'),
            'inventory_value' => MedicineBatch::query()->selectRaw('COALESCE(SUM(quantity * unit_cost), 0) as total')->value('total'),
        ];

        $inventoryStatus = collect(['normal', 'low', 'critical', 'out'])->mapWithKeys(
            fn (string $status) => [$status => $medicines->filter(fn (Medicine $medicine) => $medicine->stockStatus() === $status)->count()],
        );

        $dates = collect(range(13, 0))->map(fn (int $days) => today()->subDays($days));
        $movement = $this->movementSeries($dates);
        $expiration = [
            '30 days' => MedicineBatch::query()->where('quantity', '>', 0)->whereBetween('expiration_date', [today(), today()->addDays(30)])->count(),
            '60 days' => MedicineBatch::query()->where('quantity', '>', 0)->whereBetween('expiration_date', [today()->addDays(31), today()->addDays(60)])->count(),
            '90 days' => MedicineBatch::query()->where('quantity', '>', 0)->whereBetween('expiration_date', [today()->addDays(61), today()->addDays(90)])->count(),
            'Expired' => $statistics['expired'],
        ];

        return view('dashboard', [
            'statistics' => $statistics,
            'inventoryStatus' => $inventoryStatus,
            'movement' => $movement,
            'expiration' => $expiration,
            'recentTransactions' => InventoryTransaction::query()->with(['medicine', 'batch', 'user'])->latest('transacted_at')->limit(8)->get(),
            'lowStockMedicines' => $medicines->filter(fn (Medicine $medicine) => $medicine->stockStatus() !== 'normal')->sortBy('current_stock')->take(6),
            'expiringBatches' => MedicineBatch::query()->with('medicine')->where('quantity', '>', 0)->whereBetween('expiration_date', [today(), today()->addDays(90)])->orderBy('expiration_date')->limit(6)->get(),
        ]);
    }

    /** @param Collection<int, Carbon> $dates @return array<string, mixed> */
    private function movementSeries(Collection $dates): array
    {
        $transactions = InventoryTransaction::query()
            ->whereDate('transacted_at', '>=', $dates->first())
            ->get()
            ->groupBy(fn (InventoryTransaction $transaction) => $transaction->transacted_at->toDateString());

        return [
            'labels' => $dates->map->format('M d')->values(),
            'received' => $dates->map(fn ($date) => $transactions->get($date->toDateString(), collect())->where('type', TransactionType::StockIn)->sum('quantity'))->values(),
            'dispensed' => $dates->map(fn ($date) => $transactions->get($date->toDateString(), collect())->whereIn('type', [TransactionType::StockOut, TransactionType::Dispensed])->sum('quantity'))->values(),
            'adjusted' => $dates->map(fn ($date) => $transactions->get($date->toDateString(), collect())->where('type', TransactionType::Adjustment)->sum('quantity'))->values(),
        ];
    }
}
