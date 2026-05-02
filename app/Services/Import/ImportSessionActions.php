<?php

namespace App\Services\Import;

use App\Enums\ImportModelType;
use App\Models\Contact;
use App\Models\Donation;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\ImportIdMap;
use App\Models\ImportSession;
use App\Models\ImportStagedUpdate;
use App\Models\Membership;
use App\Models\Note;
use App\Models\Organization;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Performs approve / rollback / delete on ImportSession records with the
 * per-model_type cascade logic. Factored out of ImporterPage so the three
 * Filament action closures can stay thin and the logic can be tested
 * without mounting a Livewire component.
 *
 * Permission enforcement is NOT the service's responsibility — every caller
 * is required to check `review_imports` before invoking these methods.
 * ImporterPage enforces this via `abort_unless(...)` inside the action
 * closure (the third layer of defense alongside `hidden()` and
 * `requiresConfirmation()`); the service stays permission-agnostic so it
 * can be called from commands, jobs, or tests without stubbing auth.
 */
class ImportSessionActions
{
    public function approve(ImportSession $session): void
    {
        $session->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        $staged        = ImportStagedUpdate::where('import_session_id', $session->id)->get();
        $subjects      = $this->preloadStagedSubjects($staged);
        $sourceName    = $session->importSource?->name;
        $sourceId      = $session->importSource?->id;
        $sessionLabel  = $session->session_label ?: $session->filename;
        $sourceDisplay = $sourceName ?: 'unknown source';
        $approverName  = auth()->user()?->name;

        foreach ($staged as $update) {
            $subject = $subjects[$update->subject_type][$update->subject_id] ?? null;

            if (! $subject) {
                continue;
            }

            if (! empty($update->attributes)) {
                $subject->fill($update->attributes)->save();
            }

            if ($subject instanceof Contact) {
                if (! empty($update->tag_ids)) {
                    $subject->tags()->syncWithoutDetaching($update->tag_ids);
                }

                Note::create([
                    'notable_type'     => Contact::class,
                    'notable_id'       => $subject->id,
                    'author_id'        => auth()->id(),
                    'body'             => "Changes applied from import from {$sourceDisplay} (session: {$sessionLabel}) — approved by {$approverName}",
                    'occurred_at'      => now(),
                    'import_source_id' => $sourceId,
                ]);
            }
        }

        $staged->each->delete();
    }

    public function rollback(ImportSession $session): void
    {
        if ($session->model_type === ImportModelType::Event) {
            $this->rollBackEventSession($session);
            $session->delete();
            return;
        }

        if (in_array($session->model_type, [ImportModelType::Donation, ImportModelType::Membership, ImportModelType::InvoiceDetail], true)) {
            $this->rollBackFinancialSession($session);
            $session->delete();
            return;
        }

        if ($session->model_type === ImportModelType::Note) {
            $this->rollBackNoteSession($session);
            $session->delete();
            return;
        }

        if ($session->model_type === ImportModelType::Organization) {
            $this->rollBackOrganizationSession($session);
            $session->delete();
            return;
        }

        // Contacts (default)
        $contactIds = Contact::withoutGlobalScopes()
            ->where('import_session_id', $session->id)
            ->pluck('id')
            ->toArray();

        if (! empty($contactIds)) {
            DB::table('taggables')
                ->whereIn('taggable_id', $contactIds)
                ->where('taggable_type', Contact::class)
                ->delete();

            Contact::withoutGlobalScopes()
                ->whereIn('id', $contactIds)
                ->forceDelete();
        }

        $staged        = ImportStagedUpdate::where('import_session_id', $session->id)->get();
        $subjects      = $this->preloadStagedSubjects($staged);
        $sourceName    = $session->importSource?->name;
        $sourceId      = $session->importSource?->id;
        $sessionLabel  = $session->session_label ?: $session->filename;
        $sourceDisplay = $sourceName ?: 'unknown source';

        foreach ($staged as $update) {
            $subject = $subjects[$update->subject_type][$update->subject_id] ?? null;

            if ($subject instanceof Contact) {
                Note::create([
                    'notable_type'     => Contact::class,
                    'notable_id'       => $subject->id,
                    'author_id'        => auth()->id(),
                    'body'             => "Staged changes from import from {$sourceDisplay} (session: {$sessionLabel}) were discarded during rollback.",
                    'occurred_at'      => now(),
                    'import_source_id' => $sourceId,
                ]);
            }
        }

        $staged->each->delete();

        // Delete the session itself — import_id_maps are preserved
        $session->delete();
    }

