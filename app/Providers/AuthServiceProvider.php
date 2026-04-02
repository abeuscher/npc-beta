<?php

namespace App\Providers;

use App\Models\SiteSetting;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;

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
        \App\Models\Event::class          => \App\Policies\EventPolicy::class,
        \App\Models\Form::class           => \App\Policies\FormPolicy::class,
        \App\Models\Product::class        => \App\Policies\ProductPolicy::class,
        \App\Models\MailingList::class      => \App\Policies\MailingListPolicy::class,
        \App\Models\FormSubmission::class  => \App\Policies\FormSubmissionPolicy::class,
    ];

    public function boot(): void
    {
        // super_admin bypasses all policy checks
        Gate::before(function ($user, string $ability) {
            if ($user instanceof \App\Models\User && $user->hasRole('super_admin')) {
                return true;
            }
        });

        // Horizon dashboard — disabled by default, super_admin only when enabled
        Horizon::auth(function ($request) {
            if (SiteSetting::get('horizon_enabled', 'false') !== 'true') {
                return false;
            }

            return $request->user()?->hasRole('super_admin') ?? false;
        });
    }
}
