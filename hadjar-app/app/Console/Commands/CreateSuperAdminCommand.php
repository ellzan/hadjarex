<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class CreateSuperAdminCommand extends Command
{
    protected $signature = 'app:create-super-admin';

    protected $description = 'Create the first Super Admin account with kode mitra 001';

    public function handle(): int
    {
        if (User::where('kode_mitra', '001')->exists()) {
            $this->error('Super Admin with kode mitra 001 already exists.');

            return self::FAILURE;
        }

        $email = (string) $this->ask('Email');
        $password = (string) $this->secret('Password');

        $validator = Validator::make(
            [
                'email' => $email,
                'password' => $password,
            ],
            [
                'email' => ['required', 'email', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        Role::firstOrCreate([
            'name' => RoleSeeder::ROLE_SUPER_ADMIN,
            'guard_name' => 'web',
        ]);

        Role::firstOrCreate([
            'name' => RoleSeeder::ROLE_MITRA,
            'guard_name' => 'web',
        ]);

        $user = User::create([
            'name' => 'Super Admin',
            'email' => $email,
            'password' => $password,
            'kode_mitra' => '001',
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $user->assignRole(RoleSeeder::ROLE_SUPER_ADMIN);

        $this->info("Super Admin created successfully with kode mitra HSC {$user->kode_mitra}.");

        return self::SUCCESS;
    }
}
