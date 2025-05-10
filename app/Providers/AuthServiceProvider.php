<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\Challange;
use App\Models\ChallangeCategory;
use App\Models\Event;
use App\Models\Lab;
use App\Models\LabCategory;
use App\Policies\AdminPolicy;
use App\Policies\ChallangePolicy;
use App\Policies\ChallangeCategoryPolicy;
use App\Policies\EventPolicy;
use App\Policies\LabPolicy;
use App\Policies\LabCategoryPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Admin::class => AdminPolicy::class,
        Lab::class => LabPolicy::class,
        LabCategory::class => LabCategoryPolicy::class,
        Event::class => EventPolicy::class,
        Challange::class => ChallangePolicy::class,
        ChallangeCategory::class => ChallangeCategoryPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
} 