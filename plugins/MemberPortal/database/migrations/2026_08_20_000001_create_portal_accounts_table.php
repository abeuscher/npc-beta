<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Plugin-owned schema (session 395, Plugin Architecture arc D5 — the fourth
// squash-boundary redraw per plan doc § 6 disposition 7 / contract surface 5).
// The DDL is copied verbatim from the pre-redraw core dump so a fresh install
// of the full composition reproduces today's exact schema — raw statements,
// not schema-builder calls, because pg_dump round-trips its own output
// byte-identically and the per-composition identity check diffs dump output.
// The bigint PK's sequence travels complete (CREATE SEQUENCE + OWNED BY +
// SET DEFAULT nextval — the donation_receipts_id_seq mechanics, session 390).
// The contact_id FK is ON DELETE SET NULL: a contact force-delete orphans the
// portal account row rather than blocking — existing behavior, travels as-is.
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE public.portal_accounts (
    id bigint NOT NULL,
    contact_id uuid,
    email character varying(255) NOT NULL,
    password character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_active boolean DEFAULT true NOT NULL
);

CREATE SEQUENCE public.portal_accounts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER SEQUENCE public.portal_accounts_id_seq OWNED BY public.portal_accounts.id;

ALTER TABLE ONLY public.portal_accounts ALTER COLUMN id SET DEFAULT nextval('public.portal_accounts_id_seq'::regclass);

ALTER TABLE ONLY public.portal_accounts
    ADD CONSTRAINT portal_accounts_email_unique UNIQUE (email);

ALTER TABLE ONLY public.portal_accounts
    ADD CONSTRAINT portal_accounts_pkey PRIMARY KEY (id);

CREATE INDEX portal_accounts_contact_id_index ON public.portal_accounts USING btree (contact_id);

ALTER TABLE ONLY public.portal_accounts
    ADD CONSTRAINT portal_accounts_contact_id_foreign FOREIGN KEY (contact_id) REFERENCES public.contacts(id) ON DELETE SET NULL;
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_accounts');
    }
};
