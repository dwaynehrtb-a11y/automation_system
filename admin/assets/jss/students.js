// Enhanced students.js with SweetAlert and dynamic updates like subjects.js

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

async function makeRequest(url) {
    const response = await fetch(url, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    });
    return response.json();
}

// Student management class - Enhanced version
class StudentManager {
    constructor() {
        this.editMode = false;
        this.currentEditId = null;
        this.currentEditEmail = null;
        this.isSubmitting = false;
        this.init();
    }
    
    init() {
        // Get the form
        const form = document.getElementById('addStudentForm');
        if (form) {
            // Remove ALL existing event listeners by cloning the form
            const newForm = form.cloneNode(true);
            form.parentNode.replaceChild(newForm, form);
            
            // Now add our single event listener to the clean form
            newForm.addEventListener('submit', (e) => this.handleSubmit(e));
        }

        // Setup real-time validation
        this.setupStudentIdValidation();
        this.setupEmailValidation();
        this.setupNameValidation();
        this.setupDateValidation();

        // Setup search and filter
        this.setupSearchAndFilter();

        // Setup character counters
        this.setupCharacterCounters();

        // Setup edit functionality
        this.setupEditHandlers();
    }
    
    setupStudentIdValidation() {
        const studentIdField = document.getElementById('student_id');
        if (!studentIdField) return;
        
        const checkStudentId = debounce(async (value) => {
            if (!value || value.length < 9) return;
            
            try {
                const response = await makeRequest(`ajax/check_student_id.php?id=${encodeURIComponent(value)}`);
                
                if (response.exists && !this.editMode) {
                    this.showFieldError('student_id', 'This student ID already exists');
                } else if (response.exists && this.editMode && value !== this.currentEditId) {
                    this.showFieldError('student_id', 'This student ID already exists');
                } else {
                    this.showFieldSuccess('student_id');
                }
            } catch (error) {
                console.error('Student ID validation error:', error);
            }
        }, 500);
        
        studentIdField.addEventListener('input', (e) => {
            // Format as YYYY-XXXXXX
            let value = e.target.value.replace(/[^0-9]/g, '');
            if (value.length > 4) {
                value = value.substring(0, 4) + '-' + value.substring(4, 10);
            }
            e.target.value = value;
            
            if (value.length >= 9) {
                checkStudentId(value);
            }
        });
    }

    setupEmailValidation() {
        const emailField = document.getElementById('student_email');
        if (!emailField) return;
        
        const checkEmail = debounce(async (value) => {
            if (!value || !value.includes('@')) return;
            
            try {
                const response = await makeRequest(`ajax/check_student_email.php?email=${encodeURIComponent(value)}`);
                
                if (response.exists && !this.editMode) {
                    this.showFieldError('email', 'This email address is already registered');
                } else if (response.exists && this.editMode && value !== this.currentEditEmail) {
                    this.showFieldError('email', 'This email address is already registered');
                } else {
                    this.showFieldSuccess('email');
                }
            } catch (error) {
                console.error('Email validation error:', error);
            }
        }, 500);
        
        emailField.addEventListener('blur', (e) => {
            const value = e.target.value.trim();
            if (value) {
                checkEmail(value);
            }
        });
    }

    setupNameValidation() {
        ['first_name', 'last_name'].forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (!field) return;
            
            field.addEventListener('input', (e) => {
                // Allow only letters, spaces, and common name characters
                let value = e.target.value.replace(/[^a-zA-Z\s\-\.\']/g, '');
                e.target.value = value;
                
                if (value.length >= 2) {
                    this.showFieldSuccess(fieldId);
                }
            });
        });

        // Middle initial formatting
        const middleInitialField = document.getElementById('middle_initial');
        if (middleInitialField) {
            middleInitialField.addEventListener('input', (e) => {
                let value = e.target.value.replace(/[^a-zA-Z]/g, '').toUpperCase();
                if (value.length > 1) {
                    value = value.substring(0, 1);
                }
                e.target.value = value;
            });
        }
    }

