<?php

namespace Writeshh\Yarp\Tests\Unit;

use Writeshh\Yarp\Tests\Models\User;
use Writeshh\Yarp\Tests\Repositories\DirectUserRepository;
use Writeshh\Yarp\Tests\TestCase;

class DirectRepositoryTest extends TestCase
{
    protected DirectUserRepository $repository;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Initialize the repository
        $this->repository = new DirectUserRepository(new User());
    }
    
    /** @test */
    public function it_can_get_all_records()
    {
        // Create test users
        User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
        User::create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
        
        $users = $this->repository->all();
        
        $this->assertCount(2, $users);
        $this->assertEquals('John Doe', $users->first()->name);
    }
    
    /** @test */
    public function it_can_create_and_update_a_record()
    {
        // Create a user
        $user = $this->repository->create([
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);
        
        $this->assertEquals('John Doe', $user->name);
        
        // Update the user
        $updated = $this->repository->update(['name' => 'Jane Doe'], $user->id);
        
        $this->assertTrue($updated);
        
        // Retrieve the user and check if it was updated
        $updatedUser = $this->repository->show($user->id);
        $this->assertEquals('Jane Doe', $updatedUser->name);
    }
    
    /** @test */
    public function it_can_find_where_and_delete()
    {
        // Create two users
        User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
        User::create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
        
        // Find users where name is John Doe
        $users = $this->repository->findWhere('name', 'John Doe');
        
        $this->assertCount(1, $users);
        $userId = $users->first()->id;
        
        // Delete the user
        $deleted = $this->repository->delete($userId);
        
        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }
}
