<?php

namespace Sprocketbox\Eloquence\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use Sprocketbox\Eloquence\IdentityManager;
use Sprocketbox\Eloquence\ModelIdentity;

/**
 * Eloquence Facade
 *
 * @method static IdentityManager getInstance()
 * @method static bool hasIdentity(ModelIdentity $identity)
 * @method static Model|null getIdentity(ModelIdentity $identity)
 * @method static IdentityManager storeIdentity(ModelIdentity $identity, Model $model)
 * @method static IdentityManager removeIdentity(ModelIdentity $identity)
 * @method static array allIdentities()
 *
 * @package Sprocketbox\Eloquence\Facades
 */
class Eloquence extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'eloquence';
    }
}