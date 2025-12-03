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
        Schema::table('keywords', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'status']);
            $table->dropIndex(['status', 'priority']);
            $table->dropColumn(['status', 'error_message']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('keywords', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('priority');
            $table->text('error_message')->nullable()->after('status');
            $table->index(['project_id', 'status']);
            $table->index(['status', 'priority']);
        });
    }
};
