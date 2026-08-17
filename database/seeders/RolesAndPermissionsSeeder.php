<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Permisos
        $permisos = [
            'ver-productos',
            'crear-productos',
            'editar-productos',
            'eliminar-productos',
        ];

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso]);
        }

        // Roles
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $usuario = Role::firstOrCreate(['name' => 'usuario']);

        // Asignar todos los permisos al admin
        $admin->givePermissionTo($permisos);

        // El usuario normal solo puede ver productos
        $usuario->givePermissionTo(['ver-productos']);
    }
}