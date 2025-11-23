console.log('🚀 main.js loaded');

let csrfToken = null;

function initializeCSRFToken() {
    const metaToken = document.querySelector('meta[name="csrf-token"]');
    if (metaToken) {
        csrfToken = metaToken.getAttribute('content');
        console.log('✅ CSRF token loaded from meta tag');
    } else {
        console.warn('⚠️ No CSRF token found in meta tag');
    }
}

function getCSRFToken() {
    return csrfToken;
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ DOM ready - initializing main.js');
    
    initializeCSRFToken();
//    initializeSectionManagement();//
    
    console.log('✅ Main.js initialized');
});
