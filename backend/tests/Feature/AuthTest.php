<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_un_usuario_puede_registrarse(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Laura',
            'email' => 'laura@test.com',
            'password' => '123456',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['user', 'token']);

        $this->assertDatabaseHas('users', [
            'email' => 'laura@test.com',
        ]);
    }

    public function test_no_se_puede_registrar_con_email_duplicado(): void
    {
        User::factory()->create(['email' => 'laura@test.com']);

        $response = $this->postJson('/api/register', [
            'name' => 'Laura',
            'email' => 'laura@test.com',
            'password' => '123456',
        ]);

        $response->assertStatus(422);
    }

    public function test_un_usuario_puede_iniciar_sesion(): void
    {
        User::factory()->create([
            'email' => 'laura@test.com',
            'password' => Hash::make('123456'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'laura@test.com',
            'password' => '123456',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['user', 'token']);
    }

    public function test_no_puede_iniciar_sesion_con_credenciales_incorrectas(): void
    {
        User::factory()->create([
            'email' => 'laura@test.com',
            'password' => Hash::make('123456'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'laura@test.com',
            'password' => 'password-incorrecto',
        ]);

        $response->assertStatus(422);
    }

    public function test_un_usuario_autenticado_puede_cerrar_sesion(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Sesión cerrada correctamente.']);
    }

    public function test_un_usuario_no_autenticado_no_puede_cerrar_sesion(): void
    {
        $response = $this->postJson('/api/logout');

        $response->assertStatus(401);
    }
}
