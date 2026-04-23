<?php

namespace App\Providers;

use App\Services\PageContextTokens;
use App\Services\WidgetAssetResolver;
use App\Services\WidgetConfigResolver;
use App\Services\WidgetRegistry;
use App\WidgetPrimitive\SlotRegistry;
use App\WidgetPrimitive\Slots\DashboardGridSlot;
use App\WidgetPrimitive\Slots\PageBuilderCanvasSlot;
use App\WidgetPrimitive\Slots\RecordDetailSidebarSlot;
use App\Widgets\BarChart\BarChartDefinition;
use App\Widgets\BlogListing\BlogListingDefinition;
use App\Widgets\BlogPager\BlogPagerDefinition;
use App\Widgets\BoardMembers\BoardMembersDefinition;
use App\Widgets\Carousel\CarouselDefinition;
use App\Widgets\DonationForm\DonationFormDefinition;
use App\Widgets\EventCalendar\EventCalendarDefinition;
use App\Widgets\EventDescription\EventDescriptionDefinition;
use App\Widgets\EventRegistration\EventRegistrationDefinition;
use App\Widgets\EventsListing\EventsListingDefinition;
use App\Widgets\Hero\HeroDefinition;
use App\Widgets\Image\ImageDefinition;
use App\Widgets\Logo\LogoDefinition;
use App\Widgets\LogoGarden\LogoGardenDefinition;
use App\Widgets\MapEmbed\MapEmbedDefinition;
use App\Widgets\Nav\NavDefinition;
use App\Widgets\PortalAccountDashboard\PortalAccountDashboardDefinition;
use App\Widgets\PortalChangePassword\PortalChangePasswordDefinition;
use App\Widgets\PortalContactEdit\PortalContactEditDefinition;
use App\Widgets\PortalEventRegistrations\PortalEventRegistrationsDefinition;
use App\Widgets\PortalForgotPassword\PortalForgotPasswordDefinition;
use App\Widgets\PortalLogin\PortalLoginDefinition;
use App\Widgets\PortalSignup\PortalSignupDefinition;
use App\Widgets\ProductCarousel\ProductCarouselDefinition;
use App\Widgets\ProductDisplay\ProductDisplayDefinition;
use App\Widgets\SocialSharing\SocialSharingDefinition;
use App\Widgets\TextBlock\TextBlockDefinition;
use App\Widgets\ThreeBuckets\ThreeBucketsDefinition;
use App\Widgets\VideoEmbed\VideoEmbedDefinition;
use App\Widgets\WebForm\WebFormDefinition;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class WidgetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WidgetRegistry::class, fn () => new WidgetRegistry());
        $this->app->singleton(WidgetConfigResolver::class, fn ($app) => new WidgetConfigResolver($app->make(WidgetRegistry::class)));
        $this->app->singleton(PageContextTokens::class, fn () => new PageContextTokens());
        $this->app->singleton(SlotRegistry::class, fn () => new SlotRegistry());
        $this->app->singleton(WidgetAssetResolver::class, fn () => new WidgetAssetResolver());
    }

    public function boot(): void
    {
        View::addNamespace('widgets', app_path('Widgets'));

        $slots = $this->app->make(SlotRegistry::class);
        $slots->register(new PageBuilderCanvasSlot());
        $slots->register(new DashboardGridSlot());
        $slots->register(new RecordDetailSidebarSlot());

        $registry = $this->app->make(WidgetRegistry::class);

        $registry->register(new TextBlockDefinition());
        $registry->register(new EventDescriptionDefinition());
        $registry->register(new EventRegistrationDefinition());
        $registry->register(new EventsListingDefinition());
        $registry->register(new BlogListingDefinition());
        $registry->register(new BlogPagerDefinition());
        $registry->register(new WebFormDefinition());
        $registry->register(new PortalSignupDefinition());
        $registry->register(new PortalLoginDefinition());
        $registry->register(new PortalContactEditDefinition());
        $registry->register(new PortalChangePasswordDefinition());
        $registry->register(new PortalEventRegistrationsDefinition());
        $registry->register(new PortalForgotPasswordDefinition());
        $registry->register(new PortalAccountDashboardDefinition());
        $registry->register(new ProductDisplayDefinition());
        $registry->register(new ProductCarouselDefinition());
        $registry->register(new DonationFormDefinition());
        $registry->register(new ImageDefinition());
        $registry->register(new LogoDefinition());
        $registry->register(new VideoEmbedDefinition());
        $registry->register(new BarChartDefinition());
        $registry->register(new EventCalendarDefinition());
        $registry->register(new CarouselDefinition());
        $registry->register(new LogoGardenDefinition());
        $registry->register(new BoardMembersDefinition());
        $registry->register(new ThreeBucketsDefinition());
        $registry->register(new HeroDefinition());
        $registry->register(new MapEmbedDefinition());
        $registry->register(new SocialSharingDefinition());
        $registry->register(new NavDefinition());
    }
}
