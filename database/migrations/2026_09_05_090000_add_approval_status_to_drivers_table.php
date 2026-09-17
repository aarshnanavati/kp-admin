<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A newly self-registered driver stays "Pending" until an admin reviews the
     * full profile and approves it. Only "Approved" drivers can log in.
     */
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->string('approval_status')->default('Pending')->after('status'); // Pending | Approved | Rejected
            $table->text('rejection_reason')->nullable()->after('approval_status');
            $table->timestamp('reviewed_at')->nullable()->after('rejection_reason');
            $table->unsignedBigInteger('reviewed_by')->nullable()->after('reviewed_at');
        });

        // Existing drivers are already operating - grandfather them in as approved.
        DB::table('drivers')->update(['approval_status' => 'Approved']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn(['approval_status', 'rejection_reason', 'reviewed_at', 'reviewed_by']);
        });
    }
};
