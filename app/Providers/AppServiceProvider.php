<?php

namespace App\Providers;

use Anthropic\Client;
use App\Ai\ClaudeNpcBrain;
use App\Ai\Contracts\NpcBrain;
use App\Ai\ScriptedNpcBrain;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
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

        $this->configureTrustedProxies();
        $this->configureRateLimiting();
    }

    /**
     * Trust the load balancer's forwarded headers when one is configured.
     */
    protected function configureTrustedProxies(): void
    {
        $proxies = config('app.trusted_proxies');

        if (is_string($proxies) && $proxies !== '') {
            TrustProxies::at($proxies === '*' ? '*' : array_map(trim(...), explode(',', $proxies)));
        }
    }

    /**
     * Per-IP limits on the endpoints that can spend tokens.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('npc', fn (Request $request) => Limit::perMinute(config('ai.rate_limits.turns_per_minute'))->by($this->clientIp($request)));
        RateLimiter::for('new-game', fn (Request $request) => Limit::perHour(config('ai.rate_limits.games_per_hour'))->by($this->clientIp($request)));
    }

    /**
     * The visitor's address for rate limiting: the configured edge header
     * when there is one, otherwise the connecting address.
     */
    protected function clientIp(Request $request): string
    {
        $header = config('ai.rate_limits.client_ip_header');
        $forwarded = is_string($header) && $header !== '' ? $request->header($header) : null;

        return is_string($forwarded) && filter_var($forwarded, FILTER_VALIDATE_IP) ? $forwarded : (string) $request->ip();
    }
}
