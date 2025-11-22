// Subject form validation rules 
const subjectValidationRules = {
    course_code: [
        {
            validator: ValidationRules.required,
            message: 'Course code is required'
        },
        {
            // FIXED: Custom validator for new format (CTAPROJ1, SYSAD101, etc.)
            validator: function(value) {
                if (!value) return false;
                // New format: 3-15 alphanumeric characters
                const pattern = /^[A-Z0-9]{3,15}$/;
                return pattern.test(value.toString().toUpperCase());
            },
            message: 'Course code must be 3-15 alphanumeric characters (e.g., CTAPROJ1, SYSAD101)'
        },
        {
            validator: ValidationRules.maxLength(50),
            message: 'Course code cannot exceed 50 characters'
        }
    ],
    course_title: [
        {
            validator: ValidationRules.required,
            message: 'Course title is required'
        },
        {
            validator: ValidationRules.minLength(3),
            message: 'Course title must be at least 3 characters'
        },
        {
            validator: ValidationRules.maxLength(255),
            message: 'Course title cannot exceed 255 characters'
        }
    ],
    units: [
        {
            validator: ValidationRules.required,
            message: 'Units selection is required'
        },
        {
            validator: function(value) {
                const numValue = parseInt(value);
                return numValue >= 1 && numValue <= 5;
            },
            message: 'Units must be between 1 and 5'
        }
    ],
    course_desc: [
        {
            validator: ValidationRules.maxLength(500),
            message: 'Description cannot exceed 500 characters'
        }
    ]
};

// Subject management class
class SubjectManager {
    constructor() {
        this.validator = null;
        this.editMode = false;
        this.currentEditCode = null;
        this.isSubmitting = false;
        this.init();
    }
    
    init() {
        // Initialize form validator
        this.validator = new FormValidator('addSubjectForm', subjectValidationRules);
        
        // Get the form
        const form = document.getElementById('addSubjectForm');
        if (form) {
            // Remove ALL existing event listeners by cloning the form
            const newForm = form.cloneNode(true);
            form.parentNode.replaceChild(newForm, form);
            
            // Now add our single event listener to the clean form
            newForm.addEventListener('submit', (e) => this.handleSubmit(e));
            
            // Re-initialize validator with the new form
            this.validator = new FormValidator('addSubjectForm', subjectValidationRules);
        }

        // Add real-time course code checking
        this.setupCourseCodeValidation();

        // Add real-time course title validation
        this.setupCourseTitleValidation();

        // Add real-time units validation
        this.setupUnitsValidation();

        // Setup character counters
        this.setupCharacterCounters();

        // Auto-format course code
        this.setupCourseCodeFormatting();

        // Setup edit functionality
        this.setupEditHandlers();
    }
    
    setupCourseCodeValidation() {
        const courseCodeField = document.getElementById('subject_course_code')
        if (!courseCodeField) return;
        
        const checkCourseCode = debounce(async (value) => {
            if (!value || value.length < 3) return; // Changed from 10 to 3 for new format
            
            try {
                const response = await makeRequest(`ajax/check_course_code.php?code=${encodeURIComponent(value)}`);
                
                if (response.exists && !this.editMode) {
                    this.validator.showFieldError('course_code', 'This course code already exists');
                } else if (response.exists && this.editMode && value !== this.currentEditCode) {
                    this.validator.showFieldError('course_code', 'This course code already exists');
                } else {
                    if (this.validator.validateField('course_code')) {
                        this.validator.showFieldSuccess('course_code');
                    }
                }
            } catch (error) {
                console.error('Course code validation error:', error);
                // Don't show error to user for validation checks
            }
        }, 500);
        
        courseCodeField.addEventListener('input', (e) => {
            const value = e.target.value.trim();
            if (value.length >= 3) { // Changed from 10 to 3
                checkCourseCode(value);
            }
        });
    }

    setupCourseTitleValidation() {
        const courseTitleField = document.getElementById('course_title');
        if (!courseTitleField) return;
        
        const validateTitle = debounce((value) => {
            if (!value || value.length < 3) {
                return;
            }
            
            // Check if field passes validation rules
            if (this.validator.validateField('course_title')) {
                this.validator.showFieldSuccess('course_title');
            }
        }, 300);
        
        courseTitleField.addEventListener('input', (e) => {
            const value = e.target.value.trim();
            if (value.length >= 3) {
                validateTitle(value);
            } else {
                // Clear any existing success state for short titles
                this.validator.clearFieldError('course_title');
            }
        });
        
        // Also validate on blur
        courseTitleField.addEventListener('blur', (e) => {
            const value = e.target.value.trim();
            if (value.length >= 3) {
                if (this.validator.validateField('course_title')) {
                    this.validator.showFieldSuccess('course_title');
                }
            }
        });
    }

    setupUnitsValidation() {
        const unitsField = document.getElementById('units');
        if (!unitsField) return;
        
        unitsField.addEventListener('change', (e) => {
            const value = e.target.value;
            if (value && parseInt(value) >= 1 && parseInt(value) <= 5) {
                this.validator.showFieldSuccess('units');
            } else {
                this.validator.clearFieldError('units');
            }
        });
        
        // Also check on input for immediate feedback
        unitsField.addEventListener('input', (e) => {
            const value = e.target.value;
            if (value && parseInt(value) >= 1 && parseInt(value) <= 5) {
                this.validator.showFieldSuccess('units');
            }
        });
    }
    
