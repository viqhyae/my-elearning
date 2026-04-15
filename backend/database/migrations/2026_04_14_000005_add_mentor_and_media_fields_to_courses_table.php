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
        Schema::table('courses', function (Blueprint $table): void {
            $table->foreignId('mentor_user_id')->nullable()->after('category')->constrained('users')->nullOnDelete();
            $table->string('trailer_video_url', 500)->nullable()->after('mentor_user_id');
            $table->json('tools')->nullable()->after('trailer_video_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('mentor_user_id');
            $table->dropColumn(['trailer_video_url', 'tools']);
        });
    }
};
