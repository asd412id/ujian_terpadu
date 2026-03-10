<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('peserta', function (Blueprint $table) {
            // Ubah dari VARCHAR(20) ke TEXT agar bisa menyimpan nilai encrypt()
            $table->text('password_plain')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('peserta', function (Blueprint $table) {
            $table->string('password_plain', 20)->nullable()->change();
        });
    }
};
