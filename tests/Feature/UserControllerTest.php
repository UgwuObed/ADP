<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use App\Models\User;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
   /** @test */
public function it_creates_user_successfully()
{
    // Authenticate as an admin user
    $admin = User::factory()->create(['role' => 'admin']);
    Sanctum::actingAs($admin);


    $response = $this->postJson('/api/users', [
        'name' => 'Test User',
        'email' => 'test@gmail.com',
        'password' => 'password123',
    ]);


    $response->assertStatus(201)
             ->assertJson(['message' => 'User created successfully']);
}

/** @test */
public function it_returns_unauthorized_when_creating_user_without_admin_privileges()
{
    // Authenticate as a regular user (non-admin)
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/users', [
        'name' => 'Test User',
        'email' => 'test@gmail.com',
        'password' => 'password123',
    ]);

    
    $response->assertStatus(401);
}

public function it_returns_error_when_creating_user_with_invalid_data()
{
    
    $admin = User::factory()->create(['role' => 'admin']);
    Sanctum::actingAs($admin);

    // Attempt to create a new user with invalid data (missing required fields)
    $response = $this->postJson('/api/users', []);

    $response->assertStatus(422)
             ->assertJsonValidationErrors(['name', 'email', 'password']);
}

public function it_retrieves_user_profiles_successfully()
{
 
    $admin = User::factory()->create(['role' => 'admin']);
    Sanctum::actingAs($admin);

    // Retrieve user profiles
    $response = $this->getJson('/api/admin/users');


    $response->assertStatus(200)
             ->assertJsonStructure(['users']);
}

/** @test */
public function it_returns_unauthorized_when_non_admin_attempts_to_retrieve_user_profiles()
{
    $user = User::factory()->create();
    Sanctum::actingAs($user);
   
    $response = $this->getJson('/api/admin/users');
    
    $response->assertStatus(401)
             ->assertJson(['error' => 'Unauthorized']);
}

/** @test */
public function it_updates_user_profile_successfully()
{
  
    $user = User::factory()->create();
    Sanctum::actingAs($user);

  
    $response = $this->putJson('/api/user', [
        'name' => 'New Name',
        'email' => 'newemail@example.com',
        'password' => 'newpassword',
    ]);


    $response->assertStatus(200)
             ->assertJson(['message' => 'User profile updated successfully']);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'New Name',
        'email' => 'newemail@example.com',
    ]);
}

/** @test */
public function it_returns_validation_error_when_updating_user_profile_with_invalid_data()
{
    
    $user = User::factory()->create();
    Sanctum::actingAs($user);
   
    $response = $this->putJson('/api/user', []);

    $response->assertStatus(422)
             ->assertJsonValidationErrors(['name', 'email']);
}

/** @test */
public function it_retrieves_user_profile_successfully()
{

    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/user');

    $response->assertStatus(200)
             ->assertJson([
                 'name' => $user->name,
                 'email' => $user->email,
             ]);
}

/** @test */
public function it_returns_unauthorized_when_user_not_authenticated()
{
    // Attempt to retrieve user profile without authentication
    $response = $this->getJson('/api/user');

    // Assert that the response status code is 401 (Unauthorized) and check the response JSON
    $response->assertStatus(401)
             ->assertJson(['message' => 'Unauthenticated.']);
}


/** @test */
public function it_deletes_user_successfully()
{
    // Create an admin user
    $admin = User::factory()->create(['role' => 'admin']);
    Sanctum::actingAs($admin);

    // Create a user to delete
    $userToDelete = User::factory()->create();

    // Delete user
    $response = $this->deleteJson("/api/users/{$userToDelete->id}");

    $response->assertStatus(200)
             ->assertJson(['message' => 'User deleted successfully']);

    $this->assertDatabaseMissing('users', ['id' => $userToDelete->id]);
}

/** @test */
public function it_returns_unauthorized_when_non_admin_attempts_to_delete_user()
{
    // Authenticate as a regular user (non-admin)
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->deleteJson("/api/users/1");

    $response->assertStatus(401)
             ->assertJson(['error' => 'Unauthorized']);
}

/** @test */
public function it_returns_not_found_when_deleting_non_existing_user()
{
   
    $admin = User::factory()->create(['role' => 'admin']);
    Sanctum::actingAs($admin);

    $response = $this->deleteJson("/api/users/999");

    $response->assertStatus(404)
             ->assertJson(['error' => 'User not found']);
}

}
