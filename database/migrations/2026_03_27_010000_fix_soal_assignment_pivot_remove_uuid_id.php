<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->safeDropColumn('kategori_soal_user', 'id');
        $this->safeDropColumn('soal_user', 'id');
    }

    public function down(): void
    {
        Schema::table('kategori_soal_user', function (Blueprint $table) {
            $table->uuid('id')->first();
        });

        Schema::table('soal_user', function (Blueprint $table) {
            $table->uuid('id')->first();
        });
    }

    private function safeDropColumn(string $table, string $column): void
    {
        $exists = DB::selectOne(
            "SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            [$table, $column]
        );

        if ($exists && $exists->cnt > 0) {
            Schema::table($table, function (Blueprint $table) use ($column) {
                $table->dropColumn($column);
            });
        }
    }
};
