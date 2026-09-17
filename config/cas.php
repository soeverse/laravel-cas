<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CAS Server Base URL
    |--------------------------------------------------------------------------
    |
    | URL dasar CAS server, termasuk path jika diperlukan.
    | Contoh: https://sso.example.com/cas
    |
    */
    'base_url' => env('CAS_BASE_URL'),

    // Backward-compatible fallback for existing installations.
    'hostname' => env('CAS_HOSTNAME', 'sso.example.com'),

    /*
    |--------------------------------------------------------------------------
    | CAS Protocol Version
    |--------------------------------------------------------------------------
    |
    | Versi protokol CAS yang digunakan. Umumnya '2.0' atau '3.0'.
    |
    */
    'version' => env('CAS_VERSION', '2.0'),

    /*
    |--------------------------------------------------------------------------
    | CAS Logout URL
    |--------------------------------------------------------------------------
    |
    | URL spesifik untuk logout dari CAS server.
    | Jika kosong, akan secara otomatis menggunakan https://{hostname}/cas/logout
    |
    */
    'logout_url' => env('CAS_LOGOUT_URL', null),

    /*
    |--------------------------------------------------------------------------
    | SSL Verification
    |--------------------------------------------------------------------------
    |
    | Apakah koneksi server-to-server ke CAS harus memverifikasi SSL certificate.
    | Setel ke true di lingkungan production.
    |
    */
    'ssl_verify' => (bool) env('CAS_SSL_VERIFY', true),

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeout
    |--------------------------------------------------------------------------
    |
    | Batas waktu HTTP timeout dan connect timeout dalam detik.
    |
    */
    'timeout' => (int) env('CAS_TIMEOUT', 10),
    'connect_timeout' => (int) env('CAS_CONNECT_TIMEOUT', 3),
];
