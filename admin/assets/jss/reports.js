// js/reports.js - Reports & Analytics Functions
// =============================================

// GENERATE REPORT
function generateReport() {
    const reportType = document.getElementById('reportType')?.value;
    const reportPeriod = document.getElementById('reportPeriod')?.value;

    if (!reportType) {
        Toast.fire({
            icon: 'warning',
            title: 'Please select a report type'
        });
        return;
    }

    Toast.fire({
        icon: 'info',
        title: 'Generating report...',
        timer: 2000
    });
    
    // Open report in new window
    const reportUrl = `reports/generate_report.php?type=${reportType}&period=${reportPeriod || ''}`;
    window.open(reportUrl, '_blank');
}

// VIEW CLASS SCHEDULES
function viewClassSchedules(section, course_code, academic_year, term) {
    Swal.fire({
        title: `<i class="fas fa-spinner fa-spin"></i> Loading Schedules for ${section} - ${course_code}`,
        html: 'Please wait...',
        showConfirmButton: false,
        allowOutsideClick: false
    });

    fetch(`ajax/get_class_schedules.php?section=${section}&course_code=${course_code}&academic_year=${academic_year}&term=${term}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data) {
        const schedulesHTML = createScheduleViewHTML(section, course_code, academic_year, term, data.data);

            Swal.fire({
                title: `<i class="fas fa-calendar-alt"></i> ${section} - ${course_code} Schedule`,
                html: schedulesHTML,
                width: '800px',
                confirmButtonText: '<i class="fas fa-times"></i> Close',
                confirmButtonColor: '#6b7280'
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error Loading Schedules',
                text: data.message || 'Failed to load class schedules',
                confirmButtonText: 'OK'
            });
        }
    })
    .catch(error => {
        console.error('Detailed error:', error);
        Toast.fire({
            icon: 'error',
            title: 'Network error: ' + (error.message || 'An error occurred')
        });
    });
}

// CREATE SCHEDULE VIEW HTML
function createScheduleViewHTML(section, course_code, academic_year, term, schedules) {
    let schedulesHTML = `
        <div style="text-align: left; max-height: 500px; overflow-y: auto;">
            <div style="margin-bottom: 1.5rem; padding: 1.5rem; background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border-radius: 0.75rem; border: 1px solid #93c5fd;">
                <h4 style="margin: 0 0 0.75rem 0; color: #1e40af; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-info-circle"></i>
                    Class Information
                </h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.875rem;">
                    <div>
                        <p style="margin: 0.25rem 0;"><strong style="color: #374151;">Section:</strong> <span style="color: #1e40af;">${section}</span></p>
                        <p style="margin: 0.25rem 0;"><strong style="color: #374151;">Course:</strong> <span style="color: #1e40af;">${course_code}</span></p>
                    </div>
                    <div>
                        <p style="margin: 0.25rem 0;"><strong style="color: #374151;">Academic Year:</strong> <span style="color: #1e40af;">${academic_year}</span></p>
                        <p style="margin: 0.25rem 0;"><strong style="color: #374151;">Term:</strong> <span style="color: #1e40af;">${term}</span></p>
                    </div>
                </div>
            </div>
            
            <h5 style="margin-bottom: 1rem; color: #374151; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-calendar-week" style="color: #059669;"></i>
                Weekly Schedule (${schedules.length} time slots)
            </h5>
    `;
    
    if (schedules.length > 0) {
        schedules.forEach((schedule, index) => {
            schedulesHTML += createScheduleCardHTML(schedule, index);
        });
        
        schedulesHTML += `
            <div style="margin-top: 1.5rem; padding: 1rem; background: #f0f9ff; border-radius: 0.5rem; border: 1px solid #bae6fd; text-align: center;">
                <p style="margin: 0; color: #0369a1; font-size: 0.875rem;">
                    <i class="fas fa-lightbulb" style="color: #f59e0b;"></i>
                    <strong>Total:</strong> ${schedules.length} scheduled time slots for this class
                </p>
            </div>
        `;
    } else {
        schedulesHTML += '<p style="text-align: center; color: #6b7280; padding: 2rem;">No schedules found for this class.</p>';
    }
    
    schedulesHTML += '</div>';
    return schedulesHTML;
}

// CREATE INDIVIDUAL SCHEDULE CARD HTML
function createScheduleCardHTML(schedule, index) {
    return `
        <div style="border: 1px solid #e5e7eb; padding: 1.25rem; margin-bottom: 1rem; border-radius: 0.75rem; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                <h6 style="margin: 0; color: #1f2937; font-size: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    <span style="background: #059669; color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 600;">${index + 1}</span>
                    ${schedule.day} Class
                </h6>
                <div style="display: flex; gap: 0.5rem;">
                    <button onclick="editClassFromReport(${schedule.class_id}, '${schedule.section}', '${schedule.academic_year}', '${schedule.term}', '${schedule.course_code}', '${schedule.day}', '${schedule.time}', '${schedule.room}', ${schedule.faculty_id}); Swal.close();" 
                            class="btn btn-warning btn-sm" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button onclick="confirmDeleteIndividualClass(${schedule.class_id}, '${schedule.day}', '${schedule.time}'); Swal.close();" 
                            class="btn btn-danger btn-sm" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </div>
            
            <div style="background: #f8fafc; padding: 1rem; border-radius: 0.5rem; border-left: 4px solid #059669;">
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; font-size: 0.875rem;">
                    <div>
                        <p style="margin: 0.25rem 0; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-clock" style="color: #f59e0b; width: 16px;"></i>
                            <strong style="color: #374151;">Time:</strong>
                        </p>
                        <p style="margin: 0; color: #059669; font-weight: 500;">${schedule.time}</p>
                    </div>
                    <div>
                        <p style="margin: 0.25rem 0; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-door-open" style="color: #8b5cf6; width: 16px;"></i>
                            <strong style="color: #374151;">Room:</strong>
                        </p>
                        <p style="margin: 0; color: #7c3aed; font-weight: 500;">${schedule.room}</p>
                    </div>
                    <div>
                        <p style="margin: 0.25rem 0; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-user-tie" style="color: #dc2626; width: 16px;"></i>
                            <strong style="color: #374151;">Faculty:</strong>
                        </p>
                        <p style="margin: 0; color: #dc2626; font-weight: 500;">${schedule.faculty_name}</p>
                    </div>
                </div>
                
                <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid #e5e7eb; font-size: 0.75rem; color: #6b7280;">
                    <p style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-book"></i>
                        <strong>Course:</strong> ${schedule.course_title || 'N/A'} | 
                        <strong>Class ID:</strong> ${schedule.class_id}
                    </p>
                </div>
            </div>
        </div>
    `;
}

// SAFE EDIT FUNCTION THAT CHECKS FOR DEPENDENCIES
function editClassFromReport(classId, section, academicYear, term, courseCode, day, time, room, facultyId) {
    console.log('editClassFromReport called with:', {classId, section, academicYear, term, courseCode, day, time, room, facultyId});
    
    // Check if editClass function is available (from classes.js)
    if (typeof window.editClass === 'function') {
        // Call the editClass function directly
        window.editClass(classId, section, academicYear, term, courseCode, day, time, room, facultyId);
    } else if (typeof editClass === 'function') {
        // Try global scope
        editClass(classId, section, academicYear, term, courseCode, day, time, room, facultyId);
    } else {
        // Function not available - show error and try to navigate
        console.error('editClass function not found. classes.js may not be loaded.');
        
        Toast.fire({
            icon: 'warning',
            title: 'Edit function not available. Redirecting to classes page...'
        });
        
        // Try to navigate to classes page with edit parameters
        setTimeout(() => {
            const editUrl = `admin_dashboard.php?page=classes&edit=${classId}&section=${encodeURIComponent(section)}&academic_year=${encodeURIComponent(academicYear)}&term=${encodeURIComponent(term)}&course_code=${encodeURIComponent(courseCode)}&day=${encodeURIComponent(day)}&time=${encodeURIComponent(time)}&room=${encodeURIComponent(room)}&faculty_id=${facultyId}`;
            window.location.href = editUrl;
        }, 1500);
    }
}

// DELETE INDIVIDUAL CLASS SCHEDULE
function confirmDeleteIndividualClass(class_id, day, time) {
    Swal.fire({
        title: 'Delete This Schedule?',
        html: `
            <div style="text-align: left;">
                <p>Are you sure you want to delete this specific schedule?</p>
                <div style="background: #f3f4f6; padding: 1rem; border-radius: 0.5rem; margin: 1rem 0;">
                    <p><strong>Day:</strong> ${day}</p>
                    <p><strong>Time:</strong> ${time}</p>
                </div>
                <div style="font-size: 0.875rem; color: #6b7280;">
                    <i class="fas fa-info-circle"></i> 
                    This will only delete this specific time slot, not the entire class.
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        confirmButtonText: '<i class="fas fa-trash"></i> Delete Schedule',
        cancelButtonText: '<i class="fas fa-times"></i> Cancel',
        width: '500px'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', class_id);
            
            fetch('ajax/process_class.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Toast.fire({
                        icon: 'success',
                        title: 'Schedule deleted successfully!'
                    });
                    
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    Toast.fire({
                        icon: 'error',
                        title: data.message || 'Failed to delete schedule'
                    });
                }
            })
            .catch(error => {
                console.error('Detailed error:', error);
                Toast.fire({
                    icon: 'error',
                    title: 'Network error: ' + (error.message || 'An error occurred')
                });
            });
        }
    });
}

