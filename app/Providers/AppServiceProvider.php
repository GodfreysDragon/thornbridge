<?php

namespace App\Providers;

use Anthropic\Client;
use App\Ai\ClaudeNpcBrain;
use App\Ai\Contracts\NpcBrain;
use App\Ai\ScriptedNpcBrain;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(NpcBrain::class, fn () => match (true) {
            config('ai.driver') === 'scripted', blank(config('ai.anthropic.api_key')) => new ScriptedNpcBrain,
            default => new ClaudeNpcBrain(
                new Client(apiKey: config('ai.anthropic.api_key')),
                config('ai.anthropic.model'),
            ),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();

        $this->configureRateLimiting();
    }

    /**
     * Per-IP limits on the endpoints that can spend tokens.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('npc', fn (Request $request) => Limit::perMinute(config('ai.rate_limits.turns_per_minute'))->by($request->ip()));
        RateLimiter::for('new-game', fn (Request $request) => Limit::perHour(config('ai.rate_limits.games_per_hour'))->by($request->ip()));
    }
}
