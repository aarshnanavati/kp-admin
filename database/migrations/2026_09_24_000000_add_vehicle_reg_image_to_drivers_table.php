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
        if (Schema::hasTable('drivers') && !Schema::hasColumn('drivers', 'vehicle_reg_image')) {
            Schema::table('drivers', function (Blueprint $table) {
                $table->string('vehicle_reg_image')->nullable()->after('vehicle_reg_no');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('drivers') && Schema::hasColumn('drivers', 'vehicle_reg_image')) {
            Schema::table('drivers', function (Blueprint $table) {
                $table->dropColumn('vehicle_reg_image');
            });
        }
    }
};
