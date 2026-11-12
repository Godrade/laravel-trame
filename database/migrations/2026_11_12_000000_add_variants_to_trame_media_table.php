<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trame_media', function (Blueprint $table) {
            // Resized copies for srcset: [{"path": "trame/a-480w.jpg", "width": 480}, ...]
            $table->json('variants')->nullable()->after('height');
        });
    }

    public function down(): void
    {
        Schema::table('trame_media', function (Blueprint $table) {
            $table->dropColumn('variants');
        });
    }
};
