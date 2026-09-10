<?php

namespace App\Jobs;

use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, Queueable, InteractsWithQueue, SerializesModels;

    protected Import $import;
    protected array $offers;

    public function __construct(Import $import, array $offers)
    {
        $this->import = $import;
        $this->offers = $offers;
    }

    public function handle(): void
    {
        $this->import->status = 'processing';
        $this->import->save();

        $chunkSize = 500;

        foreach (array_chunk($this->offers, $chunkSize) as $chunk) {
            foreach ($chunk as $offerData) {
                $propertyCode = $offerData['property']['code'] ?? null;

                $property = Property::firstOrCreate(
                    ['code' => $propertyCode],
                    [
                        'name' => $offerData['property']['name'] ?? '',
                        'city' => $offerData['property']['city'] ?? '',
                    ]
                );

                Offer::updateOrCreate(
                    [
                        'supplier_id' => $this->import->supplier_id,
                        'external_id' => $offerData['external_id']
                    ],
                    [
                        'import_id' => $this->import->id,
                        'property_id' => $property->id,
                        'check_in' => $offerData['check_in'],
                        'check_out' => $offerData['check_out'],
                        'max_guests' => $offerData['max_guests'] ?? 2,
                        'price' => $offerData['price'],
                        'currency' => $offerData['currency'],
                        'available_units' => $offerData['available_units'],
                        'expires_at' => $offerData['expires_at'] ?? null,
                    ]
                );

                $this->import->increment('processed_offers', 1);
            }
        }

        $this->import->status = 'completed';
        $this->import->save();
    }
}
