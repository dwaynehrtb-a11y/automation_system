// ===== CHANGE PASSWORD PAGE JAVASCRIPT =====

// Toggle password visibility
document.querySelectorAll('.toggle-password').forEach(button => {
    button.addEventListener('click', function() {
        const targetId = this.getAttribute('data-target');
        const field = document.getElementById(targetId);
        const icon = this.querySelector('i');
        
        if (field.type === 'password') {
            field.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            field.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });
});

// Password validation with real-time feedback
const newPasswordInput = document.getElementById('new_password');

newPasswordInput.addEventListener('input', function() {
    const password = this.value;
    
    // Check length
    validateRequirement('req-length', password.length >= 8);
    
    // Check uppercase
    validateRequirement('req-uppercase', /[A-Z]/.test(password));
    
    // Check lowercase
    validateRequirement('req-lowercase', /[a-z]/.test(password));
    
    // Check number
    validateRequirement('req-number', /[0-9]/.test(password));
});

function validateRequirement(elementId, isValid) {
    const element = document.getElementById(elementId);
    const icon = element.querySelector('i');
    
    if (isValid) {
        element.classList.add('valid');
        icon.classList.remove('fa-circle');
        icon.classList.add('fa-check-circle');
    } else {
        element.classList.remove('valid');
        icon.classList.remove('fa-check-circle');
        icon.classList.add('fa-circle');
    }
}

// Form submission
document.getElementById('changePasswordForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const errorMessage = document.getElementById('errorMessage');
    const submitBtn = document.getElementById('submitBtn');
    const btnText = document.getElementById('btnText');
    const btnLoading = document.getElementById('btnLoading');
    
    // Hide previous errors
    errorMessage.style.display = 'none';
    errorMessage.textContent = '';
    
    // Get form values
    const currentPassword = document.getElementById('current_password').value;
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    // Client-side validation
    if (!validateForm(currentPassword, newPassword, confirmPassword, errorMessage)) {
        return;
    }
    
    // Show loading state
    submitBtn.disabled = true;
    btnText.style.display = 'none';
    btnLoading.style.display = 'inline-flex';
    
    // Prepare form data
    const formData = new FormData(this);
    
    try {
        const response = await fetch('process_change_password.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            await Swal.fire({
                icon: 'success',
                title: 'Password Changed!',
                text: 'Your password has been updated successfully. Redirecting to dashboard...',
                timer: 2000,
                showConfirmButton: false,
                timerProgressBar: true
            });
            
            // Redirect based on role
            window.location.href = result.redirect;
        } else {
            showError(errorMessage, result.message || 'Failed to change password. Please try again.');
            resetButton(submitBtn, btnText, btnLoading);
        }
    } catch (error) {
        console.error('Error:', error);
        showError(errorMessage, 'An error occurred. Please try again.');
        resetButton(submitBtn, btnText, btnLoading);
    }
});

// Validation function
function validateForm(currentPassword, newPassword, confirmPassword, errorMessage) {
    // Check if passwords match
    if (newPassword !== confirmPassword) {
        showError(errorMessage, 'New passwords do not match!');
        return false;
    }
    
    // Check password strength
    if (newPassword.length < 8) {
        showError(errorMessage, 'Password must be at least 8 characters long!');
        return false;
    }
    
    if (!/[A-Z]/.test(newPassword)) {
        showError(errorMessage, 'Password must contain at least one uppercase letter!');
        return false;
    }
    
    if (!/[a-z]/.test(newPassword)) {
        showError(errorMessage, 'Password must contain at least one lowercase letter!');
        return false;
    }
    
    if (!/[0-9]/.test(newPassword)) {
        showError(errorMessage, 'Password must contain at least one number!');
        return false;
    }
    
    // Check if new password is different from current
    if (currentPassword === newPassword) {
        showError(errorMessage, 'New password must be different from your current password!');
        return false;
    }
    
    return true;
}

// Show error message
function showError(errorElement, message) {
    errorElement.textContent = message;
    errorElement.style.display = 'block';
    errorElement.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// Reset button state
function resetButton(button, textElement, loadingElement) {
    button.disabled = false;
    textElement.style.display = 'inline';
    loadingElement.style.display = 'none';
}

// Prevent form resubmission on page refresh
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
