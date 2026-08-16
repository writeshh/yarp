<?php

declare(strict_types=1);

namespace Writeshh\Yarp\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Writeshh\Yarp\Exceptions\RepositoryException;
use Writeshh\Yarp\Tests\Fixtures\Models\Tag;
use Writeshh\Yarp\Tests\Fixtures\Models\User;
use Writeshh\Yarp\Tests\Fixtures\Repositories\TagRepository;
use Writeshh\Yarp\Tests\Fixtures\Repositories\UserRepository;
use Writeshh\Yarp\Tests\TestCase;

class SoftDeleteTest extends TestCase
{
    private TagRepository $tags;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tags = new TagRepository(new Tag);
    }

    #[Test]
    public function deleting_soft_deletes(): void
    {
        $tag = $this->makeTag();

        $this->assertTrue($this->tags->delete($tag->id));

        $this->assertSame(0, $this->tags->count());
        $this->assertDatabaseHas('tags', ['id' => $tag->id]);
    }

    #[Test]
    public function it_includes_trashed_records_on_request(): void
    {
        $tag = $this->makeTag();
        $this->tags->delete($tag->id);
        $this->makeTag();

        $this->assertSame(2, $this->tags->withTrashed()->count());
    }

    #[Test]
    public function it_restricts_to_trashed_records(): void
    {
        $tag = $this->makeTag(['label' => 'gone']);
        $this->tags->delete($tag->id);
        $this->makeTag(['label' => 'here']);

        $trashed = $this->tags->onlyTrashed()->get();

        $this->assertCount(1, $trashed);
        $this->assertSame('gone', $trashed->first()?->label);
    }

    #[Test]
    public function it_restores_a_trashed_record(): void
    {
        $tag = $this->makeTag();
        $this->tags->delete($tag->id);

        $this->assertTrue($this->tags->restore($tag->id));
        $this->assertSame(1, $this->tags->count());
    }

    #[Test]
    public function restoring_a_missing_record_returns_false(): void
    {
        $this->assertFalse($this->tags->restore(9999));
    }

    #[Test]
    public function it_force_deletes_a_trashed_record(): void
    {
        $tag = $this->makeTag();
        $this->tags->delete($tag->id);

        $this->assertTrue($this->tags->forceDelete($tag->id));
        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }

    #[Test]
    public function it_force_deletes_a_live_record(): void
    {
        $tag = $this->makeTag();

        $this->assertTrue($this->tags->forceDelete($tag->id));
        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }

    #[Test]
    public function force_delete_works_on_models_without_soft_deletes(): void
    {
        $users = new UserRepository(new User);
        $user = $this->makeUser();

        $this->assertTrue($users->forceDelete($user->id));
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    #[Test]
    public function with_trashed_rejects_a_model_without_soft_deletes(): void
    {
        $users = new UserRepository(new User);

        $this->expectException(RepositoryException::class);
        $this->expectExceptionMessageMatches('/does not use the .*SoftDeletes.* trait/');

        $users->withTrashed();
    }

    #[Test]
    public function only_trashed_rejects_a_model_without_soft_deletes(): void
    {
        $this->expectException(RepositoryException::class);

        (new UserRepository(new User))->onlyTrashed();
    }

    #[Test]
    public function restore_rejects_a_model_without_soft_deletes(): void
    {
        $this->expectException(RepositoryException::class);

        (new UserRepository(new User))->restore(1);
    }
}
