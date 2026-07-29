<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_aliases', function (Blueprint $table) {
            $table->index('description');
        });
    }

    public function down(): void
    {
        Schema::table('product_aliases', function (Blueprint $table) {
            $table->dropIndex(['description']);
        });
    }
};
