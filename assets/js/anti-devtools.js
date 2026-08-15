/**
 * Mango Numbers - Developer Tools & Inspection Blocker
 * Blocks right-click, shortcuts, and crashes/freezes the tab if DevTools are opened.
 */
(function() {
    // 1. Disable Right-Click
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
    });

    // 2. Disable Keyboard Shortcuts for DevTools
    document.addEventListener('keydown', function(e) {
        if (
            e.key === 'F12' ||
            ((e.ctrlKey || e.metaKey) && e.shiftKey && ['I', 'J', 'C'].includes(e.key.toUpperCase())) ||
            ((e.ctrlKey || e.metaKey) && e.key.toUpperCase() === 'U')
        ) {
            e.preventDefault();
        }
    });

    // 3. DevTools Detection & Instant Tab Blocking
    let detectCount = 0;
    
    function triggerAction() {
        // Clear document body immediately to hide page source and UI
        try {
            document.body.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100vh;background:#1A1208;color:#FFFDF9;font-family:sans-serif;font-weight:bold;font-size:24px;letter-spacing:-0.5px;">Action Blocked</div>';
        } catch(e) {}
        
        // Clean infinite recursion stack overflow to crash the execution thread
        function crash() {
            crash();
        }
        
        // Redirect to about:blank shortly after to purge the session completely
        setTimeout(function() {
            window.location.href = 'about:blank';
        }, 100);
        
        crash();
    }

    // Window size difference check (highly reliable for docked DevTools)
    function checkSize() {
        const threshold = 160;
        const widthDiff = window.outerWidth - window.innerWidth;
        const heightDiff = window.outerHeight - window.innerHeight;
        
        // If docked DevTools is open, the outer dimension is much larger than inner dimension
        if (widthDiff > threshold || heightDiff > threshold) {
            return true;
        }
        return false;
    }

    // Timing check using debugger statement (robust but filters background tabs and stutters)
    function checkTiming() {
        // Skip checks when document is in background to prevent interval throttling false positives
        if (document.hidden) {
            return false;
        }
        
        const start = performance.now();
        debugger;
        const end = performance.now();
        
        // If taking more than 100ms, a debugger is actively intercepting execution
        if (end - start > 100) {
            return true;
        }
        return false;
    }

    // Run verification loop every 1000ms (much more efficient than 200ms, avoids CPU drain)
    setInterval(function() {
        let detected = false;
        
        if (checkSize()) {
            detected = true;
        }
        
        if (checkTiming()) {
            detected = true;
        }
        
        if (detected) {
            detectCount++;
            if (detectCount >= 3) { // Require 3 consecutive hits to confirm DevTools is open (prevents stutter false positives)
                triggerAction();
            }
        } else {
            detectCount = 0; // Reset counter on clean tick
        }
    }, 1000);
})();
