<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Plugin-owned schema (session 397, Plugin Architecture arc D7 — the fifth
// squash-boundary redraw; see the forms migration ahead of this one for the
// byte-faithful-DDL rationale). The second of this redraw's two traveling
// sequences. The contact_id FK is ON DELETE SET NULL: a contact force-delete
// orphans the submission row rather than blocking — existing behavior,
// travels as-is (the portal_accounts precedent, session 395). The form_id FK
// is ON DELETE CASCADE — plugin-internal: deleting a form removes its
// submissions, both tables plugin-owned.
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE public.form_submissions (
    id bigint NOT NULL,
    form_id bigint NOT NULL,
    data json DEFAULT '{}'::json NOT NULL,
    ip_address character varying(255),
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    contact_id uuid,
    deleted_at timestamp(0) without time zone
);

CREATE SEQUENCE public.form_submissions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER SEQUENCE public.form_submissions_id_seq OWNED BY public.form_submissions.id;

ALTER TABLE ONLY public.form_submissions ALTER COLUMN id SET DEFAULT nextval('public.form_submissions_id_seq'::regclass);

ALTER TABLE ONLY public.form_submissions
    ADD CONSTRAINT form_submissions_pkey PRIMARY KEY (id);

CREATE INDEX form_submissions_contact_id_index ON public.form_submissions USING btree (contact_id);

CREATE INDEX form_submissions_form_id_index ON public.form_submissions USING btree (form_id);

ALTER TABLE ONLY public.form_submissions
    ADD CONSTRAINT form_submissions_contact_id_foreign FOREIGN KEY (contact_id) REFERENCES public.contacts(id) ON DELETE SET NULL;

ALTER TABLE ONLY public.form_submissions
    ADD CONSTRAINT form_submissions_form_id_foreign FOREIGN KEY (form_id) REFERENCES public.forms(id) ON DELETE CASCADE;
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
    }
};