    updateRowInTable(data) {
        // Find the subjects table specifically
        const subjectsTable = document.querySelector('#subjects .table tbody');
        
        if (!subjectsTable) {
            console.log('Subjects table not found for update');
            setTimeout(() => window.location.reload(), 1000);
            return;
        }
        
        // Find the row to update
        const rows = subjectsTable.querySelectorAll('tr');
        let rowFound = false;
        
        for (let row of rows) {
            const codeCell = row.querySelector('td:first-child strong');
            if (codeCell && codeCell.textContent.trim() === this.currentEditCode) {
                // Get the updated data from the form
                const courseCode = document.getElementById('subject_course_code').value;
                const courseTitle = document.getElementById('course_title').value;
                const courseDesc = document.getElementById('course_desc').value || 'No description';
                const units = document.getElementById('units').value;
                
                // Update cell contents
                const cells = row.querySelectorAll('td');
                cells[0].innerHTML = `<strong>${courseCode}</strong>`;
                cells[1].textContent = courseTitle;
                cells[2].textContent = courseDesc;
                cells[3].innerHTML = `<span class="badge badge-primary">${units} unit${units > 1 ? 's' : ''}</span>`;
                
                // Update the action buttons with new data
                const editBtn = row.querySelector('button[onclick*="editSubject"]');
                const deleteBtn = row.querySelector('button[onclick*="deleteSubject"]');
                
                if (editBtn) {
                    editBtn.setAttribute('onclick', `editSubject('${courseCode}', '${courseTitle}', '${courseDesc}', '${units}')`);
                }
                if (deleteBtn) {
                    deleteBtn.setAttribute('onclick', `deleteSubject('${courseCode}')`);
                }
                
                // Highlight the updated row briefly
                row.style.backgroundColor = '#d4edda';
                setTimeout(() => {
                    row.style.backgroundColor = '';
                }, 2000);
                
                rowFound = true;
                break;
            }
        }
        
        if (!rowFound) {
            console.log('Row not found for update, reloading page...');
            setTimeout(() => window.location.reload(), 1000);
        }
    }

    setupCharacterCounters() {
        const fields = [
            { id: 'course_code', max: 50 },
            { id: 'course_title', max: 255 },
            { id: 'course_desc', max: 500 }
        ];
        
        fields.forEach(field => {
            const element = document.getElementById(field.id);
            if (!element) return;
            
            // Check if counter already exists to prevent duplicates
            if (element.parentNode.querySelector('.character-counter')) {
                return;
            }
            
            // Create counter element
            const counter = document.createElement('div');
            counter.className = 'character-counter';
            counter.innerHTML = `<small class="text-muted">0 / ${field.max} characters</small>`;
            
            // Insert after the field
            element.parentNode.appendChild(counter);
            
            // Update counter on input
            element.addEventListener('input', () => {
                const length = element.value.length;
                const counterText = counter.querySelector('small');
                counterText.textContent = `${length} / ${field.max} characters`;
                
                // Change color based on usage
                if (length > field.max * 0.9) {
                    counterText.className = 'text-warning';
                } else if (length === field.max) {
                    counterText.className = 'text-danger';
                } else {
                    counterText.className = 'text-muted';
                }
            });
        });
    }

    resetCharacterCounters() {
        const fields = ['course_code', 'course_title', 'course_desc'];
        
        fields.forEach(fieldId => {
            const element = document.getElementById(fieldId);
            if (!element) return;
            
            const counter = element.parentNode.querySelector('.character-counter small');
            if (counter) {
                // Reset counter text and styling
                const maxLength = fieldId === 'course_code' ? 50 : 
                                fieldId === 'course_title' ? 255 : 500;
                counter.textContent = `0 / ${maxLength} characters`;
                counter.className = 'text-muted';
            }
        });
    }
    
    setupCourseCodeFormatting() {
        const courseCodeField = document.getElementById('subject_course_code');
        if (!courseCodeField) return;
        
        courseCodeField.addEventListener('input', (e) => {
            // Auto-uppercase and allow only alphanumeric (no underscores for new format)
            let value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            
            // Limit length to 15 characters (reasonable max for new format)
            if (value.length > 15) {
                value = value.substring(0, 15);
            }
            
            e.target.value = value;
        });
    }
    
    setupEditHandlers() {
        // Add edit buttons click handlers
        document.addEventListener('click', (e) => {
            if (e.target.matches('.btn-edit-subject') || e.target.closest('.btn-edit-subject')) {
                const btn = e.target.matches('.btn-edit-subject') ? e.target : e.target.closest('.btn-edit-subject');
                this.handleEdit(btn);
            }
        });
    }
    
    handleSubmit(e) {
        e.preventDefault();
        e.stopPropagation(); // Prevent event bubbling
        
        // Prevent multiple submissions
        if (this.isSubmitting) {
            return;
        }
        
        // Validate form
        if (!this.validator.validateAll()) {
            this.validator.showValidationSummary();
            return;
        }
        
        // For both add and edit, proceed directly
        this.performSubmit();
    }

