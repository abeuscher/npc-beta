<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Plugin-owned schema (session 397, Plugin Architecture arc D7 — the fifth
// squash-boundary redraw per plan doc § 6 disposition 7 / contract surface 5).
// The DDL is copied verbatim from the pre-redraw core dump so a fresh install
// of the full composition reproduces today's exact schema — raw statements,
// not schema-builder calls, because pg_dump round-trips its own output
// byte-identically and the per-composition identity check diffs dump output.
// The bigint PK's sequence travels complete (CREATE SEQUENCE + OWNED BY +
// SET DEFAULT nextval — the portal_accounts_id_seq mechanics, session 395;
// this redraw is the first carrying TWO sequences, one per table).
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE public.forms (
    id bigint NOT NULL,
    title character varying(255) NOT NULL,
    handle character varying(255) NOT NULL,
    description text,
    fields json DEFAULT '[]'::json NOT NULL,
    settings json DEFAULT '{}'::json NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    is_archived boolean DEFAULT false NOT NULL
);

CREATE SEQUENCE public.forms_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER SEQUENCE public.forms_id_seq OWNED BY public.forms.id;

ALTER TABLE ONLY public.forms ALTER COLUMN id SET DEFAULT nextval('public.forms_id_seq'::regclass);

ALTER TABLE ONLY public.forms
    ADD CONSTRAINT forms_handle_unique UNIQUE (handle);

ALTER TABLE ONLY public.forms
    ADD CONSTRAINT forms_pkey PRIMARY KEY (id);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('forms');
    }
};
