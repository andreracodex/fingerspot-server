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
       Schema::create('fingerprint_logs', function (Blueprint $table) {
            $table->id();
            $table->string('device_ip');
            $table->text('raw_data'); // Hex format
            $table->integer('data_length');
            $table->string('command_code')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('user_id')->nullable();
            $table->string('event_type')->nullable(); // check_in, check_out, etc.
            $table->timestamp('timestamp')->nullable();
            $table->boolean('checksum_valid')->default(false);
            $table->json('parsed_data')->nullable();
            $table->timestamps();
            
            $table->index('device_ip');
            $table->index('timestamp');
            $table->index('user_id');
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('fingerprint_logs');
    }
};
