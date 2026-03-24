<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pembuat Soal Configuration
    |--------------------------------------------------------------------------
    */
    'pembuat_soal' => [
        'require_verification' => env('PEMBUAT_SOAL_REQUIRE_VERIFICATION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Anti-Cheat Configuration
    |--------------------------------------------------------------------------
    */
    'anti_cheat' => [
        'max_violations' => (int) env('UJIAN_MAX_VIOLATIONS', 3),
    ],

];