<?php

namespace Tests\Feature;

use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyOfferTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_status_endpoint()
    {
        $supplier = Supplier::create(['name' => 'Supplier']);
        $import = Import::create([
            'supplier_id' => $supplier->id,
            'external_import_id' => 'EXT-1',
            'status' => 'completed',
            'total_offers' => 10,
            'processed_offers' => 10,
            'completed_at' => now(),
        ]);

        $response = $this->getJson("/api/imports/{$import->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $import->id,
            'status' => 'completed',
            'total_offers' => 10,
            'processed_offers' => 10,
        ]);

        $response404 = $this->getJson('/api/imports/99999');
        $response404->assertStatus(404);
    }

    public function test_property_search_endpoint_with_filters_and_pagination()
    {
        $supplier = Supplier::create(['name' => 'Supplier']);
        $import = Import::create([
            'supplier_id' => $supplier->id,
            'external_import_id' => 'EXT-3',
            'status' => 'completed',
            'total_offers' => 2,
            'processed_offers' => 2,
        ]);

        $property1 = Property::create(['code' => 'P1', 'name' => 'Property 1', 'city' => 'Kyiv']);
        $property2 = Property::create(['code' => 'P2', 'name' => 'Property 2', 'city' => 'Lviv']);

        Offer::create([
            'supplier_id' => $supplier->id,
            'import_id' => $import->id,
            'property_id' => $property1->id,
            'external_id' => 'O1',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-05',
            'max_guests' => 4,
            'price' => 150.00,
            'currency' => 'USD',
            'available_units' => 2,
            'expires_at' => now()->addDays(5),
        ]);

        Offer::create([
            'supplier_id' => $supplier->id,
            'import_id' => $import->id,
            'property_id' => $property2->id,
            'external_id' => 'O2',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-05',
            'max_guests' => 4,
            'price' => 100.00,
            'currency' => 'USD',
            'available_units' => 2,
            'expires_at' => now()->addDays(5),
        ]);

        $response = $this->getJson('/api/properties?check_in=2026-10-01&check_out=2026-10-05&guests=2&city=Kyiv');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $property1->id);
        $response->assertJsonPath('data.0.best_offer.external_id', 'O1');
        $response->assertJsonStructure(['data', 'links', 'current_page', 'per_page', 'total']);
    }

    public function test_property_search_endpoint_validation_errors()
    {
        $response = $this->getJson('/api/properties');
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['check_in', 'check_out', 'guests']);
    }
}
