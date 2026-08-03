<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorite_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('canonical_name');
            $table->string('unit')->nullable();
            $table->decimal('last_notified_price', 10, 2)->nullable();
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'canonical_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorite_products');
    }
};
