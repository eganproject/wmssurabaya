<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Super Administrator', 'slug' => 'superadmin', 'description' => 'Full access to system'],
            ['name' => 'User', 'slug' => 'user', 'description' => 'Standard user role'],
            ['name' => 'QC Scan', 'slug' => 'picker', 'description' => 'Role QC scan input barang ke QC transit'],
            ['name' => 'Packer', 'slug' => 'packer', 'description' => 'Role mobile lama untuk flow packing sebelumnya'],
            ['name' => 'Scan Out', 'slug' => 'admin-scan', 'description' => 'Role mobile khusus scan out'],
            ['name' => 'Captain', 'slug' => 'captain', 'description' => 'Role captain gudang'],
            ['name' => 'Admin Retur', 'slug' => 'admin-retur', 'description' => 'Role admin retur'],
            ['name' => 'Admin Resi', 'slug' => 'admin-resi', 'description' => 'Role admin import dan pengelolaan resi'],
            ['name' => 'Kepala Gudang', 'slug' => 'kepala-gudang', 'description' => 'Role kepala gudang'],
            ['name' => 'Admin Gudang', 'slug' => 'admin-gudang', 'description' => 'Role admin gudang'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['slug' => $role['slug']],
                [
                    'name' => $role['name'],
                    'description' => $role['description'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
