<?php

namespace App\Http\Controllers\Api;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGroupRequest;
use App\Http\Requests\UpdateGroupMembersRequest;
use App\Http\Requests\UpdateGroupPermissionsRequest;
use App\Http\Requests\UpdateGroupRequest;
use App\Http\Resources\GroupResource;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\AuditService;
use App\Services\GroupService;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    use \App\Http\Traits\AdminAuthorizationTrait;
    use \App\Http\Traits\ApiResponseTrait;

    public function __construct(
        private GroupService $groupService,
        private PermissionService $permissionService,
        private AuditService $auditService
    ) {}

    /**
     * List all groups with member count.
     */
    public function index(): JsonResponse
    {
        $groups = UserGroup::withCount('members')->orderBy('name')->get();

        return $this->dataResponse(['data' => GroupResource::collection($groups)]);
    }

    /**
     * Create a new group.
     */
    public function store(StoreGroupRequest $request): JsonResponse
    {
        $group = $this->groupService->create($request->validated());

        $this->auditService->log('group.created', $group, [], [
            'name' => $group->name,
            'slug' => $group->slug,
        ]);

        return $this->createdResponse('Group created successfully', [
            'group' => new GroupResource($group),
        ]);
    }

    /**
     * Get a single group.
     */
    public function show(UserGroup $group): JsonResponse
    {
        $group->loadCount('members');

        return $this->dataResponse(['group' => new GroupResource($group)]);
    }

    /**
     * Update a group.
     */
    public function update(UpdateGroupRequest $request, UserGroup $group): JsonResponse
    {
        $data = $request->validated();
        if ($group->is_system && isset($data['slug'])) {
            return $this->errorResponse('Cannot modify system group slug', 422);
        }

        $oldValues = $group->only(array_keys($data));
        $group = $this->groupService->update($group, $data);
        $newValues = $group->only(array_keys($data));

        $this->auditService->logModelChange($group, 'group.updated', $oldValues, $newValues);

        return $this->successResponse('Group updated successfully', [
            'group' => new GroupResource($group),
        ]);
    }

    /**
     * Delete a group.
     */
    public function destroy(UserGroup $group): JsonResponse
    {
        if ($group->is_system) {
            return $this->errorResponse('Cannot delete system group', 422);
        }

        $this->auditService->log('group.deleted', $group, [
            'name' => $group->name,
            'slug' => $group->slug,
        ], []);

        $this->groupService->delete($group);

        return $this->successResponse('Group deleted successfully');
    }

    /**
     * List group members (paginated).
     */
    public function members(Request $request, UserGroup $group): JsonResponse
    {
        $perPage = $request->input('per_page', config('app.pagination.default'));
        $members = $group->members()
            ->orderBy('name')
            ->paginate($perPage);

        return $this->dataResponse($members);
    }

    /**
     * Add members to a group.
     */
    public function addMembers(UpdateGroupMembersRequest $request, UserGroup $group): JsonResponse
    {
        $userIds = $request->validated()['user_ids'];
        $this->groupService->addMembers($group, $userIds);

        $this->auditService->log('group.member_added', $group, [], [
            'user_ids' => $userIds,
        ]);

        return $this->successResponse('Members added', []);
    }

    /**
     * Remove a member from a group.
     */
    public function removeMember(UserGroup $group, User $user): JsonResponse
    {
        if ($group->slug === 'admin') {
            if ($error = $this->ensureNotLastAdmin($user, 'remove from admin group')) {
                return $error;
            }
        }

        $this->groupService->removeMembers($group, [$user->id]);

        $this->auditService->log('group.member_removed', $group, [], [
            'user_id' => $user->id,
        ]);

        return $this->deleteResponse('Member removed');
    }

    /**
     * Get group permissions.
     */
    public function permissions(UserGroup $group): JsonResponse
    {
        $permissions = $group->permissions;

        return $this->dataResponse(['permissions' => $permissions]);
    }

    /**
     * Update group permissions.
     */
    public function updatePermissions(UpdateGroupPermissionsRequest $request, UserGroup $group): JsonResponse
    {
        $permissions = $request->validated()['permissions'];
        $this->groupService->setPermissions($group, $permissions);

        $this->auditService->log('group.permissions_updated', $group, [], [
            'count' => count($permissions),
        ]);

        return $this->successResponse('Permissions updated', []);
    }

    /**
     * List available permissions (for UI dropdowns / permission matrix).
     */
    public function availablePermissions(): JsonResponse
    {
        $categories = [];
        foreach (Permission::categories() as $name => $cases) {
            $categories[$name] = array_map(fn (Permission $p) => $p->value, $cases);
        }

        return $this->dataResponse([
            'permissions' => Permission::all(),
            'categories' => $categories,
        ]);
    }
}
