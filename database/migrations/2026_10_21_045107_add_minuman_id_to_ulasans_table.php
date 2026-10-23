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
        Schema::table('ulasans', function (Blueprint $table) {
            $table->foreignId('minuman_id')
                ->nullable()
                ->after('makanan_id')
                ->constrained('minumans')
                ->cascadeOnDelete();
        });

        Schema::table('ulasans', function (Blueprint $table): void {
            $table->foreignId('makanan_id')->nullable()->change();
        });

        Schema::table('ulasans', function (Blueprint $table): void {
            $table->foreignId('makanan_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ulasans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('minuman_id');
        });

        Schema::table('ulasans', function (Blueprint $table): void {
            $table->foreignId('makanan_id')->nullable(false)->change();
        });
    }
};
