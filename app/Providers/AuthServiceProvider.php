<?php

namespace App\Providers;

use Laravel\Passport\Passport;
use Illuminate\Support\Facades\Gate;
use App\Policies\UserPolicy;
use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

      
        Passport::tokensCan([
            'admin' => 'Admin Access',
            'user' => 'User Access',
            'super_admin' => 'Super Admin Access',
            'manager' => 'Manager Access',
        ]);

        Passport::setDefaultScope([
            'user',
        ]);

        // Passport routes if needed
        // Passport::routes();
    }
}