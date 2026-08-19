<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Plugin-owned schema (session 390, arc D3 — see the create_funds_table
// migration header). Plugin→core FKs (contacts, organizations, import
// sources/sessions) are legitimate per contract surface 5 — the plugin owns
// the table, not isolation from core's schema.
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE public.donations (
    id uuid NOT NULL,
    contact_id uuid,
    amount numeric(10,2) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    type character varying(255) NOT NULL,
    currency character varying(3) DEFAULT 'usd'::character varying NOT NULL,
    frequency character varying(255),
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    stripe_subscription_id character varying(255),
    stripe_customer_id character varying(255),
    started_at timestamp(0) without time zone,
    ended_at timestamp(0) without time zone,
    fund_id uuid,
    import_source_id uuid,
    import_session_id uuid,
    external_id character varying(255),
    custom_fields jsonb,
    source character varying(255) DEFAULT 'stripe_webhook'::character varying NOT NULL,
    organization_id uuid,
    acknowledged_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.donations
    ADD CONSTRAINT donations_pkey PRIMARY KEY (id);

CREATE INDEX donations_contact_id_index ON public.donations USING btree (contact_id);

CREATE INDEX donations_fund_id_index ON public.donations USING btree (fund_id);

CREATE INDEX donations_import_external_idx ON public.donations USING btree (import_source_id, external_id);

CREATE INDEX donations_organization_id_index ON public.donations USING btree (organization_id);

CREATE INDEX donations_source_index ON public.donations USING btree (source);

ALTER TABLE ONLY public.donations
    ADD CONSTRAINT donations_contact_id_foreign FOREIGN KEY (contact_id) REFERENCES public.contacts(id) ON DELETE SET NULL;

ALTER TABLE ONLY public.donations
    ADD CONSTRAINT donations_fund_id_foreign FOREIGN KEY (fund_id) REFERENCES public.funds(id) ON DELETE SET NULL;

ALTER TABLE ONLY public.donations
    ADD CONSTRAINT donations_import_session_id_foreign FOREIGN KEY (import_session_id) REFERENCES public.import_sessions(id) ON DELETE SET NULL;

ALTER TABLE ONLY public.donations
    ADD CONSTRAINT donations_import_source_id_foreign FOREIGN KEY (import_source_id) REFERENCES public.import_sources(id) ON DELETE SET NULL;

ALTER TABLE ONLY public.donations
    ADD CONSTRAINT donations_organization_id_foreign FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE SET NULL;
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
