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

        $validatedImports = $request->validated();
        $createdImports = [];

        foreach ($validatedImports as $importData) {
            //  dd($importData);
            $supplier = Supplier::updateOrCreate(
                ['name' => $importData['supplier']],
                []
            );
            //  dd($request->all());
            $import = Import::updateOrCreate(
                [
                    'supplier_id' => $supplier->id,
                    'external_import_id' => $importData['external_import_id'],
                ],
                [
                    'sent_at' => $importData['sent_at'] ?? null,
                    'status' => 'pending',
                    'total_offers' => count($importData['offers']),
                    'processed_offers' => 0,
                ]
            );

            ProcessImportJob::dispatch($import, $importData['offers']);

            $createdImports[] = [
                'id' => $import->id,
                'supplier' => $supplier->name,
                'external_import_id' => $import->external_import_id,
                'status' => $import->status,
                'total_offers' => $import->total_offers,
            ];
        }

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
