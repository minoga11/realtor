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
        $supplier = Supplier::create(['code' => 'SUP-TEST', 'name' => 'Supplier']);
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

    public function test_cheapest_active_offers_query()
    {
        $supplier = Supplier::create(['code' => 'SUP-TEST', 'name' => 'Supplier']);
        $import = Import::create([
            'supplier_id' => $supplier->id,
            'external_import_id' => 'EXT-2',
            'status' => 'completed',
            'total_offers' => 5,
            'processed_offers' => 5,
        ]);

        $property1 = Property::create(['code' => 'P1', 'name' => 'Property 1', 'city' => 'Kyiv']);
        $property2 = Property::create(['code' => 'P2', 'name' => 'Property 2', 'city' => 'Lviv']);

        // Property 1: offer 1 (price 200, active), offer 2 (price 100, active) -> cheapest should be 100
        Offer::create([
            'supplier_id' => $supplier->id,
            'import_id' => $import->id,
            'property_id' => $property1->id,
            'external_id' => 'O1',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-05',
            'max_guests' => 2,
            'price' => 200.00,
            'currency' => 'USD',
            'available_units' => 2,
            'expires_at' => now()->addDays(5),
        ]);

        Offer::create([
            'supplier_id' => $supplier->id,
            'import_id' => $import->id,
            'property_id' => $property1->id,
            'external_id' => 'O2',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-05',
            'max_guests' => 2,
            'price' => 100.00,
            'currency' => 'USD',
            'available_units' => 2,
            'expires_at' => now()->addDays(5),
        ]);

        // Property 2: offer 3 (price 150, expired), offer 4 (price 120, 0 available units), offer 5 (price 180, active) -> cheapest should be 180
        Offer::create([
            'supplier_id' => $supplier->id,
            'import_id' => $import->id,
            'property_id' => $property2->id,
            'external_id' => 'O3',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-05',
            'max_guests' => 2,
            'price' => 150.00,
            'currency' => 'USD',
            'available_units' => 2,
            'expires_at' => now()->subDays(1), // expired
        ]);

        Offer::create([
            'supplier_id' => $supplier->id,
            'import_id' => $import->id,
            'property_id' => $property2->id,
            'external_id' => 'O4',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-05',
            'max_guests' => 2,
            'price' => 120.00,
            'currency' => 'USD',
            'available_units' => 0, // 0 units
            'expires_at' => now()->addDays(5),
        ]);

        Offer::create([
            'supplier_id' => $supplier->id,
            'import_id' => $import->id,
            'property_id' => $property2->id,
            'external_id' => 'O5',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-05',
            'max_guests' => 2,
            'price' => 180.00,
            'currency' => 'USD',
            'available_units' => 1,
            'expires_at' => now()->addDays(5),
        ]);

        $response = $this->getJson('/api/properties/cheapest-offers');

        $response->assertStatus(200);
        $response->assertJsonCount(2);

        $data = $response->json();

        // Find offer for Property 1 (should be O2 with price 100)
        $offerP1 = collect($data)->firstWhere('property_id', $property1->id);
        $this->assertEquals(100.00, $offerP1['price']);
        $this->assertEquals('O2', $offerP1['external_id']);

        // Find offer for Property 2 (should be O5 with price 180)
        $offerP2 = collect($data)->firstWhere('property_id', $property2->id);
        $this->assertEquals(180.00, $offerP2['price']);
        $this->assertEquals('O5', $offerP2['external_id']);
    }
}
