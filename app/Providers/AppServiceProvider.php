<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
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
        DB::prohibitDestructiveCommands(! $this->app->environment('testing') ||
            DB::connection()->getDriverName() !== 'pgsql' || DB::connection()->getDatabaseName() !== 'prematricula_test');

        RateLimiter::for('login', function (Request $request): array {
            $identifier = $request->is('api/*')
                ? $request->input('identifier', $request->input('correo_institucional'))
                : $request->input('correo_institucional');
            $key = hash('sha256', is_string($identifier) ? mb_strtolower(trim($identifier)) : 'invalid');

            return [
                Limit::perMinute(30)->by('ip:'.$request->ip()),
                Limit::perMinute(5)->by('identifier:'.$key.'|'.$request->ip()),
            ];
        });
    }
}
