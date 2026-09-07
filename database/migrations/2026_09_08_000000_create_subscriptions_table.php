<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('plan')->default('free');
            $table->string('status')->default('active');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('canceled_at')->nullable();

            // Preparação para integração futura com gateway de pagamento — nullable, sem uso ainda.
            $table->string('gateway')->nullable();
            $table->string('gateway_customer_id')->nullable();
            $table->string('gateway_subscription_id')->nullable();
            $table->string('gateway_status')->nullable();

            $table->timestamps();
        });

        $this->backfillExistingUsersToFreePlan();
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }

    /**
     * Todo usuário já cadastrado antes deste módulo existir precisa ficar
     * com uma assinatura Grátis ativa, sem intervenção manual.
     */
    private function backfillExistingUsersToFreePlan(): void
    {
        $now = now();

        DB::table('users')->select('id')->orderBy('id')->chunkById(500, function ($users) use ($now) {
            $rows = $users->map(fn ($user) => [
                'user_id' => $user->id,
                'plan' => 'free',
                'status' => 'active',
                'started_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            DB::table('subscriptions')->insertOrIgnore($rows);
        });
    }
};
