<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Models\UserGroup;
use Illuminate\Database\Seeder;

class UserGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = UserGroup::create([
            'name' => 'Administrators',
            'slug' => 'admin',
            'description' => 'Full system access',
            'is_system' => true,
            'is_default' => false,
        ]);

        $userGroup = UserGroup::create([
            'name' => 'Users',
            'slug' => 'user',
            'description' => 'Standard user access',
            'is_system' => true,
            'is_default' => true,
        ]);

        $this->assignAdminPermissions($admin);
        $this->assignUserPermissions($userGroup);
    }

    private function assignAdminPermissions(UserGroup $admin): void
    {
        foreach (Permission::all() as $permission) {
            $admin->permissions()->create([
                'permission' => $permission,
                'resource_type' => null,
                'resource_id' => null,
            ]);
        }
    }

    private function assignUserPermissions(UserGroup $userGroup): void
    {
        $basicPermissions = [
            Permission::USERS_VIEW->value,
        ];

        foreach ($basicPermissions as $permission) {
            $userGroup->permissions()->create([
                'permission' => $permission,
                'resource_type' => null,
                'resource_id' => null,
            ]);
        }
    }
}
