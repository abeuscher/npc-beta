<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dropForeign(['campaign_id']);
            $table->dropForeign(['fund_id']);
            $table->dropForeign(['contact_id']);

            $table->dropIndex('donations_campaign_id_index');
            $table->dropIndex('donations_fund_id_index');
            $table->dropIndex('donations_contact_id_index');

            $table->dropColumn([
                'campaign_id',
                'fund_id',
                'donated_on',
                'method',
                'reference',
                'is_anonymous',
                'notes',
                'deleted_at',
            ]);

            $table->foreign('contact_id')->references('id')->on('contacts')->nullOnDelete();
            $table->index('contact_id');

            $table->string('type')->after('contact_id');
            $table->string('currency', 3)->default('usd')->after('amount');
            $table->string('frequency')->nullable()->after('currency');
            $table->string('status')->default('pending')->after('frequency');
            $table->string('stripe_subscription_id')->nullable()->after('status');
            $table->string('stripe_customer_id')->nullable()->after('stripe_subscription_id');
            $table->timestamp('started_at')->nullable()->after('stripe_customer_id');
            $table->timestamp('ended_at')->nullable()->after('started_at');
        });
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
            $table->dropIndex('donations_contact_id_index');

            $table->dropColumn([
                'type',
                'currency',
                'frequency',
                'status',
                'stripe_subscription_id',
                'stripe_customer_id',
                'started_at',
                'ended_at',
            ]);

            $table->foreign('contact_id')->references('id')->on('contacts')->cascadeOnDelete();
            $table->index('contact_id');

            $table->uuid('campaign_id')->nullable()->after('contact_id');
            $table->uuid('fund_id')->nullable()->after('campaign_id');
            $table->date('donated_on')->after('amount');
            $table->string('method')->default('other')->after('donated_on');
            $table->string('reference')->nullable()->after('method');
            $table->boolean('is_anonymous')->default(false)->after('reference');
            $table->text('notes')->nullable()->after('is_anonymous');
            $table->softDeletes();

            $table->foreign('campaign_id')->references('id')->on('campaigns')->nullOnDelete();
            $table->foreign('fund_id')->references('id')->on('funds')->nullOnDelete();

            $table->index('campaign_id');
            $table->index('fund_id');
        });
    }
};
