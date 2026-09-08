<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PropertyOfferController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'check_in' => ['required', 'date'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'guests' => ['required', 'integer', 'min:1'],
            'city' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $checkIn = $validated['check_in'];
        $checkOut = $validated['check_out'];
        $guests = $validated['guests'];
        $city = $validated['city'] ?? null;
        $perPage = $validated['per_page'] ?? 15;
        $now = now();

        $query = Property::query()
            ->when($city, function ($q) use ($city) {
                $q->where('city', $city);
            })
            ->whereHas('offers', function ($q) use ($checkIn, $checkOut, $guests, $now) {
                $q->where('check_in', '<=', $checkIn)
                    ->where('check_out', '>=', $checkOut)
                    ->where('max_guests', '>=', $guests)
                    ->where('available_units', '>', 0)
                    ->where(function ($sub) use ($now) {
                        $sub->whereNull('expires_at')
                            ->orWhere('expires_at', '>', $now);
                    });
            });

        $paginator = $query->paginate($perPage);

        $propertyIds = $paginator->pluck('id');

        if ($propertyIds->isNotEmpty()) {
            $bestOffers = Offer::query()
                ->select('offers.*')
                ->whereIn('property_id', $propertyIds)
                ->where('check_in', '<=', $checkIn)
                ->where('check_out', '>=', $checkOut)
                ->where('max_guests', '>=', $guests)
                ->where('available_units', '>', 0)
                ->where(function ($sub) use ($now) {
                    $sub->whereNull('expires_at')
                        ->orWhere('expires_at', '>', $now);
                })
                ->whereIn('id', function ($subQ) use ($checkIn, $checkOut, $guests, $now) {
                    $subQ->select('id')
                        ->from(DB::raw('(SELECT id, ROW_NUMBER() OVER (PARTITION BY property_id ORDER BY price ASC) as rn FROM offers WHERE check_in <= "' . $checkIn . '" AND check_out >= "' . $checkOut . '" AND max_guests >= ' . $guests . ' AND available_units > 0 AND (expires_at IS NULL OR expires_at > "' . $now . '")) as ranked'))
                        ->where('rn', 1);
                })
                ->with('supplier')
                ->get()
                ->keyBy('property_id');

            $paginator->getCollection()->transform(function ($property) use ($bestOffers) {
                $offer = $bestOffers->get($property->id);
                $property->setRelation('best_offer', $offer);
                return $property;
            });
        }

        return response()->json($paginator);
    }

    public function cheapestOffers(): JsonResponse
    {
        $now = now();
        DB::enableQueryLog();
        $offers = Offer::query()
            ->select('offers.*')
            ->where('available_units', '>', 0)
            ->where(function ($query) use ($now) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', $now);
            })
            ->whereIn('id', function ($query) use ($now) {
                $query->select('id')
                    ->from(DB::raw('(SELECT id, ROW_NUMBER() OVER (PARTITION BY property_id ORDER BY price ASC) as rn FROM offers WHERE available_units > 0 AND (expires_at IS NULL OR expires_at > "' . $now . '")) as ranked'))
                    ->where('rn', 1);
            })
            ->with(['property', 'supplier'])
            ->get();
        dd(DB::getQueryLog());

        return response()->json($offers);
    }
}
