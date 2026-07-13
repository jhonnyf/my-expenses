<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_avatar_redirects_unauthenticated_user(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('avatar.png', 100, 100);

        $this->post('/account/avatar', ['avatar' => $file])->assertRedirect('/login');
    }

    public function test_update_avatar_rejects_non_image_file(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $this->actingAs($user)
            ->post('/account/avatar', ['avatar' => $file])
            ->assertSessionHasErrors(['avatar']);
    }

    public function test_update_avatar_stores_file_and_associates_with_user(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('avatar.png', 200, 200);

        $response = $this->actingAs($user)->post('/account/avatar', ['avatar' => $file]);

        $response->assertRedirect(route('account.index'));

        $user->refresh();
        $this->assertNotNull($user->avatar);
        $this->assertSame('avatar', $user->avatar->collection);
        $this->assertSame(200, $user->avatar->width);
        $this->assertSame(200, $user->avatar->height);
        Storage::disk('public')->assertExists($user->avatar->path);
    }

    public function test_update_avatar_replaces_previous_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $firstFile = UploadedFile::fake()->image('first.png', 100, 100);
        $secondFile = UploadedFile::fake()->image('second.png', 100, 100);

        $this->actingAs($user)->post('/account/avatar', ['avatar' => $firstFile]);
        $firstPath = $user->refresh()->avatar->path;

        $this->actingAs($user)->post('/account/avatar', ['avatar' => $secondFile]);
        $user->refresh();

        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($user->avatar->path);
        $this->assertDatabaseCount('files', 1);
    }
}
