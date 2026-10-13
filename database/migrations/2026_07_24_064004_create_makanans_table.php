<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('makanans', function (Blueprint $table) {
            $table->id();
            $table->string('nama_makanan', 150);
            $table->foreignId('kategori_id')->constrained('kategoris')->onDelete('cascade');
            $table->decimal('harga', 10, 2);
            $table->integer('stok')->default(0);
            $table->text('deskripsi')->nullable();
            $table->timestamps();

            $table->index('nama_makanan');
            $table->index(['kategori_id', 'harga']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('makanans');
    }
};
