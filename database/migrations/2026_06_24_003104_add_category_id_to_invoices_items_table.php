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
        Schema::table('invoices_items', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('total_price')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices_items', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });
    }
};
