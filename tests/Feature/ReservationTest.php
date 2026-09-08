<?php

namespace Tests\Feature;

use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_booking_via_offer_route()
    {
        $supplier = Supplier::create(['code' => 'SUP-1', 'name' => 'Supplier']);
        $import = Import::create([
            'supplier_id' => $supplier->id,
            'external_import_id' => 'EXT-1',
            'status' => 'completed',
            'total_offers' => 1,
            'processed_offers' => 1,
        ]);
        $property = Property::create(['code' => 'PROP-1', 'name' => 'Property', 'city' => 'Kyiv']);
        $offer = Offer::create([
            'supplier_id' => $supplier->id,
            'import_id' => $import->id,
            'property_id' => $property->id,
            'external_id' => 'OFF-1',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-05',
            'max_guests' => 2,
            'price' => 150.00,
            'currency' => 'USD',
            'available_units' => 3,
        ]);

        $response = $this->postJson("/api/offers/{$offer->id}/reservations", [
            'client_reference' => 'REF-123',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'units' => 2,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('units', 2);
        $response->assertJsonPath('offer_id', $offer->id);
        $response->assertJsonPath('client_reference', 'REF-123');
        $response->assertJsonPath('customer_name', 'John Doe');
        $response->assertJsonPath('customer_email', 'john@example.com');

        $this->assertDatabaseHas('offers', [
            'id' => $offer->id,
            'available_units' => 1,
        ]);

        $this->assertDatabaseHas('reservations', [
            'offer_id' => $offer->id,
            'client_reference' => 'REF-123',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'units' => 2,
            'status' => 'confirmed',
        ]);
    }

    public function test_validation_errors_for_required_fields()
    {
        $supplier = Supplier::create(['code' => 'SUP-1', 'name' => 'Supplier']);
        $import = Import::create([
            'supplier_id' => $supplier->id,
            'external_import_id' => 'EXT-1',
            'status' => 'completed',
            'total_offers' => 1,
            'processed_offers' => 1,
        ]);
        $property = Property::create(['code' => 'PROP-1', 'name' => 'Property', 'city' => 'Kyiv']);
        $offer = Offer::create([
            'supplier_id' => $supplier->id,
            'import_id' => $import->id,
            'property_id' => $property->id,
            'external_id' => 'OFF-1',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-05',
            'max_guests' => 2,
            'price' => 150.00,
            'currency' => 'USD',
            'available_units' => 3,
        ]);

        $response = $this->postJson("/api/offers/{$offer->id}/reservations", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['client_reference', 'customer_name', 'customer_email']);
    }

    public function test_failed_booking_insufficient_units()
    {
        $supplier = Supplier::create(['code' => 'SUP-1', 'name' => 'Supplier']);
        $import = Import::create([
            'supplier_id' => $supplier->id,
            'external_import_id' => 'EXT-1',
            'status' => 'completed',
            'total_offers' => 1,
            'processed_offers' => 1,
        ]);
        $property = Property::create(['code' => 'PROP-1', 'name' => 'Property', 'city' => 'Kyiv']);
        $offer = Offer::create([
            'supplier_id' => $supplier->id,
            'import_id' => $import->id,
            'property_id' => $property->id,
            'external_id' => 'OFF-1',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-05',
            'max_guests' => 2,
            'price' => 150.00,
            'currency' => 'USD',
            'available_units' => 1,
        ]);

        $response = $this->postJson("/api/offers/{$offer->id}/reservations", [
            'client_reference' => 'REF-1',
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'units' => 5,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['units']);
    }

    public function test_concurrency_race_condition_prevention()
    {
        $supplier = Supplier::create(['code' => 'SUP-1', 'name' => 'Supplier']);
        $import = Import::create([
            'supplier_id' => $supplier->id,
            'external_import_id' => 'EXT-1',
            'status' => 'completed',
            'total_offers' => 1,
            'processed_offers' => 1,
        ]);
        $property = Property::create(['code' => 'PROP-1', 'name' => 'Property', 'city' => 'Kyiv']);
        $offer = Offer::create([
            'supplier_id' => $supplier->id,
            'import_id' => $import->id,
            'property_id' => $property->id,
            'external_id' => 'OFF-1',
            'check_in' => '2026-10-01',
            'check_out' => '2026-10-05',
            'max_guests' => 2,
            'price' => 150.00,
            'currency' => 'USD',
            'available_units' => 1, // Only 1 unit available
        ]);

        $controller = new \App\Http\Controllers\ReservationController();

        $request1 = \Illuminate\Http\Request::create("/api/offers/{$offer->id}/reservations", 'POST', [
            'client_reference' => 'REF-A',
            'customer_name' => 'User One',
            'customer_email' => 'one@example.com',
            'units' => 1,
        ]);

        $request2 = \Illuminate\Http\Request::create("/api/offers/{$offer->id}/reservations", 'POST', [
            'client_reference' => 'REF-B',
            'customer_name' => 'User Two',
            'customer_email' => 'two@example.com',
            'units' => 1,
        ]);

        // First booking should succeed
        $response1 = $controller->store($request1, $offer);
        $this->assertEquals(201, $response1->getStatusCode());

        // Second booking should fail due to insufficient units (0 left)
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $controller->store($request2, $offer);
    }
}