// SIMPLE EDIT CLASS GROUP - Now uses safe edit function
function editClassGroup(section, academic_year, term, course_code, faculty_id, room, schedule_data) {
    console.log('editClassGroup called with:', {section, academic_year, term, course_code, faculty_id, room, schedule_data});
    
    // Parse first schedule from schedule_data
    // schedule_data format: "Monday|08:00 AM - 10:00 AM|57||Tuesday|01:00 PM - 03:00 PM|58"
    
    const schedules = schedule_data.split('||');
    if (schedules.length > 0) {
        const firstSchedule = schedules[0].split('|');
        if (firstSchedule.length >= 3) {
            const day = firstSchedule[0];
            const time = firstSchedule[1];
            const class_id = firstSchedule[2];
            
            // Call the safe edit function
            editClassFromReport(class_id, section, academic_year, term, course_code, day, time, room, faculty_id);
            return;
        }
    }
    
    // Fallback if parsing fails
    Toast.fire({
        icon: 'warning',
        title: 'Unable to load class data for editing'
    });
}

// ANALYTICS FUNCTIONS
function loadAnalyticsData() {
    fetch('ajax/get_analytics_data.php', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateAnalyticsCards(data.analytics);
        }
    })
    .catch(error => {
        console.error('Analytics loading error:', error);
    });
}

