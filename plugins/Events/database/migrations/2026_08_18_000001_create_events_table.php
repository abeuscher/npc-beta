<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Plugin-owned schema (session 383, Plugin Architecture arc P7 — the squash
// boundary redraw per plan doc § 6 disposition 7 / § 6.7). The DDL is copied
// verbatim from the pre-redraw core dump so a fresh install of the full
// composition reproduces today's exact schema — raw statements, not schema-
// builder calls, because pg_dump round-trips its own output byte-identically
// and the per-composition identity check diffs dump output.
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE public.events (
    id uuid NOT NULL,
    title character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    description text,
    status character varying(255) DEFAULT 'draft'::character varying NOT NULL,
    is_in_person boolean DEFAULT true NOT NULL,
    address_line_1 character varying(255),
    address_line_2 character varying(255),
    city character varying(100),
    state character varying(100),
    zip character varying(20),
    map_url character varying(2048),
    map_label character varying(255),
    is_virtual boolean DEFAULT false NOT NULL,
    meeting_url character varying(2048),
    is_free boolean DEFAULT true NOT NULL,
    is_recurring boolean DEFAULT false NOT NULL,
    recurrence_type character varying(255),
    recurrence_rule json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    landing_page_id uuid,
    meeting_label character varying(255),
    meeting_details text,
    external_registration_url character varying(255),
    registration_mode character varying(255) DEFAULT 'open'::character varying NOT NULL,
    auto_create_contacts boolean DEFAULT true NOT NULL,
    mailing_list_opt_in_enabled boolean DEFAULT false NOT NULL,
    custom_fields jsonb,
    starts_at timestamp(0) without time zone NOT NULL,
    ends_at timestamp(0) without time zone,
    registrants_deleted_at timestamp(0) without time zone,
    author_id bigint NOT NULL,
    import_session_id uuid,
    published_at timestamp(0) without time zone,
    source character varying(255) DEFAULT 'human'::character varying NOT NULL,
    sponsor_organization_id uuid,
    sold_out boolean DEFAULT false NOT NULL,
    CONSTRAINT events_recurrence_type_check CHECK (((recurrence_type)::text = ANY (ARRAY[('manual'::character varying)::text, ('rule'::character varying)::text]))),
    CONSTRAINT events_status_check CHECK (((status)::text = ANY (ARRAY[('draft'::character varying)::text, ('published'::character varying)::text, ('cancelled'::character varying)::text])))
);

ALTER TABLE ONLY public.events
    ADD CONSTRAINT events_pkey PRIMARY KEY (id);

ALTER TABLE ONLY public.events
    ADD CONSTRAINT events_slug_unique UNIQUE (slug);

CREATE INDEX events_landing_page_id_index ON public.events USING btree (landing_page_id);

CREATE INDEX events_source_index ON public.events USING btree (source);

CREATE INDEX events_sponsor_organization_id_index ON public.events USING btree (sponsor_organization_id);

ALTER TABLE ONLY public.events
    ADD CONSTRAINT events_author_id_foreign FOREIGN KEY (author_id) REFERENCES public.users(id) ON DELETE RESTRICT;

ALTER TABLE ONLY public.events
    ADD CONSTRAINT events_import_session_id_foreign FOREIGN KEY (import_session_id) REFERENCES public.import_sessions(id) ON DELETE SET NULL;

ALTER TABLE ONLY public.events
    ADD CONSTRAINT events_landing_page_id_foreign FOREIGN KEY (landing_page_id) REFERENCES public.pages(id) ON DELETE SET NULL;

ALTER TABLE ONLY public.events
    ADD CONSTRAINT events_sponsor_organization_id_foreign FOREIGN KEY (sponsor_organization_id) REFERENCES public.organizations(id) ON DELETE SET NULL;
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
