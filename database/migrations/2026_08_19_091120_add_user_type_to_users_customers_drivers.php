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
        Schema::table('users', function (Blueprint $table) {
            $table->string('user_type')->default('admin')->after('email');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('user_type')->default('customer')->after('email');
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->string('user_type')->default('driver')->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('user_type');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('user_type');
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn('user_type');
        });
    }
};
