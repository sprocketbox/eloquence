<?php

namespace Sprocketbox\Eloquence;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $this->app->bind(IdentityManager::class, fn() => IdentityManager::getInstance(), true);
        $this->app->alias(IdentityManager::class, 'eloquence');
    }
}