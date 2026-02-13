/**
 * DokuWiki Compact Calendar Plugin
 * 
 * This file dynamically loads calendar-main.js which contains the actual functionality.
 * This approach avoids DokuWiki's automatic file concatenation which was causing conflicts.
 */

(function() {
    // Check if calendar-main.js functions are already loaded
    if (typeof window.showConflictTooltip === 'function') {
        return; // Already loaded, don't load again
    }
    
    // Dynamically load calendar-main.js
    var script = document.createElement('script');
    script.type = 'text/javascript';
    
    // Get the base path from DOKU_BASE or derive from this script's location
    var base = (typeof DOKU_BASE !== 'undefined') ? DOKU_BASE : '/';
    script.src = base + 'lib/plugins/calendar/calendar-main.js';
    
    // Add cache buster to ensure fresh load
    script.src += '?v=' + Date.now();
    
    document.head.appendChild(script);
})();
