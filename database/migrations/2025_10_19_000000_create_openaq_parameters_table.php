<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('openaq_parameters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('openaq_id')->unique();
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->string('units')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('openaq_parameters');
    }
};
