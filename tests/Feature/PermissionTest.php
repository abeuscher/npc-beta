<?php

namespace Tests\Feature;

use App\Models\CmsTag;
use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Contact;
use App\Models\Donation;
use App\Models\Fund;
use App\Models\NavigationItem;
use App\Models\Organization;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use App\Models\WidgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder']);
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);
        return $user;
    }

    // ── super_admin ──────────────────────────────────────────────────────────

    public function test_super_admin_can_access_crm(): void
    {
        $user    = $this->makeUser('super_admin');
        $contact = Contact::factory()->create();

        $this->assertTrue($user->can('viewAny', Contact::class));
        $this->assertTrue($user->can('create', Contact::class));
        $this->assertTrue($user->can('update', $contact));
        $this->assertTrue($user->can('delete', $contact));
    }

    public function test_super_admin_can_access_finance(): void
    {
        $user     = $this->makeUser('super_admin');
        $donation = Donation::factory()->create();

        $this->assertTrue($user->can('viewAny', Donation::class));
        $this->assertTrue($user->can('create', Donation::class));
        $this->assertTrue($user->can('update', $donation));
    }

    public function test_super_admin_can_access_cms(): void
    {
        $user = $this->makeUser('super_admin');
        $post = Post::factory()->create();

        $this->assertTrue($user->can('viewAny', Post::class));
        $this->assertTrue($user->can('create', Post::class));
        $this->assertTrue($user->can('update', $post));
    }

    public function test_super_admin_can_access_admin_resources(): void
    {
        $user   = $this->makeUser('super_admin');
        $widget = WidgetType::factory()->create();

        $this->assertTrue($user->can('viewAny', User::class));
        $this->assertTrue($user->can('create', User::class));
        $this->assertTrue($user->can('viewAny', WidgetType::class));
        $this->assertTrue($user->can('update', $widget));
    }

    // ── cms_editor ───────────────────────────────────────────────────────────

    public function test_cms_editor_can_manage_posts(): void
    {
        $user = $this->makeUser('cms_editor');
        $post = Post::factory()->create();

        $this->assertTrue($user->can('viewAny', Post::class));
        $this->assertTrue($user->can('view', $post));
        $this->assertTrue($user->can('create', Post::class));
        $this->assertTrue($user->can('update', $post));
        $this->assertTrue($user->can('delete', $post));
    }

    public function test_cms_editor_can_manage_pages(): void
    {
        $user = $this->makeUser('cms_editor');
        $page = Page::factory()->create();

        $this->assertTrue($user->can('viewAny', Page::class));
        $this->assertTrue($user->can('create', Page::class));
        $this->assertTrue($user->can('update', $page));
        $this->assertTrue($user->can('delete', $page));
    }

    public function test_cms_editor_can_browse_collections(): void
    {
        $user       = $this->makeUser('cms_editor');
        $collection = Collection::factory()->create();

        $this->assertTrue($user->can('viewAny', Collection::class));
        $this->assertTrue($user->can('view', $collection));
    }

    public function test_cms_editor_cannot_create_or_delete_collections(): void
    {
        $user       = $this->makeUser('cms_editor');
        $collection = Collection::factory()->create();

        $this->assertFalse($user->can('create', Collection::class));
        $this->assertFalse($user->can('update', $collection));
        $this->assertFalse($user->can('delete', $collection));
    }

    public function test_cms_editor_can_manage_collection_items(): void
    {
        $user = $this->makeUser('cms_editor');
        $item = CollectionItem::factory()->create();

        $this->assertTrue($user->can('viewAny', CollectionItem::class));
        $this->assertTrue($user->can('create', CollectionItem::class));
        $this->assertTrue($user->can('update', $item));
        $this->assertTrue($user->can('delete', $item));
    }

    public function test_cms_editor_can_manage_cms_tags(): void
    {
        $user   = $this->makeUser('cms_editor');
        $cmsTag = CmsTag::factory()->create();

        $this->assertTrue($user->can('viewAny', CmsTag::class));
        $this->assertTrue($user->can('create', CmsTag::class));
        $this->assertTrue($user->can('update', $cmsTag));
    }

    public function test_cms_editor_cannot_access_crm(): void
    {
        $user = $this->makeUser('cms_editor');

        $this->assertFalse($user->can('viewAny', Contact::class));
        $this->assertFalse($user->can('create', Contact::class));
        $this->assertFalse($user->can('viewAny', Organization::class));
    }

    public function test_cms_editor_cannot_access_finance(): void
    {
        $user = $this->makeUser('cms_editor');

        $this->assertFalse($user->can('viewAny', Donation::class));
        $this->assertFalse($user->can('create', Donation::class));
        $this->assertFalse($user->can('viewAny', Fund::class));
    }

    public function test_cms_editor_cannot_access_admin_resources(): void
    {
        $user = $this->makeUser('cms_editor');

        $this->assertFalse($user->can('viewAny', User::class));
        $this->assertFalse($user->can('viewAny', WidgetType::class));
    }

    public function test_cms_editor_cannot_access_navigation_items(): void
    {
        $user = $this->makeUser('cms_editor');
        $this->assertFalse($user->can('viewAny', NavigationItem::class));
    }

    // ── no-role user ─────────────────────────────────────────────────────────

    public function test_user_with_no_role_cannot_access_anything(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->assertFalse($user->can('viewAny', Contact::class));
        $this->assertFalse($user->can('viewAny', Post::class));
        $this->assertFalse($user->can('viewAny', Donation::class));
        $this->assertFalse($user->can('viewAny', User::class));
    }
}