    performSubmit() {
    // Set submitting flag
    this.isSubmitting = true;
    
    // Set loading state for both add and edit operations
    this.setLoadingState(true);
    
    // Prepare form data
    const formData = new FormData(document.getElementById('addSubjectForm'));
    const submitUrl = 'ajax/process_subject.php';

    // Add action parameter
    if (this.editMode) {
        formData.append('action', 'update');
        formData.append('original_code', this.currentEditCode);
    } else {
        formData.append('action', 'add');
    }
    
    //  ADD COURSE OUTCOMES DATA
    if (window.outcomesManager) {
        const outcomes = window.outcomesManager.getOutcomesData();
        console.log(' Outcomes being sent:', outcomes); 
        formData.append('course_outcomes', JSON.stringify(outcomes));
    }
    
    //  ADD CO-SO MAPPINGS DATA
    if (window.outcomesManager) {
        const mappings = window.outcomesManager.getMappingsData();
        console.log(' Mappings being sent:', mappings); 
        console.log(' Raw mappings object:', window.outcomesManager.mappings); 
        formData.append('coso_mappings', JSON.stringify(mappings));
    }
    
    // Submit
    fetch(submitUrl, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        this.setLoadingState(false);
        this.isSubmitting = false;
        
        if (data.success) {
            this.handleSuccess(data);
        } else {
            this.handleError(data);
        }
    })
    .catch(error => {
        this.setLoadingState(false);
        this.isSubmitting = false;
        console.error('Subject submission error:', error);
        
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

    handleEdit(button) {
        const row = button.closest('tr');
        if (!row) return;
        
        // Extract data from table row
        const cells = row.querySelectorAll('td');
        const courseCode = cells[0].textContent.trim();
        const courseTitle = cells[1].textContent.trim();
        const courseDesc = cells[2].textContent.trim();
        const unitsText = cells[3].textContent.trim();
        const units = unitsText.match(/\d+/)[0];
        
        // Populate form
        document.getElementById('subject_course_code').value = courseCode;
        document.getElementById('course_title').value = courseTitle;
        document.getElementById('course_desc').value = courseDesc === 'No description' ? '' : courseDesc;
        document.getElementById('units').value = units;
        
        // Set edit mode to true
        this.editMode = true;
        this.currentEditCode = courseCode;
        
        // Update form UI
        this.updateFormForEdit();
        
        // Clear any existing validation states
        this.validator.clearAllErrors();
        
        // Scroll to form
        document.getElementById('addSubjectForm').scrollIntoView({ 
            behavior: 'smooth', 
            block: 'start' 
        });
        
        // Focus on title field
        document.getElementById('course_title').focus();
    }

    updateFormForEdit() {
        const form = document.getElementById('addSubjectForm');
        const header = form.closest('.form-section').querySelector('.form-header h3');
        const subtitle = form.closest('.form-section').querySelector('.form-subtitle');
        const submitBtn = form.querySelector('button[type="submit"]');
        const btnText = submitBtn.querySelector('.btn-text');
        
        // Update header
        header.innerHTML = '<i class="fas fa-edit"></i> Edit Subject';
        subtitle.textContent = 'Update the course subject information';
        
        // Update button
        btnText.innerHTML = '<i class="fas fa-save"></i> Update Subject';
        
        // Add cancel button
        if (!form.querySelector('.btn-cancel-edit')) {
            const cancelBtn = document.createElement('button');
            cancelBtn.type = 'button';
            cancelBtn.className = 'btn btn-outline btn-cancel-edit';
            cancelBtn.innerHTML = '<i class="fas fa-times"></i> Cancel';
            cancelBtn.addEventListener('click', () => this.cancelEdit());
            
            submitBtn.parentNode.insertBefore(cancelBtn, submitBtn);
        }
        
        // Enable course code field
        const courseCodeField = document.getElementById('subject_course_code');
        courseCodeField.removeAttribute('readonly');
        courseCodeField.style.backgroundColor = '';
    }

    cancelEdit() {
    this.editMode = false;
    this.currentEditCode = null;
    
    // Reset form
    document.getElementById('addSubjectForm').reset();
    
    // Reset character counters
    this.resetCharacterCounters();
    
    // Clear course outcomes
    if (window.outcomesManager) {
        window.outcomesManager.clearAll();
    }
    
    // Update form UI
    this.updateFormForAdd();
    
    // Clear validation states
    this.validator.clearAllErrors();
}

    updateFormForAdd() {
        const form = document.getElementById('addSubjectForm');
        const header = form.closest('.form-section').querySelector('.form-header h3');
        const subtitle = form.closest('.form-section').querySelector('.form-subtitle');
        const submitBtn = form.querySelector('button[type="submit"]');
        const btnText = submitBtn.querySelector('.btn-text');
        const cancelBtn = form.querySelector('.btn-cancel-edit');
        
        // Update header
        header.innerHTML = '<i class="fas fa-plus-circle"></i> Add New Subject';
        subtitle.textContent = 'Create a new course subject for the academic system';
        
        // Update button
        btnText.innerHTML = '<i class="fas fa-save"></i> Add Subject';
        
        // Remove cancel button
        if (cancelBtn) {
            cancelBtn.remove();
        }
        
        // Enable course code field
        const courseCodeField = document.getElementById('subject_course_code');
        courseCodeField.removeAttribute('readonly');
        courseCodeField.style.backgroundColor = '';
    }

    setLoadingState(loading) {
        const submitBtn = document.querySelector('#addSubjectForm button[type="submit"]');
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

    handleSuccess(data) {
    if (this.editMode) {
        // Update the existing row first
        this.updateRowInTable(data);
        
        // Show Toast success message for edit
        if (window.Toast) {
            Toast.fire({ 
                icon: 'success', 
                title: 'Subject updated successfully!' 
            });
        } else {
            alert('Subject updated successfully!');
        }
        
        // Exit edit mode and clear outcomes
        this.cancelEdit();
        
        // Clear course outcomes
        if (window.outcomesManager) {
            window.outcomesManager.clearAll();
        }
        
    } else {
        // Show Toast success message for add
        if (window.Toast) {
            Toast.fire({ 
                icon: 'success', 
                title: 'Subject added successfully!' 
            });
        } else {
            alert('Subject added successfully!');
        }
        
        // Reset form and add to table
        document.getElementById('addSubjectForm').reset();
        this.validator.clearAllErrors();
        this.resetCharacterCounters();
        
        // Clear course outcomes
        if (window.outcomesManager) {
            window.outcomesManager.clearAll();
        }

        if (data.newItem) {
            this.addRowToTable(data.newItem);
        }
    }
}

    addRowToTable(newItem) {
        const subjectsTable = document.querySelector('#subjects .table tbody');
        
        if (!subjectsTable) {
            console.log('Subjects table not found for adding');
            setTimeout(() => window.location.reload(), 1000);
            return;
        }
        
        // Check if table has "no subjects" message and remove it
        const noDataRow = subjectsTable.querySelector('tr td[colspan="5"]');
        if (noDataRow) {
            noDataRow.closest('tr').remove();
        }
        
        const newRow = document.createElement('tr');
        newRow.innerHTML = `
            <td><strong>${newItem.course_code}</strong></td>
            <td>${newItem.course_title}</td>
            <td>${newItem.course_desc || 'No description'}</td>
            <td><span class="badge badge-primary">${newItem.units} unit${newItem.units > 1 ? 's' : ''}</span></td>
            <td>
                <div class="d-flex gap-3">
                    <button onclick="editSubject('${newItem.course_code}', '${newItem.course_title}', '${newItem.course_desc || ''}', '${newItem.units}')" class="btn btn-sm btn-outline" style="color: var(--warning-600); border-color: var(--warning-300);">
                        Edit
                    </button>
                    <button onclick="deleteSubject('${newItem.course_code}')" class="btn btn-danger btn-sm">
                        <i class="fas fa-trash"></i>
                        Delete
                    </button>
                </div>
            </td>
        `;
        
        // Add the new row at the top
        subjectsTable.insertBefore(newRow, subjectsTable.firstChild);
        
        // Highlight the new row briefly
        newRow.style.backgroundColor = '#d4edda';
        setTimeout(() => {
            newRow.style.backgroundColor = '';
        }, 2000);
    }

    handleError(data) {
        if (data.field_errors) {
            Object.keys(data.field_errors).forEach(fieldName => {
                this.validator.showFieldError(fieldName, data.field_errors[fieldName]);
            });
        } else {
            if (window.Toast) {
                Toast.fire({
                    icon: 'error',
                    title: 'Error: ' + (data.message || 'An error occurred while processing your request.')
                });
            } else {
                alert('Error: ' + (data.message || 'An error occurred while processing your request.'));
            }
        }
    }
}

// Global delete function for subjects - KEEPS YOUR SWEET ALERT
function deleteSubject(courseCode) {
    Swal.fire({
        title: 'Are you sure?',
        text: `Delete subject "${courseCode}"? This action cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel',
        reverseButtons: true
        
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading Sweet Alert
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
            fetch(`ajax/process_subject.php?action=delete&code=${encodeURIComponent(courseCode)}`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close the loading dialog first
                    Swal.close();
                    
                    // Show Toast success message for delete
                    if (window.Toast) {
                        Toast.fire({ 
                            icon: 'success', 
                            title: 'Subject deleted successfully!' 
                        });
                    } else {
                        alert('Subject deleted successfully!');
                    }
                    
                    // Remove the row from table dynamically
                    removeSubjectRow(courseCode);
                } else {
                    // Show Toast error message
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
                
                // Show Toast error message
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
    });
}

function removeSubjectRow(courseCode) {
    // Find the subjects table specifically
    const subjectsTable = document.querySelector('#subjects .table tbody');
    
    if (!subjectsTable) {
        console.log('Subjects table not found');
        // Fallback to page reload if table not found
        setTimeout(() => window.location.reload(), 1000);
        return;
    }
    
    // Find and remove the row
    const rows = subjectsTable.querySelectorAll('tr');
    let rowFound = false;
    
    for (let row of rows) {
        const codeCell = row.querySelector('td:first-child strong');
        
        if (codeCell) {
            const cellText = codeCell.textContent.trim();
            
            if (cellText === courseCode.trim()) {
                rowFound = true;
                
                // Animate removal
                row.style.backgroundColor = '#f8d7da';
                row.style.transition = 'all 0.3s ease';
                row.style.opacity = '0.5';
                
                setTimeout(() => {
                    row.remove();
                    
                    // Check if table is now empty
                    const remainingRows = subjectsTable.querySelectorAll('tr');
                    if (remainingRows.length === 0) {
                        subjectsTable.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--text-muted);">No subjects found</td></tr>';
                    }
                }, 300);
                break;
            }
        }
    }
    
    if (!rowFound) {
        setTimeout(() => window.location.reload(), 1000);
    }
}

// Global edit function for subjects
function editSubject(courseCode, courseTitle, courseDesc, units) {
    console.log(' editSubject called with:', {courseCode, courseTitle, courseDesc, units});
    
    if (window.subjectManager) {
        // Populate basic form fields
        document.getElementById('subject_course_code').value = courseCode;
        document.getElementById('course_title').value = courseTitle;
        document.getElementById('course_desc').value = courseDesc === 'No description' ? '' : courseDesc;
        document.getElementById('units').value = units;
        
        // Set edit mode
        window.subjectManager.editMode = true;
        window.subjectManager.currentEditCode = courseCode;
        
        // Update form UI
        window.subjectManager.updateFormForEdit();
        
        // Clear any existing validation states
        window.subjectManager.validator.clearAllErrors();
        
        //  LOAD COURSE OUTCOMES AND MAPPINGS
        console.log(' About to load outcomes for:', courseCode);
        if (window.outcomesManager) {
            window.outcomesManager.loadOutcomesForSubject(courseCode);
        } else {
            console.error(' outcomesManager not found!');
        }
        
        // Scroll to form
        document.getElementById('addSubjectForm').scrollIntoView({ 
            behavior: 'smooth', 
            block: 'start' 
        });
        
        // Focus on title field after a delay
        setTimeout(() => {
            document.getElementById('course_title').focus();
        }, 300);
    } else {
        console.error(' subjectManager not found!');
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('addSubjectForm')) {
        window.subjectManager = new SubjectManager();
    }
});

// Auto-save draft functionality
class SubjectDraftManager {
    constructor() {
        this.draftKey = 'subject_form_draft';
        this.form = document.getElementById('addSubjectForm');
        
        if (this.form) {
            this.init();
        }
    }
    
    init() {
        this.loadDraft();
        
        const inputs = this.form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('input', debounce(() => this.saveDraft(), 1000));
        });
        
        this.form.addEventListener('submit', () => {
            setTimeout(() => this.clearDraft(), 2000);
        });
    }
    
    saveDraft() {
        const formData = new FormData(this.form);
        const draftData = {};
        
        for (let [key, value] of formData.entries()) {
            draftData[key] = value;
        }
        
        localStorage.setItem(this.draftKey, JSON.stringify(draftData));
        this.showDraftSaved();
    }
    
    loadDraft() {
        const savedDraft = localStorage.getItem(this.draftKey);
        if (!savedDraft) return;
        
        try {
            const draftData = JSON.parse(savedDraft);
            
            const isEmpty = Array.from(this.form.elements).every(element => 
                !element.value || element.value === ''
            );
            
            if (isEmpty) {
                Object.keys(draftData).forEach(key => {
                    const element = this.form.querySelector(`[name="${key}"]`);
                    if (element) {
                        element.value = draftData[key];
                    }
                });
                
                this.showDraftLoaded();
            }
        } catch (error) {
            console.error('Error loading draft:', error);
            this.clearDraft();
        }
    }
    
    clearDraft() {
        localStorage.removeItem(this.draftKey);
    }
    
    showDraftSaved() {
        const indicator = this.getDraftIndicator();
        indicator.textContent = 'Draft saved';
        indicator.style.opacity = '1';
        
        setTimeout(() => {
            indicator.style.opacity = '0';
        }, 2000);
    }
    
    showDraftLoaded() {
        if (window.Toast) {
            Toast.fire({
                icon: 'info',
                title: 'Draft Restored: Your previous form data has been restored.'
            });
        } else {
            alert('Draft Restored: Your previous form data has been restored.');
        }
    }
    
    getDraftIndicator() {
        let indicator = document.getElementById('draft-indicator');
        
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'draft-indicator';
            indicator.className = 'draft-indicator';
            indicator.textContent = 'Draft saved';
            
            const formActions = this.form.querySelector('.form-actions');
            if (formActions) {
                formActions.appendChild(indicator);
            }
        }
        
        return indicator;
    }
}

// Initialize draft manager
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('addSubjectForm')) {
        new SubjectDraftManager();
    }
});
// ============================================
// COURSE OUTCOMES MANAGEMENT
// ============================================

class CourseOutcomesManager {
    constructor() {
        this.outcomes = [];
        this.mappings = {};
        this.editingOutcomeId = null;
        this.outcomeCounter = 0;
        this.init();
    }

    
 init() {
    // Setup Add Outcome button
    const addBtn = document.getElementById('addOutcomeBtn');
    if (addBtn) {
        addBtn.addEventListener('click', () => this.showAddForm());
    }

}
    
    showAddForm() {
        const list = document.getElementById('courseOutcomesList');
        const emptyState = document.getElementById('emptyOutcomesState');
        
        // Hide empty state
        if (emptyState) {
            emptyState.style.display = 'none';
        }
        
        // Remove any existing form
        const existingForm = list.querySelector('.outcome-form');
        if (existingForm) {
            existingForm.remove();
        }
        
        // Create form
        const formHtml = `
            <div class="outcome-form">
                <div class="form-group">
                    <label>Course Outcome Description *</label>
                    <textarea 
                        id="outcomeDescInput" 
                        placeholder="e.g., Analyze a complex computing problem and apply principles of computing..."
                        maxlength="500"
                        required
                    ></textarea>
                    <div class="char-counter">
                        <span id="charCount">0 / 500 characters</span>
                    </div>
                </div>
                <div class="outcome-form-actions">
                    <button type="button" class="btn btn-outline btn-sm" onclick="window.outcomesManager.cancelAdd()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="window.outcomesManager.saveOutcome()">
                        <i class="fas fa-check"></i> Save Outcome
                    </button>
                </div>
            </div>
        `;
        
        list.insertAdjacentHTML('afterbegin', formHtml);
        
        // Setup character counter
        const textarea = document.getElementById('outcomeDescInput');
        const charCount = document.getElementById('charCount');
        
        textarea.addEventListener('input', () => {
            const length = textarea.value.length;
            charCount.textContent = `${length} / 500 characters`;
            
            if (length > 450) {
                charCount.parentElement.classList.add('danger');
                charCount.parentElement.classList.remove('warning');
            } else if (length > 400) {
                charCount.parentElement.classList.add('warning');
                charCount.parentElement.classList.remove('danger');
            } else {
                charCount.parentElement.classList.remove('warning', 'danger');
            }
        });
        
        // Focus textarea
        textarea.focus();
    }
    
    saveOutcome() {
        const textarea = document.getElementById('outcomeDescInput');
        const description = textarea.value.trim();
        
        if (!description) {
            if (window.Toast) {
                Toast.fire({
                    icon: 'error',
                    title: 'Please enter a course outcome description'
                });
            } else {
                alert('Please enter a course outcome description');
            }
            textarea.focus();
            return;
        }
        
        if (description.length < 10) {
            if (window.Toast) {
                Toast.fire({
                    icon: 'error',
                    title: 'Description must be at least 10 characters'
                });
            } else {
                alert('Description must be at least 10 characters');
            }
            textarea.focus();
            return;
        }
        
        // Add to outcomes array
        this.outcomeCounter++;
        const outcome = {
            id: `temp_${this.outcomeCounter}`,
            number: this.outcomes.length + 1,
            description: description
        };
        
        this.outcomes.push(outcome);
        
        // Render the outcome
        this.renderOutcome(outcome);
        
        // Remove form
        this.cancelAdd();
        
        // Show success message
        if (window.Toast) {
            Toast.fire({
                icon: 'success',
                title: 'Course outcome added!'
            });
        }
    }
    
   renderOutcome(outcome) {
    const list = document.getElementById('courseOutcomesList');
    const emptyState = document.getElementById('emptyOutcomesState');
    
    // Hide empty state
    if (emptyState) {
        emptyState.style.display = 'none';
    }
    
    const cardHtml = `
        <div class="outcome-card" data-outcome-id="${outcome.id}">
            <div class="outcome-card-header">
                <span class="outcome-number">CO${outcome.number}</span>
                <div class="outcome-actions">
                    <button type="button" class="btn-icon edit" onclick="window.outcomesManager.editOutcome('${outcome.id}')" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn-icon delete" onclick="window.outcomesManager.deleteOutcome('${outcome.id}')" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <p class="outcome-description">${this.escapeHtml(outcome.description)}</p>
        </div>
    `;
    
    list.insertAdjacentHTML('beforeend', cardHtml);
    
    //  SHOW CO-SO MAPPING SECTION
    this.updateCosoMappingVisibility();
}
handleMappingChange(checkbox) {
    const coId = checkbox.dataset.coId;
    const soNumber = checkbox.dataset.soNumber;
    const mappingKey = `${coId}_${soNumber}`;
    
    if (checkbox.checked) {
        this.mappings[mappingKey] = true;
    } else {
        delete this.mappings[mappingKey];
    }
    
    console.log('Mappings updated:', this.mappings);
}
updateCosoMappingVisibility() {
    console.log(' updateCosoMappingVisibility() called!');
    console.log(' Outcomes count:', this.outcomes.length);
    
    const cosoSection = document.getElementById('cosoMappingSection');
    const emptyMappingState = document.getElementById('emptyMappingState');
    const mappingContainer = document.getElementById('mappingContainer');
    
    console.log(' Elements found:');
    console.log('  - cosoSection:', !!cosoSection);
    console.log('  - emptyMappingState:', !!emptyMappingState);
    console.log('  - mappingContainer:', !!mappingContainer);
    
    if (!cosoSection) {
        console.error(' cosoMappingSection NOT FOUND in DOM!');
        return;
    }
    
    if (this.outcomes.length > 0) {
        console.log(' SHOWING CO-SO Section (outcomes > 0)');
        cosoSection.style.display = 'block';
        
        // Hide empty state
        if (emptyMappingState) {
            emptyMappingState.style.display = 'none';
        }
        
        // Generate the mapping matrix
        console.log(' Calling generateMappingMatrix()...');
        this.generateMappingMatrix();
        console.log(' Matrix generated!');
    } else {
        console.log(' HIDING CO-SO Section (no outcomes)');
        cosoSection.style.display = 'none';
        
        // Show empty state
        if (emptyMappingState) {
            emptyMappingState.style.display = 'block';
        }
    }
}
generateMappingMatrix() {
    const mappingContainer = document.getElementById('mappingContainer');
    const emptyMappingState = document.getElementById('emptyMappingState');
    
    if (!mappingContainer) return;
    
    // Hide empty state
    if (emptyMappingState) {
        emptyMappingState.style.display = 'none';
    }
    
    // Student Outcomes (SO1-SO6)
    const studentOutcomes = [
        { number: 1, short: 'Problem Analysis' },
        { number: 2, short: 'Solution Design' },
        { number: 3, short: 'Communication' },
        { number: 4, short: 'Ethics & Responsibility' },
        { number: 5, short: 'Teamwork' },
        { number: 6, short: 'CS Theory' }
    ];
    
    // Build matrix HTML
    let matrixHtml = `
        <div class="mapping-matrix-container">
            <table class="mapping-matrix">
                <thead>
                    <tr>
                        <th>Course Outcome</th>
    `;
    
    // Add SO headers
    studentOutcomes.forEach(so => {
    matrixHtml += `
        <th class="so-header">
            <span class="so-number">SO${so.number}</span>
        </th>
    `;
})
    
    matrixHtml += `
                    </tr>
                </thead>
                <tbody>
    `;
    
    // Add rows for each CO
    this.outcomes.forEach(outcome => {
        matrixHtml += `
            <tr>
                <td>
                    <span class="co-label">CO${outcome.number}</span>
                </td>
        `;
        
        // Add checkboxes for each SO
        studentOutcomes.forEach(so => {
            const mappingKey = `${outcome.id}_${so.number}`;
            const isChecked = this.mappings && this.mappings[mappingKey] ? 'checked' : '';
            
            matrixHtml += `
                <td>
                    <div class="mapping-checkbox-wrapper">
                        <input 
                            type="checkbox" 
                            class="mapping-checkbox" 
                            data-co-id="${outcome.id}"
                            data-co-number="${outcome.number}"
                            data-so-number="${so.number}"
                            ${isChecked}
                            onchange="window.outcomesManager.handleMappingChange(this)"
                        >
                    </div>
                </td>
            `;
        });
        
        matrixHtml += `
            </tr>
        `;
    });
    
    matrixHtml += `
                </tbody>
            </table>
        </div>
    `;
    
    // Replace container content
    mappingContainer.innerHTML = matrixHtml;
}
    
    editOutcome(outcomeId) {
        const outcome = this.outcomes.find(o => o.id === outcomeId);
        if (!outcome) return;
        
        const card = document.querySelector(`[data-outcome-id="${outcomeId}"]`);
        if (!card) return;
        
        this.editingOutcomeId = outcomeId;
        
        // Replace card with edit form
        const formHtml = `
            <div class="outcome-form">
                <div class="form-group">
                    <label>Course Outcome Description *</label>
                    <textarea 
                        id="outcomeDescInput" 
                        maxlength="500"
                        required
                    >${this.escapeHtml(outcome.description)}</textarea>
                    <div class="char-counter">
                        <span id="charCount">${outcome.description.length} / 500 characters</span>
                    </div>
                </div>
                <div class="outcome-form-actions">
                    <button type="button" class="btn btn-outline btn-sm" onclick="window.outcomesManager.cancelEdit()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="window.outcomesManager.updateOutcome()">
                        <i class="fas fa-check"></i> Update
                    </button>
                </div>
            </div>
        `;
        
        card.outerHTML = formHtml;
        
        // Setup character counter
        const textarea = document.getElementById('outcomeDescInput');
        const charCount = document.getElementById('charCount');
        
        textarea.addEventListener('input', () => {
            const length = textarea.value.length;
            charCount.textContent = `${length} / 500 characters`;
            
            if (length > 450) {
                charCount.parentElement.classList.add('danger');
            } else if (length > 400) {
                charCount.parentElement.classList.add('warning');
            } else {
                charCount.parentElement.classList.remove('warning', 'danger');
            }
        });
        
        textarea.focus();
    }
    
    updateOutcome() {
        const textarea = document.getElementById('outcomeDescInput');
        const description = textarea.value.trim();
        
        if (!description || description.length < 10) {
            if (window.Toast) {
                Toast.fire({
                    icon: 'error',
                    title: 'Description must be at least 10 characters'
                });
            }
            return;
        }
        
        // Update in array
        const outcome = this.outcomes.find(o => o.id === this.editingOutcomeId);
        if (outcome) {
            outcome.description = description;
        }
        
        // Re-render all outcomes
        this.renderAllOutcomes();
        
        this.editingOutcomeId = null;
        
        if (window.Toast) {
            Toast.fire({
                icon: 'success',
                title: 'Course outcome updated!'
            });
        }
    }
    
    deleteOutcome(outcomeId) {
        const outcome = this.outcomes.find(o => o.id === outcomeId);
        if (!outcome) return;
        
        if (window.Swal) {
            Swal.fire({
                title: 'Delete Course Outcome?',
                text: `Remove CO${outcome.number}?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, delete it',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.performDelete(outcomeId);
                }
            });
        } else {
            if (confirm(`Delete CO${outcome.number}?`)) {
                this.performDelete(outcomeId);
            }
        }
    }
    
