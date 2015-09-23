<?php

namespace Nanuly\Socialize\Contracts;

interface Factory
{
    /**
     * Get an OAuth provider implementation.
     *
     * @param  string  $driver
     * @return \Nanuly\Socialize\Contracts\Provider
     */
    public function driver($driver = null);
}
