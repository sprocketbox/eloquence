<?php

namespace Sprocketbox\Package;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function boot(): void
    {
        $this->publishConfig();
    }

    private function publishConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../config/config.php' => config_path('package.php'),
        ], 'config');
    }
}