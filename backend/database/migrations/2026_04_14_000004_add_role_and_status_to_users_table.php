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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 30)->default('student')->after('password');
            $table->string('status', 20)->default('active')->after('role');
        });

        DB::table('users')->whereNull('role')->update(['role' => 'student']);
        DB::table('users')->whereNull('status')->update(['status' => 'active']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['role', 'status']);
        });
    }
};
