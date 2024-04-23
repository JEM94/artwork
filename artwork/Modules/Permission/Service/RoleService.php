<?php

namespace Artwork\Modules\Permission\Service;

use Artwork\Modules\Permission\Models\Role;
use Artwork\Modules\Permission\Repositories\RoleRepository;
use Artwork\Modules\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;

readonly class RoleService
{
    public function __construct(private RoleRepository $roleRepository)
    {
    }

    public function save(Role $role): Role
    {
        $this->roleRepository->save($role);

        return $role;
    }

    public function createFromArray(array $data): Role
    {
        /** @var Role $role */
        $role = $this->roleRepository->createFromArray($data);

        return $role;
    }

    public function findByName(string $name): Role|null
    {
        /** @var Role|null $role */
        $role = $this->roleRepository->getByName($name);

        return $role;
    }

    /**
     * @return array<mixed, mixed>
     */
    public function getTableFields(): array
    {
        return DB::select('DESCRIBE `roles`');
    }
}
