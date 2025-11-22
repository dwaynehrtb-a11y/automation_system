window.Utils = {
    // Device detection
    isMobile() {
        return window.innerWidth <= 768;
    },
    
    isTablet() {
        return window.innerWidth > 768 && window.innerWidth <= 1024;
    },
    
    isDesktop() {
        return window.innerWidth > 1024;
    },
    
    // Use your existing FormValidator methods
    showFieldError(field, message) {
        const parentGroup = field.closest('.form-group');
        if (parentGroup) {
            field.classList.add('is-invalid');
            
            let feedback = parentGroup.querySelector('.invalid-feedback');
            if (!feedback) {
                feedback = document.createElement('div');
                feedback.className = 'invalid-feedback';
                parentGroup.appendChild(feedback);
            }
            
            feedback.style.display = 'block';
            feedback.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
        }
    },
    
    clearFieldError(field) {
        field.classList.remove('is-invalid');
        const parentGroup = field.closest('.form-group');
        if (parentGroup) {
            const feedback = parentGroup.querySelector('.invalid-feedback');
            if (feedback) {
                feedback.style.display = 'none';
            }
        }
    },
    
    // Use your existing functions
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },
    
    setButtonLoading(button, isLoading) {
        if (!button) return;
        
        const textSpan = button.querySelector('.btn-text');
        const loadingSpan = button.querySelector('.btn-loading');
        
        if (isLoading) {
            button.disabled = true;
            if (textSpan) textSpan.style.display = 'none';
            if (loadingSpan) loadingSpan.style.display = 'inline-flex';
        } else {
            button.disabled = false;
            if (textSpan) textSpan.style.display = 'inline-flex';
            if (loadingSpan) loadingSpan.style.display = 'none';
        }
    },
    
    // Bridge to your existing functions
    debounce: window.debounce,
    throttle: window.throttle,
    
    // Storage (add if needed)
    storage: {
        set(key, value) {
            try {
                localStorage.setItem(`ams_${key}`, JSON.stringify(value));
                return true;
            } catch (error) {
                console.warn('LocalStorage set failed:', error);
                return false;
            }
        },
        
        get(key, defaultValue = null) {
            try {
                const item = localStorage.getItem(`ams_${key}`);
                return item ? JSON.parse(item) : defaultValue;
            } catch (error) {
                console.warn('LocalStorage get failed:', error);
                return defaultValue;
            }
        }
    }
};

// Bridge showToast to work with SweetAlert2 Toast
if (window.Toast) {
    window.showToast = function(options) {
        const iconMap = {
            success: 'success',
            error: 'error', 
            warning: 'warning',
            info: 'info'
        };
        
        window.Toast.fire({
            icon: iconMap[options.type] || 'info',
            title: options.title || options.message,
            timer: options.duration || 3000
        });
    };
}