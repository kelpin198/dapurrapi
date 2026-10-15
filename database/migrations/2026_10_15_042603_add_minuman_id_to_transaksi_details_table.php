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
        Schema::table('transaksi_details', function (Blueprint $table) {
            $table->foreignId('makanan_id')->nullable()->change();
            $table->foreignId('minuman_id')->nullable()->after('makanan_id')->constrained('minumans')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaksi_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('minuman_id');
            $table->foreignId('makanan_id')->nullable(false)->change();
        });
    }
};
