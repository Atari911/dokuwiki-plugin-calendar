#!/usr/bin/env php
<?php
/**
 * Debug script to inspect calendar event JSON files
 * 
 * Usage: php debug_events.php [namespace] [year-month]
 * Example: php debug_events.php "" 2027-03
 * Example: php debug_events.php "team" 2026-01
 */

// Set up DokuWiki environment
if (!defined('DOKU_INC')) {
    define('DOKU_INC', dirname(__FILE__) . '/../../../');
}

$namespace = isset($argv[1]) ? $argv[1] : '';
$yearMonth = isset($argv[2]) ? $argv[2] : date('Y-m');

list($year, $month) = explode('-', $yearMonth);

$dataDir = DOKU_INC . 'data/meta/';
if ($namespace) {
    $dataDir .= str_replace(':', '/', $namespace) . '/';
}
$dataDir .= 'calendar/';

$eventFile = $dataDir . sprintf('%04d-%02d.json', $year, $month);

echo "===================================\n";
echo "Calendar Event File Inspector\n";
echo "===================================\n\n";

echo "Namespace: " . ($namespace ?: '(default)') . "\n";
echo "Year-Month: $year-$month\n";
echo "File path: $eventFile\n\n";

if (!file_exists($eventFile)) {
    echo "❌ FILE DOES NOT EXIST\n";
    echo "\nChecking directory: $dataDir\n";
    
    if (is_dir($dataDir)) {
        echo "Directory exists. Files in directory:\n";
        $files = scandir($dataDir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                echo "  - $file\n";
            }
        }
    } else {
        echo "❌ Directory does not exist: $dataDir\n";
    }
    exit(1);
}

echo "✓ File exists\n";
echo "File size: " . filesize($eventFile) . " bytes\n\n";

$contents = file_get_contents($eventFile);

echo "===================================\n";
echo "RAW FILE CONTENTS:\n";
echo "===================================\n";
echo $contents . "\n\n";

echo "===================================\n";
echo "JSON VALIDATION:\n";
echo "===================================\n";

$events = json_decode($contents, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "❌ JSON PARSE ERROR: " . json_last_error_msg() . "\n";
    echo "\nAttempting to identify the problem...\n";
    
    // Try to find the error location
    $lines = explode("\n", $contents);
    echo "File has " . count($lines) . " lines\n";
    
    // Check for common issues
    if (strpos($contents, "\0") !== false) {
        echo "⚠️  WARNING: File contains NULL bytes\n";
    }
    
    if (!mb_check_encoding($contents, 'UTF-8')) {
        echo "⚠️  WARNING: File is not valid UTF-8\n";
    }
    
    exit(1);
}

echo "✓ Valid JSON\n\n";

echo "===================================\n";
echo "PARSED EVENTS:\n";
echo "===================================\n";

if (empty($events)) {
    echo "No events in this month\n";
} else {
    echo "Total dates with events: " . count($events) . "\n\n";
    
    foreach ($events as $date => $dayEvents) {
        echo "Date: $date\n";
        echo "  Events: " . count($dayEvents) . "\n";
        
        foreach ($dayEvents as $idx => $event) {
            echo "\n  Event #" . ($idx + 1) . ":\n";
            echo "    ID: " . ($event['id'] ?? 'MISSING') . "\n";
            echo "    Title: " . ($event['title'] ?? 'MISSING') . "\n";
            echo "    Time: " . ($event['time'] ?? 'none') . "\n";
            echo "    Color: " . ($event['color'] ?? 'MISSING') . "\n";
            echo "    EndDate: " . ($event['endDate'] ?? 'none') . "\n";
            echo "    IsTask: " . (isset($event['isTask']) ? ($event['isTask'] ? 'true' : 'false') : 'MISSING') . "\n";
            echo "    Recurring: " . (isset($event['recurring']) ? ($event['recurring'] ? 'true' : 'false') : 'false') . "\n";
            
            if (isset($event['recurring']) && $event['recurring']) {
                echo "    RecurringId: " . ($event['recurringId'] ?? 'MISSING') . "\n";
            }
            
            // Check for problematic data
            if (isset($event['description'])) {
                $descLen = strlen($event['description']);
                echo "    Description: " . $descLen . " chars";
                
                if ($descLen > 1000) {
                    echo " ⚠️  VERY LONG";
                }
                
                if (!mb_check_encoding($event['description'], 'UTF-8')) {
                    echo " ⚠️  INVALID UTF-8";
                }
                echo "\n";
            } else {
                echo "    Description: none\n";
            }
            
            // Check for unexpected fields
            $expectedFields = ['id', 'title', 'time', 'description', 'color', 'isTask', 'completed', 'endDate', 'recurring', 'recurringId', 'created'];
            foreach ($event as $key => $value) {
                if (!in_array($key, $expectedFields)) {
                    echo "    ⚠️  UNEXPECTED FIELD: $key\n";
                }
            }
        }
        echo "\n";
    }
}

echo "===================================\n";
echo "SUMMARY:\n";
echo "===================================\n";
echo "File: ✓ Valid\n";
echo "JSON: ✓ Valid\n";
echo "Events: " . (empty($events) ? 0 : count($events)) . " dates\n";

$totalEvents = 0;
foreach ($events as $dayEvents) {
    $totalEvents += count($dayEvents);
}
echo "Total event instances: $totalEvents\n";

echo "\n✓ Inspection complete\n";