function updateAnalyticsCards(analytics) {
    // Update analytics cards with real-time data
    if (analytics.faculty_workload) {
        updateCard('faculty-workload', analytics.faculty_workload);
    }
    if (analytics.subject_distribution) {
        updateCard('subject-distribution', analytics.subject_distribution);
    }
}

function updateCard(cardId, data) {
    const card = document.getElementById(cardId);
    if (card) {
        // Update card content based on analytics data
        const numberElement = card.querySelector('.stat-number');
        if (numberElement) {
            numberElement.textContent = data.value || '0';
        }
    }
}

// EXPORT FUNCTIONS
function exportSchedulesToPDF() {
    const section = document.getElementById('export-section')?.value;
    const term = document.getElementById('export-term')?.value;
    
    if (!section || !term) {
        Toast.fire({
            icon: 'warning',
            title: 'Please select section and term for export'
        });
        return;
    }
    
    const exportUrl = `reports/export_schedules.php?format=pdf&section=${section}&term=${term}`;
    window.open(exportUrl, '_blank');
}

function exportSchedulesToExcel() {
    const section = document.getElementById('export-section')?.value;
    const term = document.getElementById('export-term')?.value;
    
    if (!section || !term) {
        Toast.fire({
            icon: 'warning',
            title: 'Please select section and term for export'
        });
        return;
    }
    
    const exportUrl = `reports/export_schedules.php?format=excel&section=${section}&term=${term}`;
    window.open(exportUrl, '_blank');
}

// PRINT FUNCTIONS
function printSchedule(section, course_code) {
    const printUrl = `reports/print_schedule.php?section=${section}&course_code=${course_code}`;
    const printWindow = window.open(printUrl, '_blank');
    
    printWindow.onload = function() {
        printWindow.print();
    };
}

// WAIT FOR DEPENDENCIES AND INITIALIZE
function waitForDependencies() {
    // Check if required dependencies are loaded
    if (typeof window.Toast !== 'undefined' && 
        typeof window.Swal !== 'undefined') {
        
        console.log('Reports.js dependencies loaded');
        
        // Make functions available globally
        window.Reports = {
            generateReport,
            viewClassSchedules,
            confirmDeleteIndividualClass,
            editClassGroup,
            editClassFromReport,
            loadAnalyticsData,
            updateAnalyticsCards,
            exportSchedulesToPDF,
            exportSchedulesToExcel,
            printSchedule,
            createScheduleViewHTML
        };
        
        // Also make individual functions globally available
        window.generateReport = generateReport;
        window.viewClassSchedules = viewClassSchedules;
        window.confirmDeleteIndividualClass = confirmDeleteIndividualClass;
        window.editClassGroup = editClassGroup;
        window.editClassFromReport = editClassFromReport;
        window.exportSchedulesToPDF = exportSchedulesToPDF;
        window.exportSchedulesToExcel = exportSchedulesToExcel;
        window.printSchedule = printSchedule;
        
        console.log('Reports.js functions exported globally');
        
    } else {
        // Retry after a short delay
        setTimeout(waitForDependencies, 100);
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', waitForDependencies);
} else {
    waitForDependencies();
}

console.log('Reports.js loaded');