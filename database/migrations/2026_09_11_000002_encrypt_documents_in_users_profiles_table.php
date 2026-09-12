<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * cpf/cnpj passam a ser gravados criptografados (cast `encrypted` no model),
     * cuja saída tem tamanho variável e não determinístico — por isso a coluna
     * precisa virar TEXT e o índice único original não pode mais viver nela.
     * cpf_hash/cnpj_hash (HMAC-SHA256, determinístico) assumem o papel de
     * checagem de duplicidade que o UNIQUE em texto puro cumpria antes.
     */
    public function up(): void
    {
        Schema::table('users_profiles', function (Blueprint $table) {
            $table->dropUnique('users_profiles_cpf_unique');
            $table->dropUnique('users_profiles_cnpj_unique');
            $table->text('cpf')->nullable()->change();
            $table->text('cnpj')->nullable()->change();
            $table->string('cpf_hash', 64)->nullable()->unique()->after('cpf');
            $table->string('cnpj_hash', 64)->nullable()->unique()->after('cnpj');
        });
    }

    public function down(): void
    {
        Schema::table('users_profiles', function (Blueprint $table) {
            $table->dropUnique(['cpf_hash']);
            $table->dropUnique(['cnpj_hash']);
            $table->dropColumn(['cpf_hash', 'cnpj_hash']);
            $table->string('cpf', 11)->nullable()->unique()->change();
            $table->string('cnpj', 14)->nullable()->unique()->change();
        });
    }
};