    public function delete(ImportSession $session): void
    {
        if ($session->model_type === ImportModelType::Event) {
            $this->rollBackEventSession($session);
            $session->delete();
            return;
        }

        if (in_array($session->model_type, [ImportModelType::Donation, ImportModelType::Membership, ImportModelType::InvoiceDetail], true)) {
            $this->rollBackFinancialSession($session);
            $session->delete();
            return;
        }

        if ($session->model_type === ImportModelType::Note) {
            $this->rollBackNoteSession($session);
            $session->delete();
            return;
        }

        if ($session->model_type === ImportModelType::Organization) {
            $this->rollBackOrganizationSession($session);
            $session->delete();
            return;
        }

        // Contacts (default)
        $contactIds = Contact::withoutGlobalScopes()
            ->where('import_session_id', $session->id)
            ->pluck('id')
            ->toArray();

        if (! empty($contactIds)) {
            DB::table('taggables')
                ->whereIn('taggable_id', $contactIds)
                ->where('taggable_type', Contact::class)
                ->delete();

            Contact::withoutGlobalScopes()
                ->whereIn('id', $contactIds)
                ->forceDelete();
        }

        ImportStagedUpdate::where('import_session_id', $session->id)->delete();

        $session->delete();
    }

    public function approveDescription(ImportSession $session): string
    {
        if ($session->model_type === ImportModelType::Event) {
            $events = Event::where('import_session_id', $session->id)->count();
            $regs   = EventRegistration::where('import_session_id', $session->id)->count();

            return "This will mark {$events} event(s) and {$regs} registration(s) as approved. This cannot be undone.";
        }

        if (in_array($session->model_type, [ImportModelType::Donation, ImportModelType::Membership, ImportModelType::InvoiceDetail], true)) {
            $label = match ($session->model_type) {
                ImportModelType::Donation      => 'donation(s)',
                ImportModelType::Membership    => 'membership(s)',
                ImportModelType::InvoiceDetail => 'transaction(s)',
            };
            $count = match ($session->model_type) {
                ImportModelType::Donation      => Donation::where('import_session_id', $session->id)->count(),
                ImportModelType::Membership    => Membership::where('import_session_id', $session->id)->count(),
                ImportModelType::InvoiceDetail => Transaction::where('import_session_id', $session->id)->count(),
            };

            return "This will approve {$count} {$label} from this import. This cannot be undone.";
        }

        if ($session->model_type === ImportModelType::Note) {
            $count  = Note::where('import_session_id', $session->id)->count();
            $staged = ImportStagedUpdate::where('import_session_id', $session->id)->count();

            $parts = ["This will approve {$count} note(s) from this import"];

            if ($staged > 0) {
                $parts[] = "and apply {$staged} staged update(s) to existing notes";
            }

            return implode(' ', $parts) . '. This cannot be undone.';
        }

        if ($session->model_type === ImportModelType::Organization) {
            $count  = Organization::where('import_session_id', $session->id)->count();
            $staged = ImportStagedUpdate::where('import_session_id', $session->id)->count();

            $parts = ["This will approve {$count} organization(s) from this import"];

            if ($staged > 0) {
                $parts[] = "and apply {$staged} staged update(s) to existing organizations";
            }

            return implode(' ', $parts) . '. This cannot be undone.';
        }

        return "This will make all {$session->row_count} contacts from this import visible to all users, and apply all staged updates to existing contacts. This cannot be undone.";
    }

