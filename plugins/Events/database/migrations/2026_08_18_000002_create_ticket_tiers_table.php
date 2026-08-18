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
CREATE TABLE public.ticket_tiers (
    id uuid NOT NULL,
    event_id uuid NOT NULL,
    name character varying(255) NOT NULL,
    price numeric(8,2) DEFAULT '0'::numeric NOT NULL,
    capacity integer,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_complimentary boolean DEFAULT false NOT NULL
);

ALTER TABLE ONLY public.ticket_tiers
    ADD CONSTRAINT ticket_tiers_pkey PRIMARY KEY (id);

CREATE INDEX ticket_tiers_event_id_sort_order_index ON public.ticket_tiers USING btree (event_id, sort_order);

ALTER TABLE ONLY public.ticket_tiers
    ADD CONSTRAINT ticket_tiers_event_id_foreign FOREIGN KEY (event_id) REFERENCES public.events(id) ON DELETE CASCADE;
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_tiers');
    }
};
