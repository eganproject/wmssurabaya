<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategoryItemSeeder extends Seeder
{
    public function run(): void
    {
        Category::firstOrCreate(
            ['name' => 'Tanpa Kategori'],
            ['parent_id' => 0]
        );
    }
}
