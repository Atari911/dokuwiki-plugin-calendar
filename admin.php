<?php
/**
 * Calendar Plugin - Admin Interface
 * Clean rewrite - Configuration only
 * Version: 3.3
 */

if(!defined('DOKU_INC')) die();

class admin_plugin_calendar extends DokuWiki_Admin_Plugin {

    public function getMenuText($language) {
        return 'Calendar Management';
    }

    public function getMenuSort() {
        return 100;
    }
    
    public function forAdminOnly() {
        return true;
    }
    
    /**
     * Public entry point for AJAX actions routed from action.php
     */
    public function handleAjaxAction($action) {
        // Verify admin privileges for all admin AJAX actions
        if (!auth_isadmin()) {
            echo json_encode(['success' => false, 'error' => 'Admin access required']);
            return;
        }
        
        switch ($action) {
            case 'cleanup_empty_namespaces': $this->handleCleanupEmptyNamespaces(); break;
            case 'trim_all_past_recurring': $this->handleTrimAllPastRecurring(); break;
            case 'rescan_recurring': $this->handleRescanRecurring(); break;
            case 'extend_recurring': $this->handleExtendRecurring(); break;
            case 'trim_recurring': $this->handleTrimRecurring(); break;
            case 'pause_recurring': $this->handlePauseRecurring(); break;
            case 'resume_recurring': $this->handleResumeRecurring(); break;
            case 'change_start_recurring': $this->handleChangeStartRecurring(); break;
            case 'change_pattern_recurring': $this->handleChangePatternRecurring(); break;
            default:
                echo json_encode(['success' => false, 'error' => 'Unknown admin action']);
        }
    }

    public function handle() {
        global $INPUT;
        
        $action = $INPUT->str('action');
        
        if ($action === 'clear_cache') {
            $this->clearCache();
        } elseif ($action === 'save_config') {
            $this->saveConfig();
        } elseif ($action === 'delete_recurring_series') {
            $this->deleteRecurringSeries();
        } elseif ($action === 'edit_recurring_series') {
            $this->editRecurringSeries();
        } elseif ($action === 'move_selected_events') {
            $this->moveEvents();
        } elseif ($action === 'move_single_event') {
            $this->moveSingleEvent();
        } elseif ($action === 'delete_selected_events') {
            $this->deleteSelectedEvents();
        } elseif ($action === 'create_namespace') {
            $this->createNamespace();
        } elseif ($action === 'delete_namespace') {
            $this->deleteNamespace();
        } elseif ($action === 'rename_namespace') {
            $this->renameNamespace();
        } elseif ($action === 'run_sync') {
            $this->runSync();
        } elseif ($action === 'stop_sync') {
            $this->stopSync();
        } elseif ($action === 'upload_update') {
            $this->uploadUpdate();
        } elseif ($action === 'delete_backup') {
            $this->deleteBackup();
        } elseif ($action === 'rename_backup') {
            $this->renameBackup();
        } elseif ($action === 'restore_backup') {
            $this->restoreBackup();
        } elseif ($action === 'create_manual_backup') {
            $this->createManualBackup();
        } elseif ($action === 'export_config') {
            $this->exportConfig();
        } elseif ($action === 'import_config') {
            $this->importConfig();
        } elseif ($action === 'get_log') {
            $this->getLog();
        } elseif ($action === 'cleanup_empty_namespaces') {
            $this->handleCleanupEmptyNamespaces();
        } elseif ($action === 'trim_all_past_recurring') {
            $this->handleTrimAllPastRecurring();
        } elseif ($action === 'rescan_recurring') {
            $this->handleRescanRecurring();
        } elseif ($action === 'extend_recurring') {
            $this->handleExtendRecurring();
        } elseif ($action === 'trim_recurring') {
            $this->handleTrimRecurring();
        } elseif ($action === 'pause_recurring') {
            $this->handlePauseRecurring();
        } elseif ($action === 'resume_recurring') {
            $this->handleResumeRecurring();
        } elseif ($action === 'change_start_recurring') {
            $this->handleChangeStartRecurring();
        } elseif ($action === 'change_pattern_recurring') {
            $this->handleChangePatternRecurring();
        } elseif ($action === 'clear_log') {
            $this->clearLogFile();
        } elseif ($action === 'download_log') {
            $this->downloadLog();
        } elseif ($action === 'rescan_events') {
            $this->rescanEvents();
        } elseif ($action === 'export_all_events') {
            $this->exportAllEvents();
        } elseif ($action === 'import_all_events') {
            $this->importAllEvents();
        } elseif ($action === 'preview_cleanup') {
            $this->previewCleanup();
        } elseif ($action === 'cleanup_events') {
            $this->cleanupEvents();
        } elseif ($action === 'save_important_namespaces') {
            $this->saveImportantNamespaces();
        }
    }

    public function html() {
        global $INPUT;
        
        // Get current tab - default to 'manage' (Manage Events tab)
        $tab = $INPUT->str('tab', 'manage');
        
        // Get template colors
        $colors = $this->getTemplateColors();
        $accentColor = '#00cc07'; // Keep calendar plugin accent color
        
        // Tab navigation (Manage Events, Update Plugin, Outlook Sync, Themes)
        echo '<div style="border-bottom:2px solid ' . $colors['border'] . '; margin:10px 0 15px 0;">';
        echo '<a href="?do=admin&page=calendar&tab=manage" style="display:inline-block; padding:8px 16px; text-decoration:none; color:' . ($tab === 'manage' ? $accentColor : $colors['text']) . '; border-bottom:3px solid ' . ($tab === 'manage' ? $accentColor : 'transparent') . '; font-weight:' . ($tab === 'manage' ? 'bold' : 'normal') . ';">📅 Manage Events</a>';
        echo '<a href="?do=admin&page=calendar&tab=update" style="display:inline-block; padding:8px 16px; text-decoration:none; color:' . ($tab === 'update' ? $accentColor : $colors['text']) . '; border-bottom:3px solid ' . ($tab === 'update' ? $accentColor : 'transparent') . '; font-weight:' . ($tab === 'update' ? 'bold' : 'normal') . ';">📦 Update Plugin</a>';
        echo '<a href="?do=admin&page=calendar&tab=config" style="display:inline-block; padding:8px 16px; text-decoration:none; color:' . ($tab === 'config' ? $accentColor : $colors['text']) . '; border-bottom:3px solid ' . ($tab === 'config' ? $accentColor : 'transparent') . '; font-weight:' . ($tab === 'config' ? 'bold' : 'normal') . ';">⚙️ Outlook Sync</a>';
        echo '<a href="?do=admin&page=calendar&tab=themes" style="display:inline-block; padding:8px 16px; text-decoration:none; color:' . ($tab === 'themes' ? $accentColor : $colors['text']) . '; border-bottom:3px solid ' . ($tab === 'themes' ? $accentColor : 'transparent') . '; font-weight:' . ($tab === 'themes' ? 'bold' : 'normal') . ';">🎨 Themes</a>';
        echo '</div>';
        
        // Render appropriate tab
        if ($tab === 'config') {
            $this->renderConfigTab($colors);
        } elseif ($tab === 'manage') {
            $this->renderManageTab($colors);
        } elseif ($tab === 'themes') {
            $this->renderThemesTab($colors);
        } else {
            $this->renderUpdateTab($colors);
        }
    }
    
    private function renderConfigTab($colors = null) {
        global $INPUT;
        
        // Use defaults if not provided
        if ($colors === null) {
            $colors = $this->getTemplateColors();
        }
        
        // Load current config
        $configFile = DOKU_PLUGIN . 'calendar/sync_config.php';
        $config = [];
        if (file_exists($configFile)) {
            $config = include $configFile;
        }
        
        // Show message if present
        if ($INPUT->has('msg')) {
            $msg = hsc($INPUT->str('msg'));
            $type = $INPUT->str('msgtype', 'success');
            $class = ($type === 'success') ? 'msg success' : 'msg error';
            echo "<div class=\"$class\" style=\"padding:10px; margin:10px 0; border-left:3px solid " . ($type === 'success' ? '#28a745' : '#dc3545') . "; background:" . ($type === 'success' ? '#d4edda' : '#f8d7da') . "; border-radius:3px;\">";
            echo $msg;
            echo "</div>";
        }
        
        echo '<h2 style="margin:10px 0; font-size:20px;">Outlook Sync Configuration</h2>';
        
        // Import/Export buttons
        echo '<div style="display:flex; gap:10px; margin-bottom:15px;">';
        echo '<button type="button" onclick="exportConfig()" style="background:#00cc07; color:white; padding:8px 16px; border:none; border-radius:3px; cursor:pointer; font-size:13px; font-weight:bold;">📤 Export Config</button>';
        echo '<button type="button" onclick="document.getElementById(\'importFileInput\').click()" style="background:#7b1fa2; color:white; padding:8px 16px; border:none; border-radius:3px; cursor:pointer; font-size:13px; font-weight:bold;">📥 Import Config</button>';
        echo '<input type="file" id="importFileInput" accept=".enc" style="display:none;" onchange="importConfig(this)">';
        echo '<span id="importStatus" style="margin-left:10px; font-size:12px;"></span>';
        echo '</div>';
        
        echo '<form method="post" action="?do=admin&page=calendar" style="max-width:900px;">';
        echo '<input type="hidden" name="action" value="save_config">';
        
        // Azure Credentials
        echo '<div style="background:' . $colors['bg'] . '; padding:12px; margin:10px 0; border-left:3px solid #00cc07; border-radius:3px;">';
        echo '<h3 style="margin:0 0 8px 0; color:#00cc07; font-size:16px;">Microsoft Azure App Credentials</h3>';
        echo '<p style="color:' . $colors['text'] . '; font-size:0.85em; margin:0 0 10px 0;">Register at <a href="https://portal.azure.com" target="_blank" style="color:#00cc07;">Azure Portal</a> → App registrations</p>';
        
        echo '<label style="display:block; font-weight:bold; margin:8px 0 3px; font-size:13px;">Tenant ID</label>';
        echo '<input type="text" name="tenant_id" value="' . hsc($config['tenant_id'] ?? '') . '" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" required style="width:100%; padding:6px; border:1px solid ' . $colors['border'] . '; border-radius:3px; font-size:13px;">';
        
        echo '<label style="display:block; font-weight:bold; margin:8px 0 3px; font-size:13px;">Client ID (Application ID)</label>';
        echo '<input type="text" name="client_id" value="' . hsc($config['client_id'] ?? '') . '" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" required style="width:100%; padding:6px; border:1px solid ' . $colors['border'] . '; border-radius:3px; font-size:13px;">';
        
        echo '<label style="display:block; font-weight:bold; margin:8px 0 3px; font-size:13px;">Client Secret</label>';
        echo '<input type="password" name="client_secret" value="' . hsc($config['client_secret'] ?? '') . '" placeholder="Enter client secret" required style="width:100%; padding:6px; border:1px solid ' . $colors['border'] . '; border-radius:3px; font-size:13px;">';
        echo '<p style="color:#999; font-size:0.8em; margin:3px 0 0;">⚠️ Keep this secret safe!</p>';
        echo '</div>';
        
        // Outlook Settings
        echo '<div style="background:' . $colors['bg'] . '; padding:12px; margin:10px 0; border-left:3px solid #00cc07; border-radius:3px;">';
        echo '<h3 style="margin:0 0 8px 0; color:#00cc07; font-size:16px;">Outlook Settings</h3>';
        
        echo '<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">';
        
        echo '<div>';
        echo '<label style="display:block; font-weight:bold; margin:0 0 3px; font-size:13px;">User Email</label>';
        echo '<input type="email" name="user_email" value="' . hsc($config['user_email'] ?? '') . '" placeholder="your.email@company.com" required style="width:100%; padding:6px; border:1px solid ' . $colors['border'] . '; border-radius:3px; font-size:13px;">';
        echo '</div>';
        
        echo '<div>';
        echo '<label style="display:block; font-weight:bold; margin:0 0 3px; font-size:13px;">Timezone</label>';
        echo '<input type="text" name="timezone" value="' . hsc($config['timezone'] ?? 'America/Los_Angeles') . '" placeholder="America/Los_Angeles" style="width:100%; padding:6px; border:1px solid ' . $colors['border'] . '; border-radius:3px; font-size:13px;">';
        echo '</div>';
        
        echo '<div>';
        echo '<label style="display:block; font-weight:bold; margin:0 0 3px; font-size:13px;">Default Category</label>';
        echo '<input type="text" name="default_category" value="' . hsc($config['default_category'] ?? 'Blue category') . '" placeholder="Blue category" style="width:100%; padding:6px; border:1px solid ' . $colors['border'] . '; border-radius:3px; font-size:13px;">';
        echo '</div>';
        
        echo '<div>';
        echo '<label style="display:block; font-weight:bold; margin:0 0 3px; font-size:13px;">Reminder (minutes)</label>';
        echo '<input type="number" name="reminder_minutes" value="' . hsc($config['reminder_minutes'] ?? 15) . '" placeholder="15" style="width:100%; padding:6px; border:1px solid ' . $colors['border'] . '; border-radius:3px; font-size:13px;">';
        echo '</div>';
        
        echo '</div>'; // end grid
        echo '</div>';
        
        // Sync Options
        echo '<div style="background:' . $colors['bg'] . '; padding:12px; margin:10px 0; border-left:3px solid #00cc07; border-radius:3px;">';
        echo '<h3 style="margin:0 0 8px 0; color:#00cc07; font-size:16px;">Sync Options</h3>';
        
        $syncCompleted = isset($config['sync_completed_tasks']) ? $config['sync_completed_tasks'] : false;
        echo '<label style="display:inline-block; margin:5px 15px 5px 0; font-size:13px;"><input type="checkbox" name="sync_completed_tasks" value="1" ' . ($syncCompleted ? 'checked' : '') . '> Sync completed tasks</label>';
        
        $deleteOutlook = isset($config['delete_outlook_events']) ? $config['delete_outlook_events'] : true;
        echo '<label style="display:inline-block; margin:5px 15px 5px 0; font-size:13px;"><input type="checkbox" name="delete_outlook_events" value="1" ' . ($deleteOutlook ? 'checked' : '') . '> Delete from Outlook when removed</label>';
        
        $syncAll = isset($config['sync_all_namespaces']) ? $config['sync_all_namespaces'] : true;
        echo '<label style="display:inline-block; margin:5px 0; font-size:13px;"><input type="checkbox" name="sync_all_namespaces" value="1" onclick="toggleNamespaceSelection(this)" ' . ($syncAll ? 'checked' : '') . '> Sync all namespaces</label>';
        
        // Namespace selection (shown when sync_all is unchecked)
        echo '<div id="namespace_selection" style="margin-top:10px; ' . ($syncAll ? 'display:none;' : '') . '">';
        echo '<label style="display:block; font-weight:bold; margin-bottom:5px; font-size:13px;">Select namespaces to sync:</label>';
        
        // Get available namespaces
        $availableNamespaces = $this->getAllNamespaces();
        $selectedNamespaces = isset($config['sync_namespaces']) ? $config['sync_namespaces'] : [];
        
        echo '<div style="max-height:150px; overflow-y:auto; border:1px solid ' . $colors['border'] . '; border-radius:3px; padding:8px; background:' . $colors['bg'] . ';">';
        echo '<label style="display:block; margin:3px 0;"><input type="checkbox" name="sync_namespaces[]" value=""> (default)</label>';
        foreach ($availableNamespaces as $ns) {
            if ($ns !== '') {
                $checked = in_array($ns, $selectedNamespaces) ? 'checked' : '';
                echo '<label style="display:block; margin:3px 0;"><input type="checkbox" name="sync_namespaces[]" value="' . hsc($ns) . '" ' . $checked . '> ' . hsc($ns) . '</label>';
            }
        }
        echo '</div>';
        echo '</div>';
        
        echo '<script>
        function toggleNamespaceSelection(checkbox) {
            document.getElementById("namespace_selection").style.display = checkbox.checked ? "none" : "block";
        }
        </script>';
        
        echo '</div>';
        
        // Namespace and Color Mapping - Side by Side
        echo '<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin:10px 0;">';
        
        // Namespace Mapping
        echo '<div style="background:' . $colors['bg'] . '; padding:12px; border-left:3px solid #00cc07; border-radius:3px;">';
        echo '<h3 style="margin:0 0 8px 0; color:#00cc07; font-size:16px;">Namespace → Category</h3>';
        echo '<p style="color:' . $colors['text'] . '; font-size:0.8em; margin:0 0 5px;">One per line: namespace=Category</p>';
        echo '<textarea name="category_mapping" rows="6" style="width:100%; padding:6px; border:1px solid ' . $colors['border'] . '; border-radius:3px; font-family:monospace; font-size:12px; resize:vertical;" placeholder="work=Blue category&#10;personal=Green category">';
        if (isset($config['category_mapping']) && is_array($config['category_mapping'])) {
            foreach ($config['category_mapping'] as $ns => $cat) {
                echo hsc($ns) . '=' . hsc($cat) . "\n";
            }
        }
        echo '</textarea>';
        echo '</div>';
        
        // Color Mapping with Color Picker
        echo '<div style="background:' . $colors['bg'] . '; padding:12px; border-left:3px solid #00cc07; border-radius:3px;">';
        echo '<h3 style="margin:0 0 8px 0; color:#00cc07; font-size:16px;">🎨 Event Color → Category</h3>';
        echo '<p style="color:' . $colors['text'] . '; font-size:0.8em; margin:0 0 8px;">Map calendar colors to Outlook categories</p>';
        
        // Define calendar colors and Outlook categories (only the main 6 colors)
        $calendarColors = [
            '#3498db' => 'Blue',
            '#2ecc71' => 'Green',
            '#e74c3c' => 'Red',
            '#f39c12' => 'Orange',
            '#9b59b6' => 'Purple',
            '#1abc9c' => 'Teal'
        ];
        
        $outlookCategories = [
            'Blue category',
            'Green category',
            'Orange category',
            'Red category',
            'Yellow category',
            'Purple category'
        ];
        
        // Load existing color mappings
        $existingMappings = isset($config['color_mapping']) && is_array($config['color_mapping']) 
            ? $config['color_mapping'] 
            : [];
        
        // Display color mapping rows
        echo '<div id="colorMappings" style="max-height:200px; overflow-y:auto;">';
        
        $rowIndex = 0;
        foreach ($calendarColors as $hexColor => $colorName) {
            $selectedCategory = isset($existingMappings[$hexColor]) ? $existingMappings[$hexColor] : '';
            
            echo '<div style="display:flex; gap:8px; align-items:center; margin-bottom:6px;">';
            
            // Color preview box
            echo '<div style="width:24px; height:24px; background:' . $hexColor . '; border:2px solid #ddd; border-radius:3px; flex-shrink:0;"></div>';
            
            // Color name
            echo '<span style="font-size:12px; min-width:90px; color:' . $colors['text'] . ';">' . $colorName . '</span>';
            
            // Arrow
            echo '<span style="color:#999; font-size:12px;">→</span>';
            
            // Outlook category dropdown
            echo '<select name="color_map_' . $rowIndex . '" style="flex:1; padding:4px; border:1px solid ' . $colors['border'] . '; border-radius:3px; font-size:12px;">';
            echo '<option value="">-- None --</option>';
            foreach ($outlookCategories as $category) {
                $selected = ($selectedCategory === $category) ? 'selected' : '';
                echo '<option value="' . hsc($category) . '" ' . $selected . '>' . hsc($category) . '</option>';
            }
            echo '</select>';
            
            // Hidden input for the hex color
            echo '<input type="hidden" name="color_hex_' . $rowIndex . '" value="' . $hexColor . '">';
            
            echo '</div>';
            $rowIndex++;
        }
        
        echo '</div>';
        
        // Hidden input to track number of color mappings
        echo '<input type="hidden" name="color_mapping_count" value="' . $rowIndex . '">';
        
        echo '</div>';
        
        echo '</div>'; // end grid
        
        // Submit button
        echo '<button type="submit" style="background:#00cc07; color:white; padding:10px 20px; border:none; border-radius:3px; cursor:pointer; font-size:14px; font-weight:bold; margin:10px 0;">💾 Save Configuration</button>';
        echo '</form>';
        
        // JavaScript for Import/Export
        echo '<script>
        async function exportConfig() {
            try {
                const response = await fetch("?do=admin&page=calendar&action=export_config&call=ajax", {
                    method: "POST"
                });
                const data = await response.json();
                
                if (data.success) {
                    // Create download link
                    const blob = new Blob([data.encrypted], {type: "application/octet-stream"});
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement("a");
                    a.href = url;
                    a.download = "sync_config_" + new Date().toISOString().split("T")[0] + ".enc";
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                    
                    alert("✅ Config exported successfully!\\n\\n⚠️ This file contains encrypted credentials.\\nKeep it secure!");
                } else {
                    alert("❌ Export failed: " + data.message);
                }
            } catch (error) {
                alert("❌ Error: " + error.message);
            }
        }
        
        async function importConfig(input) {
            const file = input.files[0];
            if (!file) return;
            
            const status = document.getElementById("importStatus");
            status.textContent = "⏳ Importing...";
            status.style.color = "#00cc07";
            
            try {
                const encrypted = await file.text();
                
                const formData = new FormData();
                formData.append("encrypted_config", encrypted);
                
                const response = await fetch("?do=admin&page=calendar&action=import_config&call=ajax", {
                    method: "POST",
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    status.textContent = "✅ Import successful! Reloading...";
                    status.style.color = "#28a745";
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    status.textContent = "❌ Import failed: " + data.message;
                    status.style.color = "#dc3545";
                }
            } catch (error) {
                status.textContent = "❌ Error: " + error.message;
                status.style.color = "#dc3545";
            }
            
            // Reset file input
            input.value = "";
        }
        </script>';
        
        // Sync Controls Section
        echo '<div style="background:' . $colors['bg'] . '; padding:12px; margin:15px 0; border-left:3px solid #00cc07; border-radius:3px; max-width:900px;">';
        echo '<h3 style="margin:0 0 8px 0; color:#00cc07; font-size:16px;">🔄 Sync Controls</h3>';
        
        // Check cron job status
        $cronStatus = $this->getCronStatus();
        
        // Check log file permissions
        $logFile = DOKU_PLUGIN . 'calendar/sync.log';
        $logWritable = is_writable($logFile) || is_writable(dirname($logFile));
        
        echo '<div style="display:flex; gap:10px; align-items:center; margin-bottom:10px;">';
        echo '<button onclick="runSyncNow()" id="syncBtn" style="background:#00cc07; color:white; padding:8px 16px; border:none; border-radius:3px; cursor:pointer; font-size:13px; font-weight:bold;">▶️ Run Sync Now</button>';
        echo '<button onclick="stopSyncNow()" id="stopBtn" style="background:#e74c3c; color:white; padding:8px 16px; border:none; border-radius:3px; cursor:pointer; font-size:13px; font-weight:bold; display:none;">⏹️ Stop Sync</button>';
        
        if ($cronStatus['active']) {
            echo '<span style="color:' . $colors['text'] . '; font-size:12px;">⏰ ' . hsc($cronStatus['frequency']) . '</span>';
        } else {
            echo '<span style="color:#999; font-size:12px;">⚠️ No cron job detected</span>';
        }
        
        echo '<span id="syncStatus" style="color:' . $colors['text'] . '; font-size:12px; margin-left:auto;"></span>';
        echo '</div>';
        
        // Show permission warning if log not writable
        if (!$logWritable) {
            echo '<div style="background:#fff3e0; border-left:3px solid #ff9800; padding:8px; margin:8px 0; border-radius:3px;">';
            echo '<span style="color:#e65100; font-size:11px;">⚠️ Log file not writable. Run: <code style="background:#f0f0f0; padding:2px 4px; border-radius:2px;">chmod 666 ' . $logFile . '</code></span>';
            echo '</div>';
        }
        
        // Show debug info if cron detected
        if ($cronStatus['active'] && !empty($cronStatus['full_line'])) {
            echo '<details style="margin-top:5px;">';
            echo '<summary style="cursor:pointer; color:#999; font-size:11px;">Show cron details</summary>';
            echo '<pre style="background:#f0f0f0; padding:8px; border-radius:3px; font-size:10px; margin:5px 0; overflow-x:auto;">' . hsc($cronStatus['full_line']) . '</pre>';
            echo '</details>';
        }
        
        if (!$cronStatus['active']) {
            echo '<p style="color:#999; font-size:11px; margin:5px 0;">To enable automatic syncing, add to crontab: <code style="background:#f0f0f0; padding:2px 4px; border-radius:2px;">*/30 * * * * cd ' . DOKU_PLUGIN . 'calendar && php sync_outlook.php</code></p>';
        }
        
        echo '</div>';
        
        // JavaScript for Run Sync Now
        echo '<script>
        let syncAbortController = null;
        
        function runSyncNow() {
            const btn = document.getElementById("syncBtn");
            const stopBtn = document.getElementById("stopBtn");
            const status = document.getElementById("syncStatus");
            
            btn.disabled = true;
            btn.style.display = "none";
            stopBtn.style.display = "inline-block";
            btn.textContent = "⏳ Running...";
            btn.style.background = "#999";
            status.textContent = "Starting sync...";
            status.style.color = "#00cc07";
            
            // Create abort controller for this sync
            syncAbortController = new AbortController();
            
            fetch("?do=admin&page=calendar&action=run_sync&call=ajax", {
                method: "POST",
                signal: syncAbortController.signal
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        status.textContent = "✅ " + data.message;
                        status.style.color = "#28a745";
                    } else {
                        status.textContent = "❌ " + data.message;
                        status.style.color = "#dc3545";
                    }
                    btn.disabled = false;
                    btn.style.display = "inline-block";
                    stopBtn.style.display = "none";
                    btn.textContent = "▶️ Run Sync Now";
                    btn.style.background = "#00cc07";
                    syncAbortController = null;
                    
                    // Clear status after 10 seconds
                    setTimeout(() => {
                        status.textContent = "";
                    }, 10000);
                })
                .catch(error => {
                    if (error.name === "AbortError") {
                        status.textContent = "⏹️ Sync stopped by user";
                        status.style.color = "#ff9800";
                    } else {
                        status.textContent = "❌ Error: " + error.message;
                        status.style.color = "#dc3545";
                    }
                    btn.disabled = false;
                    btn.style.display = "inline-block";
                    stopBtn.style.display = "none";
                    btn.textContent = "▶️ Run Sync Now";
                    btn.style.background = "#00cc07";
                    syncAbortController = null;
                });
        }
        
        function stopSyncNow() {
            const status = document.getElementById("syncStatus");
            
            status.textContent = "⏹️ Sending stop signal...";
            status.style.color = "#ff9800";
            
            // First, send stop signal to server
            fetch("?do=admin&page=calendar&action=stop_sync&call=ajax", {
                method: "POST"
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    status.textContent = "⏹️ Stop signal sent - sync will abort soon";
                    status.style.color = "#ff9800";
                } else {
                    status.textContent = "⚠️ " + data.message;
                    status.style.color = "#ff9800";
                }
            })
            .catch(error => {
                status.textContent = "⚠️ Error sending stop signal: " + error.message;
                status.style.color = "#ff9800";
            });
            
            // Also abort the fetch request
            if (syncAbortController) {
                syncAbortController.abort();
                status.textContent = "⏹️ Stopping sync...";
                status.style.color = "#ff9800";
            }
        }
        </script>';
        
        // Log Viewer Section - More Compact
        echo '<div style="background:' . $colors['bg'] . '; padding:12px; margin:15px 0 10px 0; border-left:3px solid #00cc07; border-radius:3px; max-width:900px;">';
        echo '<h3 style="margin:0 0 5px 0; color:#00cc07; font-size:16px;">📜 Live Sync Log</h3>';
        echo '<p style="color:' . $colors['text'] . '; font-size:0.8em; margin:0 0 8px;">Updates every 2 seconds</p>';
        
        // Log viewer container
        echo '<div style="background:#1e1e1e; border-radius:5px; overflow:hidden; box-shadow:0 2px 4px rgba(0,0,0,0.3);">';
        
        // Log header - More compact
        echo '<div style="background:#2d2d2d; padding:6px 10px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #444;">';
        echo '<span style="color:#00cc07; font-family:monospace; font-weight:bold; font-size:12px;">sync.log</span>';
        echo '<div>';
        echo '<button id="pauseBtn" onclick="togglePause()" style="background:#666; color:white; border:none; padding:4px 8px; border-radius:3px; cursor:pointer; margin-right:4px; font-size:11px;">⏸ Pause</button>';
        echo '<button onclick="clearLog()" style="background:#e74c3c; color:white; border:none; padding:4px 8px; border-radius:3px; cursor:pointer; margin-right:4px; font-size:11px;">🗑️ Clear</button>';
        echo '<button onclick="downloadLog()" style="background:#666; color:white; border:none; padding:4px 8px; border-radius:3px; cursor:pointer; font-size:11px;">💾 Download</button>';
        echo '</div>';
        echo '</div>';
        
        // Log content - Reduced height to 250px
        echo '<pre id="logContent" style="background:#1e1e1e; color:#00cc07; font-family:monospace; font-size:11px; padding:10px; margin:0; overflow-x:auto; white-space:pre-wrap; word-wrap:break-word; line-height:1.4; max-height:250px; overflow-y:auto;">Loading log...</pre>';
        
        echo '</div>';
        echo '</div>';
        
        // JavaScript for log viewer
        echo '<script>
        let refreshInterval = null;
        let isPaused = false;
        
        function refreshLog() {
            if (isPaused) return;
            
            fetch("?do=admin&page=calendar&action=get_log&call=ajax")
                .then(response => response.json())
                .then(data => {
                    const logContent = document.getElementById("logContent");
                    if (logContent) {
                        logContent.textContent = data.log || "No log data available";
                        logContent.scrollTop = logContent.scrollHeight;
                    }
                })
                .catch(error => {
                    console.error("Error fetching log:", error);
                });
        }
        
        function togglePause() {
            isPaused = !isPaused;
            const btn = document.getElementById("pauseBtn");
            if (isPaused) {
                btn.textContent = "▶ Resume";
                btn.style.background = "#00cc07";
            } else {
                btn.textContent = "⏸ Pause";
                btn.style.background = "#666";
                refreshLog();
            }
        }
        
