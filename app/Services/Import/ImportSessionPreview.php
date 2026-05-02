<?php

namespace App\Services\Import;

use App\Enums\ImportModelType;
use App\Models\Contact;
use App\Models\Donation;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\ImportSession;
use App\Models\ImportStagedUpdate;
use App\Models\Membership;
use App\Models\Note;
use App\Models\Organization;
use App\Models\Transaction;
use Illuminate\Contracts\View\View;

/**
 * Renders the Preview modal body for an ImportSession. Dispatches on
 * `model_type` to one of four review blades:
 *
 *   event           → import-events-review-preview
 *   donation        → import-financial-review-preview
 *   membership      → import-financial-review-preview
 *   invoice_detail  → import-financial-review-preview
 *   note            → import-notes-review-preview
 *   contact (default) → import-review-preview
 *
 * Called from ImporterPage's preview action modalContent closure.
 */
class ImportSessionPreview
{
    public function render(ImportSession $session): View
    {
        return match (true) {
            $session->model_type === ImportModelType::Event => $this->eventPreview($session),
            in_array($session->model_type, [
                ImportModelType::Donation,
                ImportModelType::Membership,
                ImportModelType::InvoiceDetail,
            ], true) => $this->financialPreview($session),
            $session->model_type === ImportModelType::Note         => $this->notePreview($session),
            $session->model_type === ImportModelType::Organization => $this->organizationPreview($session),
            default => $this->contactPreview($session),
        };
    }

    private function organizationPreview(ImportSession $session): View
    {
        $organizations = Organization::where('import_session_id', $session->id)
            ->latest('created_at')
            ->limit(20)
            ->get(['id', 'name', 'type', 'website', 'email', 'city', 'state']);

        $orgsTotal   = Organization::where('import_session_id', $session->id)->count();
        $stagedTotal = ImportStagedUpdate::where('import_session_id', $session->id)->count();

        return view('filament.pages.import-organizations-review-preview', compact(
            'organizations', 'orgsTotal', 'stagedTotal'
        ));
    }

    private function eventPreview(ImportSession $session): View
    {
        $events = Event::where('import_session_id', $session->id)
            ->limit(20)
            ->get(['id', 'title', 'starts_at', 'ends_at', 'status']);

        $eventsTotal = Event::where('import_session_id', $session->id)->count();

        $registrations = EventRegistration::where('import_session_id', $session->id)
            ->with(['event:id,title', 'contact:id,first_name,last_name,email'])
            ->limit(20)
            ->get();

        $registrationsTotal = EventRegistration::where('import_session_id', $session->id)->count();

        $transactionsTotal = Transaction::where('import_session_id', $session->id)->count();

        return view('filament.pages.import-events-review-preview', compact(
            'events', 'eventsTotal', 'registrations', 'registrationsTotal', 'transactionsTotal'
        ));
    }

    private function financialPreview(ImportSession $session): View
    {
        $donations = $session->model_type === ImportModelType::Donation
            ? Donation::where('import_session_id', $session->id)
                ->with(['contact:id,first_name,last_name,email'])
                ->latest('created_at')
                ->limit(20)
                ->get()
            : collect();

        $memberships = $session->model_type === ImportModelType::Membership
            ? Membership::where('import_session_id', $session->id)
                ->with(['contact:id,first_name,last_name,email'])
                ->latest('starts_on')
                ->limit(20)
                ->get()
            : collect();

        $transactions = $session->model_type === ImportModelType::InvoiceDetail
            ? Transaction::where('import_session_id', $session->id)
                ->latest('occurred_at')
                ->limit(20)
                ->get()
            : collect();

        $contacts = Contact::withoutGlobalScopes()
            ->where('import_session_id', $session->id)
            ->limit(20)
            ->get(['id', 'first_name', 'last_name', 'email']);

        return view('filament.pages.import-financial-review-preview', [
            'record'            => $session,
            'donations'         => $donations,
            'memberships'       => $memberships,
            'transactions'      => $transactions,
            'contacts'          => $contacts,
            'donationsCount'    => $session->model_type === ImportModelType::Donation ? Donation::where('import_session_id', $session->id)->count() : 0,
            'membershipsCount'  => $session->model_type === ImportModelType::Membership ? Membership::where('import_session_id', $session->id)->count() : 0,
            'transactionsCount' => Transaction::where('import_session_id', $session->id)->count(),
            'contactsCount'     => Contact::withoutGlobalScopes()->where('import_session_id', $session->id)->count(),
        ]);
    }

    private function notePreview(ImportSession $session): View
    {
        $notes = Note::where('import_session_id', $session->id)
            ->with(['notable' => function ($q) {
                $q->select('id', 'first_name', 'last_name', 'email');
            }])
            ->latest('occurred_at')
            ->limit(20)
            ->get();

        $notesTotal  = Note::where('import_session_id', $session->id)->count();
        $stagedTotal = ImportStagedUpdate::where('import_session_id', $session->id)->count();

        return view('filament.pages.import-notes-review-preview', compact('notes', 'notesTotal', 'stagedTotal'));
    }

    private function contactPreview(ImportSession $session): View
    {
        $contacts = Contact::withoutGlobalScopes()
            ->where('import_session_id', $session->id)
            ->limit(20)
            ->get(['id', 'first_name', 'last_name', 'email', 'phone', 'city', 'state']);

        $total = Contact::withoutGlobalScopes()
            ->where('import_session_id', $session->id)
            ->count();

        $stagedUpdates = ImportStagedUpdate::where('import_session_id', $session->id)
            ->with('subject')
            ->limit(20)
            ->get();

        $stagedTotal = ImportStagedUpdate::where('import_session_id', $session->id)
            ->count();

        return view('filament.pages.import-review-preview', compact(
            'contacts', 'total', 'stagedUpdates', 'stagedTotal'
        ));
    }
}
