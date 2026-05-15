# Scoping — Form Submission Notifications

**Status:** **lifted and resolved at session 291.** Surfaced from session 290 (Contact page rebuild) when the user asked about leveraging the existing form-builder + form-manager + email infrastructure to wire site-owner notifications onto form submissions.

**As-built delta vs the proposed shape below:** the notification email shipped as an **operator-editable `form_submission` System Email** (rendered via `EmailTemplate::forHandle`, branded wrapper) — *not* the proposed single fixed built-in Blade template; the recipient token is the existing **`{{contact_email}}`** CMS SiteSetting — *not* a new `site_owner_email` key (added then removed for reuse-over-new-surface); per-form config narrowed to routing only (`to` + `include_submission_data`) with subject/body owned by the editable template; graceful mail-failure handling was added (out of original scope, user-directed); and decision 5's "Demo builds on this primitive" was found stale (Demo is form-less per the s289 architecture — it does not consume this surface; the consumer is the Contact page). Everything else (sync dispatch, allowlist resolver, `e()`-escaped `{{submission}}`, security invariants, no extra spam controls) shipped as proposed. See `sessions/291. … — Log.md`.

**Driving question (user, session 290):** *"One can build a form that sends a system email or maybe several? We have a lot of stuff built and I am looking for ways to leverage that through connecting pieces of it."*

---

## What's already in place (leverage inventory)

- **Mail config** at `config/mail.php` — env-driven default mailer (currently `log`), supports SMTP / Resend / Postmark / SES / failover, configurable from-name and from-address via the `MailSettingsPage` Filament surface.
- **Nine Mailable classes** at `app/Mail/` — pattern-established Blade-templated mail with rendered subject/body: AdminInvitation, DonationReceipt, EventCancellation, EventReminder, PortalEmailChange, PortalEmailVerification, PortalFormCollision, RegistrationConfirmation, TestMail. All use the standard `Mailable` base + `Mail::to($recipient)->send(new MailableClass(...))` dispatch.
- **Form model + Filament FormResource** at `app/Models/Form.php` and `app/Filament/Resources/FormResource.php` — JSON `settings` column on the Form model already carries `submit_label / success_message / honeypot / form_type`. Adding a `notifications` key here is a backward-compatible extension.
- **FormSubmission record** at `form_submissions` table — every submission already lands here with `form_id`, JSON `data`, `ip_address`. Available as the natural trigger for notification dispatch.
- **Contact-sync flow** in `FormSubmissionController::syncContact()` — already runs on submit for `form_type: 'contact'` forms; pattern of "post-write side effects after the DB record is created" is established.
- **SiteSetting model** at `app/Models/SiteSetting.php` — generic key-value store with caching + encryption. A `site_owner_email` (or `notification_default_recipient`) key fits here cleanly. Test file already references `admin_email` as a key but it's not wired anywhere.
- **No queue/job infrastructure in use for mail** — current Mailables dispatch synchronously. Decide whether notification dispatch goes sync (simpler) or queued (more resilient) early in the session.
- **No FormSubmission observer or events** — clean slate; the dispatch hook is a single addition (observer, event, or inline in the controller).

---

## The leverage opportunity, framed

Forms in the CMS are configured in the admin (Filament FormResource). A site owner can edit field shape, labels, validation, contact mapping — all without code. The notification surface is the natural next axis: per-form, configure *who gets emailed when this is submitted, with what content*. This collapses three current pain points into one configuration surface:

1. **The Contact page** wants to email the site owner on submit.
2. **The Demo page (session 291)** wants to email the site owner *and* trigger the auto-login flow.
3. **Any future form** (event registration intake, custom inquiry, partnership ask) wants flexible routing.

A `Form.settings.notifications` array shape supports **multiple recipients per form, multiple notification types per submission, per-recipient subject/body templates** — without per-form code. Same architectural posture as the existing Mailables (template + dispatch), made declarative.

---

## Proposed shape (PM-level, not a design doc)

**Form.settings extension** — add a `notifications` key carrying an array of notification configs:

```json
{
  "notifications": [
    {
      "to": "{{site_owner_email}}",
      "subject": "New contact form submission",
      "template": "form-submission-default",
      "include_submission_data": true
    }
  ]
}
```

- `to` supports a SiteSetting reference (`{{site_owner_email}}`), a literal address, or a comma-list. v1 can keep it simple — one address per entry.
- `template` references a Blade view under `resources/views/mail/forms/` rendered with the FormSubmission data.
- `include_submission_data: true` automatically appends a key-value table of the submitted fields.
- Multiple entries in the array → multiple notifications per submission.

**FormResource UI extension** — repeater on the form editor for managing the notifications array. New tab beside the existing field-builder repeater.

**FormSubmission observer** at `app/Observers/FormSubmissionObserver.php` — `created()` reads `form.settings.notifications`, resolves recipient tokens, dispatches a `FormSubmissionMailable` per entry.

