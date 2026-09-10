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
    public function store(Request $request, ?Offer $offer = null): JsonResponse
    {
        $validated = $request->validate([
            'client_reference' => ['required', 'string'],
            'customer_name' => ['required', 'string'],
            'customer_email' => ['required', 'email'],
            'units' => ['sometimes', 'integer', 'min:1'],
            'offer_id' => [$offer ? 'nullable' : 'required', 'integer', 'exists:offers,id'],
        ]);

        $offerId = $offer ? $offer->id : $validated['offer_id'];
        $requestedUnits = $validated['units'] ?? 1;

        $reservation = DB::transaction(function () use ($offerId, $validated, $requestedUnits) {
            $offerLocked = Offer::where('id', $offerId)->lockForUpdate()->first();

            if (!$offerLocked) {
                abort(404, 'Offer not found.');
            }

            if ($offerLocked->available_units < $requestedUnits) {
                throw ValidationException::withMessages([
                    'units' => ['Requested units exceed available units or no units available.'],
                ]);
            }

            $offerLocked->available_units -= $requestedUnits;
            $offerLocked->save();

            return Reservation::create([
                'offer_id' => $offerLocked->id,
                'client_reference' => $validated['client_reference'],
                'customer_name' => $validated['customer_name'],
                'customer_email' => $validated['customer_email'],
                'units' => $requestedUnits,
                'status' => 'confirmed',
            ]);
        });

        return response()->json($reservation->load('offer'), 201);
    }
}
