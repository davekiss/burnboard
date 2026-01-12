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
            $table->string('github_id')->nullable()->unique()->after('id');
            $table->string('github_username')->nullable()->after('github_id');
            $table->string('avatar_url')->nullable()->after('github_username');
            $table->string('api_token', 64)->nullable()->unique()->after('remember_token');
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['github_id', 'github_username', 'avatar_url', 'api_token']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
