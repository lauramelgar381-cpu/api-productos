<?php

namespace Tests\Feature;

use App\Mail\OtpCodeMail;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function codigoEnviado(string $email): string
    {
        $this->postJson('/api/auth/code/request', ['email' => $email])->assertOk();

        $code = null;
        Mail::assertSent(OtpCodeMail::class, function ($mail) use (&$code) {
            $code = $mail->code;
            return true;
        });

        return (string) $code;
    }

    public function test_solicitar_codigo_envia_correo(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'a@test.com']);

        $this->postJson('/api/auth/code/request', ['email' => 'a@test.com'])->assertOk();

        Mail::assertSent(OtpCodeMail::class);
    }

    public function test_correo_inexistente_responde_igual_y_no_envia(): void
    {
        Mail::fake();

        $this->postJson('/api/auth/code/request', ['email' => 'nadie@test.com'])->assertOk();

        Mail::assertNothingSent();
    }

    public function test_codigo_correcto_devuelve_token_y_permisos(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'a@test.com']);
        $code = $this->codigoEnviado('a@test.com');

        $this->postJson('/api/auth/code/verify', ['email' => 'a@test.com', 'code' => $code])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['permissions']]);
    }

    public function test_codigo_incorrecto_falla(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'a@test.com']);
        $this->codigoEnviado('a@test.com');

        $this->postJson('/api/auth/code/verify', ['email' => 'a@test.com', 'code' => '000000'])
            ->assertStatus(422);
    }

    public function test_usuario_normal_no_puede_eliminar_productos(): void
    {
        $user = User::factory()->create();
        $user->assignRole('usuario');
        $producto = Product::create(['name' => 'X', 'price' => 1, 'stock' => 1]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/products/{$producto->id}")->assertForbidden();
    }

    public function test_superadmin_puede_eliminar_productos(): void
    {
        $super = User::where('email', 'superadmin@test.com')->first();
        $producto = Product::create(['name' => 'X', 'price' => 1, 'stock' => 1]);

        Sanctum::actingAs($super);

        $this->deleteJson("/api/products/{$producto->id}")->assertOk();
    }
}
