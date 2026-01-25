<?php

namespace App\Providers;

use App\Models\Aviso;
use App\Models\User;
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
        Gate::define('avisos.manage', fn (User $user) => true);
        Gate::define('avisos.update', fn (User $user, Aviso $aviso) => true);
        Gate::define('avisos.delete', fn (User $user, Aviso $aviso) => true);
    }
}
