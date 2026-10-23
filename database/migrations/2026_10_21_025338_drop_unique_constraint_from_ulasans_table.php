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
        Schema::table('ulasans', function (Blueprint $table): void {
            $table->index('user_id');
        });

        Schema::table('ulasans', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'makanan_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ulasans', function (Blueprint $table): void {
            $table->dropIndex(['user_id']);
        });

        Schema::table('ulasans', function (Blueprint $table): void {
            $table->unique(['user_id', 'makanan_id']);
        });
    }
};
