

console.log('✓ Student Management Module Loaded');

// Global variable for current class code
window.currentClassCodeForMasterlist = '';

/**
 * View Student Masterlist
 */
async function viewStudentMasterlist(classCode) {
    console.log('Opening masterlist for:', classCode);
    
    currentClassCodeForMasterlist = classCode;
    
    // Show modal
    const modal = document.getElementById('student-masterlist-modal');
    if (modal) {
        modal.classList.add('show');
    }
    
    // Set title
    const titleEl = document.getElementById('masterlist-class-title');
    if (titleEl) {
        titleEl.textContent = `Students in ${classCode}`;
    }
    
    // Load students
    await loadClassMasterlist(classCode);
}
/**
 * Filter Masterlist by Status
 */
function filterMasterlistByStatus() {
    const filterValue = document.getElementById('masterlist-status-filter').value;
    const tbody = document.getElementById('masterlist-table-body');
    const rows = tbody.querySelectorAll('tr:not(.loading-cell)');
    
    let visibleCount = 0;
    
    rows.forEach(row => {
        const statusCell = row.querySelector('.status-badge');
        if (!statusCell) return;
        
        const status = statusCell.textContent.trim().toLowerCase();
        
        let shouldShow = false;
        
        if (filterValue === 'all') {
            shouldShow = true;
        } else if (filterValue === 'enrolled') {
            shouldShow = status === 'enrolled';
        } else if (filterValue === 'dropped') {
            shouldShow = status === 'dropped' || status === 'withdrawn';
        }
        
        if (shouldShow) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Update count badge
    const countSpan = document.getElementById('filtered-count-number');
    if (countSpan) {
        countSpan.textContent = visibleCount;
    }
}

/**
 * Load Class Masterlist
 */
async function loadClassMasterlist(classCode) {
    const tbody = document.getElementById('masterlist-table-body');
    if (!tbody) return;
    
    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin" style="color: #667eea;"></i> Loading...</td></tr>';
    
    const formData = new FormData();
    formData.append('action', 'get_class_students');
    formData.append('class_code', classCode);
    formData.append('csrf_token', window.csrfToken || window.APP.csrfToken);
    
    try {
        const response = await fetch((window.APP?.apiPath || '/faculty/ajax/') + 'student_management.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (data.students.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 40px; color: #999;">
                            <i class="fas fa-users-slash" style="font-size: 32px; display: block; margin-bottom: 10px;"></i>
                            No students enrolled yet
                        </td>
                    </tr>
                `;
            } else {
                tbody.innerHTML = data.students.map(student => `
                    <tr>
                        <td style="text-align: center;"><input type="checkbox" class="remove-checkbox" value="${student.student_id}"></td>
                        <td><span class="student-id">${student.student_id}</span></td>
                        <td style="font-weight: 500;">${student.full_name}</td>
                        <td>${student.email || 'N/A'}</td>
                        <td style="text-align: center;">
                        <span class="status-badge ${(student.status || 'enrolled').toLowerCase()}">${(student.status || 'enrolled').toLowerCase()}</span>
                        </td>
                        <td style="text-align: center;">
                        <button onclick="removeStudentFromClass('${student.student_id}', '${classCode}')" class="btn btn-danger btn-sm">
                        <i class="fas fa-trash"></i> Remove
                        </button>
                        </td>
                    </tr>
                `).join('');
                // Update the count after loading students
                filterMasterlistByStatus();
                
                // Select all functionality
                const selectAllRemove = document.getElementById('select-all-remove');
                if (selectAllRemove) {
                    selectAllRemove.addEventListener('change', function() {
                        const checkboxes = document.querySelectorAll('.remove-checkbox');
                        checkboxes.forEach(cb => cb.checked = this.checked);
                        updateBulkRemoveButton();
                    });
                }
                
                // Individual checkbox change
                document.querySelectorAll('.remove-checkbox').forEach(cb => {
                    cb.addEventListener('change', updateBulkRemoveButton);
                });
                
                // Bulk remove button
                const bulkRemoveBtn = document.getElementById('bulk-remove-btn');
                if (bulkRemoveBtn) {
                    bulkRemoveBtn.addEventListener('click', function() {
                        const checked = Array.from(document.querySelectorAll('.remove-checkbox:checked')).map(cb => cb.value);
                        if (checked.length === 0) {
                            Swal.fire({
                                title: 'No Students Selected',
                                text: 'Please select at least one student to remove.',
                                icon: 'warning',
                                confirmButtonColor: '#2563eb'
                            });
                            return;
                        }
                        removeMultipleStudentsFromClass(checked, classCode);
                    });
                }
            }
        } else {
            tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 40px; color: red;">Error: ${data.message}</td></tr>`;
        }
    } catch (error) {
        console.error('Error loading masterlist:', error);
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 40px; color: red;">Failed to load students</td></tr>';
    }
}

/**
 * Close Student Masterlist Modal
 */
function closeStudentMasterlist() {
    const modal = document.getElementById('student-masterlist-modal');
    if (modal) {
        modal.classList.remove('show');
    }
}

/**
 * Show Add Student to Class Modal
 */
function showAddStudentToClassModal() {
    const modal = document.getElementById('add-student-to-class-modal');
    if (modal) {
        modal.classList.add('show');
    }
    
    const searchInput = document.getElementById('search-student-input');
    if (searchInput) {
        searchInput.value = '';
    }
    
    const resultsDiv = document.getElementById('student-search-results');
    if (resultsDiv) {
        resultsDiv.innerHTML = `
            <p style="text-align: center; color: #999; padding: 20px;">
                <i class="fas fa-search" style="font-size: 24px; display: block; margin-bottom: 10px;"></i>
                Start typing to search...
            </p>
        `;
    }
}

/**
 * Close Add Student to Class Modal
 */
function closeAddStudentToClass() {
    const modal = document.getElementById('add-student-to-class-modal');
    if (modal) {
        modal.classList.remove('show');
    }
}

/**
 * Search Students for Enrollment
 */
let searchTimeout;
// Store all students for client-side filtering
let allStudentsForEnrollment = [];

async function searchStudentsForEnrollment() {
    clearTimeout(searchTimeout);
    
    const searchInput = document.getElementById('search-student-input');
    const resultsDiv = document.getElementById('student-search-results');
    
    if (!searchInput || !resultsDiv) return;
    
    const searchTerm = searchInput.value.trim().toLowerCase();
    
    // If no search term and we haven't loaded all students yet, load them
    if (searchTerm.length === 0) {
        if (allStudentsForEnrollment.length === 0) {
            resultsDiv.innerHTML = '<p style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin" style="color: #667eea;"></i> Loading all students...</p>';
            
            searchTimeout = setTimeout(async () => {
                await loadAllStudentsForEnrollment();
                displayEnrollmentStudents(allStudentsForEnrollment);
            }, 300);
        } else {
            displayEnrollmentStudents(allStudentsForEnrollment);
        }
        return;
    }
    
    // If we have students loaded, filter them client-side
    if (allStudentsForEnrollment.length > 0) {
        const filtered = allStudentsForEnrollment.filter(student => {
            const name = student.full_name.toLowerCase();
            const id = student.student_id.toLowerCase();
            const email = (student.email || '').toLowerCase();
            return name.includes(searchTerm) || id.includes(searchTerm) || email.includes(searchTerm);
        });
        
        resultsDiv.innerHTML = '<p style="text-align: center; padding: 10px; color: #666; font-size: 13px;"><i class="fas fa-check-circle"></i> Found ' + filtered.length + ' matching student(s)</p>';
        displayEnrollmentStudents(filtered);
        return;
    }
    
    // Otherwise, fetch all students and filter
    resultsDiv.innerHTML = '<p style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin" style="color: #667eea;"></i> Searching...</p>';
    
    searchTimeout = setTimeout(async () => {
        await loadAllStudentsForEnrollment();
        
        if (allStudentsForEnrollment.length > 0) {
            const filtered = allStudentsForEnrollment.filter(student => {
                const name = student.full_name.toLowerCase();
                const id = student.student_id.toLowerCase();
                const email = (student.email || '').toLowerCase();
                return name.includes(searchTerm) || id.includes(searchTerm) || email.includes(searchTerm);
            });
            
            resultsDiv.innerHTML = '<p style="text-align: center; padding: 10px; color: #666; font-size: 13px;"><i class="fas fa-check-circle"></i> Found ' + filtered.length + ' matching student(s)</p>';
            displayEnrollmentStudents(filtered);
        } else {
            resultsDiv.innerHTML = '<p style="text-align: center; color: red; padding: 20px;">No students available</p>';
        }
    }, 300);
}

/**
 * Load all students available for enrollment
 */
async function loadAllStudentsForEnrollment() {
    const formData = new FormData();
    formData.append('action', 'get_all_students');
    formData.append('class_code', currentClassCodeForMasterlist);
    formData.append('csrf_token', window.csrfToken || window.APP.csrfToken);
    
    try {
        const response = await fetch((window.APP?.apiPath || '/faculty/ajax/') + 'student_management.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success && Array.isArray(data.students)) {
            allStudentsForEnrollment = data.students;
        } else {
            console.error('Error loading students:', data.message);
            allStudentsForEnrollment = [];
        }
    } catch (error) {
        console.error('Error loading students:', error);
        allStudentsForEnrollment = [];
    }
}

/**
 * Display enrollment students in results div
 */
function displayEnrollmentStudents(students) {
    const resultsDiv = document.getElementById('student-search-results');
    if (!resultsDiv) return;
    
    // Filter out already enrolled students
    const availableStudents = students.filter(student => Number(student.already_enrolled) !== 1);
    
    if (availableStudents.length === 0) {
        resultsDiv.innerHTML = '<p style="text-align: center; color: #999; padding: 20px;"><i class="fas fa-search"></i> No students available for enrollment</p>';
    } else {
        // Bulk enroll UI
        let html = '<form id="bulk-enroll-form">';
        // Select all checkbox
        html += '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;"><label style="display: flex; align-items: center; font-weight: 600;"><input type="checkbox" id="select-all-checkbox" style="margin-right: 8px;"> Select All</label><button type="button" id="bulk-enroll-btn" class="btn btn-success"><i class="fas fa-user-plus"></i> Enroll Selected</button></div>';
        // Bulk enroll button at the top
        // html += '<div style="text-align: right; margin-bottom: 16px;"><button type="button" id="bulk-enroll-btn" class="btn btn-success"><i class="fas fa-user-plus"></i> Enroll Selected</button></div>';
        html += availableStudents.map(student => {
            const isEnrolled = Number(student.already_enrolled) === 1;
            const statusLabel = (student.status || '').toLowerCase();
            const statusBadge = statusLabel === 'pending' ? `<span style="display:inline-block;padding:4px 8px;border-radius:8px;background:#f59e0b;color:white;font-size:12px;margin-right:8px;">Pending</span>` : `<span style="display:inline-block;padding:4px 8px;border-radius:8px;background:#10b981;color:white;font-size:12px;margin-right:8px;">Active</span>`;
            return `
            <div class="student-search-item" style="display: flex; justify-content: space-between; align-items: center; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 8px; background: #fff; transition: all 0.2s ease;">
                <div style="display: flex; align-items: center; flex: 1;">
                    ${!isEnrolled ? `<input type="checkbox" class="bulk-enroll-checkbox" name="student_ids[]" value="${student.student_id}" style="margin-right: 12px;">` : `<input type="checkbox" disabled style="margin-right: 12px; opacity: .5;">`}
                    <div>
                        <div class="student-info-name" style="font-weight: 600; color: #1f2937; margin-bottom: 4px;">${statusBadge}${escapeHtml(student.full_name)}</div>
                        <div class="student-info-details" style="font-size: 13px; color: #6b7280;"><i class="fas fa-id-card"></i> ${student.student_id} • <i class="fas fa-envelope"></i> ${escapeHtml(student.email || 'N/A')}</div>
                    </div>
                </div>
                ${isEnrolled ? `
                    <button class="btn btn-outline btn-sm" disabled style="margin-left: 10px; white-space: nowrap; opacity: .8; cursor: default;">
                        <i class="fas fa-check"></i> Enrolled
                    </button>
                ` : `
                    <button class="btn btn-primary btn-sm enroll-student-btn" data-student-id="${student.student_id}" style="margin-left: 10px; white-space: nowrap;">
                        <i class="fas fa-plus"></i> Enroll
                    </button>
                `}
            </div>
        `;}).join('');
        // Button already added at the top
        html += '</form>';
        resultsDiv.innerHTML = html;

        // Select all functionality
        const selectAllCheckbox = document.getElementById('select-all-checkbox');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.bulk-enroll-checkbox');
                checkboxes.forEach(cb => cb.checked = this.checked);
            });
        }

        // Add event listeners to all enroll buttons
        resultsDiv.querySelectorAll('.enroll-student-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const studentId = this.getAttribute('data-student-id');
                enrollStudentToClass(studentId);
            });
        });

        // Bulk enroll button event
        const bulkBtn = document.getElementById('bulk-enroll-btn');
        if (bulkBtn) {
            bulkBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const checked = Array.from(document.querySelectorAll('.bulk-enroll-checkbox:checked')).map(cb => cb.value);
                if (checked.length === 0) {
                    Swal.fire({
                        title: 'No Students Selected',
                        text: 'Please select at least one student to enroll.',
                        icon: 'warning',
                        confirmButtonColor: '#2563eb'
                    });
                    return;
                }
                enrollMultipleStudentsToClass(checked);
            });
        }
    }
}

