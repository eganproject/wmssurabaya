<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create or update superadmin user.
        DB::table('users')->updateOrInsert(
            ['email' => 'superadmin@gmail.com'],
            [
                'name' => 'Super Administrator',
                'divisi_id' => $this->divisiId('tanpa divisi'),
                'password' => Hash::make('Password!2'),
                'email_verified_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $admin = DB::table('users')->where('email', 'superadmin@gmail.com')->first();
        $adminRole = DB::table('roles')->where('slug', 'superadmin')->first();

        if ($admin && $adminRole) {
            DB::table('role_user')->updateOrInsert(
                ['role_id' => $adminRole->id, 'user_id' => $admin->id],
                []
            );
        }

        $users = [
            ['name' => 'Captain',        'email' => 'captain@gmail.com',       'password' => 'Password!2', 'role' => 'captain'],
            ['name' => 'QC Scan',        'email' => 'qcscan@gmail.com',        'password' => 'Password!2', 'role' => 'picker'],
            ['name' => 'Packer',         'email' => 'packer@gmail.com',        'password' => 'Password!2', 'role' => 'packer'],
            ['name' => 'Scan Out',       'email' => 'scanout@gmail.com',       'password' => 'Password!2', 'role' => 'admin-scan'],
            ['name' => 'Admin Retur',    'email' => 'adminretur@gmail.com',    'password' => 'Password!2', 'role' => 'admin-retur'],
            ['name' => 'Admin Resi',     'email' => 'adminresi@gmail.com',     'password' => 'Password!2', 'role' => 'admin-resi'],
            ['name' => 'Kepala Gudang',  'email' => 'kepalagudang@gmail.com',  'password' => 'Password!2', 'role' => 'kepala-gudang'],
            ['name' => 'Admin Gudang',   'email' => 'admingudang@gmail.com',   'password' => 'Password!2', 'role' => 'admin-gudang'],
        ];

        foreach ($users as $user) {
            DB::table('users')->updateOrInsert(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'divisi_id' => $this->divisiId('tanpa divisi'),
                    'password' => Hash::make($user['password']),
                    'email_verified_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $createdUser = DB::table('users')->where('email', $user['email'])->first();
            if ($createdUser) {
                $this->syncRoles($createdUser->id, [$user['role']]);
            }
        }
    }

    private function divisiId(?string $name): ?int
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        return DB::table('divisis')
            ->whereRaw('LOWER(name) = ?', [strtolower(trim($name))])
            ->value('id');
    }

    /**
     * @param array<int,string> $slugs
     */
    private function syncRoles(int $userId, array $slugs): void
    {
        DB::table('role_user')->where('user_id', $userId)->delete();

        if (empty($slugs)) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('slug', $slugs)
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('role_user')->updateOrInsert(
                ['role_id' => $roleId, 'user_id' => $userId],
                []
            );
        }
    }
}
