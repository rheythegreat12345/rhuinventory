<?php

namespace App\Http\Controllers;

use App\Models\InventoryTransaction;
use App\Models\Medicine;
use App\Models\User;
use App\TransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(Request $request): View
    {
        $transactions = InventoryTransaction::query()->with(['medicine', 'batch', 'user'])
            ->when($request->string('search')->toString(), fn (Builder $query, string $search) => $query->where(function (Builder $nested) use ($search): void {
                $nested->where('transaction_code', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%")
                    ->orWhere('recipient', 'like', "%{$search}%")
                    ->orWhereHas('medicine', fn (Builder $medicineQuery) => $medicineQuery->where('generic_name', 'like', "%{$search}%"))
                    ->orWhereHas('batch', fn (Builder $batchQuery) => $batchQuery->where('batch_number', 'like', "%{$search}%"));
            }))
            ->when($request->string('type')->toString(), fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($request->integer('medicine'), fn (Builder $query, int $medicineId) => $query->where('medicine_id', $medicineId))
            ->when($request->integer('user'), fn (Builder $query, int $userId) => $query->where('user_id', $userId))
            ->when($request->date('date_from'), fn (Builder $query, $date) => $query->whereDate('transacted_at', '>=', $date))
            ->when($request->date('date_to'), fn (Builder $query, $date) => $query->whereDate('transacted_at', '<=', $date))
            ->latest('transacted_at')
            ->paginate(20)
            ->withQueryString();

        return view('transactions.index', [
            'transactions' => $transactions,
            'medicines' => Medicine::query()->orderBy('generic_name')->get(),
            'users' => User::query()->orderBy('name')->get(),
            'types' => TransactionType::cases(),
        ]);
    }
}
