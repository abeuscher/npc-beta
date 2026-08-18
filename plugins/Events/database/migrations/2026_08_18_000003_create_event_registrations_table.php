<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Plugin-owned schema (session 383, arc P7). DDL verbatim from the pre-redraw
// core dump — see 2026_08_18_000001_create_events_table.php for the rationale.
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE public.event_registrations (
    id uuid NOT NULL,
    contact_id uuid,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    phone character varying(50),
    company character varying(255),
    address_line_1 character varying(255),
    address_line_2 character varying(255),
    city character varying(100),
    state character varying(100),
    zip character varying(20),
    status character varying(255) DEFAULT 'registered'::character varying NOT NULL,
    registered_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    stripe_payment_intent_id character varying(255),
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    event_id uuid NOT NULL,
    mailing_list_opt_in boolean DEFAULT false NOT NULL,
    stripe_session_id character varying(255),
    ticket_type character varying(255),
    ticket_fee numeric(10,2),
    payment_state character varying(255),
    transaction_id uuid,
    import_session_id uuid,
    custom_fields jsonb DEFAULT '{}'::jsonb NOT NULL,
    source character varying(255) DEFAULT 'human'::character varying NOT NULL,
    organization_id uuid,
    ticket_tier_id uuid,
    quantity smallint DEFAULT '1'::smallint NOT NULL,
    CONSTRAINT event_registrations_status_check CHECK (((status)::text = ANY (ARRAY['pending'::text, 'registered'::text, 'waitlisted'::text, 'cancelled'::text, 'attended'::text])))
);

ALTER TABLE ONLY public.event_registrations
    ADD CONSTRAINT event_registrations_pkey PRIMARY KEY (id);

CREATE INDEX event_registrations_organization_id_index ON public.event_registrations USING btree (organization_id);

CREATE INDEX event_registrations_source_index ON public.event_registrations USING btree (source);

ALTER TABLE ONLY public.event_registrations
    ADD CONSTRAINT event_registrations_contact_id_foreign FOREIGN KEY (contact_id) REFERENCES public.contacts(id) ON DELETE SET NULL;

ALTER TABLE ONLY public.event_registrations
    ADD CONSTRAINT event_registrations_event_id_foreign FOREIGN KEY (event_id) REFERENCES public.events(id) ON DELETE CASCADE;

ALTER TABLE ONLY public.event_registrations
    ADD CONSTRAINT event_registrations_import_session_id_foreign FOREIGN KEY (import_session_id) REFERENCES public.import_sessions(id) ON DELETE SET NULL;

ALTER TABLE ONLY public.event_registrations
    ADD CONSTRAINT event_registrations_organization_id_foreign FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE SET NULL;

ALTER TABLE ONLY public.event_registrations
    ADD CONSTRAINT event_registrations_ticket_tier_id_foreign FOREIGN KEY (ticket_tier_id) REFERENCES public.ticket_tiers(id) ON DELETE SET NULL;

ALTER TABLE ONLY public.event_registrations
    ADD CONSTRAINT event_registrations_transaction_id_foreign FOREIGN KEY (transaction_id) REFERENCES public.transactions(id) ON DELETE SET NULL;
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
    }
};
