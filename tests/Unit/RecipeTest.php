<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\Recipe;
use App\Models\User;
use App\Models\RecipeLike;
use App\Models\SavedRecipe;
use Mockery;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecipeTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_is_liked_by_returns_true_if_user_liked_recipe()
    {
        $user = Mockery::mock(User::class);
        $user->id = 123;

        $recipe = new Recipe();

        $likesRelationship = Mockery::mock(HasMany::class);
        $likesRelationship->shouldReceive('where')->with('user_id', 123)->andReturnSelf();
        $likesRelationship->shouldReceive('exists')->andReturn(true);

        // We still need to mock the likes() method because we don't have a database
        // and we cannot use RefreshDatabase without a full Laravel bootstrap.
        $recipeMock = Mockery::mock(Recipe::class)->makePartial();
        $recipeMock->shouldReceive('likes')->andReturn($likesRelationship);

        $this->assertTrue($recipeMock->isLikedBy($user));
    }

    public function test_is_liked_by_returns_false_if_user_has_not_liked_recipe()
    {
        $user = Mockery::mock(User::class);
        $user->id = 123;

        $recipeMock = Mockery::mock(Recipe::class)->makePartial();

        $likesRelationship = Mockery::mock(HasMany::class);
        $likesRelationship->shouldReceive('where')->with('user_id', 123)->andReturnSelf();
        $likesRelationship->shouldReceive('exists')->andReturn(false);

        $recipeMock->shouldReceive('likes')->andReturn($likesRelationship);

        $this->assertFalse($recipeMock->isLikedBy($user));
    }

    public function test_is_saved_by_returns_true_if_user_saved_recipe()
    {
        $user = Mockery::mock(User::class);
        $user->id = 123;

        $recipeMock = Mockery::mock(Recipe::class)->makePartial();

        $savesRelationship = Mockery::mock(HasMany::class);
        $savesRelationship->shouldReceive('where')->with('user_id', 123)->andReturnSelf();
        $savesRelationship->shouldReceive('exists')->andReturn(true);

        $recipeMock->shouldReceive('saves')->andReturn($savesRelationship);

        $this->assertTrue($recipeMock->isSavedBy($user));
    }

    public function test_is_saved_by_returns_false_if_user_has_not_saved_recipe()
    {
        $user = Mockery::mock(User::class);
        $user->id = 123;

        $recipeMock = Mockery::mock(Recipe::class)->makePartial();

        $savesRelationship = Mockery::mock(HasMany::class);
        $savesRelationship->shouldReceive('where')->with('user_id', 123)->andReturnSelf();
        $savesRelationship->shouldReceive('exists')->andReturn(false);

        $recipeMock->shouldReceive('saves')->andReturn($savesRelationship);

        $this->assertFalse($recipeMock->isSavedBy($user));
    }
}
