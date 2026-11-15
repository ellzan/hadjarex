<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (config('app.name') === 'Laravel') {
            config(['app.name' => 'Hadjar Express']);
        }

        Gate::policy(User::class, UserPolicy::class);
    }
}
