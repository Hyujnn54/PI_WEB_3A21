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

// ===== Event Registration Forms (AJAX) =====
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

// ===== Event Filtering =====
function initEventFilters() {
    const filterForm = document.querySelector('.tb-filter-form');
    if (!filterForm) return;
    
    const searchInput = filterForm.querySelector('[data-filter-search]');
    const typeSelect = filterForm.querySelector('[data-filter-type-select]');
    const sortSelect = filterForm.querySelector('[data-filter-sort]');
    const resultCounter = filterForm.querySelector('[data-filter-results]');
    const gridTarget = filterForm.getAttribute('data-target');
    const itemsContainer = document.querySelector(gridTarget);
    
    if (!itemsContainer) return;
    
    function applyFilters() {
        const searchTerm = searchInput?.value.toLowerCase() || '';
        const selectedType = typeSelect?.value.toLowerCase() || '';
        const sortBy = sortSelect?.value || 'default';
        
        let items = Array.from(itemsContainer.querySelectorAll('.tb-filter-item'));
        let visibleCount = 0;
        
        items.forEach(item => {
            const text = item.getAttribute('data-filter-text') || '';
            const type = item.getAttribute('data-filter-type') || '';
            
            const matchesSearch = searchTerm === '' || text.includes(searchTerm);
            const matchesType = selectedType === '' || type === selectedType;
            
            const shouldShow = matchesSearch && matchesType;
            item.style.display = shouldShow ? '' : 'none';
            
            if (shouldShow) visibleCount++;
        });
        
        // Sort items
        items.sort((a, b) => {
            if (sortBy === 'default') return 0;
            if (sortBy === 'title_asc') return (a.getAttribute('data-sort-title') || '').localeCompare(b.getAttribute('data-sort-title') || '');
            if (sortBy === 'title_desc') return (b.getAttribute('data-sort-title') || '').localeCompare(a.getAttribute('data-sort-title') || '');
            if (sortBy === 'meta_asc') return (a.getAttribute('data-sort-meta') || '').localeCompare(b.getAttribute('data-sort-meta') || '');
            if (sortBy === 'meta_desc') return (b.getAttribute('data-sort-meta') || '').localeCompare(a.getAttribute('data-sort-meta') || '');
            return 0;
        });
        
        if (resultCounter) {
            resultCounter.textContent = `${visibleCount} result${visibleCount !== 1 ? 's' : ''}`;
        }
    }
    
    searchInput?.addEventListener('input', applyFilters);
    typeSelect?.addEventListener('change', applyFilters);
    sortSelect?.addEventListener('change', applyFilters);
}

// ===== Event Detail Panel & Modal =====
function initEventDetailPanel() {
    const detailButton = document.getElementById('candidateEventDetailBtn');
    const modalEl = document.getElementById('candidateEventDetailModal');
    
    if (!detailButton || !modalEl || typeof bootstrap === 'undefined') {
        return;
    }
    
    const modal = new bootstrap.Modal(modalEl);
    
    const findSelectedCard = function () {
        return document.querySelector('.tb-module-card.is-selected');
    };
    
    const syncButtonState = function () {
        detailButton.disabled = !findSelectedCard();
    };
    
    // Use event delegation for click events
    document.addEventListener('click', function(e) {
        const card = e.target.closest('.tb-module-card');
        if (card) {
            document.querySelectorAll('.tb-module-card').forEach(c => c.classList.remove('is-selected'));
            card.classList.add('is-selected');
            syncButtonState();
        }
    });
    
    syncButtonState();
    
    detailButton.addEventListener('click', function () {
        const card = findSelectedCard();
        if (!card) {
            return;
        }
        
        const title = card.getAttribute('data-detail-title') || 'Event Details';
        const meta = card.getAttribute('data-detail-meta') || '';
        const description = card.getAttribute('data-detail-text') || '';
        const eventType = card.getAttribute('data-event-type') || 'N/A';
        const location = card.getAttribute('data-event-location') || 'N/A';
        const capacity = card.getAttribute('data-event-capacity') || 'N/A';
        const eventDateValue = card.getAttribute('data-event-date-value') || '';
        
        modalEl.querySelector('.modal-title').textContent = title;
        document.getElementById('candidateEventDetailMeta').textContent = meta;
        document.getElementById('candidateEventDetailType').textContent = eventType;
        document.getElementById('candidateEventDetailLocation').textContent = location;
        document.getElementById('candidateEventDetailCapacity').textContent = capacity;
        document.getElementById('candidateEventDetailDescription').textContent = description;
        
        if (eventDateValue !== '') {
            const parsedDate = new Date(eventDateValue);
            document.getElementById('candidateEventDetailDate').textContent = isNaN(parsedDate.getTime())
                ? eventDateValue
                : parsedDate.toLocaleString();
        } else {
            document.getElementById('candidateEventDetailDate').textContent = 'N/A';
        }
        
        modal.show();
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('padding-right');

        document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
            backdrop.remove();
        });
    });
}

// ===== Initialize on Page Load & After Pagination =====
function initializeAll() {
    initEventRegistrationForms();
    initEventFilters();
    initEventDetailPanel();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeAll);
} else {
    initializeAll();
}

// Re-initialize after pagination (if using AJAX pagination)
document.addEventListener('pjax:complete', initializeAll);
document.addEventListener('turbo:load', initializeAll);
