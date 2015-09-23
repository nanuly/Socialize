<?php

namespace Nanuly\Socialize\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Nanuly\Socialize\SocializeManager
 */
class Socialize extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'Nanuly\Socialize\Contracts\Factory';
    }
}
