<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'sitemap_url')) {
                $table->string('sitemap_url')->nullable()->after('cta_action_text');
            }
            if (! Schema::hasColumn('projects', 'auto_internal_linking')) {
                $table->boolean('auto_internal_linking')->default(false)->after('sitemap_url');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['sitemap_url', 'auto_internal_linking']);
        });
    }
};
