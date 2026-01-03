<?php

/**
 * Full Authentication Flow Test
 * 
 * Tests the complete user journey:
 * 1. New user registers
 * 2. User is automatically logged in after registration
 * 3. User logs out
 * 4. User logs back in with their credentials
 * 
 * This test validates that the entire auth system works end-to-end.
 */

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('complete auth flow: register → auto-login → logout → login', function () {
    $testEmail = 'newuser_' . time() . '@example.com';
    $testPassword = 'SecurePassword123!';
    $testName = 'Test User';

    // Step 1: Register a new user
    $registerResponse = $this->post('/register', [
        'name' => $testName,
        'email' => $testEmail,
        'password' => $testPassword,
        'password_confirmation' => $testPassword,
    ]);

    // Should redirect after successful registration
    $registerResponse->assertRedirect();
    
    // User should be authenticated after registration
    $this->assertAuthenticated();
    
    // Verify user was created in database
    $this->assertDatabaseHas('users', [
        'email' => $testEmail,
        'name' => $testName,
    ]);

    // Get the user from database
    $user = User::where('email', $testEmail)->first();
    expect($user)->not->toBeNull();
    expect(Hash::check($testPassword, $user->password))->toBeTrue();

    // Step 2: Access a protected route (should work since we're logged in)
    $dashboardResponse = $this->get('/dashboard');
    $dashboardResponse->assertStatus(200);

    // Step 3: Logout
    $logoutResponse = $this->post('/logout');
    $logoutResponse->assertRedirect('/login');
    $this->assertGuest();

    // Step 4: Try to access protected route (should redirect to login)
    $protectedResponse = $this->get('/dashboard');
    $protectedResponse->assertRedirect('/login');

    // Step 5: Login with the created credentials
    $loginResponse = $this->post('/login', [
        'email' => $testEmail,
        'password' => $testPassword,
    ]);

    $loginResponse->assertRedirect();
    $this->assertAuthenticated();

    // Step 6: Verify we can access protected routes again
    $dashboardResponse = $this->get('/dashboard');
    $dashboardResponse->assertStatus(200);
});

test('registration fails with invalid data', function () {
    // Missing name
    $response = $this->post('/register', [
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);
    $response->assertSessionHasErrors('name');

    // Invalid email
    $response = $this->post('/register', [
        'name' => 'Test',
        'email' => 'not-an-email',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);
    $response->assertSessionHasErrors('email');

    // Password too short
    $response = $this->post('/register', [
        'name' => 'Test',
        'email' => 'test@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);
    $response->assertSessionHasErrors('password');

    // Password mismatch
    $response = $this->post('/register', [
        'name' => 'Test',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'different123',
    ]);
    $response->assertSessionHasErrors('password');
});

test('login fails with wrong password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('correct-password'),
    ]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('login fails with non-existent user', function () {
    $response = $this->post('/login', [
        'email' => 'nonexistent@example.com',
        'password' => 'any-password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('authenticated user cannot access login page', function () {
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)->get('/login');
    
    // Should redirect authenticated users away from login page
    $response->assertRedirect();
});

test('authenticated user cannot access register page', function () {
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)->get('/register');
    
    // Should redirect authenticated users away from register page
    $response->assertRedirect();
});

test('remember me functionality persists session', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password123'),
    ]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password123',
        'remember' => true,
    ]);

    $response->assertRedirect();
    $this->assertAuthenticated();
    
    // Verify remember token was set
    $user->refresh();
    expect($user->remember_token)->not->toBeNull();
});
