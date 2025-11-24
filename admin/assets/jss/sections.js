// Enhanced Sections Management - Final Version
// File: admin/assets/jss/sections.js
// Matches your exact HTML structure

class SectionManager {
    constructor() {
        this.editMode = false;
        this.currentEditId = null;
        this.currentEditCode = null;
        this.isSubmitting = false;
        console.log('SectionManager initialized');
        this.init();
    }
    
    init() {
        console.log('SectionManager init started');
        
        // Setup form submission
        this.setupFormSubmission();
        
        // Setup action buttons (Edit/Delete)
        this.setupActionButtons();
        
        // Setup section code formatting
        this.setupSectionCodeFormatting();
        
        console.log('SectionManager init completed');
    }
    
    setupFormSubmission() {
        const form = document.getElementById('addSectionForm');
        if (!form) {
            console.error('Form with ID "addSectionForm" not found');
            return;
        }
        
        console.log('Setting up form submission for addSectionForm');
        
        form.addEventListener('submit', (e) => {
            console.log('Form submission intercepted');
            e.preventDefault();
            this.handleSubmit(e);
        });
    }
    
    setupActionButtons() {
        console.log('Setting up action buttons');
        
        // Use event delegation for dynamically added buttons
        document.addEventListener('click', (e) => {
            const button = e.target.closest('[data-action]');
            if (!button) return;
            
            const action = button.dataset.action;
            const sectionId = button.dataset.sectionId;
            const sectionCode = button.dataset.sectionCode;
            
            console.log('Action button clicked:', { action, sectionId, sectionCode });
            
            switch (action) {
                case 'edit-section':
                    this.handleEdit(sectionId, sectionCode);
                    break;
                case 'delete-section':
                    this.handleDelete(sectionId, sectionCode);
                    break;
            }
        });
    }
    
    setupSectionCodeFormatting() {
        const sectionCodeField = document.getElementById('section_code');
        if (!sectionCodeField) {
            console.log('Section code field not found');
            return;
        }
        
        console.log('Setting up section code formatting');
        
        sectionCodeField.addEventListener('input', (e) => {
            // Auto-uppercase and allow letters, numbers, hyphens, underscores
            let value = e.target.value.toUpperCase().replace(/[^A-Z0-9\-_]/g, '');
            
            // Limit length to 20 characters
            if (value.length > 20) {
                value = value.substring(0, 20);
            }
            
            e.target.value = value;
        });
    }
    
    handleSubmit(e) {
        console.log('handleSubmit called');
        e.preventDefault();
        
        if (this.isSubmitting) {
            console.log('Already submitting, ignoring');
            return;
        }
        
        // Validate the form
        if (!this.validateForm()) {
            return;
        }
        
        // Proceed directly without confirmation dialog (like subjects.js)
        this.performSubmit();
    }
    
    validateForm() {
        const sectionCodeField = document.getElementById('section_code');
        const sectionCode = sectionCodeField.value.trim();
        
        console.log('Validating form with section code:', sectionCode);
        
        if (!sectionCode) {
            this.showError('Section code is required');
            return false;
        }
        
        if (sectionCode.length < 3) {
            this.showError('Section code must be at least 3 characters');
            return false;
        }
        
        if (sectionCode.length > 20) {
            this.showError('Section code cannot exceed 20 characters');
            return false;
        }
        
        if (!/^[A-Z0-9\-_]+$/.test(sectionCode)) {
            this.showError('Section code can only contain letters, numbers, hyphens, and underscores');
            return false;
        }
        
        console.log('Form validation passed');
        return true;
    }
    
