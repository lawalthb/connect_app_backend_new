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
        Schema::table('stream_cameras', function (Blueprint $table) {
            $table->string('device_id')->nullable()->after('device_type'); // Physical camera device ID
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stream_cameras', function (Blueprint $table) {
            $table->dropColumn('device_id');
        });
    }
};
