<?php
/**
 * Calendar Plugin - Google Calendar Sync
 * 
 * Provides two-way synchronization with Google Calendar using OAuth 2.0.
 * 
 * Setup:
 * 1. Create a project in Google Cloud Console
 * 2. Enable Google Calendar API
 * 3. Create OAuth 2.0 credentials (Web application)
 * 4. Add redirect URI: https://yoursite.com/lib/exe/ajax.php
 * 5. Enter Client ID and Client Secret in plugin admin
 * 
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  DokuWiki Community
 * @version 7.0.8
 */

if (!defined('DOKU_INC')) die();

class GoogleCalendarSync {
    
    /** @var string Google OAuth endpoints */
    const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    const CALENDAR_API = 'https://www.googleapis.com/calendar/v3';
    
    /** @var string Required OAuth scopes */
    const SCOPES = 'https://www.googleapis.com/auth/calendar.readonly https://www.googleapis.com/auth/calendar.events';
    
    /** @var string Path to config and token storage */
    private $configDir;
    private $configFile;
    private $tokenFile;
    
    /** @var array Configuration */
    private $config = [];
    
    /** @var CalendarAuditLogger */
    private $auditLogger;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $conf;
        $this->configDir = $conf['metadir'] . '/calendar/';
        $this->configFile = $this->configDir . 'google_config.json';
        $this->tokenFile = $this->configDir . 'google_token.json';
        
        if (!is_dir($this->configDir)) {
            @mkdir($this->configDir, 0775, true);
        }
        
        $this->loadConfig();
        
