<?php

namespace Soeverse\Cas\Tests;

use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;
use Soeverse\Cas\CasServiceInterface;
use Soeverse\Cas\CasServiceProvider;

class CasServiceTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [CasServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('cas.base_url', 'https://cas.example.test/cas');
        $app['config']->set('cas.logout_url', null);
        $app['config']->set('cas.version', '2.0');
    }

    public function test_it_builds_login_and_logout_urls(): void
    {
        $service = $this->app->make(CasServiceInterface::class);

        self::assertSame(
            'https://cas.example.test/cas/login?service=https%3A%2F%2Fapp.example.test%2Fsso%2Flogin',
            $service->getLoginUrl('https://app.example.test/sso/login')
        );
        self::assertSame(
            'https://cas.example.test/cas/logout?service=https%3A%2F%2Fapp.example.test%2F',
            $service->getLogoutUrl('https://app.example.test/')
        );
    }

    public function test_it_parses_namespaced_success_response(): void
    {
        Http::fake([
            'cas.example.test/*' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <cas:serviceResponse xmlns:cas="http://www.yale.edu/tp/cas">
                    <cas:authenticationSuccess>
                        <cas:user>user@example.com</cas:user>
                        <cas:attributes>
                            <cas:role>admin</cas:role>
                        </cas:attributes>
                    </cas:authenticationSuccess>
                </cas:serviceResponse>
                XML, 200),
        ]);

        $result = $this->app->make(CasServiceInterface::class)
            ->validateTicket('ST-123456-ticket', 'https://app.example.test/sso/login');

        self::assertSame('user@example.com', $result['user']);
        self::assertSame(['role' => 'admin'], $result['attributes']);
    }

    public function test_it_parses_success_response_without_attributes(): void
    {
        Http::fake([
            'cas.example.test/*' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <cas:serviceResponse xmlns:cas="http://www.yale.edu/tp/cas">
                    <cas:authenticationSuccess>
                        <cas:user>user@example.com</cas:user>
                    </cas:authenticationSuccess>
                </cas:serviceResponse>
                XML, 200),
        ]);

        $result = $this->app->make(CasServiceInterface::class)
            ->validateTicket('ST-123456-ticket', 'https://app.example.test/sso/login');

        self::assertSame([
            'user' => 'user@example.com',
            'attributes' => [],
        ], $result);
    }

    public function test_it_returns_null_for_authentication_failure(): void
    {
        Http::fake([
            'cas.example.test/*' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <cas:serviceResponse xmlns:cas="http://www.yale.edu/tp/cas">
                    <cas:authenticationFailure code="INVALID_TICKET">
                        Ticket is invalid
                    </cas:authenticationFailure>
                </cas:serviceResponse>
                XML, 200),
        ]);

        $result = $this->app->make(CasServiceInterface::class)
            ->validateTicket('ST-123456-ticket', 'https://app.example.test/sso/login');

        self::assertNull($result);
    }

    public function test_it_rejects_invalid_ticket_without_requesting_cas(): void
    {
        Http::fake();

        $result = $this->app->make(CasServiceInterface::class)
            ->validateTicket('invalid-ticket', 'https://app.example.test/sso/login');

        self::assertNull($result);
        Http::assertNothingSent();
    }

    public function test_it_restores_libxml_error_mode(): void
    {
        $previous = libxml_use_internal_errors(false);

        try {
            Http::fake([
                'cas.example.test/*' => Http::response('<not-valid', 200),
            ]);

            $this->app->make(CasServiceInterface::class)
                ->validateTicket('ST-123456-ticket', 'https://app.example.test/sso/login');

            self::assertFalse(libxml_use_internal_errors());
        } finally {
            libxml_use_internal_errors($previous);
        }
    }
}
