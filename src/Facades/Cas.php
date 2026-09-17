<?php

namespace Soeverse\Cas\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string getLoginUrl(string $serviceUrl)
 * @method static string getLogoutUrl(?string $serviceUrl = null)
 * @method static array|null validateTicket(string $ticket, string $serviceUrl)
 *
 * @see \Soeverse\Cas\CasService
 */
class Cas extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'cas';
    }
}