    performDelete(outcomeId) {
        // Remove from array
        this.outcomes = this.outcomes.filter(o => o.id !== outcomeId);
        
        // Renumber
        this.outcomes.forEach((outcome, index) => {
            outcome.number = index + 1;
        });
        
        // Re-render
        this.renderAllOutcomes();
        
        if (window.Toast) {
            Toast.fire({
                icon: 'success',
                title: 'Course outcome deleted!'
            });
        }
    }
    
    cancelAdd() {
        const list = document.getElementById('courseOutcomesList');
        const form = list.querySelector('.outcome-form');
        
        if (form) {
            form.remove();
        }
        
        // Show empty state if no outcomes
        if (this.outcomes.length === 0) {
            const emptyState = document.getElementById('emptyOutcomesState');
            if (emptyState) {
                emptyState.style.display = 'block';
            }
        }
    }
    
    cancelEdit() {
        this.editingOutcomeId = null;
        this.renderAllOutcomes();
    }
    
    renderAllOutcomes() {
    const list = document.getElementById('courseOutcomesList');
    const emptyState = document.getElementById('emptyOutcomesState');
    
    // Clear only the outcome cards, NOT the empty state
    const outcomeCards = list.querySelectorAll('.outcome-card');
    outcomeCards.forEach(card => card.remove());
    
    if (this.outcomes.length === 0) {
        // Just show the empty state (it's already in the DOM)
        if (emptyState) {
            emptyState.style.display = 'block';
        }
    } else {
        // Hide empty state
        if (emptyState) {
            emptyState.style.display = 'none';
        }
        
        // Render all outcomes
        this.outcomes.forEach(outcome => {
            this.renderOutcome(outcome);
        });
    }
    
    // Update CO-SO mapping visibility
    this.updateCosoMappingVisibility();
}
    
