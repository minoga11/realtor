<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PropertyOfferController extends Controller
{
    public function cheapestOffers(): JsonResponse
    {
        $now = now();

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

        return response()->json($offers);
    }
}
