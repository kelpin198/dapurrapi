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
        if (! Schema::hasColumn('ulasans', 'user_id')) {
            Schema::table('ulasans', function (Blueprint $table): void {
                $table->foreignId('user_id')
                    ->nullable()
                    ->constrained('users')
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasColumn('ulasans', 'makanan_id')) {
            Schema::table('ulasans', function (Blueprint $table): void {
                $table->foreignId('makanan_id')
                    ->nullable()
                    ->constrained('makanans')
                    ->cascadeOnDelete();
            });
        }

        Schema::table('ulasans', function (Blueprint $table): void {
            $table->text('komentar')->nullable()->change();
            $table->unique(['user_id', 'makanan_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ulasans', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'makanan_id']);
            if (Schema::hasColumn('ulasans', 'makanan_id')) {
                $table->dropConstrainedForeignId('makanan_id');
            }
            if (Schema::hasColumn('ulasans', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
            $table->text('komentar')->nullable(false)->change();
        });
    }
};
