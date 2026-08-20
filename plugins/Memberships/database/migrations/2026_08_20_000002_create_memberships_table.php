<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Plugin-owned schema (session 393, Plugin Architecture arc D4 — the third
// squash-boundary redraw). DDL verbatim from the pre-redraw core dump; see
// the membership_tiers migration for the raw-DDL rationale. Five outbound
// FKs travel with the table — note contact_id is ON DELETE RESTRICT: that
// constraint IS the blocks-contact-force-delete-while-memberships-exist
// behavior (the donation_receipts precedent, contract surface 5). No
// sequences — both membership PKs are uuid.
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE public.memberships (
    id uuid NOT NULL,
    contact_id uuid NOT NULL,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    starts_on date,
    expires_on date,
    amount_paid numeric(10,2),
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    tier_id uuid,
    stripe_session_id character varying(255),
    stripe_subscription_id character varying(255),
    import_source_id uuid,
    import_session_id uuid,
    external_id character varying(255),
    custom_fields jsonb,
    source character varying(255) DEFAULT 'human'::character varying NOT NULL,
    organization_id uuid
);

ALTER TABLE ONLY public.memberships
    ADD CONSTRAINT memberships_pkey PRIMARY KEY (id);

CREATE INDEX memberships_contact_id_index ON public.memberships USING btree (contact_id);

CREATE INDEX memberships_import_external_idx ON public.memberships USING btree (import_source_id, external_id);

CREATE INDEX memberships_organization_id_index ON public.memberships USING btree (organization_id);

CREATE INDEX memberships_source_index ON public.memberships USING btree (source);

ALTER TABLE ONLY public.memberships
    ADD CONSTRAINT memberships_contact_id_foreign FOREIGN KEY (contact_id) REFERENCES public.contacts(id) ON DELETE RESTRICT;

ALTER TABLE ONLY public.memberships
    ADD CONSTRAINT memberships_import_session_id_foreign FOREIGN KEY (import_session_id) REFERENCES public.import_sessions(id) ON DELETE SET NULL;

ALTER TABLE ONLY public.memberships
    ADD CONSTRAINT memberships_import_source_id_foreign FOREIGN KEY (import_source_id) REFERENCES public.import_sources(id) ON DELETE SET NULL;

ALTER TABLE ONLY public.memberships
    ADD CONSTRAINT memberships_organization_id_foreign FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE SET NULL;

ALTER TABLE ONLY public.memberships
    ADD CONSTRAINT memberships_tier_id_foreign FOREIGN KEY (tier_id) REFERENCES public.membership_tiers(id) ON DELETE SET NULL;
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
