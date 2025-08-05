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
        Schema::table('images', function (Blueprint $table) {
            $table->boolean('has_faces')->default(false);
            $table->integer('face_count')->default(0);
            $table->longText('face_features')->nullable(); // JSON array of face features
            $table->text('face_rectangles')->nullable(); // JSON array of face rectangles
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropColumn(['has_faces', 'face_count', 'face_features', 'face_rectangles']);
        });
    }
}; 