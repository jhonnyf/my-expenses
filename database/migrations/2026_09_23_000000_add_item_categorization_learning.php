<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices_items', function (Blueprint $table) {
            $table->string('categorization_source', 10)->nullable()->after('category_id');
        });

        Schema::create('item_category_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('description_key', 191);
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('source', 10);
            $table->timestamps();

            $table->unique(['user_id', 'description_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_category_rules');

        Schema::table('invoices_items', function (Blueprint $table) {
            $table->dropColumn('categorization_source');
        });
    }
};