/**
 * Update class student count in dashboard
 */
function updateClassStudentCount(classCode, increment) {
    // Find the classes table
    const tables = document.querySelectorAll('table.table');
    for (let table of tables) {
        const rows = table.querySelectorAll('tbody tr');
        for (let row of rows) {
            const enrollBtn = row.querySelector('button[onclick*="openAddStudentModal"]');
            if (enrollBtn) {
                const onclick = enrollBtn.getAttribute('onclick');
                const match = onclick.match(/openAddStudentModal\('([^']+)'\)/);
                if (match && match[1] === classCode) {
                    // Found the row, update student count (5th column)
                    const tds = row.querySelectorAll('td');
                    if (tds.length >= 5) {
                        const countTd = tds[4]; // 0-indexed, 4th is Students
                        const currentCount = parseInt(countTd.textContent.trim()) || 0;
                        countTd.textContent = currentCount + increment;
                    }
                    return;
                }
            }
        }
    }
}
function updateBulkRemoveButton() {
    const checked = document.querySelectorAll('.remove-checkbox:checked');
    const bulkRemoveBtn = document.getElementById('bulk-remove-btn');
    if (bulkRemoveBtn) {
        bulkRemoveBtn.style.display = checked.length > 0 ? 'inline-block' : 'none';
    }
}

