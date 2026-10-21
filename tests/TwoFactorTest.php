<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    private function usuarioConDosFactores(): array
    {
        $secret = (new Google2FA())->generateSecretKey();
        $user = User::factory()->create([
            'email' => 't@test.com',
            'password' => Hash::make('123456'),
        ]);
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ])->save();

        return [$user, $secret];
    }

    public function test_setup_devuelve_url_otpauth_sin_activar_el_2fa(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/2fa/setup')
            ->assertOk()
            ->assertJsonStructure(['otpauth_url', 'secret']);

        $this->assertNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_activar_con_codigo_valido(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $secret = $this->postJson('/api/2fa/setup')->json('secret');
        $code = (new Google2FA())->getCurrentOtp($secret);

        $this->postJson('/api/2fa/enable', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('user.two_factor_enabled', true);
    }

    public function test_activar_con_codigo_invalido_falla(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/2fa/setup');

        $this->postJson('/api/2fa/enable', ['code' => '000000'])->assertStatus(422);
    }

    public function test_login_con_2fa_no_entrega_token_sino_desafio(): void
    {
        $this->usuarioConDosFactores();

        $this->postJson('/api/login', ['email' => 't@test.com', 'password' => '123456'])
            ->assertOk()
            ->assertJsonPath('two_factor', true)
            ->assertJsonStructure(['challenge'])
            ->assertJsonMissing(['token']);
    }

    public function test_desafio_con_codigo_correcto_entrega_token(): void
    {
        [, $secret] = $this->usuarioConDosFactores();

        $challenge = $this->postJson('/api/login', ['email' => 't@test.com', 'password' => '123456'])
            ->json('challenge');

        $this->postJson('/api/auth/2fa/challenge', [
            'challenge' => $challenge,
            'code' => (new Google2FA())->getCurrentOtp($secret),
        ])->assertOk()->assertJsonStructure(['token', 'user']);
    }

    public function test_desafio_con_codigo_incorrecto_falla(): void
    {
        $this->usuarioConDosFactores();

        $challenge = $this->postJson('/api/login', ['email' => 't@test.com', 'password' => '123456'])
            ->json('challenge');

        $this->postJson('/api/auth/2fa/challenge', ['challenge' => $challenge, 'code' => '000000'])
            ->assertStatus(422);
    }

    public function test_el_secreto_nunca_se_expone_en_la_api(): void
    {
        [, $secret] = $this->usuarioConDosFactores();
        Sanctum::actingAs(User::where('email', 't@test.com')->first());

        $this->getJson('/api/me')->assertOk()->assertJsonMissing(['two_factor_secret' => $secret]);
    }
}
