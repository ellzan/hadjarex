<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Auth\Pages\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PhaseZeroFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_public_home_and_login_pages_can_be_opened(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Hadjar Express')
            ->assertSee('Login');

        $this->get('/brand/hsc-logo.png')
            ->assertOk();

        $this->get('/panel/login')
            ->assertOk()
            ->assertSee('Hadjar Express');
    }

    public function test_role_seeder_only_creates_mitra_and_super_admin_roles(): void
    {
        $roles = Role::pluck('name')->sort()->values()->all();

        $this->assertSame(['mitra', 'super_admin'], $roles);
        $this->assertDatabaseMissing('roles', ['name' => 'admin']);
    }

    public function test_create_super_admin_command_creates_super_admin_with_kode_001(): void
    {
        $credential = Str::random(16);

        $this->artisan('app:create-super-admin')
            ->expectsQuestion('Email', 'sa@hadjarexpress.test')
            ->expectsQuestion('Password', $credential)
            ->assertSuccessful();

        $user = User::where('email', 'sa@hadjarexpress.test')->first();

        $this->assertNotNull($user);
        $this->assertSame('001', $user->kode_mitra);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->hasRole(RoleSeeder::ROLE_SUPER_ADMIN));
    }

    public function test_active_user_can_login_to_panel(): void
    {
        $credential = Str::random(16);

        $user = User::factory()->create([
            'email' => 'mitra@hadjarexpress.test',
            'password' => $credential,
            'kode_mitra' => '002',
            'is_active' => true,
        ]);
        $user->assignRole(RoleSeeder::ROLE_MITRA);

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'mitra@hadjarexpress.test',
                'password' => $credential,
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect('/panel');

        $this->assertAuthenticatedAs($user);

        $this->actingAs($user)
            ->get('/panel')
            ->assertOk()
            ->assertSee('Mitra (HSC 002)');
    }

    public function test_mitra_cannot_open_super_admin_page(): void
    {
        $superAdmin = User::factory()->create([
            'kode_mitra' => '001',
            'is_active' => true,
        ]);
        $superAdmin->assignRole(RoleSeeder::ROLE_SUPER_ADMIN);

        $mitra = User::factory()->create([
            'kode_mitra' => '002',
            'is_active' => true,
        ]);
        $mitra->assignRole(RoleSeeder::ROLE_MITRA);

        $this->actingAs($superAdmin)
            ->get('/panel/users')
            ->assertOk();

        $this->actingAs($mitra)
            ->get('/panel/users')
            ->assertForbidden();
    }

    public function test_inactive_account_is_rejected_at_login_and_panel_access(): void
    {
        $credential = Str::random(16);

        $inactiveUser = User::factory()->inactive()->create([
            'email' => 'nonaktif@hadjarexpress.test',
            'password' => $credential,
            'kode_mitra' => '003',
        ]);
        $inactiveUser->assignRole(RoleSeeder::ROLE_MITRA);

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'nonaktif@hadjarexpress.test',
                'password' => $credential,
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();

        $this->actingAs($inactiveUser)
            ->get('/panel')
            ->assertForbidden();
    }
}
