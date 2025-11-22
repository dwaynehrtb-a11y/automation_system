

console.log('✓ Student Management Module Loaded');

// Global variable for current class code
let currentClassCodeForMasterlist = '';

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
    
    tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin" style="color: #667eea;"></i> Loading...</td></tr>';
    
    const formData = new FormData();
    formData.append('action', 'get_class_students');
    formData.append('class_code', classCode);
    formData.append('csrf_token', window.csrfToken || window.APP.csrfToken);
    
    try {
        const response = await fetch('/automation_system/faculty/ajax/student_management.php', {
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
                        <td><span class="student-id">${student.student_id}</span></td>
                        <td style="font-weight: 500;">${student.full_name}</td>
                        <td>${student.email || 'N/A'}</td>
                        <td style="text-align: center;">
                        <span class="status-badge ${(student.status || 'enrolled').toLowerCase()}">${student.status || 'Enrolled'}</span>
                        </td>
                        <td style="text-align: center;">
                        <button onclick="removeStudentFromClass('${student.student_id}', '${classCode}')" class="btn btn-danger btn-sm">
                        <i class="fas fa-trash"></i> Remove
                        </button>
                        </td>
                    </tr>
                `).join('');
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
async function searchStudentsForEnrollment() {
    clearTimeout(searchTimeout);
    
    const searchInput = document.getElementById('search-student-input');
    const resultsDiv = document.getElementById('student-search-results');
    
    if (!searchInput || !resultsDiv) return;
    
    const searchTerm = searchInput.value.trim();
    
    if (searchTerm.length < 2) {
        resultsDiv.innerHTML = '<p style="text-align: center; color: #999; padding: 20px;">Type at least 2 characters...</p>';
        return;
    }
    
    resultsDiv.innerHTML = '<p style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin" style="color: #667eea;"></i> Searching...</p>';
    
    searchTimeout = setTimeout(async () => {
        const formData = new FormData();
        formData.append('action', 'search_students');
        formData.append('search_term', searchTerm);
        formData.append('class_code', currentClassCodeForMasterlist);
        formData.append('csrf_token', window.csrfToken || window.APP.csrfToken);
        
        try {
            const response = await fetch('/automation_system/faculty/ajax/student_management.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                if (data.students.length === 0) {
                    resultsDiv.innerHTML = '<p style="text-align: center; color: #999; padding: 20px;">No students found</p>';
                } else {
                    resultsDiv.innerHTML = data.students.map(student => `
                        <div class="student-search-item">
                            <div>
                                <div class="student-info-name">${student.full_name}</div>
                                <div class="student-info-details">${student.student_id} • ${student.email || 'No email'}</div>
                            </div>
                            <button class="btn btn-primary btn-sm enroll-student-btn" data-student-id="${student.student_id}">
                                <i class="fas fa-plus"></i> Enroll
                            </button>
                        </div>
                    `).join('');
                    
                    // Add event listeners to all enroll buttons
                    resultsDiv.querySelectorAll('.enroll-student-btn').forEach(btn => {
                        btn.addEventListener('click', function(e) {
                            e.preventDefault();
                            const studentId = this.getAttribute('data-student-id');
                            enrollStudentToClass(studentId);
                        });
                    });
                }
            } else {
                resultsDiv.innerHTML = `<p style="text-align: center; color: red; padding: 20px;">Error: ${data.message}</p>`;
            }
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
        const response = await fetch('/automation_system/faculty/ajax/student_management.php', {
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
            cancelButtonText: 'Cancel'
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
        const response = await fetch('/automation_system/faculty/ajax/student_management.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (typeof hideLoading === 'function') hideLoading();
        
        if (data.success) {
            if (typeof Toast !== 'undefined') {
                Toast.fire({ icon: 'success', title: 'Student removed successfully' });
            }
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