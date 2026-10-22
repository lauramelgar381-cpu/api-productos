<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ProductController extends Controller
{
    #[OA\Get(
    path: '/api/products',
    summary: 'Listar productos (paginado)',
    tags: ['Productos'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 5, maximum: 50)),
        new OA\Parameter(name: 'search', in: 'query', description: 'Busca en nombre y descripción', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'sort', in: 'query', schema: new OA\Schema(type: 'string', enum: ['id', 'name', 'price', 'stock'])),
        new OA\Parameter(name: 'dir', in: 'query', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Lista paginada de productos'),
        new OA\Response(response: 401, description: 'No autenticado'),
        new OA\Response(response: 403, description: 'Sin permiso'),
    ]
)]
    public function index(Request $request)
{
    $query = Product::query();

    if ($search = $request->query('search')) {
        $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    // Lista blanca: nunca ordenes por un campo que venga sin validar
    $sort = in_array($request->query('sort'), ['id', 'name', 'price', 'stock'])
        ? $request->query('sort') : 'id';
    $dir = $request->query('dir') === 'desc' ? 'desc' : 'asc';
    $perPage = min((int) $request->query('per_page', 5), 50);

    return response()->json(
        $query->orderBy($sort, $dir)->paginate($perPage)
    );
}

    #[OA\Post(
        path: '/api/products',
        summary: 'Crear un producto nuevo',
        tags: ['Productos'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'price', 'stock'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Laptop Lenovo'),
                    new OA\Property(property: 'description', type: 'string', example: 'Laptop para desarrollo'),
                    new OA\Property(property: 'price', type: 'number', format: 'float', example: 650.00),
                    new OA\Property(property: 'stock', type: 'integer', example: 10),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Producto creado'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin permiso'),
            new OA\Response(response: 422, description: 'Error de validación'),
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
        ]);

        $product = Product::create($validated);

        return response()->json($product, 201);
    }

    #[OA\Get(
        path: '/api/products/{product}',
        summary: 'Ver un producto específico',
        tags: ['Productos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Datos del producto'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin permiso'),
            new OA\Response(response: 404, description: 'Producto no encontrado'),
        ]
    )]
    public function show(Product $product)
    {
        return response()->json($product);
    }

    #[OA\Put(
        path: '/api/products/{product}',
        summary: 'Actualizar un producto',
        tags: ['Productos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Laptop Lenovo actualizado'),
                    new OA\Property(property: 'description', type: 'string', example: 'Nueva descripción'),
                    new OA\Property(property: 'price', type: 'number', format: 'float', example: 700.00),
                    new OA\Property(property: 'stock', type: 'integer', example: 5),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Producto actualizado'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin permiso'),
            new OA\Response(response: 404, description: 'Producto no encontrado'),
            new OA\Response(response: 422, description: 'Error de validación'),
        ]
    )]
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric|min:0',
            'stock' => 'sometimes|required|integer|min:0',
        ]);

        $product->update($validated);

        return response()->json($product);
    }

    #[OA\Delete(
        path: '/api/products/{product}',
        summary: 'Eliminar un producto',
        tags: ['Productos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Producto eliminado'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin permiso'),
            new OA\Response(response: 404, description: 'Producto no encontrado'),
        ]
    )]
    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json(['message' => 'Producto eliminado correctamente.']);
    }
}
