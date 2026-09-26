<?php

namespace Tests\Feature\Actions;

use App\Actions\FindOrCreateSocialUser;
use App\Exceptions\SocialEmailNotVerifiedException;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Two\User as SocialUser;
use Tests\TestCase;

class FindOrCreateSocialUserTest extends TestCase
{
    use RefreshDatabase;

    private function makeSocialiteUser(string $id, ?string $email, ?string $name = 'Social User', ?bool $emailVerified = true): SocialiteUser
    {
        return (new SocialUser)
            ->setRaw($emailVerified === null ? [] : ['email_verified' => $emailVerified])
            ->map(['id' => $id, 'email' => $email, 'name' => $name, 'nickname' => null]);
    }

    public function test_returns_existing_user_by_provider_and_provider_id(): void
    {
        $user = User::factory()->create([
            'provider' => 'google',
            'provider_id' => 'google-123',
        ]);

        $socialiteUser = $this->makeSocialiteUser('google-123', $user->email);

        $result = app(FindOrCreateSocialUser::class)->handle($socialiteUser, 'google');

        $this->assertTrue($user->is($result));
        $this->assertDatabaseCount('users', 1);
    }

    public function test_verifies_email_of_existing_provider_linked_user_when_still_null(): void
    {
        $user = User::factory()->unverified()->create([
            'provider' => 'google',
            'provider_id' => 'google-123',
        ]);

        $socialiteUser = $this->makeSocialiteUser('google-123', $user->email);

        $result = app(FindOrCreateSocialUser::class)->handle($socialiteUser, 'google');

        $this->assertTrue($user->is($result));
        $this->assertNotNull($result->fresh()->email_verified_at);
    }

