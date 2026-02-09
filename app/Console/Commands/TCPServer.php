<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\FingerprintLog;
use App\Events\FingerprintReceived;

class TCPServer extends Command
{
    protected $signature = 'tcp:serve {--host=0.0.0.0} {--port=9001}';
    protected $description = 'Start TCP server for Fingerspot devices';
    
    private $server;
    private $clients = [];
    private $serverLogFile;

    public function handle()
    {
        $host = $this->option('host');
        $port = $this->option('port');
        
        // Create log directory
        $logDir = storage_path('logs/tcp-server');
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $this->serverLogFile = $logDir . '/server-' . date('Y-m-d') . '.log';
        
        // Create TCP socket
        $this->server = stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr);
        
        if (!$this->server) {
            $this->error("Failed to create server: $errstr ($errno)");
            $this->logError("Server creation failed: $errstr ($errno)");
            return 1;
        }
        
        $this->logInfo("Server started on {$host}:{$port}");
        $this->info("Fingerspot TCP Server listening on {$host}:{$port}");
        $this->info("PID: " . getmypid());
        $this->info("Log file: {$this->serverLogFile}");
        
        // Store server info in database
        DB::table('server_status')->updateOrInsert(
            ['server_name' => 'fingerspot_tcp'],
            [
                'status' => 'running',
                'started_at' => now(),
                'pid' => getmypid(),
                'updated_at' => now()
            ]
        );
        
        $this->listen();
        
        return 0;
    }
    
    private function logInfo($message)
    {
        $logEntry = '[' . date('Y-m-d H:i:s') . "] INFO: {$message}\n";
        file_put_contents($this->serverLogFile, $logEntry, FILE_APPEND);
        $this->info($message);
    }
    
    private function logError($message)
    {
        $logEntry = '[' . date('Y-m-d H:i:s') . "] ERROR: {$message}\n";
        file_put_contents($this->serverLogFile, $logEntry, FILE_APPEND);
        $this->error($message);
    }
    
    private function listen()
    {
        $read = [$this->server];
        $write = $except = null;
        
        while (true) {
            $changed = $read;
            
            // Add client sockets
            foreach ($this->clients as $clientId => $client) {
                $changed[] = $client['socket'];
            }
            
            // Wait for activity (1 second timeout)
            if (@stream_select($changed, $write, $except, 1) > 0) {
                
                foreach ($changed as $socket) {
                    // New connection
                    if ($socket === $this->server) {
                        $this->acceptConnection($socket);
                    } 
                    // Existing client activity
                    else {
                        $this->handleClient($socket);
                    }
                }
            }
            
            // Clean up disconnected clients every 10 seconds
            static $lastCleanup = 0;
            if (time() - $lastCleanup > 10) {
                $this->cleanupClients();
                $lastCleanup = time();
            }
        }
    }
    
    private function acceptConnection($serverSocket)
    {
        $client = stream_socket_accept($serverSocket, 0);
        
        if ($client) {
            stream_set_blocking($client, false);
            $clientId = (int)$client;
            $clientIp = @stream_socket_get_name($client, true);
            
            if (!$clientIp) {
                fclose($client);
                return;
            }
            
            list($ip, $port) = explode(':', $clientIp);
            
            $this->clients[$clientId] = [
                'socket' => $client,
                'ip' => $ip,
                'port' => $port,
                'connected_at' => now()->toDateTimeString(),
                'last_activity' => time()
            ];
            
            $this->logInfo("New connection from: {$ip}:{$port}");
        }
    }
    
    private function handleClient($clientSocket)
    {
        $clientId = (int)$clientSocket;
        
        if (!isset($this->clients[$clientId])) {
            return;
        }
        
        $data = fread($clientSocket, 4096);
        
        if ($data === false || strlen($data) === 0) {
            // Client disconnected
            $this->disconnectClient($clientId);
            return;
        }
        
        // Update last activity
        $this->clients[$clientId]['last_activity'] = time();
        
        // Process the data
        $this->processData($clientId, $data);
        
        // Send acknowledgment (if required)
        $this->sendAck($clientSocket);
    }
    
    private function processData($clientId, $data)
    {
        $client = $this->clients[$clientId];
        $hexData = bin2hex($data);
        
        $this->logInfo("Data from {$client['ip']}: {$hexData}");
        
        // Parse Fingerspot data
        $parsed = $this->parseFingerspotPacket($data, $client);
        
        try {
            // Save to database
            $log = FingerprintLog::create([
                'device_ip' => $client['ip'],
                'raw_data' => $hexData,
                'data_length' => strlen($data),
                'command_code' => $parsed['command'] ?? null,
                'serial_number' => $parsed['serial'] ?? null,
                'user_id' => $parsed['user_id'] ?? null,
                'event_type' => $parsed['event'] ?? null,
                'timestamp' => $parsed['timestamp'] ?? now(),
                'checksum_valid' => $parsed['checksum_valid'] ?? false,
                'parsed_data' => json_encode($parsed),
            ]);
            
            // Trigger event (if you want to use Laravel events)
            // event(new FingerprintReceived($log));
            
            // Log success
            $this->logInfo("Saved log #{$log->id} from {$client['ip']}");
            
        } catch (\Exception $e) {
            $this->logError("Failed to save data from {$client['ip']}: " . $e->getMessage());
        }
    }
    
    private function parseFingerspotPacket($data, $client)
    {
        $parsed = [
            'received_at' => now()->toDateTimeString(),
            'device_ip' => $client['ip'],
            'raw_hex' => bin2hex($data),
            'packet_length' => strlen($data)
        ];
        
        $bytes = unpack('C*', $data);
        $bytes = array_values($bytes);
        
        // Basic parsing - adjust based on your device's protocol
        if (count($bytes) >= 4) {
            // Check for common headers
            if ($bytes[0] == 0xAA && $bytes[1] == 0x55) {
                $parsed['header'] = 'AA55';
                $parsed['command'] = dechex($bytes[2]);
                $parsed['data_length'] = $bytes[3];
                
                // Try to extract serial number from known position
                // This is device-specific - you'll need to analyze actual packets
                if (isset($bytes[4]) && count($bytes) > 10) {
                    // Example: extract bytes 4-13 as ASCII serial
                    $serialBytes = array_slice($bytes, 4, 10);
                    $serial = '';
                    foreach ($serialBytes as $byte) {
                        if ($byte >= 32 && $byte <= 126) { // Printable ASCII
                            $serial .= chr($byte);
                        }
                    }
                    if (!empty($serial)) {
                        $parsed['serial'] = $serial;
                    }
                }
            }
            // Another common header
            elseif ($bytes[0] == 0x55 && $bytes[1] == 0xAA) {
                $parsed['header'] = '55AA';
                $parsed['command'] = dechex($bytes[2]);
            }
        }
        
        return $parsed;
    }
    
    private function sendAck($socket)
    {
        // Common Fingerspot ACK: AA 55 06
        $ack = hex2bin('AA5506');
        @fwrite($socket, $ack);
    }
    
    private function disconnectClient($clientId)
    {
        if (isset($this->clients[$clientId])) {
            $client = $this->clients[$clientId];
            @fclose($client['socket']);
            
            $this->logInfo("Client disconnected: {$client['ip']}:{$client['port']}");
            unset($this->clients[$clientId]);
        }
    }
    
    private function cleanupClients()
    {
        $timeout = 300; // 5 minutes
        $now = time();
        
        foreach ($this->clients as $clientId => $client) {
            if ($now - $client['last_activity'] > $timeout) {
                $this->disconnectClient($clientId);
            }
        }
    }
}