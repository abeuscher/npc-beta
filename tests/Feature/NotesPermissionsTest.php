<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\SiteSetting;
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

    // ── NotePolicy::update — toggle off (today's behaviour) ──────────────────

    public function test_update_toggle_off_author_allowed(): void
    {
        SiteSetting::set('notes_edit_only_by_creator', 'false');
        $author = $this->makeUser('crm_editor');
        $note = Note::factory()->create(['author_id' => $author->id]);

        $this->assertTrue($author->can('update', $note));
    }

    public function test_update_toggle_off_non_author_with_update_note_allowed(): void
    {
        SiteSetting::set('notes_edit_only_by_creator', 'false');
        $author = $this->makeUser('crm_editor');
        $other = $this->makeUser('crm_editor');
        $note = Note::factory()->create(['author_id' => $author->id]);

        $this->assertTrue($other->can('update', $note));
    }

    // ── NotePolicy::update — toggle on ───────────────────────────────────────

    public function test_update_toggle_on_author_allowed(): void
    {
        SiteSetting::set('notes_edit_only_by_creator', 'true');
        $author = $this->makeUser('crm_editor');
        $note = Note::factory()->create(['author_id' => $author->id]);

        $this->assertTrue($author->can('update', $note));
    }

    public function test_update_toggle_on_non_author_without_override_denied(): void
    {
        SiteSetting::set('notes_edit_only_by_creator', 'true');
        $author = $this->makeUser('crm_editor');
        $other = $this->makeUser('crm_editor');
        $note = Note::factory()->create(['author_id' => $author->id]);

        $this->assertFalse($other->can('update', $note));
    }

    public function test_update_toggle_on_non_author_with_override_allowed(): void
    {
        SiteSetting::set('notes_edit_only_by_creator', 'true');
        $author = $this->makeUser('crm_editor');
        $manager = $this->makeUser('developer');
        $note = Note::factory()->create(['author_id' => $author->id]);

        $this->assertTrue($manager->can('update', $note));
    }

    // ── NotePolicy::delete — toggle off ──────────────────────────────────────

    public function test_delete_toggle_off_author_allowed(): void
    {
        SiteSetting::set('notes_edit_only_by_creator', 'false');
        $author = $this->makeUser('crm_editor');
        $note = Note::factory()->create(['author_id' => $author->id]);

        $this->assertTrue($author->can('delete', $note));
    }

    public function test_delete_toggle_off_non_author_with_delete_note_allowed(): void
    {
        SiteSetting::set('notes_edit_only_by_creator', 'false');
        $author = $this->makeUser('crm_editor');
        $other = $this->makeUser('crm_editor');
        $note = Note::factory()->create(['author_id' => $author->id]);

        $this->assertTrue($other->can('delete', $note));
    }

    // ── NotePolicy::delete — toggle on ───────────────────────────────────────

    public function test_delete_toggle_on_author_allowed(): void
    {
        SiteSetting::set('notes_edit_only_by_creator', 'true');
        $author = $this->makeUser('crm_editor');
        $note = Note::factory()->create(['author_id' => $author->id]);

        $this->assertTrue($author->can('delete', $note));
    }

    public function test_delete_toggle_on_non_author_without_override_denied(): void
    {
        SiteSetting::set('notes_edit_only_by_creator', 'true');
        $author = $this->makeUser('crm_editor');
        $other = $this->makeUser('crm_editor');
        $note = Note::factory()->create(['author_id' => $author->id]);

        $this->assertFalse($other->can('delete', $note));
    }

    public function test_delete_toggle_on_non_author_with_override_allowed(): void
    {
        SiteSetting::set('notes_edit_only_by_creator', 'true');
        $author = $this->makeUser('crm_editor');
        $manager = $this->makeUser('developer');
        $note = Note::factory()->create(['author_id' => $author->id]);

        $this->assertTrue($manager->can('delete', $note));
    }

    // ── Outer capability gate — must still fire regardless of toggle ─────────

    public function test_update_without_update_note_capability_denied_toggle_off(): void
    {
        SiteSetting::set('notes_edit_only_by_creator', 'false');
        $author = $this->makeUser('crm_editor');
        $bystander = $this->makeUser('cms_editor');
        $note = Note::factory()->create(['author_id' => $author->id]);

        $this->assertFalse($bystander->can('update', $note));
    }

    public function test_update_without_update_note_capability_denied_toggle_on(): void
    {
        SiteSetting::set('notes_edit_only_by_creator', 'true');
        $author = $this->makeUser('crm_editor');
        $bystander = $this->makeUser('cms_editor');
        $note = Note::factory()->create(['author_id' => $bystander->id]);

        $this->assertFalse($bystander->can('update', $note));
    }

    public function test_delete_without_delete_note_capability_denied_toggle_off(): void
    {
        SiteSetting::set('notes_edit_only_by_creator', 'false');
        $author = $this->makeUser('crm_editor');
        $bystander = $this->makeUser('cms_editor');
        $note = Note::factory()->create(['author_id' => $author->id]);

        $this->assertFalse($bystander->can('delete', $note));
    }

    public function test_super_admin_bypasses_policy_when_toggle_on(): void
    {
        SiteSetting::set('notes_edit_only_by_creator', 'true');
        $author = $this->makeUser('crm_editor');
        $superAdmin = $this->makeUser('super_admin');
        $note = Note::factory()->create(['author_id' => $author->id]);

        $this->assertTrue($superAdmin->can('update', $note));
        $this->assertTrue($superAdmin->can('delete', $note));
    }

    public function test_default_toggle_state_preserves_pre_276_behaviour(): void
    {
        $this->assertFalse(
            SiteSetting::where('key', 'notes_edit_only_by_creator')->exists(),
            'notes_edit_only_by_creator should not be auto-seeded — default-off behaviour relies on the SiteSetting::get default.',
        );

        $author = $this->makeUser('crm_editor');
        $other = $this->makeUser('crm_editor');
        $note = Note::factory()->create(['author_id' => $author->id]);

        $this->assertTrue($other->can('update', $note));
        $this->assertTrue($other->can('delete', $note));
    }
}