    public function rollbackDescription(ImportSession $session): string
    {
        if ($session->model_type === ImportModelType::Event) {
            $events = Event::where('import_session_id', $session->id)->count();
            $regs   = EventRegistration::where('import_session_id', $session->id)->count();
            $tx     = Transaction::where('import_session_id', $session->id)->count();

            return "This will permanently delete {$events} event(s), {$regs} registration(s), and {$tx} transaction(s) created by this import. This cannot be undone.";
        }

        if (in_array($session->model_type, [ImportModelType::Donation, ImportModelType::Membership, ImportModelType::InvoiceDetail], true)) {
            return $this->financialRollbackDescription($session);
        }

        if ($session->model_type === ImportModelType::Note) {
            $count       = Note::where('import_session_id', $session->id)->count();
            $stagedCount = ImportStagedUpdate::where('import_session_id', $session->id)->count();

            $parts = ["This will permanently delete {$count} note(s) created by this import"];

            if ($stagedCount > 0) {
                $parts[] = "and discard {$stagedCount} staged update(s) to existing notes";
            }

            return implode(' ', $parts) . '. This cannot be undone.';
        }

        if ($session->model_type === ImportModelType::Organization) {
            $count       = Organization::where('import_session_id', $session->id)->count();
            $stagedCount = ImportStagedUpdate::where('import_session_id', $session->id)->count();

            $parts = ["This will permanently delete {$count} organization(s) created by this import"];

            if ($stagedCount > 0) {
                $parts[] = "and discard {$stagedCount} staged update(s) to existing organizations";
            }

            return implode(' ', $parts) . '. This cannot be undone.';
        }

        $count = Contact::withoutGlobalScopes()
            ->where('import_session_id', $session->id)
            ->count();

        $stagedCount = ImportStagedUpdate::where('import_session_id', $session->id)->count();

        $parts = ["This will permanently delete all {$count} new contacts from this import"];

        if ($stagedCount > 0) {
            $parts[] = "and discard {$stagedCount} staged update(s) to existing contacts";
        }

        return implode(' ', $parts) . '. This cannot be undone.';
    }

    public function deleteDescription(ImportSession $session): string
    {
        if ($session->model_type === ImportModelType::Event) {
            $events = Event::where('import_session_id', $session->id)->count();
            $regs   = EventRegistration::where('import_session_id', $session->id)->count();
            $tx     = Transaction::where('import_session_id', $session->id)->count();

            return "Permanently delete this session and cascade-delete {$events} event(s), {$regs} registration(s), and {$tx} transaction(s). ImportIdMap rows created by this session are also removed. This cannot be undone.";
        }

        if (in_array($session->model_type, [ImportModelType::Donation, ImportModelType::Membership, ImportModelType::InvoiceDetail], true)) {
            return $this->financialRollbackDescription($session);
        }

        if ($session->model_type === ImportModelType::Note) {
            $count = Note::where('import_session_id', $session->id)->count();

            return "Permanently delete this session and {$count} note(s) created by it. Any staged updates are discarded. This cannot be undone.";
        }

        if ($session->model_type === ImportModelType::Organization) {
            $count = Organization::where('import_session_id', $session->id)->count();

            return "Permanently delete this session and {$count} organization(s) created by it. Any staged updates are discarded. This cannot be undone.";
        }

        $count = Contact::withoutGlobalScopes()
            ->where('import_session_id', $session->id)
            ->count();

        return "Permanently delete this session and {$count} contact(s) created by it. Any staged updates are discarded. This cannot be undone.";
    }

    private function preloadStagedSubjects($staged): array
    {
        $idsByType = [];
        foreach ($staged as $update) {
            $class = $update->subject_type;
            if (! class_exists($class)) {
                continue;
            }
            $idsByType[$class] ??= [];
            $idsByType[$class][] = $update->subject_id;
        }

        $map = [];
        foreach ($idsByType as $class => $ids) {
            $query = $class::query();
            if (in_array(SoftDeletes::class, class_uses_recursive($class), true)) {
                $query->withTrashed();
            }
            if ($class === Contact::class) {
                $query->withoutGlobalScopes();
            }
            $keyName = (new $class)->getKeyName();
            $rows    = $query->whereIn($keyName, array_values(array_unique($ids)))->get();
            $map[$class] = [];
            foreach ($rows as $row) {
                $map[$class][$row->getKey()] = $row;
            }
        }

        return $map;
    }