/**
 * Remove multiple students from class (bulk)
 */
function removeMultipleStudentsFromClass(studentIds, classCode) {
    if (!Array.isArray(studentIds) || studentIds.length === 0) return;
    
    Swal.fire({
        title: 'Remove Multiple Students?',
        html: `Are you sure you want to remove <b>${studentIds.length}</b> students from this class?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#d1d5db',
        confirmButtonText: 'Yes, Remove',
        cancelButtonText: 'Cancel',
        didOpen: (modal) => {
            // Ensure buttons are clickable
            const confirmBtn = modal.querySelector('.swal2-confirm');
            const cancelBtn = modal.querySelector('.swal2-cancel');
            if (confirmBtn) {
                confirmBtn.style.pointerEvents = 'auto';
                confirmBtn.style.cursor = 'pointer';
            }
            if (cancelBtn) {
                cancelBtn.style.pointerEvents = 'auto';
                cancelBtn.style.cursor = 'pointer';
            }
        }
    }).then(async result => {
        if (result.isConfirmed) {
            // Remove all selected students
            const promises = studentIds.map(studentId => removeStudentFromClassSilent(studentId, classCode));
            const results = await Promise.all(promises);
            const successCount = results.filter(success => success).length;
            
            // Show result
            if (successCount > 0) {
                Swal.fire({
                    title: 'Removed!',
                    text: `${successCount} students removed successfully!`,
                    icon: 'success',
                    confirmButtonColor: '#10b981',
                    didOpen: (modal) => {
                        const confirmBtn = modal.querySelector('.swal2-confirm');
                        if (confirmBtn) {
                            confirmBtn.style.pointerEvents = 'auto';
                            confirmBtn.style.cursor = 'pointer';
                        }
                    }
                });
                // Update local student list to mark as not enrolled
                if (allStudentsForEnrollment && allStudentsForEnrollment.length) {
                    studentIds.forEach(studentId => {
                        const idx = allStudentsForEnrollment.findIndex(s => s.student_id === studentId);
                        if (idx !== -1) {
                            allStudentsForEnrollment[idx].already_enrolled = 0;
                        }
                    });
                }
                // Update class student count in dashboard
                updateClassStudentCount(classCode, -successCount);
                loadClassMasterlist(classCode);
            }
        }
    });
}

/**
 * Remove student silently (without confirmation)
 */
async function removeStudentFromClassSilent(studentId, classCode) {
    const formData = new FormData();
    formData.append('action', 'remove_student');
    formData.append('student_id', studentId);
    formData.append('class_code', classCode);
    formData.append('csrf_token', window.csrfToken || window.APP.csrfToken);
    
    try {
        const response = await fetch((window.APP?.apiPath || '/faculty/ajax/') + 'student_management.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        return data.success;
    } catch (error) {
        console.error('Remove error:', error);
        return false;
    }
}
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

/**
 * Enroll multiple students to class (bulk)
 */
function enrollMultipleStudentsToClass(studentIds) {
    if (!Array.isArray(studentIds) || studentIds.length === 0) return;
    const classCode = window.currentClassCodeForMasterlist;
    const csrfToken = window.csrfToken || (window.APP && window.APP.csrfToken);

    Swal.fire({
        title: 'Confirm Bulk Enrollment',
        html: `Are you sure you want to enroll <b>${studentIds.length}</b> students to this class?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#d1d5db',
        confirmButtonText: 'Yes, Enroll',
        cancelButtonText: 'Cancel',
        didOpen: (modal) => {
            // Ensure confirm/cancel buttons are always clickable
            const confirmBtn = modal.querySelector('.swal2-confirm');
            const cancelBtn = modal.querySelector('.swal2-cancel');
            if (confirmBtn) {
                confirmBtn.style.pointerEvents = 'auto';
                confirmBtn.style.cursor = 'pointer';
            }
            if (cancelBtn) {
                cancelBtn.style.pointerEvents = 'auto';
                cancelBtn.style.cursor = 'pointer';
            }
        }
    }).then(result => {
        if (result.isConfirmed) {
            // AJAX request to enroll multiple students
            fetch((window.APP?.apiPath || '/faculty/ajax/') + 'student_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=bulk_enroll&class_code=${encodeURIComponent(classCode)}&student_ids=${encodeURIComponent(JSON.stringify(studentIds))}&csrf_token=${encodeURIComponent(csrfToken)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Enrolled!',
                        text: `${studentIds.length} students enrolled successfully!`,
                        icon: 'success',
                        confirmButtonColor: '#10b981',
                        didOpen: (modal) => {
                            // Ensure confirm button is always clickable
                            const confirmBtn = modal.querySelector('.swal2-confirm');
                            if (confirmBtn) {
                                confirmBtn.style.pointerEvents = 'auto';
                                confirmBtn.style.cursor = 'pointer';
                            }
                        }
                    });
                    // Update local student list to mark as enrolled
                    if (allStudentsForEnrollment && allStudentsForEnrollment.length) {
                        studentIds.forEach(studentId => {
                            const idx = allStudentsForEnrollment.findIndex(s => s.student_id === studentId);
                            if (idx !== -1) {
                                allStudentsForEnrollment[idx].already_enrolled = 1;
                                if (allStudentsForEnrollment[idx].status && allStudentsForEnrollment[idx].status.toLowerCase() === 'pending') {
                                    allStudentsForEnrollment[idx].status = 'active';
                                }
                            }
                        });
                    }
                    // Update class student count in dashboard
                    updateClassStudentCount(classCode, studentIds.length);
                    // Refresh student list
                    searchStudentsForEnrollment();
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: data.message || 'Failed to enroll students.',
                        icon: 'error',
                        confirmButtonColor: '#ef4444'
                    });
                }
            })
            .catch(() => {
                Swal.fire({
                    title: 'Error',
                    text: 'Failed to enroll students.',
                    icon: 'error',
                    confirmButtonColor: '#ef4444'
                });
            });
        }
    });
}

