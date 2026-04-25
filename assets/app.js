import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');

// ===== Event Registration Toast Notifications =====
function showToast(title, message, type = 'success') {
    const toastContainer = document.getElementById('eventToastContainer') || createToastContainer();
    
    const toastEl = document.createElement('div');
    toastEl.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type} border-0`;
    toastEl.setAttribute('role', 'alert');
    toastEl.setAttribute('aria-live', 'assertive');
    toastEl.setAttribute('aria-atomic', 'true');
    toastEl.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <strong>${title}:</strong> ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    
    toastContainer.appendChild(toastEl);
    const toast = new bootstrap.Toast(toastEl);
    toast.show();
    
    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'eventToastContainer';
    container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    document.body.appendChild(container);
    return container;
}

// Intercept event registration forms for AJAX
function initEventRegistrationForms() {
    document.querySelectorAll('form.event-registration-form').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const action = this.getAttribute('action');
            const button = this.querySelector('button[type="submit"]');
            const originalHTML = button.innerHTML;
            const originalDisabled = button.disabled;
            
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
            
            try {
                const response = await fetch(action, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast('Success', data.message, 'success');
                    // Reload page after a short delay to update UI
                    setTimeout(() => window.location.reload(), 1500);
                } else if (data.warning) {
                    showToast('Warning', data.message, 'warning');
                    button.disabled = originalDisabled;
                    button.innerHTML = originalHTML;
                } else {
                    showToast('Error', data.message || 'An error occurred', 'error');
                    button.disabled = originalDisabled;
                    button.innerHTML = originalHTML;
                }
            } catch (error) {
                console.error('Registration error:', error);
                showToast('Error', 'Failed to process your request: ' + error.message, 'error');
                button.disabled = originalDisabled;
                button.innerHTML = originalHTML;
            }
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initEventRegistrationForms);
} else {
    initEventRegistrationForms();
}
