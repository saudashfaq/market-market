// Simple Form Validation System
document.addEventListener('DOMContentLoaded', function() {
    console.log('Validation system loaded');
    
    // Add validation to all forms
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
                return false;
            }
        });
    });

    // Add real-time validation to inputs
    const inputs = document.querySelectorAll('input, textarea, select');
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            validateField(this);
        });
        
        input.addEventListener('input', function() {
            clearError(this);
        });
    });
});

function validateForm(form) {
    let isValid = true;
    let firstError = null;
    
    // Check required fields
    const requiredFields = form.querySelectorAll('[required]');
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            showError(field, getFieldName(field) + ' is required');
            isValid = false;
            if (!firstError) firstError = field;
        }
    });
    
    // Check email fields
    const emailFields = form.querySelectorAll('input[type="email"]');
    emailFields.forEach(field => {
        if (field.value.trim() && !isValidEmail(field.value)) {
            showError(field, 'Please enter a valid email address');
            isValid = false;
            if (!firstError) firstError = field;
        }
    });
    
    // Check password fields
    const passwordFields = form.querySelectorAll('input[type="password"]');
    passwordFields.forEach(field => {
        if (field.value && field.value.length < 6) {
            showError(field, 'Password must be at least 6 characters long');
            isValid = false;
            if (!firstError) firstError = field;
        }
    });
    
    // Check confirm password
    const confirmPassword = form.querySelector('input[name="confirm_password"], input[name="confirmPassword"]');
    const password = form.querySelector('input[name="password"]');
    if (confirmPassword && password && confirmPassword.value !== password.value) {
        showError(confirmPassword, 'Passwords do not match');
        isValid = false;
        if (!firstError) firstError = confirmPassword;
    }
    
    // Check phone numbers
    const phoneFields = form.querySelectorAll('input[type="tel"], input[name*="phone"]');
    phoneFields.forEach(field => {
        if (field.value.trim() && !isValidPhone(field.value)) {
            showError(field, 'Please enter a valid phone number');
            isValid = false;
            if (!firstError) firstError = field;
        }
    });
    
    // Check URLs
    const urlFields = form.querySelectorAll('input[type="url"], input[name*="url"], input[name*="website"]');
    urlFields.forEach(field => {
        if (field.value.trim() && !isValidUrl(field.value)) {
            showError(field, 'Please enter a valid URL');
            isValid = false;
            if (!firstError) firstError = field;
        }
    });
    
    // Focus on first error
    if (firstError) {
        firstError.focus();
        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    
    return isValid;
}

function validateField(field) {
    clearError(field);
    
    // Required field check
    if (field.hasAttribute('required') && !field.value.trim()) {
        showError(field, getFieldName(field) + ' is required');
        return false;
    }
    
    // Email validation
    if (field.type === 'email' && field.value.trim() && !isValidEmail(field.value)) {
        showError(field, 'Please enter a valid email address');
        return false;
    }
    
    // Password validation
    if (field.type === 'password' && field.value && field.value.length < 6) {
        showError(field, 'Password must be at least 6 characters long');
        return false;
    }
    
    // Phone validation
    if ((field.type === 'tel' || field.name.includes('phone')) && field.value.trim() && !isValidPhone(field.value)) {
        showError(field, 'Please enter a valid phone number');
        return false;
    }
    
    // URL validation
    if ((field.type === 'url' || field.name.includes('url') || field.name.includes('website')) && field.value.trim() && !isValidUrl(field.value)) {
        showError(field, 'Please enter a valid URL');
        return false;
    }
    
    return true;
}

function showError(field, message) {
    clearError(field);
    
    // Add error class to field
    field.classList.add('error', 'border-red-500');
    
    // Create error message element
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message text-red-500 text-sm mt-1';
    errorDiv.textContent = message;
    errorDiv.setAttribute('data-field', field.name || field.id);
    
    // Insert error message after field
    field.parentNode.insertBefore(errorDiv, field.nextSibling);
    
    // Add shake animation
    field.style.animation = 'shake 0.5s ease-in-out';
    setTimeout(() => {
        field.style.animation = '';
    }, 500);
}

function clearError(field) {
    // Remove error classes
    field.classList.remove('error', 'border-red-500');
    
    // Remove error message
    const errorMsg = field.parentNode.querySelector('.error-message[data-field="' + (field.name || field.id) + '"]');
    if (errorMsg) {
        errorMsg.remove();
    }
}

function getFieldName(field) {
    // Try to get field name from label, placeholder, or name attribute
    const label = document.querySelector('label[for="' + field.id + '"]');
    if (label) {
        return label.textContent.replace('*', '').trim();
    }
    
    if (field.placeholder) {
        return field.placeholder;
    }
    
    if (field.name) {
        return field.name.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
    }
    
    return 'This field';
}

function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function isValidPhone(phone) {
    // Remove all non-digit characters
    const cleanPhone = phone.replace(/\D/g, '');
    // Check if it's between 10-15 digits
    return cleanPhone.length >= 10 && cleanPhone.length <= 15;
}

function isValidUrl(url) {
    try {
        // Add protocol if missing
        if (!url.startsWith('http://') && !url.startsWith('https://')) {
            url = 'https://' + url;
        }
        new URL(url);
        return true;
    } catch (e) {
        return false;
    }
}

// Add CSS for animations and error styles
if (!document.querySelector('#validation-styles')) {
    const style = document.createElement('style');
    style.id = 'validation-styles';
    style.textContent = `
        .error {
            border-color: #ef4444 !important;
            box-shadow: 0 0 0 1px #ef4444 !important;
        }
        
        .error-message {
            color: #ef4444;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    `;
    document.head.appendChild(style);
}

// Export functions for manual use
window.ValidationUtils = {
    validateForm: validateForm,
    validateField: validateField,
    showError: showError,
    clearError: clearError,
    isValidEmail: isValidEmail,
    isValidPhone: isValidPhone,
    isValidUrl: isValidUrl
};

console.log('✅ Simple validation system loaded successfully');
       