// Original error handler snippet (to be completed):
/*
        } catch (error) {
            console.error('Search error:', error);
            resultsDiv.innerHTML = '<p style="text-align: center; color: red; padding: 20px;">Search failed</p>';
        }
    }, 500);
}

/**
 * Enroll Student to Class
 */
async function enrollStudentToClass(studentId) {
    // SIMPLE CONFIRMATION
    const result = await Swal.fire({
        title: 'Enroll Student?',
        text: 'Add this student to the class roster?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#fbbf24',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Enroll',
        cancelButtonText: 'Cancel',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: (modal) => {
            // Ensure buttons are clickable
            const confirmBtn = modal.querySelector('.swal2-confirm');
            const cancelBtn = modal.querySelector('.swal2-cancel');
            if (confirmBtn) {
                confirmBtn.style.pointerEvents = 'auto';
                confirmBtn.style.cursor = 'pointer';
            }
            if (cancelBtn) {
                cancelBtn.style.pointerEvents = 'auto';
                cancelBtn.style.cursor = 'pointer';
            }
        }
    });

    if (!result.isConfirmed) return;
    
    if (typeof showLoading === 'function') showLoading();
    
    const formData = new FormData();
    formData.append('action', 'enroll_student');
    formData.append('student_id', studentId);
    formData.append('class_code', currentClassCodeForMasterlist);
    formData.append('csrf_token', window.csrfToken || window.APP.csrfToken);
    
    try {
        const response = await fetch((window.APP?.apiPath || '/faculty/ajax/') + 'student_management.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (typeof hideLoading === 'function') hideLoading();
        
        if (data.success) {
            // SUCCESS TOAST
            if (typeof Toast !== 'undefined') {
                Toast.fire({ 
                    icon: 'success', 
                    title: 'Student enrolled successfully!',
                    timer: 2000
                });
            }
            
            const searchInput = document.getElementById('search-student-input');
            if (searchInput && searchInput.value.trim().length >= 2) {
                // Re-run the search to update the list
                searchStudentsForEnrollment();
            } else {
                // Clear search if empty
                const resultsDiv = document.getElementById('student-search-results');
                if (resultsDiv) {
                    resultsDiv.innerHTML = `
                        <p style="text-align: center; color: #10b981; padding: 20px;">
                            <i class="fas fa-check-circle" style="font-size: 32px; display: block; margin-bottom: 10px;"></i>
                            <strong>Student enrolled!</strong><br>
                            <span style="color: #6b7280; font-size: 14px;">Search for another student to enroll</span>
                        </p>
                    `;
                }
            }
            
            
            // Mark the student locally as enrolled so UI updates immediately
            if (allStudentsForEnrollment && allStudentsForEnrollment.length) {
                const idx = allStudentsForEnrollment.findIndex(s => s.student_id === studentId);
                    if (idx !== -1) {
                        allStudentsForEnrollment[idx].already_enrolled = 1;
                        // after successful enrollment, pending students become active
                        if (allStudentsForEnrollment[idx].status && allStudentsForEnrollment[idx].status.toLowerCase() === 'pending') {
                        allStudentsForEnrollment[idx].status = 'active';
                        }
                }
            }

            // Update class student count in dashboard
            updateClassStudentCount(currentClassCodeForMasterlist, 1);

            // Re-render search results (if visible) to reflect enrolled state
            try { searchStudentsForEnrollment(); } catch (e) { /* ignore */ }

            loadClassMasterlist(currentClassCodeForMasterlist);
            
        } else {
            if (typeof Toast !== 'undefined') {
                Toast.fire({ icon: 'error', title: data.message || 'Enrollment failed' });
            } else {
                alert(data.message || 'Enrollment failed');
            }
        }
    } catch (error) {
        if (typeof hideLoading === 'function') hideLoading();
        console.error('Enrollment error:', error);
        if (typeof Toast !== 'undefined') {
            Toast.fire({ icon: 'error', title: 'Enrollment error' });
        }
    }
}

/**
 * Remove Student from Class
 */
async function removeStudentFromClass(studentId, classCode) {
    let confirmed = false;
    
    if (typeof Swal !== 'undefined') {
        const result = await Swal.fire({
            title: 'Remove Student?',
            text: 'This student will be removed from the class.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, remove',
            cancelButtonText: 'Cancel',
            didOpen: (modal) => {
                // Ensure buttons are clickable
                const confirmBtn = modal.querySelector('.swal2-confirm');
                const cancelBtn = modal.querySelector('.swal2-cancel');
                if (confirmBtn) {
                    confirmBtn.style.pointerEvents = 'auto';
                    confirmBtn.style.cursor = 'pointer';
                }
                if (cancelBtn) {
                    cancelBtn.style.pointerEvents = 'auto';
                    cancelBtn.style.cursor = 'pointer';
                }
            }
        });
        confirmed = result.isConfirmed;
    } else {
        confirmed = confirm('Remove this student from the class?');
    }

    if (!confirmed) return;
    
    if (typeof showLoading === 'function') showLoading();
    
    const formData = new FormData();
    formData.append('action', 'remove_student');
    formData.append('student_id', studentId);
    formData.append('class_code', classCode);
    formData.append('csrf_token', window.csrfToken || window.APP.csrfToken);
    
    try {
        const response = await fetch((window.APP?.apiPath || '/faculty/ajax/') + 'student_management.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (typeof hideLoading === 'function') hideLoading();
        
        if (data.success) {
            // Success dialog
            Swal.fire({
                title: 'Removed!',
                text: 'Student removed from class successfully.',
                icon: 'success',
                confirmButtonColor: '#10b981',
                didOpen: (modal) => {
                    // Ensure confirm button is always clickable
                    const confirmBtn = modal.querySelector('.swal2-confirm');
                    if (confirmBtn) {
                        confirmBtn.style.pointerEvents = 'auto';
                        confirmBtn.style.cursor = 'pointer';
                    }
                }
            });
            // Update class student count in dashboard
            updateClassStudentCount(classCode, -1);
            // Update cached students list if present
            if (allStudentsForEnrollment && allStudentsForEnrollment.length) {
                const idx = allStudentsForEnrollment.findIndex(s => s.student_id === studentId);
                if (idx !== -1) {
                    allStudentsForEnrollment[idx].already_enrolled = 0;
                }
            }
            try { searchStudentsForEnrollment(); } catch (e) { /* ignore */ }

            loadClassMasterlist(classCode);
        } else {
            if (typeof Toast !== 'undefined') {
                Toast.fire({ icon: 'error', title: data.message || 'Remove failed' });
            } else {
                alert(data.message || 'Remove failed');
            }
        }
    } catch (error) {
        if (typeof hideLoading === 'function') hideLoading();
        console.error('Remove error:', error);
        if (typeof Toast !== 'undefined') {
            Toast.fire({ icon: 'error', title: 'Remove error' });
        }
    }
}

/**
 * Open Add Student Modal (from dashboard cards)
 */
function openAddStudentModal(classCode) {
    currentClassCodeForMasterlist = classCode;
    showAddStudentToClassModal();
}

/**
 * Enroll all unenrolled students at once
 */
async function enrollAllUnenrolledStudents() {
    if (!allStudentsForEnrollment || allStudentsForEnrollment.length === 0) {
        if (typeof Toast !== 'undefined') {
            Toast.fire({ icon: 'warning', title: 'No students loaded' });
        }
        return;
    }
    
    const unenrolled = allStudentsForEnrollment.filter(s => Number(s.already_enrolled) !== 1);
    
    if (unenrolled.length === 0) {
        if (typeof Toast !== 'undefined') {
            Toast.fire({ icon: 'info', title: 'All students are already enrolled' });
        }
        return;
    }
    
    // Confirm with user
    if (typeof Swal !== 'undefined') {
        const result = await Swal.fire({
            title: 'Enroll All Students?',
            html: `You are about to enroll <strong>${unenrolled.length} student${unenrolled.length > 1 ? 's' : ''}</strong> to this class.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: `Yes, enroll ${unenrolled.length}`,
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#3b82f6'
        });
        
        if (!result.isConfirmed) return;
    } else {
        if (!confirm(`Enroll all ${unenrolled.length} students?`)) return;
    }
    
    // Show loading
    if (typeof showLoading === 'function') showLoading('Enrolling students...');
    
    let successCount = 0;
    let errorCount = 0;
    
    for (const student of unenrolled) {
        try {
            const formData = new FormData();
            formData.append('action', 'enroll_student');
            formData.append('student_id', student.student_id);
            formData.append('class_code', currentClassCodeForMasterlist);
            formData.append('csrf_token', window.csrfToken || window.APP.csrfToken);
            
            const response = await fetch((window.APP?.apiPath || '/faculty/ajax/') + 'student_management.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                successCount++;
                // Update cache
                student.already_enrolled = 1;
            } else {
                errorCount++;
                console.error(`Failed to enroll ${student.student_id}:`, data.message);
            }
        } catch (error) {
            errorCount++;
            console.error(`Error enrolling ${student.student_id}:`, error);
        }
    }
    
    if (typeof hideLoading === 'function') hideLoading();
    
    // Show result
    if (typeof Toast !== 'undefined') {
        if (errorCount === 0) {
            Toast.fire({ 
                icon: 'success', 
                title: `Successfully enrolled all ${successCount} students!` 
            });
        } else {
            Toast.fire({ 
                icon: 'warning', 
                title: `Enrolled ${successCount}, ${errorCount} failed` 
            });
        }
    }
    
    // Refresh the list
    searchStudentsForEnrollment();
    
    // Reload masterlist if it's open
    if (currentClassCodeForMasterlist) {
        loadClassMasterlist(currentClassCodeForMasterlist);
    }
}

// Export functions to global scope
window.viewStudentMasterlist = viewStudentMasterlist;
window.loadClassMasterlist = loadClassMasterlist;
window.closeStudentMasterlist = closeStudentMasterlist;
window.showAddStudentToClassModal = showAddStudentToClassModal;
window.closeAddStudentToClass = closeAddStudentToClass;
window.searchStudentsForEnrollment = searchStudentsForEnrollment;
window.enrollStudentToClass = enrollStudentToClass;
window.removeStudentFromClass = removeStudentFromClass;
window.openAddStudentModal = openAddStudentModal;
window.enrollAllUnenrolledStudents = enrollAllUnenrolledStudents;
window.filterMasterlistByStatus = filterMasterlistByStatus;

console.log('✓ Student Management functions exported to global scope:', {
    viewStudentMasterlist: typeof window.viewStudentMasterlist,
    openAddStudentModal: typeof window.openAddStudentModal,
    filterMasterlistByStatus: typeof window.filterMasterlistByStatus
});

