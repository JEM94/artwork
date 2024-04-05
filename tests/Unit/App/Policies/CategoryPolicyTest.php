<?php

namespace Tests\Unit\App\Policies;

use App\Models\User;
use App\Policies\CategoryPolicy;
use App\Enums\PermissionNameEnum;
use Tests\TestCase;

class CategoryPolicyTest extends TestCase
{
    public function testViewAny(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(PermissionNameEnum::PROJECT_SETTINGS_UPDATE->value);

        $policy = new CategoryPolicy();

        $this->assertTrue($policy->viewAny($user));
    }

    public function testCreate(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(PermissionNameEnum::PROJECT_SETTINGS_UPDATE->value);

        $policy = new CategoryPolicy();

        $this->assertTrue($policy->create($user));
    }

    public function testUpdate(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(PermissionNameEnum::PROJECT_SETTINGS_UPDATE->value);

        $policy = new CategoryPolicy();

        $this->assertTrue($policy->update($user));
    }

    public function testDelete(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(PermissionNameEnum::PROJECT_SETTINGS_UPDATE->value);

        $policy = new CategoryPolicy();

        $this->assertTrue($policy->delete($user));
    }
}
