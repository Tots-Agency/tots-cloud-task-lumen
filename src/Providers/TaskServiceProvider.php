<?php

namespace Tots\CloudTask\Providers;

use Illuminate\Support\ServiceProvider;
use Tots\CloudTask\Services\TaskService;

class TaskServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Register role singleton
        $this->app->singleton(TaskService::class, function ($app) {
            return new TaskService(config('task'));
        });
    }

    /**
     *
     * @return void
     */
    public function boot()
    {
        
    }
}
