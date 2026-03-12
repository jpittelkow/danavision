<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * All permission values - must stay in sync with App\Enums\Permission
     */
    private const PERMISSIONS = [
        'users.view', 'users.create', 'users.edit', 'users.delete',
        'groups.view', 'groups.manage',
        'settings.view', 'settings.edit',
        'backups.view', 'backups.create', 'backups.restore', 'backups.delete',
        'logs.view', 'logs.export', 'audit.view',
    ];

    /**
     * Run the migrations.
     * Ensure default groups exist, then data-migrate is_admin to group membership and drop the column.
     */
    public function up(): void
    {
        // Always ensure default groups exist (idempotent) - needed for fresh installs too
        // Use direct SQL to avoid service container issues during migrations
        $this->ensureDefaultGroupsExist();

        // Skip is_admin migration if column doesn't exist (fresh install)
        if (!Schema::hasColumn('users', 'is_admin')) {
            return;
        }

        $adminGroupId = DB::table('user_groups')->where('slug', 'admin')->value('id');
        $userGroupId = DB::table('user_groups')->where('is_default', true)->value('id');

        if (!$adminGroupId || !$userGroupId) {
            throw new \RuntimeException('Default groups (admin, user) must exist but were not created.');
        }

        $now = now();

        foreach (DB::table('users')->where('is_admin', true)->pluck('id') as $userId) {
            DB::table('user_group_members')->insertOrIgnore([
                'user_id' => $userId,
                'group_id' => $adminGroupId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (DB::table('users')->where('is_admin', false)->pluck('id') as $userId) {
            $inAnyGroup = DB::table('user_group_members')->where('user_id', $userId)->exists();
            if (!$inAnyGroup) {
                DB::table('user_group_members')->insertOrIgnore([
                    'user_id' => $userId,
                    'group_id' => $userGroupId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('avatar');
        });

        $adminGroupId = DB::table('user_groups')->where('slug', 'admin')->value('id');
        if ($adminGroupId) {
            $adminUserIds = DB::table('user_group_members')
                ->where('group_id', $adminGroupId)
                ->pluck('user_id');
            DB::table('users')->whereIn('id', $adminUserIds)->update(['is_admin' => true]);
        }
    }

    /**
     * Ensure default groups (Administrators, Users) exist using direct SQL.
     * Self-contained to avoid service container issues during migrations.
     */
    private function ensureDefaultGroupsExist(): void
    {
        // Check if groups already exist (idempotent)
        if (DB::table('user_groups')->where('slug', 'admin')->exists()) {
            return;
        }

        $now = now();

        // Create admin group
        $adminGroupId = DB::table('user_groups')->insertGetId([
            'name' => 'Administrators',
            'slug' => 'admin',
            'description' => 'Full system access',
            'is_system' => true,
            'is_default' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Create default user group
        $userGroupId = DB::table('user_groups')->insertGetId([
            'name' => 'Users',
            'slug' => 'user',
            'description' => 'Standard user access',
            'is_system' => true,
            'is_default' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Assign all permissions to admin group
        foreach (self::PERMISSIONS as $permission) {
            DB::table('group_permissions')->insert([
                'group_id' => $adminGroupId,
                'permission' => $permission,
                'resource_type' => null,
                'resource_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Assign limited permissions to user group (only users.view)
        DB::table('group_permissions')->insert([
            'group_id' => $userGroupId,
            'permission' => 'users.view',
            'resource_type' => null,
            'resource_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
