<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('access_key', 44)->unique();
            $table->string('number', 9);
            $table->string('series', 3);
            $table->dateTime('issued_at');
            $table->enum('environment', ['production', 'staging'])->default('production');

            // Issuer
            $table->string('issuer_cnpj', 14);
            $table->string('issuer_name');
            $table->string('issuer_street')->nullable();
            $table->string('issuer_street_number', 60)->nullable();
            $table->string('issuer_neighborhood')->nullable();
            $table->string('issuer_city')->nullable();
            $table->string('issuer_state', 2)->nullable();
            $table->string('issuer_zip_code', 8)->nullable();

            // Totals
            $table->decimal('total_icms_base', 15, 2)->default(0);
            $table->decimal('total_icms', 15, 2)->default(0);
            $table->decimal('total_products', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('total_taxes', 15, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
