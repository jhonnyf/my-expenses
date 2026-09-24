<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A comparação de preços filtra os itens (de todos os usuários) por descrição exata; sem índice comum cada
     * consulta varria a tabela inteira (o FULLTEXT existente não serve para igualdade).
     */
    public function up(): void
    {
        Schema::table('invoices_items', function (Blueprint $table) {
            $table->index('description', 'invoices_items_description_index');
        });
    }

    public function down(): void
    {
        Schema::table('invoices_items', function (Blueprint $table) {
            $table->dropIndex('invoices_items_description_index');
        });
    }
};
