<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicStorageServeTest extends TestCase
{
    public function test_serves_existing_file_from_public_disk_without_symlink(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/foo.jpg', 'conteudo-fake');

        $response = $this->get('/storage/avatars/foo.jpg');

        $response->assertStatus(200);
        $this->assertSame('conteudo-fake', $response->streamedContent());
    }

    public function test_returns_404_for_missing_file(): void
    {
        Storage::fake('public');

        $this->get('/storage/avatars/nao-existe.jpg')->assertStatus(404);
    }

    public function test_does_not_expose_private_local_disk_files(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('certificado.pfx', 'segredo');

        $this->get('/storage/certificado.pfx')->assertStatus(404);
    }
}
