<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Support\LoginThrottle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * O `throttle:5,1` das rotas de login limita por IP. Aqui: o limite por E-MAIL, que segura o ataque
 * espalhado por vários IPs contra uma conta só (cada tentativa abaixo vem de um IP diferente).
 */
class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    private function webAttempt(string $email, string $password, int $ip)
    {
        return $this->withServerVariables(['REMOTE_ADDR' => "10.1.0.{$ip}"])
            ->post(route('login.execute'), ['email' => $email, 'password' => $password]);
    }

    private function apiAttempt(string $email, string $password, int $ip)
    {
        return $this->withServerVariables(['REMOTE_ADDR' => "10.2.0.{$ip}"])
            ->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password]);
    }

    private function failMany(callable $attempt, string $email, int $times): void
    {
        foreach (range(1, $times) as $ip) {
            $attempt($email, 'senha-errada', $ip);
        }
    }

    public function test_web_account_locks_after_too_many_failures_from_many_ips_even_with_the_right_password(): void
    {
        $user = User::factory()->create();
        $this->failMany($this->webAttempt(...), $user->email, LoginThrottle::MAX_FAILURES);

        $response = $this->webAttempt($user->email, 'password', 200);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Muitas tentativas', session('errors')->first('email'));
    }

    public function test_web_below_the_limit_the_right_password_still_works_and_resets_the_counter(): void
    {
        $user = User::factory()->create();
        $this->failMany($this->webAttempt(...), $user->email, LoginThrottle::MAX_FAILURES - 1);

        $this->webAttempt($user->email, 'password', 200)->assertRedirect(route('dashboard.index'));
        $this->assertAuthenticatedAs($user);
        auth()->logout();

        // O contador zerou: mais 9 erros não travam.
        foreach (range(201, 200 + LoginThrottle::MAX_FAILURES - 1) as $ip) {
            $this->webAttempt($user->email, 'senha-errada', $ip);
        }
        $this->webAttempt($user->email, 'password', 250)->assertRedirect(route('dashboard.index'));
    }

    public function test_lock_is_per_email_and_ignores_case_and_spaces(): void
    {
        $victim = User::factory()->create(['email' => 'vitima@example.com']);
        $other = User::factory()->create(['email' => 'outra@example.com']);

        foreach (range(1, LoginThrottle::MAX_FAILURES) as $ip) {
            $this->webAttempt($ip % 2 ? 'VITIMA@example.com' : ' vitima@example.com', 'senha-errada', $ip);
        }

        $this->webAttempt($victim->email, 'password', 200);
        $this->assertGuest();

        $this->webAttempt($other->email, 'password', 201)->assertRedirect(route('dashboard.index'));
        $this->assertAuthenticatedAs($other);
    }

    public function test_unknown_emails_are_counted_too_so_the_lock_does_not_reveal_which_accounts_exist(): void
    {
        $this->failMany($this->webAttempt(...), 'ninguem@example.com', LoginThrottle::MAX_FAILURES);

        $response = $this->webAttempt('ninguem@example.com', 'qualquer', 200);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Muitas tentativas', session('errors')->first('email'));
    }

    public function test_api_locks_the_account_and_never_issues_a_token_while_locked(): void
    {
        $user = User::factory()->create();
        $this->failMany($this->apiAttempt(...), $user->email, LoginThrottle::MAX_FAILURES);

        $response = $this->apiAttempt($user->email, 'password', 200);

        $response->assertStatus(429);
        $this->assertStringContainsString('Muitas tentativas', $response->json('message'));
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_api_wrong_password_below_the_limit_is_still_401_and_other_accounts_are_unaffected(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->failMany($this->apiAttempt(...), $user->email, LoginThrottle::MAX_FAILURES - 1);

        $this->apiAttempt($user->email, 'senha-errada', 100)->assertUnauthorized();
        $this->apiAttempt($other->email, 'password', 101)->assertOk()->assertJsonStructure(['data' => ['token']]);
    }

    public function test_password_reset_unlocks_the_account(): void
    {
        $user = User::factory()->create();
        $this->failMany($this->apiAttempt(...), $user->email, LoginThrottle::MAX_FAILURES);
        $this->apiAttempt($user->email, 'password', 200)->assertStatus(429);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => Password::broker()->createToken($user), 'email' => $user->email,
            'password' => 'nova-senha-123', 'password_confirmation' => 'nova-senha-123',
        ])->assertOk();

        $this->apiAttempt($user->email, 'nova-senha-123', 201)->assertOk();
    }

    public function test_the_lock_expires_with_the_window(): void
    {
        $user = User::factory()->create();
        $this->failMany($this->apiAttempt(...), $user->email, LoginThrottle::MAX_FAILURES);
        $this->apiAttempt($user->email, 'password', 200)->assertStatus(429);

        $this->travel(LoginThrottle::WINDOW_SECONDS + 1)->seconds();

        $this->apiAttempt($user->email, 'password', 201)->assertOk();
    }
}
