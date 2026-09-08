<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreImportRequest;
use App\Models\Import;
use App\Models\Supplier;
use App\Jobs\ProcessImportJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Import::with('supplier')->latest()->paginate(15));
    }

    public function store(StoreImportRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $supplier = Supplier::where('code', $validated['supplier_code'])->firstOrFail();

        $import = Import::create([
            'supplier_id' => $supplier->id,
            'external_import_id' => $validated['external_import_id'],
            'status' => 'pending',
            'total_offers' => count($validated['offers']),
            'processed_offers' => 0,
        ]);

        ProcessImportJob::dispatch($import, $validated['offers']);

        return response()->json([
            'id' => $import->id,
            'status' => $import->status,
            'total_offers' => $import->total_offers,
        ], 202);
    }

    public function show(Import $import): JsonResponse
    {
        return response()->json($import);
    }
}
