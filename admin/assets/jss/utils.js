// Enhanced Form Validation and User Feedback
// File: admin/assets/jss/utils.js

// Validation rules
const ValidationRules = {
    required: (value) => value.trim() !== '',
    email: (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value),
    minLength: (min) => (value) => value.length >= min,
    maxLength: (max) => (value) => value.length <= max,
    pattern: (regex) => (value) => regex.test(value),
    numeric: (value) => /^\d+$/.test(value),
    courseCode: (value) => /^[A-Z0-9_]{10,50}$/.test(value.toUpperCase()),
    employeeId: (value) => /^[A-Z0-9-]{3,20}$/.test(value)
};

// Form validation class
class FormValidator {
    constructor(formId, rules = {}) {
        this.form = document.getElementById(formId);
        this.rules = rules;
        this.errors = {};
        
        // Skip initialization for subjects form - handled by subjects.js
        if (formId === 'addSubjectForm') {
            console.log('Skipping FormValidator init for subjects form');
            return;
        }
        
        if (this.form) {
            this.init();
        }
    }
    
    init() {
        // Add real-time validation
        Object.keys(this.rules).forEach(fieldName => {
            const field = this.form.querySelector(`[name="${fieldName}"]`);
            if (field) {
                field.addEventListener('blur', () => this.validateField(fieldName));
                field.addEventListener('input', () => this.clearFieldError(fieldName));
            }
        });
        
        // Add form submit validation
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));
    }
    validateField(fieldName) {
        const field = this.form.querySelector(`[name="${fieldName}"]`);
        const rules = this.rules[fieldName];
        
        if (!field || !rules) return true;
        
        const value = field.value;
        
        // Check each rule
        for (const rule of rules) {
            const isValid = rule.validator(value);
            if (!isValid) {
                this.showFieldError(fieldName, rule.message);
                return false;
            }
        }
        
        this.showFieldSuccess(fieldName);
        delete this.errors[fieldName];
        return true;
    }
    
    validateAll() {
        let isValid = true;
        Object.keys(this.rules).forEach(fieldName => {
            if (!this.validateField(fieldName)) {
                isValid = false;
            }
        });
        return isValid;
    }
    
    showFieldError(fieldName, message) {
        const field = this.form.querySelector(`[name="${fieldName}"]`);
        const invalidFeedback = field.parentNode.querySelector('.invalid-feedback');
        const validFeedback = field.parentNode.querySelector('.valid-feedback');
        
        field.classList.add('is-invalid');
        field.classList.remove('is-valid');
        
        if (invalidFeedback) {
            invalidFeedback.style.display = 'flex';
            invalidFeedback.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
        }
        
        if (validFeedback) {
            validFeedback.style.display = 'none';
        }
        
        this.errors[fieldName] = message;
    }
    
    showFieldSuccess(fieldName) {
    const field = this.form.querySelector(`[name="${fieldName}"]`);
    const invalidFeedback = field.parentNode.querySelector('.invalid-feedback');
    const validFeedback = field.parentNode.querySelector('.valid-feedback');
    
    field.classList.remove('is-invalid');
    field.classList.add('is-valid');
    
    if (invalidFeedback) {
        invalidFeedback.style.display = 'none';
    }
    
    
    if (validFeedback) {
        validFeedback.style.display = 'none';
    }
}
    
    clearFieldError(fieldName) {
        const field = this.form.querySelector(`[name="${fieldName}"]`);
        const invalidFeedback = field.parentNode.querySelector('.invalid-feedback');
        
        if (field.classList.contains('is-invalid')) {
            field.classList.remove('is-invalid');
            if (invalidFeedback) {
                invalidFeedback.style.display = 'none';
            }
            delete this.errors[fieldName];
        }
    }
    
    handleSubmit(e) {
        e.preventDefault();
        
        if (this.validateAll()) {
            this.submitForm();
        } else {
            this.showValidationSummary();
        }
    }
    
    showValidationSummary() {
        const errorCount = Object.keys(this.errors).length;
        
        showToast({
            type: 'error',
            title: 'Validation Error',
            message: `Please fix ${errorCount} error${errorCount > 1 ? 's' : ''} before submitting.`,
            duration: 5000
        });
        
        // Focus on first error field
        const firstErrorField = Object.keys(this.errors)[0];
        if (firstErrorField) {
            const field = this.form.querySelector(`[name="${firstErrorField}"]`);
            if (field) {
                field.focus();
                field.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    }
    
    submitForm() {
        const formData = new FormData(this.form);
        const submitBtn = this.form.querySelector('button[type="submit"]');
        const btnText = submitBtn.querySelector('.btn-text');
        const btnLoading = submitBtn.querySelector('.btn-loading');
        
        // Show loading state
        this.setLoadingState(true);
        
        // Submit via AJAX
        const submitUrl = this.form.getAttribute('action') || 'ajax/process_subject.php';
        fetch(submitUrl, {
        method: 'POST',
        body: formData,
        headers: {
        'X-Requested-With': 'XMLHttpRequest'
        }
        })
        .then(response => response.json())
        .then(data => {
            this.setLoadingState(false);
            
            if (data.success) {
                this.handleSuccess(data);
            } else {
                this.handleError(data);
            }
        })
        .catch(error => {
            this.setLoadingState(false);
            console.error('Form submission error:', error);
            
            showToast({
                type: 'error',
                title: 'Submission Error',
                message: 'An unexpected error occurred. Please try again.',
                duration: 5000
            });
        });
    }
    
    setLoadingState(loading) {
        const submitBtn = this.form.querySelector('button[type="submit"]');
        const btnText = submitBtn.querySelector('.btn-text');
        const btnLoading = submitBtn.querySelector('.btn-loading');
        
        if (loading) {
            submitBtn.disabled = true;
            submitBtn.classList.add('loading');
            if (btnText) btnText.style.display = 'none';
            if (btnLoading) btnLoading.style.display = 'flex';
        } else {
            submitBtn.disabled = false;
            submitBtn.classList.remove('loading');
            if (btnText) btnText.style.display = 'flex';
            if (btnLoading) btnLoading.style.display = 'none';
        }
    }
    
    handleSuccess(data) {
        showToast({
            type: 'success',
            title: 'Success!',
            message: data.message || 'Operation completed successfully.',
            duration: 4000
        });
        
        // Reset form if specified
        if (data.reset_form !== false) {
            this.form.reset();
            this.clearAllErrors();
        }
        
        // Redirect if specified
        if (data.redirect) {
            setTimeout(() => {
                window.location.href = data.redirect;
            }, 1500);
        }
        
        // Refresh table if specified
        if (data.refresh_table) {
            refreshTable(data.refresh_table);
        }
    }
    
    handleError(data) {
        if (data.field_errors) {
            // Show field-specific errors
            Object.keys(data.field_errors).forEach(fieldName => {
                this.showFieldError(fieldName, data.field_errors[fieldName]);
            });
        } else {
            // Show general error
            showToast({
                type: 'error',
                title: 'Error',
                message: data.message || 'An error occurred while processing your request.',
                duration: 5000
            });
        }
    }
    
    clearAllErrors() {
        Object.keys(this.rules).forEach(fieldName => {
            const field = this.form.querySelector(`[name="${fieldName}"]`);
            if (field) {
                field.classList.remove('is-invalid', 'is-valid');
                const invalidFeedback = field.parentNode.querySelector('.invalid-feedback');
                const validFeedback = field.parentNode.querySelector('.valid-feedback');
                
                if (invalidFeedback) invalidFeedback.style.display = 'none';
                if (validFeedback) validFeedback.style.display = 'none';
            }
        });
        this.errors = {};
    }
}

// Toast notification system
function showToast(options = {}) {
    const {
        type = 'info',
        title = '',
        message = '',
        duration = 4000
    } = options;
    
    // Create toast container if it doesn't exist
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'toast-container';
        document.body.appendChild(toastContainer);
    }
    
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    const iconMap = {
        success: 'fas fa-check-circle',
        error: 'fas fa-exclamation-circle',
        warning: 'fas fa-exclamation-triangle',
        info: 'fas fa-info-circle'
    };
    
    toast.innerHTML = `
        <div class="toast-content">
            <div class="toast-icon">
                <i class="${iconMap[type] || iconMap.info}"></i>
            </div>
            <div class="toast-body">
                ${title ? `<div class="toast-title">${title}</div>` : ''}
                <div class="toast-message">${message}</div>
            </div>
            <button class="toast-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    // Add to container
    toastContainer.appendChild(toast);
    
    // Animate in
    requestAnimationFrame(() => {
        toast.classList.add('toast-show');
    });
    
    // Auto remove
    setTimeout(() => {
        toast.classList.remove('toast-show');
        toast.classList.add('toast-hide');
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }, duration);
}

// Utility functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    }
}

// AJAX utility
function makeRequest(url, options = {}) {
    const defaults = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };
    
    const config = { ...defaults, ...options };
    
    return fetch(url, config)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        });
}

// SweetAlert2 wrapper for consistency
function confirmDeleteAuto(url, message = 'Are you sure you want to delete this item?') {
    Swal.fire({
        title: 'Are you sure?',
        text: message,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel',
        reverseButtons: true,
        buttonsStyling: false,
        customClass: {
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-outline'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading
            Swal.fire({
                title: 'Deleting...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Make delete request
            fetch(url, {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Deleted!',
                        text: data.message || 'Item has been deleted successfully.',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    
                    // Refresh the page or specific table
                    if (data.refresh_table) {
                        refreshTable(data.refresh_table);
                    } else {
                        setTimeout(() => window.location.reload(), 1500);
                    }
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: data.message || 'An error occurred while deleting.',
                        icon: 'error'
                    });
                }
            })
            .catch(error => {
                console.error('Delete error:', error);
                Swal.fire({
                    title: 'Error!',
                    text: 'An unexpected error occurred.',
                    icon: 'error'
                });
            });
        }
    });
}

// Table refresh utility
function refreshTable(tableId) {
    const table = document.getElementById(tableId);
    if (table) {
        // Add loading state
        table.classList.add('loading');
        
        // Reload the page section containing the table
        // This is a simple approach - you could implement partial refresh
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    }
}

// ===== ADDITIONAL UTILITY FUNCTIONS FOR CLASSES.JS COMPATIBILITY =====

// Utils class for compatibility with classes.js
class Utils {
    // Set button loading state
    static setButtonLoading(button, loading = true) {
        if (!button) return;
        
        const btnText = button.querySelector('.btn-text');
        const btnLoading = button.querySelector('.btn-loading');
        
        if (loading) {
            button.disabled = true;
            button.classList.add('loading');
            if (btnText) btnText.style.display = 'none';
            if (btnLoading) btnLoading.style.display = 'flex';
        } else {
            button.disabled = false;
            button.classList.remove('loading');
            if (btnText) btnText.style.display = 'flex';
            if (btnLoading) btnLoading.style.display = 'none';
        }
    }
    
    // Make AJAX request with proper error handling
    static async makeAjaxRequest(url, formData, options = {}) {
        const defaultOptions = {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        };
        
        // Merge options
        const requestOptions = { ...defaultOptions, ...options };
        
        // Add formData to body if it exists
        if (formData) {
            requestOptions.body = formData;
        }
        
        try {
            console.log('Making request to:', url);
            const response = await fetch(url, requestOptions);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const text = await response.text();
            console.log('Raw response:', text);
            
            // Try to parse JSON
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e);
                console.error('Response was not valid JSON:', text);
                throw new Error('Server returned invalid JSON response');
            }
            
            console.log('Parsed response:', data);
            return data;
            
        } catch (error) {
            console.error('Request error:', error);
            throw error;
        }
    }
    
    // Animate new table row
    static animateNewRow(row) {
        if (!row) return;
        
        // Add the animation class
        row.classList.add('adding');
        row.style.backgroundColor = '#d4edda';
        
        // Remove the animation class and highlight after animation
        setTimeout(() => {
            row.classList.remove('adding');
            row.style.backgroundColor = '';
        }, 2000);
    }
    
    // Animate row removal
    static animateRowRemoval(row, callback) {
        if (!row) return;
        
        row.classList.add('removing');
        row.style.backgroundColor = '#f8d7da';
        row.style.transition = 'all 0.3s ease';
        row.style.opacity = '0.5';
        
        setTimeout(() => {
            if (callback) callback();
            row.remove();
        }, 300);
    }
    
    // Validate email
    static isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    // Show form validation error
    static showFieldError(fieldId, message) {
        const field = document.getElementById(fieldId);
        if (!field) return;
        
        field.classList.add('is-invalid');
        field.classList.remove('is-valid');
        
        // Find or create error message element
        let errorElement = field.parentNode.querySelector('.invalid-feedback');
        if (!errorElement) {
            errorElement = document.createElement('div');
            errorElement.className = 'invalid-feedback';
            field.parentNode.appendChild(errorElement);
        }
        
        errorElement.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
        errorElement.style.display = 'flex';
    }
    
    // Show form validation success
    static showFieldSuccess(fieldId) {
        const field = document.getElementById(fieldId);
        if (!field) return;
        
        field.classList.add('is-valid');
        field.classList.remove('is-invalid');
        
        // Hide error message
        const errorElement = field.parentNode.querySelector('.invalid-feedback');
        if (errorElement) {
            errorElement.style.display = 'none';
        }
    }
    
    // Clear field validation
    static clearFieldValidation(fieldId) {
        const field = document.getElementById(fieldId);
        if (!field) return;
        
        field.classList.remove('is-invalid', 'is-valid');
        
        const errorElement = field.parentNode.querySelector('.invalid-feedback');
        if (errorElement) {
            errorElement.style.display = 'none';
        }
    }
    
    // Debounce function
    static debounce(func, wait, immediate) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                timeout = null;
                if (!immediate) func(...args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func(...args);
        };
    }
    
    // Throttle function
    static throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }
}

// Export Utils class for global access
window.Utils = Utils;

// Export for module use (maintain your existing exports)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        FormValidator,
        ValidationRules,
        showToast,
        debounce,
        throttle,
        makeRequest,
        confirmDeleteAuto,
        Utils
    };
}
