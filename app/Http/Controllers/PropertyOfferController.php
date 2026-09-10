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
        //   DB::enableQueryLog();
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
                    $subQ->select(DB::raw('MIN(id)'))
                        ->from('offers')
                        ->where('check_in', '<=', $checkIn)
                        ->where('check_out', '>=', $checkOut)
                        ->where('max_guests', '>=', $guests)
                        ->where('available_units', '>', 0)
                        ->where(function ($sub) use ($now) {
                            $sub->whereNull('expires_at')
                                ->orWhere('expires_at', '>', $now);
                        })
                        ->groupBy('property_id');
                })
                ->with('supplier')

                ->get()
                ->keyBy('property_id');

            //  dd(DB::getQueryLog());

            $paginator->getCollection()->transform(function ($property) use ($bestOffers) {
                $offer = $bestOffers->get($property->id);
                $property->setRelation('best_offer', $offer);
                return $property;
            });
        }

        return response()->json($paginator);
    }
}
