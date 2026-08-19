<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Plugin-owned schema (session 390, Plugin Architecture arc D3 — the second
// squash-boundary redraw per plan doc § 6 disposition 7 / contract surface 5).
// The DDL is copied verbatim from the pre-redraw core dump so a fresh install
// of the full composition reproduces today's exact schema — raw statements,
// not schema-builder calls, because pg_dump round-trips its own output
// byte-identically and the per-composition identity check diffs dump output.
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE public.funds (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    code character varying(255) NOT NULL,
    description text,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    restriction_type character varying(255) DEFAULT 'unrestricted'::character varying NOT NULL,
    quickbooks_account_id character varying(255),
    is_archived boolean DEFAULT false NOT NULL
);

ALTER TABLE ONLY public.funds
    ADD CONSTRAINT funds_pkey PRIMARY KEY (id);

ALTER TABLE ONLY public.funds
    ADD CONSTRAINT funds_code_unique UNIQUE (code);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('funds');
    }
};
