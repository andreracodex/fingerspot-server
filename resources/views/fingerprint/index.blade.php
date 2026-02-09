@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5>Total Logs</h5>
                    <h2>{{ $stats['total_logs'] }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5>Today's Logs</h5>
                    <h2>{{ $stats['today_logs'] }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5>Devices</h5>
                    <h2>{{ $stats['unique_devices'] }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card {{ $stats['server_status'] == 'running' ? 'bg-success' : 'bg-danger' }} text-white">
                <div class="card-body">
                    <h5>Server Status</h5>
                    <h2>{{ ucfirst($stats['server_status']) }}</h2>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h4>Recent Fingerprint Logs</h4>
            <a href="{{ route('fingerprint.realtime') }}" class="btn btn-primary btn-sm">Live View</a>
        </div>
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Device IP</th>
                        <th>Serial</th>
                        <th>User ID</th>
                        <th>Event</th>
                        <th>Data</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($logs as $log)
                    <tr>
                        <td>{{ $log->created_at->format('H:i:s') }}</td>
                        <td><code>{{ $log->device_ip }}</code></td>
                        <td>{{ $log->serial_number ?? 'N/A' }}</td>
                        <td>{{ $log->user_id ?? 'N/A' }}</td>
                        <td>
                            <span class="badge bg-{{ $log->event_type == 'check_in' ? 'success' : 'warning' }}">
                                {{ $log->event_type ?? 'Unknown' }}
                            </span>
                        </td>
                        <td>
                            <small>{{ Str::limit($log->raw_data, 30) }}</small>
                        </td>
                        <td>
                            <a href="{{ route('fingerprint.show', $log->id) }}" class="btn btn-sm btn-info">View</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            
            {{ $logs->links() }}
        </div>
    </div>
</div>
@endsection