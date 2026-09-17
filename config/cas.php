<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CAS Server Hostname
    |--------------------------------------------------------------------------
    |
    | Nama domain dari CAS server (tanpa https:// atau path /cas).
    | Contoh: sso.undiksha.ac.id
    |
    */
    'hostname' => env('CAS_HOSTNAME', 'sso.undiksha.ac.id'),

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
