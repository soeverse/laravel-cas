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
        $baseUrl = config('cas.base_url');
        if (!$baseUrl) {
            $host = config('cas.hostname', 'sso.example.com');
            $baseUrl = "https://{$host}/cas";
        }

        $this->baseUrl = rtrim($baseUrl, '/');

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
                    'endpoint' => $endpoint,
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
        $previousErrorMode = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string(
                $xmlString,
                'SimpleXMLElement',
                LIBXML_NONET,
                'cas',
                true
            );

            if ($xml === false) {
                $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NONET);
            }

            if ($xml === false) {
                $errors = array_map(fn($error) => trim($error->message), libxml_get_errors());
                Log::error('CAS parse error: XML tidak valid', [
                    'libxml_errors' => $errors,
                ]);
                return null;
            }

            $response = $xml->children('cas', true);
            $success = $response->authenticationSuccess;

            if (count($success) === 0) {
                $success = $xml->authenticationSuccess;
            }

            if (count($success) > 0) {
                $successData = $success->children('cas', true);
                $user = trim((string) ($successData?->user ?: $success->user));
                $attributes = [];
                $attributesNode = $successData?->attributes;

                if ($attributesNode !== null && count($attributesNode) > 0) {
                    $attributeNodes = $attributesNode->children('cas', true);

                    if ($attributeNodes !== null) {
                        foreach ($attributeNodes as $key => $value) {
                            $attributes[$key] = (string) $value;
                        }
                    }
                }

                return $user === '' ? null : [
                    'user' => $user,
                    'attributes' => $attributes,
                ];
            }

            $failure = $response->authenticationFailure;
            if (count($failure) === 0) {
                $failure = $xml->authenticationFailure;
            }

            if (count($failure) > 0) {
                Log::warning('CAS authentication failed.', [
                    'code' => (string) $failure['code'],
                ]);
            }

            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }
    }
}
