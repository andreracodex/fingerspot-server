<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\FingerprintLog;

class FingerspotController extends Controller
{
    /**
     * Handle incoming data from Fingerspot device
     * POST /api/fingerspot/webhook
     */
    public function webhook(Request $request)
    {
        // Log incoming request
        Log::channel('fingerprint')->info('=== NEW REQUEST ===', [
            'time' => now()->toDateTimeString(),
            'ip' => $request->ip(),
            'method' => $request->method(),
            'headers' => $request->headers->all(),
        ]);
        
        // Determine content type
        $contentType = $request->header('Content-Type');
        $rawData = '';
        
        if (str_contains($contentType, 'json')) {
            // JSON data
            $data = $request->json()->all();
            $rawData = json_encode($data);
            Log::channel('fingerprint')->info('JSON data received', $data);
        } else {
            // Binary/Hex data (most common for fingerprint devices)
            $rawContent = $request->getContent();
            $rawData = bin2hex($rawContent);
            $data = $this->parseBinaryData($rawContent);
            Log::channel('fingerprint')->info('Binary data received', [
                'hex' => $rawData,
                'length' => strlen($rawContent),
            ]);
        }
        
        // Extract device information
        $deviceInfo = $this->extractDeviceInfo($request, $data);
        
        // Save to database
        $log = FingerprintLog::create([
            'device_ip' => $deviceInfo['ip'],
            'device_serial' => $deviceInfo['serial'],
            'raw_data' => $rawData,
            'data_length' => strlen($request->getContent()),
            'command_code' => $data['command'] ?? null,
            'user_id' => $data['user_id'] ?? $data['uid'] ?? null,
            'event_type' => $data['event'] ?? $data['type'] ?? 'unknown',
            'timestamp' => $data['timestamp'] ?? now(),
            'checksum_valid' => $data['checksum_valid'] ?? false,
            'parsed_data' => json_encode($data),
        ]);
        
        Log::channel('fingerprint')->info('Data saved', ['log_id' => $log->id]);
        
        // Return ACK response (device may expect this)
        return response()->json([
            'status' => 'success',
            'ack_code' => 'AA5506',
            'log_id' => $log->id,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
    
    /**
     * Parse binary data from Fingerspot device
     */
    private function parseBinaryData($binary)
    {
        if (empty($binary)) {
            return [];
        }
        
        $parsed = [];
        $bytes = unpack('C*', $binary);
        
        // Check for AA55 header (common in Fingerspot)
        if (count($bytes) >= 2 && $bytes[1] == 0xAA && $bytes[2] == 0x55) {
            $parsed['header'] = 'AA55';
            $parsed['command'] = $bytes[3] ?? 0;
            $parsed['length'] = $bytes[4] ?? 0;
            
            // Extract serial number (positions may vary)
            if (count($bytes) >= 14) {
                $serialBytes = array_slice($bytes, 5, 10);
                $serial = '';
                foreach ($serialBytes as $byte) {
                    if ($byte >= 32 && $byte <= 126) {
                        $serial .= chr($byte);
                    }
                }
                if (!empty($serial)) {
                    $parsed['serial'] = $serial;
                }
            }
            
            // Extract user ID (example position)
            if (count($bytes) >= 18) {
                $parsed['user_id'] = ($bytes[15] << 8) | $bytes[16];
            }
            
            // Extract timestamp if present
            if (count($bytes) >= 24) {
                $year = ($bytes[17] << 8) | $bytes[18];
                $month = $bytes[19];
                $day = $bytes[20];
                $hour = $bytes[21];
                $minute = $bytes[22];
                $second = $bytes[23];
                $parsed['timestamp'] = sprintf("%04d-%02d-%02d %02d:%02d:%02d", 
                    $year, $month, $day, $hour, $minute, $second);
            }
        }
        
        return $parsed;
    }
    
    /**
     * Extract device information from request
     */
    private function extractDeviceInfo(Request $request, array $data)
    {
        $info = [
            'ip' => $request->ip(),
            'serial' => null,
            'model' => null,
        ];
        
        // Try to get from data first
        if (!empty($data['serial'])) {
            $info['serial'] = $data['serial'];
        } elseif (!empty($data['device_id'])) {
            $info['serial'] = $data['device_id'];
        }
        
        // Try to get from User-Agent header
        $userAgent = $request->header('User-Agent');
        if (str_contains($userAgent, 'Fingerspot')) {
            $info['model'] = $userAgent;
        }
        
        // Try to get from custom headers
        if ($request->hasHeader('X-Device-Serial')) {
            $info['serial'] = $request->header('X-Device-Serial');
        }
        
        return $info;
    }
    
    /**
     * Test endpoint - GET /api/fingerspot/test
     */
    public function test()
    {
        return response()->json([
            'status' => 'online',
            'service' => 'Fingerspot API',
            'endpoints' => [
                'webhook' => 'POST /api/fingerspot/webhook',
                'test' => 'GET /api/fingerspot/test',
                'logs' => 'GET /api/fingerspot/logs',
            ],
            'timestamp' => now()->toDateTimeString(),
            'instructions' => 'Send POST request with binary/hex data to webhook endpoint',
        ]);
    }
    
    /**
     * Get recent logs - GET /api/fingerspot/logs
     */
    public function logs()
    {
        $logs = FingerprintLog::latest()->take(50)->get();
        
        return response()->json([
            'count' => $logs->count(),
            'logs' => $logs,
        ]);
    }
}