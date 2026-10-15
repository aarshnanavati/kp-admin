<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'customers', 'drivers'] as $table) {
            if (!Schema::hasColumn($table, 'api_token_expires_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->timestamp('api_token_expires_at')->nullable()->after('api_token');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['users', 'customers', 'drivers'] as $table) {
            if (Schema::hasColumn($table, 'api_token_expires_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('api_token_expires_at');
                });
            }
        }
    }
};