    setupDateValidation() {
        const birthdayField = document.getElementById('birthday');
        if (!birthdayField) return;
        
        // Set max date to today minus 15 years, min date to today minus 50 years
        const today = new Date();
        const maxDate = new Date(today.getFullYear() - 15, today.getMonth(), today.getDate());
        const minDate = new Date(today.getFullYear() - 50, today.getMonth(), today.getDate());
        
        birthdayField.max = maxDate.toISOString().split('T')[0];
        birthdayField.min = minDate.toISOString().split('T')[0];
        
        birthdayField.addEventListener('change', (e) => {
            if (e.target.value) {
                this.showFieldSuccess('birthday');
            }
        });
    }

    setupSearchAndFilter() {
        const searchInput = document.getElementById('studentSearch');
        const statusFilter = document.getElementById('statusFilter');
        
        if (searchInput) {
            searchInput.addEventListener('input', debounce(() => {
                this.filterStudents();
            }, 300));
        }
        
        if (statusFilter) {
            statusFilter.addEventListener('change', () => {
                this.filterStudents();
            });
        }
    }

    filterStudents() {
        const searchTerm = document.getElementById('studentSearch')?.value.toLowerCase() || '';
        const statusFilter = document.getElementById('statusFilter')?.value || '';
        const tbody = document.getElementById('studentsTableBody');
        const rows = tbody.querySelectorAll('tr');
        
        rows.forEach(row => {
            const studentId = row.cells[0]?.textContent.toLowerCase() || '';
            const fullName = row.cells[1]?.textContent.toLowerCase() || '';
            const email = row.cells[2]?.textContent.toLowerCase() || '';
            const status = row.cells[4]?.textContent.toLowerCase().trim() || '';
            
            const matchesSearch = !searchTerm || 
                studentId.includes(searchTerm) || 
                fullName.includes(searchTerm) || 
                email.includes(searchTerm);
            
            const matchesStatus = !statusFilter || status === statusFilter.toLowerCase();
            
            row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
        });
    }

