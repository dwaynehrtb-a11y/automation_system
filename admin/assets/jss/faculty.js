

// Global edit function with SweetAlert modal
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
function editFaculty(id, name, employee_id, email) {
    Swal.fire({
        title: '<i class="fas fa-user-edit"></i> Edit Faculty Member',
        html: `
            <div style="margin-bottom: 1rem; text-align: left;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Full Name:</label>
                <input type="text" id="edit_name" value="${name}" style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem;">
            </div>
            <div style="margin-bottom: 1rem; text-align: left;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Employee ID:</label>
                <input type="text" id="edit_employee_id" value="${employee_id}" style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem;">
            </div>
            <div style="margin-bottom: 1rem; text-align: left;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Email:</label>
                <input type="email" id="edit_email" value="${email}" style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem;">
            </div>
            <div style="margin-bottom: 1rem; text-align: left;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Password (leave blank to keep current):</label>
                <input type="password" id="edit_password" placeholder="Enter new password" style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem;">
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-save"></i> Update Faculty',
        cancelButtonText: '<i class="fas fa-times"></i> Cancel',
        confirmButtonColor: '#2563eb',
        width: '500px',
        preConfirm: () => {
            const newName = document.getElementById('edit_name').value.trim();
            const newEmployeeId = document.getElementById('edit_employee_id').value.trim();
            const newEmail = document.getElementById('edit_email').value.trim();
            const password = document.getElementById('edit_password').value;
            
            // Basic validation
            if (!newName) {
                Swal.showValidationMessage('Name is required');
                return false;
            }
            if (!newEmployeeId) {
                Swal.showValidationMessage('Employee ID is required');
                return false;
            }
            if (!newEmail) {
                Swal.showValidationMessage('Email is required');
                return false;
            }
            if (!newEmail.includes('@')) {
                Swal.showValidationMessage('Please enter a valid email address');
                return false;
            }
            
            const formData = new FormData();
            formData.append('action', 'update');
            formData.append('id', id);
            formData.append('name', newName);
            formData.append('employee_id', newEmployeeId);
            formData.append('email', newEmail);
            
            if (password.trim()) {
                formData.append('password', password);
            }
            
            return fetch('ajax/process_faculty.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(response => response.json()).then(data => {
                if (!data.success) throw new Error(data.message || 'Update failed');
                return { success: true, newName, newEmployeeId, newEmail };
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            updateFacultyInTable(id, result.value);
            
            if (window.Toast) {
                Toast.fire({ icon: 'success', title: 'Faculty updated successfully!' });
            } else {
                alert('Faculty updated successfully!');
            }
        }
    }).catch((error) => {
        console.error('Error:', error);
        
        if (window.Toast) {
            Toast.fire({
                icon: 'error',
                title: 'Error: ' + (error.message || 'Update failed!')
            });
        } else {
            alert('Error: ' + (error.message || 'Update failed!'));
        }
    });
}

// Update faculty row in table after edit
function updateFacultyInTable(id, updatedData) {
    const tables = document.querySelectorAll('#faculty .table tbody tr');
    tables.forEach(row => {
        const editButton = row.querySelector(`button[onclick*="editFaculty(${id}"]`);
        if (editButton) {
            const cells = row.querySelectorAll('td');
            if (cells.length >= 3) {
                // Update name in first cell
                const nameDiv = cells[0].querySelector('div div');
                if (nameDiv) {
                    nameDiv.textContent = updatedData.newName;
                }
                
                // Update avatar
                const avatar = cells[0].querySelector('.user-avatar');
                if (avatar) {
                    avatar.textContent = updatedData.newName.charAt(0).toUpperCase();
                }
                
                // Update employee ID and email
                cells[1].textContent = updatedData.newEmployeeId;
                cells[2].textContent = updatedData.newEmail;
                
                // Update button onclick attributes
                const buttons = row.querySelectorAll('button[onclick*="editFaculty"], button[onclick*="viewFacultyClasses"]');
                buttons.forEach(btn => {
                    const onclick = btn.getAttribute('onclick');
                    if (onclick && onclick.includes('editFaculty')) {
                        const newOnclick = onclick.replace(
                            /editFaculty\([^)]+\)/,
                            `editFaculty(${id}, '${updatedData.newName.replace(/'/g, "\\'")}', '${updatedData.newEmployeeId}', '${updatedData.newEmail}')`
                        );
                        btn.setAttribute('onclick', newOnclick);
                    } else if (onclick && onclick.includes('viewFacultyClasses')) {
                        const newOnclick = onclick.replace(
                            /viewFacultyClasses\([^)]+\)/,
                            `viewFacultyClasses(${id}, '${updatedData.newName.replace(/'/g, "\\'")}')`
                        );
                        btn.setAttribute('onclick', newOnclick);
                    }
                });
                
                // Highlight updated row
                row.style.backgroundColor = '#d4edda';
                setTimeout(() => {
                    row.style.backgroundColor = '';
                }, 2000);
            }
        }
    });
}

