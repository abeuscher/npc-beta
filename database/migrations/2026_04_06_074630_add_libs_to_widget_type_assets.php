<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add libs key to widget_types.assets for widgets that need external JS libraries.
     */
    public function up(): void
    {
        $libsByHandle = [
            'carousel'         => ['swiper'],
            'product_carousel' => ['swiper'],
            'logo_garden'      => ['swiper'],
            'events_listing'   => ['swiper'],
            'blog_listing'     => ['swiper'],
            'bar_chart'        => ['chart.js'],
            'event_calendar'   => ['jcalendar'],
        ];

        foreach ($libsByHandle as $handle => $libs) {
            $row = DB::table('widget_types')->where('handle', $handle)->first();
            if (! $row) {
                continue;
            }

            $assets = json_decode($row->assets, true) ?: [];
            $assets['libs'] = $libs;

            DB::table('widget_types')
                ->where('handle', $handle)
                ->update(['assets' => json_encode($assets)]);
        }
    }

    /**
     * Remove the libs key from widget_types.assets.
     */
    public function down(): void
    {
        $handles = ['carousel', 'product_carousel', 'logo_garden', 'events_listing', 'blog_listing', 'bar_chart', 'event_calendar'];

        foreach ($handles as $handle) {
            $row = DB::table('widget_types')->where('handle', $handle)->first();
            if (! $row) {
                continue;
            }

            $assets = json_decode($row->assets, true) ?: [];
            unset($assets['libs']);

            DB::table('widget_types')
                ->where('handle', $handle)
                ->update(['assets' => json_encode($assets)]);
        }
    }
};
