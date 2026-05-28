<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DivisiSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure id 1 is reserved for "tanpa divisi"
        DB::table('divisis')->updateOrInsert(
            ['id' => 1],
            [
                'name' => 'tanpa divisi',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $divisis = [
            'tanpa divisi',
            'akrilik',
            'aksesoris',
        ];

        foreach ($divisis as $divisi) {
            DB::table('divisis')->updateOrInsert(
                ['name' => $divisi],
                [
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
