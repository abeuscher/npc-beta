<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Plugin-owned schema (session 398 — the sixth squash-boundary redraw; see
// the products migration's header note). The product_id FK is plugin-internal
// CASCADE; the contact_id FK into core contacts is SET NULL (plain behavior).
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE public.waitlist_entries (
    id uuid NOT NULL,
    product_id uuid NOT NULL,
    contact_id uuid,
    status character varying(255) DEFAULT 'waiting'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.waitlist_entries
    ADD CONSTRAINT waitlist_entries_pkey PRIMARY KEY (id);

CREATE INDEX waitlist_entries_contact_id_index ON public.waitlist_entries USING btree (contact_id);

CREATE INDEX waitlist_entries_product_id_index ON public.waitlist_entries USING btree (product_id);

ALTER TABLE ONLY public.waitlist_entries
    ADD CONSTRAINT waitlist_entries_contact_id_foreign FOREIGN KEY (contact_id) REFERENCES public.contacts(id) ON DELETE SET NULL;

ALTER TABLE ONLY public.waitlist_entries
    ADD CONSTRAINT waitlist_entries_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id) ON DELETE CASCADE;
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};