    performSubmit() {
        console.log('performSubmit called');
        this.isSubmitting = true;
        
        // Show loading state (no SweetAlert loading)
        this.setLoadingState(true);
        
        // Prepare form data
        const formData = new FormData(document.getElementById('addSectionForm'));
        
        // Add action parameter
        if (this.editMode) {
            formData.append('action', 'update');
            formData.append('section_id', this.currentEditId);
        } else {
            formData.append('action', 'add');
        }
        
        console.log('Submitting form data:', {
            action: this.editMode ? 'update' : 'add',
            section_code: formData.get('section_code'),
            section_id: this.editMode ? this.currentEditId : 'N/A'
        });
        
        // Submit to server
        fetch('ajax/process_section.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            console.log('Response received:', response.status, response.statusText);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            return response.text();
        })
        .then(text => {
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
            
            this.setLoadingState(false);
            this.isSubmitting = false;
            
            if (data.success) {
                this.handleSuccess(data);
            } else {
                this.handleError(data);
            }
        })
        .catch(error => {
            console.error('Submission error:', error);
            
            this.setLoadingState(false);
            this.isSubmitting = false;
            
            // Use Toast error message (same as subjects.js style)
            if (window.Toast) {
                Toast.fire({
                    icon: 'error',
                    title: 'Error: ' + (error.message || 'An unexpected error occurred!')
                });
            } else {
                alert('Error: ' + (error.message || 'An unexpected error occurred!'));
            }
        });
    }
    
    handleSuccess(data) {
        console.log('handleSuccess called with:', data);
        
        if (this.editMode) {
            // Update existing row
            this.updateRowInTable(data);
            
            // Show Toast success message for edit (same as subjects.js style)
            if (window.Toast) {
                Toast.fire({ 
                    icon: 'success', 
                    title: 'Section updated successfully!' 
                });
            } else {
                alert('Section updated successfully!');
            }
            
            // Exit edit mode
            this.cancelEdit();
        } else {
            // Add new row
            if (data.newItem) {
                this.addRowToTable(data.newItem);
            }
            
            // Show Toast success message for add (same as subjects.js style)
            if (window.Toast) {
                Toast.fire({ 
                    icon: 'success', 
                    title: 'Section added successfully!' 
                });
            } else {
                alert('Section added successfully!');
            }
            
            // Reset form
            this.resetForm();
        }
    }
    
    handleError(data) {
        console.log('handleError called with:', data);
        
        // Use Toast error message (same as subjects.js style)
        if (window.Toast) {
            Toast.fire({
                icon: 'error',
                title: 'Error: ' + (data.message || 'An error occurred while processing your request.')
            });
        } else {
            alert('Error: ' + (data.message || 'An error occurred while processing your request.'));
        }
    }
    
    handleEdit(sectionId, sectionCode) {
        console.log('handleEdit called:', { sectionId, sectionCode });
        
        // Populate the form
        const sectionCodeField = document.getElementById('section_code');
        if (sectionCodeField) {
            sectionCodeField.value = sectionCode;
        }
        
        // Set edit mode
        this.editMode = true;
        this.currentEditId = sectionId;
        this.currentEditCode = sectionCode;
        
        // Update UI for edit mode
        this.updateFormForEdit();
        
        // Scroll to form
        document.getElementById('addSectionForm').scrollIntoView({ 
            behavior: 'smooth', 
            block: 'start' 
        });
        
        // Focus on input
        if (sectionCodeField) {
            sectionCodeField.focus();
        }
        
        console.log('Edit mode activated');
    }
    
    handleDelete(sectionId, sectionCode) {
        console.log('handleDelete called:', { sectionId, sectionCode });
        
        Swal.fire({
            title: 'Are you sure?',
            text: `Delete section "${sectionCode}"? This action cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                this.performDelete(sectionId, sectionCode);
            }
        });
    }
    
    performDelete(sectionId, sectionCode) {
        console.log('performDelete called:', { sectionId, sectionCode });
        
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
        fetch(`ajax/process_section.php?action=delete&id=${encodeURIComponent(sectionId)}`, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            console.log('Delete response:', response.status, response.statusText);
            return response.text();
        })
        .then(text => {
            console.log('Delete raw response:', text);
            
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('Delete JSON parse error:', e);
                throw new Error('Server returned invalid JSON response');
            }
            
            console.log('Delete parsed response:', data);
            
            if (data.success) {
                // Close the loading dialog first
                Swal.close();
                
                // Show Toast success message for delete (same as subjects.js style)
                if (window.Toast) {
                    Toast.fire({ 
                        icon: 'success', 
                        title: 'Section deleted successfully!' 
                    });
                } else {
                    alert('Section deleted successfully!');
                }
                
                // Remove row from table
                this.removeRowFromTable(sectionId);
            } else {
                // Show Toast error message (same as subjects.js style)
                if (window.Toast) {
                    Toast.fire({
                        icon: 'error',
                        title: 'Error: ' + (data.message || 'An error occurred while deleting.')
                    });
                } else {
                    alert('Error: ' + (data.message || 'An error occurred while deleting.'));
                }
            }
        })
        .catch(error => {
            console.error('Delete error:', error);
            
            // Show Toast error message (same as subjects.js style)
            if (window.Toast) {
                Toast.fire({
                    icon: 'error',
                    title: 'Error: An unexpected error occurred.'
                });
            } else {
                alert('Error: An unexpected error occurred.');
            }
        });
    }
    
    updateFormForEdit() {
        const form = document.getElementById('addSectionForm');
        const header = form.closest('.form-section').querySelector('.form-header h3');
        const subtitle = form.closest('.form-section').querySelector('.form-subtitle');
        const submitBtn = form.querySelector('button[type="submit"] .btn-text');
        
        // Update header
        if (header) {
            header.innerHTML = '<i class="fas fa-edit"></i> Edit Section';
        }
        if (subtitle) {
            subtitle.textContent = 'Update the section information';
        }
        
        // Update button text
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Update Section';
        }
        
        // Add cancel button if not exists
        if (!form.querySelector('.btn-cancel-edit')) {
            const cancelBtn = document.createElement('button');
            cancelBtn.type = 'button';
            cancelBtn.className = 'btn btn-outline btn-cancel-edit';
            cancelBtn.innerHTML = '<i class="fas fa-times"></i> Cancel';
            cancelBtn.addEventListener('click', () => this.cancelEdit());
            
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton && submitButton.parentNode) {
                submitButton.parentNode.insertBefore(cancelBtn, submitButton);
            }
        }
    }
    
    cancelEdit() {
        console.log('cancelEdit called');
        
        this.editMode = false;
        this.currentEditId = null;
        this.currentEditCode = null;
        
        // Reset form
        this.resetForm();
        
        // Update UI back to add mode
        this.updateFormForAdd();
    }
    
    updateFormForAdd() {
        const form = document.getElementById('addSectionForm');
        const header = form.closest('.form-section').querySelector('.form-header h3');
        const subtitle = form.closest('.form-section').querySelector('.form-subtitle');
        const submitBtn = form.querySelector('button[type="submit"] .btn-text');
        const cancelBtn = form.querySelector('.btn-cancel-edit');
        
        // Update header
        if (header) {
            header.innerHTML = '<i class="fas fa-plus-circle"></i> Section Management';
        }
        if (subtitle) {
            subtitle.textContent = 'Add and manage academic sections';
        }
        
        // Update button text
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fas fa-plus"></i> Add Section';
        }
        
        // Remove cancel button
        if (cancelBtn) {
            cancelBtn.remove();
        }
    }
    
    resetForm() {
        const form = document.getElementById('addSectionForm');
        if (form) {
            form.reset();
        }
    }
    
    setLoadingState(loading) {
        const submitBtn = document.getElementById('addSectionBtn');
        const btnText = submitBtn.querySelector('.btn-text');
        const btnLoading = submitBtn.querySelector('.btn-loading');
        
        if (loading) {
            submitBtn.disabled = true;
            if (btnText) btnText.style.display = 'none';
            if (btnLoading) btnLoading.style.display = 'flex';
        } else {
            submitBtn.disabled = false;
            if (btnText) btnText.style.display = 'flex';
            if (btnLoading) btnLoading.style.display = 'none';
        }
    }
    
    addRowToTable(newItem) {
        console.log('addRowToTable called with:', newItem);
        
        const tableBody = document.querySelector('#sectionsTable tbody');
        if (!tableBody) {
            console.log('Table body not found, reloading page');
            setTimeout(() => window.location.reload(), 1000);
            return;
        }
        
        // Remove "no sections" message if exists
        const noDataRow = tableBody.querySelector('td[colspan="3"]');
        if (noDataRow) {
            noDataRow.closest('tr').remove();
        }
        
        // Create new row
        const newRow = document.createElement('tr');
        const currentDate = new Date().toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        });
        
        newRow.innerHTML = `
            <td><strong>${newItem.section_code}</strong></td>
            <td>${currentDate}</td>
            <td>
                <div class="action-buttons">
                    <button data-action="edit-section" 
                            data-section-id="${newItem.section_id}" 
                            data-section-code="${newItem.section_code}"
                            class="btn btn-sm btn-warning btn-icon"
                            title="Edit Section">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button data-action="delete-section" 
                            data-section-id="${newItem.section_id}" 
                            data-section-code="${newItem.section_code}"
                            class="btn btn-sm btn-danger btn-icon"
                            title="Delete Section">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
        
        // Add to table (at the top)
        tableBody.insertBefore(newRow, tableBody.firstChild);
        
        // Highlight briefly
        newRow.style.backgroundColor = '#d4edda';
        setTimeout(() => {
            newRow.style.backgroundColor = '';
        }, 2000);
    }
    
    updateRowInTable(data) {
        console.log('updateRowInTable called');
        
        const tableBody = document.querySelector('#sectionsTable tbody');
        if (!tableBody) {
            console.log('Table body not found, reloading page');
            setTimeout(() => window.location.reload(), 1000);
            return;
        }
        
        // Find the row to update
        const editButton = tableBody.querySelector(`[data-section-id="${this.currentEditId}"]`);
        if (!editButton) {
            console.log('Row not found, reloading page');
            setTimeout(() => window.location.reload(), 1000);
            return;
        }
        
        const row = editButton.closest('tr');
        const newSectionCode = document.getElementById('section_code').value;
        
        // Update the row
        const cells = row.querySelectorAll('td');
        cells[0].innerHTML = `<strong>${newSectionCode}</strong>`;
        
        // Update button data attributes
        const buttons = row.querySelectorAll('[data-section-code]');
        buttons.forEach(btn => {
            btn.dataset.sectionCode = newSectionCode;
        });
        
        // Highlight briefly
        row.style.backgroundColor = '#d4edda';
        setTimeout(() => {
            row.style.backgroundColor = '';
        }, 2000);
    }
    
    removeRowFromTable(sectionId) {
        console.log('removeRowFromTable called:', sectionId);
        
        const tableBody = document.querySelector('#sectionsTable tbody');
        if (!tableBody) {
            console.log('Table body not found, reloading page');
            setTimeout(() => window.location.reload(), 1000);
            return;
        }
        
        // Find and remove the row
        const deleteButton = tableBody.querySelector(`[data-section-id="${sectionId}"]`);
        if (!deleteButton) {
            console.log('Row not found, reloading page');
            setTimeout(() => window.location.reload(), 1000);
            return;
        }
        
        const row = deleteButton.closest('tr');
        
        // Animate removal
        row.style.backgroundColor = '#f8d7da';
        row.style.transition = 'all 0.3s ease';
        row.style.opacity = '0.5';
        
        setTimeout(() => {
            row.remove();
            
            // Check if table is empty
            const remainingRows = tableBody.querySelectorAll('tr');
            if (remainingRows.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="3" style="text-align: center; color: var(--text-muted);">No sections added yet</td></tr>';
            }
        }, 300);
    }
    
    showError(message) {
        console.error('Error:', message);
        
        // Use Toast error message (same as subjects.js style)
        if (window.Toast) {
            Toast.fire({
                icon: 'error',
                title: 'Error: ' + message
            });
        } else {
            alert('Error: ' + message);
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM loaded, initializing Section Manager...');
    
    // Check if we're on the sections page
    const sectionsForm = document.getElementById('addSectionForm');
    const sectionsTable = document.getElementById('sectionsTable');
    
    if (sectionsForm || sectionsTable) {
        console.log('Sections management elements found, initializing...');
        window.sectionManager = new SectionManager();
    } else {
        console.log('No sections management elements found on this page');
    }
});
