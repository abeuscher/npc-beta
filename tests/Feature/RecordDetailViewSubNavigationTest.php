<?php

use App\Filament\Concerns\HasRecordDetailSubNavigation;
use App\Models\Contact;
use App\WidgetPrimitive\Views\RecordDetailView;
use Filament\Navigation\NavigationItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

class StubViewSubPage_AlwaysAccessible
{
    public static function canAccess(array $params = []): bool
    {
        return true;
    }

    public static function getUrl(array $params = []): string
    {
        return '/stub';
    }

    public static function getRouteName(): string
    {
        return 'stub';
    }
}

class StubViewSubPage_NeverAccessible
{
    public static function canAccess(array $params = []): bool
    {
        return false;
    }

    public static function getUrl(array $params = []): string
    {
        return '/never';
    }

    public static function getRouteName(): string
    {
        return 'never';
    }
}

class StubAdditionalPage_AlwaysAccessible
{
    public static function canAccess(array $params = []): bool
    {
        return true;
    }

    public static function getNavigationItems(array $params = []): array
    {
        return [NavigationItem::make('Stub')->url('/stub')];
    }
}

class StubAdditionalPage_NeverAccessible
{
    public static function canAccess(array $params = []): bool
    {
        return false;
    }

    public static function getNavigationItems(array $params = []): array
    {
        return [NavigationItem::make('Never')->url('/never')];
    }
}

beforeEach(function () {
    $this->host = new class {
        use HasRecordDetailSubNavigation;

        public Model $record;

        /** @var array<int, class-string> */
        public array $extraPages = [];

        public ?string $subPageClass = StubViewSubPage_AlwaysAccessible::class;

        protected function additionalSubNavigationPages(): array
        {
            return $this->extraPages;
        }

        protected function recordDetailViewSubPageClass(): ?string
        {
            return $this->subPageClass;
        }
    };
});

it('returns [] when zero Views and zero additional pages', function () {
    $this->host->record = Contact::factory()->create();

    expect($this->host->getSubNavigation())->toBe([]);
});

it('returns [] when only one sub-page exists (one View, no additional pages)', function () {
    $this->host->record = Contact::factory()->create();

    RecordDetailView::factory()->create([
        'record_type' => Contact::class,
        'handle'      => 'contact_overview',
        'label'       => 'Overview',
    ]);

    expect($this->host->getSubNavigation())->toBe([]);
});

it('builds NavigationItems for each View, ordered by sort_order, when total >= 2', function () {
    $this->host->record = Contact::factory()->create();

    RecordDetailView::factory()->create([
        'record_type' => Contact::class,
        'handle'      => 'a',
        'label'       => 'A',
        'sort_order'  => 0,
    ]);
    RecordDetailView::factory()->create([
        'record_type' => Contact::class,
        'handle'      => 'b',
        'label'       => 'B',
        'sort_order'  => 1,
    ]);

    $items = $this->host->getSubNavigation();

    expect($items)->toHaveCount(2)
        ->and($items[0])->toBeInstanceOf(NavigationItem::class)
        ->and($items[0]->getLabel())->toBe('A')
        ->and($items[1]->getLabel())->toBe('B');
});

it('appends additional Filament-page sub-nav entries after View entries', function () {
    $this->host->record = Contact::factory()->create();

    RecordDetailView::factory()->create([
        'record_type' => Contact::class,
        'handle'      => 'overview',
        'label'       => 'Overview',
        'sort_order'  => 0,
    ]);

    $this->host->extraPages = [StubAdditionalPage_AlwaysAccessible::class];

    $items = $this->host->getSubNavigation();

    expect($items)->toHaveCount(2)
        ->and($items[0]->getLabel())->toBe('Overview')
        ->and($items[1]->getLabel())->toBe('Stub');
});

it('drops View entries when the sub-page class is not accessible', function () {
    $this->host->record = Contact::factory()->create();
    $this->host->subPageClass = StubViewSubPage_NeverAccessible::class;
    $this->host->extraPages = [StubAdditionalPage_AlwaysAccessible::class, StubAdditionalPage_AlwaysAccessible::class];

    RecordDetailView::factory()->create([
        'record_type' => Contact::class,
        'handle'      => 'overview',
        'label'       => 'Overview',
    ]);

    $items = $this->host->getSubNavigation();

    expect($items)->toHaveCount(2);
    foreach ($items as $item) {
        expect($item->getLabel())->toBe('Stub');
    }
});

it('drops additional pages whose canAccess returns false', function () {
    $this->host->record = Contact::factory()->create();
    $this->host->extraPages = [
        StubAdditionalPage_AlwaysAccessible::class,
        StubAdditionalPage_NeverAccessible::class,
    ];

    RecordDetailView::factory()->create([
        'record_type' => Contact::class,
        'handle'      => 'overview',
        'label'       => 'Overview',
    ]);

    $items = $this->host->getSubNavigation();

    expect($items)->toHaveCount(2)
        ->and($items[0]->getLabel())->toBe('Overview')
        ->and($items[1]->getLabel())->toBe('Stub');
});

it('returns [] when sub-page class is null even with seeded Views', function () {
    $this->host->record = Contact::factory()->create();
    $this->host->subPageClass = null;

    RecordDetailView::factory()->create(['record_type' => Contact::class, 'handle' => 'a', 'label' => 'A']);
    RecordDetailView::factory()->create(['record_type' => Contact::class, 'handle' => 'b', 'label' => 'B']);

    expect($this->host->getSubNavigation())->toBe([]);
});
