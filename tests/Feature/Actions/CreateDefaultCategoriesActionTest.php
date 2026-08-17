<?php

namespace Tests\Feature\Actions;

use App\Actions\CreateDefaultCategoriesAction;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateDefaultCategoriesActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_default_categories_for_user(): void
    {
        $user = User::factory()->create();

        app(CreateDefaultCategoriesAction::class)->execute($user);

        $this->assertSame(11, Category::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('categories', [
            'user_id' => $user->id,
            'name' => 'Hortifruti',
        ]);
    }

    public function test_created_categories_have_keywords_for_auto_categorization(): void
    {
        $user = User::factory()->create();

        app(CreateDefaultCategoriesAction::class)->execute($user);

        $category = Category::where('user_id', $user->id)->where('name', 'Bebidas')->firstOrFail();

        $this->assertContains('AGUA', $category->keywords);
    }

    public function test_is_idempotent_and_does_not_duplicate_categories(): void
    {
        $user = User::factory()->create();
        $action = app(CreateDefaultCategoriesAction::class);

        $action->execute($user);
        $action->execute($user);

        $this->assertSame(11, Category::where('user_id', $user->id)->count());
    }

    public function test_does_not_overwrite_category_already_customized_by_user(): void
    {
        $user = User::factory()->create();
        Category::create([
            'user_id' => $user->id,
            'name' => 'Bebidas',
            'keywords' => ['CUSTOM'],
        ]);

        app(CreateDefaultCategoriesAction::class)->execute($user);

        $category = Category::where('user_id', $user->id)->where('name', 'Bebidas')->firstOrFail();
        $this->assertSame(['CUSTOM'], $category->keywords);
    }
}
