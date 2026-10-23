<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ulasans', function ($table): void {
            $table->decimal('rating', 3, 1)->change();
        });
    }

    public function down(): void
    {
        Schema::table('ulasans', function ($table): void {
            $table->integer('rating')->change();
        });
    }
};
