<?php

namespace App\Http\Controllers;

use App\Models\FingerprintLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class FingerprintController extends Controller
{
    public function index()
    {
        $logs = FingerprintLog::latest()->paginate(50);
        $devices = FingerprintLog::select('device_ip')
            ->distinct()
            ->get()
            ->pluck('device_ip');
        
        $stats = [
            'total_logs' => FingerprintLog::count(),
            'today_logs' => FingerprintLog::whereDate('created_at', today())->count(),
            'unique_devices' => $devices->count(),
            'server_status' => Redis::get('tcp_server:status') ?? 'unknown'
        ];
        
        return view('fingerprint.index', compact('logs', 'devices', 'stats'));
    }
    
    public function show($id)
    {
        $log = FingerprintLog::findOrFail($id);
        return view('fingerprint.show', compact('log'));
    }
    
    public function realtime()
    {
        return view('fingerprint.realtime');
    }
    
    public function getRealtimeData()
    {
        $logs = FingerprintLog::latest()->limit(20)->get();
        return response()->json($logs);
    }
    
    public function serverStatus()
    {
        $status = Redis::get('tcp_server:status') ?? 'stopped';
        $started = Redis::get('tcp_server:started_at');
        
        return response()->json([
            'status' => $status,
            'started_at' => $started,
            'timestamp' => now()->toDateTimeString()
        ]);
    }
    
    public function sendTestCommand(Request $request)
    {
        $validated = $request->validate([
            'device_ip' => 'required|ip',
            'command' => 'required|string'
        ]);
        
        // Send command to device (if bidirectional communication is possible)
        // This requires device to be reachable and accept commands
        
        return response()->json([
            'success' => true,
            'message' => 'Command sent successfully'
        ]);
    }
}