    public function test_links_provider_to_existing_user_by_email(): void
    {
        $user = User::factory()->create(['email' => 'existing@example.com']);

        $socialiteUser = $this->makeSocialiteUser('google-456', 'existing@example.com');

        $result = app(FindOrCreateSocialUser::class)->handle($socialiteUser, 'google');

        $this->assertTrue($user->is($result));
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'email' => 'existing@example.com',
            'provider' => 'google',
            'provider_id' => 'google-456',
        ]);
    }

    public function test_creates_new_user_when_no_match_found(): void
    {
        $socialiteUser = $this->makeSocialiteUser('google-789', 'new@example.com', 'New User');

        $result = app(FindOrCreateSocialUser::class)->handle($socialiteUser, 'google');

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'email' => 'new@example.com',
            'name' => 'New User',
            'provider' => 'google',
            'provider_id' => 'google-789',
        ]);
        $this->assertInstanceOf(User::class, $result);

        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertSame(11, Category::where('user_id', $user->id)->count());
    }

    public function test_creates_user_with_null_email_when_provider_does_not_return_email(): void
    {
        $socialiteUser = $this->makeSocialiteUser('apple-001', null, 'Apple User');

        $result = app(FindOrCreateSocialUser::class)->handle($socialiteUser, 'apple');

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'name' => 'Apple User',
            'email' => null,
            'provider' => 'apple',
            'provider_id' => 'apple-001',
        ]);
        $this->assertInstanceOf(User::class, $result);
    }

    public function test_does_not_create_default_categories_for_existing_user(): void
    {
        $user = User::factory()->create([
            'provider' => 'google',
            'provider_id' => 'google-123',
        ]);

        $socialiteUser = $this->makeSocialiteUser('google-123', $user->email);

        app(FindOrCreateSocialUser::class)->handle($socialiteUser, 'google');

        $this->assertSame(0, Category::where('user_id', $user->id)->count());
    }

    public function test_does_not_link_provider_to_user_of_different_provider(): void
    {
        User::factory()->create([
            'provider' => 'facebook',
            'provider_id' => 'fb-123',
        ]);

        $socialiteUser = $this->makeSocialiteUser('google-123', 'other@example.com');

        $result = app(FindOrCreateSocialUser::class)->handle($socialiteUser, 'google');

        $this->assertDatabaseCount('users', 2);
        $this->assertSame('google', $result->provider);
        $this->assertSame('google-123', $result->provider_id);
    }

    public function test_uses_nickname_when_name_is_null(): void
    {
        $socialUser = (new SocialUser)->map(['id' => 'google-000', 'email' => 'nick@example.com', 'name' => null, 'nickname' => 'mynickname']);

        $result = app(FindOrCreateSocialUser::class)->handle($socialUser, 'google');

        $this->assertSame('mynickname', $result->name);
    }

    // ─── vínculo por e-mail: só com e-mail garantido pelo provedor ─────────

    public function test_refuses_to_link_existing_account_when_provider_does_not_verify_the_email(): void
    {
        $user = User::factory()->create(['email' => 'vitima@example.com']);

        foreach ([false, null] as $flag) {
            try {
                app(FindOrCreateSocialUser::class)->handle($this->makeSocialiteUser('google-evil', 'vitima@example.com', 'Atacante', $flag), 'google');
                $this->fail('O vínculo devia ser recusado.');
            } catch (SocialEmailNotVerifiedException) {
                // esperado
            }
        }

        $this->assertNull($user->fresh()->provider);
        $this->assertNull($user->fresh()->provider_id);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_accepts_the_google_v2_verified_email_flag_and_string_values(): void
    {
        User::factory()->create(['email' => 'a@example.com']);
        User::factory()->create(['email' => 'b@example.com']);

        $v2 = (new SocialUser)->setRaw(['verified_email' => true])->map(['id' => 'g-1', 'email' => 'a@example.com', 'name' => 'A']);
        $apple = (new SocialUser)->setRaw(['email_verified' => 'true'])->map(['id' => 'a-1', 'email' => 'b@example.com', 'name' => 'B']);

        $this->assertSame('g-1', app(FindOrCreateSocialUser::class)->handle($v2, 'google')->provider_id);
        $this->assertSame('a-1', app(FindOrCreateSocialUser::class)->handle($apple, 'apple')->provider_id);
    }

    public function test_pre_registered_unverified_account_loses_the_password_and_access_when_the_real_owner_links(): void
    {
        // Atacante cadastra o e-mail da vítima com uma senha que só ele conhece e já tem sessão/token abertos.
        $attackerAccount = User::factory()->unverified()->create(['email' => 'vitima@example.com', 'password' => 'senha-do-atacante', 'remember_token' => 'antigo']);
        $attackerAccount->createToken('atacante');
        $other = User::factory()->create();
        $otherToken = $other->createToken('outro');

        // A vítima entra com Google, que confirma o e-mail.
        $result = app(FindOrCreateSocialUser::class)->handle($this->makeSocialiteUser('google-real', 'vitima@example.com'), 'google');

        $this->assertTrue($attackerAccount->is($result));
        $fresh = $attackerAccount->fresh();
        $this->assertNull($fresh->password, 'a senha do atacante não pode continuar valendo');
        $this->assertFalse(Hash::check('senha-do-atacante', (string) $fresh->password));
        $this->assertNotNull($fresh->email_verified_at);
        $this->assertSame('google-real', $fresh->provider_id);
        $this->assertSame(0, $fresh->tokens()->count());
        $this->assertNotSame('antigo', $fresh->remember_token);
        $this->assertModelExists($otherToken->accessToken);
    }

    public function test_verified_account_keeps_its_password_and_tokens_when_linked(): void
    {
        $user = User::factory()->create(['email' => 'dono@example.com']);
        $user->createToken('app');

        app(FindOrCreateSocialUser::class)->handle($this->makeSocialiteUser('google-1', 'dono@example.com'), 'google');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        $this->assertSame(1, $user->fresh()->tokens()->count());
    }

    public function test_a_password_login_with_the_attackers_password_fails_after_the_takeover_attempt(): void
    {
        User::factory()->unverified()->create(['email' => 'vitima@example.com', 'password' => 'senha-do-atacante']);
        app(FindOrCreateSocialUser::class)->handle($this->makeSocialiteUser('google-real', 'vitima@example.com'), 'google');

        $this->postJson('/api/v1/auth/login', ['email' => 'vitima@example.com', 'password' => 'senha-do-atacante'])->assertUnauthorized();
    }
}
