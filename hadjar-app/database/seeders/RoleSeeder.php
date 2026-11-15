<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_MITRA = 'mitra';

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::firstOrCreate([
            'name' => self::ROLE_SUPER_ADMIN,
            'guard_name' => 'web',
        ]);

        Role::firstOrCreate([
            'name' => self::ROLE_MITRA,
            'guard_name' => 'web',
        ]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
