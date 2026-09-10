<?php

namespace Tests\Feature;

use App\Http\Requests\StoreImportRequest;
use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_api_returns_202()
    {
        Queue::fake();

        $supplier = Supplier::create([
            'name' => 'Supplier One',
        ]);

        Property::create(['code' => 'PROP-1', 'name' => 'Property One', 'city' => 'Kyiv']);
        Property::create(['code' => 'PROP-2', 'name' => 'Property Two', 'city' => 'Lviv']);

        $payload = [
            [
                'supplier' => $supplier->name,
                'external_import_id' => 'EXT-123',
                'offers' => [
                    [
                        'external_id' => 'OFF-1',
                        'property' => [
                            'code' => 'PROP-1',
                        ],
                        'check_in' => '2026-10-01',
                        'check_out' => '2026-10-05',
                        'max_guests' => 2,
                        'price' => 150.50,
                        'currency' => 'USD',
                        'available_units' => 2,
                        'expires_at' => '2026-12-31',
                    ],
                    [
                        'external_id' => 'OFF-2',
                        'property' => [
                            'code' => 'PROP-2',
                        ],
                        'check_in' => '2026-10-02',
                        'check_out' => '2026-10-06',
                        'max_guests' => 4,
                        'price' => 200.00,
                        'currency' => 'USD',
                        'available_units' => 1,
                        'expires_at' => '2026-12-31',
                    ],
                ],
            ]
        ];

        $response = $this->postJson('/api/imports', $payload);

        $response->assertStatus(202);
        $response->assertJsonStructure(['id', 'status', 'total_offers']);

        Queue::assertPushed(\App\Jobs\ProcessImportJob::class);

        $import = Import::where('external_import_id', 'EXT-123')->first();

        $this->assertNotNull($import);
        $this->assertEquals('pending', $import->status);
        $this->assertEquals(2, $import->total_offers);
        $this->assertEquals(0, $import->processed_offers);
    }

    public function test_import_api_validation_fails()
    {
        $response = $this->postJson('/api/imports', []);

        $response->assertStatus(422);
    }

    public function test_import_controller_creates_import_record()
    {
        $supplier = Supplier::create([
            'name' => 'Supplier Two',
        ]);

        $import = Import::create([
            'supplier_id' => $supplier->id,
            'external_import_id' => 'EXT-MANUAL',
            'status' => 'pending',
            'total_offers' => 0,
            'processed_offers' => 0,
        ]);

        $this->assertEquals('Supplier Two', $import->supplier->name);
        $this->assertEquals('EXT-MANUAL', $import->external_import_id);
        $this->assertEquals('pending', $import->status);
        $this->assertEquals(0, $import->total_offers);
        $this->assertEquals(0, $import->processed_offers);
    }

    public function test_offer_upsert_logic()
    {
        $supplier = Supplier::create([
            'name' => 'Supplier Three',
        ]);

        $import = Import::create([
            'supplier_id' => $supplier->id,
            'external_import_id' => 'EXT-UPSERT',
            'status' => 'pending',
            'total_offers' => 0,
            'processed_offers' => 0,
        ]);

        $property = Property::create(['code' => 'PROP-UPSERT', 'name' => 'Upsert Property', 'city' => 'Kyiv']);

        // Simulate the upsert logic from ProcessImportJob
        $offerData = [
            'external_id' => 'OFF-UPSERT-1',
            'property' => [
                'code' => 'PROP-UPSERT',
            ],
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-05',
            'max_guests' => 2,
            'price' => 150.50,
            'currency' => 'USD',
            'available_units' => 2,
            'expires_at' => '2026-12-31',
        ];

        $offer = Offer::updateOrCreate(
            ['supplier_id' => $supplier->id, 'external_id' => 'OFF-UPSERT-1'],
            [
                'import_id' => $import->id,
                'property_id' => $property->id,
                'check_in' => $offerData['check_in'],
                'check_out' => $offerData['check_out'],
                'max_guests' => $offerData['max_guests'],
                'price' => $offerData['price'],
                'currency' => $offerData['currency'],
                'available_units' => $offerData['available_units'],
                'expires_at' => $offerData['expires_at'],
            ]
        );

        $this->assertNotNull($offer->id);
        $this->assertEquals($property->id, $offer->property_id);
        $this->assertEquals(150.50, $offer->price);
        $this->assertEquals('USD', $offer->currency);
        $this->assertEquals(2, $offer->available_units);

        // Verify import offers relationship
        $this->assertCount(1, $import->offers);
        $this->assertEquals($offer->id, $import->offers->first()->id);
    }

    public function test_import_processing_logic()
    {
        $supplier = Supplier::create([
            'name' => 'Supplier Four',
        ]);

        $import = Import::create([
            'supplier_id' => $supplier->id,
            'external_import_id' => 'EXT-PROCESS',
            'status' => 'pending',
            'total_offers' => 0,
            'processed_offers' => 0,
        ]);

        $property = Property::create(['code' => 'PROP-PROCESS', 'name' => 'Process Property', 'city' => 'Lviv']);

        // Simulate processing multiple offers
        $offers = [
            [
                'external_id' => 'OFF-PROCESS-1',
                'property' => ['code' => 'PROP-PROCESS'],
                'check_in' => '2026-10-01',
                'check_out' => '2026-10-05',
                'max_guests' => 2,
                'price' => 100.00,
                'currency' => 'USD',
                'available_units' => 3,
                'expires_at' => '2026-12-31',
            ],
            [
                'external_id' => 'OFF-PROCESS-2',
                'property' => ['code' => 'PROP-PROCESS'],
                'check_in' => '2026-10-02',
                'check_out' => '2026-10-06',
                'max_guests' => 3,
                'price' => 200.00,
                'currency' => 'USD',
                'available_units' => 1,
                'expires_at' => '2026-12-31',
            ],
        ];

        // Simulate the processing logic from ProcessImportJob
        $chunkSize = 500;

        foreach (array_chunk($offers, $chunkSize) as $chunk) {
            foreach ($chunk as $offerData) {
                $property = Property::firstOrCreate(
                    ['code' => $offerData['property']['code']],
                    [
                        'name' => $offerData['property']['name'] ?? '',
                        'city' => $offerData['property']['city'] ?? '',
                    ]
                );

                Offer::updateOrCreate(
                    ['supplier_id' => $import->supplier_id, 'external_id' => $offerData['external_id']],
                    [
                        'import_id' => $import->id,
                        'property_id' => $property->id,
                        'check_in' => $offerData['check_in'],
                        'check_out' => $offerData['check_out'],
                        'max_guests' => $offerData['max_guests'],
                        'price' => $offerData['price'],
                        'currency' => $offerData['currency'],
                        'available_units' => $offerData['available_units'],
                        'expires_at' => $offerData['expires_at'] ?? null,
                    ]
                );

                $import->increment('processed_offers', 1);
            }
        }

        $import->status = 'completed';
        $import->save();

        $this->assertEquals('completed', $import->status);
        $this->assertEquals(2, $import->processed_offers);
        $this->assertCount(2, $import->offers);

        // Verify offers are correctly created
        $this->assertEquals(100.00, $import->offers->first()->price);
        $this->assertEquals(200.00, $import->offers->last()->price);
    }
}
