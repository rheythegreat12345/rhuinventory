<?php

namespace App\Http\Controllers;

use App\Models\InventoryTransaction;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\TransactionType;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function __invoke(): View
    {
        $transactions = InventoryTransaction::query()->with('medicine')->where('transacted_at', '>=', now()->subMonths(12)->startOfMonth())->get();
        $months = collect(range(11, 0))->map(fn (int $month) => now()->subMonths($month)->startOfMonth());
        $monthly = $transactions->groupBy(fn (InventoryTransaction $transaction) => $transaction->transacted_at->format('Y-m'));

        $monthlySeries = [
            'labels' => $months->map->format('M Y'),
            'received' => $months->map(fn ($month) => $monthly->get($month->format('Y-m'), collect())->where('type', TransactionType::StockIn)->sum('quantity')),
            'released' => $months->map(fn ($month) => $monthly->get($month->format('Y-m'), collect())->whereIn('type', [TransactionType::StockOut, TransactionType::Dispensed])->sum('quantity')),
            'volume' => $months->map(fn ($month) => $monthly->get($month->format('Y-m'), collect())->count()),
        ];

        $dispensed = $transactions->whereIn('type', [TransactionType::StockOut, TransactionType::Dispensed]);
        $received = $transactions->where('type', TransactionType::StockIn);
        $trendData = Medicine::query()->withInventory()->get()->map(function (Medicine $medicine) use ($dispensed): array {
            $current = $dispensed->where('medicine_id', $medicine->id)->where('transacted_at', '>=', now()->subDays(30))->sum('quantity');
            $previous = $dispensed->where('medicine_id', $medicine->id)->whereBetween('transacted_at', [now()->subDays(60), now()->subDays(31)])->sum('quantity');
            $change = $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : ($current > 0 ? 100 : 0);

            return ['medicine' => $medicine, 'current' => $current, 'previous' => $previous, 'change' => $change];
        });

        return view('analytics.index', [
            'monthlySeries' => $monthlySeries,
            'mostDispensed' => $this->rankMedicines($dispensed),
            'mostReceived' => $this->rankMedicines($received),
            'increasingDemand' => $trendData->where('change', '>', 0)->sortByDesc('change')->take(5),
            'decreasingDemand' => $trendData->where('change', '<', 0)->sortBy('change')->take(5),
            'inventoryValue' => MedicineBatch::query()->selectRaw('COALESCE(SUM(quantity * unit_cost), 0) as total')->value('total'),
            'stockTurnover' => MedicineBatch::query()->sum('quantity') > 0 ? round($dispensed->sum('quantity') / MedicineBatch::query()->sum('quantity'), 2) : 0,
            'expirationTrend' => MedicineBatch::query()->with('medicine')->where('quantity', '>', 0)->orderBy('expiration_date')->limit(10)->get(),
            'totalVolume' => $transactions->count(),
        ]);
    }

    /** @return Collection<int, array{medicine: Medicine, quantity: int}> */
    private function rankMedicines(Collection $transactions): Collection
    {
        return $transactions->groupBy('medicine_id')
            ->map(fn (Collection $items) => ['medicine' => $items->first()->medicine, 'quantity' => $items->sum('quantity')])
            ->sortByDesc('quantity')
            ->take(7)
            ->values();
    }
}
