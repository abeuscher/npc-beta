<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── donation_receipts.contact_id: CASCADE → RESTRICT ────────────
        // Tax receipts are financial records; must not be silently destroyed
        // when a contact is force-deleted.
        Schema::table('donation_receipts', function (Blueprint $table) {
            $table->dropForeign('donation_receipts_contact_id_foreign');
            $table->foreign('contact_id')
                ->references('id')->on('contacts')
                ->restrictOnDelete();
        });

        // ── memberships.contact_id: CASCADE → RESTRICT ─────────────────
        // Membership history (including payment data) must not be destroyed
        // on contact force-delete. Admin must resolve memberships first.
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropForeign('memberships_contact_id_foreign');
            $table->foreign('contact_id')
                ->references('id')->on('contacts')
                ->restrictOnDelete();
        });

        // ── import_sessions.imported_by: CASCADE → SET NULL ─────────────
        // Import sessions are audit records; should survive user deletion.
        Schema::table('import_sessions', function (Blueprint $table) {
            $table->dropForeign('import_sessions_imported_by_foreign');
            $table->unsignedBigInteger('imported_by')->nullable()->change();
            $table->foreign('imported_by')
                ->references('id')->on('users')
                ->nullOnDelete();
        });

        // ── contact_duplicate_dismissals.dismissed_by: CASCADE → SET NULL
        // Dismissal audit records should survive user deletion.
        Schema::table('contact_duplicate_dismissals', function (Blueprint $table) {
            $table->dropForeign('contact_duplicate_dismissals_dismissed_by_foreign');
            $table->unsignedBigInteger('dismissed_by')->nullable()->change();
            $table->foreign('dismissed_by')
                ->references('id')->on('users')
                ->nullOnDelete();
        });

        // ── page_widgets.widget_type_id: CASCADE → RESTRICT ────────────
        // Deleting a widget type must not silently destroy page content.
        // Session 109 added a UI guard; this enforces it at DB level.
        Schema::table('page_widgets', function (Blueprint $table) {
            $table->dropForeign('page_widgets_widget_type_id_foreign');
            $table->foreign('widget_type_id')
                ->references('id')->on('widget_types')
                ->restrictOnDelete();
        });

        // ── purchases.product_id: NO ACTION → RESTRICT ─────────────────
        // Financial records — product deletion must be explicitly blocked.
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropForeign('purchases_product_id_foreign');
            $table->foreign('product_id')
                ->references('id')->on('products')
                ->restrictOnDelete();
        });

        // ── purchases.product_price_id: NO ACTION → RESTRICT ───────────
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropForeign('purchases_product_price_id_foreign');
            $table->foreign('product_price_id')
                ->references('id')->on('product_prices')
                ->restrictOnDelete();
        });

        // ── waitlist_entries.product_id: NO ACTION → CASCADE ────────────
        // Waitlist entries are non-financial and meaningless without the product.
        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->dropForeign('waitlist_entries_product_id_foreign');
            $table->foreign('product_id')
                ->references('id')->on('products')
                ->cascadeOnDelete();
        });

        // ── allocations (legacy table): add explicit RESTRICT ───────────
        // Table is unused but still has FKs without onDelete rules.
        Schema::table('allocations', function (Blueprint $table) {
            $table->dropForeign('allocations_product_id_foreign');
            $table->foreign('product_id')
                ->references('id')->on('products')
                ->restrictOnDelete();
        });

        Schema::table('allocations', function (Blueprint $table) {
            $table->dropForeign('allocations_product_price_id_foreign');
            $table->foreign('product_price_id')
                ->references('id')->on('product_prices')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // ── donation_receipts: RESTRICT → CASCADE ───────────────────────
        Schema::table('donation_receipts', function (Blueprint $table) {
            $table->dropForeign('donation_receipts_contact_id_foreign');
            $table->foreign('contact_id')
                ->references('id')->on('contacts')
                ->cascadeOnDelete();
        });

        // ── memberships: RESTRICT → CASCADE ─────────────────────────────
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropForeign('memberships_contact_id_foreign');
            $table->foreign('contact_id')
                ->references('id')->on('contacts')
                ->cascadeOnDelete();
        });

        // ── import_sessions: SET NULL → CASCADE, restore NOT NULL ───────
        Schema::table('import_sessions', function (Blueprint $table) {
            $table->dropForeign('import_sessions_imported_by_foreign');
            $table->unsignedBigInteger('imported_by')->nullable(false)->change();
            $table->foreign('imported_by')
                ->references('id')->on('users')
                ->cascadeOnDelete();
        });

        // ── contact_duplicate_dismissals: SET NULL → CASCADE, NOT NULL ──
        Schema::table('contact_duplicate_dismissals', function (Blueprint $table) {
            $table->dropForeign('contact_duplicate_dismissals_dismissed_by_foreign');
            $table->unsignedBigInteger('dismissed_by')->nullable(false)->change();
            $table->foreign('dismissed_by')
                ->references('id')->on('users')
                ->cascadeOnDelete();
        });

        // ── page_widgets: RESTRICT → CASCADE ────────────────────────────
        Schema::table('page_widgets', function (Blueprint $table) {
            $table->dropForeign('page_widgets_widget_type_id_foreign');
            $table->foreign('widget_type_id')
                ->references('id')->on('widget_types')
                ->cascadeOnDelete();
        });

        // ── purchases: RESTRICT → NO ACTION ─────────────────────────────
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropForeign('purchases_product_id_foreign');
            $table->foreign('product_id')
                ->references('id')->on('products');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropForeign('purchases_product_price_id_foreign');
            $table->foreign('product_price_id')
                ->references('id')->on('product_prices');
        });

        // ── waitlist_entries: CASCADE → NO ACTION ───────────────────────
        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->dropForeign('waitlist_entries_product_id_foreign');
            $table->foreign('product_id')
                ->references('id')->on('products');
        });

        // ── allocations: RESTRICT → NO ACTION ──────────────────────────
        Schema::table('allocations', function (Blueprint $table) {
            $table->dropForeign('allocations_product_id_foreign');
            $table->foreign('product_id')
                ->references('id')->on('products');
        });

        Schema::table('allocations', function (Blueprint $table) {
            $table->dropForeign('allocations_product_price_id_foreign');
            $table->foreign('product_price_id')
                ->references('id')->on('product_prices');
        });
    }
};
