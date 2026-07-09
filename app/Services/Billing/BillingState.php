<?php

namespace App\Services\Billing;

/**
 * A read-only view over the billing-state document Fleet Manager pushes to the
 * node (contract v2.6.0), or a null-object when no document is present or the
 * document is unusable.
 *
 * DISPLAY-ONLY by contract: nothing this class exposes may influence enforcement,
 * routes, or config. Enforcement rides the `SUSPENSION_STATE` env flag
 * (SuspensionState); this document only ever improves the copy on a screen the
 * flag has already decided to show. The suspension gate must lock correctly with
 * no document present — hence the null-object, which returns `null` for every
 * accessor and `false` from isPresent().
 *
 * Accessors mirror the documented schema so CB2's Account page can consume the
 * same object; this session wires only reason()/portalUrl()/billingContactEmail()
 * (the lock-screen copy) and asOf() (the health subcheck).
 */
final class BillingState
{
    private function __construct(
        private readonly bool $present,
        private readonly array $data,
    ) {}

    public static function absent(): self
    {
        return new self(false, []);
    }

    public static function fromDocument(array $data): self
    {
        return new self(true, $data);
    }

    /** True only when a valid, schema-matching document was read. */
    public function isPresent(): bool
    {
        return $this->present;
    }

    /** ISO 8601 timestamp the document was generated; null when absent. */
    public function asOf(): ?string
    {
        return $this->string('as_of');
    }

    // ── Suspension detail (drives the lock-screen copy) ──────────────────────

    /** Reason code: delinquent / trial_expired / canceled / manual (or null). */
    public function reason(): ?string
    {
        return $this->string('suspension.reason');
    }

    /** The document's own view of the suspension state (display only). */
    public function suspensionState(): ?string
    {
        return $this->string('suspension.state');
    }

    /** When the current suspension began (ISO 8601), if any. */
    public function suspendedSince(): ?string
    {
        return $this->string('suspension.since');
    }

    /** When the grace window ends (ISO 8601), if any. */
    public function graceEndsAt(): ?string
    {
        return $this->string('suspension.grace_ends');
    }

    /** Stripe-hosted billing-portal login URL — the path to self-cure. */
    public function portalUrl(): ?string
    {
        return $this->string('portal_url');
    }

    /** Billing contact on file (read-only; edited via the Stripe portal). */
    public function billingContactEmail(): ?string
    {
        return $this->string('billing_contact_email');
    }

    // ── Plan / invoice / trial (CB2 Account page consumes these) ─────────────

    public function status(): ?string
    {
        return $this->string('status');
    }

    /** { name, amount, currency, interval } or null. */
    public function plan(): ?array
    {
        return $this->array('plan');
    }

    /** { date, amount, line_items: [{ description, amount }] } or null. */
    public function nextInvoice(): ?array
    {
        return $this->array('next_invoice');
    }

    /** { ends_at } or null. */
    public function trial(): ?array
    {
        return $this->array('trial');
    }

    // ── Derived display helpers (CB2 banners) ────────────────────────────────

    /**
     * True when the subscription is in a pre-lock delinquency window (Stripe
     * dunning `past_due`/`unpaid`, or FM's grace clock running) — the state the
     * Account page and the panel-wide banner warn about, so the person who can
     * fix it sees it before the admin panel locks, not at it.
     *
     * DISPLAY-ONLY, like everything here: this drives banner copy, never
     * enforcement. Once the flag actually locks the panel, the middleware
     * replaces the whole admin response and these banners never render. An
     * absent/unusable document is never "attention" (null-object → false).
     */
    public function needsBillingAttention(): bool
    {
        if (! $this->present) {
            return false;
        }

        return in_array($this->status(), ['past_due', 'unpaid'], true)
            || $this->graceEndsAt() !== null;
    }

    // ── Internal typed access ────────────────────────────────────────────────

    private function string(string $key): ?string
    {
        $value = data_get($this->data, $key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function array(string $key): ?array
    {
        $value = data_get($this->data, $key);

        return is_array($value) ? $value : null;
    }
}
