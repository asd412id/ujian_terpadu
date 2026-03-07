<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Update users table untuk multi-role
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('peserta')->after('email');
            // role: super_admin, admin_dinas, admin_sekolah, pengawas, peserta
            $table->uuid('sekolah_id')->nullable()->after('role');
            $table->boolean('is_active')->default(true)->after('sekolah_id');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->string('avatar')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'sekolah_id', 'is_active', 'last_login_at', 'avatar']);
        });
    }
};
