#!/usr/bin/env php
<?php
/**
 * DokuWiki Calendar - JSON Corruption Fixer
 * 
 * This script finds and fixes corrupted calendar JSON files where
 * event arrays are stored as objects instead of arrays.
 * 
 * Usage: php fix_corrupted_json.php /path/to/dokuwiki
 */

if ($argc < 2) {
    echo "Usage: php fix_corrupted_json.php /path/to/dokuwiki\n";
    echo "Example: php fix_corrupted_json.php /var/www/html/dokuwiki\n";
    exit(1);
}

$dokuwikiPath = rtrim($argv[1], '/');
$metaDir = $dokuwikiPath . '/data/meta';

if (!is_dir($metaDir)) {
    echo "Error: Directory not found: $metaDir\n";
    exit(1);
}

echo "Scanning for calendar JSON files in: $metaDir\n\n";

$filesChecked = 0;
$filesFixed = 0;
$errors = [];

function scanDirectory($dir, &$filesChecked, &$filesFixed, &$errors) {
    $items = scandir($dir);
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $path = $dir . '/' . $item;
        
        if (is_dir($path)) {
            // Check if this is a calendar directory
            if ($item === 'calendar') {
                echo "Found calendar directory: $path\n";
                scanCalendarDir($path, $filesChecked, $filesFixed, $errors);
            } else {
                // Recurse into subdirectories
                scanDirectory($path, $filesChecked, $filesFixed, $errors);
            }
        }
    }
}

function scanCalendarDir($calendarDir, &$filesChecked, &$filesFixed, &$errors) {
    $files = glob($calendarDir . '/*.json');
    
    foreach ($files as $file) {
        $filesChecked++;
        echo "  Checking: " . basename($file) . " ... ";
        
        $contents = file_get_contents($file);
        $data = json_decode($contents, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "ERROR - Invalid JSON: " . json_last_error_msg() . "\n";
            $errors[] = $file . ": " . json_last_error_msg();
            continue;
        }
        
        $fixed = false;
        
        // Check each date key
        foreach ($data as $dateKey => $events) {
            if (!is_array($events)) {
                echo "\n    CORRUPTION FOUND at $dateKey - not an array!\n";
                echo "    Type: " . gettype($events) . "\n";
                echo "    Value: " . var_export($events, true) . "\n";
                
                // Fix it by wrapping in array if it's an object/single event
                if (is_object($events) || (is_array($events) && isset($events['id']))) {
                    $data[$dateKey] = [$events];
                    echo "    FIXED: Wrapped in array\n";
                    $fixed = true;
                } else {
                    // Can't fix automatically
                    echo "    ERROR: Cannot auto-fix, unknown structure\n";
                    $errors[] = "$file at $dateKey: Cannot auto-fix";
                }
            } else {
                // Verify each event in the array is valid
                foreach ($events as $idx => $event) {
                    if (!is_array($event) || !isset($event['id'])) {
                        echo "\n    CORRUPTION FOUND at $dateKey[$idx] - invalid event structure!\n";
                        $fixed = true;
                        unset($data[$dateKey][$idx]);
                        echo "    REMOVED: Invalid event\n";
                    }
                }
                
                // Re-index array after removals
                if ($fixed) {
                    $data[$dateKey] = array_values($data[$dateKey]);
                }
            }
        }
        
        if ($fixed) {
            // Backup original
            $backupFile = $file . '.backup.' . date('YmdHis');
            copy($file, $backupFile);
            echo "    Backup created: " . basename($backupFile) . "\n";
            
            // Write fixed version
            file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
            echo "    SAVED FIXED VERSION\n";
            $filesFixed++;
        } else {
            echo "OK\n";
        }
    }
}

// Start scanning
scanDirectory($metaDir, $filesChecked, $filesFixed, $errors);

echo "\n========================================\n";
echo "SUMMARY:\n";
echo "  Files checked: $filesChecked\n";
echo "  Files fixed: $filesFixed\n";
echo "  Errors: " . count($errors) . "\n";

if (!empty($errors)) {
    echo "\nERRORS:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}

echo "\nDone!\n";
