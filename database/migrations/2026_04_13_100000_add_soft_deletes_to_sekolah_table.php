<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sekolah', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Change peserta FK from cascadeOnDelete to restrictOnDelete
        // so soft-deleted sekolah doesn't hard-delete peserta records.
        // The Sekolah model's deleting event handles proper soft-cascade.
        Schema::table('peserta', function (Blueprint $table) {
            $table->dropForeign(['sekolah_id']);
            $table->foreign('sekolah_id')
                ->references('id')->on('sekolah')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('peserta', function (Blueprint $table) {
            $table->dropForeign(['sekolah_id']);
            $table->foreign('sekolah_id')
                ->references('id')->on('sekolah')
                ->cascadeOnDelete();
        });

        Schema::table('sekolah', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
