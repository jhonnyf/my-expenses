<?php

namespace Tests\Unit\Actions;

use App\Actions\UpdateUserAvatarAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class UpdateUserAvatarActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_stores_file_and_replaces_previous_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $oldAvatar = $user->files()->create([
            'collection' => 'avatar',
            'disk' => 'public',
            'path' => 'avatars/old.png',
            'original_name' => 'old.png',
            'mime_type' => 'image/png',
            'size' => 10,
        ]);
        Storage::disk('public')->put('avatars/old.png', 'fake-content');

        $uploadedFile = UploadedFile::fake()->image('new.png', 150, 150);

        $file = (new UpdateUserAvatarAction)->execute($user, $uploadedFile);

        $this->assertDatabaseMissing('files', ['id' => $oldAvatar->id]);
        Storage::disk('public')->assertMissing('avatars/old.png');
        Storage::disk('public')->assertExists($file->path);
        $this->assertSame(150, $file->width);
        $this->assertSame(150, $file->height);
    }

    public function test_execute_keeps_previous_avatar_when_storing_new_file_fails(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $oldAvatar = $user->files()->create([
            'collection' => 'avatar',
            'disk' => 'public',
            'path' => 'avatars/old.png',
            'original_name' => 'old.png',
            'mime_type' => 'image/png',
            'size' => 10,
        ]);
        Storage::disk('public')->put('avatars/old.png', 'fake-content');

        $notAnImagePath = tempnam(sys_get_temp_dir(), 'avatar-test-');
        file_put_contents($notAnImagePath, 'not-an-image');

        $uploadedFile = Mockery::mock(UploadedFile::class);
        $uploadedFile->shouldReceive('getRealPath')->andReturn($notAnImagePath);
        $uploadedFile->shouldReceive('store')->with('avatars', 'public')->andReturn(false);

        $this->expectException(RuntimeException::class);

        try {
            (new UpdateUserAvatarAction)->execute($user, $uploadedFile);
        } finally {
            $this->assertDatabaseHas('files', ['id' => $oldAvatar->id]);
            Storage::disk('public')->assertExists('avatars/old.png');
            @unlink($notAnImagePath);
        }
    }
}
