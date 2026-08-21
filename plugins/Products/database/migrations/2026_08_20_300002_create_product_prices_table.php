<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Plugin-owned schema (session 398 — the sixth squash-boundary redraw; see
// the products migration's header note). The product_id FK is plugin-internal
// (both ends plugin-owned) and CASCADE — it travels with its table.
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE public.product_prices (
    id uuid NOT NULL,
    product_id uuid NOT NULL,
    label character varying(255) NOT NULL,
    amount numeric(10,2) NOT NULL,
    stripe_price_id character varying(255),
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.product_prices
    ADD CONSTRAINT product_prices_pkey PRIMARY KEY (id);

CREATE INDEX product_prices_product_id_index ON public.product_prices USING btree (product_id);

ALTER TABLE ONLY public.product_prices
    ADD CONSTRAINT product_prices_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id) ON DELETE CASCADE;
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};
