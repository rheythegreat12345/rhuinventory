<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMedicineCategoryRequest;
use App\Http\Requests\UpdateMedicineCategoryRequest;
use App\Models\MedicineCategory;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MedicineCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $categories = MedicineCategory::query()
            ->withCount('medicines')
            ->when($request->string('search')->toString(), fn ($query, $search) => $query->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('categories.index', compact('categories'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('categories.form', ['category' => new MedicineCategory(['is_active' => true])]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMedicineCategoryRequest $request, AuditService $auditService): RedirectResponse
    {
        $category = MedicineCategory::query()->create($request->validated());
        $auditService->record('category_created', "Created medicine category {$category->name}.", $category, null, $category->toArray());

        return redirect()->route('categories.index')->with('success', 'Category created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(MedicineCategory $category): RedirectResponse
    {
        return redirect()->route('medicines.index', ['category' => $category->id]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MedicineCategory $category): View
    {
        return view('categories.form', compact('category'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMedicineCategoryRequest $request, MedicineCategory $category, AuditService $auditService): RedirectResponse
    {
        $oldValues = $category->toArray();
        $category->update($request->validated());
        $auditService->record('category_updated', "Updated medicine category {$category->name}.", $category, $oldValues, $category->fresh()->toArray());

        return redirect()->route('categories.index')->with('success', 'Category updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MedicineCategory $category, AuditService $auditService): RedirectResponse
    {
        if ($category->medicines()->exists()) {
            return back()->with('error', 'This category has medicines and cannot be archived yet.');
        }

        $auditService->record('category_archived', "Archived medicine category {$category->name}.", $category);
        $category->delete();

        return back()->with('success', 'Category archived.');
    }
}
