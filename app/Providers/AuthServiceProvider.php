<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        \App\Models\Contact::class        => \App\Policies\ContactPolicy::class,
        \App\Models\Organization::class   => \App\Policies\OrganizationPolicy::class,
        \App\Models\Membership::class     => \App\Policies\MembershipPolicy::class,
        \App\Models\Note::class           => \App\Policies\NotePolicy::class,
        \App\Models\Tag::class            => \App\Policies\TagPolicy::class,
        \App\Models\Donation::class       => \App\Policies\DonationPolicy::class,
        \App\Models\Transaction::class    => \App\Policies\TransactionPolicy::class,
        \App\Models\Fund::class           => \App\Policies\FundPolicy::class,
        \App\Models\Campaign::class       => \App\Policies\CampaignPolicy::class,
        \App\Models\Page::class           => \App\Policies\PagePolicy::class,
        \App\Models\Collection::class     => \App\Policies\CollectionPolicy::class,
        \App\Models\CollectionItem::class => \App\Policies\CollectionItemPolicy::class,
        \App\Models\NavigationItem::class => \App\Policies\NavigationItemPolicy::class,
        \App\Models\User::class           => \App\Policies\UserPolicy::class,
        \App\Models\WidgetType::class     => \App\Policies\WidgetTypePolicy::class,
    ];

    public function boot(): void
    {
        // super_admin bypasses all policy checks
        Gate::before(function ($user, string $ability) {
            if ($user instanceof \App\Models\User && $user->hasRole('super_admin')) {
                return true;
            }
        });
    }
}
