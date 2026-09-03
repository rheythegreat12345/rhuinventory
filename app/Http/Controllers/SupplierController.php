<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $suppliers = Supplier::query()
            ->withCount('batches')
            ->when($request->string('search')->toString(), fn ($query, $search) => $query->where(function ($nested) use ($search): void {
                $nested->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")->orWhere('contact_person', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('suppliers.index', compact('suppliers'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('suppliers.form', ['supplier' => new Supplier(['is_active' => true])]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSupplierRequest $request, AuditService $auditService): RedirectResponse
    {
        $supplier = Supplier::query()->create($request->validated());
        $auditService->record('supplier_created', "Created supplier {$supplier->name}.", $supplier, null, $supplier->toArray());

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Supplier created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Supplier $supplier): View
    {
        $supplier->load([
            'batches' => fn ($query) => $query->with('medicine')->latest('received_at')->limit(30),
        ]);

        return view('suppliers.show', compact('supplier'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Supplier $supplier): View
    {
        return view('suppliers.form', compact('supplier'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSupplierRequest $request, Supplier $supplier, AuditService $auditService): RedirectResponse
    {
        $oldValues = $supplier->toArray();
        $supplier->update($request->validated());
        $auditService->record('supplier_updated', "Updated supplier {$supplier->name}.", $supplier, $oldValues, $supplier->fresh()->toArray());

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Supplier updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Supplier $supplier, AuditService $auditService): RedirectResponse
    {
        if ($supplier->batches()->where('quantity', '>', 0)->exists()) {
            return back()->with('error', 'This supplier has active stock and cannot be archived.');
        }

        $auditService->record('supplier_archived', "Archived supplier {$supplier->name}.", $supplier);
        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', 'Supplier archived.');
    }
}
