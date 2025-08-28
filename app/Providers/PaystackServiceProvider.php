<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Paystack\Paystack;

class PaystackServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(Paystack::class, function ($app) {
            return new Paystack(config('services.paystack.secret_key'));
        });
    }

    public function boot()
    {
        //
    }
}