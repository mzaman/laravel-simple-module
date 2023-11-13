<?php

namespace LaravelSimpleModule;

use Illuminate\Support\Facades\Facade;

/**
 * @see \LaravelSimpleModule\LaravelSimpleModule
 */
class LaravelSimpleModuleFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'laravel-simple-module';
    }
}
