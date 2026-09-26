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
        // 1. Customers table
        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                if (!Schema::hasColumn('customers', 'street_address')) {
                    $table->string('street_address')->nullable()->after('address');
                }
                if (!Schema::hasColumn('customers', 'city')) {
                    $table->string('city')->nullable()->after('street_address');
                }
            });
        }

        // 2. Customer Addresses table
        if (Schema::hasTable('customer_addresses')) {
            Schema::table('customer_addresses', function (Blueprint $table) {
                if (!Schema::hasColumn('customer_addresses', 'street_address')) {
                    $table->string('street_address')->nullable()->after('address_line');
                }
                if (!Schema::hasColumn('customer_addresses', 'city')) {
                    $table->string('city')->nullable()->after('street_address');
                }
            });
        }

        // 3. Drivers table
        if (Schema::hasTable('drivers')) {
            Schema::table('drivers', function (Blueprint $table) {
                if (!Schema::hasColumn('drivers', 'street_address')) {
                    $table->string('street_address')->nullable()->after('address');
                }
                if (!Schema::hasColumn('drivers', 'city')) {
                    $table->string('city')->nullable()->after('street_address');
                }
                if (!Schema::hasColumn('drivers', 'pincode')) {
                    $table->string('pincode')->nullable()->after('city');
                }
            });
        }

        // 4. Orders table
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasColumn('orders', 'customer_address')) {
                    $table->text('customer_address')->nullable()->after('customer');
                }
                if (!Schema::hasColumn('orders', 'street_address')) {
                    $table->string('street_address')->nullable()->after('customer_address');
                }
                if (!Schema::hasColumn('orders', 'city')) {
                    $table->string('city')->nullable()->after('street_address');
                }
                if (!Schema::hasColumn('orders', 'pincode')) {
                    $table->string('pincode')->nullable()->after('city');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                if (Schema::hasColumn('customers', 'city')) $table->dropColumn('city');
                if (Schema::hasColumn('customers', 'street_address')) $table->dropColumn('street_address');
            });
        }

        if (Schema::hasTable('customer_addresses')) {
            Schema::table('customer_addresses', function (Blueprint $table) {
                if (Schema::hasColumn('customer_addresses', 'city')) $table->dropColumn('city');
                if (Schema::hasColumn('customer_addresses', 'street_address')) $table->dropColumn('street_address');
            });
        }

        if (Schema::hasTable('drivers')) {
            Schema::table('drivers', function (Blueprint $table) {
                if (Schema::hasColumn('drivers', 'pincode')) $table->dropColumn('pincode');
                if (Schema::hasColumn('drivers', 'city')) $table->dropColumn('city');
                if (Schema::hasColumn('drivers', 'street_address')) $table->dropColumn('street_address');
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (Schema::hasColumn('orders', 'pincode')) $table->dropColumn('pincode');
                if (Schema::hasColumn('orders', 'city')) $table->dropColumn('city');
                if (Schema::hasColumn('orders', 'street_address')) $table->dropColumn('street_address');
                if (Schema::hasColumn('orders', 'customer_address')) $table->dropColumn('customer_address');
            });
        }
    }
};
