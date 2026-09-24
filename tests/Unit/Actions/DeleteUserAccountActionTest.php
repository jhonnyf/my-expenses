<?php

namespace Tests\Unit\Actions;

use App\Actions\DeleteUserAccountAction;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeleteUserAccountActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_removes_personal_data_and_anonymizes_invoices(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $profile = UserProfile::factory()->for($user)->create(['cpf' => '11122233344']);

        $avatarPath = 'avatars/avatar.png';
        Storage::disk('public')->put($avatarPath, 'fake-content');
        $avatar = $user->files()->create([
            'collection' => 'avatar',
            'disk' => 'public',
            'path' => $avatarPath,
            'original_name' => 'avatar.png',
            'mime_type' => 'image/png',
            'size' => 10,
        ]);

        $token = $user->createToken('test-device');

        $category = Category::factory()->for($user)->create();
        $invoice = Invoice::factory()->for($user)->for(Issuer::factory())->create();
        $item = InvoiceItem::factory()->for($invoice)->create(['category_id' => $category->id]);

        $subscriptionId = $user->subscription->id;

        app(DeleteUserAccountAction::class)->execute($user);

        $this->assertModelMissing($user);
        $this->assertModelMissing($profile);
        $this->assertModelMissing($avatar);
        Storage::disk('public')->assertMissing($avatarPath);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
        $this->assertDatabaseMissing('subscriptions', ['id' => $subscriptionId]);
        $this->assertModelMissing($category);

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'user_id' => null]);
        $this->assertDatabaseHas('invoices_items', ['id' => $item->id, 'category_id' => null]);
    }
}
