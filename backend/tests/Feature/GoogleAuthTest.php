<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        config(['services.google.client_id' => 'mi-client-id']);
    }

    private function fingirGoogle(string $aud = 'mi-client-id', string $verificado = 'true'): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response([
                'aud' => $aud,
                'email' => 'nuevo@test.com',
                'email_verified' => $verificado,
                'name' => 'Nuevo Usuario',
            ], 200),
        ]);
    }

    public function test_crea_usuario_con_rol_usuario_y_devuelve_token(): void
    {
        $this->fingirGoogle();

        $this->postJson('/api/auth/google', ['credential' => 'token-falso'])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['permissions']])
            ->assertJsonPath('user.email', 'nuevo@test.com');

        $user = User::where('email', 'nuevo@test.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('usuario'));
    }

    public function test_rechaza_token_con_aud_distinto(): void
    {
        $this->fingirGoogle('otro-client-id');

        $this->postJson('/api/auth/google', ['credential' => 'token-falso'])
            ->assertStatus(422);

        $this->assertDatabaseMissing('users', ['email' => 'nuevo@test.com']);
    }

    public function test_rechaza_correo_no_verificado(): void
    {
        $this->fingirGoogle('mi-client-id', 'false');

        $this->postJson('/api/auth/google', ['credential' => 'token-falso'])
            ->assertStatus(422);
    }
}
