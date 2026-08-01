<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * LIKE '%termo%' nunca usa índice (wildcard à esquerda) — cada busca de produto/
     * loja escaneia a tabela inteira. FULLTEXT permite busca por palavra com índice
     * real, essencial pra escalar a busca global além do volume de teste atual.
     *
     * SQLite (usado nos testes automatizados, ver phpunit.xml) não suporta FULLTEXT —
     * nesse driver o índice simplesmente não é criado, e App\Support\FullTextQuery
     * cai automaticamente pro LIKE de antes.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('invoices_items', function (Blueprint $table) {
            $table->fullText('description');
        });

        Schema::table('product_aliases', function (Blueprint $table) {
            $table->fullText('canonical_name');
        });

        Schema::table('issuers', function (Blueprint $table) {
            $table->fullText('name');
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('invoices_items', function (Blueprint $table) {
            $table->dropFullText(['description']);
        });

        Schema::table('product_aliases', function (Blueprint $table) {
            $table->dropFullText(['canonical_name']);
        });

        Schema::table('issuers', function (Blueprint $table) {
            $table->dropFullText(['name']);
        });
    }
};
