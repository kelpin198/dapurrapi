<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('minumans', function (Blueprint $table) {
            $table->foreignId('kategori_id')->nullable()->after('nama_minuman')->constrained('kategoris');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('minumans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kategori_id');
        });
    }
};
