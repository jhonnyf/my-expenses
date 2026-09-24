<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guarda até que patamar (80/100%) o usuário já foi avisado neste mês, para não repetir o alerta a cada nota importada.
     */
    public function up(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->unsignedTinyInteger('alerted_level')->default(0)->after('amount');
            $table->char('alerted_month', 7)->nullable()->after('alerted_level');
        });
    }

    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->dropColumn(['alerted_level', 'alerted_month']);
        });
    }
};
