<?php

namespace App\Tenancy;

use App\Core\Request;

class TenantContext
{
    public int $tenantId;
    public int $userId;
    public array $roles;
    public array $permissions;

    public function __construct(int $tenantId, int $userId, array $roles, array $permissions)
    {
        $this->tenantId = $tenantId;
        $this->userId = $userId;
        $this->roles = $roles;
        $this->permissions = $permissions;
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            (int) $request->getAttribute('tenant_id', 0),
            (int) $request->getAttribute('user_id', 0),
            (array) $request->getAttribute('roles', []),
            (array) $request->getAttribute('permissions', [])
        );
    }

    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'roles' => $this->roles,
            'permissions' => $this->permissions,
        ];
    }
}