    setupCharacterCounters() {
        const fields = [
            { id: 'student_id', max: 50 },
            { id: 'first_name', max: 50 },
            { id: 'last_name', max: 50 },
            { id: 'middle_initial', max: 2 },
            { id: 'student_email', max: 100 }
        ];
        
        fields.forEach(field => {
            const element = document.getElementById(field.id);
            if (!element || element.parentNode.querySelector('.character-counter')) return;
            
            const counter = document.createElement('div');
            counter.className = 'character-counter';
            counter.innerHTML = `<small class="text-muted">0 / ${field.max} characters</small>`;
            
            element.parentNode.appendChild(counter);
            
            element.addEventListener('input', () => {
                const length = element.value.length;
                const counterText = counter.querySelector('small');
                counterText.textContent = `${length} / ${field.max} characters`;
                
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
    const fields = ['student_id', 'first_name', 'last_name', 'middle_initial', 'student_email'];
        
        fields.forEach(fieldId => {
            const element = document.getElementById(fieldId);
            if (!element) return;
            
            const counter = element.parentNode.querySelector('.character-counter small');
            if (counter) {
                const maxLength = fieldId === 'middle_initial' ? 2 : 
                                fieldId === 'email' ? 100 : 50;
                counter.textContent = `0 / ${maxLength} characters`;
                counter.className = 'text-muted';
            }
        });
    }

    setupEditHandlers() {
        document.addEventListener('click', (e) => {
            if (e.target.matches('.btn-edit-student') || e.target.closest('.btn-edit-student')) {
                const btn = e.target.matches('.btn-edit-student') ? e.target : e.target.closest('.btn-edit-student');
                this.handleEdit(btn);
            }
        });
    }
    
    handleSubmit(e) {
    e.preventDefault();
    e.stopPropagation();
    
    if (this.isSubmitting) return;
    
    // Simple validation
    const studentId = document.getElementById('student_id').value.trim();
    const firstName = document.getElementById('first_name').value.trim();
    const lastName = document.getElementById('last_name').value.trim();
    const email = document.getElementById('student_email').value.trim(); 
    const birthday = document.getElementById('birthday').value;
    
    if (!studentId || !firstName || !lastName || !email || !birthday) {
        if (window.Toast) {
            Toast.fire({
                icon: 'error',
                title: 'Please fill in all required fields'
            });
        }
        return;
    }
    
    this.performSubmit();
}

    performSubmit() {
        this.isSubmitting = true;
        this.setLoadingState(true);
        
        const formData = new FormData(document.getElementById('addStudentForm'));
        const submitUrl = 'ajax/process_student.php';

        if (this.editMode) {
            formData.append('action', 'update');
            formData.append('original_id', this.currentEditId);
        } else {
            formData.append('action', 'add');
        }
        
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
    return response.text();  // Get as text first
})
.then(text => {
    console.log('Raw response:', text);  // Log the raw response
    try {
        return JSON.parse(text);  // Try to parse as JSON
    } catch (e) {
        console.error('JSON parse error. Response was:', text);
        throw new Error('Server returned invalid JSON: ' + text.substring(0, 100));
    }
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
            console.error('Student submission error:', error);
            
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
    if (this.editMode) {
        // Update the existing row
        this.updateRowInTable(data);
        
        // Show Toast success message for edit
        if (window.Toast) {
            Toast.fire({ 
                icon: 'success', 
                title: 'Student updated successfully!' 
            });
        } else {
            alert('Student updated successfully!');
        }
        
        // Exit edit mode
        this.cancelEdit();
    } else {
        // Show success message with email info
        const message = data.email_sent 
            ? `Student account created! Login credentials sent to ${data.student.email}`
            : `Student account created, but email failed. Please provide credentials manually.`;
        
        if (window.Toast) {
            Toast.fire({ 
                icon: data.email_sent ? 'success' : 'warning',
                title: message,
                timer: data.email_sent ? 3000 : 5000
            });
        } else {
            alert(message);
        }
        
        // If email failed, show credentials
        if (!data.email_sent && data.manual_credentials) {
            setTimeout(() => {
                Swal.fire({
                    title: 'Manual Credentials',
                    html: `
                        <p>Email sending failed. Please provide these credentials to the student:</p>
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0;">
                            <p><strong>Student ID:</strong> ${data.manual_credentials.student_id}</p>
                            <p><strong>Temporary Password:</strong> <code style="background: #fff; padding: 5px; border: 1px solid #ddd;">${data.manual_credentials.password}</code></p>
                        </div>
                        <p style="color: #666; font-size: 13px;">The student will need to change this password on first login.</p>
                    `,
                    icon: 'info',
                    confirmButtonColor: '#003082'
                });
            }, 500);
        }
        
        // Reset form and add to table
        document.getElementById('addStudentForm').reset();
        this.clearAllErrors();
        this.resetCharacterCounters();

        if (data.student) {
            this.addRowToTable(data.student);
        }
    }
}
    
    updateRowInTable(data) {
        const tbody = document.getElementById('studentsTableBody');
        const rows = tbody.querySelectorAll('tr');
        
        for (let row of rows) {
            const idCell = row.querySelector('td:first-child strong');
            if (idCell && idCell.textContent.trim() === this.currentEditId) {
                const studentId = document.getElementById('student_id').value;
                const firstName = document.getElementById('first_name').value;
                const lastName = document.getElementById('last_name').value;
                const middleInitial = document.getElementById('middle_initial').value;
                const email = document.getElementById('email').value;
                const birthday = document.getElementById('birthday').value;
                const status = document.getElementById('status').value;
                
                const fullName = `${lastName}, ${firstName}${middleInitial ? ' ' + middleInitial + '.' : ''}`;
                const statusClass = this.getStatusClass(status);
                const formattedDate = new Date(birthday).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
                
                const cells = row.querySelectorAll('td');
                cells[0].innerHTML = `<strong>${studentId}</strong>`;
                cells[1].textContent = fullName;
                cells[2].textContent = email;
                cells[3].textContent = formattedDate;
                cells[4].innerHTML = `<span class="badge ${statusClass}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
                
                // Update action buttons
                const editBtn = row.querySelector('button[onclick*="editStudent"]');
                const deleteBtn = row.querySelector('button[onclick*="deleteStudent"]');
                const viewBtn = row.querySelector('button[onclick*="viewStudentDetails"]');
                
                if (editBtn) {
                    editBtn.setAttribute('onclick', `editStudent('${studentId}', '${firstName}', '${lastName}', '${middleInitial}', '${email}', '${birthday}', '${status}')`);
                }
                if (deleteBtn) {
                    deleteBtn.setAttribute('onclick', `deleteStudent('${studentId}')`);
                }
                if (viewBtn) {
                    viewBtn.setAttribute('onclick', `viewStudentDetails('${studentId}')`);
                }
                
                // Highlight updated row
                row.style.backgroundColor = '#d4edda';
                setTimeout(() => {
                    row.style.backgroundColor = '';
                }, 2000);
                
                break;
            }
        }
    }

   addRowToTable(newItem) {
    const tbody = document.getElementById('studentsTableBody');
    
    // Check if table has "no students" message and remove it
    const noDataRow = tbody.querySelector('tr td[colspan="7"]');
    if (noDataRow) {
        noDataRow.closest('tr').remove();
    }
    
    const fullName = `${newItem.last_name}, ${newItem.first_name}${newItem.middle_initial ? ' ' + newItem.middle_initial + '.' : ''}`;
    const formattedDate = new Date(newItem.birthday).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    
    const newRow = document.createElement('tr');
    newRow.innerHTML = `
        <td><strong>${newItem.student_id}</strong></td>
        <td>
            <div>
                <div style="font-weight: 600;">${fullName}</div>
            </div>
        </td>
        <td>${newItem.email}</td>
        <td>${formattedDate}</td>
        <td>
            <span class="badge badge-warning">
                <i class="fas fa-clock"></i> Pending Activation
            </span>
        </td>
        <td>
            <span class="badge badge-success">Active</span>
            <br>
            <small style="color: var(--text-muted);">0 classes</small>
        </td>
        <td>
            <div class="d-flex gap-2">
                <button onclick="resendStudentCredentials('${newItem.student_id}', '${fullName.replace(/'/g, "\\'")}', '${newItem.email}')" 
                        class="btn btn-info btn-sm">
                    <i class="fas fa-envelope"></i>
                    Resend Email
                </button>
                <button onclick="viewStudentDetails('${newItem.student_id}')" 
                        class="btn btn-sm btn-info">
                    <i class="fas fa-eye"></i>
                    View
                </button>
                <button onclick="editStudent('${newItem.student_id}', '${newItem.first_name}', '${newItem.last_name}', '${newItem.middle_initial || ''}', '${newItem.email}', '${newItem.birthday}', 'active')" 
                        class="btn btn-sm btn-warning">
                    <i class="fas fa-edit"></i>
                    Edit
                </button>
                <button onclick="deleteStudent('${newItem.student_id}')" 
                        class="btn btn-danger btn-sm">
                    <i class="fas fa-trash"></i>
                    Delete
                </button>
            </div>
        </td>
    `;
    
    tbody.insertBefore(newRow, tbody.firstChild);
    
    // Highlight new row
    newRow.style.backgroundColor = '#d4edda';
    setTimeout(() => {
        newRow.style.backgroundColor = '';
    }, 2000);
}

    getStatusClass(status) {
        const statusClasses = {
            'active': 'badge-success',
            'inactive': 'badge-secondary',
            'graduated': 'badge-info',
            'transferred': 'badge-warning',
            'suspended': 'badge-danger',
            'pending': 'badge-warning'
        };
        return statusClasses[status] || 'badge-secondary';
    }

    handleError(data) {
        if (window.Toast) {
            Toast.fire({
                icon: 'error',
                title: 'Error: ' + (data.message || 'An error occurred while processing your request.')
            });
        } else {
            alert('Error: ' + (data.message || 'An error occurred while processing your request.'));
        }
    }

    setLoadingState(loading) {
        const submitBtn = document.querySelector('#addStudentForm button[type="submit"]');
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

    cancelEdit() {
        this.editMode = false;
        this.currentEditId = null;
        this.currentEditEmail = null;
        
        document.getElementById('addStudentForm').reset();
        this.resetCharacterCounters();
        this.updateFormForAdd();
        this.clearAllErrors();
    }

    updateFormForAdd() {
        const form = document.getElementById('addStudentForm');
        const header = form.closest('.form-section').querySelector('.form-header h3');
        const subtitle = form.closest('.form-section').querySelector('.form-subtitle');
        const submitBtn = form.querySelector('button[type="submit"]');
        const btnText = submitBtn.querySelector('.btn-text');
        const cancelBtn = form.querySelector('.btn-cancel-edit');
        
        header.innerHTML = '<i class="fas fa-user-plus"></i> Add New Student';
        subtitle.textContent = 'Register a new student in the academic system';
        btnText.innerHTML = '<i class="fas fa-save"></i> Add Student';
        
        if (cancelBtn) {
            cancelBtn.remove();
        }
    }

    updateFormForEdit() {
        const form = document.getElementById('addStudentForm');
        const header = form.closest('.form-section').querySelector('.form-header h3');
        const subtitle = form.closest('.form-section').querySelector('.form-subtitle');
        const submitBtn = form.querySelector('button[type="submit"]');
        const btnText = submitBtn.querySelector('.btn-text');
        
        header.innerHTML = '<i class="fas fa-user-edit"></i> Edit Student';
        subtitle.textContent = 'Update student information';
        btnText.innerHTML = '<i class="fas fa-save"></i> Update Student';
        
        if (!form.querySelector('.btn-cancel-edit')) {
            const cancelBtn = document.createElement('button');
            cancelBtn.type = 'button';
            cancelBtn.className = 'btn btn-outline btn-cancel-edit';
            cancelBtn.innerHTML = '<i class="fas fa-times"></i> Cancel';
            cancelBtn.addEventListener('click', () => this.cancelEdit());
            
            submitBtn.parentNode.insertBefore(cancelBtn, submitBtn);
        }
    }

    showFieldError(fieldName, message) {
        const field = document.getElementById(fieldName);
        if (!field) return;
        
        field.classList.add('is-invalid');
        const feedback = field.parentNode.querySelector('.invalid-feedback');
        if (feedback) {
            feedback.style.display = 'block';
            feedback.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
        }
    }

    showFieldSuccess(fieldName) {
        const field = document.getElementById(fieldName);
        if (!field) return;
        
        field.classList.remove('is-invalid');
        field.classList.add('is-valid');
        const feedback = field.parentNode.querySelector('.invalid-feedback');
        if (feedback) {
            feedback.style.display = 'none';
        }
    }

    clearFieldError(fieldName) {
        const field = document.getElementById(fieldName);
        if (!field) return;
        
        field.classList.remove('is-invalid');
        const feedback = field.parentNode.querySelector('.invalid-feedback');
        if (feedback) {
            feedback.style.display = 'none';
        }
    }
clearAllErrors() {
    const fields = ['student_id', 'first_name', 'last_name', 'middle_initial', 'student_email', 'birthday'];  
    fields.forEach(fieldName => {
        this.clearFieldError(fieldName);
    });
}
}

// Global delete function for students - SAME SWEET ALERT STYLE AS SUBJECTS
// Fixed delete function - ensures Toast works
function deleteStudent(studentId) {
    Swal.fire({
        title: 'Are you sure?',
        text: `Delete student "${studentId}"? This will also remove all their enrollments!`,
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
            fetch(`ajax/process_student.php?action=delete&id=${encodeURIComponent(studentId)}`, {
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
                    
                    // Use the SAME Toast method as add function
                    if (window.Toast) {
                        Toast.fire({ 
                            icon: 'success', 
                            title: 'Student deleted successfully!' 
                        });
                    } else {
                        alert('Student deleted successfully!');
                    }
                    
                    // Remove the row from table dynamically
                    removeStudentRow(studentId);
                } else {
                    // Use the SAME Toast method as add function
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
                
                // Use the SAME Toast method as add function
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

function removeStudentRow(studentId) {
    // Find the students table specifically (same pattern as subjects.js)
    const studentsTable = document.getElementById('studentsTableBody');
    
    if (!studentsTable) {
        console.log('Students table not found');
        // Fallback to page reload if table not found
        setTimeout(() => window.location.reload(), 1000);
        return;
    }
    
    // Find and remove the row
    const rows = studentsTable.querySelectorAll('tr');
    let rowFound = false;
    
    for (let row of rows) {
        const idCell = row.querySelector('td:first-child strong');
        
        if (idCell) {
            const cellText = idCell.textContent.trim();
            
            if (cellText === studentId.trim()) {
                rowFound = true;
                
                // Animate removal (same as subjects.js)
                row.style.backgroundColor = '#f8d7da';
                row.style.transition = 'all 0.3s ease';
                row.style.opacity = '0.5';
                
                setTimeout(() => {
                    row.remove();
                    
                    // Check if table is now empty
                    const remainingRows = studentsTable.querySelectorAll('tr');
                    if (remainingRows.length === 0) {
                        studentsTable.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--text-muted);">No students found</td></tr>';
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

// Global edit function for students (same pattern as subjects.js)
function editStudent(studentId, firstName, lastName, middleInitial, email, birthday, status) {
    if (window.studentManager) {
        // Populate form
        document.getElementById('student_id').value = studentId;
        document.getElementById('first_name').value = firstName;
        document.getElementById('last_name').value = lastName;
        document.getElementById('middle_initial').value = middleInitial;
        document.getElementById('email').value = email;
        document.getElementById('birthday').value = birthday;
        document.getElementById('status').value = status;
        
        // Set edit mode
        window.studentManager.editMode = true;
        window.studentManager.currentEditId = studentId;
        window.studentManager.currentEditEmail = email;
        
        // Update form UI
        window.studentManager.updateFormForEdit();
        
        // Clear any existing validation states
        window.studentManager.clearAllErrors();
        
        // Scroll to form
        document.getElementById('addStudentForm').scrollIntoView({ 
            behavior: 'smooth', 
            block: 'start' 
        });
        
        // Focus on first name field
        document.getElementById('first_name').focus();
    }
}

// Placeholder view function
function viewStudentDetails(studentId) {
    if (window.Toast) {
        Toast.fire({
            icon: 'info',
            title: 'View details for student: ' + studentId + ' (Coming soon)'
        });
    } else {
        alert('View details for student: ' + studentId + ' (Coming soon)');
    }
}
// Resend student credentials function
async function resendStudentCredentials(studentId, name, email) {
    const result = await Swal.fire({
        title: 'Resend Credentials?',
        html: `Send login credentials again to <strong>${name}</strong>?<br><small style="color: #6b7280;">${email}</small>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Resend',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#003082',
        cancelButtonColor: '#6b7280'
    });
    
    if (result.isConfirmed) {
        Swal.fire({
            title: 'Sending...',
            text: 'Please wait',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        try {
            const response = await fetch('ajax/resend_student_credentials.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `student_id=${encodeURIComponent(studentId)}`
            });
            
            const data = await response.json();
            
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Email Sent!',
                    text: `Credentials have been resent to ${email}`,
                    confirmButtonColor: '#003082'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Failed',
                    text: data.message || 'Failed to send email',
                    confirmButtonColor: '#ef4444'
                });
            }
        } catch (error) {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'An error occurred while sending email',
                confirmButtonColor: '#ef4444'
            });
        }
    }
}

// Initialize when DOM is loaded (same pattern as subjects.js)
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('addStudentForm')) {
        window.studentManager = new StudentManager();
        console.log('StudentManager initialized');
    }
});