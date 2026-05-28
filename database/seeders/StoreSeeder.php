<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StoreSeeder extends Seeder
{
    public function run(): void
    {
        $stores = [
            'Media Gudang ACC',
            'Akrilik Teknika',
            'Visual Aksesoris',
            'Cassa',
        ];

        foreach ($stores as $store) {
            DB::table('stores')->updateOrInsert(
                ['name' => $store],
                [
                    'pic_id' => null,
                    'logo' => null,
                    'address' => null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
