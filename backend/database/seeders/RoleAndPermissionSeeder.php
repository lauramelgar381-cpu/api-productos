<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permisos = ['ver-productos', 'crear-productos', 'editar-productos', 'eliminar-productos'];
        foreach ($permisos as $p) {
            Permission::findOrCreate($p, 'web');
        }

        Role::findOrCreate('admin', 'web')->syncPermissions($permisos);
        Role::findOrCreate('usuario', 'web')->syncPermissions(['ver-productos']);

        $superadminRole = Role::findOrCreate('superadmin', 'web');
        $superadminRole->syncPermissions(Permission::all());

       $super = User::firstOrCreate(
       ['email' => 'superadmin@test.com'],
       ['name' => 'Super Admin', 'password' => Hash::make('super1234')],
   );
        $super->assignRole('superadmin');

        $admin = User::firstOrCreate(
            ['email' => 'admin@test.com'],
            ['name' => 'Admin', 'password' => Hash::make('admin1234')],
        );
        $admin->assignRole('admin');
    }
}