// Global delete function with confirmation
function confirmDeleteFaculty(facultyId, facultyName) {
    Swal.fire({
        title: 'Are you sure?',
        text: `Delete faculty member "${facultyName}"? This action cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, delete!',
        cancelButtonText: 'Cancel',
        reverseButtons: true
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
            fetch(`ajax/process_faculty.php?action=delete&id=${facultyId}`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Deleted!',
                        text: data.message || 'Faculty member deleted successfully.',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    
                    // Remove row from table
                    removeFacultyRow(facultyId);
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

// Remove faculty row from table with animation
function removeFacultyRow(facultyId) {
    const facultyTable = document.querySelector('#faculty .table tbody');
    
    if (!facultyTable) {
        setTimeout(() => window.location.reload(), 1000);
        return;
    }
    
    const rows = facultyTable.querySelectorAll('tr');
    let rowFound = false;
    
    for (let row of rows) {
        const editBtn = row.querySelector(`button[onclick*="editFaculty(${facultyId}"]`);
        
        if (editBtn) {
            rowFound = true;
            
            // Animate removal
            row.style.backgroundColor = '#f8d7da';
            row.style.transition = 'all 0.3s ease';
            row.style.opacity = '0.5';
            
            setTimeout(() => {
                row.remove();
                
                // Check if table is empty
                const remainingRows = facultyTable.querySelectorAll('tr');
                if (remainingRows.length === 0) {
                    facultyTable.innerHTML = '<tr><td colspan="4" style="text-align: center; color: var(--text-muted);">No faculty members found</td></tr>';
                }
            }, 300);
            break;
        }
    }
    
    if (!rowFound) {
        setTimeout(() => window.location.reload(), 1000);
    }
}

// View faculty classes function
function viewFacultyClasses(faculty_id, faculty_name) {
    Swal.fire({
        title: `<i class="fas fa-spinner fa-spin"></i> Loading Classes for ${faculty_name}`,
        html: 'Please wait...',
        showConfirmButton: false,
        allowOutsideClick: false
    });

    fetch(`ajax/get_faculty_classes.php?faculty_id=${faculty_id}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let classesHTML = '';
            
            if (data.classes && data.classes.length > 0) {
                classesHTML = `
                    <div style="text-align: left; max-height: 400px; overflow-y: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f8f9fa;">
                                    <th style="padding: 0.5rem; border: 1px solid #dee2e6; font-weight: 600;">Course</th>
                                    <th style="padding: 0.5rem; border: 1px solid #dee2e6; font-weight: 600;">Section</th>
                                    <th style="padding: 0.5rem; border: 1px solid #dee2e6; font-weight: 600;">Schedule</th>
                                    <th style="padding: 0.5rem; border: 1px solid #dee2e6; font-weight: 600;">Room</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                data.classes.forEach(cls => {
                    classesHTML += `
                        <tr>
                            <td style="padding: 0.5rem; border: 1px solid #dee2e6;">
                                <strong>${cls.course_code}</strong><br>
                                <small>${cls.course_title || 'N/A'}</small>
                            </td>
                            <td style="padding: 0.5rem; border: 1px solid #dee2e6;">${cls.section}</td>
                            <td style="padding: 0.5rem; border: 1px solid #dee2e6;">
                                <strong>${cls.day}</strong><br>
                                <small>${cls.time}</small>
                            </td>
                            <td style="padding: 0.5rem; border: 1px solid #dee2e6;">${cls.room}</td>
                        </tr>
                    `;
                });
                
                classesHTML += '</tbody></table></div>';
            } else {
                classesHTML = '<p style="text-align: center; color: #6b7280; margin: 2rem 0;">No classes assigned to this faculty member.</p>';
            }

            Swal.fire({
                title: `<i class="fas fa-chalkboard"></i> Classes for ${faculty_name}`,
                html: classesHTML,
                width: '700px',
                confirmButtonText: '<i class="fas fa-times"></i> Close',
                confirmButtonColor: '#6b7280'
            });
        } else {
            if (window.Toast) {
                Toast.fire({ 
                    icon: 'error', 
                    title: data.message || 'Failed to load classes!' 
                });
            } else {
                alert('Error: ' + (data.message || 'Failed to load classes!'));
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (window.Toast) {
            Toast.fire({
                icon: 'error',
                title: 'Network error loading classes.'
            });
        } else {
            alert('Network error loading classes.');
        }
    });
}

// Legacy functions for backward compatibility
function loadFacultyOptions(selectId, selectedFacultyId = null) {
    fetch('ajax/get_faculty_list.php', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        const select = document.getElementById(selectId);
        if (select && data.success && data.faculty) {
            select.innerHTML = '<option value="">Select Faculty</option>';
            data.faculty.forEach(faculty => {
                const option = document.createElement('option');
                option.value = faculty.id;
                option.textContent = faculty.name;
                if (selectedFacultyId && faculty.id == selectedFacultyId) {
                    option.selected = true;
                }
                select.appendChild(option);
            });
        }
    })
    .catch(error => {
        console.error('Error loading faculty options:', error);
        if (window.Toast) {
            Toast.fire({
                type: 'error',
                title: 'Error',
                message: 'Failed to load faculty options.',
                duration: 4000
            });
        }
    });
}

function loadFacultyOptionsForEdit(selectId, selectedFacultyId) {
    loadFacultyOptions(selectId, selectedFacultyId);
}

// Export for global access
window.Faculty = {
    editFaculty,
    viewFacultyClasses,
    confirmDeleteFaculty,
    removeFacultyRow,
    loadFacultyOptions,
    loadFacultyOptionsForEdit
};
// Faculty Account Creation Handler
// Faculty Account Creation Handler with Dynamic Table Update
document.addEventListener('DOMContentLoaded', function() {
    const createFacultyForm = document.getElementById('createFacultyAccountForm');
    
    if (createFacultyForm) {
        createFacultyForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('createFacultyBtn');
            const btnText = submitBtn.querySelector('.btn-text');
            const btnLoading = submitBtn.querySelector('.btn-loading');
            
            const formData = new FormData(createFacultyForm);
            
            submitBtn.disabled = true;
            btnText.style.display = 'none';
            btnLoading.style.display = 'inline-block';
            
            try {
                const response = await fetch('ajax/create_faculty_account.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                console.log('Response data:', data);
                console.log('Faculty data:', data.faculty);
                
                if (data.success) {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Faculty Account Created!',
                        html: `
                            <p><strong>${data.faculty.name}</strong> has been added successfully.</p>
                            <p style="color: #6b7280; font-size: 14px;">Login credentials sent to <strong>${data.faculty.email}</strong></p>
                        `,
                        confirmButtonColor: '#003082'
                    });
                    
                    // Reset form
                    createFacultyForm.reset();
                    
                    // Add new row to table WITHOUT refresh
                    addNewFacultyRow(data.faculty);
                    
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Creation Failed',
                        text: data.message || 'Failed to create faculty account.',
                        confirmButtonColor: '#ef4444'
                    });
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred. Please try again.'
                });
            } finally {
                submitBtn.disabled = false;
                btnText.style.display = 'inline-block';
                btnLoading.style.display = 'none';
            }
        });
    }
});

// Add new faculty row to table dynamically
function addNewFacultyRow(faculty) {
    const facultyTableBody = document.querySelector('#faculty .table tbody');
    
    if (!facultyTableBody) {
        console.error('Faculty table body not found');
        return;
    }
    
    // Remove "no faculty found" message if exists
    const noDataRow = facultyTableBody.querySelector('td[colspan]');
    if (noDataRow) {
        noDataRow.parentElement.remove();
    }
    
    // Create new row
    const newRow = document.createElement('tr');
    newRow.className = 'adding'; // For animation
    newRow.innerHTML = `
        <td>
            <div class="d-flex align-center">
                <div class="user-avatar" style="margin-right: var(--space-3);">
                    ${faculty.name.charAt(0).toUpperCase()}
                </div>
                <div>
                    <div style="font-weight: 600;">${escapeHtml(faculty.name)}</div>
                </div>
            </div>
        </td>
        <td>${escapeHtml(faculty.employee_id)}</td>
        <td>${escapeHtml(faculty.email)}</td>
        <td>
            <span class="badge badge-warning">
                <i class="fas fa-clock"></i> Pending Activation
            </span>
        </td>
        <td>
            <div class="d-flex gap-3">
                <button onclick="resendCredentials(${faculty.id}, '${escapeHtml(faculty.name)}', '${escapeHtml(faculty.email)}')" 
                        class="btn btn-info btn-sm">
                    <i class="fas fa-envelope"></i>
                    Resend Email
                </button>
                <button onclick="viewFacultyClasses(${faculty.id}, '${escapeHtml(faculty.name)}')" 
                        class="btn btn-info btn-sm">
                    <i class="fas fa-eye"></i>
                    View Classes
                </button>
                <button onclick="editFaculty(${faculty.id}, '${escapeHtml(faculty.name)}', '${escapeHtml(faculty.employee_id)}', '${escapeHtml(faculty.email)}')" 
                        class="btn btn-warning btn-sm">
                    <i class="fas fa-edit"></i>
                    Edit
                </button>
                <button onclick="confirmDeleteFaculty(${faculty.id}, '${escapeHtml(faculty.name)}')" 
                        class="btn btn-danger btn-sm">
                    <i class="fas fa-trash"></i>
                    Delete
                </button>
            </div>
        </td>
    `;
    
    // Insert at top of table
    facultyTableBody.insertBefore(newRow, facultyTableBody.firstChild);
    
    // Remove animation class after animation
    setTimeout(() => {
        newRow.classList.remove('adding');
    }, 500);
}

// Resend Credentials Function
async function resendCredentials(facultyId, name, email) {
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
            const response = await fetch('ajax/resend_faculty_credentials.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `faculty_id=${facultyId}`
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

