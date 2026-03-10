<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE soal MODIFY COLUMN tipe_soal ENUM('pg','pg_kompleks','menjodohkan','isian','essay','benar_salah') NOT NULL DEFAULT 'pg'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE soal MODIFY COLUMN tipe_soal ENUM('pg','pg_kompleks','menjodohkan','isian','essay') NOT NULL DEFAULT 'pg'");
    }
};
