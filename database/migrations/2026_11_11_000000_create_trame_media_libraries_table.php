<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner record for SpatieMediaProvider: Spatie Media Library attaches media
 * to a model, the Trame library is one row of this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trame_media_libraries', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trame_media_libraries');
    }
};
