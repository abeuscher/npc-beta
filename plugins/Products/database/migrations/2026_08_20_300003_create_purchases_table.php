<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Plugin-owned schema (session 398 — the sixth squash-boundary redraw; see
// the products migration's header note). The two RESTRICT FKs are plugin-
// internal and ARE behavior — the blocks-product-delete-while-purchases-exist
// constraint travels with the table (the donation_receipts/memberships
// precedent; the CascadeDeleteTest net rides the plugin-owned schema
// unchanged). The contact_id FK into core contacts is SET NULL — plain
// referential behavior, plugin→core FKs are legitimate (surface 5).
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE public.purchases (
    id uuid NOT NULL,
    product_id uuid NOT NULL,
    product_price_id uuid NOT NULL,
    contact_id uuid,
    stripe_session_id character varying(255),
    amount_paid numeric(10,2) NOT NULL,
    status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    occurred_at timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.purchases
    ADD CONSTRAINT purchases_pkey PRIMARY KEY (id);

CREATE INDEX purchases_contact_id_index ON public.purchases USING btree (contact_id);

CREATE INDEX purchases_product_id_index ON public.purchases USING btree (product_id);

CREATE INDEX purchases_product_price_id_index ON public.purchases USING btree (product_price_id);

ALTER TABLE ONLY public.purchases
    ADD CONSTRAINT purchases_contact_id_foreign FOREIGN KEY (contact_id) REFERENCES public.contacts(id) ON DELETE SET NULL;

ALTER TABLE ONLY public.purchases
    ADD CONSTRAINT purchases_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id) ON DELETE RESTRICT;

ALTER TABLE ONLY public.purchases
    ADD CONSTRAINT purchases_product_price_id_foreign FOREIGN KEY (product_price_id) REFERENCES public.product_prices(id) ON DELETE RESTRICT;
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
