<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\InventoryTransaction;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineCategory;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AuditService;
use App\TransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\StreamedResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ReportController extends Controller
{
    public const TYPES = [
        'current_inventory' => 'Current Inventory Report',
        'low_stock' => 'Low Stock Report',
        'out_of_stock' => 'Out-of-Stock Report',
        'expiring' => 'Expiring Medicines Report',
        'expired' => 'Expired Medicines Report',
        'stock_in' => 'Stock-In Report',
        'stock_out' => 'Stock-Out Report',
        'dispensing' => 'Dispensing Report',
        'adjustments' => 'Inventory Adjustment Report',
        'supplier' => 'Supplier Report',
        'transactions' => 'Transaction History',
        'monthly' => 'Monthly Inventory Report',
    ];

    public function index(Request $request): View
    {
        $report = $this->build($request);

        return view('reports.index', [
            ...$report,
            'types' => self::TYPES,
            'medicines' => Medicine::query()->orderBy('generic_name')->get(),
            'categories' => MedicineCategory::query()->orderBy('name')->get(),
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'users' => User::query()->orderBy('name')->get(),
            'transactionTypes' => TransactionType::cases(),
        ]);
    }

    public function export(Request $request, string $format, AuditService $auditService): StreamedResponse|Response|View
    {
        abort_unless(in_array($format, ['csv', 'excel', 'print'], true), 404);
        $report = $this->build($request);
        $auditService->record('report_generated', "Generated {$report['title']} as {$format}.");

        if ($format === 'print') {
            return view('reports.print', $report);
        }

        $filename = str($report['title'])->slug().'-'.today()->format('Y-m-d');
        if ($format === 'excel') {
            $html = view('reports.excel', $report)->render();

            return response($html, 200, [
                'Content-Type' => 'application/vnd.ms-excel',
                'Content-Disposition' => "attachment; filename=\"{$filename}.xls\"",
            ]);
        }

        return response()->streamDownload(function () use ($report): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, $report['headings']);
            foreach ($report['rows'] as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
        }, "{$filename}.csv", ['Content-Type' => 'text/csv']);
    }

    public function auditLogs(Request $request): View
    {
        $logs = AuditLog::query()->with('user')
            ->when($request->string('search')->toString(), fn (Builder $query, string $search) => $query->where('description', 'like', "%{$search}%")->orWhere('action', 'like', "%{$search}%"))
            ->when($request->integer('user'), fn (Builder $query, int $userId) => $query->where('user_id', $userId))
            ->when($request->date('date_from'), fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($request->date('date_to'), fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('audit.index', ['logs' => $logs, 'users' => User::query()->orderBy('name')->get()]);
    }

    /** @return array{title: string, headings: array<int, string>, rows: Collection<int, array<int, mixed>>, summary: string} */
    private function build(Request $request): array
    {
        $type = array_key_exists($request->string('report')->toString(), self::TYPES) ? $request->string('report')->toString() : 'current_inventory';

        return match ($type) {
            'current_inventory', 'low_stock', 'out_of_stock' => $this->inventoryReport($request, $type),
            'expiring', 'expired' => $this->expirationReport($request, $type),
            'supplier' => $this->supplierReport(),
            default => $this->transactionReport($request, $type),
        };
    }

    /** @return array{title: string, headings: array<int, string>, rows: Collection<int, array<int, mixed>>, summary: string} */
    private function inventoryReport(Request $request, string $type): array
    {
        $medicines = Medicine::query()->with('category')->withInventory()
            ->when($request->integer('medicine'), fn (Builder $query, int $id) => $query->whereKey($id))
            ->when($request->integer('category'), fn (Builder $query, int $id) => $query->where('medicine_category_id', $id))
            ->orderBy('generic_name')->get();

        if ($type === 'low_stock') {
            $medicines = $medicines->filter(fn (Medicine $medicine) => in_array($medicine->stockStatus(), ['low', 'critical'], true));
        } elseif ($type === 'out_of_stock') {
            $medicines = $medicines->filter(fn (Medicine $medicine) => $medicine->stockStatus() === 'out');
        }

        return [
            'title' => self::TYPES[$type],
            'headings' => ['Medicine ID', 'Medicine', 'Category', 'Stock', 'Unit', 'Minimum', 'Reorder Qty', 'Status', 'Inventory Value'],
            'rows' => $medicines->map(fn (Medicine $medicine) => [
                $medicine->medicine_code, $medicine->generic_name, $medicine->category->name,
                $medicine->current_stock ?? 0, $medicine->unit, $medicine->minimum_stock_level,
                $medicine->recommendedReorderQuantity(), str($medicine->stockStatus())->headline(),
                number_format(($medicine->current_stock ?? 0) * (float) $medicine->unit_cost, 2),
            ])->values(),
            'summary' => $medicines->count().' medicine records · '.number_format($medicines->sum('current_stock')).' total units',
        ];
    }

    /** @return array{title: string, headings: array<int, string>, rows: Collection<int, array<int, mixed>>, summary: string} */
    private function expirationReport(Request $request, string $type): array
    {
        $batches = MedicineBatch::query()->with(['medicine.category', 'supplier'])->where('quantity', '>', 0)
            ->when($type === 'expired', fn (Builder $query) => $query->whereDate('expiration_date', '<', today()), fn (Builder $query) => $query->whereBetween('expiration_date', [today(), today()->addDays(90)]))
            ->when($request->integer('medicine'), fn (Builder $query, int $id) => $query->where('medicine_id', $id))
            ->when($request->integer('supplier'), fn (Builder $query, int $id) => $query->where('supplier_id', $id))
            ->orderBy('expiration_date')->get();

        return [
            'title' => self::TYPES[$type],
            'headings' => ['Medicine', 'Batch', 'Supplier', 'Quantity', 'Expiration Date', 'Expiration Status'],
            'rows' => $batches->map(fn (MedicineBatch $batch) => [$batch->medicine->generic_name, $batch->batch_number, $batch->supplier?->name ?? '—', $batch->quantity, $batch->expiration_date->format('Y-m-d'), str($batch->expirationStatus())->replace('_', ' ')->headline()])->values(),
            'summary' => $batches->count().' batches · '.number_format($batches->sum('quantity')).' units affected',
        ];
    }

    /** @return array{title: string, headings: array<int, string>, rows: Collection<int, array<int, mixed>>, summary: string} */
    private function supplierReport(): array
    {
        $suppliers = Supplier::query()->withCount('batches')->withSum('batches as supplied_stock', 'quantity')->orderBy('name')->get();

        return [
            'title' => self::TYPES['supplier'],
            'headings' => ['Supplier ID', 'Supplier', 'Contact', 'Phone', 'Email', 'Batches', 'Current Supplied Stock', 'Status'],
            'rows' => $suppliers->map(fn (Supplier $supplier) => [$supplier->code, $supplier->name, $supplier->contact_person, $supplier->phone, $supplier->email, $supplier->batches_count, $supplier->supplied_stock ?? 0, $supplier->is_active ? 'Active' : 'Inactive'])->values(),
            'summary' => $suppliers->count().' suppliers',
        ];
    }

    /** @return array{title: string, headings: array<int, string>, rows: Collection<int, array<int, mixed>>, summary: string} */
    private function transactionReport(Request $request, string $type): array
    {
        $typeMap = [
            'stock_in' => [TransactionType::StockIn->value],
            'stock_out' => [TransactionType::StockOut->value, TransactionType::Dispensed->value],
            'dispensing' => [TransactionType::Dispensed->value],
            'adjustments' => [TransactionType::Adjustment->value],
        ];
        $transactions = InventoryTransaction::query()->with(['medicine', 'batch', 'user'])
            ->when($typeMap[$type] ?? null, fn (Builder $query, array $types) => $query->whereIn('type', $types))
            ->when($request->string('transaction_type')->toString(), fn (Builder $query, string $transactionType) => $query->where('type', $transactionType))
            ->when($request->integer('medicine'), fn (Builder $query, int $id) => $query->where('medicine_id', $id))
            ->when($request->integer('user'), fn (Builder $query, int $id) => $query->where('user_id', $id))
            ->when($request->date('date_from'), fn (Builder $query, $date) => $query->whereDate('transacted_at', '>=', $date))
            ->when($request->date('date_to'), fn (Builder $query, $date) => $query->whereDate('transacted_at', '<=', $date))
            ->latest('transacted_at')->get();

        return [
            'title' => self::TYPES[$type],
            'headings' => ['Transaction ID', 'Date', 'Medicine', 'Batch', 'Type', 'Quantity', 'Previous', 'New', 'User', 'Reference', 'Reason'],
            'rows' => $transactions->map(fn (InventoryTransaction $transaction) => [$transaction->transaction_code, $transaction->transacted_at->format('Y-m-d H:i'), $transaction->medicine->generic_name, $transaction->batch?->batch_number ?? '—', $transaction->type->label(), $transaction->quantity, $transaction->previous_stock, $transaction->new_stock, $transaction->user?->name ?? 'System', $transaction->reference_number, $transaction->reason])->values(),
            'summary' => $transactions->count().' transactions · '.number_format($transactions->sum('quantity')).' units moved',
        ];
    }
}
