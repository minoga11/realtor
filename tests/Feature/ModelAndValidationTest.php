<?php

namespace Tests\Feature;

use App\Http\Requests\StoreImportRequest;
use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ModelAndValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_models_relationships_and_casts()
    {
        $supplier = Supplier::create([
            'name' => 'Supplier One',
        ]);

        $import = Import::create([
            'supplier_id' => $supplier->id,
            'external_import_id' => 'EXT-123',
            'sent_at' => now(),
            'status' => 'pending',
            'total_offers' => 1,
            'processed_offers' => 0,
        ]);

        $property = Property::create([
            'code' => 'PROP1',
            'name' => 'Property One',
            'city' => 'Kyiv',
        ]);

        $offer = Offer::create([
            'supplier_id' => $supplier->id,
            'property_id' => $property->id,
            'import_id' => $import->id,
            'external_id' => 'OFF-1',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-05',
            'max_guests' => 2,
            'price' => 150.50,
            'currency' => 'EUR',
            'available_units' => 3,
            'expires_at' => now()->addDay(),
        ]);

        $reservation = Reservation::create([
            'offer_id' => $offer->id,
            'client_reference' => 'REF-001',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'units' => 1,
            'status' => 'confirmed',
        ]);

        $this->assertCount(1, $supplier->imports);
        $this->assertCount(1, $supplier->offers);
        $this->assertEquals($supplier->id, $import->supplier->id);
        $this->assertCount(1, $import->offers);
        $this->assertCount(1, $property->offers);
        $this->assertEquals($supplier->id, $offer->supplier->id);
        $this->assertEquals($property->id, $offer->property->id);
        $this->assertEquals($import->id, $offer->import->id);
        $this->assertCount(1, $offer->reservations);
        $this->assertEquals($offer->id, $reservation->offer->id);

        $this->assertInstanceOf(\Carbon\CarbonInterface::class, $offer->check_in);
        $this->assertInstanceOf(\Carbon\CarbonInterface::class, $offer->check_out);
        $this->assertInstanceOf(\Carbon\CarbonInterface::class, $offer->expires_at);
        $this->assertInstanceOf(\Carbon\CarbonInterface::class, $import->sent_at);
    }

    public function test_store_import_request_validation()
    {
        $supplier = Supplier::create([
            'name' => 'Supplier One',
        ]);

        $property = Property::create([
            'code' => 'PROP1',
            'name' => 'Property One',
            'city' => 'Kyiv',
        ]);

        $payload = [
            [
                'supplier' => 'Supplier One',
                'external_import_id' => 'EXT-999',
                'offers' => [
                    [
                        'external_id' => 'OFF-999',
                        'property' => [
                            'code' => 'PROP1',
                        ],
                        'check_in' => now()->addDays(2)->toDateString(),
                        'check_out' => now()->addDays(5)->toDateString(),
                        'max_guests' => 2,
                        'price' => 200.00,
                        'currency' => 'USD',
                        'available_units' => 2,
                    ]
                ]
            ]
        ];

        $request = StoreImportRequest::create('/api/imports', 'POST', $payload);
        $validator = Validator::make($payload, $request->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_store_import_request_validation_fails_for_invalid_data()
    {
        $payload = [
            [
                'supplier' => '',
                'external_import_id' => '',
                'offers' => [
                    [
                        'external_id' => '',
                        'property' => [
                            'code' => '',
                        ],
                        'check_in' => 'invalid-date',
                        'check_out' => '2026-09-01',
                        'price' => -10,
                        'currency' => 'US',
                        'available_units' => 0,
                    ]
                ]
            ]
        ];

        $request = StoreImportRequest::create('/api/imports', 'POST', $payload);
        $validator = Validator::make($payload, $request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('0.supplier', $validator->errors()->messages());
        $this->assertArrayHasKey('0.external_import_id', $validator->errors()->messages());
        $this->assertArrayHasKey('0.offers.0.external_id', $validator->errors()->messages());
        $this->assertArrayHasKey('0.offers.0.property.code', $validator->errors()->messages());
        $this->assertArrayHasKey('0.offers.0.currency', $validator->errors()->messages());
        $this->assertArrayHasKey('0.offers.0.available_units', $validator->errors()->messages());
    }
}
