<?php

namespace App\Providers;

use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Support\Facades\Route;
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
        RedirectIfAuthenticated::redirectUsing(
            fn () => Route::has('admin.reports.index') ? route('admin.reports.index') : '/admin'
        );

        \Illuminate\Pagination\Paginator::useTailwind();

        $this->ensureStorageDirectories();
    }

    protected function ensureStorageDirectories(): void
    {
        $dirs = [
            storage_path('app/public/electricity-reports'),
            storage_path('framework/sessions'),
            storage_path('framework/cache/data'),
            storage_path('logs'),
        ];

        foreach ($dirs as $dir) {
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
        }
    }
}
