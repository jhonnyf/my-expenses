<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EncryptUserProfileDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_encrypts_plain_text_documents_and_fills_hash_columns(): void
    {
        $user = User::factory()->create();
        $profileId = DB::table('users_profiles')->insertGetId([
            'user_id' => $user->id,
            'cpf' => '11122233344',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('user-profiles:encrypt-documents')->assertExitCode(0);

        $row = DB::table('users_profiles')->where('id', $profileId)->first();

        $this->assertSame('11122233344', Crypt::decryptString($row->cpf));
        $this->assertSame(hash_hmac('sha256', '11122233344', config('app.key')), $row->cpf_hash);
    }

    public function test_dry_run_does_not_write_changes(): void
    {
        $user = User::factory()->create();
        $profileId = DB::table('users_profiles')->insertGetId([
            'user_id' => $user->id,
            'cpf' => '11122233344',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('user-profiles:encrypt-documents', ['--dry-run' => true])->assertExitCode(0);

        $this->assertSame('11122233344', DB::table('users_profiles')->where('id', $profileId)->value('cpf'));
    }

    public function test_is_idempotent_when_run_twice(): void
    {
        $user = User::factory()->create();
        $profileId = DB::table('users_profiles')->insertGetId([
            'user_id' => $user->id,
            'cpf' => '11122233344',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('user-profiles:encrypt-documents')->assertExitCode(0);
        $firstPass = DB::table('users_profiles')->where('id', $profileId)->value('cpf');

        $this->artisan('user-profiles:encrypt-documents')->assertExitCode(0);
        $secondPass = DB::table('users_profiles')->where('id', $profileId)->value('cpf');

        $this->assertSame($firstPass, $secondPass);
        $this->assertSame('11122233344', Crypt::decryptString($secondPass));
    }
}