  async loadOutcomesForSubject(courseCode) {
    console.log(' Loading outcomes for:', courseCode);
    
    if (!courseCode) {
        console.error(' No course code provided');
        return;
    }
    
    try {
        // Fetch outcomes and mappings from API
        const response = await fetch(`/automation_system/ajax/get_subject_outcomes.php?code=${encodeURIComponent(courseCode)}`);
        const data = await response.json();
        
        console.log(' API Response:', data);
        
        if (!data.success) {
            console.error(' Failed to load outcomes:', data.message);
            if (window.Toast) {
                Toast.fire({
                    icon: 'warning',
                    title: 'Could not load course outcomes'
                });
            }
            return;
        }
        
        // Clear existing outcomes first
        this.clearAll();
        
        // Load outcomes
        if (data.outcomes && data.outcomes.length > 0) {
            console.log(` Loading ${data.outcomes.length} outcomes...`);
            
            this.outcomes = data.outcomes.map(outcome => ({
                id: outcome.id,
                number: outcome.number,
                description: outcome.description
            }));
            
            // Update counter to prevent ID conflicts
            this.outcomeCounter = this.outcomes.length;
            
            console.log(' Outcomes loaded:', this.outcomes);
        }
        
        // Load mappings
        if (data.mappings && data.mappings.length > 0) {
            console.log(` Loading ${data.mappings.length} mappings...`);
            
            this.mappings = {};
            
            data.mappings.forEach(mapping => {
                const mappingKey = `${mapping.co_id}_${mapping.so_number}`;
                this.mappings[mappingKey] = true;
            });
            
            console.log(' Mappings loaded:', this.mappings);
        }
        
        // Re-render all outcomes
        this.renderAllOutcomes();
        
        console.log(' All outcomes and mappings loaded successfully');
        
    } catch (error) {
        console.error(' Error loading outcomes:', error);
        if (window.Toast) {
            Toast.fire({
                icon: 'error',
                title: 'Failed to load course outcomes'
            });
        }
    }
}
    
