
console.log('✓ Grading Integration Module Loaded');

/**
 * Load Classes for Grading Selection
 */
async function loadFlexibleClassesForGrading(academicYear, term) {
    const classSelect = document.getElementById('flex_grading_class');
    if (!classSelect) {
        console.error('Class select element not found');
        return;
    }
    
    classSelect.innerHTML = '<option value="">Loading...</option>';
    classSelect.disabled = true;

    const formData = new FormData();
    formData.append('action', 'get_classes');
    formData.append('academic_year', academicYear);
    formData.append('term', term);
    formData.append('csrf_token', window.csrfToken || window.APP.csrfToken);

    try {
        const response = await fetch((window.APP?.apiPath || '/faculty/ajax/') + 'process_grades.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            classSelect.innerHTML = '<option value="">-- Select Class --</option>';
            data.classes.forEach(cls => {
                const option = document.createElement('option');
                option.value = cls.class_code;
                option.textContent = `${cls.course_code} - ${cls.course_title || 'Course'} (${cls.section}) - ${cls.student_count} students`;
                classSelect.appendChild(option);
            });
            classSelect.disabled = false;
        } else {
            if (typeof Toast !== 'undefined') {
                Toast.fire({ icon: 'error', title: data.message || 'Failed to load classes' });
            }
            classSelect.innerHTML = '<option value="">Error loading classes</option>';
        }
    } catch (error) {
        console.error('Error loading classes:', error);
        if (typeof Toast !== 'undefined') {
            Toast.fire({ icon: 'error', title: 'Failed to load classes' });
        }
        classSelect.innerHTML = '<option value="">Error loading classes</option>';
    }
}

/**
 * Load Class and Initialize Grading System
 */
async function loadClassAndGrading() {
    const academicYear = document.getElementById('flex_grading_academic_year')?.value;
    const term = document.getElementById('flex_grading_term')?.value;
    const classCode = document.getElementById('flex_grading_class')?.value;
    
    if (!classCode) {
        if (typeof Toast !== 'undefined') {
            Toast.fire({ icon: 'error', title: 'Please select a class' });
        } else {
            alert('Please select a class');
        }
        return;
    }
    
    if (typeof showLoading === 'function') showLoading();
    
    try {
        // Show the grading interface container
        const gradingInterface = document.getElementById('flexible-grading-interface');
        if (gradingInterface) {
            gradingInterface.classList.remove('grading-interface-hidden');  // ✅ FIXED!
        }
        
        // Initialize grading system from flexible_grading.js
        if (typeof initGrading === 'function') {
            await initGrading(classCode);
        } else {
            throw new Error('Grading system not loaded. Check flexible_grading.js');
        }
        
        if (typeof hideLoading === 'function') hideLoading();
        
    } catch (error) {
        console.error('Error loading grading system:', error);
        if (typeof Toast !== 'undefined') {
            Toast.fire({ icon: 'error', title: 'Failed to load grading system' });
        }
        if (typeof hideLoading === 'function') hideLoading();
    }
}
/**
 * Load Students for Selected Class
 */
async function loadFlexibleStudents(classCode) {
    const formData = new FormData();
    formData.append('action', 'get_students');
    formData.append('class_code', classCode);
    formData.append('csrf_token', window.csrfToken || window.APP.csrfToken);

    try {
        const response = await fetch((window.APP?.apiPath || '/faculty/ajax/') + 'process_grades.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            window.currentStudents = data.students;
            
            // Update flexible grading system if available
            if (typeof FGS !== 'undefined') {
                FGS.students = data.students;
            }
            
            // Update UI
            const classSelect = document.getElementById('flex_grading_class');
            if (classSelect) {
                const classText = classSelect.options[classSelect.selectedIndex]?.textContent || classCode;
                
                const titleEl = document.getElementById('flex-selected-class-title');
                if (titleEl) {
                    titleEl.innerHTML = `<i class="fas fa-users"></i> ${classText}`;
                }
                
                const countEl = document.getElementById('flex-student-count');
                if (countEl) {
                    countEl.textContent = `${data.students.length} students enrolled`;
                }
            }
            
            console.log(`✓ Loaded ${data.students.length} students`);
        } else {
            if (typeof Toast !== 'undefined') {
                Toast.fire({ icon: 'error', title: 'Failed to load students' });
            }
        }
    } catch (error) {
        console.error('Error loading students:', error);
        if (typeof Toast !== 'undefined') {
            Toast.fire({ icon: 'error', title: 'Error loading students' });
        }
    }
}

/**
 * Switch Between Midterm, Finals, and Summary Tabs
 */
function switchFlexibleMainTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.main-tab-content').forEach(c => c.classList.remove('active'));
    document.querySelectorAll('.main-tab-btn').forEach(b => b.classList.remove('active'));
    
    // Show selected tab content
    const tabContent = document.getElementById(`flex-${tabName}-content`);
    if (tabContent) {
        tabContent.classList.add('active');
    }
    
    // Activate tab button
    const tabButton = document.querySelector(`[data-term="${tabName}"]`);
    if (tabButton) {
        tabButton.classList.add('active');
    }
    
    // Handle term-specific logic
    if (typeof FGS !== 'undefined') {
        if (tabName === 'midterm') {
            FGS.currentTermType = 'midterm';
            if (typeof switchTerm === 'function') {
                switchTerm('midterm');
            }
        } else if (tabName === 'finals') {
            FGS.currentTermType = 'finals';
            if (typeof switchTerm === 'function') {
                switchTerm('finals');
            }
        } else if (tabName === 'summary') {
            // Render summary using flexible_grading.js function
            if (typeof renderSummary === 'function') {
                renderSummary();
            } else {
                console.warn('renderSummary function not found in flexible_grading.js');
            }
        }
    }
}

/**
 * Export Grades to CSV
 */
function exportFlexibleGradesToCSV() {
    if (!window.currentStudents || window.currentStudents.length === 0) {
        if (typeof Toast !== 'undefined') {
            Toast.fire({ icon: 'warning', title: 'No students to export' });
        } else {
            alert('No students to export');
        }
        return;
    }
    
    // CSV Headers
    let csv = 'Student ID,Name,Midterm,Finals,Term Grade\n';
    
    // Add student data
    window.currentStudents.forEach(student => {
        const midterm = 0;  // TODO: Calculate actual midterm grade
        const finals = 0;   // TODO: Calculate actual finals grade
        const term = (midterm + finals) / 2;
        
        csv += `${student.student_id},"${student.full_name || student.name}",${midterm},${finals},${term}\n`;
    });
    
    // Create and download file
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `grades_${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    
    if (typeof Toast !== 'undefined') {
        Toast.fire({ icon: 'success', title: 'Grades exported successfully' });
    }
}

/**
 * Auto-compact Table Mode for Many Columns
 */
function checkTableCompactMode() {
    const tables = document.querySelectorAll('.flexible-grading-table');
    tables.forEach(table => {
        const headerCells = table.querySelectorAll('thead th');
        if (headerCells.length > 10) {
            table.classList.add('compact-mode');
        } else {
            table.classList.remove('compact-mode');
        }
    });
}

/**
 * Initialize Event Listeners for Grading System
 */
function initGradingSystemListeners() {
    const yearSelect = document.getElementById('flex_grading_academic_year');
    const termSelect = document.getElementById('flex_grading_term');
    const classSelect = document.getElementById('flex_grading_class');

    if (yearSelect && termSelect) {
        yearSelect.addEventListener('change', function() {
            if (this.value && termSelect.value) {
                loadFlexibleClassesForGrading(this.value, termSelect.value);
            } else {
                if (classSelect) {
                    classSelect.innerHTML = '<option value="">-- Select Class --</option>';
                    classSelect.disabled = true;
                }
            }
        });

        termSelect.addEventListener('change', function() {
            if (this.value && yearSelect.value) {
                loadFlexibleClassesForGrading(yearSelect.value, this.value);
            } else {
                if (classSelect) {
                    classSelect.innerHTML = '<option value="">-- Select Class --</option>';
                    classSelect.disabled = true;
                }
            }
        });
    }
    
    // Check for compact mode when tables load
    setTimeout(checkTableCompactMode, 500);
    
    // Watch for table changes
    const containers = document.querySelectorAll('#flexible-table-container, #flexible-table-container-finals');
    if (containers.length > 0) {
        const observer = new MutationObserver(checkTableCompactMode);
        containers.forEach(container => {
            observer.observe(container, { childList: true, subtree: true });
        });
    }
}

// Initialize listeners when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initGradingSystemListeners);
} else {
    initGradingSystemListeners();
}

// Export functions to global scope
window.loadFlexibleClassesForGrading = loadFlexibleClassesForGrading;
window.loadClassAndGrading = loadClassAndGrading;
window.loadFlexibleStudents = loadFlexibleStudents;
window.switchFlexibleMainTab = switchFlexibleMainTab;
window.exportFlexibleGradesToCSV = exportFlexibleGradesToCSV;
window.checkTableCompactMode = checkTableCompactMode;

console.log('✓ Grading Integration Ready');
