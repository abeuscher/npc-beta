<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Plugin-owned schema (session 390, arc D3 — see the create_funds_table
// migration header). The contact_id FK is ON DELETE RESTRICT deliberately:
// that constraint IS the "blocks contact force-delete while receipts exist"
// behavior, and it travels with the table.
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE public.donation_receipts (
    id bigint NOT NULL,
    contact_id uuid NOT NULL,
    tax_year integer NOT NULL,
    sent_at timestamp(0) without time zone NOT NULL,
    total_amount numeric(10,2) NOT NULL,
    breakdown json NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE SEQUENCE public.donation_receipts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER SEQUENCE public.donation_receipts_id_seq OWNED BY public.donation_receipts.id;

ALTER TABLE ONLY public.donation_receipts ALTER COLUMN id SET DEFAULT nextval('public.donation_receipts_id_seq'::regclass);

ALTER TABLE ONLY public.donation_receipts
    ADD CONSTRAINT donation_receipts_pkey PRIMARY KEY (id);

CREATE INDEX donation_receipts_contact_id_tax_year_index ON public.donation_receipts USING btree (contact_id, tax_year);

ALTER TABLE ONLY public.donation_receipts
    ADD CONSTRAINT donation_receipts_contact_id_foreign FOREIGN KEY (contact_id) REFERENCES public.contacts(id) ON DELETE RESTRICT;
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('donation_receipts');
    }
};
