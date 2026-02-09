<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FingerprintLog extends Model
{
    protected $fillable = [
        'device_ip',
        'raw_data',
        'data_length',
        'command_code',
        'serial_number',
        'user_id',
        'event_type',
        'timestamp',
        'checksum_valid',
        'parsed_data'
    ];
    
    protected $casts = [
        'parsed_data' => 'array',
        'checksum_valid' => 'boolean',
        'timestamp' => 'datetime'
    ];
}
