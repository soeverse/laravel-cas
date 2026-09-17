<?php

namespace Soeverse\Cas;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CasService implements CasServiceInterface
{
    protected string $baseUrl;
    protected string $logoutUrl;
    protected string $version;
    protected bool $verifySsl;
    protected int $timeout;
    protected int $connectTimeout;

    public function __construct()
    {
        $host = config('cas.hostname', 'sso.undiksha.ac.id');
        $uri  = '/cas';

        // Base URL: https://sso.undiksha.ac.id/cas
        $this->baseUrl = "https://{$host}" . rtrim($uri, '/');

        // Logout URL
        $this->logoutUrl = config('cas.logout_url') ?: "{$this->baseUrl}/logout";

        // Versi CAS protokol
        $this->version = config('cas.version', '2.0');

        // SSL verification & Timeouts
        $this->verifySsl = (bool) config('cas.ssl_verify', true);
        $this->timeout = (int) config('cas.timeout', 10);
        $this->connectTimeout = (int) config('cas.connect_timeout', 3);
    }

    /**
     * URL untuk mengarahkan pengguna login ke CAS Server
     */
    public function getLoginUrl(string $serviceUrl): string
    {
        return $this->baseUrl . '/login?' . http_build_query([
            'service' => $serviceUrl
        ]);
    }

    /**
     * URL untuk logout dari CAS Server
     */
    public function getLogoutUrl(?string $serviceUrl = null): string
    {
        $params = $serviceUrl ? ['service' => $serviceUrl] : [];
        return $this->logoutUrl . ($params ? '?' . http_build_query($params) : '');
    }

    /**
     * Validasi tiket ST (Service Ticket) secara server-to-server ke CAS Server
     */
    public function validateTicket(string $ticket, string $serviceUrl): ?array
    {
        // Validasi format tiket CAS standar (try → rollback jika ternyata format berbeda)
        if (!preg_match('/^(ST|PT)-\d+-[A-Za-z0-9._-]+$/', $ticket)) {
            Log::warning('CAS: Format tiket tidak dikenali, diabaikan.', [
                'ticket_prefix' => substr($ticket, 0, 30),
            ]);
            return null;
        }

        $endpoint = ($this->version === '3.0')
            ? "{$this->baseUrl}/p3/serviceValidate"
            : "{$this->baseUrl}/serviceValidate";

        try {
            $response = Http::retry(1, 500)->withOptions([
                'verify'          => $this->verifySsl,
                'timeout'         => $this->timeout,
                'connect_timeout' => $this->connectTimeout,
            ])->get($endpoint, [
                'service' => $serviceUrl,
                'ticket'  => $ticket,
            ]);

            if (!$response->successful()) {
                Log::error('CAS validation HTTP failed: ' . $response->status(), [
                    'body' => $response->body()
                ]);
                return null;
            }

            return $this->parseCasResponse($response->body());
        } catch (\Throwable $e) {
            Log::error('CAS Exception: ' . $e->getMessage(), [
                'class' => get_class($e),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);
            return null;
        }
    }

    /**
     * Parsing XML Response resmi dari CAS Server
     */
    protected function parseCasResponse(string $xmlString): ?array
    {
        // Parse XML dengan libxml error capture, tanpa @ suppressor
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', 0, 'cas', true);

        if ($xml === false) {
            // Fallback tanpa namespace
            $xml = simplexml_load_string($xmlString);
            if ($xml === false) {
                $errors = array_map(fn($e) => trim($e->message), libxml_get_errors());
                libxml_clear_errors();
                Log::error('CAS parse error: XML tidak valid', [
                    'libxml_errors' => $errors,
                    'raw_preview'   => substr($xmlString, 0, 300),
                ]);
                return null;
            }
        }
        libxml_clear_errors();

        // Jika Autentikasi Berhasil
        if (isset($xml->authenticationSuccess)) {
            $user = (string) $xml->authenticationSuccess->user;
            $attributes = [];

            // Ambil atribut tambahan jika CAS server mengirimkannya (CAS 3.0 / SAML)
            if (isset($xml->authenticationSuccess->attributes)) {
                foreach ($xml->authenticationSuccess->attributes->children('cas', true) as $key => $value) {
                    $attributes[$key] = (string) $value;
                }
            }

            return [
                'user' => $user, // Email atau username pengguna
                'attributes' => $attributes,
            ];
        }

        // Jika Tiket Invalid / Expired
        if (isset($xml->authenticationFailure)) {
            Log::warning('CAS Auth Failed: ' . (string) $xml->authenticationFailure);
        }

        return null;
    }
}
