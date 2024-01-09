<?php

namespace App\Providers;

use App\Enums\RoleNameEnum;
use App\Models\Area;
use App\Models\Category;
use App\Models\Freelancer;
use App\Models\ServiceProvider as ServiceProviderModel;
use App\Policies\FreelancerPolicy;
use App\Policies\ServiceProviderPolicy;
use Artwork\Modules\Checklist\Models\Checklist;
use App\Models\ChecklistTemplate;
use App\Models\Comment;
use App\Models\Contract;
use App\Models\Department;
use App\Models\Genre;
use App\Models\Invitation;
use App\Models\Project;
use App\Models\Sector;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Policies\AreaPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\ChecklistPolicy;
use App\Policies\ChecklistTemplatePolicy;
use App\Policies\CommentPolicy;
use App\Policies\ContractPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\GenrePolicy;
use App\Policies\InvitationPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\SectorPolicy;
use App\Policies\TaskTemplatePolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Invitation::class => InvitationPolicy::class,
        User::class => UserPolicy::class,
        Department::class => DepartmentPolicy::class,
        Project::class => ProjectPolicy::class,
        Checklist::class => ChecklistPolicy::class,
        Sector::class => SectorPolicy::class,
        Category::class => CategoryPolicy::class,
        Genre::class => GenrePolicy::class,
        Comment::class => CommentPolicy::class,
        ChecklistTemplate::class => ChecklistTemplatePolicy::class,
        TaskTemplate::class => TaskTemplatePolicy::class,
        Area::class => AreaPolicy::class,
        Contract::class => ContractPolicy::class,
        Freelancer::class => FreelancerPolicy::class,
        ServiceProviderModel::class => ServiceProviderPolicy::class
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // Implicitly grant "admin" role all permissions
        // This works in the app by using gate-related functions like auth()->user->can() and @can()
        Gate::before(function ($user) {
            return $user->hasRole(RoleNameEnum::ARTWORK_ADMIN->value) ? true : null;
        });
    }
}
