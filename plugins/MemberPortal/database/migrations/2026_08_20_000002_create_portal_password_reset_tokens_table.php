<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Plugin-owned schema (session 395, arc D5 — see the create_portal_accounts
// migration header). Three columns, PK on email, no FKs, no sequence.
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE public.portal_password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.portal_password_reset_tokens
    ADD CONSTRAINT portal_password_reset_tokens_pkey PRIMARY KEY (email);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_password_reset_tokens');
    }
};
