<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('notifications', 'action_url')) {
                $table->string('action_url', 1000)->nullable()->after('message');
            }
            if (! Schema::hasColumn('notifications', 'action_label')) {
                $table->string('action_label', 64)->nullable()->after('action_url');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table) {
            if (Schema::hasColumn('notifications', 'action_label')) {
                $table->dropColumn('action_label');
            }
            if (Schema::hasColumn('notifications', 'action_url')) {
                $table->dropColumn('action_url');
            }
        });
    }
};