        // Load audit logger if available
        if (class_exists('CalendarAuditLogger')) {
            $this->auditLogger = new CalendarAuditLogger();
        }
    }
    
    /**
     * Load configuration from file
     */
    private function loadConfig() {
        if (file_exists($this->configFile)) {
            $data = file_get_contents($this->configFile);
            $this->config = json_decode($data, true) ?: [];
        }
    }
    
    /**
     * Save configuration to file
     */
    public function saveConfig($clientId, $clientSecret, $calendarId = 'primary') {
        $this->config = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'calendar_id' => $calendarId,
            'updated' => date('Y-m-d H:i:s')
        ];
        
        file_put_contents($this->configFile, json_encode($this->config, JSON_PRETTY_PRINT));
        
        // Secure the file
        @chmod($this->configFile, 0600);
        
        return true;
    }
    
    /**
     * Check if Google sync is configured
     */
    public function isConfigured() {
        return !empty($this->config['client_id']) && !empty($this->config['client_secret']);
    }
    
    /**
     * Check if we have a valid access token
     */
    public function isAuthenticated() {
        if (!file_exists($this->tokenFile)) {
            return false;
        }
        
        $token = $this->getToken();
        if (!$token || empty($token['access_token'])) {
            return false;
        }
        
        // Check if token is expired
        if (isset($token['expires_at']) && time() >= $token['expires_at']) {
            // Try to refresh
            if (!empty($token['refresh_token'])) {
                return $this->refreshToken($token['refresh_token']);
            }
            return false;
        }
        
        return true;
    }
    
    /**
     * Get the OAuth authorization URL
     */
    public function getAuthUrl($redirectUri) {
        if (!$this->isConfigured()) {
            return null;
        }
        
        $state = bin2hex(random_bytes(16));
        $this->saveState($state);
        
        $params = [
            'client_id' => $this->config['client_id'],
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state
        ];
        
        return self::AUTH_URL . '?' . http_build_query($params);
    }
    
    /**
     * Save OAuth state for CSRF protection
     */
    private function saveState($state) {
        $stateFile = $this->configDir . 'google_state.json';
        file_put_contents($stateFile, json_encode([
            'state' => $state,
            'created' => time()
        ]));
    }
    
    /**
     * Verify OAuth state
     */
    public function verifyState($state) {
        $stateFile = $this->configDir . 'google_state.json';
        if (!file_exists($stateFile)) {
            return false;
        }
        
        $data = json_decode(file_get_contents($stateFile), true);
        @unlink($stateFile); // One-time use
        
        // Check state matches and is not too old (10 minutes)
        if ($data['state'] === $state && (time() - $data['created']) < 600) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Exchange authorization code for tokens
     */
    public function handleCallback($code, $redirectUri) {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Google sync not configured'];
        }
        
        $params = [
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri
        ];
        
        $response = $this->httpPost(self::TOKEN_URL, $params);
        
        if (!$response || isset($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error_description'] ?? $response['error'] ?? 'Token exchange failed'
            ];
        }
        
        // Save token with expiry time
        $token = [
            'access_token' => $response['access_token'],
            'refresh_token' => $response['refresh_token'] ?? null,
            'token_type' => $response['token_type'] ?? 'Bearer',
            'expires_at' => time() + ($response['expires_in'] ?? 3600),
            'created' => date('Y-m-d H:i:s')
        ];
        
        $this->saveToken($token);
        
        if ($this->auditLogger) {
            $this->auditLogger->log('google_auth', ['action' => 'connected']);
        }
        
        return ['success' => true];
    }
    
    /**
     * Refresh the access token
     */
    private function refreshToken($refreshToken) {
        $params = [
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token'
        ];
        
        $response = $this->httpPost(self::TOKEN_URL, $params);
        
        if (!$response || isset($response['error'])) {
            return false;
        }
        
        // Update token
        $token = $this->getToken();
        $token['access_token'] = $response['access_token'];
        $token['expires_at'] = time() + ($response['expires_in'] ?? 3600);
        
        // Preserve refresh token if not returned
        if (isset($response['refresh_token'])) {
            $token['refresh_token'] = $response['refresh_token'];
        }
        
        $this->saveToken($token);
        
        return true;
    }
    
    /**
     * Save token to file
     */
    private function saveToken($token) {
        file_put_contents($this->tokenFile, json_encode($token, JSON_PRETTY_PRINT));
        @chmod($this->tokenFile, 0600);
    }
    
    /**
     * Get current token
     */
    private function getToken() {
        if (!file_exists($this->tokenFile)) {
            return null;
        }
        return json_decode(file_get_contents($this->tokenFile), true);
    }
    
    /**
     * Disconnect from Google Calendar
     */
    public function disconnect() {
        if (file_exists($this->tokenFile)) {
            @unlink($this->tokenFile);
        }
        
        if ($this->auditLogger) {
            $this->auditLogger->log('google_auth', ['action' => 'disconnected']);
        }
        
        return true;
    }
    
    /**
     * Get list of user's calendars
     */
    public function getCalendars() {
        if (!$this->isAuthenticated()) {
            return ['success' => false, 'error' => 'Not authenticated'];
        }
        
        $token = $this->getToken();
        $url = self::CALENDAR_API . '/users/me/calendarList';
        
        $response = $this->httpGet($url, $token['access_token']);
        
        if (!$response || isset($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error']['message'] ?? 'Failed to get calendars'
            ];
        }
        
        $calendars = [];
        foreach ($response['items'] ?? [] as $cal) {
            $calendars[] = [
                'id' => $cal['id'],
                'summary' => $cal['summary'],
                'primary' => $cal['primary'] ?? false,
                'accessRole' => $cal['accessRole']
            ];
        }
        
        return ['success' => true, 'calendars' => $calendars];
    }
    
    /**
     * Import events from Google Calendar
     * 
     * @param string $namespace DokuWiki namespace to import into
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array Result with imported count
     */
    public function importEvents($namespace = '', $startDate = null, $endDate = null) {
        if (!$this->isAuthenticated()) {
            return ['success' => false, 'error' => 'Not authenticated'];
        }
        
        // Default date range: 3 months past to 12 months future
        if (!$startDate) {
            $startDate = date('Y-m-d', strtotime('-3 months'));
        }
        if (!$endDate) {
            $endDate = date('Y-m-d', strtotime('+12 months'));
        }
        
        $token = $this->getToken();
        $calendarId = $this->config['calendar_id'] ?? 'primary';
        
        // Build API URL
        $url = self::CALENDAR_API . '/calendars/' . urlencode($calendarId) . '/events';
        $params = [
            'timeMin' => $startDate . 'T00:00:00Z',
            'timeMax' => $endDate . 'T23:59:59Z',
            'singleEvents' => 'true',  // Expand recurring events
            'orderBy' => 'startTime',
            'maxResults' => 2500
        ];
        
        $response = $this->httpGet($url . '?' . http_build_query($params), $token['access_token']);
        
        if (!$response || isset($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error']['message'] ?? 'Failed to fetch events'
            ];
        }
        
        // Process and save events
        $imported = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($response['items'] ?? [] as $gEvent) {
            $result = $this->importSingleEvent($gEvent, $namespace);
            if ($result['success']) {
                $imported++;
            } elseif ($result['skipped']) {
                $skipped++;
            } else {
                $errors[] = $result['error'];
            }
        }
        
        if ($this->auditLogger) {
            $this->auditLogger->log('google_import', [
                'namespace' => $namespace,
                'imported' => $imported,
                'skipped' => $skipped,
                'date_range' => "$startDate to $endDate"
            ]);
        }
        
        return [
            'success' => true,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors
        ];
    }
    
    /**
     * Import a single Google event
     */
    private function importSingleEvent($gEvent, $namespace) {
        // Skip cancelled events
        if (($gEvent['status'] ?? '') === 'cancelled') {
            return ['success' => false, 'skipped' => true];
        }
        
        // Parse date/time
        $startDateTime = $gEvent['start']['dateTime'] ?? $gEvent['start']['date'] ?? null;
        $endDateTime = $gEvent['end']['dateTime'] ?? $gEvent['end']['date'] ?? null;
        
        if (!$startDateTime) {
            return ['success' => false, 'skipped' => true, 'error' => 'No start date'];
        }
        
        // Determine if all-day event
        $isAllDay = isset($gEvent['start']['date']) && !isset($gEvent['start']['dateTime']);
        
        // Parse dates
        if ($isAllDay) {
            $date = $gEvent['start']['date'];
            $endDate = $gEvent['end']['date'];
            // Google all-day events end on the next day
            $endDate = date('Y-m-d', strtotime($endDate . ' -1 day'));
            $time = '';
            $endTime = '';
        } else {
            $startObj = new DateTime($startDateTime);
            $endObj = new DateTime($endDateTime);
            
            $date = $startObj->format('Y-m-d');
            $endDate = $endObj->format('Y-m-d');
            $time = $startObj->format('H:i');
            $endTime = $endObj->format('H:i');
            
            // If same day, don't set endDate
            if ($date === $endDate) {
                $endDate = '';
            }
        }
        
        // Build event data
        $eventId = 'g_' . substr(md5($gEvent['id']), 0, 8) . '_' . time();
        
        $eventData = [
            'id' => $eventId,
            'title' => $gEvent['summary'] ?? 'Untitled',
            'time' => $time,
            'endTime' => $endTime,
            'description' => $gEvent['description'] ?? '',
            'color' => $this->colorFromGoogle($gEvent['colorId'] ?? null),
            'isTask' => false,
            'completed' => false,
            'endDate' => $endDate,
            'namespace' => $namespace,
            'googleId' => $gEvent['id'],
            'created' => date('Y-m-d H:i:s'),
            'imported' => true
        ];
        
        // Save to calendar file
        return $this->saveImportedEvent($namespace, $date, $eventData);
    }
    
    /**
     * Save an imported event to the calendar JSON file
     */
    private function saveImportedEvent($namespace, $date, $eventData) {
        list($year, $month, $day) = explode('-', $date);
        
        $dataDir = DOKU_INC . 'data/meta/';
        if ($namespace) {
            $dataDir .= str_replace(':', '/', $namespace) . '/';
        }
        $dataDir .= 'calendar/';
        
        if (!is_dir($dataDir)) {
            @mkdir($dataDir, 0755, true);
        }
        
        $eventFile = $dataDir . sprintf('%04d-%02d.json', $year, $month);
        
        // Load existing events
        $events = [];
        if (file_exists($eventFile)) {
            $events = json_decode(file_get_contents($eventFile), true) ?: [];
        }
        
        // Check if this Google event already exists (by googleId)
        if (isset($events[$date])) {
            foreach ($events[$date] as $existing) {
                if (isset($existing['googleId']) && $existing['googleId'] === $eventData['googleId']) {
                    return ['success' => false, 'skipped' => true]; // Already imported
                }
            }
        }
        
        // Add event
        if (!isset($events[$date])) {
            $events[$date] = [];
        }
        $events[$date][] = $eventData;
        
        // Save using file handler if available
        if (class_exists('CalendarFileHandler')) {
            CalendarFileHandler::writeJson($eventFile, $events);
        } else {
            file_put_contents($eventFile, json_encode($events, JSON_PRETTY_PRINT));
        }
        
        return ['success' => true];
    }
    
    /**
     * Export events to Google Calendar
     * 
     * @param string $namespace DokuWiki namespace to export from
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array Result with exported count
     */
    public function exportEvents($namespace = '', $startDate = null, $endDate = null) {
        if (!$this->isAuthenticated()) {
            return ['success' => false, 'error' => 'Not authenticated'];
        }
        
        // Default date range
        if (!$startDate) {
            $startDate = date('Y-m-d');
        }
        if (!$endDate) {
            $endDate = date('Y-m-d', strtotime('+12 months'));
        }
        
        $token = $this->getToken();
        $calendarId = $this->config['calendar_id'] ?? 'primary';
        
        // Find events in date range
        $events = $this->getLocalEvents($namespace, $startDate, $endDate);
        
        $exported = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($events as $event) {
            // Skip already-imported events (came from Google)
            if (!empty($event['imported']) || !empty($event['googleId'])) {
                $skipped++;
                continue;
            }
            
            $result = $this->exportSingleEvent($event, $calendarId, $token['access_token']);
            if ($result['success']) {
                $exported++;
            } else {
                $errors[] = $result['error'];
            }
        }
        
        if ($this->auditLogger) {
            $this->auditLogger->log('google_export', [
                'namespace' => $namespace,
                'exported' => $exported,
                'skipped' => $skipped,
                'date_range' => "$startDate to $endDate"
            ]);
        }
        
        return [
            'success' => true,
            'exported' => $exported,
            'skipped' => $skipped,
            'errors' => $errors
        ];
    }
    
    /**
     * Export a single event to Google
     */
    private function exportSingleEvent($event, $calendarId, $accessToken) {
        $date = $event['date'];
        $endDate = $event['endDate'] ?? $date;
        
        // Build Google event
        if (empty($event['time'])) {
            // All-day event
            $gEvent = [
                'summary' => $event['title'],
                'description' => $event['description'] ?? '',
                'start' => ['date' => $date],
                'end' => ['date' => date('Y-m-d', strtotime($endDate . ' +1 day'))] // Google expects exclusive end
            ];
        } else {
            // Timed event
            $startTime = $date . 'T' . $event['time'] . ':00';
            $endTime = ($endDate ?: $date) . 'T' . ($event['endTime'] ?: $event['time']) . ':00';
            
            $gEvent = [
                'summary' => $event['title'],
                'description' => $event['description'] ?? '',
                'start' => ['dateTime' => $startTime, 'timeZone' => date_default_timezone_get()],
                'end' => ['dateTime' => $endTime, 'timeZone' => date_default_timezone_get()]
            ];
        }
        
        // Set color if available
        $colorId = $this->colorToGoogle($event['color'] ?? null);
        if ($colorId) {
            $gEvent['colorId'] = $colorId;
        }
        
        // Create event via API
        $url = self::CALENDAR_API . '/calendars/' . urlencode($calendarId) . '/events';
        $response = $this->httpPost($url, $gEvent, $accessToken, true);
        
        if (!$response || isset($response['error'])) {
            return [
                'success' => false,
                'error' => ($event['title'] ?? 'Event') . ': ' . ($response['error']['message'] ?? 'Failed to create')
            ];
        }
        
        return ['success' => true, 'googleId' => $response['id']];
    }
    
    /**
     * Get local calendar events
     */
    private function getLocalEvents($namespace, $startDate, $endDate) {
        $events = [];
        
        $dataDir = DOKU_INC . 'data/meta/';
        if ($namespace) {
            $dataDir .= str_replace(':', '/', $namespace) . '/';
        }
        $dataDir .= 'calendar/';
        
        if (!is_dir($dataDir)) {
            return $events;
        }
        
        // Parse date range
        $startObj = new DateTime($startDate);
        $endObj = new DateTime($endDate);
        
        // Iterate through month files
        $current = clone $startObj;
        $current->modify('first day of this month');
        
        while ($current <= $endObj) {
            $file = $dataDir . $current->format('Y-m') . '.json';
            
            if (file_exists($file)) {
                $data = json_decode(file_get_contents($file), true) ?: [];
                
                foreach ($data as $date => $dayEvents) {
                    if ($date >= $startDate && $date <= $endDate) {
                        foreach ($dayEvents as $event) {
                            $event['date'] = $date;
                            $events[] = $event;
                        }
                    }
                }
            }
            
            $current->modify('+1 month');
        }
        
        return $events;
    }
    
    /**
     * Convert Google color ID to hex
     */
    private function colorFromGoogle($colorId) {
        $colors = [
            '1' => '#7986cb',  // Lavender
            '2' => '#33b679',  // Sage
            '3' => '#8e24aa',  // Grape
            '4' => '#e67c73',  // Flamingo
            '5' => '#f6c026',  // Banana
            '6' => '#f5511d',  // Tangerine
            '7' => '#039be5',  // Peacock
            '8' => '#616161',  // Graphite
            '9' => '#3f51b5',  // Blueberry
            '10' => '#0b8043', // Basil
            '11' => '#d60000', // Tomato
        ];
        
        return $colors[$colorId] ?? '#3498db';
    }
    
    /**
     * Convert hex color to Google color ID
     */
    private function colorToGoogle($hex) {
        if (!$hex) return null;
        
        $hex = strtolower($hex);
        
        // Map common colors to Google IDs
        $map = [
            '#7986cb' => '1', '#33b679' => '2', '#8e24aa' => '3',
            '#e67c73' => '4', '#f6c026' => '5', '#f5511d' => '6',
            '#039be5' => '7', '#616161' => '8', '#3f51b5' => '9',
            '#0b8043' => '10', '#d60000' => '11',
            // Common defaults
            '#3498db' => '7', // Blue -> Peacock
            '#e74c3c' => '11', // Red -> Tomato
            '#2ecc71' => '2', // Green -> Sage
            '#9b59b6' => '3', // Purple -> Grape
            '#f39c12' => '5', // Orange -> Banana
        ];
        
        return $map[$hex] ?? null;
    }
    
    /**
     * HTTP GET request
     */
    private function httpGet($url, $accessToken = null) {
        $headers = ['Accept: application/json'];
        
        if ($accessToken) {
            $headers[] = 'Authorization: Bearer ' . $accessToken;
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
    
    /**
     * HTTP POST request
     */
    private function httpPost($url, $data, $accessToken = null, $json = false) {
        $headers = ['Accept: application/json'];
        
        if ($accessToken) {
            $headers[] = 'Authorization: Bearer ' . $accessToken;
        }
        
        if ($json) {
            $headers[] = 'Content-Type: application/json';
            $postData = json_encode($data);
        } else {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $postData = http_build_query($data);
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
    
    /**
     * Get sync status information
     */
    public function getStatus() {
        return [
            'configured' => $this->isConfigured(),
            'authenticated' => $this->isAuthenticated(),
            'calendar_id' => $this->config['calendar_id'] ?? 'primary',
            'has_client_id' => !empty($this->config['client_id']),
            'config_date' => $this->config['updated'] ?? null
        ];
    }
    
    /**
     * Get the configured calendar ID
     */
    public function getCalendarId() {
        return $this->config['calendar_id'] ?? 'primary';
    }
    
    /**
     * Set the calendar ID to sync with
     */
    public function setCalendarId($calendarId) {
        $this->config['calendar_id'] = $calendarId;
        file_put_contents($this->configFile, json_encode($this->config, JSON_PRETTY_PRINT));
        return true;
    }
}
