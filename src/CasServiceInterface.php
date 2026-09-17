<?php

namespace Soeverse\Cas;

interface CasServiceInterface
{
    /**
     * URL untuk mengarahkan pengguna login ke CAS Server
     */
    public function getLoginUrl(string $serviceUrl): string;

    /**
     * URL untuk logout dari CAS Server
     */
    public function getLogoutUrl(?string $serviceUrl = null): string;

    /**
     * Validasi tiket ST (Service Ticket) secara server-to-server ke CAS Server
     */
    public function validateTicket(string $ticket, string $serviceUrl): ?array;
}
