# Soeverse Laravel CAS

[![Latest Version on Packagist](https://img.shields.io/packagist/v/soeverse/laravel-cas.svg)](https://packagist.org/packages/soeverse/laravel-cas)
[![Total Downloads](https://img.shields.io/packagist/dt/soeverse/laravel-cas.svg)](https://packagist.org/packages/soeverse/laravel-cas)
[![Tests](https://github.com/soeverse/laravel-cas/actions/workflows/tests.yml/badge.svg)](https://github.com/soeverse/laravel-cas/actions/workflows/tests.yml)

A lightweight native CAS authentication package for Laravel with no dependency on outdated packages.

Supports Laravel 9 through 13 and PHP 8.1 or later.

## Installation

Install this package in your Laravel application using Composer:

```bash
composer require soeverse/laravel-cas
```

### Configuration

Publish the configuration file so you can customize the package settings:

```bash
php artisan vendor:publish --tag="cas-config"
```

The command above copies the configuration file to `config/cas.php`. Add the following environment variables to your `.env` file:

```env
CAS_BASE_URL=https://sso.example.com/cas
CAS_VERSION=2.0
CAS_LOGOUT_URL=https://sso.example.com/cas/logout
CAS_SSL_VERIFY=true
CAS_TIMEOUT=10
CAS_CONNECT_TIMEOUT=3
```

`CAS_HOSTNAME` remains supported for existing installations, but `CAS_BASE_URL` is recommended for new installations.

## Usage

To use this package effectively, it helps to understand the CAS authentication flow:

### CAS Authentication Flow

1. **Access the local login endpoint**: The user visits your application login URL, such as `/sso/login`.
2. **Redirect to the CAS server**: `CasService` checks for the `?ticket=` query parameter. If it is missing, `CasService` redirects the user to the central CAS login page.
3. **Authenticate with SSO**: The user enters their _username_ and _password_ on the SSO page. After successful authentication, the CAS server redirects the user back to your application login URL with a _Service Ticket_, such as `/sso/login?ticket=ST-12345...`.
4. **Validate the ticket**: Your application detects the `?ticket=` parameter. The controller uses `CasService` to validate the ticket with a server-to-server _HTTP request_ to the CAS server.
5. **Create the local session**: If the ticket is valid, the CAS server returns user data, such as an email address. Your application then matches the email against the local `users` database table and creates a Laravel session using `Auth::login()`.

The following example implements this flow:

### 1. Register the Routes

In `routes/web.php`, define routes for login and logout:

```php
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/sso/login', [AuthController::class, 'ssoLogin'])->name('sso.login');
Route::post('/sso/logout', [AuthController::class, 'ssoLogout'])->name('sso.logout');
```

### 2. Implement the Controller

The following is a complete `AuthController.php` example using dependency injection:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Soeverse\Cas\CasServiceInterface;
use App\Models\User;

class AuthController extends Controller
{
    /**
     * Handle the SSO login flow.
     */
    public function ssoLogin(Request $request, CasServiceInterface $cas)
    {
        // 1. Determine the callback URL (this route's URL)
        $serviceUrl = route('sso.login');

        // 2. If there is no ticket, redirect to the CAS server
        if (!$request->has('ticket')) {
            return redirect()->away($cas->getLoginUrl($serviceUrl));
        }

        // 3. Validate the ticket with the CAS server
        $ticket = $request->query('ticket');
        $authData = $cas->validateTicket($ticket, $serviceUrl);

        // 4. Handle ticket validation failure
        if (!$authData || empty($authData['user'])) {
            return redirect()->route('login')->with('error', 'Tiket SSO tidak valid atau sudah kedaluwarsa.');
        }

        // 5. Normalize the email or username
        $email = strtolower(trim($authData['user']));

        // 6. Find the user in the local database by email
        $user = User::where('email', $email)->first();

        if (!$user) {
            return redirect()->route('login')->with('error', "Akun dengan email {$email} tidak ditemukan di sistem lokal.");
        }

        // 7. Log the user into the local Laravel session
        Auth::login($user);

        // Optional: Store a flag indicating that the user logged in through CAS
        $request->session()->put('is_native_cas', true);

        // 8. Redirect to the local dashboard
        return redirect()->intended('/dashboard');
    }

    /**
     * Handle the SSO logout flow.
     */
    public function ssoLogout(Request $request, CasServiceInterface $cas)
    {
        $isNativeCas = $request->session()->get('is_native_cas', false);

        // 1. Clear the local Laravel session
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // 2. If the user logged in through CAS, redirect to CAS logout
        if ($isNativeCas) {
            // Return to the application homepage after CAS logout
            return redirect()->away($cas->getLogoutUrl(url('/')));
        }

        // 3. For non-SSO users, return directly to the local homepage
        return redirect('/');
    }
}
```

### 3. Use the Facade (Optional)

If you prefer Laravel-style static calls, you can also use the `Cas` facade:

```php
use Soeverse\Cas\Facades\Cas;

// ...
public function checkSsoUrl()
{
    // Cukup panggil method secara statis
    $loginUrl = Cas::getLoginUrl(route('sso.login'));
    return $loginUrl;
}
// ...
```

## Separated Frontend and Backend

This package can also be used when the frontend and Laravel backend are deployed separately. The Laravel backend must own the CAS callback because the service ticket is validated server-to-server with the CAS server.

The recommended flow is:

1. The frontend sends the user to a backend endpoint such as `/auth/cas/login`.
2. The backend redirects the user to CAS using `getLoginUrl()`.
3. CAS redirects back to a backend callback such as `/auth/cas/callback?ticket=...`.
4. The backend validates the ticket using `validateTicket()` and finds or creates the local user.
5. The backend authenticates the frontend using Laravel Sanctum, Passport, or another application-specific token/session mechanism.
6. The backend redirects the user to the frontend application.

Example backend routes:

```php
Route::get('/auth/cas/login', function (CasServiceInterface $cas) {
    $callbackUrl = config('app.url') . '/auth/cas/callback';

    return redirect()->away($cas->getLoginUrl($callbackUrl));
});

Route::get('/auth/cas/callback', function (Request $request, CasServiceInterface $cas) {
    $callbackUrl = config('app.url') . '/auth/cas/callback';
    $authData = $cas->validateTicket($request->query('ticket'), $callbackUrl);

    if (!$authData || empty($authData['user'])) {
        abort(401, 'Invalid CAS ticket.');
    }

    // Find or create the local user, then issue an application token
    // or establish the session used by the separated frontend.

    return redirect()->away('https://frontend.example.com/auth/success');
});
```

The package does not issue API tokens, configure CORS, or manage frontend sessions. Those concerns belong to the Laravel application. Do not send the CAS service ticket to the frontend or place long-lived access tokens in redirect URLs. For unrelated domains, use a short-lived, single-use authorization code and exchange it through the backend for an access token. For subdomains under the same parent domain, a secure, correctly configured cookie-based session may also be used.

## API Reference

### `getLoginUrl(string $serviceUrl): string`

Generates the complete URL for redirecting users to the SSO login page.

- `$serviceUrl` is your application callback URL after authentication succeeds. The CAS server redirects to this URL with a `?ticket=...` parameter.

### `validateTicket(string $ticket, string $serviceUrl): ?array`

Validates a ticket with the CAS server using a server-to-server HTTP request.

- Returns an _array_ containing the `user` and `attributes` keys when validation succeeds.
- Returns `null` when validation fails or the ticket format is not recognized.

### `getLogoutUrl(?string $serviceUrl = null): string`

Generates the complete URL for redirecting users to the SSO logout page.

- If `$serviceUrl` is provided, the CAS server redirects to that URL after logout (optional).

## Testing

Install the development dependencies and run the test suite:

```bash
composer install
composer test
```

## License

This package is released under the [MIT License](LICENSE).
