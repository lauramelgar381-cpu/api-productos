<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function crearUsuario(): User
    {
        $user = User::create([
            'name' => 'Laura',
            'email' => 'laura@test.com',
            'password' => 'secreto123',
        ]);
        $user->assignRole('usuario');

        return $user;
    }

    private function crearUsuarioConDosFactores(): array
    {
        $user = $this->crearUsuario();
        $secret = (new Google2FA())->generateSecretKey();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ])->save();

        return [$user, $secret];
    }

    public function test_setup_devuelve_url_y_secreto_sin_activar(): void
    {
        $user = $this->crearUsuario();
        Sanctum::actingAs($user);

        $this->postJson('/api/2fa/setup')
            ->assertOk()
            ->assertJsonStructure(['otpauth_url', 'secret']);

        $this->assertNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_enable_con_codigo_valido_activa_el_2fa(): void
    {
        $user = $this->crearUsuario();
        Sanctum::actingAs($user);

        $secret = $this->postJson('/api/2fa/setup')->json('secret');
        $codigo = (new Google2FA())->getCurrentOtp($secret);

        $this->postJson('/api/2fa/enable', ['code' => $codigo])
            ->assertOk()
            ->assertJsonPath('user.two_factor_enabled', true);

        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_enable_con_codigo_invalido_devuelve_422(): void
    {
        $user = $this->crearUsuario();
        Sanctum::actingAs($user);

        $this->postJson('/api/2fa/setup');

        $this->postJson('/api/2fa/enable', ['code' => '00000a'])->assertStatus(422);
        $this->assertNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_login_con_2fa_devuelve_challenge_y_no_token(): void
    {
        $this->crearUsuarioConDosFactores();

        $this->postJson('/api/login', [
            'email' => 'laura@test.com',
            'password' => 'secreto123',
        ])
            ->assertOk()
            ->assertJsonPath('two_factor', true)
            ->assertJsonStructure(['challenge'])
            ->assertJsonMissingPath('token');
    }

    public function test_challenge_con_codigo_correcto_devuelve_token(): void
    {
        [, $secret] = $this->crearUsuarioConDosFactores();

        $challenge = $this->postJson('/api/login', [
            'email' => 'laura@test.com',
            'password' => 'secreto123',
        ])->json('challenge');

        $this->postJson('/api/auth/2fa/challenge', [
            'challenge' => $challenge,
            'code' => (new Google2FA())->getCurrentOtp($secret),
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['permissions']]);
    }

    public function test_challenge_con_codigo_incorrecto_devuelve_422(): void
    {
        $this->crearUsuarioConDosFactores();

        $challenge = $this->postJson('/api/login', [
            'email' => 'laura@test.com',
            'password' => 'secreto123',
        ])->json('challenge');

        $this->postJson('/api/auth/2fa/challenge', [
            'challenge' => $challenge,
            'code' => '12345a',
        ])->assertStatus(422);
    }

    public function test_el_secreto_nunca_aparece_en_me(): void
    {
        [$user] = $this->crearUsuarioConDosFactores();
        Sanctum::actingAs($user);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonMissingPath('user.two_factor_secret')
            ->assertJsonPath('user.two_factor_enabled', true);
    }
}
