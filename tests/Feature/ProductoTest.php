<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['ver-productos', 'crear-productos', 'editar-productos', 'eliminar-productos'] as $permiso) {
            Permission::firstOrCreate(['name' => $permiso]);
        }

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->givePermissionTo(['ver-productos', 'crear-productos', 'editar-productos', 'eliminar-productos']);

        $usuario = Role::firstOrCreate(['name' => 'usuario']);
        $usuario->givePermissionTo(['ver-productos']);
    }

    protected function usuarioConToken(string $rol = 'admin'): array
    {
        $user = User::factory()->create();
        $user->assignRole($rol);
        $token = $user->createToken('api-token')->plainTextToken;

        return [$user, $token];
    }

    public function test_no_autenticado_no_puede_listar_productos(): void
    {
        $response = $this->getJson('/api/products');

        $response->assertStatus(401);
    }

    public function test_admin_puede_listar_productos(): void
    {
        [, $token] = $this->usuarioConToken('admin');
        Product::factory()->count(3)->create();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_puede_crear_un_producto(): void
    {
        [, $token] = $this->usuarioConToken('admin');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products', [
                'name' => 'Laptop Lenovo',
                'description' => 'Laptop para desarrollo',
                'price' => 650.00,
                'stock' => 10,
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Laptop Lenovo']);

        $this->assertDatabaseHas('products', ['name' => 'Laptop Lenovo']);
    }

    public function test_usuario_sin_permiso_no_puede_crear_producto(): void
    {
        [, $token] = $this->usuarioConToken('usuario');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products', [
                'name' => 'Laptop Lenovo',
                'price' => 650.00,
                'stock' => 10,
            ]);

        $response->assertStatus(403);
    }

    public function test_no_se_puede_crear_producto_sin_campos_requeridos(): void
    {
        [, $token] = $this->usuarioConToken('admin');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'price', 'stock']);
    }

    public function test_admin_puede_ver_un_producto_especifico(): void
    {
        [, $token] = $this->usuarioConToken('admin');
        $product = Product::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $product->id]);
    }

    public function test_admin_puede_actualizar_un_producto(): void
    {
        [, $token] = $this->usuarioConToken('admin');
        $product = Product::factory()->create(['name' => 'Nombre viejo']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/products/{$product->id}", [
                'name' => 'Nombre nuevo',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Nombre nuevo']);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Nombre nuevo']);
    }

    public function test_usuario_sin_permiso_no_puede_actualizar_producto(): void
    {
        [, $token] = $this->usuarioConToken('usuario');
        $product = Product::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/products/{$product->id}", ['name' => 'Intento no autorizado']);

        $response->assertStatus(403);
    }

    public function test_admin_puede_eliminar_un_producto(): void
    {
        [, $token] = $this->usuarioConToken('admin');
        $product = Product::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_usuario_sin_permiso_no_puede_eliminar_producto(): void
    {
        [, $token] = $this->usuarioConToken('usuario');
        $product = Product::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(403);
    }
}
