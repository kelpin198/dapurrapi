<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('makanans', function (Blueprint $table) {
            $table->softDeletes(); // otomatis bikin kolom deleted_at (nullable timestamp)
        });
    }

    public function down(): void
    {
        Schema::table('makanans', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
