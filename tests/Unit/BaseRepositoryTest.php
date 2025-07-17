<?php

namespace Writeshh\Yarp\Tests\Unit;

use Writeshh\Yarp\Tests\Models\User;
use Writeshh\Yarp\Tests\Repositories\UserRepository;
use Writeshh\Yarp\Tests\TestCase;

class BaseRepositoryTest extends TestCase
{
    protected UserRepository $repository;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Initialize the repository
        $this->repository = new UserRepository(new User());
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
    public function it_can_get_paginated_records()
    {
        // Create test users
        User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
        User::create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
        
        $users = $this->repository->paginate(1);
        
        $this->assertEquals(1, $users->count());
        $this->assertEquals(2, $users->total());
    }
    
    /** @test */
    public function it_can_find_a_record_by_id()
    {
        $user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
        
        $foundUser = $this->repository->show($user->id);
        
        $this->assertNotNull($foundUser);
        $this->assertEquals('John Doe', $foundUser->name);
    }
    
    /** @test */
    public function it_can_create_a_record()
    {
        $data = ['name' => 'John Doe', 'email' => 'john@example.com'];
        
        $user = $this->repository->create($data);
        
        $this->assertNotNull($user);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        
        // Check if it's actually in the database
        $this->assertDatabaseHas('users', $data);
    }
    
    /** @test */
    public function it_can_update_a_record()
    {
        $user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
        
        $updated = $this->repository->update(['name' => 'Jane Doe'], $user->id);
        
        $this->assertTrue($updated);
        
        // Refresh the model from database
        $user->refresh();
        
        $this->assertEquals('Jane Doe', $user->name);
    }
    
    /** @test */
    public function it_returns_false_when_updating_non_existent_record()
    {
        $updated = $this->repository->update(['name' => 'Jane Doe'], 999);
        
        $this->assertFalse($updated);
    }
    
    /** @test */
    public function it_can_delete_a_record()
    {
        $user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
        
        $deleted = $this->repository->delete($user->id);
        
        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
    
    /** @test */
    public function it_can_find_records_by_a_field()
    {
        User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
        User::create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
        
        $users = $this->repository->findWhere('name', 'John Doe');
        
        $this->assertCount(1, $users);
        $this->assertEquals('John Doe', $users->first()->name);
    }
    
    /** @test */
    public function it_can_execute_custom_query_methods()
    {
        User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
        
        $user = $this->repository->findByEmail('john@example.com');
        
        $this->assertNotNull($user);
        $this->assertEquals('John Doe', $user->name);
    }
}
