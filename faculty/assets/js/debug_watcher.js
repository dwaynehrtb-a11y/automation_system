// Add this temporarily to track down the issue
// Place this at the END of flexible_grading.js

(function() {
    console.warn('🔍 DEBUG WATCHER INSTALLED');
    
    setInterval(() => {
        const inputs = document.querySelectorAll('.fgs-score-input');
        inputs.forEach(inp => {
            const val = inp.value;
            if (val && val.includes('%')) {
                console.error('❌ FOUND PERCENTAGE IN INPUT!', {
                    student: inp.getAttribute('data-student-id'),
                    column: inp.getAttribute('data-column-id'),
                    value: val
                });
                console.trace('Stack trace:');
            }
        });
    }, 500);
})();
