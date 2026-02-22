<?php

namespace App\Providers;

use App\Events\StoryStageCompleted;
use App\Listeners\AdvancePipeline;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Event::listen(StoryStageCompleted::class, AdvancePipeline::class);
    }
}
