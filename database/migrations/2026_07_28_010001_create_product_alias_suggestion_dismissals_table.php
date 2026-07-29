<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_alias_suggestion_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('description_a');
            $table->string('description_b');
            $table->timestamps();

            $table->unique(['user_id', 'description_a', 'description_b'], 'product_alias_dismissals_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_alias_suggestion_dismissals');
    }
};
