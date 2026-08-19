<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class VerificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_notice_shows_for_unverified_authenticated_user(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertStatus(200);
    }

    public function test_notice_redirects_to_dashboard_for_already_verified_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertRedirect(route('dashboard.index'));
    }

    public function test_notice_requires_authentication(): void
    {
        $this->get(route('verification.notice'))
            ->assertRedirect(route('login.index'));
    }

    public function test_verify_marks_email_as_verified_and_redirects_to_dashboard(): void
    {
        Event::fake();

        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)
            ->get($url)
            ->assertRedirect(route('dashboard.index'));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Event::assertDispatched(Verified::class);
    }

    public function test_verify_rejects_invalid_signature(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]))
            ->assertStatus(403);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_resend_sends_new_verification_email(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect();

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_resend_does_nothing_for_already_verified_user(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('verification.send'));

        Notification::assertNotSentTo($user, VerifyEmailNotification::class);
    }

    public function test_unverified_user_can_still_logout(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post(route('login.logout'))
            ->assertRedirect(route('login.index'));

        $this->assertGuest();
    }
}
