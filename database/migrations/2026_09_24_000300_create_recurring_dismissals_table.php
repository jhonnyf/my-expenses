<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Produtos que o usuário marcou como "não recorrente" e saem da lista de Compras Recorrentes.
     */
    public function up(): void
    {
        Schema::create('recurring_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->timestamps();

            $table->unique(['user_id', 'description']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_dismissals');
    }
};
