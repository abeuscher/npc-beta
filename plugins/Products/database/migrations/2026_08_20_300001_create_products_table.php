<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Plugin-owned schema (session 398, Plugin Architecture arc D8 — the sixth
// squash-boundary redraw per plan doc § 6 disposition 7 / contract surface 5).
// The DDL is copied verbatim from the pre-redraw core dump so a fresh install
// of the full composition reproduces today's exact schema — raw statements,
// not schema-builder calls, because pg_dump round-trips its own output
// byte-identically and the per-composition identity check diffs dump output.
// All four product tables carry uuid PKs — NO sequences travel (the second
// no-sequences redraw, the Memberships category; recorded, not skipped).
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE public.products (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    description text,
    capacity integer NOT NULL,
    stripe_product_id character varying(255),
    status character varying(255) DEFAULT 'draft'::character varying NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_archived boolean DEFAULT false NOT NULL,
    published_at timestamp(0) without time zone,
    source character varying(255) DEFAULT 'human'::character varying NOT NULL
);

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_pkey PRIMARY KEY (id);

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_slug_unique UNIQUE (slug);

CREATE INDEX products_source_index ON public.products USING btree (source);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
