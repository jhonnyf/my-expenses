<?php

namespace Tests\Unit\Models;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_cpf_is_encrypted_at_rest_but_readable_through_the_model(): void
    {
        $profile = UserProfile::factory()->for(User::factory())->create(['cpf' => '11122233344']);

        $raw = DB::table('users_profiles')->where('id', $profile->id)->value('cpf');

        $this->assertNotSame('11122233344', $raw);
        $this->assertSame('11122233344', $profile->fresh()->cpf);
    }

    public function test_saving_cpf_computes_deterministic_hash_for_deduplication(): void
    {
        $profile = UserProfile::factory()->for(User::factory())->create(['cpf' => '11122233344']);

        $expectedHash = hash_hmac('sha256', '11122233344', config('app.key'));

        $this->assertSame($expectedHash, DB::table('users_profiles')->where('id', $profile->id)->value('cpf_hash'));
    }

    public function test_two_profiles_with_the_same_cpf_collide_on_cpf_hash(): void
    {
        UserProfile::factory()->for(User::factory())->create(['cpf' => '11122233344']);
        $second = UserProfile::factory()->for(User::factory())->make(['cpf' => '11122233344']);

        $this->expectException(QueryException::class);

        $second->save();
    }

    public function test_null_document_keeps_hash_null(): void
    {
        $profile = UserProfile::factory()->for(User::factory())->create(['cpf' => null, 'cnpj' => null]);

        $this->assertNull(DB::table('users_profiles')->where('id', $profile->id)->value('cpf_hash'));
        $this->assertNull(DB::table('users_profiles')->where('id', $profile->id)->value('cnpj_hash'));
    }
}