**SiteSetting** — add `site_owner_email` key + wire it into the Mail settings Filament page so the user can set it without code.

**Mailable** — single new `FormSubmissionMailable` at `app/Mail/FormSubmissionMailable.php` accepting form + submission + notification config, rendering the chosen template.

---

## Decisions the user needs to make before lift

1. **Queue or sync dispatch?** Sync is simpler for v1 but blocks the submit response on mail-delivery latency. Queued requires a queue runner (currently not configured in deploy).
2. **Recipient resolution model.** SiteSetting reference (`{{site_owner_email}}`) only, or also literal addresses + comma-lists + user-role lookups (e.g. `{{role:super_admin}}`)? More flexibility = more sanitization surface.
3. **Template surface.** One built-in template (default, full submission dump) with future custom-template support? Or open the per-notification template selection in v1?
4. **Spam / volume controls.** Throttle and honeypot already gate the submission endpoint; do notifications need additional rate-limiting (e.g. per-recipient daily cap) to prevent inbox flooding from a determined spammer who bypasses honeypot?
5. **Where does the Demo page (session 291) sit?** If the notification surface is in place before 291, the demo-form auto-login flow can layer on top — the notification is one half of "what happens on submit", the auto-login is the other. If notifications lift *after* 291, the demo page gets a one-off Mail::to call as an interim, and the notification surface absorbs it later.

---

## Session-shape estimate

**One session.** Three components — DB-storage extension on Form.settings (no migration; JSON column), one Mailable + one Blade template, one observer + one SiteSetting key + one Filament page extension. Plus Pest coverage: form-validation of the notifications schema, observer-triggered dispatch with mocked mail, template-rendering with submission data, multi-recipient dispatch correctness, sanitization of recipient tokens.

**Out of scope of that session:** real SMTP wiring (env decision, separate); rate-limiting beyond the existing throttle; full template-authoring UI (use one default template, lift custom-template support if real-world use surfaces the need); per-role recipient lookups (start with SiteSetting refs + literals).

**Lift order recommendation:** before session 291 (Demo page) so the demo's submit flow can use it as a primitive. Sequence as a lifted-gap-class session, outside the Public Marketing Website track's phase count, parallel to how 285 (CMS fixes) and 288 (pricing_chart widget) sat alongside the track's content sessions.

---

## Security considerations (pre-registered for the eventual audit)

The canonical, disposition-tagged threat register lives in **`docs/security-forms.md` → "Notification dispatch surface (planned — session 291)"** (Finding register rows 4–9). That document is the audit-facing artifact; this section is the implementer-facing summary — the constraints below are **hard requirements on the 291 build**, not advisory.

**Strategy:** these are pre-registered deliberately. Expensive third-party audits surface everything and force a known / by-design / won't-fix triage anyway; presenting a maintained known-issues set with dispositions up front shortens that audit and biases it toward a positive outcome. Every session that touches this surface updates the register before close ("document as we go").

**Hard constraints on the 291 implementation:**

- **Recipient invariant (load-bearing).** Submission-controlled data must never influence the recipient set or any mail header (to / from / reply-to / subject / cc / bcc). Recipients come only from the `site_owner_email` SiteSetting token or an operator-typed literal; subject is operator config. This is the single control that keeps the instance from being a third-party spam/phishing relay. Add a Pest case that asserts a submission cannot redirect or add a recipient.
- **No `{!! !!}` on submission data** anywhere in the notification Mailable or template. Blade `{{ }}` auto-escaping only. The submitted body is untrusted content arriving in a trusted envelope.
- **Token resolver is an allowlist, not a generic eval.** `{{site_owner_email}}` resolves against a hard-coded allowed-token set, not arbitrary `{{anything}}` → SiteSetting lookup. Add a Pest case for an unknown/malformed token (no mail sent, no leak).
- **Recipient sanitisation.** Resolved recipient must validate as a single email; malformed/empty → no mail, no exception leak. Already listed in the Testing section; called out here as a security control, not just a correctness one.

**Tripwire for future work (not 291 scope):** any later feature that sends mail *to the submitter* (confirmation copy, autoresponder) breaks the recipient invariant by definition and must not ship without a dedicated security review — see `docs/security-forms.md` Finding 6.

---

## Cross-references

- `sessions/public website/contact.json` — first consumer (session 290), currently ships with form-only-storage + manual admin review of `form_submissions` table.
- `database/seeders/FormSeeder.php` — `contact-page` form description references this scope doc as the un-wired notification surface.
- `app/Http/Controllers/FormSubmissionController.php` — the dispatch hook lands either inline here (line ~92, after `syncContact`) or via the new observer.
- `sessions/release-plan.md` — when lifted, slot as a lifted-gap session before 291 (Demo page) inside the Public Website Complete milestone execution order.
