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
        Schema::create('server_status', function (Blueprint $table) {
            $table->id();
            $table->string('server_name')->unique();
            $table->string('status')->default('stopped'); // running, stopped, error
            $table->timestamp('started_at')->nullable();
            $table->integer('pid')->nullable();
            $table->integer('connections')->default(0);
            $table->json('metrics')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_status');
    }
};
