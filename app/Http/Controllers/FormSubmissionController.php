<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Note;
use App\Services\PiiScanner;
use Illuminate\Http\Request;

class FormSubmissionController extends Controller
{
    public function store(Request $request, string $handle)
    {
        $form = Form::where('handle', $handle)->where('is_active', true)->firstOrFail();

        // ── Honeypot — silently succeed if bot fills the hidden field ──────
        if (($form->settings['honeypot'] ?? true) && $request->filled('_hp')) {
            return $this->successResponse($request, $form);
        }

        // ── PII screen — reject if any field handle or value triggers it ───
        $scanner = new PiiScanner();

        $fieldHandles = collect($form->fields ?? [])->pluck('handle')->filter()->values()->toArray();
        if ($scanner->scanHeaders($fieldHandles) !== null) {
            return $this->errorResponse($request, 'One or more fields contain information that cannot be accepted.');
        }

        foreach ($request->only($fieldHandles) as $value) {
            if (is_string($value) && $value !== '' && $scanner->scanCell($value) !== null) {
                return $this->errorResponse($request, 'One or more fields contain information that cannot be accepted.');
            }
        }

        // ── Validation ────────────────────────────────────────────────────
        $rules = $form->fieldValidationRules();
        $validated = $request->validate($rules);

        // ── Store only keys that match field handles ───────────────────────
        $data = array_intersect_key($validated, array_flip($fieldHandles));

        // Write hidden field default values into the submission data
        foreach ($form->fields ?? [] as $field) {
            if (($field['type'] ?? '') === 'hidden' && isset($field['handle'])) {
                $data[$field['handle']] = $field['default_value'] ?? '';
            }
        }

        $submission = FormSubmission::create([
            'form_id'    => $form->id,
            'data'       => $data,
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        // ── Contact creation/update (contact-type forms only) ─────────────
        if (($form->settings['form_type'] ?? 'general') === 'contact') {
            $contactId = $this->syncContact($form, $data);
            if ($contactId) {
                $submission->update(['contact_id' => $contactId]);
            }
        }

        return $this->successResponse($request, $form);
    }

    private function syncContact(Form $form, array $data): ?string
    {
        // Build a map of contact_field → submitted value from mapped fields
        $mapped = [];
        foreach ($form->fields ?? [] as $field) {
            $contactField = $field['contact_field'] ?? '';
            $handle       = $field['handle'] ?? '';

            if (! $contactField || ! $handle || ! isset($data[$handle])) {
                continue;
            }

            $value = $data[$handle];

            // Skip empty values — never overwrite existing data with blank
            if ($value === null || $value === '') {
                continue;
            }

            $mapped[$contactField] = $value;
        }

        // Need at least an email to find or create the contact
        $email = $mapped['email'] ?? null;
        if (! $email) {
            return null;
        }

        unset($mapped['email']);

        // Separate standard columns from custom fields
        $standardColumns = [
            'first_name', 'last_name', 'phone',
            'address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'country',
        ];

        $standardAttrs = array_intersect_key($mapped, array_flip($standardColumns));
        $customAttrs   = [];

        foreach ($mapped as $key => $value) {
            if (str_starts_with($key, 'custom_fields.')) {
                $customKey = substr($key, strlen('custom_fields.'));
                $customAttrs[$customKey] = $value;
            }
        }

        // mailing_list_opt_in is opt-in only on public forms — never opt out
        $optIn = isset($mapped['mailing_list_opt_in']) && (bool) $mapped['mailing_list_opt_in'];

        $wasCreated = false;

        $contact = Contact::firstOrCreate(
            ['email' => $email],
            array_merge($standardAttrs, ['source' => 'web_form'])
        );

        if ($contact->wasRecentlyCreated) {
            $wasCreated = true;
        } else {
            // Update non-empty standard attributes on existing contact
            $updates = [];
            foreach ($standardAttrs as $col => $value) {
                $updates[$col] = $value;
            }
            if ($updates) {
                $contact->update($updates);
            }
        }

        // Merge opt-in (only set to true, never false)
        if ($optIn && ! $contact->mailing_list_opt_in) {
            $contact->update(['mailing_list_opt_in' => true]);
        }

        // Merge custom fields (only fill non-empty values)
        if ($customAttrs) {
            $existing = $contact->custom_fields ?? [];
            foreach ($customAttrs as $key => $value) {
                $existing[$key] = $value;
            }
            $contact->update(['custom_fields' => $existing]);
        }

        // Write a note to the contact record
        $action = $wasCreated ? 'created via' : 'updated via';
        Note::create([
            'notable_type' => Contact::class,
            'notable_id'   => $contact->id,
            'author_id'    => null,
            'body'         => "Contact {$action} web form: {$form->title}",
            'occurred_at'  => now(),
        ]);

        return $contact->id;
    }

    private function successResponse(Request $request, Form $form)
    {
        $message = $form->settings['success_message'] ?? 'Thank you. Your message has been received.';

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return back()->with('form_success_' . $form->handle, $message);
    }

    private function errorResponse(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], 422);
        }

        return back()->withErrors(['_form' => $message]);
    }
}
