<?php
/**
 * System Stats API Endpoint
 * Returns real-time CPU and memory usage
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

$stats = [
    'cpu' => 0,
    'cpu_5min' => 0,
    'memory' => 0,
    'timestamp' => time(),
    'load' => ['1min' => 0, '5min' => 0, '15min' => 0],
    'uptime' => '',
    'memory_details' => [],
    'top_processes' => []
];

// Get CPU usage and load averages
if (function_exists('sys_getloadavg')) {
    $load = sys_getloadavg();
    if ($load !== false) {
        // Use 1-minute load average for real-time feel
        // Normalize to percentage (assuming max load of 2.0 = 100%)
        $stats['cpu'] = min(100, ($load[0] / 2.0) * 100);
        
        // 5-minute average for green bar
        $stats['cpu_5min'] = min(100, ($load[1] / 2.0) * 100);
        
        // Store all three load averages for tooltip
        $stats['load'] = [
            '1min' => round($load[0], 2),
            '5min' => round($load[1], 2),
            '15min' => round($load[2], 2)
        ];
    }
}

// Get memory usage
if (stristr(PHP_OS, 'linux')) {
    // Linux: Read from /proc/meminfo
    $meminfo = file_get_contents('/proc/meminfo');
    if ($meminfo) {
        preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
        preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $available);
        
        if (isset($total[1]) && isset($available[1])) {
            $totalMem = $total[1];
            $availableMem = $available[1];
            $usedMem = $totalMem - $availableMem;
            $stats['memory'] = ($usedMem / $totalMem) * 100;
        }
    }
} elseif (stristr(PHP_OS, 'darwin') || stristr(PHP_OS, 'bsd')) {
    // macOS/BSD: Use vm_stat
    $vm_stat = shell_exec('vm_stat');
    if ($vm_stat) {
        preg_match('/Pages free:\s+(\d+)\./', $vm_stat, $free);
        preg_match('/Pages active:\s+(\d+)\./', $vm_stat, $active);
        preg_match('/Pages inactive:\s+(\d+)\./', $vm_stat, $inactive);
        preg_match('/Pages wired down:\s+(\d+)\./', $vm_stat, $wired);
        
        if (isset($free[1], $active[1], $inactive[1], $wired[1])) {
            $pageSize = 4096; // bytes
            $totalPages = $free[1] + $active[1] + $inactive[1] + $wired[1];
            $usedPages = $active[1] + $inactive[1] + $wired[1];
            
            if ($totalPages > 0) {
                $stats['memory'] = ($usedPages / $totalPages) * 100;
            }
        }
    }
} elseif (stristr(PHP_OS, 'win')) {
    // Windows: Use wmic
    $wmic = shell_exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /Value');
    if ($wmic) {
        preg_match('/FreePhysicalMemory=(\d+)/', $wmic, $free);
        preg_match('/TotalVisibleMemorySize=(\d+)/', $wmic, $total);
        
        if (isset($free[1]) && isset($total[1])) {
            $freeMem = $free[1];
            $totalMem = $total[1];
            $usedMem = $totalMem - $freeMem;
            $stats['memory'] = ($usedMem / $totalMem) * 100;
        }
    }
}

// Fallback: Use PHP memory if system memory unavailable
if ($stats['memory'] == 0) {
    $memLimit = ini_get('memory_limit');
    if ($memLimit != '-1') {
        $memLimitBytes = return_bytes($memLimit);
        $memUsage = memory_get_usage(true);
        $stats['memory'] = ($memUsage / $memLimitBytes) * 100;
    }
}

// Get uptime (Linux/Unix)
if (file_exists('/proc/uptime')) {
    $uptime = file_get_contents('/proc/uptime');
    if ($uptime) {
        $uptimeSeconds = floatval(explode(' ', $uptime)[0]);
        $days = floor($uptimeSeconds / 86400);
        $hours = floor(($uptimeSeconds % 86400) / 3600);
        $minutes = floor(($uptimeSeconds % 3600) / 60);
        $stats['uptime'] = sprintf('%dd %dh %dm', $days, $hours, $minutes);
    }
} elseif (stristr(PHP_OS, 'win')) {
    // Windows uptime
    $wmic = shell_exec('wmic os get lastbootuptime');
    if ($wmic && preg_match('/(\d{14})/', $wmic, $matches)) {
        $bootTime = DateTime::createFromFormat('YmdHis', $matches[1]);
        $now = new DateTime();
        $diff = $now->diff($bootTime);
        $stats['uptime'] = sprintf('%dd %dh %dm', $diff->days, $diff->h, $diff->i);
    }
}

// Get detailed memory info (Linux)
if (stristr(PHP_OS, 'linux') && file_exists('/proc/meminfo')) {
    $meminfo = file_get_contents('/proc/meminfo');
    if ($meminfo) {
        preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
        preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $available);
        preg_match('/MemFree:\s+(\d+)/', $meminfo, $free);
        preg_match('/Buffers:\s+(\d+)/', $meminfo, $buffers);
        preg_match('/Cached:\s+(\d+)/', $meminfo, $cached);
        
        if (isset($total[1])) {
            $totalMB = round($total[1] / 1024, 1);
            $availableMB = isset($available[1]) ? round($available[1] / 1024, 1) : 0;
            $usedMB = round(($total[1] - ($available[1] ?? $free[1] ?? 0)) / 1024, 1);
            $buffersMB = isset($buffers[1]) ? round($buffers[1] / 1024, 1) : 0;
            $cachedMB = isset($cached[1]) ? round($cached[1] / 1024, 1) : 0;
            
            $stats['memory_details'] = [
                'total' => $totalMB . ' MB',
                'used' => $usedMB . ' MB',
                'available' => $availableMB . ' MB',
                'buffers' => $buffersMB . ' MB',
                'cached' => $cachedMB . ' MB'
            ];
        }
    }
}

// Get top 5 processes by CPU (Linux/Unix)
if (stristr(PHP_OS, 'linux') || stristr(PHP_OS, 'darwin')) {
    $ps = shell_exec('ps aux --sort=-%cpu | head -6 | tail -5 2>/dev/null');
    if (!$ps) {
        // Try BSD/macOS format
        $ps = shell_exec('ps aux -r | head -6 | tail -5 2>/dev/null');
    }
    if ($ps) {
        $lines = explode("\n", trim($ps));
        foreach ($lines as $line) {
            if (empty($line)) continue;
            $parts = preg_split('/\s+/', $line, 11);
            if (count($parts) >= 11) {
                $stats['top_processes'][] = [
                    'cpu' => $parts[2] . '%',
                    'mem' => $parts[3] . '%',
                    'command' => substr($parts[10], 0, 30)
                ];
            }
        }
    }
} elseif (stristr(PHP_OS, 'win')) {
    // Windows top processes
    $wmic = shell_exec('wmic process get Caption,KernelModeTime /format:csv | findstr /V "^$" | sort /R /+1 | more +1 | findstr /N "^" | findstr "^[1-5]:"');
    if ($wmic) {
        $lines = explode("\n", trim($wmic));
        foreach ($lines as $line) {
            if (preg_match('/^\d+:(.+),(.+),(\d+)/', $line, $matches)) {
                $stats['top_processes'][] = [
                    'command' => substr($matches[2], 0, 30),
                    'cpu' => '-'
                ];
            }
        }
    }
}

echo json_encode($stats);

function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}