    /**
     * Cascade rollback for an events import session: registrations → transactions
     * → events → id_maps. Order matters — registrations hold FKs to both
     * transactions and events. ImportIdMap rows for events and transactions
     * created by this session are removed so the next import starts clean.
     */
    private function rollBackEventSession(ImportSession $session): void
    {
        $eventIds = Event::where('import_session_id', $session->id)->pluck('id')->toArray();
        $txIds    = Transaction::where('import_session_id', $session->id)->pluck('id')->toArray();

        EventRegistration::where('import_session_id', $session->id)->delete();

        if (! empty($txIds)) {
            Transaction::whereIn('id', $txIds)->delete();
        }

        if (! empty($eventIds)) {
            Event::whereIn('id', $eventIds)->delete();

            ImportIdMap::where('import_source_id', $session->import_source_id)
                ->where('model_type', 'event')
                ->whereIn('model_uuid', $eventIds)
                ->delete();
        }

        if (! empty($txIds)) {
            ImportIdMap::where('import_source_id', $session->import_source_id)
                ->where('model_type', 'transaction')
                ->whereIn('model_uuid', $txIds)
                ->delete();
        }
    }

    private function rollBackNoteSession(ImportSession $session): void
    {
        Note::where('import_session_id', $session->id)->forceDelete();
        ImportStagedUpdate::where('import_session_id', $session->id)->delete();
    }

    private function rollBackOrganizationSession(ImportSession $session): void
    {
        $orgIds = Organization::where('import_session_id', $session->id)->pluck('id')->toArray();

        if (! empty($orgIds)) {
            DB::table('taggables')
                ->whereIn('taggable_id', $orgIds)
                ->where('taggable_type', Organization::class)
                ->delete();

            Note::where('notable_type', Organization::class)
                ->whereIn('notable_id', $orgIds)
                ->forceDelete();

            Organization::whereIn('id', $orgIds)->forceDelete();
        }

        ImportStagedUpdate::where('import_session_id', $session->id)->delete();
    }

    private function rollBackFinancialSession(ImportSession $session): void
    {
        if ($session->model_type === ImportModelType::Donation) {
            $donationIds = Donation::where('import_session_id', $session->id)->pluck('id')->toArray();

            // Delete transactions linked to these donations.
            if (! empty($donationIds)) {
                Transaction::where('subject_type', Donation::class)
                    ->whereIn('subject_id', $donationIds)
                    ->delete();

                Donation::whereIn('id', $donationIds)->delete();
            }

            // Also delete any transactions directly created by this session.
            Transaction::where('import_session_id', $session->id)->delete();
        }

        if ($session->model_type === ImportModelType::Membership) {
            Membership::where('import_session_id', $session->id)->forceDelete();
        }

        if ($session->model_type === ImportModelType::InvoiceDetail) {
            Transaction::where('import_session_id', $session->id)->delete();
        }

        // Delete auto-created contacts.
        $contactIds = Contact::withoutGlobalScopes()
            ->where('import_session_id', $session->id)
            ->pluck('id')
            ->toArray();

        if (! empty($contactIds)) {
            DB::table('taggables')
                ->whereIn('taggable_id', $contactIds)
                ->where('taggable_type', Contact::class)
                ->delete();

            Contact::withoutGlobalScopes()
                ->whereIn('id', $contactIds)
                ->forceDelete();
        }
    }

    private function financialRollbackDescription(ImportSession $session): string
    {
        $parts = [];

        if ($session->model_type === ImportModelType::Donation) {
            $donations = Donation::where('import_session_id', $session->id)->count();
            $parts[]   = "{$donations} donation(s)";
        }

        if ($session->model_type === ImportModelType::Membership) {
            $memberships = Membership::where('import_session_id', $session->id)->count();
            $parts[]     = "{$memberships} membership(s)";
        }

        $tx = Transaction::where('import_session_id', $session->id)->count();
        if ($tx > 0) {
            $parts[] = "{$tx} transaction(s)";
        }

        $contacts = Contact::withoutGlobalScopes()
            ->where('import_session_id', $session->id)
            ->count();
        if ($contacts > 0) {
            $parts[] = "{$contacts} auto-created contact(s)";
        }

        return "This will permanently delete " . implode(', ', $parts) . " created by this import. This cannot be undone.";
    }
}
