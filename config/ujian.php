<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pembuat Soal Configuration
    |--------------------------------------------------------------------------
    */
    'pembuat_soal' => [
        // Jika true, soal yang dibuat oleh pembuat_soal akan otomatis is_verified=false
        // dan perlu diverifikasi oleh admin dinas sebelum bisa dipakai di paket ujian.
        'require_verification' => env('PEMBUAT_SOAL_REQUIRE_VERIFICATION', true),
    ],

];
