<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $legacyNames = [
            'Dayao Satellite College' => 'Dayao Satellite Campus',
            'Pontevedra Campus' => 'Pontevedra Satellite Campus',
            'Sigma Satellite College' => 'Sigma Satellite Campus',
            'Tapaz Satellite College' => 'Tapaz Satellite Campus',
        ];

        foreach ($legacyNames as $legacyName => $currentName) {
            $legacyLocation = Location::query()->where('name', $legacyName)->first();

            if ($legacyLocation && ! Location::query()->where('name', $currentName)->exists()) {
                $legacyLocation->update(['name' => $currentName]);
            }
        }

        $locations = [
            'Central Administration',
            'Roxas Main Campus',
            'Dayao Satellite Campus',
            'Pontevedra Satellite Campus',
            'Sigma Satellite Campus',
            'Tapaz Satellite Campus',
        ];

        foreach ($locations as $location) {
            Location::firstOrCreate(['name' => $location]);
        }
    }
}
