<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'offer_id' => ['required', 'integer', 'exists:offers,id'],
            'units' => ['sometimes', 'integer', 'min:1'],
        ]);

        $requestedUnits = $validated['units'] ?? 1;

        $reservation = DB::transaction(function () use ($validated, $requestedUnits) {
            $offer = Offer::where('id', $validated['offer_id'])->lockForUpdate()->first();

            if (!$offer) {
                abort(404, 'Offer not found.');
            }

            if ($offer->available_units < $requestedUnits) {
                throw ValidationException::withMessages([
                    'units' => ['Requested units exceed available units or no units available.'],
                ]);
            }

            $offer->available_units -= $requestedUnits;
            $offer->save();

            return Reservation::create([
                'offer_id' => $offer->id,
                'units' => $requestedUnits,
                'status' => 'confirmed',
            ]);
        });

        return response()->json($reservation->load('offer'), 201);
    }
}
