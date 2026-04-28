<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('dashboard_configs', 'dashboard_views');

        DB::table('page_widgets')
            ->where('owner_type', 'App\\Models\\DashboardConfig')
            ->update(['owner_type' => 'App\\WidgetPrimitive\\Views\\DashboardView']);

        DB::table('page_layouts')
            ->where('owner_type', 'App\\Models\\DashboardConfig')
            ->update(['owner_type' => 'App\\WidgetPrimitive\\Views\\DashboardView']);
    }

    public function down(): void
    {
        DB::table('page_layouts')
            ->where('owner_type', 'App\\WidgetPrimitive\\Views\\DashboardView')
            ->update(['owner_type' => 'App\\Models\\DashboardConfig']);

        DB::table('page_widgets')
            ->where('owner_type', 'App\\WidgetPrimitive\\Views\\DashboardView')
            ->update(['owner_type' => 'App\\Models\\DashboardConfig']);

        Schema::rename('dashboard_views', 'dashboard_configs');
    }
};
