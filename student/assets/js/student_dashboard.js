console.log('✓ Student Dashboard JS Loaded');

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    console.log('Student Dashboard Initializing...');
    console.log('Student Data:', window.studentData);
    
    // Load grade previews for all enrolled classes
    loadAllGradePreviews();
    
    // Auto-refresh grade previews every 10 seconds to detect when faculty releases grades
    setInterval(function() {
        console.log('Auto-refreshing grade previews...');
        loadAllGradePreviews();
    }, 10000); // Refresh every 10 seconds
});

/**
 * Load grade previews for all enrolled classes
 */
async function loadAllGradePreviews() {
    const classCards = document.querySelectorAll('.class-card');
    
    classCards.forEach(card => {
        const classCode = card.getAttribute('data-class-code');
        if (classCode) {
            loadGradePreview(classCode);
        }
    });
}

/**
 * Load grade preview for a specific class
 */
async function loadGradePreview(classCode) {
    const previewElement = document.getElementById(`grade-preview-${classCode}`);
    const card = document.querySelector(`[data-class-code="${classCode}"]`);
    
    if (!previewElement) {
        console.warn(`Preview element not found for ${classCode}`);
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('action', 'get_student_grade_summary');
        formData.append('class_code', classCode);
        formData.append('student_id', window.studentData.studentId);
        formData.append('csrf_token', window.studentData.csrfToken);
        formData.append('_t', new Date().getTime()); // Cache buster
        
        const response = await fetch('ajax/get_grades.php', {
            method: 'POST',
            body: formData,
            cache: 'no-store' // Prevent caching
        });
        
        const data = await response.json();
        console.log('Grade data received:', data);
        
        if (data.success) {
            renderGradePreview(previewElement, data);
            
            // Store hidden state on card and update button
            if (card) {
                card.setAttribute('data-grades-hidden', data.term_grade_hidden ? 'true' : 'false');
                
                // Update button state
                const viewButton = card.querySelector('.btn-view-grades');
                if (viewButton) {
                    if (data.term_grade_hidden) {
                        viewButton.style.opacity = '0.5';
                        viewButton.style.pointerEvents = 'none';
                        viewButton.title = 'Grades have not been released yet';
                    } else {
                        viewButton.style.opacity = '1';
                        viewButton.style.pointerEvents = 'auto';
                        viewButton.title = 'View your grades';
                    }
                }
            }
        } else {
            previewElement.innerHTML = `
                <div class="grade-preview-content">
                    <div style="color: var(--gray-500); font-size: 0.875rem;">
                        <i class="fas fa-info-circle"></i>
                        ${data.message || 'No grades available yet'}
                    </div>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading grade preview:', error);
        previewElement.innerHTML = `
            <div class="grade-preview-content">
                <div style="color: var(--gray-500); font-size: 0.875rem;">
                    <i class="fas fa-exclamation-circle"></i>
                    Unable to load grades
                </div>
            </div>
        `;
    }
}

/**
 * Render grade preview in card
 */
function renderGradePreview(element, data) {
    console.log('Rendering grades:', {
        midterm_grade: data.midterm_grade,
        finals_grade: data.finals_grade,
        term_grade: data.term_grade,
        grade_status: data.grade_status
    });
    
    // Check if term grade is hidden
    if (data.term_grade_hidden) {
        element.innerHTML = `
            <div class="grade-preview-content" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; text-align: center;">
                <div style="display: flex; flex-direction: column; align-items: center; padding: 1rem; border-radius: 8px; background: #f3f4f6;">
                    <i class="fas fa-lock" style="font-size: 24px; color: #fbbf24; margin-bottom: 0.5rem;"></i>
                    <div style="color: #6b7280; font-size: 12px;">Grades not yet released</div>
                </div>
                <div style="display: flex; flex-direction: column; align-items: center; padding: 1rem; border-radius: 8px; background: #f3f4f6;">
                    <i class="fas fa-lock" style="font-size: 24px; color: #fbbf24; margin-bottom: 0.5rem;"></i>
                    <div style="color: #6b7280; font-size: 12px;">Grades not yet released</div>
                </div>
                <div style="display: flex; flex-direction: column; align-items: center; padding: 1rem; border-radius: 8px; background: #f3f4f6;">
                    <i class="fas fa-lock" style="font-size: 24px; color: #fbbf24; margin-bottom: 0.5rem;"></i>
                    <div style="color: #6b7280; font-size: 12px;">Grades not yet released</div>
                </div>
            </div>
        `;
        return;
    }
    
    // Use grades (4-point scale) for Midterm and Finals
    const midtermGrade = data.midterm_grade || 0;
    const finalsGrade = data.finals_grade || 0;
    const termGrade = data.term_grade || 0;
    const grade_status = data.grade_status || 'pending';
    
    // Determine what to display in term grade box based on status
    let termGradeDisplay = termGrade.toFixed(1);
    let badgeColor = '#D4AF37'; // Default gold
    let badgeText = termGradeDisplay; // Default to numeric grade
    
    // If status is failed, incomplete, or dropped, show that instead of the numeric grade
    if (grade_status === 'failed') {
        badgeText = 'FAILED';
        badgeColor = '#ef4444'; // Red
    } else if (grade_status === 'incomplete') {
        badgeText = 'INC';
        badgeColor = '#f97316'; // Orange
    } else if (grade_status === 'dropped') {
        badgeText = 'DRP';
        badgeColor = '#9ca3af'; // Gray
    } else {
        // Only apply numeric grade coloring if no special status
        if (termGrade >= 1.0) {
            badgeColor = '#10b981'; // Green for passing
        } else if (termGrade > 0) {
            badgeColor = '#ef4444'; // Red for failing
        }
        badgeText = termGradeDisplay;
    }
    
    element.innerHTML = `
        <div class="grade-preview-content" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem; text-align: center;">
            <div class="grade-item" style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 120px;">
                <div style="font-size: 11px; color: #6b7280; font-weight: 600; margin-bottom: 0.75rem; letter-spacing: 0.5px;">MIDTERM</div>
                <div style="font-size: 13px; color: #9ca3af; font-weight: 500; margin-bottom: 0.5rem;">(40%)</div>
                <div class="midterm-percentage" style="font-size: 16px; font-weight: 700; color: #003082; margin-bottom: 0.25rem;">${data.midterm_percentage.toFixed(2)}%</div>
                <div class="midterm-grade" style="font-size: 13px; font-weight: 600; color: #6b7280;">${midtermGrade.toFixed(1)}</div>
            </div>
            <div class="grade-item" style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 120px;">
                <div style="font-size: 11px; color: #6b7280; font-weight: 600; margin-bottom: 0.75rem; letter-spacing: 0.5px;">FINALS</div>
                <div style="font-size: 13px; color: #9ca3af; font-weight: 500; margin-bottom: 0.5rem;">(60%)</div>
                <div class="finals-percentage" style="font-size: 16px; font-weight: 700; color: #003082; margin-bottom: 0.25rem;">${data.finals_percentage.toFixed(2)}%</div>
                <div class="finals-grade" style="font-size: 13px; font-weight: 600; color: #6b7280;">${finalsGrade.toFixed(1)}</div>
            </div>
            <div class="grade-item" style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 120px;">
                <div style="font-size: 11px; color: #6b7280; font-weight: 600; margin-bottom: 0.75rem; letter-spacing: 0.5px;">TERM GRADE</div>
                <div class="term-grade-status" style="font-size: 13px; color: ${badgeColor}; font-weight: 700; margin-bottom: 0.5rem;">${badgeText}</div>
                <div class="term-grade-percentage" style="font-size: 16px; font-weight: 700; color: #003082; margin-bottom: 0.25rem;">${data.term_percentage.toFixed(2)}%</div>
                <div class="term-grade-value" style="font-size: 13px; font-weight: 600; color: #6b7280;">${termGrade.toFixed(1)}</div>
            </div>
        </div>
    `;
}

/**
 * Get status information (label, icon, color, class)
 */
function getStatusInfo(status) {
    const statusMap = {
        'passed': {
            label: 'PASSED',
            icon: '✓',
            color: '#10b981',
            class: 'status-passed'
        },
        'failed': {
            label: 'FAILED',
            icon: '✗',
            color: '#ef4444',
            class: 'status-failed'
        },
        'incomplete': {
            label: 'INC',
            icon: '⏳',
            color: '#f59e0b',
            class: 'status-incomplete'
        },
        'dropped': {
            label: 'DRP',
            icon: '⊗',
            color: '#8b5cf6',
            class: 'status-dropped'
        },
        'pending': {
            label: 'PENDING',
            icon: '⋯',
            color: '#6b7280',
            class: 'status-pending'
        }
    };
    
    return statusMap[status] || statusMap['pending'];
}

/**
 * Get color based on grade value
 */
function getGradeColor(grade) {
    const g = parseFloat(grade);
    // Passed (1.0 or higher) = Green
    if (g >= 1.0) return '#10b981';
    // Incomplete/INC (0.0 to 0.99) = Yellow
    if (g > 0.0) return '#f59e0b';
    // Failed (0.0) = Red
    return '#ef4444';
}

/**
 * View detailed grades for a class
 */
async function viewClassGrades(classCode, courseTitle) {
    // Check if grades are hidden
    const card = document.querySelector(`[data-class-code="${classCode}"]`);
    if (card && card.getAttribute('data-grades-hidden') === 'true') {
        // Show alert instead of opening modal
        alert('Grades have not been released yet by your instructor. Please try again later.');
        return;
    }
    
    const modal = document.getElementById('gradeModal');
    const modalTitle = document.getElementById('modalClassTitle');
    const modalContent = document.getElementById('modalGradeContent');
    
    // Update modal title
    modalTitle.textContent = courseTitle;
    
    // Show modal
    modal.classList.add('active');
    
    // Show loading
    modalContent.innerHTML = `
        <div style="text-align: center; padding: 3rem;">
            <i class="fas fa-spinner fa-spin" style="font-size: 3rem; color: var(--nu-blue);"></i>
            <p style="margin-top: 1rem; color: var(--gray-600);">Loading detailed grades...</p>
        </div>
    `;
    
    try {
        const formData = new FormData();
        formData.append('action', 'get_student_detailed_grades');
        formData.append('class_code', classCode);
        formData.append('student_id', window.studentData.studentId);
        formData.append('csrf_token', window.studentData.csrfToken);
        
        const response = await fetch('ajax/get_grades.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            renderDetailedGrades(modalContent, data);
        } else {
            modalContent.innerHTML = `
                <div style="text-align: center; padding: 3rem;">
                    <i class="fas fa-info-circle" style="font-size: 3rem; color: var(--gray-400);"></i>
                    <h3 style="margin-top: 1rem; color: var(--gray-800);">No Grades Available</h3>
                    <p style="color: var(--gray-600);">${data.message || 'Grades have not been posted yet'}</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading detailed grades:', error);
        modalContent.innerHTML = `
            <div style="text-align: center; padding: 3rem;">
                <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: var(--danger);"></i>
                <h3 style="margin-top: 1rem; color: var(--gray-800);">Error Loading Grades</h3>
                <p style="color: var(--gray-600);">Unable to load grades. Please try again later.</p>
            </div>
        `;
    }
}

/**
 * Render detailed grades in modal
 */
function renderDetailedGrades(container, data) {
    const { midterm, finals, term_grade, grade_status, term_grade_hidden, midterm_weight, finals_weight } = data;
    
    // If term grade is hidden, show message
    if (term_grade_hidden) {
        container.innerHTML = `
            <div class="grade-detail-container">
                <div class="hidden-grades-message" style="padding: 40px 20px; text-align: center;">
                    <i class="fas fa-lock" style="font-size: 48px; color: #fbbf24; margin-bottom: 20px; display: block;"></i>
                    <h3 style="color: #374151; font-size: 18px; margin: 20px 0;">Grades Not Yet Released</h3>
                    <p style="color: #6b7280; margin: 10px 0;">Your instructor has not yet released your grades for this class.</p>
                    <p style="color: #9ca3af; font-size: 14px; margin-top: 20px;">Check back later or contact your instructor for more information.</p>
                </div>
            </div>
        `;
        return;
    }
    
    let html = `
        <div class="grade-detail-container">
            <!-- Tab Navigation -->
            <div class="grade-tabs">
                <button class="grade-tab active" onclick="switchGradeTab(event, 'midterm-tab')">
                    <i class="fas fa-bookmark"></i>
                    MIDTERM (${midterm_weight}%)
                </button>
                <button class="grade-tab" onclick="switchGradeTab(event, 'finals-tab')">
                    <i class="fas fa-trophy"></i>
                    FINALS (${finals_weight}%)
                </button>
                <button class="grade-tab" onclick="switchGradeTab(event, 'summary-tab')">
                    <i class="fas fa-chart-pie"></i>
                    SUMMARY
                </button>
            </div>

            <!-- Midterm Tab -->
            <div id="midterm-tab" class="grade-tab-content active">
                <div class="term-header">
                    <div class="term-title">
                        <i class="fas fa-bookmark"></i>
                        <span>Midterm Assessment</span>
                    </div>
                    <div class="term-grade-display" style="background: ${getGradeColor(midterm.grade)};">
                        ${midterm.grade.toFixed(1)}
                    </div>
                </div>
                ${renderTermContent(midterm.components, midterm.percentage)}
            </div>

            <!-- Finals Tab -->
            <div id="finals-tab" class="grade-tab-content">
                <div class="term-header">
                    <div class="term-title">
                        <i class="fas fa-trophy"></i>
                        <span>Finals Assessment</span>
                    </div>
                    <div class="term-grade-display" style="background: ${getGradeColor(finals.grade)};">
                        ${finals.grade.toFixed(1)}
                    </div>
                </div>
                ${renderTermContent(finals.components, finals.percentage)}
            </div>

            <!-- Summary Tab -->
            <div id="summary-tab" class="grade-tab-content">
                <div class="summary-section">
                    <div class="final-grade-box">
                        <div class="final-grade-header">
                            <h3>Term Grade Calculation</h3>
                        </div>
                        <div class="calculation-formula">
                            <div class="calc-item">
                                <span class="calc-label">Midterm Grade</span>
                                <span class="calc-value">${midterm.grade.toFixed(2)}</span>
                                <span class="calc-weight">× ${midterm_weight}%</span>
                            </div>
                            <div class="calc-operator">+</div>
                            <div class="calc-item">
                                <span class="calc-label">Finals Grade</span>
                                <span class="calc-value">${finals.grade.toFixed(2)}</span>
                                <span class="calc-weight">× ${finals_weight}%</span>
                            </div>
                        </div>
                        <div class="final-result">
                            <div class="result-label">Final Term Grade</div>
                            <div class="result-value" style="background: ${grade_status === 'failed' || grade_status === 'incomplete' || grade_status === 'dropped' ? getStatusInfo(grade_status).color : getGradeColor(term_grade)};">
                                ${grade_status === 'failed' || grade_status === 'incomplete' || grade_status === 'dropped' ? (grade_status === 'failed' ? 'FAILED' : grade_status === 'incomplete' ? 'INC' : 'DRP') : term_grade.toFixed(2)}
                            </div>
                        </div>
                        <div class="grade-status ${getStatusClass(grade_status)}">
                            ${getStatusLabel(grade_status)}
                        </div>
                    </div>

                    <div class="component-weights-info">
                        <h4>Grade Components Summary</h4>
                        <div class="weight-breakdown">
                            <div class="weight-item">
                                <span class="weight-label">Midterm:</span>
                                <div class="weight-bar">
                                    <div class="weight-fill" style="width: ${midterm_weight}%; background: #3b82f6;"></div>
                                </div>
                                <span class="weight-percent">${midterm_weight}%</span>
                            </div>
                            <div class="weight-item">
                                <span class="weight-label">Finals:</span>
                                <div class="weight-bar">
                                    <div class="weight-fill" style="width: ${finals_weight}%; background: #10b981;"></div>
                                </div>
                                <span class="weight-percent">${finals_weight}%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    container.innerHTML = html;
}

/**
 * Render term content (components breakdown)
 */
function renderTermContent(components, termPercentage) {
    if (!components || components.length === 0) {
        return `
            <div class="no-components">
                <i class="fas fa-inbox"></i>
                <p>No grading components set up yet</p>
            </div>
        `;
    }
    
    let html = '<div class="components-list">';
    
    components.forEach(comp => {
        const compColor = getComponentColor(comp.component_name);
        const itemsHtml = renderComponentItems(comp.items);
        
        html += `
            <div class="component-section">
                <div class="component-header" style="background: ${compColor};">
                    <div class="component-info">
                        <div class="component-name">${comp.component_name}</div>
                        <div class="component-weight">${comp.percentage}% of term</div>
                    </div>
                    <div class="component-score">
                        <div class="component-percentage">${comp.average_percentage.toFixed(1)}%</div>
                        <div class="component-grade" style="background: ${getGradeColor(comp.grade)};">
                            ${comp.grade.toFixed(1)}
                        </div>
                    </div>
                </div>
                <div class="component-items">
                    ${itemsHtml}
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    return html;
}

/**
 * Render individual component items
 */
function renderComponentItems(items, componentName) {
    if (!items || items.length === 0) {
        return '<div class="no-items"><i class="fas fa-clipboard"></i> No items yet</div>';
    }
    
    // Safety check for component name
    const compName = componentName ? String(componentName).toLowerCase() : '';
    
    // Special handling for Attendance - just show total
    if (compName.includes('attendance')) {
        let totalScore = 0;
        let totalMax = 0;
        
        items.forEach(item => {
            if (item.score !== null) {
                totalScore += parseFloat(item.score);
                totalMax += parseFloat(item.max_score);
            }
        });
        
        const percentage = totalMax > 0 ? ((totalScore / totalMax) * 100) : 0;
        
        let html = '<div class="items-table">';
        html += `
            <table>
                <thead>
                    <tr>
                        <th>Attendance Summary</th>
                        <th>Score</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="item-name">
                            <i class="fas fa-calendar-check"></i>
                            Total Attendance
                        </td>
                        <td class="item-score">
                            <span class="score-value">${totalScore.toFixed(0)} / ${totalMax.toFixed(0)}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        `;
        return html;
    }
    
    // Regular component items
    let html = '<div class="items-table">';
    html += `
        <table>
            <thead>
                <tr>
                    <th>Assessment Item</th>
                    <th>Score</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    items.forEach(item => {
        const score = item.score !== null ? parseFloat(item.score) : null;
        const maxScore = parseFloat(item.max_score);
        
        let scoreDisplay = '';
        
        if (score !== null) {
            scoreDisplay = `<span class="score-value">${score.toFixed(1)} / ${maxScore.toFixed(1)}</span>`;
        } else {
            scoreDisplay = '<span class="no-score">-</span>';
        }
        
        html += `
            <tr>
                <td class="item-name">
                    <i class="fas fa-file-alt"></i>
                    ${item.column_name}
                </td>
                <td class="item-score">
                    ${scoreDisplay}
                </td>
            </tr>
        `;
    });
    
    html += `
            </tbody>
        </table>
    </div>
    `;
    
    return html;
}

/**
 * Get component color
 */
function getComponentColor(name) {
    const colors = [
        'linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)',
        'linear-gradient(135deg, #10b981 0%, #059669 100%)',
        'linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%)',
        'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
        'linear-gradient(135deg, #06b6d4 0%, #0891b2 100%)',
        'linear-gradient(135deg, #ec4899 0%, #db2777 100%)'
    ];
    
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    
    return colors[Math.abs(hash) % colors.length];
}

/**
 * Get grade status text
 */
function getGradeStatus(grade) {
    if (grade >= 1.0) {
        return '<i class="fas fa-check-circle"></i> PASSED';
    } else if (grade > 0) {
        return '<i class="fas fa-clock"></i> PENDING';
    } else {
        return '<i class="fas fa-times-circle"></i> FAILED';
    }
}

/**
 * Get CSS class for grade status
 */
function getStatusClass(status) {
    switch(status) {
        case 'passed':
            return 'passed';
        case 'failed':
            return 'failed';
        case 'incomplete':
        case 'INC':
            return 'pending';
        case 'dropped':
        case 'DRP':
            return 'dropped';
        default:
            return 'pending';
    }
}

/**
 * Get status label text
 */
function getStatusLabel(status) {
    switch(status) {
        case 'passed':
            return '<i class="fas fa-check-circle"></i> PASSED';
        case 'failed':
            return '<i class="fas fa-times-circle"></i> FAILED';
        case 'incomplete':
        case 'INC':
            return '<i class="fas fa-hourglass-half"></i> INCOMPLETE';
        case 'dropped':
        case 'DRP':
            return '<i class="fas fa-ban"></i> DROPPED';
        default:
            return '<i class="fas fa-hourglass"></i> PENDING';
    }
}

/**
 * Switch between grade tabs
 */
function switchGradeTab(event, tabId) {
    event.preventDefault();
    
    // Hide all tabs
    const allTabs = document.querySelectorAll('.grade-tab-content');
    allTabs.forEach(tab => tab.classList.remove('active'));
    
    // Remove active class from all buttons
    const allButtons = document.querySelectorAll('.grade-tab');
    allButtons.forEach(btn => btn.classList.remove('active'));
    
    // Show selected tab
    const selectedTab = document.getElementById(tabId);
    if (selectedTab) {
        selectedTab.classList.add('active');
    }
    
    // Add active class to clicked button
    event.target.closest('.grade-tab').classList.add('active');
}

/**
 * Close grade modal
 */
function closeGradeModal() {
    const modal = document.getElementById('gradeModal');
    modal.classList.remove('active');
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    const modal = document.getElementById('gradeModal');
    if (e.target === modal) {
        closeGradeModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeGradeModal();
    }
});

// Export functions
window.viewClassGrades = viewClassGrades;
window.closeGradeModal = closeGradeModal;
window.switchGradeTab = switchGradeTab;

/**
 * Open Pending Activities Modal
 */
async function openPendingActivitiesModal() {
    const modal = document.getElementById('pendingActivitiesModal');
    const contentEl = document.getElementById('pendingActivitiesContent');
    
    modal.classList.add('active');
    
    try {
        const response = await fetch('ajax/get_pending_activities.php?_t=' + new Date().getTime(), {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success && data.activities && data.activities.length > 0) {
            let html = `
                <div style="padding: 0;">
                    <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 1rem; margin-bottom: 1.5rem; border-radius: 6px;">
                        <div style="display: flex; align-items: center; gap: 0.75rem; color: #92400e;">
                            <i class="fas fa-exclamation-circle" style="font-size: 18px;"></i>
                            <span style="font-weight: 600;">You have ${data.count} incomplete grade(s)</span>
                        </div>
                    </div>
                    <div style="display: grid; gap: 1rem;">
            `;
            
            data.activities.forEach(activity => {
                const termDisplay = activity.term.charAt(0).toUpperCase() + activity.term.slice(1);
                const termColor = activity.term === 'midterm' ? '#3b82f6' : '#10b981';
                
                // Build INC components list
                let incComponentsHtml = '';
                if (activity.inc_components && activity.inc_components.length > 0) {
                    incComponentsHtml = `
                        <div style="background: #fee2e2; padding: 0.75rem; border-radius: 6px; margin-bottom: 0.75rem; border-left: 3px solid #dc2626;">
                            <div style="font-weight: 600; color: #991b1b; margin-bottom: 0.5rem;">Incomplete Components:</div>
                            <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                    `;
                    
                    activity.inc_components.forEach(comp => {
                        incComponentsHtml += `
                            <span style="background: #fecaca; color: #7f1d1d; padding: 0.4rem 0.8rem; border-radius: 4px; font-size: 12px; font-weight: 600;">
                                ${comp.column_name}
                            </span>
                        `;
                    });
                    
                    incComponentsHtml += `
                            </div>
                        </div>
                    `;
                }
                
                html += `
                    <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem; transition: all 0.3s;">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.75rem;">
                            <div style="flex: 1;">
                                <div style="font-weight: 700; color: #003082; margin-bottom: 0.25rem;">${activity.course_code}</div>
                                <div style="font-size: 13px; color: #6b7280; margin-bottom: 0.5rem;">${activity.course_title}</div>
                            </div>
                            <div style="background: ${termColor}20; color: ${termColor}; padding: 0.4rem 0.8rem; border-radius: 6px; font-size: 12px; font-weight: 600;">
                                ${termDisplay}
                            </div>
                        </div>
                        
                        ${incComponentsHtml}
                    </div>
                `;
            });
            
            html += `
                    </div>
                </div>
            `;
            
            contentEl.innerHTML = html;
        } else {
            contentEl.innerHTML = `
                <div style="text-align: center; padding: 2rem; color: #6b7280;">
                    <div style="font-size: 48px; margin-bottom: 1rem; color: #d1d5db;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3 style="font-size: 18px; font-weight: 600; color: #374151; margin-bottom: 0.5rem;">No Pending Activities</h3>
                    <p>All your grades have been recorded!</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading pending activities:', error);
        console.error('Error details:', {
            name: error.name,
            message: error.message,
            stack: error.stack
        });
        contentEl.innerHTML = `
            <div style="text-align: center; padding: 2rem; color: #dc2626;">
                <i class="fas fa-exclamation-triangle" style="font-size: 24px; margin-bottom: 1rem;"></i>
                <p>Failed to load pending activities</p>
                <p style="font-size: 12px; margin-top: 0.5rem; color: #9ca3af;">${error.message}</p>
            </div>
        `;
    }
}

/**
 * Close Pending Activities Modal
 */
function closePendingActivitiesModal() {
    const modal = document.getElementById('pendingActivitiesModal');
    modal.classList.remove('active');
}

// Close pending modal when clicking outside
document.addEventListener('DOMContentLoaded', function() {
    const pendingModal = document.getElementById('pendingActivitiesModal');
    if (pendingModal) {
        window.addEventListener('click', function(e) {
            if (e.target === pendingModal) {
                closePendingActivitiesModal();
            }
        });
    }
});

// Export pending activities functions to global scope
window.openPendingActivitiesModal = openPendingActivitiesModal;
window.closePendingActivitiesModal = closePendingActivitiesModal;
