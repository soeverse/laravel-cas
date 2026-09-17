# Soeverse Laravel CAS

Package otentikasi CAS native dan mandiri untuk ekosistem aplikasi Undiksha, dibuat spesifik agar stabil, ringan, dan tidak bergantung pada package usang.

## Instalasi

Tambahkan package ini ke proyek Laravel Anda melalui Composer:

```bash
composer require soeverse/laravel-cas
```

### Konfigurasi

Publish file konfigurasi agar Anda bisa menyesuaikan pengaturan:

```bash
php artisan vendor:publish --tag="cas-config"
```

Perintah di atas akan menyalin file konfigurasi ke `config/cas.php`. Pastikan Anda menambahkan environment variables berikut di file `.env` Anda:

````env
CAS_HOSTNAME=sso.undiksha.ac.id
CAS_VERSION=2.0
CAS_LOGOUT_URL=https://sso.undiksha.ac.id/cas/logout
CAS_SSL_VERIFY=true
CAS_TIMEOUT=10
## Penggunaan Secara Rigid (Praktik Terbaik)

Untuk menggunakan package ini secara optimal, Anda perlu memahami bagaimana siklus autentikasi CAS bekerja:

### Siklus Autentikasi CAS (Alur Implementasi)

1. **Akses Endpoint Login (Lokal)**: Pengguna mengakses URL login aplikasi Anda (misal: `/sso/login`).
2. **Redirect ke CAS Server**: `CasService` mengecek apakah ada query parameter `?ticket=`. Jika tidak ada, `CasService` akan mengarahkan (redirect) pengguna ke halaman login terpusat SSO (CAS Server).
3. **Autentikasi di SSO**: Pengguna memasukkan *username* dan *password* di halaman SSO. Jika berhasil, CAS Server akan mengarahkan pengguna **kembali** ke URL login aplikasi Anda, namun kali ini dengan membawa *Service Ticket* di URL (misal: `/sso/login?ticket=ST-12345...`).
4. **Validasi Tiket**: Aplikasi Anda mendeteksi adanya `?ticket=`. Controller kemudian menggunakan `CasService` untuk melakukan *HTTP request* (server-to-server) ke CAS Server untuk memvalidasi tiket tersebut.
5. **Pembuatan Sesi (Lokal)**: Jika tiket valid, CAS Server akan merespon dengan data *user* (email). Aplikasi lokal Anda kemudian bertugas mencocokkan email tersebut ke database lokal (`users`) dan membuat sesi login Laravel (via `Auth::login()`).

Berikut adalah implementasi dari siklus di atas:

### 1. Daftarkan Route

Pada file `routes/web.php`, buat route untuk login dan logout:

```php
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/sso/login', [AuthController::class, 'ssoLogin'])->name('sso.login');
Route::post('/sso/logout', [AuthController::class, 'ssoLogout'])->name('sso.logout');
````

### 2. Implementasi Controller

Berikut adalah contoh komprehensif implementasi `AuthController.php` menggunakan Dependency Injection:

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
     * Handle proses Login SSO.
     */
    public function ssoLogin(Request $request, CasServiceInterface $cas)
    {
        // 1. Tentukan URL callback (URL route ini sendiri)
        $serviceUrl = route('sso.login');

        // 2. Jika tidak ada tiket (belum login di CAS Server), redirect ke CAS
        if (!$request->has('ticket')) {
            return redirect()->away($cas->getLoginUrl($serviceUrl));
        }

        // 3. Jika ada tiket, lakukan validasi ke CAS Server
        $ticket = $request->query('ticket');
        $authData = $cas->validateTicket($ticket, $serviceUrl);

        // 4. Handle kegagalan validasi tiket
        if (!$authData || empty($authData['user'])) {
            return redirect()->route('login')->with('error', 'Tiket SSO tidak valid atau sudah kedaluwarsa.');
        }

        // 5. Normalisasi email/username untuk keamanan (mencegah spasi berlebih)
        $email = strtolower(trim($authData['user']));

        // 6. Cari user di database lokal berdasarkan email
        $user = User::where('email', $email)->first();

        if (!$user) {
            return redirect()->route('login')->with('error', "Akun dengan email {$email} tidak ditemukan di sistem lokal.");
        }

        // 7. Login pengguna secara lokal (Laravel Session)
        Auth::login($user);

        // Tambahan: Menyimpan flag bahwa user login melalui CAS (jika diperlukan untuk proses logout)
        $request->session()->put('is_native_cas', true);

        // 8. Redirect ke halaman dashboard lokal
        return redirect()->intended('/dashboard');
    }

    /**
     * Handle proses Logout SSO.
     */
    public function ssoLogout(Request $request, CasServiceInterface $cas)
    {
        $isNativeCas = $request->session()->get('is_native_cas', false);

        // 1. Hapus sesi lokal Laravel
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // 2. Jika user sebelumnya login menggunakan SSO CAS, redirect ke CAS Logout
        if ($isNativeCas) {
            // Setelah logout dari CAS, arahkan kembali ke halaman utama aplikasi
            return redirect()->away($cas->getLogoutUrl(url('/')));
        }

        // 3. Jika login non-SSO, langsung kembali ke beranda lokal
        return redirect('/');
    }
}
```

### 3. Pemanggilan via Facade (Opsional)

Jika Anda lebih menyukai gaya pemanggilan statis ala Laravel, Anda juga dapat menggunakan Facade `Cas`:

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

## API Reference

### `getLoginUrl(string $serviceUrl): string`

Menghasilkan URL lengkap untuk mengarahkan pengguna ke halaman login SSO.

- `$serviceUrl` adalah URL callback aplikasi Anda setelah proses autentikasi berhasil (tempat CAS akan melempar parameter `?ticket=...`).

### `validateTicket(string $ticket, string $serviceUrl): ?array`

Melakukan _server-to-server validation_ ke CAS Server menggunakan HTTP Client.

- Mengembalikan _array_ berisi kunci `user` (email) dan `attributes` jika validasi sukses.
- Mengembalikan `null` jika validasi gagal atau format tiket tidak dikenali.

### `getLogoutUrl(?string $serviceUrl = null): string`

Menghasilkan URL lengkap untuk mengarahkan pengguna ke halaman logout SSO.

- Jika `$serviceUrl` diberikan, CAS akan diarahkan kembali ke URL tersebut setelah logout berhasil (opsional).
