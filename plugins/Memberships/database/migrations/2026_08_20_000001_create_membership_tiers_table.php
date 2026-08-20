<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Plugin-owned schema (session 393, Plugin Architecture arc D4 — the third
// squash-boundary redraw per plan doc § 6 disposition 7 / contract surface 5).
// The DDL is copied verbatim from the pre-redraw core dump so a fresh install
// of the full composition reproduces today's exact schema — raw statements,
// not schema-builder calls, because pg_dump round-trips its own output
// byte-identically and the per-composition identity check diffs dump output
// (the billing_interval check constraint would risk expression-normalization
// drift under the schema builder).
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE public.membership_tiers (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    billing_interval character varying(255) NOT NULL,
    default_price numeric(8,2),
    renewal_notice_days integer DEFAULT 30 NOT NULL,
    description text,
    is_active boolean DEFAULT true NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_archived boolean DEFAULT false NOT NULL,
    CONSTRAINT membership_tiers_billing_interval_check CHECK (((billing_interval)::text = ANY (ARRAY[('monthly'::character varying)::text, ('annual'::character varying)::text, ('one_time'::character varying)::text, ('lifetime'::character varying)::text])))
);

ALTER TABLE ONLY public.membership_tiers
    ADD CONSTRAINT membership_tiers_pkey PRIMARY KEY (id);

ALTER TABLE ONLY public.membership_tiers
    ADD CONSTRAINT membership_tiers_slug_unique UNIQUE (slug);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_tiers');
    }
};
