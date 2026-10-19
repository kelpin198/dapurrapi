<?php

namespace App\Providers;

use App\Models\User;
use App\Repositories\Interfaces\KategorisRepository;
use App\Repositories\Interfaces\KategorisRepositoryInterface;
use App\Repositories\Interfaces\MakanansRepository;
use App\Repositories\Interfaces\MakanansRepositoryInterface;
use App\Repositories\Interfaces\TransaksisRepository;
use App\Repositories\Interfaces\TransaksisRepositoryInterface;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MakanansRepositoryInterface::class, MakanansRepository::class);
        $this->app->bind(KategorisRepositoryInterface::class, KategorisRepository::class);
        $this->app->bind(TransaksisRepositoryInterface::class, TransaksisRepository::class);
    }

    public function boot(): void
    {
        Gate::define('manage-catalog', fn (User $user): bool => $user->isAdmin());
        Gate::define('customer-access', fn (User $user): bool => ! $user->isAdmin());
    }
}