        function clearLog() {
            if (!confirm("Clear the sync log file?\\n\\nThis will delete all log entries.")) {
                return;
            }
            
            fetch("?do=admin&page=calendar&action=clear_log&call=ajax", {
                method: "POST"
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        refreshLog();
                        alert("Log cleared successfully");
                    } else {
                        alert("Error clearing log: " + data.message);
                    }
                })
                .catch(error => {
                    alert("Error: " + error.message);
                });
        }
        
        function downloadLog() {
            window.location.href = "?do=admin&page=calendar&action=download_log";
        }
        
        // Start auto-refresh
        refreshLog();
        refreshInterval = setInterval(refreshLog, 2000);
        
        // Cleanup on page unload
        window.addEventListener("beforeunload", function() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        });
        </script>';
    }
    
    private function renderManageTab($colors = null) {
        global $INPUT;
        
        // Use defaults if not provided
        if ($colors === null) {
            $colors = $this->getTemplateColors();
        }
        
        // Show message if present
        if ($INPUT->has('msg')) {
            $msg = hsc($INPUT->str('msg'));
            $type = $INPUT->str('msgtype', 'success');
            echo "<div style=\"padding:10px; margin:10px 0; border-left:3px solid " . ($type === 'success' ? '#28a745' : '#dc3545') . "; background:" . ($type === 'success' ? '#d4edda' : '#f8d7da') . "; border-radius:3px;\">";
            echo $msg;
            echo "</div>";
        }
        
        echo '<h2 style="margin:10px 0; font-size:20px;">Manage Calendar Events</h2>';
        
        // Events Manager Section
        echo '<div style="background:' . $colors['bg'] . '; padding:12px; margin:10px 0; border-left:3px solid #00cc07; border-radius:3px; max-width:1200px;">';
        echo '<h3 style="margin:0 0 8px 0; color:#00cc07; font-size:16px;">📊 Events Manager</h3>';
        echo '<p style="color:' . $colors['text'] . '; font-size:11px; margin:0 0 10px;">Scan, export, and import all calendar events across all namespaces.</p>';
        
        // Get event statistics
        $stats = $this->getEventStatistics();
        
        // Statistics display
        echo '<div style="background:' . $colors['bg'] . '; padding:10px; border-radius:3px; margin-bottom:10px; border:1px solid ' . $colors['border'] . ';">';
        echo '<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:10px; font-size:12px;">';
        
        echo '<div style="background:#f3e5f5; padding:8px; border-radius:3px; text-align:center;">';
        echo '<div style="font-size:24px; font-weight:bold; color:#7b1fa2;">' . $stats['total_events'] . '</div>';
        echo '<div style="color:' . $colors['text'] . '; font-size:10px;">Total Events</div>';
        echo '</div>';
        
        echo '<div style="background:#f3e5f5; padding:8px; border-radius:3px; text-align:center;">';
        echo '<div style="font-size:24px; font-weight:bold; color:#7b1fa2;">' . $stats['total_namespaces'] . '</div>';
        echo '<div style="color:' . $colors['text'] . '; font-size:10px;">Namespaces</div>';
        echo '</div>';
        
        echo '<div style="background:#e8f5e9; padding:8px; border-radius:3px; text-align:center;">';
        echo '<div style="font-size:24px; font-weight:bold; color:#388e3c;">' . $stats['total_files'] . '</div>';
        echo '<div style="color:' . $colors['text'] . '; font-size:10px;">JSON Files</div>';
        echo '</div>';
        
        echo '<div style="background:#fff3e0; padding:8px; border-radius:3px; text-align:center;">';
        echo '<div style="font-size:24px; font-weight:bold; color:#f57c00;">' . $stats['total_recurring'] . '</div>';
        echo '<div style="color:' . $colors['text'] . '; font-size:10px;">Recurring</div>';
        echo '</div>';
        
        echo '</div>';
        
        // Last scan time
        if (!empty($stats['last_scan'])) {
            echo '<div style="margin-top:8px; color:' . $colors['text'] . '; font-size:10px;">Last scanned: ' . hsc($stats['last_scan']) . '</div>';
        }
        
        echo '</div>';
        
        // Action buttons
        echo '<div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:10px;">';
        
        // Rescan button
        echo '<form method="post" action="?do=admin&page=calendar&tab=manage" style="display:inline;">';
        echo '<input type="hidden" name="action" value="rescan_events">';
        echo '<button type="submit" style="background:#00cc07; color:white; border:none; padding:8px 16px; border-radius:3px; cursor:pointer; font-size:12px; display:flex; align-items:center; gap:6px;">';
        echo '<span>🔄</span><span>Re-scan Events</span>';
        echo '</button>';
        echo '</form>';
        
        // Export button
        echo '<form method="post" action="?do=admin&page=calendar&tab=manage" style="display:inline;">';
        echo '<input type="hidden" name="action" value="export_all_events">';
        echo '<button type="submit" style="background:#7b1fa2; color:white; border:none; padding:8px 16px; border-radius:3px; cursor:pointer; font-size:12px; display:flex; align-items:center; gap:6px;">';
        echo '<span>💾</span><span>Export All Events</span>';
        echo '</button>';
        echo '</form>';
        
        // Import button (with file upload)
        echo '<form method="post" action="?do=admin&page=calendar&tab=manage" enctype="multipart/form-data" style="display:inline;" onsubmit="return confirm(\'Import will merge with existing events. Continue?\')">';
        echo '<input type="hidden" name="action" value="import_all_events">';
        echo '<label style="background:#7b1fa2; color:white; border:none; padding:8px 16px; border-radius:3px; cursor:pointer; font-size:12px; display:inline-flex; align-items:center; gap:6px;">';
        echo '<span>📁</span><span>Import Events</span>';
        echo '<input type="file" name="import_file" accept=".json,.zip" required style="display:none;" onchange="this.form.submit()">';
        echo '</label>';
        echo '</form>';
        
        echo '</div>';
        
        // Breakdown by namespace
        if (!empty($stats['by_namespace'])) {
            echo '<details style="margin-top:12px;">';
            echo '<summary style="cursor:pointer; padding:6px; background:#e9e9e9; border-radius:3px; font-size:11px; font-weight:bold;">View Breakdown by Namespace</summary>';
            echo '<div style="margin-top:8px; max-height:200px; overflow-y:auto; border:1px solid ' . $colors['border'] . '; border-radius:3px;">';
            echo '<table style="width:100%; border-collapse:collapse; font-size:11px;">';
            echo '<thead style="position:sticky; top:0; background:#f5f5f5;">';
            echo '<tr>';
            echo '<th style="padding:4px 6px; text-align:left; border-bottom:2px solid #ddd;">Namespace</th>';
            echo '<th style="padding:4px 6px; text-align:right; border-bottom:2px solid #ddd;">Events</th>';
            echo '<th style="padding:4px 6px; text-align:right; border-bottom:2px solid #ddd;">Files</th>';
            echo '</tr></thead><tbody>';
            
            foreach ($stats['by_namespace'] as $ns => $nsStats) {
                echo '<tr style="border-bottom:1px solid #eee;">';
                echo '<td style="padding:4px 6px;"><code style="background:#f0f0f0; padding:1px 3px; border-radius:2px; font-size:10px;">' . hsc($ns ?: '(default)') . '</code></td>';
                echo '<td style="padding:4px 6px; text-align:right;"><strong>' . $nsStats['events'] . '</strong></td>';
                echo '<td style="padding:4px 6px; text-align:right;">' . $nsStats['files'] . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
            echo '</div>';
            echo '</details>';
        }
        
        echo '</div>';
        
        // Important Namespaces Section
        $configFile = DOKU_PLUGIN . 'calendar/sync_config.php';
        $importantConfig = [];
        if (file_exists($configFile)) {
            $importantConfig = include $configFile;
        }
        $importantNsValue = isset($importantConfig['important_namespaces']) ? $importantConfig['important_namespaces'] : 'important';
        
        echo '<div style="background:' . $colors['bg'] . '; padding:12px; margin:10px 0; border-left:3px solid #00cc07; border-radius:3px; max-width:1200px;">';
        echo '<h3 style="margin:0 0 8px 0; color:#00cc07; font-size:16px;">📌 Important Namespaces (Sidebar Widget)</h3>';
        echo '<p style="color:' . $colors['text'] . '; font-size:11px; margin:0 0 8px;">Events from these namespaces will be highlighted in purple in the sidebar widget\'s "Important Events" section.</p>';
        echo '<form method="post" action="?do=admin&page=calendar&tab=manage" style="display:flex; gap:8px; align-items:center;">';
        echo '<input type="hidden" name="action" value="save_important_namespaces">';
        echo '<input type="text" name="important_namespaces" value="' . hsc($importantNsValue) . '" style="flex:1; padding:6px; border:1px solid ' . $colors['border'] . '; border-radius:3px; font-size:12px;" placeholder="important,urgent,priority">';
        echo '<button type="submit" style="background:#00cc07; color:white; padding:6px 16px; border:none; border-radius:3px; cursor:pointer; font-size:12px; font-weight:bold; white-space:nowrap;">Save</button>';
        echo '</form>';
        echo '<p style="color:' . $colors['text'] . '; font-size:10px; margin:4px 0 0;">Comma-separated list of namespace names</p>';
        echo '</div>';
        
        // Cleanup Events Section
        echo '<div style="background:' . $colors['bg'] . '; padding:12px; margin:10px 0; border-left:3px solid #00cc07; border-radius:3px; max-width:1200px;">';
        echo '<h3 style="margin:0 0 6px 0; color:#00cc07; font-size:16px;">🧹 Cleanup Old Events</h3>';
        echo '<p style="color:' . $colors['text'] . '; font-size:11px; margin:0 0 12px;">Delete events based on criteria below. Automatic backup created before deletion.</p>';
        
        echo '<form method="post" action="?do=admin&page=calendar&tab=manage" id="cleanupForm">';
        echo '<input type="hidden" name="action" value="cleanup_events">';
        
        // Compact options layout
        echo '<div style="background:' . $colors['bg'] . '; padding:10px; border:1px solid ' . $colors['border'] . '; border-radius:3px; margin-bottom:10px;">';
        
        // Radio buttons in a row
        echo '<div style="display:flex; gap:20px; margin-bottom:10px; padding-bottom:8px; border-bottom:1px solid #f0f0f0;">';
        echo '<label style="cursor:pointer; font-size:12px; font-weight:600; display:flex; align-items:center; gap:4px;">';
        echo '<input type="radio" name="cleanup_type" value="age" checked onchange="updateCleanupOptions()">';
        echo '<span>By Age</span>';
        echo '</label>';
        echo '<label style="cursor:pointer; font-size:12px; font-weight:600; display:flex; align-items:center; gap:4px;">';
        echo '<input type="radio" name="cleanup_type" value="status" onchange="updateCleanupOptions()">';
        echo '<span>By Status</span>';
        echo '</label>';
        echo '<label style="cursor:pointer; font-size:12px; font-weight:600; display:flex; align-items:center; gap:4px;">';
        echo '<input type="radio" name="cleanup_type" value="range" onchange="updateCleanupOptions()">';
        echo '<span>By Date Range</span>';
        echo '</label>';
        echo '</div>';
        
        // Age options
        echo '<div id="age-options" style="padding:6px 0;">';
        echo '<span style="font-size:11px; color:' . $colors['text'] . '; margin-right:8px;">Delete events older than:</span>';
        echo '<select name="age_value" style="width:50px; padding:3px 4px; font-size:11px; border:1px solid #d0d0d0; border-radius:3px; margin-right:4px;">';
        for ($i = 1; $i <= 24; $i++) {
            $sel = $i === 6 ? ' selected' : '';
            echo '<option value="' . $i . '"' . $sel . '>' . $i . '</option>';
        }
        echo '</select>';
        echo '<select name="age_unit" style="width:80px; padding:3px 4px; font-size:11px; border:1px solid #d0d0d0; border-radius:3px;">';
        echo '<option value="months" selected>months</option>';
        echo '<option value="years">years</option>';
        echo '</select>';
        echo '</div>';
        
        // Status options
        echo '<div id="status-options" style="padding:6px 0; opacity:0.4;">';
        echo '<span style="font-size:11px; color:' . $colors['text'] . '; margin-right:8px;">Delete:</span>';
        echo '<label style="display:inline-block; font-size:11px; margin-right:12px; cursor:pointer;"><input type="checkbox" name="delete_completed" value="1" style="margin-right:3px;"> Completed tasks</label>';
        echo '<label style="display:inline-block; font-size:11px; cursor:pointer;"><input type="checkbox" name="delete_past" value="1" style="margin-right:3px;"> Past events</label>';
        echo '</div>';
        
        // Range options
        echo '<div id="range-options" style="padding:6px 0; opacity:0.4;">';
        echo '<span style="font-size:11px; color:' . $colors['text'] . '; margin-right:8px;">From:</span>';
        echo '<input type="date" name="range_start" style="padding:3px 6px; font-size:11px; border:1px solid #d0d0d0; border-radius:3px; margin-right:10px;">';
        echo '<span style="font-size:11px; color:' . $colors['text'] . '; margin-right:8px;">To:</span>';
        echo '<input type="date" name="range_end" style="padding:3px 6px; font-size:11px; border:1px solid #d0d0d0; border-radius:3px;">';
        echo '</div>';
        
        echo '</div>';
        
        // Namespace filter - compact
        echo '<div style="background:' . $colors['bg'] . '; padding:8px 10px; border:1px solid ' . $colors['border'] . '; border-radius:3px; margin-bottom:10px; display:flex; align-items:center; gap:8px;">';
        echo '<label style="font-size:11px; font-weight:600; white-space:nowrap; color:#555;">Namespace:</label>';
        echo '<input type="text" name="namespace_filter" placeholder="Leave empty for all, or specify: work, personal, etc." style="flex:1; padding:4px 8px; font-size:11px; border:1px solid #d0d0d0; border-radius:3px;">';
        echo '</div>';
        
        // Action buttons - compact row
        echo '<div style="display:flex; gap:8px; align-items:center;">';
        echo '<button type="button" onclick="previewCleanup()" style="background:#7b1fa2; color:white; border:none; padding:6px 14px; border-radius:3px; cursor:pointer; font-size:11px; font-weight:600;">👁️ Preview</button>';
        echo '<button type="submit" onclick="return confirmCleanup()" style="background:#dc3545; color:white; border:none; padding:6px 14px; border-radius:3px; cursor:pointer; font-size:11px; font-weight:600;">🗑️ Delete</button>';
        echo '<span style="font-size:10px; color:#999;">⚠️ Backup created automatically</span>';
        echo '</div>';
        
        echo '</form>';
        
        // Preview results area
        echo '<div id="cleanup-preview" style="margin-top:10px; display:none;"></div>';
        
        echo '<script>
        function updateCleanupOptions() {
            const type = document.querySelector(\'input[name="cleanup_type"]:checked\').value;
            
            // Show selected, gray out others
            document.getElementById(\'age-options\').style.opacity = type === \'age\' ? \'1\' : \'0.4\';
            document.getElementById(\'status-options\').style.opacity = type === \'status\' ? \'1\' : \'0.4\';
            document.getElementById(\'range-options\').style.opacity = type === \'range\' ? \'1\' : \'0.4\';
            
            // Enable/disable inputs
            document.querySelectorAll(\'#age-options select\').forEach(el => el.disabled = type !== \'age\');
            document.querySelectorAll(\'#status-options input\').forEach(el => el.disabled = type !== \'status\');
            document.querySelectorAll(\'#range-options input\').forEach(el => el.disabled = type !== \'range\');
        }
        
        function previewCleanup() {
            const form = document.getElementById(\'cleanupForm\');
            const formData = new FormData(form);
            formData.set(\'action\', \'preview_cleanup\');
            
            const preview = document.getElementById(\'cleanup-preview\');
            preview.innerHTML = \'<div style="text-align:center; padding:20px; color:' . $colors['text'] . ';">Loading preview...</div>\';
            preview.style.display = \'block\';
            
            fetch(\'?do=admin&page=calendar&tab=manage\', {
                method: \'POST\',
                body: new URLSearchParams(formData)
            })
            .then(r => r.json())
            .then(data => {
                if (data.count === 0) {
                    let html = \'<div style="background:#d4edda; border:1px solid #c3e6cb; padding:10px; border-radius:3px; font-size:12px; color:#155724;">✅ No events match the criteria. Nothing would be deleted.</div>\';
                    
                    // Show debug info if available
                    if (data.debug) {
                        html += \'<details style="margin-top:8px; font-size:11px; color:' . $colors['text'] . ';">\';
                        html += \'<summary style="cursor:pointer;">Debug Info</summary>\';
                        html += \'<pre style="background:#f5f5f5; padding:6px; margin-top:4px; border-radius:3px; overflow-x:auto;">\' + JSON.stringify(data.debug, null, 2) + \'</pre>\';
                        html += \'</details>\';
                    }
                    
                    preview.innerHTML = html;
                } else {
                    let html = \'<div style="background:#f8d7da; border:1px solid #f5c6cb; padding:10px; border-radius:3px; font-size:12px; color:#721c24;">\';
                    html += \'<strong>⚠️ Warning:</strong> The following \' + data.count + \' event(s) would be deleted:<br><br>\';
                    html += \'<div style="max-height:150px; overflow-y:auto; margin-top:6px; background:' . $colors['bg'] . '; padding:6px; border-radius:3px;">\';
                    data.events.forEach(evt => {
                        html += \'<div style="padding:3px; border-bottom:1px solid #eee; font-size:11px;">\';
                        html += \'• \' + evt.title + \' (\' + evt.date + \')\';
                        if (evt.namespace) html += \' <span style="background:#e3f2fd; padding:1px 4px; border-radius:2px; font-size:9px;">\' + evt.namespace + \'</span>\';
                        html += \'</div>\';
                    });
                    html += \'</div></div>\';
                    preview.innerHTML = html;
                }
            })
            .catch(err => {
                preview.innerHTML = \'<div style="background:#f8d7da; border:1px solid #f5c6cb; padding:10px; border-radius:3px; font-size:12px; color:#721c24;">Error loading preview</div>\';
            });
        }
        
        function confirmCleanup() {
            return confirm(\'Are you sure you want to delete these events? A backup will be created first, but this action cannot be easily undone.\');
        }
        
        updateCleanupOptions();
        </script>';
        
        echo '</div>';
        
        // Recurring Events Section
        echo '<div id="recurring-section" style="background:' . $colors['bg'] . '; padding:12px; margin:10px 0; border-left:3px solid #00cc07; border-radius:3px; max-width:1200px;">';
        echo '<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">';
        echo '<h3 style="margin:0; color:#00cc07; font-size:16px;">🔄 Recurring Events</h3>';
        echo '<div style="display:flex; gap:6px;">';
        echo '<button onclick="trimAllPastRecurring()" id="trim-all-past-btn" style="background:#e74c3c; color:#fff; border:none; padding:4px 12px; border-radius:3px; cursor:pointer; font-size:11px; font-weight:600; transition:all 0.15s;" onmouseover="this.style.filter=\'brightness(1.2)\'" onmouseout="this.style.filter=\'none\'">✂️ Trim All Past</button>';
        echo '<button onclick="rescanRecurringEvents()" id="rescan-recurring-btn" style="background:#00cc07; color:#fff; border:none; padding:4px 12px; border-radius:3px; cursor:pointer; font-size:11px; font-weight:600; transition:all 0.15s;" onmouseover="this.style.filter=\'brightness(1.2)\'" onmouseout="this.style.filter=\'none\'">🔍 Rescan</button>';
        echo '</div>';
        echo '</div>';
        
        $recurringEvents = $this->findRecurringEvents();
        
        echo '<div id="recurring-content">';
        $this->renderRecurringTable($recurringEvents, $colors);
        echo '</div>';
        echo '</div>';
        
        // Compact Tree-based Namespace Manager
        echo '<div style="background:' . $colors['bg'] . '; padding:12px; margin:10px 0; border-left:3px solid #00cc07; border-radius:3px; max-width:1200px;">';
        echo '<h3 style="margin:0 0 8px 0; color:#00cc07; font-size:16px;">📁 Namespace Explorer</h3>';
        echo '<p style="color:' . $colors['text'] . '; font-size:11px; margin:0 0 8px;">Select events and move between namespaces. Drag & drop also supported.</p>';
        
        // Search bar
        echo '<div style="margin-bottom:8px;">';
        echo '<input type="text" id="searchEvents" onkeyup="filterEvents()" placeholder="🔍 Search events by title..." style="width:100%; padding:6px 10px; border:1px solid ' . $colors['border'] . '; border-radius:3px; font-size:12px;">';
        echo '</div>';
        
        $eventsByNamespace = $this->getEventsByNamespace();
        
        // Control bar
        echo '<form method="post" action="?do=admin&page=calendar&tab=manage" id="moveForm">';
        echo '<input type="hidden" name="action" value="move_selected_events" id="formAction">';
        echo '<div style="background:#2d2d2d; color:white; padding:6px 10px; border-radius:3px; margin-bottom:8px; display:flex; gap:8px; align-items:center; font-size:12px;">';
        echo '<button type="button" onclick="selectAll()" style="background:#00cc07; color:white; border:none; padding:4px 8px; border-radius:2px; cursor:pointer; font-size:11px;">☑ All</button>';
        echo '<button type="button" onclick="deselectAll()" style="background:#666; color:white; border:none; padding:4px 8px; border-radius:2px; cursor:pointer; font-size:11px;">☐ None</button>';
        echo '<button type="button" onclick="deleteSelected()" style="background:#e74c3c; color:white; border:none; padding:4px 8px; border-radius:2px; cursor:pointer; font-size:11px; margin-left:10px;">🗑️ Delete</button>';
        echo '<span style="margin-left:10px;">Move to:</span>';
        echo '<input list="namespaceList" name="target_namespace" required style="padding:3px 6px; border:1px solid ' . $colors['border'] . '; border-radius:2px; font-size:11px; min-width:150px;" placeholder="Type or select...">';
        echo '<datalist id="namespaceList">';
        echo '<option value="">(default)</option>';
        foreach (array_keys($eventsByNamespace) as $ns) {
            if ($ns !== '') {
                echo '<option value="' . hsc($ns) . '">' . hsc($ns) . '</option>';
            }
        }
        echo '</datalist>';
        echo '<button type="submit" style="background:#00cc07; color:white; border:none; padding:4px 10px; border-radius:2px; cursor:pointer; font-size:11px; font-weight:bold;">➡️ Move</button>';
        echo '<button type="button" onclick="createNewNamespace()" style="background:#7b1fa2; color:white; border:none; padding:4px 10px; border-radius:2px; cursor:pointer; font-size:11px; font-weight:bold; margin-left:5px;">➕ New Namespace</button>';
        echo '<button type="button" onclick="cleanupEmptyNamespaces()" id="cleanup-ns-btn" style="background:#e74c3c; color:white; border:none; padding:4px 10px; border-radius:2px; cursor:pointer; font-size:11px; font-weight:bold; margin-left:5px;">🧹 Cleanup</button>';
        echo '<span id="selectedCount" style="margin-left:auto; color:#00cc07; font-size:11px;">0 selected</span>';
        echo '</div>';
        
        // Cleanup status message - displayed prominently after control bar
        echo '<div id="cleanup-ns-status" style="font-size:12px; margin-bottom:8px; min-height:18px;"></div>';
        
        echo '<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">';
        
        // Event list with checkboxes
        echo '<div>';
        echo '<div style="max-height:450px; overflow-y:auto; border:1px solid ' . $colors['border'] . '; border-radius:3px; background:' . $colors['bg'] . ';">';
        
        foreach ($eventsByNamespace as $namespace => $data) {
            $nsId = 'ns_' . md5($namespace);
            $eventCount = count($data['events']);
            
            echo '<div style="border-bottom:1px solid #ddd;">';
            
            // Namespace header - ultra compact
            echo '<div style="background:#f5f5f5; padding:3px 6px; display:flex; justify-content:space-between; align-items:center; font-size:11px;">';
            echo '<div style="display:flex; align-items:center; gap:4px;">';
            echo '<span onclick="toggleNamespace(\'' . $nsId . '\')" style="cursor:pointer; width:12px; display:inline-block; font-size:10px;"><span id="' . $nsId . '_arrow">▶</span></span>';
            echo '<input type="checkbox" onclick="toggleNamespaceSelect(\'' . $nsId . '\')" id="' . $nsId . '_check" style="margin:0; width:12px; height:12px;">';
            echo '<span onclick="toggleNamespace(\'' . $nsId . '\')" style="cursor:pointer; font-weight:600; font-size:11px;">📁 ' . hsc($namespace ?: '(default)') . '</span>';
            echo '</div>';
            echo '<div style="display:flex; gap:3px; align-items:center;">';
            echo '<span style="background:#00cc07; color:white; padding:0px 4px; border-radius:6px; font-size:9px; line-height:14px;">' . $eventCount . '</span>';
            echo '<button type="button" onclick="renameNamespace(\'' . hsc($namespace) . '\')" style="background:#3498db; color:white; border:none; padding:1px 4px; border-radius:2px; cursor:pointer; font-size:9px; line-height:14px;" title="Rename namespace">✏️</button>';
            echo '<button type="button" onclick="deleteNamespace(\'' . hsc($namespace) . '\')" style="background:#e74c3c; color:white; border:none; padding:1px 4px; border-radius:2px; cursor:pointer; font-size:9px; line-height:14px;">🗑️</button>';
            echo '</div>';
            echo '</div>';
            
            // Events - ultra compact
            echo '<div id="' . $nsId . '" style="display:none; max-height:150px; overflow-y:auto;">';
            foreach ($data['events'] as $event) {
                $eventId = $event['id'] . '|' . $namespace . '|' . $event['date'] . '|' . $event['month'];
                $checkId = 'evt_' . md5($eventId);
                
                echo '<div draggable="true" ondragstart="dragStart(event, \'' . hsc($eventId) . '\')" style="padding:2px 6px 2px 16px; border-bottom:1px solid #f8f8f8; display:flex; align-items:center; gap:4px; font-size:10px; cursor:move;" class="event-row" onmouseover="this.style.background=\'#f9f9f9\'" onmouseout="this.style.background=\'white\'">';
                echo '<input type="checkbox" name="events[]" value="' . hsc($eventId) . '" class="event-checkbox ' . $nsId . '_events" id="' . $checkId . '" onclick="updateCount()" style="margin:0; width:12px; height:12px;">';
                echo '<div style="flex:1; min-width:0;">';
                echo '<div style="font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-size:10px;">' . hsc($event['title']) . '</div>';
                echo '<div style="color:#999; font-size:9px;">' . hsc($event['date']) . ($event['startTime'] ? ' • ' . hsc($event['startTime']) : '') . '</div>';
                echo '</div>';
                echo '</div>';
            }
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
        
        // Drop zones - ultra compact
        echo '<div>';
        echo '<div style="background:#00cc07; color:white; padding:3px 6px; border-radius:3px 3px 0 0; font-size:11px; font-weight:bold;">🎯 Drop Target</div>';
        echo '<div style="border:1px solid ' . $colors['border'] . '; border-top:none; border-radius:0 0 3px 3px; max-height:450px; overflow-y:auto; background:' . $colors['bg'] . ';">';
        
        foreach (array_keys($eventsByNamespace) as $namespace) {
            echo '<div ondrop="drop(event, \'' . hsc($namespace) . '\')" ondragover="allowDrop(event)" style="padding:5px 6px; border-bottom:1px solid #eee; background:' . $colors['bg'] . '; min-height:28px;" onmouseover="this.style.background=\'#f0fff0\'" onmouseout="this.style.background=\'white\'">';
            echo '<div style="font-size:11px; font-weight:600; color:#00cc07;">📁 ' . hsc($namespace ?: '(default)') . '</div>';
            echo '<div style="color:#999; font-size:9px; margin-top:1px;">Drop here</div>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
        
        echo '</div>'; // end grid
        echo '</form>';
        
        echo '</div>';
        
        // JavaScript
        echo '<script>
        var adminColors = {
            text: "' . $colors['text'] . '",
            bg: "' . $colors['bg'] . '",
            border: "' . $colors['border'] . '"
        };
        // Table sorting functionality - defined early so onclick handlers work
        let sortDirection = {}; // Track sort direction for each column
        
        function cleanupEmptyNamespaces() {
            var btn = document.getElementById("cleanup-ns-btn");
            var status = document.getElementById("cleanup-ns-status");
            if (btn) { btn.textContent = "⏳ Scanning..."; btn.disabled = true; }
            if (status) { status.innerHTML = ""; }
            
            // Dry run first
            fetch(DOKU_BASE + "lib/exe/ajax.php", {
                method: "POST",
                headers: {"Content-Type": "application/x-www-form-urlencoded"},
                body: "call=plugin_calendar&action=cleanup_empty_namespaces&dry_run=1&sectok=" + JSINFO.sectok
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (btn) { btn.textContent = "🧹 Cleanup"; btn.disabled = false; }
                if (!data.success) {
                    if (status) { status.innerHTML = "<span style=\\\'color:#e74c3c;\\\'>❌ " + (data.error || "Failed") + "</span>"; }
                    return;
                }
                
                var details = data.details || [];
                var totalActions = details.length;
                
                if (totalActions === 0) {
                    if (status) { status.innerHTML = "<span style=\\\'color:#00cc07;\\\'>✅ No empty namespaces or orphan calendar folders found.</span>"; }
                    return;
                }
                
                // Build detail list for confirm
                var msg = "Found " + totalActions + " item(s) to clean up:\\n\\n";
                for (var i = 0; i < details.length; i++) {
                    msg += "• " + details[i] + "\\n";
                }
                msg += "\\nProceed with cleanup?";
                
                if (!confirm(msg)) return;
                
                // Execute
                if (btn) { btn.textContent = "⏳ Cleaning..."; btn.disabled = true; }
                fetch(DOKU_BASE + "lib/exe/ajax.php", {
                    method: "POST",
                    headers: {"Content-Type": "application/x-www-form-urlencoded"},
                    body: "call=plugin_calendar&action=cleanup_empty_namespaces&sectok=" + JSINFO.sectok
                })
                .then(function(r) { return r.json(); })
                .then(function(data2) {
                    var msgText = data2.message || "Cleanup complete";
                    if (data2.details && data2.details.length > 0) {
                        msgText += " (" + data2.details.join(", ") + ")";
                    }
                    window.location.href = "?do=admin&page=calendar&tab=manage&msg=" + encodeURIComponent(msgText) + "&msgtype=success";
                });
            })
            .catch(function(err) {
                if (btn) { btn.textContent = "🧹 Cleanup"; btn.disabled = false; }
                if (status) { status.innerHTML = "<span style=\\\'color:#e74c3c;\\\'>❌ Error: " + err + "</span>"; }
            });
        }
        function trimAllPastRecurring() {
            var btn = document.getElementById("trim-all-past-btn");
            if (btn) { btn.textContent = "⏳ Counting..."; btn.disabled = true; }
            
            // Step 1: dry run to get count
            fetch(DOKU_BASE + "lib/exe/ajax.php", {
                method: "POST",
                headers: {"Content-Type": "application/x-www-form-urlencoded"},
                body: "call=plugin_calendar&action=trim_all_past_recurring&dry_run=1&sectok=" + JSINFO.sectok
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (btn) { btn.textContent = "✂️ Trim All Past"; btn.disabled = false; }
                var count = data.count || 0;
                if (count === 0) {
                    alert("No past recurring events found to remove.");
                    return;
                }
                if (!confirm("Found " + count + " past recurring event" + (count !== 1 ? "s" : "") + " to remove.\n\nThis cannot be undone. Proceed?")) return;
                
                // Step 2: actually delete
                if (btn) { btn.textContent = "⏳ Trimming..."; btn.disabled = true; }
                fetch(DOKU_BASE + "lib/exe/ajax.php", {
                    method: "POST",
                    headers: {"Content-Type": "application/x-www-form-urlencoded"},
                    body: "call=plugin_calendar&action=trim_all_past_recurring&sectok=" + JSINFO.sectok
                })
                .then(function(r) { return r.json(); })
                .then(function(data2) {
                    if (btn) {
                        btn.textContent = data2.success ? ("✅ Removed " + (data2.count || 0)) : "❌ Failed";
                        btn.disabled = false;
                    }
                    setTimeout(function() { if (btn) btn.textContent = "✂️ Trim All Past"; }, 3000);
                    rescanRecurringEvents();
                });
            })
            .catch(function(err) {
                if (btn) { btn.textContent = "✂️ Trim All Past"; btn.disabled = false; }
            });
        }
        
        function rescanRecurringEvents() {
            var btn = document.getElementById("rescan-recurring-btn");
            var content = document.getElementById("recurring-content");
            if (btn) { btn.textContent = "⏳ Scanning..."; btn.disabled = true; }
            
            fetch(DOKU_BASE + "lib/exe/ajax.php", {
                method: "POST",
                headers: {"Content-Type": "application/x-www-form-urlencoded"},
                body: "call=plugin_calendar&action=rescan_recurring&sectok=" + JSINFO.sectok
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && content) {
                    content.innerHTML = data.html;
                }
                if (btn) { btn.textContent = "🔍 Rescan (" + (data.count || 0) + " found)"; btn.disabled = false; }
                setTimeout(function() { if (btn) btn.textContent = "🔍 Rescan"; }, 3000);
            })
            .catch(function(err) {
                if (btn) { btn.textContent = "🔍 Rescan"; btn.disabled = false; }
                console.error("Rescan failed:", err);
            });
        }
        
        function recurringAction(action, params, statusEl) {
            if (statusEl) statusEl.textContent = "⏳ Working...";
            var body = "call=plugin_calendar&action=" + action + "&sectok=" + JSINFO.sectok;
            for (var key in params) {
                body += "&" + encodeURIComponent(key) + "=" + encodeURIComponent(params[key]);
            }
            return fetch(DOKU_BASE + "lib/exe/ajax.php", {
                method: "POST",
                headers: {"Content-Type": "application/x-www-form-urlencoded"},
                body: body
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (statusEl) {
                    statusEl.textContent = data.success ? ("✅ " + data.message) : ("❌ " + (data.error || "Failed"));
                    statusEl.style.color = data.success ? "#00cc07" : "#e74c3c";
                }
                return data;
            })
            .catch(function(err) {
                if (statusEl) { statusEl.textContent = "❌ Error: " + err; statusEl.style.color = "#e74c3c"; }
            });
        }
        
        function manageRecurringSeries(title, namespace, count, firstDate, pattern, hasFlag) {
            var isPaused = title.indexOf("⏸") === 0;
            var cleanTitle = title.replace(/^⏸\s*/, "");
            var safeTitle = title.replace(/\x27/g, "\\\x27");
            var todayStr = new Date().toISOString().split("T")[0];
            
            var dialog = document.createElement("div");
            dialog.style.cssText = "position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center; z-index:10000;";
            dialog.addEventListener("click", function(e) { if (e.target === dialog) dialog.remove(); });
            
            var h = "<div style=\"background:' . $colors['bg'] . '; padding:20px; border-radius:8px; min-width:520px; max-width:700px; max-height:90vh; overflow-y:auto; font-family:system-ui,sans-serif;\">";
            h += "<h3 style=\"margin:0 0 5px; color:#00cc07;\">⚙️ Manage Recurring Series</h3>";
            h += "<p style=\"margin:0 0 15px; color:' . $colors['text'] . '; font-size:13px;\"><strong>" + cleanTitle + "</strong> — " + count + " occurrences, " + pattern + ", starts " + firstDate + "</p>";
            h += "<div id=\"manage-status\" style=\"font-size:12px; min-height:18px; margin-bottom:10px;\"></div>";
            
            // Extend
            h += "<div style=\"border:1px solid ' . $colors['border'] . '; border-radius:4px; padding:10px; margin-bottom:10px;\">";
            h += "<div style=\"font-weight:700; color:#00cc07; font-size:12px; margin-bottom:6px;\">📅 Extend Series</div>";
            h += "<div style=\"display:flex; gap:8px; align-items:end;\">";
            h += "<div><label style=\"font-size:11px; display:block; margin-bottom:2px;\">Add occurrences:</label>";
            h += "<input type=\"number\" id=\"manage-extend-count\" value=\"4\" min=\"1\" max=\"52\" style=\"width:60px; padding:4px; border:1px solid ' . $colors['border'] . '; border-radius:3px; font-size:12px;\"></div>";
            h += "<div><label style=\"font-size:11px; display:block; margin-bottom:2px;\">Days apart:</label>";
            h += "<select id=\"manage-extend-interval\" style=\"padding:4px; border:1px solid ' . $colors['border'] . '; border-radius:3px; font-size:12px;\">";
            h += "<option value=\"1\">Daily</option><option value=\"7\" selected>Weekly</option><option value=\"14\">Bi-weekly</option><option value=\"30\">Monthly</option><option value=\"90\">Quarterly</option><option value=\"365\">Yearly</option></select></div>";
            h += "<button onclick=\"recurringAction(\x27extend_recurring\x27, {title:\x27" + safeTitle + "\x27, namespace:\x27" + namespace + "\x27, count:document.getElementById(\x27manage-extend-count\x27).value, interval_days:document.getElementById(\x27manage-extend-interval\x27).value}, document.getElementById(\x27manage-status\x27))\" style=\"background:#00cc07; color:#fff; border:none; padding:5px 12px; border-radius:3px; cursor:pointer; font-size:11px; font-weight:600;\">Extend</button>";
            h += "</div></div>";
            
            // Trim
            h += "<div style=\"border:1px solid ' . $colors['border'] . '; border-radius:4px; padding:10px; margin-bottom:10px;\">";
            h += "<div style=\"font-weight:700; color:#e74c3c; font-size:12px; margin-bottom:6px;\">✂️ Trim Past Events</div>";
            h += "<div style=\"display:flex; gap:8px; align-items:end;\">";
            h += "<div><label style=\"font-size:11px; display:block; margin-bottom:2px;\">Remove before:</label>";
            h += "<input type=\"date\" id=\"manage-trim-date\" value=\"" + todayStr + "\" style=\"padding:4px; border:1px solid ' . $colors['border'] . '; border-radius:3px; font-size:12px;\"></div>";
            h += "<button onclick=\"if(confirm(\x27Remove all occurrences before \x27 + document.getElementById(\x27manage-trim-date\x27).value + \x27?\x27)) recurringAction(\x27trim_recurring\x27, {title:\x27" + safeTitle + "\x27, namespace:\x27" + namespace + "\x27, cutoff_date:document.getElementById(\x27manage-trim-date\x27).value}, document.getElementById(\x27manage-status\x27))\" style=\"background:#e74c3c; color:#fff; border:none; padding:5px 12px; border-radius:3px; cursor:pointer; font-size:11px; font-weight:600;\">Trim</button>";
            h += "</div></div>";
            
            // Change Pattern
            h += "<div style=\"border:1px solid ' . $colors['border'] . '; border-radius:4px; padding:10px; margin-bottom:10px;\">";
            h += "<div style=\"font-weight:700; color:#ff9800; font-size:12px; margin-bottom:6px;\">🔄 Change Pattern</div>";
            h += "<p style=\"font-size:11px; color:' . $colors['text'] . '; margin:0 0 6px; opacity:0.7;\">Respaces future occurrences only. Past events stay in place.</p>";
            h += "<div style=\"display:flex; gap:8px; align-items:end;\">";
            h += "<div><label style=\"font-size:11px; display:block; margin-bottom:2px;\">New interval:</label>";
            h += "<select id=\"manage-pattern-interval\" style=\"padding:4px; border:1px solid ' . $colors['border'] . '; border-radius:3px; font-size:12px;\">";
            h += "<option value=\"1\">Daily</option><option value=\"7\">Weekly</option><option value=\"14\">Bi-weekly</option><option value=\"30\">Monthly</option><option value=\"90\">Quarterly</option><option value=\"365\">Yearly</option></select></div>";
            h += "<button onclick=\"if(confirm(\x27Respace all future occurrences?\x27)) recurringAction(\x27change_pattern_recurring\x27, {title:\x27" + safeTitle + "\x27, namespace:\x27" + namespace + "\x27, interval_days:document.getElementById(\x27manage-pattern-interval\x27).value}, document.getElementById(\x27manage-status\x27))\" style=\"background:#ff9800; color:#fff; border:none; padding:5px 12px; border-radius:3px; cursor:pointer; font-size:11px; font-weight:600;\">Change</button>";
            h += "</div></div>";
            
            // Change Start Date
            h += "<div style=\"border:1px solid ' . $colors['border'] . '; border-radius:4px; padding:10px; margin-bottom:10px;\">";
            h += "<div style=\"font-weight:700; color:#2196f3; font-size:12px; margin-bottom:6px;\">📆 Change Start Date</div>";
            h += "<p style=\"font-size:11px; color:' . $colors['text'] . '; margin:0 0 6px; opacity:0.7;\">Shifts ALL occurrences by the difference between old and new start date.</p>";
            h += "<div style=\"display:flex; gap:8px; align-items:end;\">";
            h += "<div><label style=\"font-size:11px; display:block; margin-bottom:2px;\">Current: " + firstDate + "</label>";
            h += "<input type=\"date\" id=\"manage-start-date\" value=\"" + firstDate + "\" style=\"padding:4px; border:1px solid ' . $colors['border'] . '; border-radius:3px; font-size:12px;\"></div>";
            h += "<button onclick=\"if(confirm(\x27Shift all occurrences to new start date?\x27)) recurringAction(\x27change_start_recurring\x27, {title:\x27" + safeTitle + "\x27, namespace:\x27" + namespace + "\x27, new_start_date:document.getElementById(\x27manage-start-date\x27).value}, document.getElementById(\x27manage-status\x27))\" style=\"background:#2196f3; color:#fff; border:none; padding:5px 12px; border-radius:3px; cursor:pointer; font-size:11px; font-weight:600;\">Shift</button>";
            h += "</div></div>";
            
            // Pause/Resume
            h += "<div style=\"border:1px solid ' . $colors['border'] . '; border-radius:4px; padding:10px; margin-bottom:10px;\">";
            h += "<div style=\"font-weight:700; color:#9c27b0; font-size:12px; margin-bottom:6px;\">" + (isPaused ? "▶️ Resume Series" : "⏸ Pause Series") + "</div>";
            h += "<p style=\"font-size:11px; color:' . $colors['text'] . '; margin:0 0 6px; opacity:0.7;\">" + (isPaused ? "Removes ⏸ prefix and paused flag from all occurrences." : "Adds ⏸ prefix to future occurrences. They remain in the calendar but are visually marked as paused.") + "</p>";
            h += "<button onclick=\"recurringAction(\x27" + (isPaused ? "resume_recurring" : "pause_recurring") + "\x27, {title:\x27" + safeTitle + "\x27, namespace:\x27" + namespace + "\x27}, document.getElementById(\x27manage-status\x27))\" style=\"background:#9c27b0; color:#fff; border:none; padding:5px 12px; border-radius:3px; cursor:pointer; font-size:11px; font-weight:600;\">" + (isPaused ? "▶️ Resume" : "⏸ Pause") + "</button>";
            h += "</div>";
            
            // Close
            h += "<div style=\"text-align:right; margin-top:10px;\">";
            h += "<button onclick=\"this.closest(\x27[style*=fixed]\x27).remove(); rescanRecurringEvents();\" style=\"background:#666; color:#fff; border:none; padding:8px 20px; border-radius:3px; cursor:pointer; font-weight:600;\">Close</button>";
            h += "</div></div>";
            
            dialog.innerHTML = h;
            document.body.appendChild(dialog);
        }
        
        function sortRecurringTable(columnIndex) {
            const table = document.getElementById("recurringTable");
            const tbody = document.getElementById("recurringTableBody");
            
            if (!table || !tbody) return;
            
            const rows = Array.from(tbody.querySelectorAll("tr"));
            if (rows.length === 0) return;
            
            // Toggle sort direction for this column
            if (!sortDirection[columnIndex]) {
                sortDirection[columnIndex] = "asc";
            } else {
                sortDirection[columnIndex] = sortDirection[columnIndex] === "asc" ? "desc" : "asc";
            }
            
            const direction = sortDirection[columnIndex];
            const isNumeric = columnIndex === 4; // Count column
            
            // Sort rows
            rows.sort((a, b) => {
                let aValue = a.cells[columnIndex].textContent.trim();
                let bValue = b.cells[columnIndex].textContent.trim();
                
                // Extract text from code elements for namespace column
                if (columnIndex === 1) {
                    const aCode = a.cells[columnIndex].querySelector("code");
                    const bCode = b.cells[columnIndex].querySelector("code");
                    aValue = aCode ? aCode.textContent.trim() : aValue;
                    bValue = bCode ? bCode.textContent.trim() : bValue;
                }
                
                // Extract number from strong elements for count column
                if (isNumeric) {
                    const aStrong = a.cells[columnIndex].querySelector("strong");
                    const bStrong = b.cells[columnIndex].querySelector("strong");
                    aValue = aStrong ? parseInt(aStrong.textContent.trim()) : 0;
                    bValue = bStrong ? parseInt(bStrong.textContent.trim()) : 0;
                    
                    return direction === "asc" ? aValue - bValue : bValue - aValue;
                }
                
                // String comparison
                if (direction === "asc") {
                    return aValue.localeCompare(bValue);
                } else {
                    return bValue.localeCompare(aValue);
                }
            });
            
            // Update arrows
            const headers = table.querySelectorAll("th");
            headers.forEach((header, index) => {
                const arrow = header.querySelector(".sort-arrow");
                if (arrow) {
                    if (index === columnIndex) {
                        arrow.textContent = direction === "asc" ? "↑" : "↓";
                        arrow.style.color = "#00cc07";
                    } else {
                        arrow.textContent = "⇅";
                        arrow.style.color = "#999";
                    }
                }
            });
            
            // Rebuild tbody
            rows.forEach(row => tbody.appendChild(row));
        }
        
        function filterRecurringEvents() {
            const searchInput = document.getElementById("searchRecurring");
            const filter = normalizeText(searchInput.value);
            const tbody = document.getElementById("recurringTableBody");
            const rows = tbody.getElementsByTagName("tr");
            
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const titleCell = row.getElementsByTagName("td")[0];
                
                if (titleCell) {
                    const titleText = normalizeText(titleCell.textContent || titleCell.innerText);
                    
                    if (titleText.indexOf(filter) > -1) {
                        row.classList.remove("recurring-row-hidden");
                    } else {
                        row.classList.add("recurring-row-hidden");
                    }
                }
            }
        }
        
        function normalizeText(text) {
            // Convert to lowercase
            text = text.toLowerCase();
            
            // Remove apostrophes and quotes
            text = text.replace(/[\'\"]/g, "");
            
            // Replace accented characters with regular ones
            text = text.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
            
            // Remove special characters except spaces and alphanumeric
            text = text.replace(/[^a-z0-9\s]/g, "");
            
            // Collapse multiple spaces
            text = text.replace(/\s+/g, " ");
            
            return text.trim();
        }
        
        function filterEvents() {
            const searchText = normalizeText(document.getElementById("searchEvents").value);
            const eventRows = document.querySelectorAll(".event-row");
            let visibleCount = 0;
            
            eventRows.forEach(row => {
                const titleElement = row.querySelector("div div");
                const originalTitle = titleElement.getAttribute("data-original-title") || titleElement.textContent;
                
                // Store original title if not already stored
                if (!titleElement.getAttribute("data-original-title")) {
                    titleElement.setAttribute("data-original-title", originalTitle);
                }
                
                const normalizedTitle = normalizeText(originalTitle);
                
                if (normalizedTitle.includes(searchText) || searchText === "") {
                    row.style.display = "flex";
                    visibleCount++;
                } else {
                    row.style.display = "none";
                }
            });
            
            // Update namespace visibility and counts
            document.querySelectorAll("[id^=ns_]").forEach(nsDiv => {
                if (nsDiv.id.endsWith("_arrow") || nsDiv.id.endsWith("_check")) return;
                
                const visibleEvents = nsDiv.querySelectorAll(".event-row[style*=\\"display: flex\\"], .event-row:not([style*=\\"display: none\\"])").length;
                const nsId = nsDiv.id;
                const arrow = document.getElementById(nsId + "_arrow");
                
                // Auto-expand namespaces with matches when searching
                if (searchText && visibleEvents > 0) {
                    nsDiv.style.display = "block";
                    if (arrow) arrow.textContent = "▼";
                }
            });
        }
        
        function toggleNamespace(id) {
            const elem = document.getElementById(id);
            const arrow = document.getElementById(id + "_arrow");
            if (elem.style.display === "none") {
                elem.style.display = "block";
                arrow.textContent = "▼";
            } else {
                elem.style.display = "none";
                arrow.textContent = "▶";
            }
        }
        
        function toggleNamespaceSelect(nsId) {
            const checkbox = document.getElementById(nsId + "_check");
            const events = document.querySelectorAll("." + nsId + "_events");
            
            // Only select visible events (not hidden by search)
            events.forEach(cb => {
                const eventRow = cb.closest(".event-row");
                if (eventRow && eventRow.style.display !== "none") {
                    cb.checked = checkbox.checked;
                }
            });
            updateCount();
        }
        
        function selectAll() {
            // Only select visible events
            document.querySelectorAll(".event-checkbox").forEach(cb => {
                const eventRow = cb.closest(".event-row");
                if (eventRow && eventRow.style.display !== "none") {
                    cb.checked = true;
                }
            });
            // Update namespace checkboxes to indeterminate if partially selected
            document.querySelectorAll("input[id$=_check]").forEach(nsCheckbox => {
                const nsId = nsCheckbox.id.replace("_check", "");
                const events = document.querySelectorAll("." + nsId + "_events");
                const visibleEvents = Array.from(events).filter(cb => {
                    const row = cb.closest(".event-row");
                    return row && row.style.display !== "none";
                });
                const checkedVisible = visibleEvents.filter(cb => cb.checked);
                
                if (checkedVisible.length === visibleEvents.length && visibleEvents.length > 0) {
                    nsCheckbox.checked = true;
                } else if (checkedVisible.length > 0) {
                    nsCheckbox.indeterminate = true;
                } else {
                    nsCheckbox.checked = false;
                }
            });
            updateCount();
        }
        
        function deselectAll() {
            document.querySelectorAll(".event-checkbox").forEach(cb => cb.checked = false);
            document.querySelectorAll("input[id$=_check]").forEach(cb => {
                cb.checked = false;
                cb.indeterminate = false;
            });
            updateCount();
        }
        
        function deleteSelected() {
            const checkedBoxes = document.querySelectorAll(".event-checkbox:checked");
            if (checkedBoxes.length === 0) {
                alert("No events selected");
                return;
            }
            
            const count = checkedBoxes.length;
            if (!confirm(`Delete ${count} selected event(s)?\\n\\nThis cannot be undone!`)) {
                return;
            }
            
            const form = document.createElement("form");
            form.method = "POST";
            form.action = "?do=admin&page=calendar&tab=manage";
            
            const actionInput = document.createElement("input");
            actionInput.type = "hidden";
            actionInput.name = "action";
            actionInput.value = "delete_selected_events";
            form.appendChild(actionInput);
            
            checkedBoxes.forEach(cb => {
                const eventInput = document.createElement("input");
                eventInput.type = "hidden";
                eventInput.name = "events[]";
                eventInput.value = cb.value;
                form.appendChild(eventInput);
            });
            
            document.body.appendChild(form);
            form.submit();
        }
        
        function createNewNamespace() {
            const namespaceName = prompt("Enter new namespace name:\\n\\nExamples:\\n- work\\n- personal\\n- projects:alpha\\n- aspen:travel:2025");
            
            if (!namespaceName) {
                return; // Cancelled
            }
            
            // Validate namespace name
            if (!/^[a-zA-Z0-9_:-]+$/.test(namespaceName)) {
                alert("Invalid namespace name.\\n\\nUse only letters, numbers, underscore, hyphen, and colon.\\nExample: work:projects:alpha");
                return;
            }
            
            // Submit form to create namespace
            const form = document.createElement("form");
            form.method = "POST";
            form.action = "?do=admin&page=calendar&tab=manage";
            
            const actionInput = document.createElement("input");
            actionInput.type = "hidden";
            actionInput.name = "action";
            actionInput.value = "create_namespace";
            form.appendChild(actionInput);
            
            const namespaceInput = document.createElement("input");
            namespaceInput.type = "hidden";
            namespaceInput.name = "namespace_name";
            namespaceInput.value = namespaceName;
            form.appendChild(namespaceInput);
            
            document.body.appendChild(form);
            form.submit();
        }
        
        function updateCount() {
            const count = document.querySelectorAll(".event-checkbox:checked").length;
            document.getElementById("selectedCount").textContent = count + " selected";
        }
        
        function deleteNamespace(namespace) {
            const displayName = namespace || "(default)";
            if (!confirm("Delete ENTIRE namespace: " + displayName + "?\\n\\nThis will delete ALL events in this namespace!\\n\\nThis cannot be undone!")) {
                return;
            }
            const form = document.createElement("form");
            form.method = "POST";
            form.action = "?do=admin&page=calendar&tab=manage";
            const actionInput = document.createElement("input");
            actionInput.type = "hidden";
            actionInput.name = "action";
            actionInput.value = "delete_namespace";
            form.appendChild(actionInput);
            const nsInput = document.createElement("input");
            nsInput.type = "hidden";
            nsInput.name = "namespace";
            nsInput.value = namespace;
            form.appendChild(nsInput);
            document.body.appendChild(form);
            form.submit();
        }
        
        function renameNamespace(oldNamespace) {
            const displayName = oldNamespace || "(default)";
            const newName = prompt("Rename namespace: " + displayName + "\\n\\nEnter new name:", oldNamespace);
            if (newName === null || newName === oldNamespace) {
                return; // Cancelled or no change
            }
            const form = document.createElement("form");
            form.method = "POST";
            form.action = "?do=admin&page=calendar&tab=manage";
            const actionInput = document.createElement("input");
            actionInput.type = "hidden";
            actionInput.name = "action";
            actionInput.value = "rename_namespace";
            form.appendChild(actionInput);
            const oldInput = document.createElement("input");
            oldInput.type = "hidden";
            oldInput.name = "old_namespace";
            oldInput.value = oldNamespace;
            form.appendChild(oldInput);
            const newInput = document.createElement("input");
            newInput.type = "hidden";
            newInput.name = "new_namespace";
            newInput.value = newName;
            form.appendChild(newInput);
            document.body.appendChild(form);
            form.submit();
        }
        
        let draggedEvent = null;
        
        function dragStart(event, eventId) {
            const checkbox = event.target.closest(".event-row").querySelector(".event-checkbox");
            
            // If this event is checked, drag all checked events
            const checkedBoxes = document.querySelectorAll(".event-checkbox:checked");
            if (checkbox && checkbox.checked && checkedBoxes.length > 1) {
                // Dragging multiple selected events
                draggedEvent = "MULTIPLE";
                event.dataTransfer.setData("text/plain", "MULTIPLE");
            } else {
                // Dragging single event
                draggedEvent = eventId;
                event.dataTransfer.setData("text/plain", eventId);
            }
            event.dataTransfer.effectAllowed = "move";
            event.target.style.opacity = "0.5";
        }
        
        function allowDrop(event) {
            event.preventDefault();
            event.dataTransfer.dropEffect = "move";
        }
        
        function drop(event, targetNamespace) {
            event.preventDefault();
            
            if (draggedEvent === "MULTIPLE") {
                // Move all selected events
                const checkedBoxes = document.querySelectorAll(".event-checkbox:checked");
                if (checkedBoxes.length === 0) return;
                
                const form = document.createElement("form");
                form.method = "POST";
                form.action = "?do=admin&page=calendar&tab=manage";
                
                const actionInput = document.createElement("input");
                actionInput.type = "hidden";
                actionInput.name = "action";
                actionInput.value = "move_selected_events";
                form.appendChild(actionInput);
                
                checkedBoxes.forEach(cb => {
                    const eventInput = document.createElement("input");
                    eventInput.type = "hidden";
                    eventInput.name = "events[]";
                    eventInput.value = cb.value;
                    form.appendChild(eventInput);
                });
                
                const targetInput = document.createElement("input");
                targetInput.type = "hidden";
                targetInput.name = "target_namespace";
                targetInput.value = targetNamespace;
                form.appendChild(targetInput);
                
                document.body.appendChild(form);
                form.submit();
            } else {
                // Move single event
                if (!draggedEvent) return;
                const parts = draggedEvent.split("|");
                const sourceNamespace = parts[1];
                if (sourceNamespace === targetNamespace) return;
                
                const form = document.createElement("form");
                form.method = "POST";
                form.action = "?do=admin&page=calendar&tab=manage";
                const actionInput = document.createElement("input");
                actionInput.type = "hidden";
                actionInput.name = "action";
                actionInput.value = "move_single_event";
                form.appendChild(actionInput);
                const eventInput = document.createElement("input");
                eventInput.type = "hidden";
                eventInput.name = "event";
                eventInput.value = draggedEvent;
                form.appendChild(eventInput);
                const targetInput = document.createElement("input");
                targetInput.type = "hidden";
                targetInput.name = "target_namespace";
                targetInput.value = targetNamespace;
                form.appendChild(targetInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function editRecurringSeries(title, namespace) {
            // Get available namespaces from the namespace explorer
            const namespaces = new Set();
            
            // Method 1: Try to get from namespace explorer folder names
            document.querySelectorAll("[id^=ns_]").forEach(el => {
                const nsSpan = el.querySelector("span:nth-child(3)");
                if (nsSpan) {
                    let nsText = nsSpan.textContent.replace("📁 ", "").trim();
                    if (nsText && nsText !== "(default)") {
                        namespaces.add(nsText);
                    }
                }
            });
            
            // Method 2: Get from datalist if it exists
            document.querySelectorAll("#namespaceList option").forEach(opt => {
                if (opt.value && opt.value !== "") {
                    namespaces.add(opt.value);
                }
            });
            
            // Convert to sorted array
            const nsArray = Array.from(namespaces).sort();
            
            // Build options - include current namespace AND all others
            let nsOptions = "<option value=\\"\\">(default)</option>";
            
            // Add current namespace if it\'s not default
            if (namespace && namespace !== "") {
                nsOptions += "<option value=\\"" + namespace + "\\" selected>" + namespace + " (current)</option>";
            }
            
            // Add all other namespaces
            for (const ns of nsArray) {
                if (ns !== namespace) {
                    nsOptions += "<option value=\\"" + ns + "\\">" + ns + "</option>";
                }
            }
            
            // Show edit dialog for recurring events
            const dialog = document.createElement("div");
            dialog.style.cssText = "position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center; z-index:10000;";
            
            // Close on clicking background
            dialog.addEventListener("click", function(e) {
                if (e.target === dialog) {
                    dialog.remove();
                }
            });
            
            dialog.innerHTML = `
                <div style="background:' . $colors['bg'] . '; padding:20px; border-radius:8px; min-width:500px; max-width:700px; max-height:90vh; overflow-y:auto;">
                    <h3 style="margin:0 0 15px; color:#00cc07;">Edit Recurring Event</h3>
                    <p style="margin:0 0 15px; color:' . $colors['text'] . '; font-size:13px;">Changes will apply to ALL occurrences of: <strong>${title}</strong></p>
                    
                    <form id="editRecurringForm" style="display:flex; flex-direction:column; gap:12px;">
                        <div>
                            <label style="display:block; font-weight:bold; margin-bottom:4px; font-size:13px;">New Title:</label>
                            <input type="text" name="new_title" value="${title}" style="width:100%; padding:8px; border:1px solid ' . $colors['border'] . '; border-radius:3px;" required>
                        </div>
                        
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                            <div>
                                <label style="display:block; font-weight:bold; margin-bottom:4px; font-size:13px;">Start Time:</label>
                                <input type="time" name="start_time" style="width:100%; padding:8px; border:1px solid ' . $colors['border'] . '; border-radius:3px;">
                                <small style="color:#999; font-size:11px;">Leave blank to keep current</small>
                            </div>
                            <div>
                                <label style="display:block; font-weight:bold; margin-bottom:4px; font-size:13px;">End Time:</label>
                                <input type="time" name="end_time" style="width:100%; padding:8px; border:1px solid ' . $colors['border'] . '; border-radius:3px;">
                                <small style="color:#999; font-size:11px;">Leave blank to keep current</small>
                            </div>
                        </div>
                        
                        <div>
                            <label style="display:block; font-weight:bold; margin-bottom:4px; font-size:13px;">Interval (days between occurrences):</label>
                            <select name="interval" style="width:100%; padding:8px; border:1px solid ' . $colors['border'] . '; border-radius:3px;">
                                <option value="">Keep current interval</option>
                                <option value="1">Daily (1 day)</option>
                                <option value="7">Weekly (7 days)</option>
                                <option value="14">Bi-weekly (14 days)</option>
                                <option value="30">Monthly (30 days)</option>
                                <option value="365">Yearly (365 days)</option>
                            </select>
                        </div>
                        
                        <div>
                            <label style="display:block; font-weight:bold; margin-bottom:4px; font-size:13px;">Move to Namespace:</label>
                            <select name="new_namespace" style="width:100%; padding:8px; border:1px solid ' . $colors['border'] . '; border-radius:3px;">
                                ${nsOptions}
                            </select>
                        </div>
                        
                        <div style="display:flex; gap:10px; margin-top:10px;">
                            <button type="submit" style="flex:1; background:#00cc07; color:white; padding:10px; border:none; border-radius:3px; cursor:pointer; font-weight:bold;">Save Changes</button>
                            <button type="button" onclick="closeEditDialog()" style="flex:1; background:#999; color:white; padding:10px; border:none; border-radius:3px; cursor:pointer;">Cancel</button>
                        </div>
                    </form>
                </div>
            `;
            
            document.body.appendChild(dialog);
            
            // Add close function to window
            window.closeEditDialog = function() {
                dialog.remove();
            };
            
            // Handle form submission
            dialog.querySelector("#editRecurringForm").addEventListener("submit", function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                // Submit the edit
                const form = document.createElement("form");
                form.method = "POST";
                form.action = "?do=admin&page=calendar&tab=manage";
                
                const actionInput = document.createElement("input");
                actionInput.type = "hidden";
                actionInput.name = "action";
                actionInput.value = "edit_recurring_series";
                form.appendChild(actionInput);
                
                const oldTitleInput = document.createElement("input");
                oldTitleInput.type = "hidden";
                oldTitleInput.name = "old_title";
                oldTitleInput.value = title;
                form.appendChild(oldTitleInput);
                
                const oldNamespaceInput = document.createElement("input");
                oldNamespaceInput.type = "hidden";
                oldNamespaceInput.name = "old_namespace";
                oldNamespaceInput.value = namespace;
                form.appendChild(oldNamespaceInput);
                
                // Add all form fields
                for (let [key, value] of formData.entries()) {
                    const input = document.createElement("input");
                    input.type = "hidden";
                    input.name = key;
                    input.value = value;
                    form.appendChild(input);
                }
                
                document.body.appendChild(form);
                form.submit();
            });
        }
        
        function deleteRecurringSeries(title, namespace) {
            const displayNs = namespace || "(default)";
            if (!confirm("Delete ALL occurrences of: " + title + " (" + displayNs + ")?\\n\\nThis cannot be undone!")) {
                return;
            }
            const form = document.createElement("form");
            form.method = "POST";
            form.action = "?do=admin&page=calendar&tab=manage";
            const actionInput = document.createElement("input");
            actionInput.type = "hidden";
            actionInput.name = "action";
            actionInput.value = "delete_recurring_series";
            form.appendChild(actionInput);
            const titleInput = document.createElement("input");
            titleInput.type = "hidden";
            titleInput.name = "event_title";
            titleInput.value = title;
            form.appendChild(titleInput);
            const namespaceInput = document.createElement("input");
            namespaceInput.type = "hidden";
            namespaceInput.name = "namespace";
            namespaceInput.value = namespace;
            form.appendChild(namespaceInput);
            document.body.appendChild(form);
            form.submit();
        }
        
        document.addEventListener("dragend", function(e) {
            if (e.target.draggable) {
                e.target.style.opacity = "1";
            }
        });
        </script>';
    }
    
    private function renderUpdateTab($colors = null) {
        global $INPUT;
        
        // Use defaults if not provided
        if ($colors === null) {
            $colors = $this->getTemplateColors();
        }
        
        echo '<h2 style="margin:10px 0; font-size:20px;">📦 Update Plugin</h2>';
        
        // Show message if present
        if ($INPUT->has('msg')) {
            $msg = hsc($INPUT->str('msg'));
            $type = $INPUT->str('msgtype', 'success');
            $class = ($type === 'success') ? 'msg success' : 'msg error';
            echo "<div class=\"$class\" style=\"padding:10px; margin:10px 0; border-left:3px solid " . ($type === 'success' ? '#28a745' : '#dc3545') . "; background:" . ($type === 'success' ? '#d4edda' : '#f8d7da') . "; border-radius:3px; max-width:1200px;\">";
            echo $msg;
            echo "</div>";
        }
        
        // Show current version FIRST (MOVED TO TOP)
        $pluginInfo = DOKU_PLUGIN . 'calendar/plugin.info.txt';
        $info = ['version' => 'Unknown', 'date' => 'Unknown', 'name' => 'Calendar Plugin', 'author' => 'Unknown', 'email' => '', 'desc' => ''];
        if (file_exists($pluginInfo)) {
            $info = array_merge($info, confToHash($pluginInfo));
        }
        
        echo '<div style="background:' . $colors['bg'] . '; padding:12px; margin:10px 0; border-left:3px solid #00cc07; border-radius:3px; max-width:1200px;">';
        echo '<h3 style="margin:0 0 8px 0; color:#00cc07; font-size:16px;">📋 Current Version</h3>';
        echo '<div style="font-size:12px; line-height:1.6;">';
        echo '<div style="margin:4px 0;"><strong>Version:</strong> ' . hsc($info['version']) . ' (' . hsc($info['date']) . ')</div>';
        echo '<div style="margin:4px 0;"><strong>Author:</strong> ' . hsc($info['author']) . ($info['email'] ? ' &lt;' . hsc($info['email']) . '&gt;' : '') . '</div>';
        if ($info['desc']) {
            echo '<div style="margin:4px 0;"><strong>Description:</strong> ' . hsc($info['desc']) . '</div>';
        }
        echo '<div style="margin:4px 0;"><strong>Location:</strong> <code style="background:#f0f0f0; padding:2px 4px; border-radius:2px;">' . DOKU_PLUGIN . 'calendar/</code></div>';
        echo '</div>';
        
        // Check permissions
        $pluginDir = DOKU_PLUGIN . 'calendar/';
        $pluginWritable = is_writable($pluginDir);
        $parentWritable = is_writable(DOKU_PLUGIN);
        
        echo '<div style="margin-top:8px; padding-top:8px; border-top:1px solid ' . $colors['border'] . ';">';
        if ($pluginWritable && $parentWritable) {
            echo '<p style="margin:5px 0; font-size:13px; color:#28a745;"><strong>✅ Permissions:</strong> OK - ready to update</p>';
        } else {
            echo '<p style="margin:5px 0; font-size:13px; color:#dc3545;"><strong>❌ Permissions:</strong> Issues detected</p>';
            if (!$pluginWritable) {
                echo '<p style="margin:2px 0 2px 20px; font-size:12px; color:#dc3545;">Plugin directory not writable</p>';
            }
            if (!$parentWritable) {
                echo '<p style="margin:2px 0 2px 20px; font-size:12px; color:#dc3545;">Parent directory not writable</p>';
            }
            echo '<p style="margin:5px 0; font-size:12px; color:' . $colors['text'] . ';">Fix with: <code style="background:#f0f0f0; padding:2px 4px; border-radius:2px;">chmod -R 755 ' . DOKU_PLUGIN . 'calendar/</code></p>';
            echo '<p style="margin:2px 0; font-size:12px; color:' . $colors['text'] . ';">Or: <code style="background:#f0f0f0; padding:2px 4px; border-radius:2px;">chown -R www-data:www-data ' . DOKU_PLUGIN . 'calendar/</code></p>';
        }
        echo '</div>';
        
        echo '</div>';
        
        // Combined upload and notes section (SIDE BY SIDE)
        echo '<div style="display:flex; gap:15px; max-width:1200px; margin:10px 0;">';
        
        // Left side - Upload form (60% width)
        echo '<div style="flex:1; min-width:0; background:' . $colors['bg'] . '; padding:12px; border-left:3px solid #00cc07; border-radius:3px;">';
        echo '<h3 style="margin:0 0 8px 0; color:#00cc07; font-size:16px;">📤 Upload New Version</h3>';
        echo '<p style="color:' . $colors['text'] . '; font-size:13px; margin:0 0 10px;">Upload a calendar plugin ZIP file to update. Your configuration will be preserved.</p>';
        
        echo '<form method="post" action="?do=admin&page=calendar&tab=update" enctype="multipart/form-data" id="uploadForm">';
        echo '<input type="hidden" name="action" value="upload_update">';
        echo '<div style="margin:10px 0;">';
        echo '<input type="file" name="plugin_zip" accept=".zip" required style="padding:8px; border:1px solid ' . $colors['border'] . '; border-radius:3px; font-size:13px; width:100%;">';
        echo '</div>';
        echo '<div style="margin:10px 0;">';
        echo '<label style="display:flex; align-items:center; gap:8px; font-size:13px;">';
        echo '<input type="checkbox" name="backup_first" value="1" checked>';
        echo '<span>Create backup before updating (Recommended)</span>';
        echo '</label>';
        echo '</div>';
        
        // Buttons side by side
        echo '<div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">';
        echo '<button type="submit" onclick="return confirmUpload()" style="background:#00cc07; color:white; padding:10px 20px; border:none; border-radius:3px; cursor:pointer; font-size:14px; font-weight:bold;">📤 Upload & Install</button>';
        echo '</form>';
        
        // Clear Cache button (next to Upload button)
        echo '<form method="post" action="?do=admin&page=calendar&tab=update" style="display:inline; margin:0;">';
        echo '<input type="hidden" name="action" value="clear_cache">';
        echo '<input type="hidden" name="tab" value="update">';
        echo '<button type="submit" onclick="return confirm(\'Clear all DokuWiki cache? This will refresh all plugin files.\')" style="background:#ff9800; color:white; padding:10px 20px; border:none; border-radius:3px; cursor:pointer; font-size:14px; font-weight:bold;">🗑️ Clear Cache</button>';
        echo '</form>';
        echo '</div>';
        
        echo '<p style="margin:8px 0 0 0; font-size:12px; color:' . $colors['text'] . ';">Clear the DokuWiki cache if changes aren\'t appearing or after updating the plugin.</p>';
        echo '</div>';
        
        // Right side - Important Notes (40% width)
        echo '<div style="flex:0 0 350px; min-width:0; background:#fff3e0; border-left:3px solid #ff9800; padding:12px; border-radius:3px;">';
        echo '<h4 style="margin:0 0 5px 0; color:#e65100; font-size:14px;">⚠️ Important Notes</h4>';
        echo '<ul style="margin:5px 0; padding-left:20px; font-size:12px; color:#e65100; line-height:1.6;">';
        echo '<li>This will replace all plugin files</li>';
        echo '<li>Configuration files (sync_config.php) will be preserved</li>';
        echo '<li>Event data will not be affected</li>';
        echo '<li>Backup will be saved to: <code style="font-size:10px;">calendar.backup.vX.X.X.YYYY-MM-DD_HH-MM-SS.zip</code></li>';
        echo '<li>Make sure the ZIP file is a valid calendar plugin</li>';
        echo '</ul>';
        echo '</div>';
        
        echo '</div>'; // End flex container
        
        // Changelog section - Timeline viewer
        echo '<div style="background:' . $colors['bg'] . '; padding:12px; margin:10px 0; border-left:3px solid #00cc07; border-radius:3px; max-width:1200px;">';
        echo '<h3 style="margin:0 0 8px 0; color:#00cc07; font-size:16px;">📋 Version History</h3>';
        
        $changelogFile = DOKU_PLUGIN . 'calendar/CHANGELOG.md';
        if (file_exists($changelogFile)) {
            $changelog = file_get_contents($changelogFile);
            
            // Parse ALL versions into structured data
            $lines = explode("\n", $changelog);
            $versions = [];
            $currentVersion = null;
            $currentSubsection = '';
            
            foreach ($lines as $line) {
                $trimmed = trim($line);
                
                // Version header (## Version X.X.X or ## Version X.X.X (date) - title)
                if (preg_match('/^## Version (.+?)(?:\s*\(([^)]+)\))?\s*(?:-\s*(.+))?$/', $trimmed, $matches)) {
                    if ($currentVersion !== null) {
                        $versions[] = $currentVersion;
                    }
                    $currentVersion = [
                        'number' => trim($matches[1]),
                        'date' => isset($matches[2]) ? trim($matches[2]) : '',
                        'title' => isset($matches[3]) ? trim($matches[3]) : '',
                        'items' => []
                    ];
                    $currentSubsection = '';
                }
                // Subsection header (### Something)
                elseif ($currentVersion !== null && preg_match('/^### (.+)$/', $trimmed, $matches)) {
                    $currentSubsection = trim($matches[1]);
                    $currentVersion['items'][] = [
                        'type' => 'section',
                        'desc' => $currentSubsection
                    ];
                }
                // Formatted item (- **Type:** description)
                elseif ($currentVersion !== null && preg_match('/^- \*\*(.+?):\*\*\s*(.+)$/', $trimmed, $matches)) {
                    $currentVersion['items'][] = [
                        'type' => trim($matches[1]),
                        'desc' => trim($matches[2])
                    ];
                }
                // Plain bullet item (- something)
                elseif ($currentVersion !== null && preg_match('/^- (.+)$/', $trimmed, $matches)) {
                    $currentVersion['items'][] = [
                        'type' => $currentSubsection ?: 'Changed',
                        'desc' => trim($matches[1])
                    ];
                }
            }
            // Don't forget last version
            if ($currentVersion !== null) {
                $versions[] = $currentVersion;
            }
            
            $totalVersions = count($versions);
            $uniqueId = 'changelog_' . substr(md5(microtime()), 0, 6);
            
            // Find the index of the currently running version
            $runningVersion = trim($info['version']);
            $runningIndex = 0;
            foreach ($versions as $idx => $ver) {
                if (trim($ver['number']) === $runningVersion) {
                    $runningIndex = $idx;
                    break;
                }
            }
            
            if ($totalVersions > 0) {
                // Timeline navigation bar
                echo '<div id="' . $uniqueId . '_wrap" style="position:relative;">';
                
                // Nav controls
                echo '<div style="display:flex; align-items:center; gap:8px; margin-bottom:10px;">';
                echo '<button id="' . $uniqueId . '_prev" onclick="changelogNav(\'' . $uniqueId . '\', -1)" style="background:none; border:1px solid ' . $colors['border'] . '; color:' . $colors['text'] . '; width:32px; height:32px; border-radius:50%; cursor:pointer; font-size:16px; display:flex; align-items:center; justify-content:center; transition:all 0.15s;" onmouseover="this.style.borderColor=\'#00cc07\'; this.style.color=\'#00cc07\'" onmouseout="this.style.borderColor=\'' . $colors['border'] . '\'; this.style.color=\'' . $colors['text'] . '\'">‹</button>';
                echo '<div style="flex:1; text-align:center; display:flex; align-items:center; justify-content:center; gap:10px;">';
                echo '<span id="' . $uniqueId . '_counter" style="font-size:11px; color:' . $colors['text'] . '; opacity:0.7;">1 of ' . $totalVersions . '</span>';
                echo '<button id="' . $uniqueId . '_current" onclick="changelogJumpTo(\'' . $uniqueId . '\', ' . $runningIndex . ')" style="background:#00cc07; border:none; color:#fff; padding:3px 10px; border-radius:3px; cursor:pointer; font-size:10px; font-weight:600; letter-spacing:0.3px; transition:all 0.15s;" onmouseover="this.style.filter=\'brightness(1.2)\'" onmouseout="this.style.filter=\'none\'">Current Release</button>';
                echo '</div>';
                echo '<button id="' . $uniqueId . '_next" onclick="changelogNav(\'' . $uniqueId . '\', 1)" style="background:none; border:1px solid ' . $colors['border'] . '; color:' . $colors['text'] . '; width:32px; height:32px; border-radius:50%; cursor:pointer; font-size:16px; display:flex; align-items:center; justify-content:center; transition:all 0.15s;" onmouseover="this.style.borderColor=\'#00cc07\'; this.style.color=\'#00cc07\'" onmouseout="this.style.borderColor=\'' . $colors['border'] . '\'; this.style.color=\'' . $colors['text'] . '\'">›</button>';
                echo '</div>';
                
                // Version cards (one per version, only first visible)
                foreach ($versions as $i => $ver) {
                    $display = ($i === 0) ? 'block' : 'none';
                    $isRunning = (trim($ver['number']) === $runningVersion);
                    $cardBorder = $isRunning ? '2px solid #00cc07' : '1px solid ' . $colors['border'];
                    echo '<div class="' . $uniqueId . '_card" id="' . $uniqueId . '_card_' . $i . '" style="display:' . $display . '; padding:10px; background:' . $colors['bg'] . '; border:' . $cardBorder . '; border-left:3px solid #00cc07; border-radius:4px; transition:opacity 0.2s;">';
                    
                    // Version header
                    echo '<div style="display:flex; align-items:baseline; gap:8px; margin-bottom:8px;">';
                    echo '<span style="font-weight:bold; color:#00cc07; font-size:14px;">v' . hsc($ver['number']) . '</span>';
                    if ($isRunning) {
                        echo '<span style="background:#00cc07; color:#fff; padding:1px 6px; border-radius:3px; font-size:9px; font-weight:700; letter-spacing:0.3px;">RUNNING</span>';
                    }
                    if ($ver['date']) {
                        echo '<span style="font-size:11px; color:' . $colors['text'] . '; opacity:0.6;">' . hsc($ver['date']) . '</span>';
                    }
                    echo '</div>';
                    if ($ver['title']) {
                        echo '<div style="font-size:12px; font-weight:600; color:' . $colors['text'] . '; margin-bottom:8px;">' . hsc($ver['title']) . '</div>';
                    }
                    
                    // Change items
                    if (!empty($ver['items'])) {
                        echo '<div style="font-size:12px; line-height:1.7;">';
                        foreach ($ver['items'] as $item) {
                            if ($item['type'] === 'section') {
                                echo '<div style="margin:6px 0 2px 0; font-weight:700; color:#00cc07; font-size:11px; letter-spacing:0.3px;">' . hsc($item['desc']) . '</div>';
                                continue;
                            }
                            $color = '#666'; $icon = '•';
                            $t = $item['type'];
                            if ($t === 'Added' || $t === 'New') { $color = '#28a745'; $icon = '✨'; }
                            elseif ($t === 'Fixed' || $t === 'Fix' || $t === 'Bug Fix') { $color = '#dc3545'; $icon = '🔧'; }
                            elseif ($t === 'Changed' || $t === 'Change') { $color = '#00cc07'; $icon = '🔄'; }
                            elseif ($t === 'Improved' || $t === 'Enhancement') { $color = '#ff9800'; $icon = '⚡'; }
                            elseif ($t === 'Removed') { $color = '#e91e63'; $icon = '🗑️'; }
                            elseif ($t === 'Development' || $t === 'Refactored') { $color = '#6c757d'; $icon = '🛠️'; }
                            elseif ($t === 'Result') { $color = '#2196f3'; $icon = '✅'; }
                            else { $color = $colors['text']; $icon = '•'; }
                            
                            echo '<div style="margin:2px 0; padding-left:4px;">';
                            echo '<span style="color:' . $color . '; font-weight:600;">' . $icon . ' ' . hsc($item['type']) . ':</span> ';
                            echo '<span style="color:' . $colors['text'] . ';">' . hsc($item['desc']) . '</span>';
                            echo '</div>';
                        }
                        echo '</div>';
                    } else {
                        echo '<div style="font-size:11px; color:' . $colors['text'] . '; opacity:0.5; font-style:italic;">No detailed changes recorded</div>';
                    }
                    
                    echo '</div>';
                }
                
                echo '</div>'; // wrap
                
                // JavaScript for navigation
                echo '<script>
                (function() {
                    var id = "' . $uniqueId . '";
                    var total = ' . $totalVersions . ';
                    var current = 0;
                    
                    function showCard(idx) {
                        // Hide current
                        var curCard = document.getElementById(id + "_card_" + current);
                        if (curCard) curCard.style.display = "none";
                        
                        // Show target
                        current = idx;
                        var nextCard = document.getElementById(id + "_card_" + current);
                        if (nextCard) nextCard.style.display = "block";
                        
                        // Update counter
                        var counter = document.getElementById(id + "_counter");
                        if (counter) counter.textContent = (current + 1) + " of " + total;
                        
                        // Update button states
                        var prevBtn = document.getElementById(id + "_prev");
                        var nextBtn = document.getElementById(id + "_next");
                        if (prevBtn) prevBtn.style.opacity = (current === 0) ? "0.3" : "1";
                        if (nextBtn) nextBtn.style.opacity = (current === total - 1) ? "0.3" : "1";
                    }
                    
                    window.changelogNav = function(uid, dir) {
                        if (uid !== id) return;
                        var next = current + dir;
                        if (next < 0 || next >= total) return;
                        showCard(next);
                    };
                    
                    window.changelogJumpTo = function(uid, idx) {
                        if (uid !== id) return;
                        if (idx < 0 || idx >= total) return;
                        showCard(idx);
                    };
                    
                    // Initialize button states
                    var prevBtn = document.getElementById(id + "_prev");
                    if (prevBtn) prevBtn.style.opacity = "0.3";
                })();
                </script>';
                
            } else {
                echo '<p style="color:#999; font-size:13px; font-style:italic;">No versions found in changelog</p>';
            }
        } else {
            echo '<p style="color:#999; font-size:13px; font-style:italic;">Changelog not available</p>';
        }
        
        echo '</div>';
        
        // Backup list or manual backup section
        $backupDir = DOKU_PLUGIN;
        $backups = glob($backupDir . 'calendar*.zip');
        
        // Filter to only show files that look like backups (not the uploaded plugin files)
        $backups = array_filter($backups, function($file) {
            $name = basename($file);
            // Include files that start with "calendar" but exclude files that are just "calendar.zip" (uploaded plugin)
            return $name !== 'calendar.zip';
        });
        
        // Always show backup section (even if no backups yet)
        echo '<div id="backupSection" style="background:' . $colors['bg'] . '; padding:12px; margin:10px 0; border-left:3px solid #00cc07; border-radius:3px; max-width:900px;">';
        echo '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">';
        echo '<h3 style="margin:0; color:#00cc07; font-size:16px;">📁 Backups</h3>';
        
        // Manual backup button
        echo '<form method="post" action="?do=admin&page=calendar&tab=update" style="margin:0;">';
        echo '<input type="hidden" name="action" value="create_manual_backup">';
        echo '<button type="submit" onclick="return confirm(\'Create a backup of the current plugin version?\')" style="background:#00cc07; color:white; padding:6px 12px; border:none; border-radius:3px; cursor:pointer; font-size:12px; font-weight:bold;">💾 Create Backup Now</button>';
        echo '</form>';
        echo '</div>';
        
        if (!empty($backups)) {
            rsort($backups); // Newest first
            echo '<div style="max-height:200px; overflow-y:auto; border:1px solid ' . $colors['border'] . '; border-radius:3px; background:' . $colors['bg'] . ';">';
            echo '<table id="backupTable" style="width:100%; border-collapse:collapse; font-size:12px;">';
            echo '<thead style="position:sticky; top:0; background:#e9e9e9;">';
            echo '<tr>';
            echo '<th style="padding:6px; text-align:left; border-bottom:2px solid ' . $colors['border'] . ';">Backup File</th>';
            echo '<th style="padding:6px; text-align:left; border-bottom:2px solid ' . $colors['border'] . ';">Size</th>';
            echo '<th style="padding:6px; text-align:left; border-bottom:2px solid ' . $colors['border'] . ';">Actions</th>';
            echo '</tr></thead><tbody>';
            
            foreach ($backups as $backup) {
                $filename = basename($backup);
                $size = $this->formatBytes(filesize($backup));
                echo '<tr style="border-bottom:1px solid #eee;">';
                echo '<td style="padding:6px;"><code style="font-size:11px;">' . hsc($filename) . '</code></td>';
                echo '<td style="padding:6px;">' . $size . '</td>';
                echo '<td style="padding:6px; white-space:nowrap;">';
                echo '<a href="' . DOKU_BASE . 'lib/plugins/' . hsc($filename) . '" download style="color:#00cc07; text-decoration:none; font-size:11px; margin-right:10px;">📥 Download</a>';
                echo '<button onclick="renameBackup(\'' . hsc(addslashes($filename)) . '\')" style="background:#f39c12; color:white; border:none; padding:2px 6px; border-radius:2px; cursor:pointer; font-size:10px; margin-right:5px;">✏️ Rename</button>';
                echo '<button onclick="restoreBackup(\'' . hsc(addslashes($filename)) . '\')" style="background:#7b1fa2; color:white; border:none; padding:2px 6px; border-radius:2px; cursor:pointer; font-size:10px; margin-right:5px;">🔄 Restore</button>';
                echo '<button onclick="deleteBackup(\'' . hsc(addslashes($filename)) . '\')" style="background:#e74c3c; color:white; border:none; padding:2px 6px; border-radius:2px; cursor:pointer; font-size:10px;">🗑️ Delete</button>';
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
            echo '</div>';
        } else {
            echo '<p style="color:' . $colors['text'] . '; font-size:13px; margin:8px 0;">No backups yet. Click "Create Backup Now" to create your first backup.</p>';
        }
        echo '</div>';
        
        echo '<script>
        function confirmUpload() {
            const fileInput = document.querySelector(\'input[name="plugin_zip"]\');
            if (!fileInput.files[0]) {
                alert("Please select a ZIP file");
                return false;
            }
            
            const fileName = fileInput.files[0].name;
            if (!fileName.endsWith(".zip")) {
                alert("Please select a ZIP file");
                return false;
            }
            
            return confirm("Upload and install: " + fileName + "?\\n\\nThis will replace all plugin files.\\nYour configuration and data will be preserved.\\n\\nContinue?");
        }
        
        function deleteBackup(filename) {
            if (!confirm("Delete backup: " + filename + "?\\n\\nThis cannot be undone!")) {
                return;
            }
            
            // Use AJAX to delete without page refresh
            const formData = new FormData();
            formData.append(\'action\', \'delete_backup\');
            formData.append(\'backup_file\', filename);
            
            fetch(\'?do=admin&page=calendar&tab=update\', {
                method: \'POST\',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                // Remove the row from the table
                const rows = document.querySelectorAll(\'tr\');
                rows.forEach(row => {
                    if (row.textContent.includes(filename)) {
                        row.style.transition = \'opacity 0.3s\';
                        row.style.opacity = \'0\';
                        setTimeout(() => {
                            row.remove();
                            // Check if table is now empty
                            const tbody = document.querySelector(\'#backupTable tbody\');
                            if (tbody && tbody.children.length === 0) {
                                const backupSection = document.querySelector(\'#backupSection\');
                                if (backupSection) {
                                    backupSection.style.transition = \'opacity 0.3s\';
                                    backupSection.style.opacity = \'0\';
                                    setTimeout(() => backupSection.remove(), 300);
                                }
                            }
                        }, 300);
                    }
                });
                
                // Show success message
                const msg = document.createElement(\'div\');
                msg.style.cssText = \'padding:10px; margin:10px 0; border-left:3px solid #28a745; background:#d4edda; border-radius:3px; max-width:900px; transition:opacity 0.3s;\';
                msg.textContent = \'✓ Backup deleted: \' + filename;
                document.querySelector(\'h2\').after(msg);
                setTimeout(() => {
                    msg.style.opacity = \'0\';
                    setTimeout(() => msg.remove(), 300);
                }, 3000);
            })
            .catch(error => {
                alert(\'Error deleting backup: \' + error);
            });
        }
        
        function restoreBackup(filename) {
            if (!confirm("Restore from backup: " + filename + "?\\n\\nThis will replace all current plugin files with the backup version.\\nYour current configuration will be replaced with the backed up configuration.\\n\\nContinue?")) {
                return;
            }
            
            const form = document.createElement("form");
            form.method = "POST";
            form.action = "?do=admin&page=calendar&tab=update";
            
            const actionInput = document.createElement("input");
            actionInput.type = "hidden";
            actionInput.name = "action";
            actionInput.value = "restore_backup";
            form.appendChild(actionInput);
            
            const filenameInput = document.createElement("input");
            filenameInput.type = "hidden";
            filenameInput.name = "backup_file";
            filenameInput.value = filename;
            form.appendChild(filenameInput);
            
            document.body.appendChild(form);
            form.submit();
        }
        
        function renameBackup(filename) {
            const newName = prompt("Enter new backup name (without .zip extension):\\n\\nCurrent: " + filename.replace(/\\.zip$/, ""), filename.replace(/\\.zip$/, ""));
            if (!newName || newName === filename.replace(/\\.zip$/, "")) {
                return;
            }
            
            // Add .zip if not present
            const newFilename = newName.endsWith(".zip") ? newName : newName + ".zip";
            
            // Basic validation
            if (!/^[a-zA-Z0-9._-]+$/.test(newFilename.replace(/\\.zip$/, ""))) {
                alert("Invalid filename. Use only letters, numbers, dots, dashes, and underscores.");
                return;
            }
            
            const form = document.createElement("form");
            form.method = "POST";
            form.action = "?do=admin&page=calendar&tab=update";
            
            const actionInput = document.createElement("input");
            actionInput.type = "hidden";
            actionInput.name = "action";
            actionInput.value = "rename_backup";
            form.appendChild(actionInput);
            
            const oldNameInput = document.createElement("input");
            oldNameInput.type = "hidden";
            oldNameInput.name = "old_name";
            oldNameInput.value = filename;
            form.appendChild(oldNameInput);
            
            const newNameInput = document.createElement("input");
            newNameInput.type = "hidden";
            newNameInput.name = "new_name";
            newNameInput.value = newFilename;
            form.appendChild(newNameInput);
            
            document.body.appendChild(form);
            form.submit();
        }
        </script>';
    }
    
    private function saveConfig() {
        global $INPUT;
        
        // Load existing config to preserve all settings
        $configFile = DOKU_PLUGIN . 'calendar/sync_config.php';
        $existingConfig = [];
        if (file_exists($configFile)) {
            $existingConfig = include $configFile;
        }
        
        // Update only the fields from the form - preserve everything else
        $config = $existingConfig;
        
        // Update basic fields
        $config['tenant_id'] = $INPUT->str('tenant_id');
        $config['client_id'] = $INPUT->str('client_id');
        $config['client_secret'] = $INPUT->str('client_secret');
        $config['user_email'] = $INPUT->str('user_email');
        $config['timezone'] = $INPUT->str('timezone', 'America/Los_Angeles');
        $config['default_category'] = $INPUT->str('default_category', 'Blue category');
        $config['reminder_minutes'] = $INPUT->int('reminder_minutes', 15);
        $config['sync_completed_tasks'] = $INPUT->bool('sync_completed_tasks');
        $config['delete_outlook_events'] = $INPUT->bool('delete_outlook_events');
        $config['sync_all_namespaces'] = $INPUT->bool('sync_all_namespaces');
        $config['sync_namespaces'] = $INPUT->arr('sync_namespaces');
        // important_namespaces is managed from the Manage tab, preserve existing value
        if (!isset($config['important_namespaces'])) {
            $config['important_namespaces'] = 'important';
        }
        
        // Parse category mapping
        $config['category_mapping'] = [];
        $mappingText = $INPUT->str('category_mapping');
        if ($mappingText) {
            $lines = explode("\n", $mappingText);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    $config['category_mapping'][trim($parts[0])] = trim($parts[1]);
                }
            }
        }
        
        // Parse color mapping from dropdown selections
        $config['color_mapping'] = [];
        $colorMappingCount = $INPUT->int('color_mapping_count', 0);
        for ($i = 0; $i < $colorMappingCount; $i++) {
            $hexColor = $INPUT->str('color_hex_' . $i);
            $category = $INPUT->str('color_map_' . $i);
            
            if (!empty($hexColor) && !empty($category)) {
                $config['color_mapping'][$hexColor] = $category;
            }
        }
        
        // Build file content using return format
        $content = "<?php\n";
        $content .= "/**\n";
        $content .= " * DokuWiki Calendar → Outlook Sync - Configuration\n";
        $content .= " * \n";
        $content .= " * SECURITY: Add this file to .gitignore!\n";
        $content .= " * Never commit credentials to version control.\n";
        $content .= " */\n\n";
        $content .= "return " . var_export($config, true) . ";\n";
        
        // Save file
        if (file_put_contents($configFile, $content)) {
            $this->redirect('Configuration saved successfully!', 'success');
        } else {
            $this->redirect('Error: Could not save configuration file', 'error');
        }
    }
    
    private function clearCache() {
        // Clear DokuWiki cache
        $cacheDir = DOKU_INC . 'data/cache';
        
        if (is_dir($cacheDir)) {
            $this->recursiveDelete($cacheDir, false);
            $this->redirect('Cache cleared successfully!', 'success', 'update');
        } else {
            $this->redirect('Cache directory not found', 'error', 'update');
        }
    }
    
    private function recursiveDelete($dir, $deleteRoot = true) {
        if (!is_dir($dir)) return;
        
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->recursiveDelete($path, true);
            } else {
                @unlink($path);
            }
        }
        
        if ($deleteRoot) {
            @rmdir($dir);
        }
    }
    
    private function findRecurringEvents() {
        $dataDir = DOKU_INC . 'data/meta/';
        $recurring = [];
        $allEvents = []; // Track all events to detect patterns
        $flaggedSeries = []; // Track events with recurring flag by recurringId
        
        // Helper to process events from a calendar directory
        $processCalendarDir = function($calDir, $fallbackNamespace) use (&$allEvents, &$flaggedSeries) {
            if (!is_dir($calDir)) return;
            
            foreach (glob($calDir . '/*.json') as $file) {
                $data = json_decode(file_get_contents($file), true);
                if (!$data || !is_array($data)) continue;
                
                foreach ($data as $dateKey => $events) {
                    if (!is_array($events)) continue;
                    foreach ($events as $event) {
                        if (!isset($event['title']) || empty(trim($event['title']))) continue;
                        
                        $ns = isset($event['namespace']) ? $event['namespace'] : $fallbackNamespace;
                        
                        // If event has recurring flag, group by recurringId
                        if (!empty($event['recurring']) && !empty($event['recurringId'])) {
                            $rid = $event['recurringId'];
                            if (!isset($flaggedSeries[$rid])) {
                                $flaggedSeries[$rid] = [
                                    'title' => $event['title'],
                                    'namespace' => $ns,
                                    'dates' => [],
                                    'events' => []
                                ];
                            }
                            $flaggedSeries[$rid]['dates'][] = $dateKey;
                            $flaggedSeries[$rid]['events'][] = $event;
                        }
                        
                        // Also group by title+namespace for pattern detection
                        $groupKey = strtolower(trim($event['title'])) . '|' . $ns;
                        
                        if (!isset($allEvents[$groupKey])) {
                            $allEvents[$groupKey] = [
                                'title' => $event['title'],
                                'namespace' => $ns,
                                'dates' => [],
                                'events' => [],
                                'hasFlag' => false
                            ];
                        }
                        $allEvents[$groupKey]['dates'][] = $dateKey;
                        $allEvents[$groupKey]['events'][] = $event;
                        if (!empty($event['recurring'])) {
                            $allEvents[$groupKey]['hasFlag'] = true;
                        }
                    }
                }
            }
        };
        
        // Check root calendar directory (blank/default namespace)
        $processCalendarDir($dataDir . 'calendar', '');
        
        // Scan all namespace directories (including nested)
        $this->scanNamespaceDirs($dataDir, $processCalendarDir);
        
        // Deduplicate: remove from allEvents groups that are fully covered by flaggedSeries
        $flaggedTitleNs = [];
        foreach ($flaggedSeries as $rid => $series) {
            $key = strtolower(trim($series['title'])) . '|' . $series['namespace'];
            $flaggedTitleNs[$key] = $rid;
        }
        
        // Build results from flaggedSeries first (known recurring)
        $seen = [];
        foreach ($flaggedSeries as $rid => $series) {
            sort($series['dates']);
            $dedupDates = array_unique($series['dates']);
            
            $pattern = $this->detectRecurrencePattern($dedupDates);
            
            $recurring[] = [
                'baseId' => $rid,
                'title' => $series['title'],
                'namespace' => $series['namespace'],
                'pattern' => $pattern,
                'count' => count($dedupDates),
                'firstDate' => $dedupDates[0],
                'hasFlag' => true
            ];
            $seen[strtolower(trim($series['title'])) . '|' . $series['namespace']] = true;
        }
        
        // Add pattern-detected recurring (3+ occurrences, not already in flaggedSeries)
        foreach ($allEvents as $groupKey => $group) {
            if (isset($seen[$groupKey])) continue;
            
            $dedupDates = array_unique($group['dates']);
            sort($dedupDates);
            
            if (count($dedupDates) < 3) continue;
            
            $pattern = $this->detectRecurrencePattern($dedupDates);
            
            $baseId = isset($group['events'][0]['recurringId']) 
                ? $group['events'][0]['recurringId'] 
                : md5($group['title'] . $group['namespace']);
            
            $recurring[] = [
                'baseId' => $baseId,
                'title' => $group['title'],
                'namespace' => $group['namespace'],
                'pattern' => $pattern,
                'count' => count($dedupDates),
                'firstDate' => $dedupDates[0],
                'hasFlag' => $group['hasFlag']
            ];
        }
        
        // Sort by title
        usort($recurring, function($a, $b) {
            return strcasecmp($a['title'], $b['title']);
        });
        
        return $recurring;
    }
    
    /**
     * Recursively scan namespace directories for calendar data
     */
    private function scanNamespaceDirs($baseDir, $callback) {
        foreach (glob($baseDir . '*', GLOB_ONLYDIR) as $nsDir) {
            $namespace = basename($nsDir);
            
            // Skip the root 'calendar' dir (already processed)
            if ($namespace === 'calendar') continue;
            
            $calendarDir = $nsDir . '/calendar';
            if (is_dir($calendarDir)) {
                // Derive namespace from path relative to meta dir
                $metaDir = DOKU_INC . 'data/meta/';
                $relPath = str_replace($metaDir, '', $nsDir);
                $ns = str_replace('/', ':', trim($relPath, '/'));
                $callback($calendarDir, $ns);
            }
            
            // Recurse into subdirectories for nested namespaces
            $this->scanNamespaceDirs($nsDir . '/', $callback);
        }
    }
    
    /**
     * Detect recurrence pattern from sorted dates using median interval
     */
    private function detectRecurrencePattern($dates) {
        if (count($dates) < 2) return 'Single';
        
        // Calculate all intervals between consecutive dates
        $intervals = [];
        for ($i = 1; $i < count($dates); $i++) {
            try {
                $d1 = new DateTime($dates[$i - 1]);
                $d2 = new DateTime($dates[$i]);
                $intervals[] = $d1->diff($d2)->days;
            } catch (Exception $e) {
                continue;
            }
        }
        
        if (empty($intervals)) return 'Custom';
        
        // Use median interval (more robust than first pair)
        sort($intervals);
        $mid = floor(count($intervals) / 2);
        $median = (count($intervals) % 2 === 0) 
            ? ($intervals[$mid - 1] + $intervals[$mid]) / 2 
            : $intervals[$mid];
        
        if ($median <= 1) return 'Daily';
        if ($median >= 6 && $median <= 8) return 'Weekly';
        if ($median >= 13 && $median <= 16) return 'Bi-weekly';
        if ($median >= 27 && $median <= 32) return 'Monthly';
        if ($median >= 89 && $median <= 93) return 'Quarterly';
        if ($median >= 180 && $median <= 186) return 'Semi-annual';
        if ($median >= 363 && $median <= 368) return 'Yearly';
        
        return 'Every ~' . round($median) . ' days';
    }
    
    /**
     * Render the recurring events table HTML
     */
    private function renderRecurringTable($recurringEvents, $colors) {
        if (empty($recurringEvents)) {
            echo '<p style="color:' . $colors['text'] . '; font-size:13px; margin:5px 0;">No recurring events found.</p>';
            return;
        }
        
        // Search bar
        echo '<div style="margin-bottom:8px;">';
        echo '<input type="text" id="searchRecurring" onkeyup="filterRecurringEvents()" placeholder="🔍 Search recurring events..." style="width:100%; padding:6px 10px; border:1px solid ' . $colors['border'] . '; border-radius:3px; font-size:12px;">';
        echo '</div>';
        
        echo '<style>
            .sort-arrow {
                color: #999;
                font-size: 10px;
                margin-left: 3px;
                display: inline-block;
            }
            #recurringTable th:hover {
                background: #ddd;
            }
            #recurringTable th:hover .sort-arrow {
                color: #00cc07;
            }
            .recurring-row-hidden {
                display: none;
            }
        </style>';
        echo '<div style="max-height:250px; overflow-y:auto; border:1px solid ' . $colors['border'] . '; border-radius:3px;">';
        echo '<table id="recurringTable" style="width:100%; border-collapse:collapse; font-size:11px;">';
        echo '<thead style="position:sticky; top:0; background:#e9e9e9;">';
        echo '<tr>';
        echo '<th onclick="sortRecurringTable(0)" style="padding:4px 6px; text-align:left; border-bottom:2px solid #ddd; cursor:pointer; user-select:none;">Title <span class="sort-arrow">⇅</span></th>';
        echo '<th onclick="sortRecurringTable(1)" style="padding:4px 6px; text-align:left; border-bottom:2px solid #ddd; cursor:pointer; user-select:none;">Namespace <span class="sort-arrow">⇅</span></th>';
        echo '<th onclick="sortRecurringTable(2)" style="padding:4px 6px; text-align:left; border-bottom:2px solid #ddd; cursor:pointer; user-select:none;">Pattern <span class="sort-arrow">⇅</span></th>';
        echo '<th onclick="sortRecurringTable(3)" style="padding:4px 6px; text-align:left; border-bottom:2px solid #ddd; cursor:pointer; user-select:none;">First <span class="sort-arrow">⇅</span></th>';
        echo '<th onclick="sortRecurringTable(4)" style="padding:4px 6px; text-align:left; border-bottom:2px solid #ddd; cursor:pointer; user-select:none;">Count <span class="sort-arrow">⇅</span></th>';
        echo '<th onclick="sortRecurringTable(5)" style="padding:4px 6px; text-align:left; border-bottom:2px solid #ddd; cursor:pointer; user-select:none;">Source <span class="sort-arrow">⇅</span></th>';
        echo '<th style="padding:4px 6px; text-align:left; border-bottom:2px solid #ddd;">Actions</th>';
        echo '</tr></thead><tbody id="recurringTableBody">';
        
        foreach ($recurringEvents as $series) {
            $sourceLabel = $series['hasFlag'] ? '🏷️ Flagged' : '🔍 Detected';
            $sourceColor = $series['hasFlag'] ? '#00cc07' : '#ff9800';
            echo '<tr style="border-bottom:1px solid #eee;">';
            echo '<td style="padding:4px 6px;">' . hsc($series['title']) . '</td>';
            echo '<td style="padding:4px 6px;"><code style="background:#f0f0f0; padding:1px 3px; border-radius:2px; font-size:10px;">' . hsc($series['namespace'] ?: '(default)') . '</code></td>';
            echo '<td style="padding:4px 6px;">' . hsc($series['pattern']) . '</td>';
            echo '<td style="padding:4px 6px;">' . hsc($series['firstDate']) . '</td>';
            echo '<td style="padding:4px 6px;"><strong>' . $series['count'] . '</strong></td>';
            echo '<td style="padding:4px 6px;"><span style="color:' . $sourceColor . '; font-size:10px;">' . $sourceLabel . '</span></td>';
            echo '<td style="padding:4px 6px; white-space:nowrap;">';
            $jsTitle = hsc(addslashes($series['title']));
            $jsNs = hsc($series['namespace']);
            $jsCount = $series['count'];
            $jsFirst = hsc($series['firstDate']);
            $jsPattern = hsc($series['pattern']);
            $jsHasFlag = $series['hasFlag'] ? 'true' : 'false';
            echo '<button onclick="editRecurringSeries(\'' . $jsTitle . '\', \'' . $jsNs . '\')" style="background:#00cc07; color:white; border:none; padding:2px 6px; border-radius:2px; cursor:pointer; font-size:10px; margin-right:2px;" title="Edit title, time, namespace, interval">Edit</button>';
            echo '<button onclick="manageRecurringSeries(\'' . $jsTitle . '\', \'' . $jsNs . '\', ' . $jsCount . ', \'' . $jsFirst . '\', \'' . $jsPattern . '\', ' . $jsHasFlag . ')" style="background:#ff9800; color:white; border:none; padding:2px 6px; border-radius:2px; cursor:pointer; font-size:10px; margin-right:2px;" title="Extend, trim, pause, change start">Manage</button>';
            echo '<button onclick="deleteRecurringSeries(\'' . $jsTitle . '\', \'' . $jsNs . '\')" style="background:#e74c3c; color:white; border:none; padding:2px 6px; border-radius:2px; cursor:pointer; font-size:10px;" title="Delete all occurrences">Del</button>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        echo '</div>';
        echo '<p style="color:' . $colors['text'] . '; font-size:10px; margin:5px 0 0;">Total: ' . count($recurringEvents) . ' series</p>';
    }
    
    /**
     * AJAX handler: rescan recurring events and return HTML
     */
    private function handleCleanupEmptyNamespaces() {
        global $INPUT;
        $dryRun = $INPUT->bool('dry_run', false);
        
        $metaDir = DOKU_INC . 'data/meta/';
        $details = [];
        $removedDirs = 0;
        $removedCalDirs = 0;
        
        // 1. Find all calendar/ subdirectories anywhere under data/meta/
        $allCalDirs = [];
        $this->findAllCalendarDirsRecursive($metaDir, $allCalDirs);
        
        // 2. Check each calendar dir for empty JSON files
        foreach ($allCalDirs as $calDir) {
            $jsonFiles = glob($calDir . '/*.json');
            $hasEvents = false;
            
            foreach ($jsonFiles as $jsonFile) {
                $data = json_decode(file_get_contents($jsonFile), true);
                if ($data && is_array($data)) {
                    // Check if any date key has actual events
                    foreach ($data as $dateKey => $events) {
                        if (is_array($events) && !empty($events)) {
                            $hasEvents = true;
                            break 2;
                        }
                    }
                    // JSON file has data but all dates are empty — remove it
                    if (!$dryRun) unlink($jsonFile);
                }
            }
            
            // Re-check after cleaning empty JSON files
            if (!$dryRun) {
                $jsonFiles = glob($calDir . '/*.json');
            }
            
            // Derive display name from path
            $relPath = str_replace($metaDir, '', $calDir);
            $relPath = rtrim(str_replace('/calendar', '', $relPath), '/');
            $displayName = $relPath ?: '(root)';
            
            if ($displayName === '(root)') continue; // Never remove root calendar dir
            
            if (!$hasEvents || empty($jsonFiles)) {
                $removedCalDirs++;
                $details[] = "Remove empty calendar folder: " . $displayName . "/calendar/ (0 events)";
                
                if (!$dryRun) {
                    // Remove all remaining files in calendar dir
                    foreach (glob($calDir . '/*') as $f) {
                        if (is_file($f)) unlink($f);
                    }
                    @rmdir($calDir);
                    
                    // Check if parent namespace dir is now empty too
                    $parentDir = dirname($calDir);
                    if ($parentDir !== $metaDir && is_dir($parentDir)) {
                        $remaining = array_diff(scandir($parentDir), ['.', '..']);
                        if (empty($remaining)) {
                            @rmdir($parentDir);
                            $removedDirs++;
                            $details[] = "Removed empty namespace directory: " . $displayName . "/";
                        }
                    }
                }
            }
        }
        
        // 3. Also scan for namespace dirs that have a calendar/ subdir with 0 json files
        //    (already covered above, but also check for namespace dirs without calendar/ at all
        //    that are tracked in the event system)
        
        $total = $removedCalDirs + $removedDirs;
        $message = $dryRun 
            ? "Found $total item(s) to clean up"
            : "Cleaned up $removedCalDirs empty calendar folder(s)" . ($removedDirs > 0 ? " and $removedDirs empty namespace directory(ies)" : "");
        
        if (!$dryRun) $this->clearStatsCache();
        
        echo json_encode([
            'success' => true,
            'count' => $total,
            'message' => $message,
            'details' => $details
        ]);
    }
    
    /**
     * Recursively find all 'calendar' directories under a base path
     */
    private function findAllCalendarDirsRecursive($baseDir, &$results) {
        $entries = glob($baseDir . '*', GLOB_ONLYDIR);
        if (!$entries) return;
        
        foreach ($entries as $dir) {
            $name = basename($dir);
            if ($name === 'calendar') {
                $results[] = $dir;
            } else {
                // Check for calendar subdir
                if (is_dir($dir . '/calendar')) {
                    $results[] = $dir . '/calendar';
                }
                // Recurse into subdirectories for nested namespaces
                $this->findAllCalendarDirsRecursive($dir . '/', $results);
            }
        }
    }
    
    private function handleTrimAllPastRecurring() {
        global $INPUT;
        $dryRun = $INPUT->bool('dry_run', false);
        $today = date('Y-m-d');
        $dataDir = DOKU_INC . 'data/meta/';
        $calendarDirs = [];
        
        if (is_dir($dataDir . 'calendar')) {
            $calendarDirs[] = $dataDir . 'calendar';
        }
        $this->findCalendarDirs($dataDir, $calendarDirs);
        
        $removed = 0;
        
        foreach ($calendarDirs as $calDir) {
            foreach (glob($calDir . '/*.json') as $file) {
                $data = json_decode(file_get_contents($file), true);
                if (!$data || !is_array($data)) continue;
                
                $modified = false;
                foreach ($data as $dateKey => &$dayEvents) {
                    if ($dateKey >= $today) continue;
                    if (!is_array($dayEvents)) continue;
                    
                    $filtered = [];
                    foreach ($dayEvents as $event) {
                        if (!empty($event['recurring']) || !empty($event['recurringId'])) {
                            $removed++;
                            if (!$dryRun) $modified = true;
                        } else {
                            $filtered[] = $event;
                        }
                    }
                    if (!$dryRun) $dayEvents = $filtered;
                }
                unset($dayEvents);
                
                if (!$dryRun && $modified) {
                    foreach ($data as $dk => $evts) {
                        if (empty($evts)) unset($data[$dk]);
                    }
                    if (empty($data)) {
                        unlink($file);
                    } else {
                        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
                    }
                }
            }
        }
        
        if (!$dryRun) $this->clearStatsCache();
        echo json_encode(['success' => true, 'count' => $removed, 'message' => "Removed $removed past recurring occurrences"]);
    }
    
    private function handleRescanRecurring() {
        $colors = $this->getTemplateColors();
        $recurringEvents = $this->findRecurringEvents();
        
        ob_start();
        $this->renderRecurringTable($recurringEvents, $colors);
        $html = ob_get_clean();
        
        echo json_encode([
            'success' => true,
            'html' => $html,
            'count' => count($recurringEvents)
        ]);
    }
    
    /**
     * Helper: find all events matching a title in a namespace's calendar dir
     */
    private function getRecurringSeriesEvents($title, $namespace) {
        $dataDir = DOKU_INC . 'data/meta/';
        if ($namespace !== '') {
            $dataDir .= str_replace(':', '/', $namespace) . '/';
        }
        $dataDir .= 'calendar/';
        
        $events = []; // ['date' => dateKey, 'file' => filepath, 'event' => eventData, 'index' => idx]
        
        if (!is_dir($dataDir)) return $events;
        
        foreach (glob($dataDir . '*.json') as $file) {
            $data = json_decode(file_get_contents($file), true);
            if (!$data || !is_array($data)) continue;
            
            foreach ($data as $dateKey => $dayEvents) {
                if (!is_array($dayEvents)) continue;
                foreach ($dayEvents as $idx => $event) {
                    if (strtolower(trim($event['title'])) === strtolower(trim($title))) {
                        $events[] = [
                            'date' => $dateKey,
                            'file' => $file,
                            'event' => $event,
                            'index' => $idx
                        ];
                    }
                }
            }
        }
        
        // Sort by date
        usort($events, function($a, $b) {
            return strcmp($a['date'], $b['date']);
        });
        
        return $events;
    }
    
    /**
     * Extend series: add more future occurrences
     */
    private function handleExtendRecurring() {
        global $INPUT;
        $title = $INPUT->str('title');
        $namespace = $INPUT->str('namespace');
        $count = $INPUT->int('count', 4);
        $intervalDays = $INPUT->int('interval_days', 7);
        
        $events = $this->getRecurringSeriesEvents($title, $namespace);
        if (empty($events)) {
            echo json_encode(['success' => false, 'error' => 'Series not found']);
            return;
        }
        
        // Use last event as template
        $lastEvent = end($events);
        $lastDate = new DateTime($lastEvent['date']);
        $template = $lastEvent['event'];
        
        $dataDir = DOKU_INC . 'data/meta/';
        if ($namespace !== '') {
            $dataDir .= str_replace(':', '/', $namespace) . '/';
        }
        $dataDir .= 'calendar/';
        
        if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);
        
        $added = 0;
        $baseId = isset($template['recurringId']) ? $template['recurringId'] : md5($title . $namespace);
        $maxExistingIdx = 0;
        foreach ($events as $e) {
            if (isset($e['event']['id']) && preg_match('/-(\d+)$/', $e['event']['id'], $m)) {
                $maxExistingIdx = max($maxExistingIdx, (int)$m[1]);
            }
        }
        
        for ($i = 1; $i <= $count; $i++) {
            $newDate = clone $lastDate;
            $newDate->modify('+' . ($i * $intervalDays) . ' days');
            $dateKey = $newDate->format('Y-m-d');
            list($year, $month) = explode('-', $dateKey);
            
            $file = $dataDir . sprintf('%04d-%02d.json', $year, $month);
            $fileData = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
            if (!is_array($fileData)) $fileData = [];
            
            if (!isset($fileData[$dateKey])) $fileData[$dateKey] = [];
            
            $newEvent = $template;
            $newEvent['id'] = $baseId . '-' . ($maxExistingIdx + $i);
            $newEvent['recurring'] = true;
            $newEvent['recurringId'] = $baseId;
            $newEvent['created'] = date('Y-m-d H:i:s');
            unset($newEvent['completed']);
            $newEvent['completed'] = false;
            
            $fileData[$dateKey][] = $newEvent;
            file_put_contents($file, json_encode($fileData, JSON_PRETTY_PRINT));
            $added++;
        }
        
        $this->clearStatsCache();
        echo json_encode(['success' => true, 'message' => "Added $added new occurrences"]);
    }
    
    /**
     * Trim series: remove past occurrences before a cutoff date
     */
    private function handleTrimRecurring() {
        global $INPUT;
        $title = $INPUT->str('title');
        $namespace = $INPUT->str('namespace');
        $cutoffDate = $INPUT->str('cutoff_date', date('Y-m-d'));
        
        $events = $this->getRecurringSeriesEvents($title, $namespace);
        $removed = 0;
        
        foreach ($events as $entry) {
            if ($entry['date'] < $cutoffDate) {
                // Remove this event from its file
                $data = json_decode(file_get_contents($entry['file']), true);
                if (!$data || !isset($data[$entry['date']])) continue;
                
                // Find and remove by matching title
                foreach ($data[$entry['date']] as $k => $evt) {
                    if (strtolower(trim($evt['title'])) === strtolower(trim($title))) {
                        unset($data[$entry['date']][$k]);
                        $data[$entry['date']] = array_values($data[$entry['date']]);
                        $removed++;
                        break;
                    }
                }
                
                // Clean up empty dates
                if (empty($data[$entry['date']])) unset($data[$entry['date']]);
                
                if (empty($data)) {
                    unlink($entry['file']);
                } else {
                    file_put_contents($entry['file'], json_encode($data, JSON_PRETTY_PRINT));
                }
            }
        }
        
        $this->clearStatsCache();
        echo json_encode(['success' => true, 'message' => "Removed $removed past occurrences before $cutoffDate"]);
    }
    
    /**
     * Pause series: mark all future occurrences as paused
     */
    private function handlePauseRecurring() {
        global $INPUT;
        $title = $INPUT->str('title');
        $namespace = $INPUT->str('namespace');
        $today = date('Y-m-d');
        
        $events = $this->getRecurringSeriesEvents($title, $namespace);
        $paused = 0;
        
        foreach ($events as $entry) {
            if ($entry['date'] >= $today) {
                $data = json_decode(file_get_contents($entry['file']), true);
                if (!$data || !isset($data[$entry['date']])) continue;
                
                foreach ($data[$entry['date']] as $k => &$evt) {
                    if (strtolower(trim($evt['title'])) === strtolower(trim($title))) {
                        $evt['paused'] = true;
                        $evt['title'] = '⏸ ' . preg_replace('/^⏸\s*/', '', $evt['title']);
                        $paused++;
                        break;
                    }
                }
                unset($evt);
                
                file_put_contents($entry['file'], json_encode($data, JSON_PRETTY_PRINT));
            }
        }
        
        $this->clearStatsCache();
        echo json_encode(['success' => true, 'message' => "Paused $paused future occurrences"]);
    }
    
    /**
     * Resume series: unmark paused occurrences
     */
    private function handleResumeRecurring() {
        global $INPUT;
        $title = $INPUT->str('title');
        $namespace = $INPUT->str('namespace');
        
        // Search for both paused and non-paused versions
        $dataDir = DOKU_INC . 'data/meta/';
        if ($namespace !== '') {
            $dataDir .= str_replace(':', '/', $namespace) . '/';
        }
        $dataDir .= 'calendar/';
        
        $resumed = 0;
        $cleanTitle = preg_replace('/^⏸\s*/', '', $title);
        
        if (!is_dir($dataDir)) {
            echo json_encode(['success' => false, 'error' => 'Directory not found']);
            return;
        }
        
        foreach (glob($dataDir . '*.json') as $file) {
            $data = json_decode(file_get_contents($file), true);
            if (!$data) continue;
            
            $modified = false;
            foreach ($data as $dateKey => &$dayEvents) {
                foreach ($dayEvents as $k => &$evt) {
                    $evtCleanTitle = preg_replace('/^⏸\s*/', '', $evt['title']);
                    if (strtolower(trim($evtCleanTitle)) === strtolower(trim($cleanTitle)) && 
                        (!empty($evt['paused']) || strpos($evt['title'], '⏸') === 0)) {
                        $evt['paused'] = false;
                        $evt['title'] = $cleanTitle;
                        $resumed++;
                        $modified = true;
                    }
                }
                unset($evt);
            }
            unset($dayEvents);
            
            if ($modified) {
                file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
            }
        }
        
        $this->clearStatsCache();
        echo json_encode(['success' => true, 'message' => "Resumed $resumed occurrences"]);
    }
    
    /**
     * Change start date: shift all occurrences by an offset
     */
    private function handleChangeStartRecurring() {
        global $INPUT;
        $title = $INPUT->str('title');
        $namespace = $INPUT->str('namespace');
        $newStartDate = $INPUT->str('new_start_date');
        
        if (empty($newStartDate)) {
            echo json_encode(['success' => false, 'error' => 'No start date provided']);
            return;
        }
        
        $events = $this->getRecurringSeriesEvents($title, $namespace);
        if (empty($events)) {
            echo json_encode(['success' => false, 'error' => 'Series not found']);
            return;
        }
        
        // Calculate offset from old first date to new first date
        $oldFirst = new DateTime($events[0]['date']);
        $newFirst = new DateTime($newStartDate);
        $offsetDays = (int)$oldFirst->diff($newFirst)->format('%r%a');
        
        if ($offsetDays === 0) {
            echo json_encode(['success' => true, 'message' => 'Start date unchanged']);
            return;
        }
        
        $dataDir = DOKU_INC . 'data/meta/';
        if ($namespace !== '') {
            $dataDir .= str_replace(':', '/', $namespace) . '/';
        }
        $dataDir .= 'calendar/';
        
        // Collect all events to move
        $toMove = [];
        foreach ($events as $entry) {
            $oldDate = new DateTime($entry['date']);
            $newDate = clone $oldDate;
            $newDate->modify(($offsetDays > 0 ? '+' : '') . $offsetDays . ' days');
            
            $toMove[] = [
                'oldDate' => $entry['date'],
                'newDate' => $newDate->format('Y-m-d'),
                'event' => $entry['event'],
                'file' => $entry['file']
            ];
        }
        
        // Remove all from old positions
        foreach ($toMove as $move) {
            $data = json_decode(file_get_contents($move['file']), true);
            if (!$data || !isset($data[$move['oldDate']])) continue;
            
            foreach ($data[$move['oldDate']] as $k => $evt) {
                if (strtolower(trim($evt['title'])) === strtolower(trim($title))) {
                    unset($data[$move['oldDate']][$k]);
                    $data[$move['oldDate']] = array_values($data[$move['oldDate']]);
                    break;
                }
            }
            if (empty($data[$move['oldDate']])) unset($data[$move['oldDate']]);
            if (empty($data)) {
                unlink($move['file']);
            } else {
                file_put_contents($move['file'], json_encode($data, JSON_PRETTY_PRINT));
            }
        }
        
        // Add to new positions
        $moved = 0;
        foreach ($toMove as $move) {
            list($year, $month) = explode('-', $move['newDate']);
            $file = $dataDir . sprintf('%04d-%02d.json', $year, $month);
            $data = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
            if (!is_array($data)) $data = [];
            
            if (!isset($data[$move['newDate']])) $data[$move['newDate']] = [];
            $data[$move['newDate']][] = $move['event'];
            file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
            $moved++;
        }
        
        $dir = $offsetDays > 0 ? 'forward' : 'back';
        $this->clearStatsCache();
        echo json_encode(['success' => true, 'message' => "Shifted $moved occurrences $dir by " . abs($offsetDays) . " days"]);
    }
    
    /**
     * Change pattern: re-space all future events with a new interval
     */
    private function handleChangePatternRecurring() {
        global $INPUT;
        $title = $INPUT->str('title');
        $namespace = $INPUT->str('namespace');
        $newIntervalDays = $INPUT->int('interval_days', 7);
        
        $events = $this->getRecurringSeriesEvents($title, $namespace);
        $today = date('Y-m-d');
        
        // Split into past and future
        $pastEvents = [];
        $futureEvents = [];
        foreach ($events as $e) {
            if ($e['date'] < $today) {
                $pastEvents[] = $e;
            } else {
                $futureEvents[] = $e;
            }
        }
        
        if (empty($futureEvents)) {
            echo json_encode(['success' => false, 'error' => 'No future occurrences to respace']);
            return;
        }
        
        $dataDir = DOKU_INC . 'data/meta/';
        if ($namespace !== '') {
            $dataDir .= str_replace(':', '/', $namespace) . '/';
        }
        $dataDir .= 'calendar/';
        
        // Use first future event as anchor
        $anchorDate = new DateTime($futureEvents[0]['date']);
        
        // Remove all future events from files
        foreach ($futureEvents as $entry) {
            $data = json_decode(file_get_contents($entry['file']), true);
            if (!$data || !isset($data[$entry['date']])) continue;
            
            foreach ($data[$entry['date']] as $k => $evt) {
                if (strtolower(trim($evt['title'])) === strtolower(trim($title))) {
                    unset($data[$entry['date']][$k]);
                    $data[$entry['date']] = array_values($data[$entry['date']]);
                    break;
                }
            }
            if (empty($data[$entry['date']])) unset($data[$entry['date']]);
            if (empty($data)) {
                unlink($entry['file']);
            } else {
                file_put_contents($entry['file'], json_encode($data, JSON_PRETTY_PRINT));
            }
        }
        
        // Re-create with new spacing
        $template = $futureEvents[0]['event'];
        $baseId = isset($template['recurringId']) ? $template['recurringId'] : md5($title . $namespace);
        $count = count($futureEvents);
        $created = 0;
        
        for ($i = 0; $i < $count; $i++) {
            $newDate = clone $anchorDate;
            $newDate->modify('+' . ($i * $newIntervalDays) . ' days');
            $dateKey = $newDate->format('Y-m-d');
            list($year, $month) = explode('-', $dateKey);
            
            $file = $dataDir . sprintf('%04d-%02d.json', $year, $month);
            $fileData = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
            if (!is_array($fileData)) $fileData = [];
            
            if (!isset($fileData[$dateKey])) $fileData[$dateKey] = [];
            
            $newEvent = $template;
            $newEvent['id'] = $baseId . '-respace-' . $i;
            $newEvent['recurring'] = true;
            $newEvent['recurringId'] = $baseId;
            
            $fileData[$dateKey][] = $newEvent;
            file_put_contents($file, json_encode($fileData, JSON_PRETTY_PRINT));
            $created++;
        }
        
        $this->clearStatsCache();
        $patternName = $this->intervalToPattern($newIntervalDays);
        echo json_encode(['success' => true, 'message' => "Respaced $created future occurrences to $patternName ($newIntervalDays days)"]);
    }
    
    private function intervalToPattern($days) {
        if ($days == 1) return 'Daily';
        if ($days == 7) return 'Weekly';
        if ($days == 14) return 'Bi-weekly';
        if ($days >= 28 && $days <= 31) return 'Monthly';
        if ($days >= 89 && $days <= 93) return 'Quarterly';
        if ($days >= 363 && $days <= 368) return 'Yearly';
        return "Every $days days";
    }
    
    private function getEventsByNamespace() {
        $dataDir = DOKU_INC . 'data/meta/';
        $result = [];
        
        // Check root calendar directory first (blank/default namespace)
        $rootCalendarDir = $dataDir . 'calendar';
        if (is_dir($rootCalendarDir)) {
            $hasFiles = false;
            $events = [];
            
            foreach (glob($rootCalendarDir . '/*.json') as $file) {
                $hasFiles = true;
                $month = basename($file, '.json');
                $data = json_decode(file_get_contents($file), true);
                if (!$data) continue;
                
                foreach ($data as $dateKey => $eventList) {
                    foreach ($eventList as $event) {
                        $events[] = [
                            'id' => $event['id'],
                            'title' => $event['title'],
                            'date' => $dateKey,
                            'startTime' => $event['startTime'] ?? null,
                            'month' => $month
                        ];
                    }
                }
            }
            
            // Add if it has JSON files (even if empty)
            if ($hasFiles) {
                $result[''] = ['events' => $events];
            }
        }
        
        // Recursively scan all namespace directories including sub-namespaces
        $this->scanNamespaceRecursive($dataDir, '', $result);
        
        // Sort namespaces, but keep '' (default) first
        uksort($result, function($a, $b) {
            if ($a === '') return -1;
            if ($b === '') return 1;
            return strcmp($a, $b);
        });
        
        return $result;
    }
    
    private function scanNamespaceRecursive($baseDir, $parentNamespace, &$result) {
        foreach (glob($baseDir . '*', GLOB_ONLYDIR) as $nsDir) {
            $dirName = basename($nsDir);
            
            // Skip the root 'calendar' dir
            if ($dirName === 'calendar' && empty($parentNamespace)) continue;
            
            // Build namespace path
            $namespace = empty($parentNamespace) ? $dirName : $parentNamespace . ':' . $dirName;
            
            // Check for calendar directory
            $calendarDir = $nsDir . '/calendar';
            if (is_dir($calendarDir)) {
                $hasFiles = false;
                $events = [];
                
                // Scan all calendar files
                foreach (glob($calendarDir . '/*.json') as $file) {
                    $hasFiles = true;
                    $month = basename($file, '.json');
                    $data = json_decode(file_get_contents($file), true);
                    if (!$data) continue;
                    
                    foreach ($data as $dateKey => $eventList) {
                        foreach ($eventList as $event) {
                            $events[] = [
                                'id' => $event['id'],
                                'title' => $event['title'],
                                'date' => $dateKey,
                                'startTime' => $event['startTime'] ?? null,
                                'month' => $month
                            ];
                        }
                    }
                }
                
                // Add namespace if it has JSON files (even if empty)
                if ($hasFiles) {
                    $result[$namespace] = ['events' => $events];
                }
            }
            
            // Recursively scan sub-directories
            $this->scanNamespaceRecursive($nsDir . '/', $namespace, $result);
        }
    }
    
    private function getAllNamespaces() {
        $dataDir = DOKU_INC . 'data/meta/';
        $namespaces = [];
        
        // Check root calendar directory first
        $rootCalendarDir = $dataDir . 'calendar';
        if (is_dir($rootCalendarDir)) {
            $namespaces[] = '';  // Blank/default namespace
        }
        
        // Check all other namespace directories
        foreach (glob($dataDir . '*', GLOB_ONLYDIR) as $nsDir) {
            $namespace = basename($nsDir);
            
            // Skip the root 'calendar' dir (already added as '')
            if ($namespace === 'calendar') continue;
            
            $calendarDir = $nsDir . '/calendar';
            if (is_dir($calendarDir)) {
                $namespaces[] = $namespace;
            }
        }
        
        return $namespaces;
    }
    
    private function searchEvents($search, $filterNamespace) {
        $dataDir = DOKU_INC . 'data/meta/';
        $results = [];
        
        $search = strtolower(trim($search));
        
        foreach (glob($dataDir . '*', GLOB_ONLYDIR) as $nsDir) {
            $namespace = basename($nsDir);
            $calendarDir = $nsDir . '/calendar';
            
            if (!is_dir($calendarDir)) continue;
            if ($filterNamespace !== '' && $namespace !== $filterNamespace) continue;
            
            foreach (glob($calendarDir . '/*.json') as $file) {
                $month = basename($file, '.json');
                $data = json_decode(file_get_contents($file), true);
                if (!$data) continue;
                
                foreach ($data as $dateKey => $events) {
                    foreach ($events as $event) {
                        if ($search === '' || strpos(strtolower($event['title']), $search) !== false) {
                            $results[] = [
                                'id' => $event['id'],
                                'title' => $event['title'],
                                'date' => $dateKey,
                                'startTime' => $event['startTime'] ?? null,
                                'namespace' => $event['namespace'] ?? '',
                                'month' => $month
                            ];
                        }
                    }
                }
            }
        }
        
        return $results;
    }
    
    private function deleteRecurringSeries() {
        global $INPUT;
        
        $eventTitle = $INPUT->str('event_title');
        $namespace = $INPUT->str('namespace');
        
        // Collect ALL calendar directories
        $dataDir = DOKU_INC . 'data/meta/';
        $calendarDirs = [];
        if (is_dir($dataDir . 'calendar')) {
            $calendarDirs[] = $dataDir . 'calendar';
        }
        $this->findCalendarDirs($dataDir, $calendarDirs);
        
        $count = 0;
        
        foreach ($calendarDirs as $calDir) {
            foreach (glob($calDir . '/*.json') as $file) {
                $data = json_decode(file_get_contents($file), true);
                if (!$data || !is_array($data)) continue;
                
                $modified = false;
                foreach ($data as $dateKey => $events) {
                    $filtered = [];
                    foreach ($events as $event) {
                        $eventNs = isset($event['namespace']) ? $event['namespace'] : '';
                        // Match by title AND namespace field
                        if (strtolower(trim($event['title'])) === strtolower(trim($eventTitle)) &&
                            strtolower(trim($eventNs)) === strtolower(trim($namespace))) {
                            $count++;
                            $modified = true;
                        } else {
                            $filtered[] = $event;
                        }
                    }
                    $data[$dateKey] = $filtered;
                }
                
                if ($modified) {
                    foreach ($data as $dk => $evts) {
                        if (empty($evts)) unset($data[$dk]);
                    }
                    
                    if (empty($data)) {
                        unlink($file);
                    } else {
                        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
                    }
                }
            }
        }
        
        $this->clearStatsCache();
        $this->redirect("Deleted $count occurrences of recurring event: " . $eventTitle, 'success', 'manage');
    }
    
    private function editRecurringSeries() {
        global $INPUT;
        
        $oldTitle = $INPUT->str('old_title');
        $oldNamespace = $INPUT->str('old_namespace');
        $newTitle = $INPUT->str('new_title');
        $startTime = $INPUT->str('start_time');
        $endTime = $INPUT->str('end_time');
        $interval = $INPUT->int('interval', 0);
        $newNamespace = $INPUT->str('new_namespace');
        
        // Use old namespace if new namespace is empty (keep current)
        if (empty($newNamespace) && !isset($_POST['new_namespace'])) {
            $newNamespace = $oldNamespace;
        }
        
        // Collect ALL calendar directories to search
        $dataDir = DOKU_INC . 'data/meta/';
        $calendarDirs = [];
        
        // Root calendar dir
        if (is_dir($dataDir . 'calendar')) {
            $calendarDirs[] = $dataDir . 'calendar';
        }
        
        // All namespace dirs
        $this->findCalendarDirs($dataDir, $calendarDirs);
        
        $count = 0;
        
        // Pass 1: Rename title, update time, update namespace field in ALL matching events
        foreach ($calendarDirs as $calDir) {
            if (is_string($calDir)) {
                $dir = $calDir;
            } else {
                $dir = $calDir['dir'];
            }
            
            foreach (glob($dir . '/*.json') as $file) {
                $data = json_decode(file_get_contents($file), true);
                if (!$data || !is_array($data)) continue;
                
                $modified = false;
                foreach ($data as $dateKey => &$dayEvents) {
                    if (!is_array($dayEvents)) continue;
                    foreach ($dayEvents as $key => &$event) {
                        // Match by old title (case-insensitive) AND namespace field
                        $eventNs = isset($event['namespace']) ? $event['namespace'] : '';
                        if (strtolower(trim($event['title'])) !== strtolower(trim($oldTitle))) continue;
                        if (strtolower(trim($eventNs)) !== strtolower(trim($oldNamespace))) continue;
                        
                        // Update title
                        $event['title'] = $newTitle;
                        
                        // Update start time if provided
                        if (!empty($startTime)) {
                            $event['time'] = $startTime;
                        }
                        
                        // Update end time if provided
                        if (!empty($endTime)) {
                            $event['endTime'] = $endTime;
                        }
                        
                        // Update namespace field
                        $event['namespace'] = $newNamespace;
                        
                        $count++;
                        $modified = true;
                    }
                    unset($event);
                }
                unset($dayEvents);
                
                if ($modified) {
                    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
                }
            }
        }
        
        // Pass 2: Handle interval changes (respace events from first date)
        if ($interval > 0 && $count > 0) {
            // Use getRecurringSeriesEvents to find all events with the NEW title
            $allEvents = $this->getRecurringSeriesEvents($newTitle, $newNamespace);
            
            if (count($allEvents) > 1) {
                $firstDate = new DateTime($allEvents[0]['date']);
                
                // Remove all except first, then re-create with new spacing
                for ($i = 1; $i < count($allEvents); $i++) {
                    $entry = $allEvents[$i];
                    $data = json_decode(file_get_contents($entry['file']), true);
                    if (!$data || !isset($data[$entry['date']])) continue;
                    
                    foreach ($data[$entry['date']] as $k => $evt) {
                        if (strtolower(trim($evt['title'])) === strtolower(trim($newTitle))) {
                            unset($data[$entry['date']][$k]);
                            $data[$entry['date']] = array_values($data[$entry['date']]);
                            break;
                        }
                    }
                    if (empty($data[$entry['date']])) unset($data[$entry['date']]);
                    if (empty($data)) {
                        unlink($entry['file']);
                    } else {
                        file_put_contents($entry['file'], json_encode($data, JSON_PRETTY_PRINT));
                    }
                }
                
                // Re-create with new interval
                $template = $allEvents[0]['event'];
                $targetDir = ($newNamespace === '') 
                    ? DOKU_INC . 'data/meta/calendar' 
                    : DOKU_INC . 'data/meta/' . str_replace(':', '/', $newNamespace) . '/calendar';
                if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
                
                $baseId = isset($template['recurringId']) ? $template['recurringId'] : md5($newTitle . $newNamespace);
                
                for ($i = 1; $i < count($allEvents); $i++) {
                    $newDate = clone $firstDate;
                    $newDate->modify('+' . ($i * $interval) . ' days');
                    $dateKey = $newDate->format('Y-m-d');
                    list($year, $month) = explode('-', $dateKey);
                    
                    $file = $targetDir . '/' . sprintf('%04d-%02d.json', $year, $month);
                    $fileData = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
                    if (!is_array($fileData)) $fileData = [];
                    if (!isset($fileData[$dateKey])) $fileData[$dateKey] = [];
                    
                    $newEvent = $template;
                    $newEvent['id'] = $baseId . '-respace-' . $i;
                    $fileData[$dateKey][] = $newEvent;
                    file_put_contents($file, json_encode($fileData, JSON_PRETTY_PRINT));
                }
            }
        }
        
        $changes = [];
        if ($oldTitle !== $newTitle) $changes[] = "title";
        if (!empty($startTime) || !empty($endTime)) $changes[] = "time";
        if ($interval > 0) $changes[] = "interval";
        if ($newNamespace !== $oldNamespace) $changes[] = "namespace";
        
        $changeStr = !empty($changes) ? " (" . implode(", ", $changes) . ")" : "";
        $this->clearStatsCache();
        $this->redirect("Updated $count occurrences of recurring event$changeStr", 'success', 'manage');
    }
    
    /**
     * Find all calendar directories recursively
     */
    private function findCalendarDirs($baseDir, &$dirs) {
        foreach (glob($baseDir . '*', GLOB_ONLYDIR) as $nsDir) {
            $name = basename($nsDir);
            if ($name === 'calendar') continue; // Skip root calendar (added separately)
            
            $calDir = $nsDir . '/calendar';
            if (is_dir($calDir)) {
                $dirs[] = $calDir;
            }
            
            // Recurse
            $this->findCalendarDirs($nsDir . '/', $dirs);
        }
    }

    private function moveEvents() {
        global $INPUT;
        
        $events = $INPUT->arr('events');
        $targetNamespace = $INPUT->str('target_namespace');
        
        if (empty($events)) {
            $this->redirect('No events selected', 'error', 'manage');
        }
        
        $moved = 0;
        
        foreach ($events as $eventData) {
            list($id, $namespace, $date, $month) = explode('|', $eventData);
            
            // Determine old file path
            if ($namespace === '') {
                $oldFile = DOKU_INC . 'data/meta/calendar/' . $month . '.json';
            } else {
                $oldFile = DOKU_INC . 'data/meta/' . $namespace . '/calendar/' . $month . '.json';
            }
            
            if (!file_exists($oldFile)) continue;
            
            $oldData = json_decode(file_get_contents($oldFile), true);
            if (!$oldData) continue;
            
            // Find and remove event from old file
            $event = null;
            if (isset($oldData[$date])) {
                foreach ($oldData[$date] as $key => $evt) {
                    if ($evt['id'] === $id) {
                        $event = $evt;
                        unset($oldData[$date][$key]);
                        $oldData[$date] = array_values($oldData[$date]);
                        break;
                    }
                }
                
                // Remove empty date arrays
                if (empty($oldData[$date])) {
                    unset($oldData[$date]);
                }
            }
            
            if (!$event) continue;
            
            // Save old file
            file_put_contents($oldFile, json_encode($oldData, JSON_PRETTY_PRINT));
            
            // Update event namespace
            $event['namespace'] = $targetNamespace;
            
            // Determine new file path
            if ($targetNamespace === '') {
                $newFile = DOKU_INC . 'data/meta/calendar/' . $month . '.json';
                $newDir = dirname($newFile);
            } else {
                $newFile = DOKU_INC . 'data/meta/' . $targetNamespace . '/calendar/' . $month . '.json';
                $newDir = dirname($newFile);
            }
            
            if (!is_dir($newDir)) {
                mkdir($newDir, 0755, true);
            }
            
            $newData = [];
            if (file_exists($newFile)) {
                $newData = json_decode(file_get_contents($newFile), true) ?: [];
            }
            
            if (!isset($newData[$date])) {
                $newData[$date] = [];
            }
            $newData[$date][] = $event;
            
            file_put_contents($newFile, json_encode($newData, JSON_PRETTY_PRINT));
            $moved++;
        }
        
        $displayTarget = $targetNamespace ?: '(default)';
        $this->clearStatsCache();
        $this->redirect("Moved $moved event(s) to namespace: " . $displayTarget, 'success', 'manage');
    }
    
    private function moveSingleEvent() {
        global $INPUT;
        
        $eventData = $INPUT->str('event');
        $targetNamespace = $INPUT->str('target_namespace');
        
        list($id, $namespace, $date, $month) = explode('|', $eventData);
        
        // Determine old file path
        if ($namespace === '') {
            $oldFile = DOKU_INC . 'data/meta/calendar/' . $month . '.json';
        } else {
            $oldFile = DOKU_INC . 'data/meta/' . $namespace . '/calendar/' . $month . '.json';
        }
        
        if (!file_exists($oldFile)) {
            $this->redirect('Event file not found', 'error', 'manage');
        }
        
        $oldData = json_decode(file_get_contents($oldFile), true);
        if (!$oldData) {
            $this->redirect('Could not read event file', 'error', 'manage');
        }
        
        // Find and remove event from old file
        $event = null;
        if (isset($oldData[$date])) {
            foreach ($oldData[$date] as $key => $evt) {
                if ($evt['id'] === $id) {
                    $event = $evt;
                    unset($oldData[$date][$key]);
                    $oldData[$date] = array_values($oldData[$date]);
                    break;
                }
            }
            
            // Remove empty date arrays
            if (empty($oldData[$date])) {
                unset($oldData[$date]);
            }
        }
        
        if (!$event) {
            $this->redirect('Event not found', 'error', 'manage');
        }
        
        // Save old file (or delete if empty)
        if (empty($oldData)) {
            unlink($oldFile);
        } else {
            file_put_contents($oldFile, json_encode($oldData, JSON_PRETTY_PRINT));
        }
        
        // Update event namespace
        $event['namespace'] = $targetNamespace;
        
        // Determine new file path
        if ($targetNamespace === '') {
            $newFile = DOKU_INC . 'data/meta/calendar/' . $month . '.json';
            $newDir = dirname($newFile);
        } else {
            $newFile = DOKU_INC . 'data/meta/' . $targetNamespace . '/calendar/' . $month . '.json';
            $newDir = dirname($newFile);
        }
        
        if (!is_dir($newDir)) {
            mkdir($newDir, 0755, true);
        }
        
        $newData = [];
        if (file_exists($newFile)) {
            $newData = json_decode(file_get_contents($newFile), true) ?: [];
        }
        
        if (!isset($newData[$date])) {
            $newData[$date] = [];
        }
        $newData[$date][] = $event;
        
        file_put_contents($newFile, json_encode($newData, JSON_PRETTY_PRINT));
        
        $displayTarget = $targetNamespace ?: '(default)';
        $this->clearStatsCache();
        $this->redirect('Moved "' . $event['title'] . '" to ' . $displayTarget, 'success', 'manage');
    }
    
    private function createNamespace() {
        global $INPUT;
        
        $namespaceName = $INPUT->str('namespace_name');
        
        // Validate namespace name
        if (empty($namespaceName)) {
            $this->redirect('Namespace name cannot be empty', 'error', 'manage');
        }
        
        if (!preg_match('/^[a-zA-Z0-9_:-]+$/', $namespaceName)) {
            $this->redirect('Invalid namespace name. Use only letters, numbers, underscore, hyphen, and colon.', 'error', 'manage');
        }
        
        // Convert namespace to directory path
        $namespacePath = str_replace(':', '/', $namespaceName);
        $calendarDir = DOKU_INC . 'data/meta/' . $namespacePath . '/calendar';
        
        // Check if already exists
        if (is_dir($calendarDir)) {
            // Check if it has any JSON files
            $hasFiles = !empty(glob($calendarDir . '/*.json'));
            if ($hasFiles) {
                $this->redirect("Namespace '$namespaceName' already exists with events", 'info', 'manage');
            }
            // If directory exists but empty, continue to create placeholder
        }
        
        // Create the directory
        if (!is_dir($calendarDir)) {
            if (!mkdir($calendarDir, 0755, true)) {
                $this->redirect("Failed to create namespace directory", 'error', 'manage');
            }
        }
        
        // Create a placeholder JSON file with an empty structure for current month
        // This ensures the namespace appears in the list immediately
        $currentMonth = date('Y-m');
        $placeholderFile = $calendarDir . '/' . $currentMonth . '.json';
        
        if (!file_exists($placeholderFile)) {
            file_put_contents($placeholderFile, json_encode([], JSON_PRETTY_PRINT));
        }
        
        $this->redirect("Created namespace: $namespaceName", 'success', 'manage');
    }
    
    private function deleteNamespace() {
        global $INPUT;
        
        $namespace = $INPUT->str('namespace');
        
        // Validate namespace name to prevent path traversal
        if ($namespace !== '' && !preg_match('/^[a-zA-Z0-9_:-]+$/', $namespace)) {
            $this->redirect('Invalid namespace name. Use only letters, numbers, underscore, hyphen, and colon.', 'error', 'manage');
            return;
        }
        
        // Additional safety: ensure no path traversal sequences
        if (strpos($namespace, '..') !== false || strpos($namespace, '/') !== false || strpos($namespace, '\\') !== false) {
            $this->redirect('Invalid namespace: path traversal not allowed', 'error', 'manage');
            return;
        }
        
        // Convert namespace to directory path (e.g., "work:projects" → "work/projects")
        $namespacePath = str_replace(':', '/', $namespace);
        
        // Determine calendar directory
        if ($namespace === '') {
            $calendarDir = DOKU_INC . 'data/meta/calendar';
            $namespaceDir = null; // Don't delete root
        } else {
            $calendarDir = DOKU_INC . 'data/meta/' . $namespacePath . '/calendar';
            $namespaceDir = DOKU_INC . 'data/meta/' . $namespacePath;
        }
        
        // Check if directory exists
        if (!is_dir($calendarDir)) {
            // Maybe it was never created or already deleted
            $this->redirect("Namespace directory not found: $calendarDir", 'error', 'manage');
            return;
        }
        
        $filesDeleted = 0;
        $eventsDeleted = 0;
        
        // Delete all calendar JSON files (including empty ones)
        foreach (glob($calendarDir . '/*.json') as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                foreach ($data as $events) {
                    $eventsDeleted += count($events);
                }
            }
            unlink($file);
            $filesDeleted++;
        }
        
        // Delete any other files in calendar directory
        foreach (glob($calendarDir . '/*') as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        
        // Remove the calendar directory
        if ($namespace !== '') {
            @rmdir($calendarDir);
            
            // Try to remove parent directories if they're empty
            // This handles nested namespaces like work:projects:alpha
            $currentDir = dirname($calendarDir);
            $metaDir = DOKU_INC . 'data/meta';
            
            while ($currentDir !== $metaDir && $currentDir !== dirname($metaDir)) {
                if (is_dir($currentDir)) {
                    // Check if directory is empty
                    $contents = scandir($currentDir);
                    $isEmpty = count($contents) === 2; // Only . and ..
                    
                    if ($isEmpty) {
                        @rmdir($currentDir);
                        $currentDir = dirname($currentDir);
                    } else {
                        break; // Directory not empty, stop
                    }
                } else {
                    break;
                }
            }
        }
        
        $displayName = $namespace ?: '(default)';
        $this->clearStatsCache();
        $this->redirect("Deleted namespace '$displayName': $eventsDeleted events in $filesDeleted files", 'success', 'manage');
    }
    
    private function renameNamespace() {
        global $INPUT;
        
        $oldNamespace = $INPUT->str('old_namespace');
        $newNamespace = $INPUT->str('new_namespace');
        
        // Validate namespace names to prevent path traversal
        if ($oldNamespace !== '' && !preg_match('/^[a-zA-Z0-9_:-]+$/', $oldNamespace)) {
            $this->redirect('Invalid old namespace name. Use only letters, numbers, underscore, hyphen, and colon.', 'error', 'manage');
            return;
        }
        
        if ($newNamespace !== '' && !preg_match('/^[a-zA-Z0-9_:-]+$/', $newNamespace)) {
            $this->redirect('Invalid new namespace name. Use only letters, numbers, underscore, hyphen, and colon.', 'error', 'manage');
            return;
        }
        
        // Additional safety: ensure no path traversal sequences
        if (strpos($oldNamespace, '..') !== false || strpos($oldNamespace, '/') !== false || strpos($oldNamespace, '\\') !== false ||
            strpos($newNamespace, '..') !== false || strpos($newNamespace, '/') !== false || strpos($newNamespace, '\\') !== false) {
            $this->redirect('Invalid namespace: path traversal not allowed', 'error', 'manage');
            return;
        }
        
        // Validate new namespace name
        if ($newNamespace === '') {
            $this->redirect("Cannot rename to empty namespace", 'error', 'manage');
            return;
        }
        
        // Convert namespaces to directory paths
        $oldPath = str_replace(':', '/', $oldNamespace);
        $newPath = str_replace(':', '/', $newNamespace);
        
        // Determine source and destination directories
        if ($oldNamespace === '') {
            $sourceDir = DOKU_INC . 'data/meta/calendar';
        } else {
            $sourceDir = DOKU_INC . 'data/meta/' . $oldPath . '/calendar';
        }
        
        if ($newNamespace === '') {
            $targetDir = DOKU_INC . 'data/meta/calendar';
        } else {
            $targetDir = DOKU_INC . 'data/meta/' . $newPath . '/calendar';
        }
        
        // Check if source exists
        if (!is_dir($sourceDir)) {
            $this->redirect("Source namespace not found: $oldNamespace", 'error', 'manage');
            return;
        }
        
        // Check if target already exists
        if (is_dir($targetDir)) {
            $this->redirect("Target namespace already exists: $newNamespace", 'error', 'manage');
            return;
        }
        
        // Create target directory
        if (!file_exists(dirname($targetDir))) {
            mkdir(dirname($targetDir), 0755, true);
        }
        
        // Rename directory
        if (!rename($sourceDir, $targetDir)) {
            $this->redirect("Failed to rename namespace", 'error', 'manage');
            return;
        }
        
        // Update event namespace field in all JSON files
        $eventsUpdated = 0;
        foreach (glob($targetDir . '/*.json') as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                foreach ($data as $date => &$events) {
                    foreach ($events as &$event) {
                        if (isset($event['namespace']) && $event['namespace'] === $oldNamespace) {
                            $event['namespace'] = $newNamespace;
                            $eventsUpdated++;
                        }
                    }
                }
                file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
            }
        }
        
        // Clean up old directory structure if empty
        if ($oldNamespace !== '') {
            $currentDir = dirname($sourceDir);
            $metaDir = DOKU_INC . 'data/meta';
            
            while ($currentDir !== $metaDir && $currentDir !== dirname($metaDir)) {
                if (is_dir($currentDir)) {
                    $contents = scandir($currentDir);
                    $isEmpty = count($contents) === 2; // Only . and ..
                    
                    if ($isEmpty) {
                        @rmdir($currentDir);
                        $currentDir = dirname($currentDir);
                    } else {
                        break;
                    }
                } else {
                    break;
                }
            }
        }
        
        $this->clearStatsCache();
        $this->redirect("Renamed namespace from '$oldNamespace' to '$newNamespace' ($eventsUpdated events updated)", 'success', 'manage');
    }
    
    private function deleteSelectedEvents() {
        global $INPUT;
        
        $events = $INPUT->arr('events');
        
        if (empty($events)) {
            $this->redirect('No events selected', 'error', 'manage');
        }
        
        $deletedCount = 0;
        
        foreach ($events as $eventData) {
            list($id, $namespace, $date, $month) = explode('|', $eventData);
            
            // Determine file path
            if ($namespace === '') {
                $file = DOKU_INC . 'data/meta/calendar/' . $month . '.json';
            } else {
                $file = DOKU_INC . 'data/meta/' . $namespace . '/calendar/' . $month . '.json';
            }
            
            if (!file_exists($file)) continue;
            
            $data = json_decode(file_get_contents($file), true);
            if (!$data) continue;
            
            // Find and remove event
            if (isset($data[$date])) {
                foreach ($data[$date] as $key => $evt) {
                    if ($evt['id'] === $id) {
                        unset($data[$date][$key]);
                        $data[$date] = array_values($data[$date]);
                        $deletedCount++;
                        break;
                    }
                }
                
                // Remove empty date arrays
                if (empty($data[$date])) {
                    unset($data[$date]);
                }
                
                // Save file
                file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
            }
        }
        
        $this->clearStatsCache();
        $this->redirect("Deleted $deletedCount event(s)", 'success', 'manage');
    }
    
    /**
     * Clear the event statistics cache so counts refresh after mutations
     */
    private function saveImportantNamespaces() {
        global $INPUT;
        
        $configFile = DOKU_PLUGIN . 'calendar/sync_config.php';
        $config = [];
        if (file_exists($configFile)) {
            $config = include $configFile;
        }
        
        $config['important_namespaces'] = $INPUT->str('important_namespaces', 'important');
        
        $content = "<?php\nreturn " . var_export($config, true) . ";\n";
        if (file_put_contents($configFile, $content)) {
            $this->redirect('Important namespaces saved', 'success', 'manage');
        } else {
            $this->redirect('Error: Could not save configuration', 'error', 'manage');
        }
    }
    
    private function clearStatsCache() {
        $cacheFile = DOKU_PLUGIN . 'calendar/.event_stats_cache';
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }
    
    private function getCronStatus() {
        // Try to read root's crontab first, then current user
        $output = [];
        exec('sudo crontab -l 2>/dev/null', $output);
        
        // If sudo doesn't work, try current user
        if (empty($output)) {
            exec('crontab -l 2>/dev/null', $output);
        }
        
        // Also check system crontab files
        if (empty($output)) {
            $cronFiles = [
                '/etc/crontab',
                '/etc/cron.d/calendar',
                '/var/spool/cron/root',
                '/var/spool/cron/crontabs/root'
            ];
            
            foreach ($cronFiles as $file) {
                if (file_exists($file) && is_readable($file)) {
                    $content = file_get_contents($file);
                    $output = explode("\n", $content);
                    break;
                }
            }
        }
        
        // Look for sync_outlook.php in the cron entries
        foreach ($output as $line) {
            $line = trim($line);
            
            // Skip empty lines and comments
            if (empty($line) || $line[0] === '#') continue;
            
            // Check if line contains sync_outlook.php
            if (strpos($line, 'sync_outlook.php') !== false) {
                // Parse cron expression
                // Format: minute hour day month weekday [user] command
                $parts = preg_split('/\s+/', $line, 7);
                
                if (count($parts) >= 5) {
                    // Determine if this has a user field (system crontab format)
                    $hasUser = (count($parts) >= 6 && !preg_match('/^[\/\*]/', $parts[5]));
                    $offset = $hasUser ? 1 : 0;
                    
                    $frequency = $this->parseCronExpression($parts[0], $parts[1], $parts[2], $parts[3], $parts[4]);
                    return [
                        'active' => true,
                        'frequency' => $frequency,
                        'expression' => implode(' ', array_slice($parts, 0, 5)),
                        'full_line' => $line
                    ];
                }
            }
        }
        
        return ['active' => false, 'frequency' => '', 'expression' => '', 'full_line' => ''];
    }
    
    private function parseCronExpression($minute, $hour, $day, $month, $weekday) {
        // Parse minute field
        if ($minute === '*') {
            return 'Runs every minute';
        } elseif (strpos($minute, '*/') === 0) {
            $interval = substr($minute, 2);
            if ($interval == 1) {
                return 'Runs every minute';
            } elseif ($interval == 5) {
                return 'Runs every 5 minutes';
            } elseif ($interval == 8) {
                return 'Runs every 8 minutes';
            } elseif ($interval == 10) {
                return 'Runs every 10 minutes';
            } elseif ($interval == 15) {
                return 'Runs every 15 minutes';
            } elseif ($interval == 30) {
                return 'Runs every 30 minutes';
            } else {
                return "Runs every $interval minutes";
            }
        }
        
        // Parse hour field
        if ($hour === '*' && $minute !== '*') {
            return 'Runs hourly';
        } elseif (strpos($hour, '*/') === 0 && $minute !== '*') {
            $interval = substr($hour, 2);
            if ($interval == 1) {
                return 'Runs every hour';
            } else {
                return "Runs every $interval hours";
            }
        }
        
        // Parse day field
        if ($day === '*' && $hour !== '*' && $minute !== '*') {
            return 'Runs daily';
        }
        
        // Default
        return 'Custom schedule';
    }
    
    private function runSync() {
        global $INPUT;
        
        if ($INPUT->str('call') === 'ajax') {
            header('Content-Type: application/json');
            
            $syncScript = DOKU_PLUGIN . 'calendar/sync_outlook.php';
            $abortFile = DOKU_PLUGIN . 'calendar/.sync_abort';
            
            // Remove any existing abort flag
            if (file_exists($abortFile)) {
                @unlink($abortFile);
            }
            
            if (!file_exists($syncScript)) {
                echo json_encode(['success' => false, 'message' => 'Sync script not found at: ' . $syncScript]);
                exit;
            }
            
            // Change to plugin directory
            $pluginDir = DOKU_PLUGIN . 'calendar';
            $logFile = $pluginDir . '/sync.log';
            
            // Ensure log file exists and is writable
            if (!file_exists($logFile)) {
                @touch($logFile);
                @chmod($logFile, 0666);
            }
            
            // Try to log the execution (but don't fail if we can't)
            if (is_writable($logFile)) {
                $tz = new DateTimeZone('America/Los_Angeles');
                $now = new DateTime('now', $tz);
                $timestamp = $now->format('Y-m-d H:i:s');
                @file_put_contents($logFile, "[$timestamp] [ADMIN] Manual sync triggered via admin panel\n", FILE_APPEND);
            }
            
            // Find PHP binary - try multiple methods
            $phpPath = $this->findPhpBinary();
            
            // Build command
            $command = sprintf(
                'cd %s && %s %s 2>&1',
                escapeshellarg($pluginDir),
                $phpPath,
                escapeshellarg(basename($syncScript))
            );
            
            // Execute and capture output
            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);
            
            // Check if sync completed
            $lastLines = array_slice($output, -5);
            $completed = false;
            foreach ($lastLines as $line) {
                if (strpos($line, 'Sync Complete') !== false || strpos($line, 'Created:') !== false) {
                    $completed = true;
                    break;
                }
            }
            
            if ($returnCode === 0 && $completed) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Sync completed successfully! Check log below.'
                ]);
            } elseif ($returnCode === 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Sync started. Check log below for progress.'
                ]);
            } else {
                // Include output for debugging
                $errorMsg = 'Sync failed with error code: ' . $returnCode;
                if (!empty($output)) {
                    $errorMsg .= ' | ' . implode(' | ', array_slice($output, -3));
                }
                echo json_encode([
                    'success' => false,
                    'message' => $errorMsg
                ]);
            }
            exit;
        }
    }
    
    private function stopSync() {
        global $INPUT;
        
        if ($INPUT->str('call') === 'ajax') {
            header('Content-Type: application/json');
            
            $abortFile = DOKU_PLUGIN . 'calendar/.sync_abort';
            
            // Create abort flag file
            if (file_put_contents($abortFile, date('Y-m-d H:i:s')) !== false) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Stop signal sent to sync process'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to create abort flag'
                ]);
            }
            exit;
        }
    }
    
    private function uploadUpdate() {
        if (!isset($_FILES['plugin_zip']) || $_FILES['plugin_zip']['error'] !== UPLOAD_ERR_OK) {
            $this->redirect('Upload failed: ' . ($_FILES['plugin_zip']['error'] ?? 'No file uploaded'), 'error', 'update');
            return;
        }
        
        $uploadedFile = $_FILES['plugin_zip']['tmp_name'];
        $pluginDir = DOKU_PLUGIN . 'calendar/';
        $backupFirst = isset($_POST['backup_first']);
        
        // Check if plugin directory is writable
        if (!is_writable($pluginDir)) {
            $this->redirect('Plugin directory is not writable. Please check permissions: ' . $pluginDir, 'error', 'update');
            return;
        }
        
        // Check if parent directory is writable (for backup and temp files)
        if (!is_writable(DOKU_PLUGIN)) {
            $this->redirect('Plugin parent directory is not writable. Please check permissions: ' . DOKU_PLUGIN, 'error', 'update');
            return;
        }
        
        // Verify it's a ZIP file
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $uploadedFile);
        finfo_close($finfo);
        
        if ($mimeType !== 'application/zip' && $mimeType !== 'application/x-zip-compressed') {
            $this->redirect('Invalid file type. Please upload a ZIP file.', 'error', 'update');
            return;
        }
        
        // Create backup if requested
        if ($backupFirst) {
            // Get current version
            $pluginInfo = $pluginDir . 'plugin.info.txt';
            $version = 'unknown';
            if (file_exists($pluginInfo)) {
                $info = confToHash($pluginInfo);
                $version = $info['version'] ?? ($info['date'] ?? 'unknown');
            }
            
            $backupName = 'calendar.backup.v' . $version . '.' . date('Y-m-d_H-i-s') . '.zip';
            $backupPath = DOKU_PLUGIN . $backupName;
            
            try {
                $zip = new ZipArchive();
                if ($zip->open($backupPath, ZipArchive::CREATE) === TRUE) {
                    $fileCount = $this->addDirectoryToZip($zip, $pluginDir, 'calendar/');
                    $zip->close();
                    
                    // Verify backup was created and has content
                    if (!file_exists($backupPath)) {
                        $this->redirect('Backup file was not created', 'error', 'update');
                        return;
                    }
                    
                    $backupSize = filesize($backupPath);
                    if ($backupSize < 1000) { // Backup should be at least 1KB
                        @unlink($backupPath);
                        $this->redirect('Backup file is too small (' . $backupSize . ' bytes). Only ' . $fileCount . ' files were added. Backup aborted.', 'error', 'update');
                        return;
                    }
                    
                    if ($fileCount < 10) { // Should have at least 10 files
                        @unlink($backupPath);
                        $this->redirect('Backup incomplete: Only ' . $fileCount . ' files were added (expected 30+). Backup aborted.', 'error', 'update');
                        return;
                    }
                } else {
                    $this->redirect('Failed to create backup ZIP file', 'error', 'update');
                    return;
                }
            } catch (Exception $e) {
                if (file_exists($backupPath)) {
                    @unlink($backupPath);
                }
                $this->redirect('Backup failed: ' . $e->getMessage(), 'error', 'update');
                return;
            }
        }
        
        // Extract uploaded ZIP
        $zip = new ZipArchive();
        if ($zip->open($uploadedFile) !== TRUE) {
            $this->redirect('Failed to open ZIP file', 'error', 'update');
            return;
        }
        
        // Check if ZIP contains calendar folder
        $hasCalendarFolder = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (strpos($filename, 'calendar/') === 0) {
                $hasCalendarFolder = true;
                break;
            }
        }
        
        // Extract to temp directory first
        $tempDir = DOKU_PLUGIN . 'calendar_update_temp/';
        if (is_dir($tempDir)) {
            $this->deleteDirectory($tempDir);
        }
        mkdir($tempDir);
        
        $zip->extractTo($tempDir);
        $zip->close();
        
        // Determine source directory
        if ($hasCalendarFolder) {
            $sourceDir = $tempDir . 'calendar/';
        } else {
            $sourceDir = $tempDir;
        }
        
        // Preserve configuration files
        $preserveFiles = ['sync_config.php', 'sync_state.json', 'sync.log'];
        $preserved = [];
        foreach ($preserveFiles as $file) {
            $oldFile = $pluginDir . $file;
            if (file_exists($oldFile)) {
                $preserved[$file] = file_get_contents($oldFile);
            }
        }
        
        // Delete old plugin files (except data files)
        $this->deleteDirectoryContents($pluginDir, $preserveFiles);
        
        // Copy new files
        $this->recursiveCopy($sourceDir, $pluginDir);
        
        // Restore preserved files
        foreach ($preserved as $file => $content) {
            file_put_contents($pluginDir . $file, $content);
        }
        
        // Update version and date in plugin.info.txt
        $pluginInfo = $pluginDir . 'plugin.info.txt';
        if (file_exists($pluginInfo)) {
            $info = confToHash($pluginInfo);
            
            // Get new version from uploaded plugin
            $newVersion = $info['version'] ?? 'unknown';
            
            // Update date to current
            $info['date'] = date('Y-m-d');
            
            // Write updated info back
            $lines = [];
            foreach ($info as $key => $value) {
                $lines[] = str_pad($key, 8) . ' ' . $value;
            }
            file_put_contents($pluginInfo, implode("\n", $lines) . "\n");
        }
        
        // Cleanup temp directory
        $this->deleteDirectory($tempDir);
        
        $message = 'Plugin updated successfully!';
        if ($backupFirst) {
            $message .= ' Backup saved as: ' . $backupName;
        }
        $this->redirect($message, 'success', 'update');
    }
    
    private function deleteBackup() {
        global $INPUT;
        
        $filename = $INPUT->str('backup_file');
        
        if (empty($filename)) {
            $this->redirect('No backup file specified', 'error', 'update');
            return;
        }
        
        // Security: only allow files starting with "calendar" and ending with .zip, no directory traversal
        if (!preg_match('/^calendar[a-zA-Z0-9._-]*\.zip$/', $filename)) {
            $this->redirect('Invalid backup filename', 'error', 'update');
            return;
        }
        
        $backupPath = DOKU_PLUGIN . $filename;
        
        if (!file_exists($backupPath)) {
            $this->redirect('Backup file not found', 'error', 'update');
            return;
        }
        
        if (@unlink($backupPath)) {
            $this->redirect('Backup deleted: ' . $filename, 'success', 'update');
        } else {
            $this->redirect('Failed to delete backup. Check file permissions.', 'error', 'update');
        }
    }
    
    private function renameBackup() {
        global $INPUT;
        
        $oldName = $INPUT->str('old_name');
        $newName = $INPUT->str('new_name');
        
        if (empty($oldName) || empty($newName)) {
            $this->redirect('Missing filename(s)', 'error', 'update');
            return;
        }
        
        // Security: validate filenames
        if (!preg_match('/^[a-zA-Z0-9._-]+\.zip$/', $oldName) || !preg_match('/^[a-zA-Z0-9._-]+\.zip$/', $newName)) {
            $this->redirect('Invalid filename format', 'error', 'update');
            return;
        }
        
        $oldPath = DOKU_PLUGIN . $oldName;
        $newPath = DOKU_PLUGIN . $newName;
        
        if (!file_exists($oldPath)) {
            $this->redirect('Backup file not found', 'error', 'update');
            return;
        }
        
        if (file_exists($newPath)) {
            $this->redirect('A file with the new name already exists', 'error', 'update');
            return;
        }
        
        if (@rename($oldPath, $newPath)) {
            $this->redirect('Backup renamed: ' . $oldName . ' → ' . $newName, 'success', 'update');
        } else {
            $this->redirect('Failed to rename backup. Check file permissions.', 'error', 'update');
        }
    }
    
    private function restoreBackup() {
        global $INPUT;
        
        $filename = $INPUT->str('backup_file');
        
        if (empty($filename)) {
            $this->redirect('No backup file specified', 'error', 'update');
            return;
        }
        
        // Security: only allow files starting with "calendar" and ending with .zip, no directory traversal
        if (!preg_match('/^calendar[a-zA-Z0-9._-]*\.zip$/', $filename)) {
            $this->redirect('Invalid backup filename', 'error', 'update');
            return;
        }
        
        $backupPath = DOKU_PLUGIN . $filename;
        $pluginDir = DOKU_PLUGIN . 'calendar/';
        
        if (!file_exists($backupPath)) {
            $this->redirect('Backup file not found', 'error', 'update');
            return;
        }
        
        // Check if plugin directory is writable
        if (!is_writable($pluginDir)) {
            $this->redirect('Plugin directory is not writable. Please check permissions.', 'error', 'update');
            return;
        }
        
        // Extract backup to temp directory
        $tempDir = DOKU_PLUGIN . 'calendar_restore_temp/';
        if (is_dir($tempDir)) {
            $this->deleteDirectory($tempDir);
        }
        mkdir($tempDir);
        
        $zip = new ZipArchive();
        if ($zip->open($backupPath) !== TRUE) {
            $this->redirect('Failed to open backup ZIP file', 'error', 'update');
            return;
        }
        
        $zip->extractTo($tempDir);
        $zip->close();
        
        // The backup contains a "calendar/" folder
        $sourceDir = $tempDir . 'calendar/';
        
        if (!is_dir($sourceDir)) {
            $this->deleteDirectory($tempDir);
            $this->redirect('Invalid backup structure', 'error', 'update');
            return;
        }
        
        // Delete current plugin directory contents
        $this->deleteDirectoryContents($pluginDir, []);
        
        // Copy backup files to plugin directory
        $this->recursiveCopy($sourceDir, $pluginDir);
        
        // Cleanup temp directory
        $this->deleteDirectory($tempDir);
        
        $this->redirect('Plugin restored from backup: ' . $filename, 'success', 'update');
    }
    
    private function createManualBackup() {
        $pluginDir = DOKU_PLUGIN . 'calendar/';
        
        // Check if plugin directory is readable
        if (!is_readable($pluginDir)) {
            $this->redirect('Plugin directory is not readable. Please check permissions.', 'error', 'update');
            return;
        }
        
        // Check if parent directory is writable (for saving backup)
        if (!is_writable(DOKU_PLUGIN)) {
            $this->redirect('Plugin parent directory is not writable. Cannot save backup.', 'error', 'update');
            return;
        }
        
        // Get current version
        $pluginInfo = $pluginDir . 'plugin.info.txt';
        $version = 'unknown';
        if (file_exists($pluginInfo)) {
            $info = confToHash($pluginInfo);
            $version = $info['version'] ?? ($info['date'] ?? 'unknown');
        }
        
        $backupName = 'calendar.backup.v' . $version . '.manual.' . date('Y-m-d_H-i-s') . '.zip';
        $backupPath = DOKU_PLUGIN . $backupName;
        
        try {
            $zip = new ZipArchive();
            if ($zip->open($backupPath, ZipArchive::CREATE) === TRUE) {
                $fileCount = $this->addDirectoryToZip($zip, $pluginDir, 'calendar/');
                $zip->close();
                
                // Verify backup was created and has content
                if (!file_exists($backupPath)) {
                    $this->redirect('Backup file was not created', 'error', 'update');
                    return;
                }
                
                $backupSize = filesize($backupPath);
                if ($backupSize < 1000) { // Backup should be at least 1KB
                    @unlink($backupPath);
                    $this->redirect('Backup file is too small (' . $this->formatBytes($backupSize) . '). Only ' . $fileCount . ' files were added. Backup failed.', 'error', 'update');
                    return;
                }
                
                if ($fileCount < 10) { // Should have at least 10 files
                    @unlink($backupPath);
                    $this->redirect('Backup incomplete: Only ' . $fileCount . ' files were added (expected 30+). Backup failed.', 'error', 'update');
                    return;
                }
                
                // Success!
                $this->redirect('✓ Manual backup created successfully: ' . $backupName . ' (' . $this->formatBytes($backupSize) . ', ' . $fileCount . ' files)', 'success', 'update');
                
            } else {
                $this->redirect('Failed to create backup ZIP file', 'error', 'update');
                return;
            }
        } catch (Exception $e) {
            if (file_exists($backupPath)) {
                @unlink($backupPath);
            }
            $this->redirect('Backup failed: ' . $e->getMessage(), 'error', 'update');
            return;
        }
    }
    
    private function addDirectoryToZip($zip, $dir, $zipPath = '') {
        $fileCount = 0;
        $errors = [];
        
        // Ensure dir has trailing slash
        $dir = rtrim($dir, '/') . '/';
        
        if (!is_dir($dir)) {
            throw new Exception("Directory does not exist: $dir");
        }
        
        if (!is_readable($dir)) {
            throw new Exception("Directory is not readable: $dir");
        }
        
        try {
            // First, add all directories to preserve structure (including empty ones)
            $dirs = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST  // Process directories before their contents
            );
            
            foreach ($dirs as $item) {
                $itemPath = $item->getRealPath();
                if (!$itemPath) continue;
                
                // Calculate relative path from the source directory
                $relativePath = $zipPath . substr($itemPath, strlen($dir));
                
                if ($item->isDir()) {
                    // Add directory to ZIP (preserves empty directories and structure)
                    $dirInZip = rtrim($relativePath, '/') . '/';
                    $zip->addEmptyDir($dirInZip);
                } else {
                    // Add file to ZIP
                    if (is_readable($itemPath)) {
                        if ($zip->addFile($itemPath, $relativePath)) {
                            $fileCount++;
                        } else {
                            $errors[] = "Failed to add: " . basename($itemPath);
                        }
                    } else {
                        $errors[] = "Cannot read: " . basename($itemPath);
                    }
                }
            }
            
            // Log any errors but don't fail if we got most files
            if (!empty($errors) && count($errors) < 5) {
                foreach ($errors as $error) {
                    error_log('Calendar plugin backup warning: ' . $error);
                }
            }
            
            // If too many errors, fail
            if (count($errors) > 5) {
                throw new Exception("Too many errors adding files to backup: " . implode(', ', array_slice($errors, 0, 5)));
            }
            
        } catch (Exception $e) {
            error_log('Calendar plugin backup error: ' . $e->getMessage());
            throw $e;
        }
        
        return $fileCount;
    }
    
    private function deleteDirectory($dir) {
        if (!is_dir($dir)) return;
        
        try {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            
            foreach ($files as $file) {
                if ($file->isDir()) {
                    @rmdir($file->getRealPath());
                } else {
                    @unlink($file->getRealPath());
                }
            }
            
            @rmdir($dir);
        } catch (Exception $e) {
            error_log('Calendar plugin delete directory error: ' . $e->getMessage());
        }
    }
    
    private function deleteDirectoryContents($dir, $preserve = []) {
        if (!is_dir($dir)) return;
        
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            if (in_array($item, $preserve)) continue;
            
            $path = $dir . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
    }
    
    private function recursiveCopy($src, $dst) {
        if (!is_dir($src)) {
            return false;
        }
        
        $dir = opendir($src);
        if (!$dir) {
            return false;
        }
        
        // Create destination directory with proper permissions (0755)
        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }
        
        while (($file = readdir($dir)) !== false) {
            if ($file !== '.' && $file !== '..') {
                $srcPath = $src . '/' . $file;
                $dstPath = $dst . '/' . $file;
                
                if (is_dir($srcPath)) {
                    // Recursively copy subdirectory
                    $this->recursiveCopy($srcPath, $dstPath);
                } else {
                    // Copy file and preserve permissions
                    if (copy($srcPath, $dstPath)) {
                        // Try to preserve file permissions from source, fallback to 0644
                        $perms = @fileperms($srcPath);
                        if ($perms !== false) {
                            @chmod($dstPath, $perms);
                        } else {
                            @chmod($dstPath, 0644);
                        }
                    }
                }
            }
        }
        
        closedir($dir);
        return true;
    }
    
    private function formatBytes($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
    
    private function findPhpBinary() {
        // Try PHP_BINARY constant first (most reliable if available)
        if (defined('PHP_BINARY') && !empty(PHP_BINARY) && is_executable(PHP_BINARY)) {
            return escapeshellarg(PHP_BINARY);
        }
        
        // Try common PHP binary locations
        $possiblePaths = [
            '/usr/bin/php',
            '/usr/bin/php8.1',
            '/usr/bin/php8.2',
            '/usr/bin/php8.3',
            '/usr/bin/php7.4',
            '/usr/local/bin/php',
            'php' // Last resort - rely on PATH
        ];
        
        foreach ($possiblePaths as $path) {
            // Test if this PHP binary works
            $testOutput = [];
            $testReturn = 0;
            exec($path . ' -v 2>&1', $testOutput, $testReturn);
            
            if ($testReturn === 0) {
                return ($path === 'php') ? 'php' : escapeshellarg($path);
            }
        }
        
        // Fallback to 'php' and hope it's in PATH
        return 'php';
    }
    
    private function redirect($message, $type = 'success', $tab = null) {
        $url = '?do=admin&page=calendar';
        if ($tab) {
            $url .= '&tab=' . $tab;
        }
        $url .= '&msg=' . urlencode($message) . '&msgtype=' . $type;
        header('Location: ' . $url);
        exit;
    }
    
    private function getLog() {
        global $INPUT;
        
        if ($INPUT->str('call') === 'ajax') {
            header('Content-Type: application/json');
            
            $logFile = DOKU_PLUGIN . 'calendar/sync.log';
            $log = '';
            
            if (file_exists($logFile)) {
                // Get last 500 lines
                $lines = file($logFile);
                if ($lines !== false) {
                    $lines = array_slice($lines, -500);
                    $log = implode('', $lines);
                }
            } else {
                $log = "No log file found. Sync hasn't run yet.";
            }
            
            echo json_encode(['log' => $log]);
            exit;
        }
    }
    
    private function exportConfig() {
        global $INPUT;
        
        if ($INPUT->str('call') === 'ajax') {
            header('Content-Type: application/json');
            
            try {
                $configFile = DOKU_PLUGIN . 'calendar/sync_config.php';
                
                if (!file_exists($configFile)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Config file not found'
                    ]);
                    exit;
                }
                
                // Read config file
                $configContent = file_get_contents($configFile);
                
                // Generate encryption key from DokuWiki secret
                $key = $this->getEncryptionKey();
                
                // Encrypt config
                $encrypted = $this->encryptData($configContent, $key);
                
                echo json_encode([
                    'success' => true,
                    'encrypted' => $encrypted,
                    'message' => 'Config exported successfully'
                ]);
                exit;
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
                exit;
            }
        }
    }
    
    private function importConfig() {
        global $INPUT;
        
        if ($INPUT->str('call') === 'ajax') {
            header('Content-Type: application/json');
            
            try {
                $encrypted = $_POST['encrypted_config'] ?? '';
                
                if (empty($encrypted)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'No config data provided'
                    ]);
                    exit;
                }
                
                // Generate encryption key from DokuWiki secret
                $key = $this->getEncryptionKey();
                
                // Decrypt config
                $configContent = $this->decryptData($encrypted, $key);
                
                if ($configContent === false) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Decryption failed. Invalid key or corrupted file.'
                    ]);
                    exit;
                }
                
                // Validate PHP config file structure (without using eval)
                // Check that it starts with <?php and contains a return statement with array
                if (strpos($configContent, '<?php') === false) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Invalid config file: missing PHP opening tag'
                    ]);
                    exit;
                }
                
                // Check for dangerous patterns that shouldn't be in a config file
                $dangerousPatterns = [
                    '/\b(exec|shell_exec|system|passthru|popen|proc_open)\s*\(/i',
                    '/\b(eval|assert|create_function)\s*\(/i',
                    '/\b(file_get_contents|file_put_contents|fopen|fwrite|unlink|rmdir)\s*\(/i',
                    '/\$_(GET|POST|REQUEST|SERVER|FILES|COOKIE|SESSION)\s*\[/i',
                    '/`[^`]+`/',  // Backtick execution
                ];
                
                foreach ($dangerousPatterns as $pattern) {
                    if (preg_match($pattern, $configContent)) {
                        echo json_encode([
                            'success' => false,
                            'message' => 'Invalid config file: contains prohibited code patterns'
                        ]);
                        exit;
                    }
                }
                
                // Verify it looks like a valid config (has return array structure)
                if (!preg_match('/return\s*\[/', $configContent)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Invalid config file: must contain a return array statement'
                    ]);
                    exit;
                }
                
                // Write to config file
                $configFile = DOKU_PLUGIN . 'calendar/sync_config.php';
                
                // Backup existing config
                if (file_exists($configFile)) {
                    $backupFile = $configFile . '.backup.' . date('Y-m-d_H-i-s');
                    copy($configFile, $backupFile);
                }
                
                // Write new config
                if (file_put_contents($configFile, $configContent) === false) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Failed to write config file'
                    ]);
                    exit;
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Config imported successfully'
                ]);
                exit;
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
                exit;
            }
        }
    }
    
    private function getEncryptionKey() {
        global $conf;
        // Use DokuWiki's secret as the base for encryption
        // This ensures the key is unique per installation
        return hash('sha256', $conf['secret'] . 'calendar_config_encryption', true);
    }
    
    private function encryptData($data, $key) {
        // Use AES-256-CBC encryption
        $ivLength = openssl_cipher_iv_length('aes-256-cbc');
        $iv = openssl_random_pseudo_bytes($ivLength);
        
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
        
        // Combine IV and encrypted data, then base64 encode
        return base64_encode($iv . $encrypted);
    }
    
    private function decryptData($encryptedData, $key) {
        // Decode base64
        $data = base64_decode($encryptedData);
        
        if ($data === false) {
            return false;
        }
        
        // Extract IV and encrypted content
        $ivLength = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);
        
        // Decrypt
        $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
        
        return $decrypted;
    }
    
    private function clearLogFile() {
        global $INPUT;
        
        if ($INPUT->str('call') === 'ajax') {
            header('Content-Type: application/json');
            
            $logFile = DOKU_PLUGIN . 'calendar/sync.log';
            
            if (file_exists($logFile)) {
                if (file_put_contents($logFile, '')) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Could not clear log file']);
                }
            } else {
                echo json_encode(['success' => true, 'message' => 'No log file to clear']);
            }
            exit;
        }
    }
    
    private function downloadLog() {
        $logFile = DOKU_PLUGIN . 'calendar/sync.log';
        
        if (file_exists($logFile)) {
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="calendar-sync-' . date('Y-m-d-His') . '.log"');
            readfile($logFile);
            exit;
        } else {
            echo 'No log file found';
            exit;
        }
    }
    
    private function getEventStatistics() {
        $stats = [
            'total_events' => 0,
            'total_namespaces' => 0,
            'total_files' => 0,
            'total_recurring' => 0,
            'by_namespace' => [],
            'last_scan' => ''
        ];
        
        $metaDir = DOKU_INC . 'data/meta/';
        $cacheFile = DOKU_PLUGIN . 'calendar/.event_stats_cache';
        
        // Check if we have cached stats (less than 5 minutes old)
        if (file_exists($cacheFile)) {
            $cacheData = json_decode(file_get_contents($cacheFile), true);
            if ($cacheData && (time() - $cacheData['timestamp']) < 300) {
                return $cacheData['stats'];
            }
        }
        
        // Scan for events
        $this->scanDirectoryForStats($metaDir, '', $stats);
        
        // Count recurring events
        $recurringEvents = $this->findRecurringEvents();
        $stats['total_recurring'] = count($recurringEvents);
        
        $stats['total_namespaces'] = count($stats['by_namespace']);
        $stats['last_scan'] = date('Y-m-d H:i:s');
        
        // Cache the results
        file_put_contents($cacheFile, json_encode([
            'timestamp' => time(),
            'stats' => $stats
        ]));
        
        return $stats;
    }
    
    private function scanDirectoryForStats($dir, $namespace, &$stats) {
        if (!is_dir($dir)) return;
        
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $path = $dir . $item;
            
            // Check if this is a calendar directory
            if ($item === 'calendar' && is_dir($path)) {
                $jsonFiles = glob($path . '/*.json');
                $eventCount = 0;
                
                foreach ($jsonFiles as $file) {
                    $stats['total_files']++;
                    $data = json_decode(file_get_contents($file), true);
                    if ($data) {
                        foreach ($data as $dateEvents) {
                            $eventCount += count($dateEvents);
                        }
                    }
                }
                
                $stats['total_events'] += $eventCount;
                
                if ($eventCount > 0) {
                    $stats['by_namespace'][$namespace] = [
                        'events' => $eventCount,
                        'files' => count($jsonFiles)
                    ];
                }
            } elseif (is_dir($path)) {
                // Recurse into subdirectories
                $newNamespace = $namespace ? $namespace . ':' . $item : $item;
                $this->scanDirectoryForStats($path . '/', $newNamespace, $stats);
            }
        }
    }
    
    private function rescanEvents() {
        // Clear the cache to force a rescan
        $this->clearStatsCache();
        
        // Get fresh statistics
        $stats = $this->getEventStatistics();
        
        // Build absolute redirect URL
        $redirectUrl = DOKU_URL . 'doku.php?do=admin&page=calendar&tab=manage&msg=' . urlencode('Events rescanned! Found ' . $stats['total_events'] . ' events in ' . $stats['total_namespaces'] . ' namespaces.') . '&msgtype=success';
        
        // Redirect with success message using absolute URL
        header('Location: ' . $redirectUrl, true, 303);
        exit;
    }
    
    private function exportAllEvents() {
        $metaDir = DOKU_INC . 'data/meta/';
        $allEvents = [];
        
        // Collect all events
        $this->collectAllEvents($metaDir, '', $allEvents);
        
        // Create export package
        // Get current version
        $pluginInfo = DOKU_PLUGIN . 'calendar/plugin.info.txt';
        $info = file_exists($pluginInfo) ? confToHash($pluginInfo) : [];
        $currentVersion = isset($info['version']) ? trim($info['version']) : 'unknown';
        
        $exportData = [
            'export_date' => date('Y-m-d H:i:s'),
            'version' => $currentVersion,
            'total_events' => 0,
            'namespaces' => []
        ];
        
        foreach ($allEvents as $namespace => $files) {
            $exportData['namespaces'][$namespace] = [];
            foreach ($files as $filename => $events) {
                $exportData['namespaces'][$namespace][$filename] = $events;
                foreach ($events as $dateEvents) {
                    $exportData['total_events'] += count($dateEvents);
                }
            }
        }
        
        // Send as download
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="calendar-events-export-' . date('Y-m-d-His') . '.json"');
        echo json_encode($exportData, JSON_PRETTY_PRINT);
        exit;
    }
    
    private function collectAllEvents($dir, $namespace, &$allEvents) {
        if (!is_dir($dir)) return;
        
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $path = $dir . $item;
            
            // Check if this is a calendar directory
            if ($item === 'calendar' && is_dir($path)) {
                $jsonFiles = glob($path . '/*.json');
                
                if (!isset($allEvents[$namespace])) {
                    $allEvents[$namespace] = [];
                }
                
                foreach ($jsonFiles as $file) {
                    $filename = basename($file);
                    $data = json_decode(file_get_contents($file), true);
                    if ($data) {
                        $allEvents[$namespace][$filename] = $data;
                    }
                }
            } elseif (is_dir($path)) {
                // Recurse into subdirectories
                $newNamespace = $namespace ? $namespace . ':' . $item : $item;
                $this->collectAllEvents($path . '/', $newNamespace, $allEvents);
            }
        }
    }
    
    private function importAllEvents() {
        global $INPUT;
        
        if (!isset($_FILES['import_file'])) {
            $redirectUrl = DOKU_URL . 'doku.php?do=admin&page=calendar&tab=manage&msg=' . urlencode('No file uploaded') . '&msgtype=error';
            header('Location: ' . $redirectUrl, true, 303);
            exit;
        }
        
        $file = $_FILES['import_file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $redirectUrl = DOKU_URL . 'doku.php?do=admin&page=calendar&tab=manage&msg=' . urlencode('Upload error: ' . $file['error']) . '&msgtype=error';
            header('Location: ' . $redirectUrl, true, 303);
            exit;
        }
        
        // Read and decode the import file
        $importData = json_decode(file_get_contents($file['tmp_name']), true);
        
        if (!$importData || !isset($importData['namespaces'])) {
            $redirectUrl = DOKU_URL . 'doku.php?do=admin&page=calendar&tab=manage&msg=' . urlencode('Invalid import file format') . '&msgtype=error';
            header('Location: ' . $redirectUrl, true, 303);
            exit;
        }
        
        $importedCount = 0;
        $mergedCount = 0;
        
        // Import events
        foreach ($importData['namespaces'] as $namespace => $files) {
            $metaDir = DOKU_INC . 'data/meta/';
            if ($namespace) {
                $metaDir .= str_replace(':', '/', $namespace) . '/';
            }
            $calendarDir = $metaDir . 'calendar/';
            
            // Create directory if needed
            if (!is_dir($calendarDir)) {
                mkdir($calendarDir, 0755, true);
            }
            
            foreach ($files as $filename => $events) {
                $targetFile = $calendarDir . $filename;
                
                // If file exists, merge events
                if (file_exists($targetFile)) {
                    $existing = json_decode(file_get_contents($targetFile), true);
                    if ($existing) {
                        foreach ($events as $date => $dateEvents) {
                            if (!isset($existing[$date])) {
                                $existing[$date] = [];
                            }
                            foreach ($dateEvents as $event) {
                                // Check if event with same ID exists
                                $found = false;
                                foreach ($existing[$date] as $existingEvent) {
                                    if ($existingEvent['id'] === $event['id']) {
                                        $found = true;
                                        break;
                                    }
                                }
                                if (!$found) {
                                    $existing[$date][] = $event;
                                    $importedCount++;
                                } else {
                                    $mergedCount++;
                                }
                            }
                        }
                        file_put_contents($targetFile, json_encode($existing, JSON_PRETTY_PRINT));
                    }
                } else {
                    // New file
                    file_put_contents($targetFile, json_encode($events, JSON_PRETTY_PRINT));
                    foreach ($events as $dateEvents) {
                        $importedCount += count($dateEvents);
                    }
                }
            }
        }
        
        // Clear cache
        $this->clearStatsCache();
        
        $message = "Import complete! Imported $importedCount new events";
        if ($mergedCount > 0) {
            $message .= ", skipped $mergedCount duplicates";
        }
        
        $redirectUrl = DOKU_URL . 'doku.php?do=admin&page=calendar&tab=manage&msg=' . urlencode($message) . '&msgtype=success';
        header('Location: ' . $redirectUrl, true, 303);
        exit;
    }
    
    private function previewCleanup() {
        global $INPUT;
        
        $cleanupType = $INPUT->str('cleanup_type', 'age');
        $namespaceFilter = $INPUT->str('namespace_filter', '');
        
        // Debug info
        $debug = [];
        $debug['cleanup_type'] = $cleanupType;
        $debug['namespace_filter'] = $namespaceFilter;
        $debug['age_value'] = $INPUT->int('age_value', 6);
        $debug['age_unit'] = $INPUT->str('age_unit', 'months');
        $debug['range_start'] = $INPUT->str('range_start', '');
        $debug['range_end'] = $INPUT->str('range_end', '');
        $debug['delete_completed'] = $INPUT->bool('delete_completed', false);
        $debug['delete_past'] = $INPUT->bool('delete_past', false);
        
        $dataDir = DOKU_INC . 'data/meta/';
        $debug['data_dir'] = $dataDir;
        $debug['data_dir_exists'] = is_dir($dataDir);
        
        $eventsToDelete = $this->findEventsToCleanup($cleanupType, $namespaceFilter);
        
        // Merge with scan debug info
        if (isset($this->_cleanupDebug)) {
            $debug = array_merge($debug, $this->_cleanupDebug);
        }
        
        // Return JSON for preview with debug info
        header('Content-Type: application/json');
        echo json_encode([
            'count' => count($eventsToDelete),
            'events' => array_slice($eventsToDelete, 0, 50), // Limit to 50 for preview
            'debug' => $debug
        ]);
        exit;
    }
    
    private function cleanupEvents() {
        global $INPUT;
        
        $cleanupType = $INPUT->str('cleanup_type', 'age');
        $namespaceFilter = $INPUT->str('namespace_filter', '');
        
        // Create backup first
        $backupDir = DOKU_PLUGIN . 'calendar/backups/';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $backupFile = $backupDir . 'before-cleanup-' . date('Y-m-d-His') . '.zip';
        $this->createBackup($backupFile);
        
        // Find events to delete
        $eventsToDelete = $this->findEventsToCleanup($cleanupType, $namespaceFilter);
        $deletedCount = 0;
        
        // Group by file
        $fileGroups = [];
        foreach ($eventsToDelete as $evt) {
            $fileGroups[$evt['file']][] = $evt;
        }
        
        // Delete from each file
        foreach ($fileGroups as $file => $events) {
            if (!file_exists($file)) continue;
            
            $json = file_get_contents($file);
            $data = json_decode($json, true);
            
            if (!$data) continue;
            
            // Remove events
            foreach ($events as $evt) {
                if (isset($data[$evt['date']])) {
                    $data[$evt['date']] = array_filter($data[$evt['date']], function($e) use ($evt) {
                        return $e['id'] !== $evt['id'];
                    });
                    
                    // Remove date key if empty
                    if (empty($data[$evt['date']])) {
                        unset($data[$evt['date']]);
                    }
                    
                    $deletedCount++;
                }
            }
            
            // Save file or delete if empty
            if (empty($data)) {
                unlink($file);
            } else {
                file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
            }
        }
        
        // Clear cache
        $this->clearStatsCache();
        
        $message = "Cleanup complete! Deleted $deletedCount event(s). Backup created: " . basename($backupFile);
        $redirectUrl = DOKU_URL . 'doku.php?do=admin&page=calendar&tab=manage&msg=' . urlencode($message) . '&msgtype=success';
        header('Location: ' . $redirectUrl, true, 303);
        exit;
    }
    
    private function findEventsToCleanup($cleanupType, $namespaceFilter) {
        global $INPUT;
        
        $eventsToDelete = [];
        $dataDir = DOKU_INC . 'data/meta/';
        
        $debug = [];
        $debug['scanned_dirs'] = [];
        $debug['found_files'] = [];
        
        // Calculate cutoff date for age-based cleanup
        $cutoffDate = null;
        if ($cleanupType === 'age') {
            $ageValue = $INPUT->int('age_value', 6);
            $ageUnit = $INPUT->str('age_unit', 'months');
            
            if ($ageUnit === 'years') {
                $ageValue *= 12; // Convert to months
            }
            
            $cutoffDate = date('Y-m-d', strtotime("-$ageValue months"));
            $debug['cutoff_date'] = $cutoffDate;
        }
        
        // Get date range for range-based cleanup
        $rangeStart = $cleanupType === 'range' ? $INPUT->str('range_start', '') : null;
        $rangeEnd = $cleanupType === 'range' ? $INPUT->str('range_end', '') : null;
        
        // Get status filters
        $deleteCompleted = $cleanupType === 'status' && $INPUT->bool('delete_completed', false);
        $deletePast = $cleanupType === 'status' && $INPUT->bool('delete_past', false);
        
        // Check root calendar directory first (blank/default namespace)
        $rootCalendarDir = $dataDir . 'calendar';
        $debug['root_calendar_dir'] = $rootCalendarDir;
        $debug['root_exists'] = is_dir($rootCalendarDir);
        
        if (is_dir($rootCalendarDir)) {
            if (!$namespaceFilter || $namespaceFilter === '' || $namespaceFilter === 'default') {
                $debug['scanned_dirs'][] = $rootCalendarDir;
                $files = glob($rootCalendarDir . '/*.json');
                $debug['found_files'] = array_merge($debug['found_files'], $files);
                $this->processCalendarFiles($rootCalendarDir, '', $eventsToDelete, $cleanupType, $cutoffDate, $rangeStart, $rangeEnd, $deleteCompleted, $deletePast);
            }
        }
        
        // Scan all namespace directories
        $namespaceDirs = glob($dataDir . '*', GLOB_ONLYDIR);
        $debug['namespace_dirs_found'] = $namespaceDirs;
        
        foreach ($namespaceDirs as $nsDir) {
            $namespace = basename($nsDir);
            
            // Skip the root 'calendar' dir (already processed above)
            if ($namespace === 'calendar') continue;
            
            // Check namespace filter
            if ($namespaceFilter && strpos($namespace, $namespaceFilter) === false) {
                continue;
            }
            
            $calendarDir = $nsDir . '/calendar';
            $debug['checked_calendar_dirs'][] = $calendarDir;
            
            if (!is_dir($calendarDir)) {
                $debug['missing_calendar_dirs'][] = $calendarDir;
                continue;
            }
            
            $debug['scanned_dirs'][] = $calendarDir;
            $files = glob($calendarDir . '/*.json');
            $debug['found_files'] = array_merge($debug['found_files'], $files);
            $this->processCalendarFiles($calendarDir, $namespace, $eventsToDelete, $cleanupType, $cutoffDate, $rangeStart, $rangeEnd, $deleteCompleted, $deletePast);
        }
        
        // Store debug info globally for preview
        $this->_cleanupDebug = $debug;
        
        return $eventsToDelete;
    }
    
    private function processCalendarFiles($calendarDir, $namespace, &$eventsToDelete, $cleanupType, $cutoffDate, $rangeStart, $rangeEnd, $deleteCompleted, $deletePast) {
        foreach (glob($calendarDir . '/*.json') as $file) {
            $json = file_get_contents($file);
            $data = json_decode($json, true);
            
            if (!$data) continue;
            
            foreach ($data as $date => $dateEvents) {
                foreach ($dateEvents as $event) {
                    $shouldDelete = false;
                    
                    // Age-based
                    if ($cleanupType === 'age' && $cutoffDate && $date < $cutoffDate) {
                        $shouldDelete = true;
                    }
                    
                    // Range-based
                    if ($cleanupType === 'range' && $rangeStart && $rangeEnd) {
                        if ($date >= $rangeStart && $date <= $rangeEnd) {
                            $shouldDelete = true;
                        }
                    }
                    
                    // Status-based
                    if ($cleanupType === 'status') {
                        $isTask = isset($event['isTask']) && $event['isTask'];
                        $isCompleted = isset($event['completed']) && $event['completed'];
                        $isPast = $date < date('Y-m-d');
                        
                        if ($deleteCompleted && $isTask && $isCompleted) {
                            $shouldDelete = true;
                        }
                        if ($deletePast && !$isTask && $isPast) {
                            $shouldDelete = true;
                        }
                    }
                    
                    if ($shouldDelete) {
                        $eventsToDelete[] = [
                            'id' => $event['id'],
                            'title' => $event['title'],
                            'date' => $date,
                            'namespace' => $namespace ?: 'default',
                            'file' => $file
                        ];
                    }
                }
            }
        }
    }
    
    /**
     * Render Themes tab for sidebar widget theme selection
     */
    private function renderThemesTab($colors = null) {
        global $INPUT;
        
        // Use defaults if not provided
        if ($colors === null) {
            $colors = $this->getTemplateColors();
        }
        
        // Handle theme save
        if ($INPUT->str('action') === 'save_theme') {
            $theme = $INPUT->str('theme', 'matrix');
            $weekStart = $INPUT->str('week_start', 'monday');
            $this->saveSidebarTheme($theme);
            $this->saveWeekStartDay($weekStart);
            echo '<div style="background:#d4edda; border:1px solid #c3e6cb; color:#155724; padding:12px; border-radius:4px; margin-bottom:20px;">';
            echo '✓ Theme and settings saved successfully! Refresh any page with the sidebar to see changes.';
            echo '</div>';
        }
        
        $currentTheme = $this->getSidebarTheme();
        $currentWeekStart = $this->getWeekStartDay();
        
        echo '<h2 style="margin:0 0 20px 0; color:' . $colors['text'] . ';">🎨 Sidebar Widget Settings</h2>';
        echo '<p style="color:' . $colors['text'] . '; margin-bottom:20px;">Customize the appearance and behavior of the sidebar calendar widget.</p>';
        
        echo '<form method="post" action="?do=admin&page=calendar&tab=themes">';
        echo '<input type="hidden" name="action" value="save_theme">';
        
        // Week Start Day Section
        echo '<div style="background:' . $colors['bg'] . '; border:1px solid ' . $colors['border'] . '; border-radius:6px; padding:20px; margin-bottom:30px;">';
        echo '<h3 style="margin:0 0 15px 0; color:' . $colors['text'] . '; font-size:16px;">📅 Week Start Day</h3>';
        echo '<p style="color:' . $colors['text'] . '; margin-bottom:15px; font-size:13px;">Choose which day the week calendar grid starts with:</p>';
        
        echo '<div style="display:flex; gap:15px;">';
        echo '<label style="flex:1; padding:12px; border:2px solid ' . ($currentWeekStart === 'monday' ? '#00cc07' : $colors['border']) . '; border-radius:4px; background:' . ($currentWeekStart === 'monday' ? 'rgba(0, 204, 7, 0.05)' : $colors['bg']) . '; cursor:pointer; display:flex; align-items:center;">';
        echo '<input type="radio" name="week_start" value="monday" ' . ($currentWeekStart === 'monday' ? 'checked' : '') . ' style="margin-right:10px; width:18px; height:18px;">';
        echo '<div>';
        echo '<div style="font-weight:bold; color:' . $colors['text'] . '; margin-bottom:3px;">Monday</div>';
        echo '<div style="font-size:11px; color:' . $colors['text'] . ';">Week starts on Monday (ISO standard)</div>';
        echo '</div>';
        echo '</label>';
        
        echo '<label style="flex:1; padding:12px; border:2px solid ' . ($currentWeekStart === 'sunday' ? '#00cc07' : $colors['border']) . '; border-radius:4px; background:' . ($currentWeekStart === 'sunday' ? 'rgba(0, 204, 7, 0.05)' : $colors['bg']) . '; cursor:pointer; display:flex; align-items:center;">';
        echo '<input type="radio" name="week_start" value="sunday" ' . ($currentWeekStart === 'sunday' ? 'checked' : '') . ' style="margin-right:10px; width:18px; height:18px;">';
        echo '<div>';
        echo '<div style="font-weight:bold; color:' . $colors['text'] . '; margin-bottom:3px;">Sunday</div>';
        echo '<div style="font-size:11px; color:' . $colors['text'] . ';">Week starts on Sunday (US/Canada standard)</div>';
        echo '</div>';
        echo '</label>';
        echo '</div>';
        echo '</div>';
        
        // Visual Theme Section
        echo '<h3 style="margin:0 0 15px 0; color:' . $colors['text'] . '; font-size:16px;">🎨 Visual Theme</h3>';
        
        // Matrix Theme
        echo '<div style="border:2px solid ' . ($currentTheme === 'matrix' ? '#00cc07' : $colors['border']) . '; border-radius:6px; padding:20px; margin-bottom:20px; background:' . ($currentTheme === 'matrix' ? 'rgba(0, 204, 7, 0.05)' : $colors['bg']) . ';">';
        echo '<label style="display:flex; align-items:center; cursor:pointer;">';
        echo '<input type="radio" name="theme" value="matrix" ' . ($currentTheme === 'matrix' ? 'checked' : '') . ' style="margin-right:12px; width:20px; height:20px;">';
        echo '<div style="flex:1;">';
        echo '<div style="font-size:18px; font-weight:bold; color:#00cc07; margin-bottom:8px;">🟢 Matrix Edition</div>';
        echo '<div style="color:' . $colors['text'] . '; margin-bottom:12px;">Dark green theme with Matrix-style glow effects and neon accents</div>';
        echo '<div style="display:inline-block; background:#242424; border:2px solid #00cc07; padding:8px 12px; border-radius:4px; font-size:11px; font-family:monospace; color:#00cc07; box-shadow:0 0 10px rgba(0, 204, 7, 0.3);">Preview: Matrix Theme</div>';
        echo '</div>';
        echo '</label>';
        echo '</div>';
        
        // Purple Theme
        echo '<div style="border:2px solid ' . ($currentTheme === 'purple' ? '#9b59b6' : $colors['border']) . '; border-radius:6px; padding:20px; margin-bottom:20px; background:' . ($currentTheme === 'purple' ? 'rgba(155, 89, 182, 0.05)' : $colors['bg']) . ';">';
        echo '<label style="display:flex; align-items:center; cursor:pointer;">';
        echo '<input type="radio" name="theme" value="purple" ' . ($currentTheme === 'purple' ? 'checked' : '') . ' style="margin-right:12px; width:20px; height:20px;">';
        echo '<div style="flex:1;">';
        echo '<div style="font-size:18px; font-weight:bold; color:#9b59b6; margin-bottom:8px;">🟣 Purple Dream</div>';
        echo '<div style="color:' . $colors['text'] . '; margin-bottom:12px;">Rich purple theme with elegant violet accents and soft glow</div>';
        echo '<div style="display:inline-block; background:#2a2030; border:2px solid #9b59b6; padding:8px 12px; border-radius:4px; font-size:11px; font-family:monospace; color:#b19cd9; box-shadow:0 0 10px rgba(155, 89, 182, 0.3);">Preview: Purple Theme</div>';
        echo '</div>';
        echo '</label>';
        echo '</div>';
        
        // Professional Blue Theme
        echo '<div style="border:2px solid ' . ($currentTheme === 'professional' ? '#4a90e2' : $colors['border']) . '; border-radius:6px; padding:20px; margin-bottom:20px; background:' . ($currentTheme === 'professional' ? 'rgba(74, 144, 226, 0.05)' : $colors['bg']) . ';">';
        echo '<label style="display:flex; align-items:center; cursor:pointer;">';
        echo '<input type="radio" name="theme" value="professional" ' . ($currentTheme === 'professional' ? 'checked' : '') . ' style="margin-right:12px; width:20px; height:20px;">';
        echo '<div style="flex:1;">';
        echo '<div style="font-size:18px; font-weight:bold; color:#4a90e2; margin-bottom:8px;">🔵 Professional Blue</div>';
        echo '<div style="color:' . $colors['text'] . '; margin-bottom:12px;">Clean blue and grey theme with modern professional styling, no glow effects</div>';
        echo '<div style="display:inline-block; background:#f5f7fa; border:2px solid #4a90e2; padding:8px 12px; border-radius:4px; font-size:11px; font-family:sans-serif; color:#2c3e50; box-shadow:0 2px 4px rgba(0, 0, 0, 0.1);">Preview: Professional Theme</div>';
        echo '</div>';
        echo '</label>';
        echo '</div>';
        
        // Pink Bling Theme
        echo '<div style="border:2px solid ' . ($currentTheme === 'pink' ? '#ff1493' : $colors['border']) . '; border-radius:6px; padding:20px; margin-bottom:20px; background:' . ($currentTheme === 'pink' ? 'rgba(255, 20, 147, 0.05)' : $colors['bg']) . ';">';
        echo '<label style="display:flex; align-items:center; cursor:pointer;">';
        echo '<input type="radio" name="theme" value="pink" ' . ($currentTheme === 'pink' ? 'checked' : '') . ' style="margin-right:12px; width:20px; height:20px;">';
        echo '<div style="flex:1;">';
        echo '<div style="font-size:18px; font-weight:bold; color:#ff1493; margin-bottom:8px;">💎 Pink Bling</div>';
        echo '<div style="color:' . $colors['text'] . '; margin-bottom:12px;">Glamorous hot pink theme with maximum sparkle, hearts, and diamonds ✨</div>';
        echo '<div style="display:inline-block; background:#1a0d14; border:2px solid #ff1493; padding:8px 12px; border-radius:4px; font-size:11px; font-family:monospace; color:#ff69b4; box-shadow:0 0 12px rgba(255, 20, 147, 0.6);">Preview: Pink Bling Theme 💖</div>';
        echo '</div>';
        echo '</label>';
        echo '</div>';
        
        // Wiki Default Theme
        echo '<div style="border:2px solid ' . ($currentTheme === 'wiki' ? '#2b73b7' : $colors['border']) . '; border-radius:6px; padding:20px; margin-bottom:20px; background:' . ($currentTheme === 'wiki' ? 'rgba(43, 115, 183, 0.05)' : $colors['bg']) . ';">';
        echo '<label style="display:flex; align-items:center; cursor:pointer;">';
        echo '<input type="radio" name="theme" value="wiki" ' . ($currentTheme === 'wiki' ? 'checked' : '') . ' style="margin-right:12px; width:20px; height:20px;">';
        echo '<div style="flex:1;">';
        echo '<div style="font-size:18px; font-weight:bold; color:#2b73b7; margin-bottom:8px;">📄 Wiki Default</div>';
        echo '<div style="color:' . $colors['text'] . '; margin-bottom:12px;">Automatically matches your DokuWiki template theme using CSS variables - adapts to light and dark themes</div>';
        echo '<div style="display:inline-block; background:#f5f5f5; border:2px solid #ccc; padding:8px 12px; border-radius:4px; font-size:11px; font-family:sans-serif; color:' . $colors['text'] . '; box-shadow:0 1px 2px rgba(0, 0, 0, 0.1);">Preview: Matches Your Wiki Theme</div>';
        echo '</div>';
        echo '</label>';
        echo '</div>';
        
        echo '<button type="submit" style="background:#00cc07; color:#fff; border:none; padding:12px 24px; border-radius:4px; font-size:14px; font-weight:bold; cursor:pointer; box-shadow:0 2px 4px rgba(0,0,0,0.2);">Save Settings</button>';
        echo '</form>';
    }
    
    /**
     * Get current sidebar theme
     */
    private function getSidebarTheme() {
        $configFile = DOKU_INC . 'data/meta/calendar_theme.txt';
        if (file_exists($configFile)) {
            return trim(file_get_contents($configFile));
        }
        return 'matrix'; // Default
    }
    
    /**
     * Save sidebar theme
     */
    private function saveSidebarTheme($theme) {
        $configFile = DOKU_INC . 'data/meta/calendar_theme.txt';
        $validThemes = ['matrix', 'purple', 'professional', 'pink', 'wiki'];
        
        if (in_array($theme, $validThemes)) {
            file_put_contents($configFile, $theme);
            return true;
        }
        return false;
    }
    
    /**
     * Get week start day
     */
    private function getWeekStartDay() {
        $configFile = DOKU_INC . 'data/meta/calendar_week_start.txt';
        if (file_exists($configFile)) {
            $start = trim(file_get_contents($configFile));
            if (in_array($start, ['monday', 'sunday'])) {
                return $start;
            }
        }
        return 'sunday'; // Default to Sunday (US/Canada standard)
    }
    
    /**
     * Save week start day
     */
    private function saveWeekStartDay($weekStart) {
        $configFile = DOKU_INC . 'data/meta/calendar_week_start.txt';
        $validStarts = ['monday', 'sunday'];
        
        if (in_array($weekStart, $validStarts)) {
            file_put_contents($configFile, $weekStart);
            return true;
        }
        return false;
    }
    
    /**
     * Get colors from DokuWiki template's style.ini file
     */
    private function getTemplateColors() {
        global $conf;
        
        // Get current template name
        $template = $conf['template'];
        
        // Try multiple possible locations for style.ini
        $possiblePaths = [
            DOKU_INC . 'conf/tpl/' . $template . '/style.ini',
            DOKU_INC . 'lib/tpl/' . $template . '/style.ini',
        ];
        
        $styleIni = null;
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $styleIni = parse_ini_file($path, true);
                break;
            }
        }
        
        if (!$styleIni || !isset($styleIni['replacements'])) {
            // Return defaults
            return [
                'bg' => '#fff',
                'bg_alt' => '#e8e8e8',
                'text' => '#333',
                'border' => '#ccc',
                'link' => '#2b73b7',
            ];
        }
        
        $r = $styleIni['replacements'];
        
        return [
            'bg' => isset($r['__background__']) ? $r['__background__'] : '#fff',
            'bg_alt' => isset($r['__background_alt__']) ? $r['__background_alt__'] : '#e8e8e8',
            'text' => isset($r['__text__']) ? $r['__text__'] : '#333',
            'border' => isset($r['__border__']) ? $r['__border__'] : '#ccc',
            'link' => isset($r['__link__']) ? $r['__link__'] : '#2b73b7',
        ];
    }
}
