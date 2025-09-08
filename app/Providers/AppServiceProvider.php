<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\ProfileService;
use App\Services\TeamService;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ProfileService::class);
        $this->app->singleton(TeamService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        //
    }
}
