<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\ChangePassword;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PhaseOneAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_super_admin_creates_mitra_with_sequential_kode_mitra_and_temporary_credential(): void
    {
        $superAdmin = User::factory()->create([
            'kode_mitra' => '001',
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $superAdmin->assignRole(RoleSeeder::ROLE_SUPER_ADMIN);

        $this->actingAs($superAdmin);

        $this->get('/panel/users/create')->assertOk();

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Mitra Pertama',
                'email' => 'mitra1@hadjarexpress.test',
                'phone' => '081234567890',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified();

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Mitra Kedua',
                'email' => 'mitra2@hadjarexpress.test',
                'phone' => '081234567891',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $mitra1 = User::where('email', 'mitra1@hadjarexpress.test')->firstOrFail();
        $mitra2 = User::where('email', 'mitra2@hadjarexpress.test')->firstOrFail();

        $this->assertSame('002', $mitra1->kode_mitra);
        $this->assertSame('003', $mitra2->kode_mitra);
        $this->assertTrue($mitra1->hasRole(RoleSeeder::ROLE_MITRA));
        $this->assertTrue($mitra2->hasRole(RoleSeeder::ROLE_MITRA));
        $this->assertTrue($mitra1->must_change_password);
        $this->assertTrue($mitra2->must_change_password);
    }

    public function test_new_mitra_must_change_password_on_first_login_and_middleware_blocks_other_pages(): void
    {
        $newCredential = Str::random(16);

        $mitra = User::factory()->create([
            'email' => 'mitrabaru@hadjarexpress.test',
            'kode_mitra' => '002',
            'is_active' => true,
            'must_change_password' => true,
        ]);
        $mitra->assignRole(RoleSeeder::ROLE_MITRA);

        $this->actingAs($mitra);

        $this->get('/panel')
            ->assertRedirect(route('filament.panel.pages.change-password'));

        $this->get('/panel/change-password')
            ->assertOk()
            ->assertSee('Wajib Ganti Kata Sandi');

        Livewire::test(ChangePassword::class)
            ->fillForm([
                'password' => $newCredential,
                'password_confirmation' => $newCredential,
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect('/panel');

        $mitra->refresh();

        $this->assertFalse($mitra->must_change_password);
        $this->assertTrue(Hash::check($newCredential, $mitra->password));

        $this->get('/panel')->assertOk();
    }

    public function test_login_lockout_after_five_failed_attempts_for_fifteen_minutes(): void
    {
        $validCredential = Str::random(16);
        $wrongCredential = Str::random(16);

        $mitra = User::factory()->create([
            'email' => 'locktest@hadjarexpress.test',
            'password' => $validCredential,
            'kode_mitra' => '002',
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $mitra->assignRole(RoleSeeder::ROLE_MITRA);

        for ($i = 0; $i < 5; $i++) {
            Livewire::test(Login::class)
                ->fillForm([
                    'email' => 'locktest@hadjarexpress.test',
                    'password' => $wrongCredential,
                ])
                ->call('authenticate')
                ->assertHasFormErrors(['email']);
        }

        // 6th attempt even with valid credential must be rejected while locked
        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'locktest@hadjarexpress.test',
                'password' => $validCredential,
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();

        // After 14 minutes, still locked
        $this->travel(14)->minutes();

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'locktest@hadjarexpress.test',
                'password' => $validCredential,
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();

        // After 16 minutes total (> 15 minutes), lock expires and login succeeds
        $this->travel(2)->minutes();

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'locktest@hadjarexpress.test',
                'password' => $validCredential,
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect('/panel');

        $this->assertAuthenticatedAs($mitra);
    }

    public function test_session_expires_after_two_hours_of_inactivity(): void
    {
        $mitra = User::factory()->create([
            'kode_mitra' => '002',
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $mitra->assignRole(RoleSeeder::ROLE_MITRA);

        $this->actingAs($mitra)
            ->withSession(['panel_last_activity_at' => now()->timestamp])
            ->get('/panel')
            ->assertOk();

        $this->travel(121)->minutes();

        $this->actingAs($mitra)
            ->get('/panel')
            ->assertRedirect('/panel/login');

        $this->assertGuest();
    }
}
