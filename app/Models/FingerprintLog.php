<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FingerprintLog extends Model
{
    protected $fillable = [
        'device_ip',
        'device_serial',
        'raw_data',
        'data_length',
        'command_code',
        'user_id',
        'event_type',
        'timestamp',
        'checksum_valid',
        'parsed_data',
    ];
    
    protected $casts = [
        'parsed_data' => 'array',
        'checksum_valid' => 'boolean',
        'timestamp' => 'datetime',
    ];
    
    // Add this method for easy hex viewing
    public function getFormattedHexAttribute()
    {
        $hex = $this->raw_data;
        if (empty($hex)) {
            return '';
        }
        
        // Format as grouped hex
        return implode(' ', str_split($hex, 2));
    }
}
