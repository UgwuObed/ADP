<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use OpenAI\Laravel\Facades\OpenAI;
use App\Services\AIService;
use OpenAI\Client as OpenAIClient;
use OpenAI\Client;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register()
    {
        // // Register the AIService and inject the OpenAI client
        // $this->app->singleton(AIService::class, function ($app) {
        //     return new AIService(OpenAI::client());
        // });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        //
    }
}
