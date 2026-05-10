<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NotesPermissionsTest extends TestCase
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

    public function test_edit_others_note_permission_is_registered(): void
    {
        $this->assertTrue(
            Permission::where('name', 'edit_others_note')->where('guard_name', 'web')->exists(),
            'Permission edit_others_note is missing from the seeder.',
        );
    }

    public function test_developer_role_has_edit_others_note(): void
    {
        $user = $this->makeUser('developer');

        $this->assertTrue($user->can('edit_others_note'));
    }

    public function test_super_admin_passes_edit_others_note_via_gate_bypass(): void
    {
        $user = $this->makeUser('super_admin');

        $this->assertTrue($user->can('edit_others_note'));
    }

    public function test_crm_editor_does_not_have_edit_others_note(): void
    {
        $user = $this->makeUser('crm_editor');

        $this->assertFalse($user->can('edit_others_note'));
    }

    public function test_volunteer_coordinator_does_not_have_edit_others_note(): void
    {
        $user = $this->makeUser('volunteer_coordinator');

        $this->assertFalse($user->can('edit_others_note'));
    }

    public function test_cms_editor_does_not_have_edit_others_note(): void
    {
        $user = $this->makeUser('cms_editor');

        $this->assertFalse($user->can('edit_others_note'));
    }
}
