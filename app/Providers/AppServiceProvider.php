<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider; 
use App\Repositories\TransactionRepository;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind the UserRepository interface to its implementation
        $this->app->bind('App\Domain\Repositories\UserRepositoryInterface', 'App\Repositories\UserRepository');
        $this->app->bind('App\Domain\Repositories\TransactionRepositoryInterface', 'App\Repositories\TransactionRepository');
        
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
