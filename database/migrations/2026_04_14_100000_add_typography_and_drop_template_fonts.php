<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $default = DB::table('templates')
            ->where('type', 'page')
            ->where('is_default', true)
            ->first(['heading_font', 'body_font']);

        $typography = \App\Services\TypographyResolver::defaults();

        if ($default) {
            if (filled($default->heading_font)) {
                $typography['buckets']['heading_family'] = $default->heading_font;
            }
            if (filled($default->body_font)) {
                $typography['buckets']['body_family'] = $default->body_font;
            }
        }

        $existing = DB::table('site_settings')->where('key', 'typography')->first();
        if ($existing) {
            DB::table('site_settings')->where('key', 'typography')->update([
                'value'      => json_encode($typography),
                'type'       => 'json',
                'group'      => 'design',
                'updated_at' => now(),
            ]);
        } else {
            DB::table('site_settings')->insert([
                'key'        => 'typography',
                'value'      => json_encode($typography),
                'type'       => 'json',
                'group'      => 'design',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn(['heading_font', 'body_font']);
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->string('heading_font')->nullable();
            $table->string('body_font')->nullable();
        });

        DB::table('site_settings')->where('key', 'typography')->delete();
    }
};
