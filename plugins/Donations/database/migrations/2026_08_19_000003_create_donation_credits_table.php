<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Plugin-owned schema (session 390, arc D3 — see the create_funds_table
// migration header). The attributable morph columns carry no FK by design.
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE public.donation_credits (
    id uuid NOT NULL,
    donation_id uuid NOT NULL,
    attributable_type character varying(255) NOT NULL,
    attributable_id uuid NOT NULL,
    credit_pct numeric(5,2) NOT NULL,
    credit_role text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.donation_credits
    ADD CONSTRAINT donation_credits_pkey PRIMARY KEY (id);

CREATE INDEX donation_credits_attributable_type_attributable_id_index ON public.donation_credits USING btree (attributable_type, attributable_id);

CREATE INDEX donation_credits_donation_id_index ON public.donation_credits USING btree (donation_id);

ALTER TABLE ONLY public.donation_credits
    ADD CONSTRAINT donation_credits_donation_id_foreign FOREIGN KEY (donation_id) REFERENCES public.donations(id) ON DELETE CASCADE;
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('donation_credits');
    }
};
