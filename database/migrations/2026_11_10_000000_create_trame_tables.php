<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trame_contents', function (Blueprint $table) {
            $table->id();
            $table->morphs('contentable');
            $table->string('key')->default('default');
            $table->json('document');
            $table->json('published')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->timestamps();

            $table->unique(['contentable_type', 'contentable_id', 'key']);
        });

        Schema::create('trame_pages', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->json('slug');
            $table->json('meta')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('trame_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kind')->index();
            $table->json('content');
            $table->boolean('is_global')->default(false);
            $table->timestamps();
        });

        Schema::create('trame_media', function (Blueprint $table) {
            $table->id();
            $table->string('disk');
            $table->string('path');
            $table->string('name');
            $table->string('mime');
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trame_media');
        Schema::dropIfExists('trame_templates');
        Schema::dropIfExists('trame_pages');
        Schema::dropIfExists('trame_contents');
    }
};