    getOutcomesData() {
        return this.outcomes.map(o => ({
            number: o.number,
            description: o.description
        }));
    }
    getMappingsData() {
    const mappingsArray = [];
    
    console.log(' getMappingsData called');
    console.log(' this.mappings:', this.mappings);
    console.log(' this.outcomes:', this.outcomes);
    
    Object.keys(this.mappings).forEach(key => {
        if (this.mappings[key]) {
            // Key format: "db_41_1" means co_id="db_41", so_number="1"
            const lastUnderscoreIndex = key.lastIndexOf('_');
            const coId = key.substring(0, lastUnderscoreIndex);
            const soNumber = key.substring(lastUnderscoreIndex + 1);
            
            console.log(` Processing key: ${key} -> coId: ${coId}, soNumber: ${soNumber}`);
            
            const outcome = this.outcomes.find(o => o.id === coId);
            
            if (outcome) {
                mappingsArray.push({
                    co_number: outcome.number,
                    so_number: parseInt(soNumber)
                });
                console.log(` Added mapping: CO${outcome.number} -> SO${soNumber}`);
            } else {
                console.log(` Outcome not found for coId: ${coId}`);
            }
        }
    });
    
    console.log(' Final mappingsArray:', mappingsArray);
    return mappingsArray;
}
    
    clearAll() {
        this.outcomes = [];
        this.mappings = {};
        this.outcomeCounter = 0;
        this.editingOutcomeId = null;
        this.renderAllOutcomes();
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize Course Outcomes Manager
// Initialize Course Outcomes Manager - WITH DEBUG LOGS
document.addEventListener('DOMContentLoaded', () => {
    console.log(' Checking for courseOutcomesList...');
    
    const outcomesList = document.getElementById('courseOutcomesList');
    console.log('courseOutcomesList found:', !!outcomesList);
    
    if (outcomesList) {
        console.log(' Initializing CourseOutcomesManager...');
        window.outcomesManager = new CourseOutcomesManager();
        console.log(' CourseOutcomesManager initialized:', window.outcomesManager);
        
        // Test if CO-SO section exists
        const cosoSection = document.getElementById('cosoMappingSection');
        console.log(' cosoMappingSection found:', !!cosoSection);
    } else {
        console.error(' courseOutcomesList NOT FOUND in DOM!');
        console.log('Available IDs:', Array.from(document.querySelectorAll('[id]')).map(el => el.id));
    }
});