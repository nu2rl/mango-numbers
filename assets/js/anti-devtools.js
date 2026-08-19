/**
 * Mango Numbers - Protection & Inspection Helper
 * ponytail: disabled destructive tab-wiping logic to prevent false-positive error screens on high-DPI screens, mobile emulation, or window resizes.
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
})();

