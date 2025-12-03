<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite doesn't support ALTER COLUMN, so we recreate the table
            Schema::table('keywords', function (Blueprint $table) {
                $table->string('search_intent_new')->nullable();
            });

            DB::statement('UPDATE keywords SET search_intent_new = search_intent');

            Schema::table('keywords', function (Blueprint $table) {
                $table->dropColumn('search_intent');
            });

            Schema::table('keywords', function (Blueprint $table) {
                $table->renameColumn('search_intent_new', 'search_intent');
            });
        } else {
            Schema::table('keywords', function (Blueprint $table) {
                $table->string('search_intent')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Update any null values to 'informational' before making NOT NULL
        DB::statement("UPDATE keywords SET search_intent = 'informational' WHERE search_intent IS NULL");

        if (DB::getDriverName() === 'sqlite') {
            Schema::table('keywords', function (Blueprint $table) {
                $table->string('search_intent_new')->default('informational');
            });

            DB::statement('UPDATE keywords SET search_intent_new = search_intent');

            Schema::table('keywords', function (Blueprint $table) {
                $table->dropColumn('search_intent');
            });

            Schema::table('keywords', function (Blueprint $table) {
                $table->renameColumn('search_intent_new', 'search_intent');
            });
        } else {
            Schema::table('keywords', function (Blueprint $table) {
                $table->string('search_intent')->default('informational')->nullable(false)->change();
            });
        }
    }
};
