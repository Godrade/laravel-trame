<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Draft history of a content: snapshots taken on save, publish and restore. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trame_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trame_content_id')->constrained('trame_contents')->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->string('kind', 20);
            $table->json('document');
            // No foreign key: the host application's user table is unknown.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index(['trame_content_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trame_revisions');
    }
};
