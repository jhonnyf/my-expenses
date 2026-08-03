<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users_profiles', function (Blueprint $table) {
            $table->string('cidade')->nullable();
            $table->string('estado', 2)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('location_suggestion_dismissed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'cidade',
                'estado',
                'latitude',
                'longitude',
                'location_suggestion_dismissed_at',
            ]);
        });
    }
};
