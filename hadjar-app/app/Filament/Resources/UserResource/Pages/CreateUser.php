<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use App\Services\KodeMitraService;
use Database\Seeders\RoleSeeder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected static bool $canCreateAnother = false;

    protected ?string $generatedTemporaryPassword = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->generatedTemporaryPassword = Str::random(12);

        $data['kode_mitra'] = app(KodeMitraService::class)->generateNext();
        $data['password'] = $this->generatedTemporaryPassword;
        $data['must_change_password'] = true;
        $data['is_active'] = $data['is_active'] ?? true;

        return $data;
    }

    protected function afterCreate(): void
    {
        Role::firstOrCreate([
            'name' => RoleSeeder::ROLE_MITRA,
            'guard_name' => 'web',
        ]);

        /** @var User $record */
        $record = $this->record;
        $record->assignRole(RoleSeeder::ROLE_MITRA);
    }

    protected function getCreatedNotification(): ?Notification
    {
        /** @var User $record */
        $record = $this->record;
        $temporaryPassword = $this->generatedTemporaryPassword;
        $this->generatedTemporaryPassword = null;

        return Notification::make()
            ->title('Akun mitra berhasil dibuat')
            ->body("Kode Mitra: HSC {$record->kode_mitra} | Kata sandi sementara (hanya tampil sekali): {$temporaryPassword}")
            ->persistent()
            ->success();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
