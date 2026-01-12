<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description');
            $table->string('category'); // efficiency, streak, milestone, competitive, time, hidden
            $table->unsignedTinyInteger('tier')->nullable(); // for tiered badges (1, 2, 3...)
            $table->string('icon')->nullable(); // emoji or icon name
            $table->boolean('is_hidden')->default(false); // secret badges
            $table->boolean('is_active')->default(true);
            $table->json('requirements')->nullable(); // flexible requirements definition
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('category');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badges');
    }
};
