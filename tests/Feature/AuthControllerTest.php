<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use App\Models\User;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

 /** @test */
 public function it_registers_admin_successfully()
 {
    $response = $this->postJson('/api/register/admin', [
        'name' => 'James Doe',
        'email' => 'jamesdoe@apex.com', 
        'password' => 'yourStrongPassword123',
    ]);

    $response->assertStatus(201)
             ->assertJsonStructure(['token']);
 }

 /** @test */
 public function it_fails_to_register_admin_with_invalid_email_domain()
 {
    $response = $this->postJson('/api/register/admin', [
        'name' => 'James Doe',
        'email' => 'jamesdoe@gmail.com', 
        'password' => 'yourStrongPassword123',
    ]);

    $response->assertStatus(422)
             ->assertJsonValidationErrors(['email']);
 }

 /** @test */
 public function it_fails_to_register_admin_with_short_password()
 {
    $response = $this->postJson('/api/register/admin', [
        'name' => 'James Doe',
        'email' => 'jamesdoe@gmail.com', 
        'password' => 'pass', 
    ]);

    $response->assertStatus(422)
             ->assertJsonValidationErrors(['password']);
 }

 /** @test */
public function it_registers_user_successfully()
{
    $response = $this->postJson('/api/register', [
        'name' => 'Ijeoma Ade',
        'email' => 'ijeade@gmail.com',
        'password' => 'passwordStrong',
    ]);

    $response->assertStatus(201)
             ->assertJsonStructure(['token']);
}

/** @test */
public function it_fails_to_register_user_with_existing_email()
{
    // Create a user with the same email to simulate an existing user
    User::create([
        'name' => 'Mary Doe',
        'email' => 'janedoe@gmail.com',
        'password' => bcrypt('yourStrongPassword123'),
        'role' => 'user',
    ]);

    $response = $this->postJson('/api/register', [
        'name' => 'Mary Doe',
        'email' => 'janedoe@gmail.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(422)
             ->assertJsonValidationErrors(['email']);
}


/** @test */
public function it_fails_to_register_user_with_invalid_email()
{
    $response = $this->postJson('/api/register', [
        'name' => 'Ijeoma Ade',
        'email' => 'user#pick.com', 
        'password' => 'passwordStrong',
    ]);

    $response->assertStatus(422)
             ->assertJsonValidationErrors(['email']); 
}


/** @test */
public function it_logs_in_user_successfully()
{
    $user = User::create([
        'name' => 'Ijeoma Ade',
        'email' => 'ijeade@gmail.com',
        'password' => Hash::make('passwordStrong'),
    ]);
    
    $response = $this->postJson('/api/login', [
        'email' => 'ijeade@gmail.com',
        'password' => 'passwordStrong',
    ]);

    $response->assertStatus(200)
             ->assertJsonStructure(['token', 'role']);
}

/** @test */
public function it_fails_to_login_with_invalid_credentials()
{
    // Attempt login with incorrect credentials
    $response = $this->postJson('/api/login', [
        'email' => 'vdm@gmail.com', 
        'password' => 'verydarkman',
    ]);

    $response->assertStatus(401)
             ->assertJson(['error' => 'Unauthorized']);
}

/** @test */
public function it_logs_out_authenticated_user_successfully()
{
    // Create a user
    $user = User::create([
        'name' => 'Mark Ade',
        'email' => 'markade@gmail.com',
        'password' => Hash::make('passwordStrong'),
    ]);
    
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/logout');

    $response->assertStatus(200)
             ->assertJson(['message' => 'Logged out successfully']);

    $this->assertEmpty($user->tokens);
}


/** @test */
public function it_returns_unauthorized_when_attempting_to_logout_unauthenticated_user()
{
    // Access the logout endpoint without authenticating
    $response = $this->postJson('/api/logout');

    
    $response->assertStatus(401);
}

}
