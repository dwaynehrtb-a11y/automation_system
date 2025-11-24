console.log('✓ Flexible Grading System - FULLY FUNCTIONAL v2.0 POLISHED');

// Initialize APP object with fallbacks
window.APP = window.APP || {
    csrfToken: window.csrfToken || '',
    apiPath: window.location.hostname.includes('hostinger') ? '/faculty/ajax/' : '/automation_system/faculty/ajax/'
};

const FGS = {
    currentClassCode: null,
    currentTermType: 'midterm',
    students: [],
    components: [],
    allComponents: [],
    currentComponentId: null,
    grades: {},
    gradeStatuses: {},  // Store grade statuses for midterm tab display
    editMode: false,
    midtermWeight: 40.0,
    finalsWeight: 60.0,
    termLimit: 40.0,
    usedPercentage: 0,
    remainingPercentage: 40.0
};

// ---- Missing helper implementations (added to resolve ReferenceError) ----
async function loadWeights(classCode) {
    const fd = new FormData();
    fd.append('action','get_term_weights');
    fd.append('class_code', classCode);
    fd.append('csrf_token', window.csrfToken || (APP && APP.csrfToken) || '');
    try {
        const res = await fetch((APP && APP.apiPath ? APP.apiPath : '/faculty/ajax/') + 'manage_grading_components.php', { method:'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            FGS.midtermWeight = parseFloat(data.midterm_weight);
            FGS.finalsWeight = parseFloat(data.finals_weight);
            // Set current term limit based on selected term
            FGS.termLimit = (FGS.currentTermType === 'midterm') ? FGS.midtermWeight : FGS.finalsWeight;
        } else {
            console.warn('loadWeights failed:', data.message);
        }
    } catch (e) {
        console.error('loadWeights error', e);
    }
}

async function loadStudents(classCode) {
    // Prefer existing integration function if available
    if (typeof loadFlexibleStudents === 'function') {
        try {
            await loadFlexibleStudents(classCode);
            // Ensure FGS.students is populated
            if (!FGS.students || FGS.students.length === 0) {
                console.warn('loadFlexibleStudents did not populate FGS.students, falling back...');
            } else {
                return;
            }
        } catch (err) {
            console.warn('loadFlexibleStudents failed, falling back:', err);
        }
    }
    const fd = new FormData();
    fd.append('action','get_students');
    fd.append('class_code', classCode);
    fd.append('csrf_token', window.csrfToken || (APP && APP.csrfToken) || '');
    try {
        const res = await fetch((APP && APP.apiPath ? APP.apiPath : '/faculty/ajax/') + 'process_grades.php', { method:'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            FGS.students = data.students || [];
        } else {
            console.warn('loadStudents failed:', data.message);
        }
    } catch (e) { console.error('loadStudents error', e); }
}

async function loadComponents(classCode, termType) {
    const fd = new FormData();
    fd.append('action','get_components');
    fd.append('class_code', classCode);
    fd.append('term_type', termType);
    fd.append('csrf_token', window.csrfToken || (APP && APP.csrfToken) || '');
    try {
        const res = await fetch((APP && APP.apiPath ? APP.apiPath : '/faculty/ajax/') + 'manage_grading_components.php', { method:'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            FGS.components = data.components || [];
            FGS.allComponents = data.all_components || [];
            console.log('✓ loadComponents:', { componentsCount: FGS.components.length, firstCompColumns: FGS.components[0]?.columns?.length || 0, rawFirstComp: FGS.components[0] });
            // Auto-select first component if none selected
            if (!FGS.currentComponentId && FGS.components.length > 0) {
                FGS.currentComponentId = FGS.components[0].id;
            }
            updateLimit();
        } else {
            console.warn('loadComponents failed:', data.message);
        }
    } catch (e) { console.error('loadComponents error', e); }
}

async function loadGrades(classCode, componentId) {
    if (!componentId) return;
    console.log('🔵 loadGrades called - componentId:', componentId);
    const fd = new FormData();
    fd.append('action','get_grades');
    fd.append('component_id', componentId);
    fd.append('csrf_token', window.csrfToken || (APP && APP.csrfToken) || '');
    try {
        const res = await fetch((APP && APP.apiPath ? APP.apiPath : '/faculty/ajax/') + 'process_grades.php', { method:'POST', body: fd });
        const data = await res.json();
        console.log('🔵 loadGrades response:', data);
        if (data.success) {
            FGS.grades = data.grades || {};
            console.log('🔵 FGS.grades updated:', Object.keys(FGS.grades).length, 'grades loaded');
            // Log ALL grades for inspection
            const keys = Object.keys(FGS.grades);
            console.log(`🔵 ====== COMPLETE GRADES DUMP (${keys.length} total) ======`);
            keys.forEach((k, idx) => {
                const score = FGS.grades[k].score;
                const colId = k.split('_')[1];
                const comp = FGS.components.find(c => {
                    const col = c.columns?.find(col => col.id == colId);
                    return col ? c : null;
                });
                const colName = comp?.columns?.find(col => col.id == colId)?.column_name || 'UNKNOWN';
                const maxScore = comp?.columns?.find(col => col.id == colId)?.max_score || '?';
                console.log(`  [${idx}] ${k} = ${score} | ${colName} /${maxScore}`);
            });
            console.log('🔵 ====== END DUMP ======');
        } else {
            console.warn('loadGrades failed:', data.message);
        }
    } catch (e) { console.error('loadGrades error', e); }
}

function updateLimit() {
    FGS.termLimit = (FGS.currentTermType === 'midterm') ? FGS.midtermWeight : FGS.finalsWeight;
    let used = 0;
    FGS.components.forEach(c => { used += parseFloat(c.percentage) || 0; });
    FGS.usedPercentage = used;
    FGS.remainingPercentage = Math.max(0, FGS.termLimit - used);
    if (typeof renderProgressBar === 'function') renderProgressBar();
}

// Expose for other modules if needed
window.loadWeights = loadWeights;
window.loadStudents = loadStudents;
window.loadComponents = loadComponents;
window.loadGrades = loadGrades;
window.updateLimit = updateLimit;

function renderUI() {
    // Progress bar (term allocation)
    if (typeof renderProgressBar === 'function') renderProgressBar();
    // Component list container per term
    const listContainer = (FGS.currentTermType === 'midterm') ? document.getElementById('midterm-components') : document.getElementById('finals-components');
    if (listContainer) {
        if (!FGS.components || FGS.components.length === 0) {
            listContainer.innerHTML = '<div class="fgs-empty-state"><p style="margin:8px 0;color:#555">No components yet</p></div>';
        } else {
            let html = '<div style="display:flex; flex-wrap:wrap; gap:16px; justify-content:center; align-items:flex-start; width:100%; padding:12px 8px;">';
            
            // Add MIDTERM SUMMARY card at the beginning (only for midterm tab)
            if (FGS.currentTermType === 'midterm') {
                const summaryActive = FGS.currentComponentId === null;
                html += `<div class="fgs-component-card${summaryActive?' active':''}" onclick="switchToMidtermSummary()">\n                    <div style="font-weight:700; font-size:14px; margin-bottom:4px; display:flex; align-items:center; gap:6px;">\n                        <i class="fas fa-chart-line"></i> Midterm Summary\n                    </div>\n                    <div style="font-size:11px; opacity:.85; margin-bottom:0;">Class overview & status</div>\n                </div>`;
            }
            
            FGS.components.forEach(c => {
                const active = c.id === FGS.currentComponentId;
                html += `<div class="fgs-component-card${active?' active':''}" onclick="switchComponent(${c.id})">`+
                        `<div style=\"font-weight:700;font-size:14px;margin-bottom:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;padding-right:70px;\">${c.component_name}</div>`+
                        `<div style=\"font-size:11px;opacity:.85;margin-bottom:0;\">${c.percentage}%${c.columns&&c.columns.length?` • ${c.columns.length} item${c.columns.length>1?'s':''}`:''}</div>`;
                        if (FGS.editMode) {
                            html += `<div style="position:absolute;top:10px;right:8px;display:flex;gap:3px;flex-wrap:nowrap;">` +
                                    `<button class="fgs-btn-small" title="Edit Component" onclick="event.stopPropagation(); editComponent(${c.id}, '${c.component_name.replace(/'/g, "\\'")}', ${c.percentage})" style="width:24px;height:24px;padding:3px;background:#3b82f6;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:11px;display:flex;align-items:center;justify-content:center;transition:all 0.2s ease;flex-shrink:0;" onmouseover="this.style.background='#2563eb';this.style.boxShadow='0 2px 6px rgba(59,130,246,0.3)'" onmouseout="this.style.background='#3b82f6';this.style.boxShadow='none'"><i class="fas fa-edit"></i></button>` +
                                    `<button class="fgs-btn-small" title="Bulk Add Items" onclick="event.stopPropagation(); bulkAddColumnModal(${c.id}, '${c.component_name}')" style="width:24px;height:24px;padding:3px;background:#10b981;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:11px;display:flex;align-items:center;justify-content:center;transition:all 0.2s ease;flex-shrink:0;" onmouseover="this.style.background='#059669';this.style.boxShadow='0 2px 6px rgba(16,185,129,0.3)'" onmouseout="this.style.background='#10b981';this.style.boxShadow='none'"><i class="fas fa-plus"></i></button>` +
                                    `<button class="fgs-btn-small" title="Delete Component" onclick="event.stopPropagation(); delComponent(${c.id}, '${c.component_name}')" style="width:24px;height:24px;padding:3px;background:#ef4444;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:11px;display:flex;align-items:center;justify-content:center;transition:all 0.2s ease;flex-shrink:0;" onmouseover="this.style.background='#dc2626';this.style.boxShadow='0 2px 6px rgba(239,68,68,0.3)'" onmouseout="this.style.background='#ef4444';this.style.boxShadow='none'"><i class="fas fa-trash"></i></button>` +
                                    `</div>`;
                        }
                        html += `</div>`;
            });
            html += '</div>';
            listContainer.innerHTML = html;
        }
    }
    // Render table for selected component (shows grading entry grid)
    if (typeof renderTable === 'function') renderTable();
}

window.renderUI = renderUI;

// Inverted discrete ladder (server authoritative bands)
// 60–65.99 => 1.0 | 66–71.99 => 1.5 | 72–77.99 => 2.0 | 78–83.99 => 2.5 | 84–89.99 => 3.0 | 90–95.99 => 3.5 | 96–100 => 4.0
function toGrade(pct) {
    const p = parseFloat(pct);
    if (isNaN(p) || p < 60) return 0.0; // failed band (no discrete)
    if (p < 66) return 1.0;
    if (p < 72) return 1.5;
    if (p < 78) return 2.0;
    if (p < 84) return 2.5;
    if (p < 90) return 3.0;
    if (p < 96) return 3.5;
    return 4.0;
}

function gradeColor(grade) {
    const g = parseFloat(grade);
    // Passed (1.0 or higher) = Green
    if (g >= 1.0) return '#10b981';
    // Incomplete/INC (0.0 to 0.99) = Yellow
    if (g > 0.0) return '#f59e0b';
    // Failed (0.0) = Red
    return '#ef4444';
}

// Professional blue color palette
function componentColor(name) {
    return '#003082';  // Always NU Blue
}

async function initGrading(classCode) {
    console.log('Init grading:', classCode);
    FGS.currentClassCode = classCode;
    if (typeof showLoading === 'function') showLoading();
    try {
        await loadWeights(classCode);
        await loadStudents(classCode);
        await loadGradeStatuses();  // Load grade statuses for midterm tab
        const countEl = document.getElementById('flex-student-count');
        if (countEl && FGS.students) {
            countEl.innerHTML = `<i class="fas fa-user-graduate"></i> ${FGS.students.length} student${FGS.students.length !== 1 ? 's' : ''}`;
        }
        await loadComponents(classCode, 'midterm');
        // Load grades for first component if available
        if (FGS.components.length > 0) {
            await loadGrades(classCode, FGS.currentComponentId);
        }
        if (typeof renderUI === 'function') renderUI();
        console.log('✓ Grading initialized successfully');
    } catch (error) {
        console.error('Init grading failed:', error);
        if (typeof hideLoading === 'function') hideLoading();
    }
}

// Summary renderer (server authoritative, normalized statuses)
async function renderSummary() {
    const container = document.getElementById('summary-grades-container');
    if (!container) return;
    if (!FGS.currentClassCode) {
        container.innerHTML = '<div class="fgs-empty-state"><h3 class="fgs-empty-title">Select a Class</h3></div>';
        return;
    }
    container.innerHTML = '<div class="fgs-loading-container"><i class="fas fa-spinner fa-spin fgs-loading-icon"></i><p class="fgs-loading-text">Loading computed grades...</p></div>';
    try {
        const fd = new FormData();
        fd.append('class_code', FGS.currentClassCode);
        fd.append('csrf_token', window.csrfToken || (APP && APP.csrfToken) || '');
        const res = await fetch((APP && APP.apiPath ? APP.apiPath : '/faculty/ajax/') + 'compute_term_grades.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Compute failed');
        const rowsByStudent = {};
        data.rows.forEach(r => { rowsByStudent[r.student_id] = r; });
        const statusColors = { passed: '#10b981', failed: '#ef4444', incomplete: '#f59e0b', dropped: '#6b7280' };
        const statusLabels = { passed: 'Passed', failed: 'Failed', incomplete: 'INC', dropped: 'DRP' };
        let html = `<div class="fgs-summary-container"><div class="fgs-summary-header"><h3 class="fgs-summary-title">Grade Summary</h3><p class="fgs-summary-subtitle">Midterm ${data.midterm_weight}% | Finals ${data.finals_weight}%</p></div><div class="fgs-summary-scroll"><table class="fgs-summary-table"><thead><tr><th>Student</th><th>Midterm %</th><th>Finals %</th><th>Term %</th><th>Discrete</th><th>Status</th><th>Freeze</th></tr></thead><tbody>`;
        FGS.students.forEach(stu => {
            const r = rowsByStudent[stu.student_id] || {};
            const mid = r.midterm_percentage ?? '0.00';
            const fin = r.finals_percentage ?? '0.00';
            const term = r.term_percentage ?? '0.00';
            const termPctFloat = parseFloat(term) || 0.0;
            const grade = r.term_grade ?? '--';
            let status = (r.grade_status ? r.grade_status.toLowerCase() : 'incomplete');
            // Safety override front-end view (backend already enforces)
            if (status === 'passed' && termPctFloat < 60.0) {
                status = (termPctFloat < 57.0 ? 'failed' : 'incomplete');
            }
            const frozen = r.manual_frozen ? true : false;
            const freezeBadge = frozen ? `<span class=\"fgs-freeze-badge\" title=\"Manual Freeze Active\"><i class=\"fas fa-snowflake\"></i></span>` : '';
            html += `<tr><td><div class=\"fgs-student-cell\"><div class=\"fgs-student-avatar\">${stu.name.charAt(0)}${freezeBadge}</div><div><div class=\"fgs-student-name\">${stu.name}${frozen?'<span class=\\"fgs-inline-freeze-label\\" aria-label=\\"Frozen\\" title=\\"Frozen\\"><i class=\\"fas fa-lock\\"></i></span>':''}</div><div class=\"fgs-student-id\">${stu.student_id}</div></div></div></td><td class=\"midterm-col\">${mid}%</td><td class=\"finals-col\">${fin}%</td><td class=\"term-col\">${term}%</td><td class=\"grade-col\"><div class=\"fgs-summary-grade-badge\" style=\"background:${gradeColor(parseFloat(grade)||0)};\">${grade}</div></td><td><span class=\"fgs-status-badge\" data-status=\"${status}\" style=\"background:${statusColors[status]||'#6b7280'}\">${statusLabels[status]||status}</span></td><td><button class=\"btn btn-sm\" style=\"background:${frozen?'#6b7280':'var(--nu-navy)'};color:#fff;\" onclick=\"toggleManualFreeze('${stu.student_id}', ${frozen});\">${frozen?'Unfreeze':'Freeze'}</button></td></tr>`;
        });
        html += '</tbody></table></div></div>';
        container.innerHTML = html;
    } catch (e) {
        container.innerHTML = `<div class=\"fgs-error-state\"><i class=\"fas fa-exclamation-circle fgs-error-icon\"></i><h3 class=\"fgs-error-title\">Error</h3><p class=\"fgs-error-message\">${e.message}</p></div>`;
    }
}

async function toggleManualFreeze(studentId, currentlyFrozen) {
    if (!FGS.currentClassCode) return;
    const fd = new FormData();
    fd.append('class_code', FGS.currentClassCode);
    fd.append('student_id', studentId);
    fd.append('freeze', currentlyFrozen ? 'no' : 'yes');
    fd.append('csrf_token', window.csrfToken || APP.csrfToken);
    try {
        const res = await fetch(APP.apiPath + 'toggle_manual_freeze.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.success) {
            if (typeof Toast !== 'undefined') Toast.fire({icon:'error',title:data.message||'Freeze update failed'});
        } else {
            if (typeof Toast !== 'undefined') Toast.fire({icon:'success',title: currentlyFrozen ? 'Unfroze status' : 'Froze status'});
            // Recompute to reflect freeze
            renderSummary();
        }
    } catch (e) {
        console.error(e);
        if (typeof Toast !== 'undefined') Toast.fire({icon:'error',title:'Freeze error'});
    }
}

function adjustColor(color, amount) {
    const clamp = (num) => Math.min(Math.max(num, 0), 255);
    const num = parseInt(color.replace("#", ""), 16);
    const r = clamp((num >> 16) + amount);
    const g = clamp(((num >> 8) & 0x00FF) + amount);
    const b = clamp((num & 0x0000FF) + amount);
    return "#" + (0x1000000 + r * 0x10000 + g * 0x100 + b).toString(16).slice(1);
}

function renderProgressBar() {
    const isMidterm = FGS.currentTermType === 'midterm';
    const containerId = isMidterm ? 'midterm-progress' : 'finals-progress';
    const container = document.getElementById(containerId);
    
    if (!container) return;

    const usedPct = (FGS.usedPercentage / FGS.termLimit) * 100;
    const isOver = FGS.usedPercentage > FGS.termLimit;
    const progressColor = isOver ? '#dc2626' : '#3b82f6';

    container.innerHTML = `
        <div class="fgs-progress-container">
            <div class="fgs-progress-header">
                <div>
                    <div class="fgs-progress-label">Component Allocation</div>
                    <div class="fgs-progress-value">
                        ${FGS.usedPercentage.toFixed(2)}% <span class="fgs-progress-limit">/ ${FGS.termLimit}%</span>
                    </div>
                </div>
                <div style="text-align:right;">
                    <div class="fgs-progress-label">Remaining</div>
                    <div class="fgs-progress-remaining ${isOver ? 'error' : 'success'}">
                        ${FGS.remainingPercentage.toFixed(2)}%
                    </div>
                </div>
            </div>
            <div class="fgs-progress-bar-outer ${isOver ? 'over' : 'normal'}">
                <div class="fgs-progress-bar-inner ${isOver ? 'over' : 'normal'}" style="width:${Math.min(usedPct, 100)}%;">
                    ${usedPct > 10 ? `<span class="fgs-progress-bar-text">${usedPct.toFixed(1)}%</span>` : ''}
                </div>
            </div>
            ${isOver ? `
                <div class="fgs-progress-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Warning: Total exceeds ${FGS.termLimit}% limit!</span>
                </div>
            ` : ''}
        </div>
    `;
}

function renderTable() {
    const isMidterm = FGS.currentTermType === 'midterm';
    const containerId = isMidterm ? 'flexible-table-container' : 'flexible-table-container-finals';
    const container = document.getElementById(containerId);
    
    if (!container) {
        console.warn(`Container ${containerId} not found`);
        return;
    }

    if (!FGS.students || FGS.students.length === 0) {
        container.innerHTML = `
            <div class="fgs-empty-state">
                <i class="fas fa-users fgs-empty-icon"></i>
                <h3 class="fgs-empty-title">No Students</h3>
                <p class="fgs-empty-description">No students enrolled in this class</p>
            </div>
        `;
        return;
    }

    if (!FGS.components || FGS.components.length === 0) {
        container.innerHTML = `
            <div class="fgs-empty-state">
                <i class="fas fa-clipboard-list fgs-empty-icon"></i>
                <h3 class="fgs-empty-title">No Components</h3>
                <p class="fgs-empty-description">Create components (quizzes, assignments, etc.) to start grading</p>
            </div>
        `;
        return;
    }

    if (!FGS.currentComponentId) {
        container.innerHTML = `
            <div class="fgs-select-state">
                <i class="fas fa-hand-pointer fgs-select-icon"></i>
                <h3 class="fgs-select-title">Select a Component</h3>
                <p class="fgs-select-description">Choose a component above to view and manage grades</p>
            </div>
        `;
        return;
    }

    const comp = FGS.components.find(c => c.id === FGS.currentComponentId);
    if (!comp) {
        container.innerHTML = `
            <div class="fgs-error-state">
                <i class="fas fa-exclamation-circle fgs-error-icon"></i>
                <h3 class="fgs-error-title">Component Not Found</h3>
            </div>
        `;
        return;
    }

    // For now, show summary view with instructions to add items
    const columns = comp.columns || [];
    if (columns.length === 0) {
        container.innerHTML = `
            <div class="fgs-empty-state">
                <i class="fas fa-plus-circle fgs-empty-icon"></i>
                <h3 class="fgs-empty-title">${comp.component_name} - No Items</h3>
                <p class="fgs-empty-description">Add grading items (quizzes, activities, etc.) to this component</p>
                <div style="display: flex; gap: 12px; margin-top: 24px; justify-content: center; flex-wrap: wrap;">
                    <button onclick="addColumnModal(${comp.id},'${comp.component_name.replace(/'/g, "\\'")}')" class="fgs-add-component-btn" style="display: inline-flex; align-items: center; gap: 8px;">
                        <i class="fas fa-plus-circle"></i> Add First Item
                    </button>
                    <button onclick="bulkAddColumnModal(${comp.id},'${comp.component_name.replace(/'/g, "\\'")}')" style="display: inline-flex; align-items: center; gap: 8px; background: #10b981; color: white; border: 2px solid #059669; padding: 12px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 15px; transition: all 0.2s ease;" onmouseover="this.style.background='#059669'; this.style.boxShadow='0 4px 12px rgba(16,185,129,0.3)'" onmouseout="this.style.background='#10b981'; this.style.boxShadow='none'">
                        <i class="fas fa-layer-group"></i> Bulk Add Items
                    </button>
                </div>
            </div>
        `;
        return;
    }

    // Items exist, show grading table with professional styling
    let html = `
        <div class="fgs-summary-container">
            <div class="fgs-summary-header" style="display:flex; align-items:center; justify-content:space-between;">
                <div>
                    <h3 class="fgs-summary-title">${comp.component_name}</h3>
                    <p class="fgs-summary-subtitle">${comp.percentage}% weight • ${columns.length} item${columns.length === 1 ? '' : 's'}</p>
                </div>
                ${FGS.editMode ? `<button id="bulk-delete-items-btn" class="btn btn-danger btn-sm" style="display:none; background:#dc2626; color:#fff; padding:8px 14px; border-radius:6px; border:none; cursor:pointer; font-weight:600; font-size:14px;" onclick="bulkDeleteColumns()">
                    <i class="fas fa-trash"></i> Delete Selected
                </button>` : ''}
            </div>
            <div class="fgs-summary-scroll">
                <table class="fgs-summary-table">
                    <thead>
                        <tr>
                            <th class="student-col" style="position:sticky; left:0; z-index:10; background:#f0f4f8;">
                                ${FGS.editMode ? `<div style="display:flex; align-items:center; gap:8px;"><input type="checkbox" id="bulk-select-all-cols" style="cursor:pointer; width:18px; height:18px;" onchange="toggleSelectAllColumns(this)"><span style="font-weight:700;">Student</span></div>` : `<span style="font-weight:700;">Student</span>`}
                            </th>`;
    
    columns.forEach(col => {
        let headerHtml = `<div style="display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px; min-height:80px;">`;
        if (FGS.editMode) {
            headerHtml += `<div style="display:flex; gap:4px; width:100%; justify-content:center;"><input type="checkbox" class="fgs-col-checkbox" data-col-id="${col.id}" style="cursor:pointer; width:16px; height:16px;" onchange="updateBulkDeleteBtn()"><button class="fgs-col-edit-btn" title="Edit Item" onclick="event.stopPropagation(); editColumn(${col.id}, '${col.column_name.replace(/'/g, "\\'")}', '${col.max_score}')" style="width:24px; height:24px; padding:3px; background:#3b82f6; color:#fff; border:none; border-radius:4px; cursor:pointer; font-size:11px;"><i class="fas fa-edit"></i></button><button class="fgs-col-delete-btn" title="Delete Item" onclick="event.stopPropagation(); delColumn(${col.id}, '${col.column_name.replace(/'/g, "\\'")}' )" style="width:24px; height:24px; padding:3px; background:#ef4444; color:#fff; border:none; border-radius:4px; cursor:pointer; font-size:11px;"><i class="fas fa-trash"></i></button></div>`;
        }
        headerHtml += `<div style="text-align:center;"><span style="font-size:13px; font-weight:700; letter-spacing:.5px; display:block;">${col.column_name}</span><small style="font-size:11px; color:#666; font-weight:500;">/${col.max_score}</small></div>`;
        headerHtml += `</div>`;
        html += `<th style="text-align:center; background:#e8eef9; color:#0b3b85; padding:8px 4px; vertical-align:middle;">${headerHtml}</th>`;
    });
    
    html += `<th style="text-align:center; background:#fff5d8; color:#7a4e00; padding:8px 4px;"><div style="font-size:13px; font-weight:700; letter-spacing:.5px;">TOTAL %</div></th></tr></thead><tbody>`;

    // Helper banner: show a brief note to users about input format
    const helperBanner = `<div style="margin-bottom:8px;padding:10px;border-radius:8px;background:#f1f5f9;color:#0f172a;font-size:13px;border:1px solid #e6eef8;">` +
        `<strong style="margin-right:8px;">Note:</strong> Enter <strong>raw scores only</strong> (e.g. <code>8</code> for a /10 item). Do <em>not</em> include a percent sign (%). The <strong>TOTAL %</strong> column shows the computed percentage.</div>`;
    
    FGS.students.forEach((student, idx) => {
        if (idx === 0) {
            console.log(`\n🎨 ====== RENDERING STUDENT #${idx}: ${student.student_id} ======`);
        }
        html += `<tr>`;
        html += `<td class="student-col" style="background:#ffffff; position:sticky; left:0; box-shadow: 4px 0 6px -4px rgba(0,0,0,0.05); z-index:5; padding:12px 8px;"><div class="fgs-student-cell"><div class="fgs-student-avatar" style="width:40px; height:40px; font-weight:700; min-width:40px;">${student.name.charAt(0).toUpperCase()}</div><div><div class="fgs-student-name" style="font-weight:700; color:#111; font-size:14px;">${student.name}</div><div class="fgs-student-id" style="font-size:12px; color:#666;">${student.student_id}</div></div></div></td>`;
        
        // Check if this student has an incomplete grade status
        const studentGradeStatus = FGS.gradeStatuses && FGS.gradeStatuses[student.student_id] ? FGS.gradeStatuses[student.student_id] : null;
        const isIncomplete = studentGradeStatus === 'incomplete';
        
        let totalScore = 0, totalMax = 0;
        columns.forEach((col, colIdx) => {
            const key = `${student.student_id}_${col.id}`;
            const gradeObj = FGS.grades[key] || {};
            let rawVal = (gradeObj.score !== undefined && gradeObj.score !== null) ? gradeObj.score : '';
            const gradeStatus = gradeObj.status || 'submitted';  // Check if component is marked as INC
            const maxScore = parseFloat(col.max_score);
            
            if (idx === 0) {
                console.log(`  Col ${colIdx}: ${col.column_name} (ID:${col.id}, maxScore:${maxScore}, status:${gradeStatus})`);
                console.log(`    Key: ${key}`);
                console.log(`    Grade Object:`, gradeObj);
                console.log(`    Raw Value From DB:`, rawVal, `(type: ${typeof rawVal})`);
            }
            
            // Strip % symbol if present as string (debug info)
            if (typeof rawVal === 'string') {
                if (rawVal.includes('%')) {
                    console.debug(`DEBUG: Found '%' symbol in grade for student ${student.student_id}, col ${col.id} — original rawVal: '${rawVal}'`);
                    rawVal = rawVal.replace('%', '').trim();
                    console.debug(`DEBUG: After removing '%', rawVal='${rawVal}'`);
                } else {
                    // Log if value is stored as string but not percent
                    if (idx === 0) console.debug(`DEBUG: rawVal is string for ${student.student_id}_${col.id}: '${rawVal}' (no % found)`);
                }
            }
            
            // CRITICAL AUTO-FIX: Detect if value is stored as percentage instead of raw score
            let needsFix = false;
            if (rawVal !== '' && !isNaN(parseFloat(rawVal))) {
                let numVal = parseFloat(rawVal);

                if (idx === 0) {
                    console.log(`    → Parsed numVal: ${numVal}`);
                    console.log(`    → Check: numVal (${numVal}) > maxScore (${maxScore})?  ${numVal > maxScore}`);
                    console.log(`    → Check: numVal (${numVal}) <= 100?  ${numVal <= 100}`);
                    console.log(`    → Check: maxScore (${maxScore}) >= 5?  ${maxScore >= 5}`);
                }

                // Enhanced auto-fix: Only trigger if value > maxScore, <= 100, and maxScore is reasonable (>= 5)
                // This prevents false positives for small max scores where raw scores might be > maxScore due to data errors
                if (numVal > maxScore && numVal <= 100 && maxScore >= 5) {
                    console.warn(`DEBUG-AUTO-FIX: Treating stored value ${numVal} as percent for ${student.student_id} col ${col.id} (maxScore ${maxScore}) — converting to raw`);
                    const correctedRawVal = (numVal / 100) * maxScore;
                    if (idx === 0) console.warn(`    🔧 AUTO-FIX: Converting ${numVal}% → ${correctedRawVal.toFixed(2)}/${maxScore} (maxScore: ${maxScore})`);

                    // Update rawVal to the corrected value for display
                    rawVal = correctedRawVal;
                    needsFix = true;

                    // Save corrected value to database in background (non-blocking)
                    (async () => {
                        try {
                            const fd = new FormData();
                            fd.append('column_id', col.id);
                            fd.append('grade', correctedRawVal);
                            fd.append('student_id', student.student_id);
                            fd.append('class_code', FGS.currentClassCode);
                            fd.append('csrf_token', window.csrfToken || APP.csrfToken);

                            const res = await fetch('/ajax/update_grade.php', { method: 'POST', body: fd });
                            const data = await res.json();
                            if (data.success) {
                                console.log(`    ✅ Database corrected: ${student.student_id} column ${col.id} = ${correctedRawVal.toFixed(2)}`);
                                console.debug(`DEBUG: Auto-fix DB save response for ${student.student_id}_${col.id}:`, data);
                                FGS.grades[key].score = correctedRawVal;
                            } else {
                                console.error(`    ❌ Database correction failed: ${data.message || 'Unknown error'}`);
                            }
                        } catch(err) {
                            console.error(`Auto-fix error for ${student.student_id}:`, err);
                        }
                    })();
                } else {
                    if (idx === 0) {
                        if (numVal > maxScore && numVal > 100) {
                            console.log(`    ✓ Value OK - raw score (${numVal}) > maxScore (${maxScore}) but > 100, likely not percentage`);
                        } else if (numVal <= maxScore) {
                            console.log(`    ✓ Value OK - raw score (${numVal} <= ${maxScore})`);
                        } else if (maxScore < 5) {
                            console.log(`    ✓ Skipping auto-fix - maxScore (${maxScore}) too small, likely not percentage issue`);
                        } else {
                            console.log(`    ? Unexpected case - numVal: ${numVal}, maxScore: ${maxScore}`);
                        }
                    }
                }
            }
            
            // Calculate total using the corrected value (skip if INC)
            if (gradeStatus !== 'inc' && rawVal !== '' && !isNaN(parseFloat(rawVal))) {
                totalScore += parseFloat(rawVal);
                totalMax += maxScore;
            }
            
            // Format display value - NOW uses corrected rawVal
            let displayVal = rawVal !== '' ? (parseFloat(rawVal) % 1 === 0 ? parseInt(rawVal) : parseFloat(rawVal).toFixed(2)) : '';
            // Force removal of any stray '%' and trim
            if (typeof displayVal === 'string' && displayVal.includes('%')) {
                console.debug(`DEBUG: Removing stray '%' from displayVal for ${student.student_id}_${col.id} -> '${displayVal}'`);
                displayVal = displayVal.replace(/%/g, '').trim();
            }
            
            if (idx === 0) {
                console.log(`    → Final displayVal: "${displayVal}"`);
                console.log(`    → Will render with background: ${needsFix ? '#fff3cd (YELLOW)' : '#fff (WHITE)'}`);
                console.log(`    → Grade status: ${gradeStatus}`);
            }
            
            // Add visual indicator if value was auto-fixed
            const bgColor = needsFix ? '#fff3cd' : '#fff';
            const borderColor = needsFix ? '#ffc107' : '#d1d5db';
            
            // If component is marked as INC, show "INC" instead of input field
            if (gradeStatus === 'inc') {
                html += `<td style="text-align:center; padding:12px 6px; background:#fee2e2; border-radius:5px; position:relative;" title="Marked as incomplete" oncontextmenu="showComponentContextMenu(event, '${student.student_id}', ${col.id}); return false;"><div style="font-weight:700; font-size:14px; color:#dc2626; padding:8px; cursor:pointer;">INC</div></td>`;
            } else {
                html += `<td style="text-align:center; padding:12px 6px; position:relative;"><input type="number" class="fgs-score-input" data-student-id="${student.student_id}" data-column-id="${col.id}" min="0" max="${maxScore}" step="0.01" value="${displayVal}" onchange="saveRawScore(this)" oninput="validateInput(this, ${maxScore})" oncontextmenu="showComponentContextMenu(event, '${student.student_id}', ${col.id}); return false;" style="width:70px; padding:8px 6px; border:1.5px solid ${borderColor}; border-radius:5px; text-align:center; font-weight:600; font-size:14px; transition:all .2s ease; background:${bgColor};" onmouseover="this.style.borderColor='#9ca3af'" onmouseout="this.style.borderColor='${borderColor}'" onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)'" onblur="this.style.borderColor='${borderColor}'; this.style.boxShadow='none'" /></td>`;
            }
        });
        
        const totalPct = totalMax > 0 ? (totalScore / totalMax) * 100 : 0;
        html += `<td style="text-align:center; background:linear-gradient(180deg,#fffdf6 0%,#fff9e7 100%); font-weight:700; padding:12px 10px; color:#7a4e00; font-size:14px;">${totalPct.toFixed(1)}%</td>`;
        html += `</tr>`;
        
        if (idx === 0) {
            console.log(`🎨 ====== END STUDENT RENDER ======\n`);
        }
    });
    
    html += `</tbody></table></div>`;
    html += `</div>`;
    
    // Add action buttons footer AFTER the container closes
    html += `
        <div class="fgs-summary-footer">
            <button onclick="addColumnModal(${comp.id},'${comp.component_name.replace(/'/g, "\\'")}')" class="fgs-modal-btn fgs-modal-btn-submit" style="display: inline-flex; align-items: center; gap: 8px;">
                <i class="fas fa-plus"></i> Add Item
            </button>
            <button onclick="bulkAddColumnModal(${comp.id},'${comp.component_name.replace(/'/g, "\\'")}')" class="fgs-summary-footer-btn-bulk" style="display: inline-flex; align-items: center; gap: 8px;">
                <i class="fas fa-layer-group"></i> Bulk Add Items
            </button>
        </div>
    `;
    
    html += `</div>`;
    
    console.log(`\n💾 BEFORE RENDER TO DOM: Checking HTML that will be rendered...`);
    console.log(`💾 HTML length: ${html.length} characters`);
    // Count how many input fields in HTML
    const inputCount = (html.match(/type="number"/g) || []).length;
    console.log(`💾 Input fields in HTML: ${inputCount}`);
    // Log sample of second student row if exists
    const rows = html.split('<tr>');
    if (rows.length > 2) {
        const secondRow = rows[2].substring(0, 500); // First 500 chars of second row
        console.log(`💾 Second row starts with:`, secondRow);
    }
    
    // Prepend helper banner that instructs users to enter raw scores only
    container.innerHTML = (typeof helperBanner !== 'undefined' ? helperBanner : '') + html;
    
    console.log(`✅ HTML rendered to DOM. Table should now be visible.`);
    
    // POST-RENDER DOM VERIFICATION
    console.log(`\n🔍 POST-RENDER VERIFICATION`);    
    setTimeout(() => {
        const inputs = container.querySelectorAll('.fgs-score-input');
        console.log(`🔍 Found ${inputs.length} input fields in DOM`);
        
        // Check each input's actual value
        inputs.forEach((inp, idx) => {
            const studentId = inp.getAttribute('data-student-id');
            const colId = inp.getAttribute('data-column-id');
            const displayedValue = inp.value;
            const bgColor = inp.style.background;
            
            // Only log first few and any that show percentages
            if (idx < 6 || displayedValue.includes('%')) {
                console.log(`🔍 Input[${idx}]: Student ${studentId}, Col ${colId} = "${displayedValue}" (bg: ${bgColor})`);
            }
        });
        console.log(`🔍 POST-RENDER VERIFICATION COMPLETE\n`);
    }, 100);
    
    // ATTACH TAB/ENTER/ARROW KEY HANDLERS TO ALL INPUTS
    setTimeout(() => {
        const inputs = container.querySelectorAll('.fgs-score-input');
        inputs.forEach(input => {
            input.addEventListener('keydown', handleGradeInputKeydown);
            // Note: Save is handled by onchange inline handler, no need for blur event
            
            // DEBUG: Monitor if something overwrites input values
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'value') {
                        const newValue = input.value;
                        const studentId = input.getAttribute('data-student-id');
                        const columnId = input.getAttribute('data-column-id');
                        console.warn(`⚠️ INPUT VALUE CHANGED EXTERNALLY! Student: ${studentId}, Column: ${columnId}, New Value: "${newValue}"`);
                        console.trace('Change source:');
                    }
                });
            });
            observer.observe(input, { attributes: true, attributeFilter: ['value'] });
        });
    }, 50);
}

/**
 * Switch to Midterm Summary view - Shows midterm grades with status and actions
 */
function switchToMidtermSummary() {
    FGS.currentComponentId = null;  // Clear component selection
    renderUI();  // Re-render cards to show Midterm Summary as active
    const isMidterm = FGS.currentTermType === 'midterm';
    const containerId = isMidterm ? 'flexible-table-container' : 'flexible-table-container-finals';
    const container = document.getElementById(containerId);
    
    if (!container) {
        console.warn(`Container ${containerId} not found`);
        return;
    }
    
    if (!FGS.students || FGS.students.length === 0) {
        container.innerHTML = `
            <div class="fgs-empty-state">
                <i class="fas fa-users fgs-empty-icon"></i>
                <h3 class="fgs-empty-title">No Students</h3>
                <p class="fgs-empty-description">No students enrolled in this class</p>
            </div>
        `;
        return;
    }
    
    // Get components for midterm
    const midtermComponents = FGS.allComponents?.filter(c => c.term_type === 'midterm') || [];
    
    // Calculate midterm percentage for each student
    let html = `
        <div class="fgs-summary-container">
            <div class="fgs-summary-header">
                <div>
                    <h3 class="fgs-summary-title">Midterm Summary</h3>
                    <p class="fgs-summary-subtitle">Midterm Weight: ${FGS.midtermWeight}%</p>
                </div>
            </div>
            <div class="fgs-summary-scroll">
                <table class="fgs-summary-table">
                    <thead>
                        <tr>
                            <th class="student-col" style="position:sticky; left:0; z-index:10; background:#f0f4f8;">
                                <span style="font-weight:700;">Student</span>
                            </th>
                            <th style="text-align:center; background:#e8eef9; color:#0b3b85; padding:8px 4px;"><div style="font-size:13px; font-weight:700; letter-spacing:.5px;">MIDTERM %</div></th>
                            <th style="text-align:center; background:#f0f4f8; color:#0b3b85; padding:8px 4px;"><div style="font-size:13px; font-weight:700; letter-spacing:.5px;">GRADE</div></th>
                            <th style="text-align:center; background:#f0f4f8; color:#0b3b85; padding:8px 4px;"><div style="font-size:13px; font-weight:700; letter-spacing:.5px;">STATUS</div></th>
                            <th style="text-align:center; background:#f0f4f8; color:#0b3b85; padding:8px 4px;"><div style="font-size:13px; font-weight:700; letter-spacing:.5px;">ACTIONS</div></th>
                        </tr>
                    </thead>
                    <tbody>`;
    
    FGS.students.forEach(student => {
        // Calculate midterm percentage from all midterm components
        let totalScore = 0, totalMax = 0;
        
        midtermComponents.forEach(comp => {
            const columns = comp.columns || [];
            columns.forEach(col => {
                const key = `${student.student_id}_${col.id}`;
                const gradeObj = FGS.grades[key] || {};
                const score = parseFloat(gradeObj.score) || 0;
                if (score > 0) {
                    totalScore += score;
                    totalMax += parseFloat(col.max_score);
                }
            });
        });
        
        const midtermPct = totalMax > 0 ? (totalScore / totalMax) * 100 : 0;
        const midtermGrade = toGrade(midtermPct);
        
        // Get current status
        const studentStatus = FGS.gradeStatuses?.[student.student_id] || 'pending';
        const statusColors = {
            'passed': '#10b981',
            'failed': '#ef4444',
            'incomplete': '#f59e0b',
            'dropped': '#6b7280',
            'pending': '#9ca3af'
        };
        const statusLabels = {
            'passed': 'Passed',
            'failed': 'Failed',
            'incomplete': 'INC',
            'dropped': 'DRP',
            'pending': 'Pending'
        };
        
        html += `
            <tr>
                <td class="student-col" style="background:#ffffff; position:sticky; left:0; box-shadow: 4px 0 6px -4px rgba(0,0,0,0.05); z-index:5; padding:12px 8px;">
                    <div class="fgs-student-cell">
                        <div class="fgs-student-avatar" style="width:40px; height:40px; font-weight:700; min-width:40px;">${student.name.charAt(0).toUpperCase()}</div>
                        <div>
                            <div class="fgs-student-name" style="font-weight:700; color:#111; font-size:14px;">${student.name}</div>
                            <div class="fgs-student-id" style="font-size:12px; color:#666;">${student.student_id}</div>
                        </div>
                    </div>
                </td>
                <td style="text-align:center; background:#e8eef9; padding:12px 10px; font-weight:700; color:#0b3b85; font-size:14px;">${midtermPct.toFixed(1)}%</td>
                <td style="text-align:center; padding:12px 10px;">
                    <div style="background:${gradeColor(midtermGrade)}; color:white; padding:6px 12px; border-radius:8px; font-size:14px; font-weight:800; display:inline-block;">
                        ${midtermGrade.toFixed(1)}
                    </div>
                </td>
                <td style="text-align:center; padding:12px 8px;">
                    <span class="fgs-status-badge" id="status-badge-${student.student_id}" style="background:${statusColors[studentStatus] || '#9ca3af'}; color: white; padding: 6px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; display: inline-block;">
                        ${statusLabels[studentStatus] || 'Pending'}
                    </span>
                </td>
                <td style="text-align:center; padding:12px 8px;">
                    <button onclick="changeGradeStatus('${student.student_id}', '${student.name.replace(/'/g, "\\'")}', ${midtermPct.toFixed(2)})" class="btn btn-sm btn-outline" style="padding: 6px 12px; font-size: 12px; background:#e5e7eb; border:1px solid #d1d5db; border-radius:6px; cursor:pointer; font-weight:600; color:#374151; transition:all 0.2s;">
                        <i class="fas fa-edit"></i> Change Status
                    </button>
                </td>
            </tr>`;
    });
    
    html += `</tbody></table></div></div>`;
    
    container.innerHTML = html;
    console.log('✓ Midterm Summary rendered');
}

/**
 * Handle Tab, Enter, Arrow keys on grade inputs - SMOOTH NAVIGATION
 */
function handleGradeInputKeydown(event) {
    if (event.key !== 'Tab' && event.key !== 'Enter' && 
        event.key !== 'ArrowUp' && event.key !== 'ArrowDown' && 
        event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
        return;
    }
    
    const currentInput = event.target;
    
    // Note: onchange will handle saving when focus moves
    // No need to explicitly save here
    
    const tbody = currentInput.closest('tbody');
    if (!tbody) return;
    
    const currentRow = currentInput.closest('tr');
    const allRows = Array.from(tbody.querySelectorAll('tr'));
    const currentRowIdx = allRows.indexOf(currentRow);
    
    const allInputsInRow = Array.from(currentRow.querySelectorAll('.fgs-score-input'));
    const currentColIdx = allInputsInRow.indexOf(currentInput);
    
    let nextInput = null;
    
    if (event.key === 'Tab' || event.key === 'Enter') {
        // TAB/ENTER: Move to next cell (right), or next row if at end
        event.preventDefault();
        
        if (currentColIdx < allInputsInRow.length - 1) {
            // Move right in same row
            nextInput = allInputsInRow[currentColIdx + 1];
        } else if (currentRowIdx < allRows.length - 1) {
            // Move to first cell of next row
            const nextRow = allRows[currentRowIdx + 1];
            const nextRowInputs = Array.from(nextRow.querySelectorAll('.fgs-score-input'));
            nextInput = nextRowInputs[0];
        }
    } else if (event.shiftKey && event.key === 'Tab') {
        // SHIFT+TAB: Move to previous cell (left), or previous row if at start
        event.preventDefault();
        
        if (currentColIdx > 0) {
            // Move left in same row
            nextInput = allInputsInRow[currentColIdx - 1];
        } else if (currentRowIdx > 0) {
            // Move to last cell of previous row
            const prevRow = allRows[currentRowIdx - 1];
            const prevRowInputs = Array.from(prevRow.querySelectorAll('.fgs-score-input'));
            nextInput = prevRowInputs[prevRowInputs.length - 1];
        }
    } else if (event.key === 'ArrowUp') {
        // UP: Move to same column, previous row
        event.preventDefault();
        
        if (currentRowIdx > 0) {
            const prevRow = allRows[currentRowIdx - 1];
            const prevRowInputs = Array.from(prevRow.querySelectorAll('.fgs-score-input'));
            nextInput = prevRowInputs[currentColIdx];
        }
    } else if (event.key === 'ArrowDown') {
        // DOWN: Move to same column, next row
        event.preventDefault();
        
        if (currentRowIdx < allRows.length - 1) {
            const nextRow = allRows[currentRowIdx + 1];
            const nextRowInputs = Array.from(nextRow.querySelectorAll('.fgs-score-input'));
            nextInput = nextRowInputs[currentColIdx];
        }
    } else if (event.key === 'ArrowLeft') {
        // LEFT: Move to previous cell
        event.preventDefault();
        
        if (currentColIdx > 0) {
            nextInput = allInputsInRow[currentColIdx - 1];
        }
    } else if (event.key === 'ArrowRight') {
        // RIGHT: Move to next cell
        event.preventDefault();
        
        if (currentColIdx < allInputsInRow.length - 1) {
            nextInput = allInputsInRow[currentColIdx + 1];
        }
    }
    
    // Focus and select the next input for smooth entry
    if (nextInput) {
        nextInput.focus();
        nextInput.select();
    }
}

window.renderTable = renderTable;

function validateInput(input, max) {
    // Remove any % symbols that might have been pasted
    if (input.value.includes('%')) {
        input.value = input.value.replace('%', '').trim();
    }
    
    const val = parseFloat(input.value);
    if (isNaN(val) || val < 0) {
        input.value = 0;
    } else if (val > max) {
        input.value = max;
    }
}

// Simple toast notification utility
function toast(type, message) {
    const icons = { success: 'check-circle', error: 'exclamation-circle', warning: 'exclamation-triangle', info: 'info-circle' };
    const icon = icons[type] || icons.info;
    const toast = document.createElement('div');
    toast.className = `fgs-toast ${type}`;
    toast.innerHTML = `<i class="fas fa-${icon}"></i><span>${message}</span>`;
    document.body.appendChild(toast);
    setTimeout(() => { toast.classList.add('slide-out'); setTimeout(() => toast.remove(), 300); }, 3000);
}

function addComponentModal() {
    
    if (FGS.remainingPercentage <= 0) {
        toast('error', `Cannot add component. ${FGS.currentTermType === 'midterm' ? 'Midterm' : 'Finals'} allocation of ${FGS.termLimit}% is already fully used!`);
        return;
    }
    
    const m = document.getElementById('add-comp-modal');
    if (m) m.remove();

    const html = `
        <div id="add-comp-modal" class="fgs-modal-overlay">
            <div class="fgs-modal">
                <div class="fgs-modal-header">
                    <h3 class="fgs-modal-title">Add New Component</h3>
                    <button onclick="closeModal('add-comp-modal')" class="fgs-modal-close">&times;</button>
                </div>
                <form onsubmit="return submitAddComp(event)">
                    <div class="fgs-modal-body">
                        <div class="fgs-form-group">
                            <label class="fgs-form-label">Component Name *</label>
                            <input type="text" id="comp-name" class="fgs-form-input" required placeholder="e.g., Written Works, Performance Tasks">
                        </div>
                        <div class="fgs-form-group">
                            <label class="fgs-form-label">Percentage (%) *</label>
                            <input type="number" id="comp-pct" class="fgs-form-input" required min="0" max="${FGS.remainingPercentage}" step="0.01" placeholder="e.g., 20">
                            <small class="fgs-form-hint">Remaining: ${FGS.remainingPercentage.toFixed(1)}%</small>
                        </div>
                    </div>
                    <div class="fgs-modal-actions">
                        <button type="button" onclick="closeModal('add-comp-modal')" class="fgs-modal-btn fgs-modal-btn-cancel">Cancel</button>
                        <button type="submit" class="fgs-modal-btn fgs-modal-btn-submit">Add Component</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', html);
}

async function submitAddComp(e) {
    e.preventDefault();
    const name = document.getElementById('comp-name').value.trim();
    const pct = parseFloat(document.getElementById('comp-pct').value);

    if (pct > FGS.remainingPercentage) {
        toast('error', `Cannot exceed remaining ${FGS.remainingPercentage.toFixed(1)}%`);
        return false;
    }

    const fd = new FormData();
    fd.append('action', 'add_component');
    fd.append('class_code', FGS.currentClassCode);
    fd.append('term_type', FGS.currentTermType);
    fd.append('component_name', name);
    fd.append('percentage', pct);
    fd.append('csrf_token', window.csrfToken || APP.csrfToken);

    const res = await fetch(APP.apiPath + 'manage_grading_components.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.success) {
        toast('success', 'Component added successfully!');
        closeModal('add-comp-modal');
        await loadComponents(FGS.currentClassCode, FGS.currentTermType);
        renderUI();
    } else {
        toast('error', data.message);
    }
    return false;
}

async function editComponent(id, name, pct) {
    const m = document.getElementById('edit-comp-modal');
    if (m) m.remove();

    // Get course code from class code
    const courseCode = FGS.currentClassCode ? FGS.currentClassCode.split('_')[2] : '';
    
    // Load Course Outcomes for this subject
    let courseOutcomes = [];
    if (courseCode) {
        try {
            const response = await fetch(`${window.APP?.apiPath || "/faculty/ajax/"}get_subject_outcomes.php?code=${courseCode}`);
            const data = await response.json();
            if (data.success && data.outcomes) {
                courseOutcomes = data.outcomes;
            }
        } catch (error) {
            console.error('Error loading course outcomes:', error);
        }
    }

    // Fetch current component details to get existing CO mappings
    let existingCoMappings = [];
    let isSummative = false;
    let performanceTarget = 60;
    try {
        const fd = new FormData();
        fd.append('action', 'get_component_details');
        fd.append('component_id', id);
        fd.append('csrf_token', window.csrfToken || APP.csrfToken);
        
        const response = await fetch(APP.apiPath + 'manage_grading_components.php', { method: 'POST', body: fd });
        const data = await response.json();
        
        if (data.success && data.component && data.component.columns && data.component.columns.length > 0) {
            // Get CO mappings from the first column (they should all be the same)
            existingCoMappings = data.component.columns[0].co_mappings || [];
            isSummative = data.component.columns[0].is_summative === 'yes';
            performanceTarget = data.component.columns[0].performance_target || 60;
        }
    } catch (error) {
        console.error('Error loading component details:', error);
    }

    // Build CO checkboxes HTML
    let coCheckboxesHtml = '';
    if (courseOutcomes.length > 0) {
        coCheckboxesHtml = `
            <div class="fgs-form-group" style="margin-top: 20px; padding: 15px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb;">
                <label class="fgs-form-label" style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                    <i class="fas fa-bullseye" style="color: #3b82f6;"></i>
                    Apply Course Outcomes to ALL Items (Optional)
                </label>
                <small class="fgs-form-hint" style="display: block; margin-bottom: 12px; color: #6b7280;">
                    Select outcomes to apply to all items in this component
                </small>
                <div id="co-checkboxes-edit-comp" style="display: flex; flex-direction: column; gap: 8px;">
        `;
        
        courseOutcomes.forEach(co => {
            const isChecked = existingCoMappings.includes(parseInt(co.number)) ? 'checked' : '';
            coCheckboxesHtml += `
                <label style="display: flex; align-items: start; gap: 10px; padding: 8px; cursor: pointer; border-radius: 6px; transition: background 0.2s;" 
                       onmouseover="this.style.background='#f3f4f6'" 
                       onmouseout="this.style.background='transparent'">
                    <input type="checkbox" 
                           name="co_mapping_edit_comp[]" 
                           value="${co.number}" 
                           ${isChecked}
                           style="margin-top: 2px; cursor: pointer;">
                    <span style="flex: 1; font-size: 14px; line-height: 1.5;">
                        <strong style="color: #1f2937;">CO${co.number}:</strong> 
                        <span style="color: #4b5563;">${co.description}</span>
                    </span>
                </label>
            `;
        });
        
        coCheckboxesHtml += `
                </div>
            </div>
        `;
        
        coCheckboxesHtml += `
            <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="is-summative-checkbox-edit-comp" ${isSummative ? 'checked' : ''} style="cursor: pointer;" onchange="togglePerformanceTargetEditComp()">
                    <span style="font-weight: 600; color: #1f2937;">
                        <i class="fas fa-graduation-cap" style="color: #3b82f6;"></i>
                        Mark ALL Items as Summative Assessment
                    </span>
                </label>
                <div id="performance-target-section-edit-comp" style="${isSummative ? 'block' : 'none'}; margin-left: 24px;">
                    <label class="fgs-form-label" style="font-size: 13px;">Performance Target (%)</label>
                    <input type="number" id="performance-target-input-edit-comp" class="fgs-form-input" min="0" max="100" value="${performanceTarget}" step="0.01" style="width: 120px;">
                    <small class="fgs-form-hint">Minimum percentage to be considered successful</small>
                </div>
            </div>
        `;
    }

    const html = `
        <div id="edit-comp-modal" class="fgs-modal-overlay">
            <div class="fgs-modal" style="max-width: 600px;">
                <div class="fgs-modal-header">
                    <h3 class="fgs-modal-title">Edit Component</h3>
                    <button onclick="closeModal('edit-comp-modal')" class="fgs-modal-close">&times;</button>
                </div>
                <form onsubmit="return submitEditComp(event,${id})">
                    <div class="fgs-modal-body">
                        <div class="fgs-form-group">
                            <label class="fgs-form-label">Component Name *</label>
                            <input type="text" id="edit-comp-name" class="fgs-form-input" required value="${name}">
                        </div>
                        <div class="fgs-form-group">
                            <label class="fgs-form-label">Percentage (%) *</label>
                            <input type="number" id="edit-comp-pct" class="fgs-form-input" required min="0" step="0.01" value="${pct}">
                        </div>
                        ${coCheckboxesHtml}
                    </div>
                    <div class="fgs-modal-actions">
                        <button type="button" onclick="closeModal('edit-comp-modal')" class="fgs-modal-btn fgs-modal-btn-cancel">Cancel</button>
                        <button type="submit" class="fgs-modal-btn fgs-modal-btn-update">Update Component & Apply to All Items</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', html);
}

// Toggle performance target for edit component
window.togglePerformanceTargetEditComp = function() {
    const checkbox = document.getElementById('is-summative-checkbox-edit-comp');
    const section = document.getElementById('performance-target-section-edit-comp');
    if (checkbox && section) {
        section.style.display = checkbox.checked ? 'block' : 'none';
    }
};

async function submitEditComp(e, id) {
    e.preventDefault();
    const name = document.getElementById('edit-comp-name').value.trim();
    const pct = parseFloat(document.getElementById('edit-comp-pct').value);

    // Get CO mappings
    const coCheckboxes = document.querySelectorAll('input[name="co_mapping_edit_comp[]"]:checked');
    const coMappings = Array.from(coCheckboxes).map(cb => parseInt(cb.value));
    
    // Get summative assessment data
    const isSummative = document.getElementById('is-summative-checkbox-edit-comp')?.checked ? 'yes' : 'no';
    const performanceTarget = document.getElementById('performance-target-input-edit-comp')?.value || '60';

    const fd = new FormData();
    fd.append('action', 'update_component');
    fd.append('component_id', id);
    fd.append('component_name', name);
    fd.append('percentage', pct);
    
    // Add CO mappings and summative data to apply to all items
    if (coMappings.length > 0) {
        fd.append('apply_co_mappings', 'yes');
        fd.append('co_mappings', JSON.stringify(coMappings));
    }
    
    if (isSummative === 'yes') {
        fd.append('apply_summative', 'yes');
        fd.append('is_summative', isSummative);
        fd.append('performance_target', performanceTarget);
    }
    
    fd.append('csrf_token', window.csrfToken || APP.csrfToken);

    const res = await fetch(APP.apiPath + 'manage_grading_components.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.success) {
        toast('success', 'Component updated successfully!');
        closeModal('edit-comp-modal');
        await loadComponents(FGS.currentClassCode, FGS.currentTermType);
        renderUI();
    } else {
        toast('error', data.message);
    }
    return false;
}

async function delComponent(id, name) {
    if (!confirm(`Delete "${name}"?\n\nThis will delete all related items and grades.`)) return;

    const fd = new FormData();
    fd.append('action', 'delete_component');
    fd.append('component_id', id);
    fd.append('csrf_token', window.csrfToken || APP.csrfToken);

    const res = await fetch(APP.apiPath + 'manage_grading_components.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.success) {
        toast('success', 'Component deleted successfully!');
        await loadComponents(FGS.currentClassCode, FGS.currentTermType);
        renderUI();
    } else {
        toast('error', data.message);
    }
}

async function addColumnModal(compId, compName) {
    const m = document.getElementById('add-col-modal');
    if (m) m.remove();

    // Get course code from class code (e.g., "24_T1_CTAPROJ1_INF221" -> "CTAPROJ1")
    const courseCode = FGS.currentClassCode ? FGS.currentClassCode.split('_')[2] : '';
    
    // Load Course Outcomes for this subject
    let courseOutcomes = [];
    if (courseCode) {
        try {
            const response = await fetch(`${window.APP?.apiPath || "/faculty/ajax/"}get_subject_outcomes.php?code=${courseCode}`);
            const data = await response.json();
            if (data.success && data.outcomes) {
                courseOutcomes = data.outcomes;
            }
        } catch (error) {
            console.error('Error loading course outcomes:', error);
        }
    }
    
    // Build CO checkboxes HTML
    let coCheckboxesHtml = '';
    if (courseOutcomes.length > 0) {
        coCheckboxesHtml = `
            <div class="fgs-form-group" style="margin-top: 20px; padding: 15px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb;">
                <label class="fgs-form-label" style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                    <i class="fas fa-bullseye" style="color: #3b82f6;"></i>
                    Course Outcome Assessment (Optional)
                </label>
                <small class="fgs-form-hint" style="display: block; margin-bottom: 12px; color: #6b7280;">
                    Select which outcomes this item measures. Leave unchecked if not applicable.
                </small>
                <div id="co-checkboxes" style="display: flex; flex-direction: column; gap: 8px;">
        `;
        
        courseOutcomes.forEach(co => {
            coCheckboxesHtml += `
                <label style="display: flex; align-items: start; gap: 10px; padding: 8px; cursor: pointer; border-radius: 6px; transition: background 0.2s;" 
                       onmouseover="this.style.background='#f3f4f6'" 
                       onmouseout="this.style.background='transparent'">
                    <input type="checkbox" 
                           name="co_mapping[]" 
                           value="${co.number}" 
                           style="margin-top: 2px; cursor: pointer;">
                    <span style="flex: 1; font-size: 14px; line-height: 1.5;">
                        <strong style="color: #1f2937;">CO${co.number}:</strong> 
                        <span style="color: #4b5563;">${co.description}</span>
                    </span>
                </label>
            `;
        });
        
        coCheckboxesHtml += `
                </div>
            </div>
        `;
    }
if (courseOutcomes.length > 0) {
        coCheckboxesHtml += `
            <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="is-summative-checkbox" style="cursor: pointer;" onchange="togglePerformanceTarget()">
                    <span style="font-weight: 600; color: #1f2937;">
                        <i class="fas fa-graduation-cap" style="color: #3b82f6;"></i>
                        Mark as Summative Assessment
                    </span>
                </label>
                <div id="performance-target-section" style="display: none; margin-left: 24px;">
                    <label class="fgs-form-label" style="font-size: 13px;">Performance Target (%)</label>
                    <input type="number" id="performance-target-input" class="fgs-form-input" min="0" max="100" value="60" step="0.01" style="width: 120px;">
                    <small class="fgs-form-hint">Minimum percentage to be considered successful</small>
                </div>
            </div>
        `;
    }
    const html = `
        <div id="add-col-modal" class="fgs-modal-overlay">
            <div class="fgs-modal" style="max-width: 600px;">
                <div class="fgs-modal-header">
                    <h3 class="fgs-modal-title">Add Item to ${compName}</h3>
                    <button onclick="closeModal('add-col-modal')" class="fgs-modal-close">&times;</button>
                </div>
                <form onsubmit="return submitAddCol(event,${compId})">
                    <div class="fgs-modal-body">
                        <div class="fgs-form-group">
                            <label class="fgs-form-label">Item Name *</label>
                            <input type="text" id="col-name" class="fgs-form-input" required placeholder="e.g., Quiz 1, Assignment 2">
                        </div>
                        <div class="fgs-form-group">
                            <label class="fgs-form-label">Max Score *</label>
                            <input type="number" id="col-max" class="fgs-form-input" required min="1" value="100">
                        </div>
                        ${coCheckboxesHtml}
                    </div>
                    <div class="fgs-modal-actions">
                        <button type="button" onclick="closeModal('add-col-modal')" class="fgs-modal-btn fgs-modal-btn-cancel">Cancel</button>
                        <button type="submit" class="fgs-modal-btn fgs-modal-btn-submit">Add Item</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', html);
}
async function bulkAddColumnModal(compId, compName) {
    const m = document.getElementById('bulk-add-col-modal');
    if (m) m.remove();

    // Get course code from class code (e.g., "24_T1_CTAPROJ1_INF221" -> "CTAPROJ1")
    const courseCode = FGS.currentClassCode ? FGS.currentClassCode.split('_')[2] : '';
    
    // Load Course Outcomes for this subject
    let courseOutcomes = [];
    if (courseCode) {
        try {
            const response = await fetch(`${window.APP?.apiPath || "/faculty/ajax/"}get_subject_outcomes.php?code=${courseCode}`);
            const data = await response.json();
            if (data.success && data.outcomes) {
                courseOutcomes = data.outcomes;
            }
        } catch (error) {
            console.error('Error loading course outcomes:', error);
        }
    }
    
    // Build CO checkboxes HTML
    let coCheckboxesHtml = '';
    if (courseOutcomes.length > 0) {
        coCheckboxesHtml = `
            <div class="fgs-form-group" style="margin-top: 20px; padding: 15px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb;">
                <label class="fgs-form-label" style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                    <i class="fas fa-bullseye" style="color: #3b82f6;"></i>
                    Course Outcome Assessment (Optional)
                </label>
                <small class="fgs-form-hint" style="display: block; margin-bottom: 12px; color: #6b7280;">
                    Select which outcomes ALL these items will measure.
                </small>
                <div id="co-checkboxes-bulk" style="display: flex; flex-direction: column; gap: 8px;">
        `;
        
        courseOutcomes.forEach(co => {
            coCheckboxesHtml += `
                <label style="display: flex; align-items: start; gap: 10px; padding: 8px; cursor: pointer; border-radius: 6px; transition: background 0.2s;" 
                       onmouseover="this.style.background='#f3f4f6'" 
                       onmouseout="this.style.background='transparent'">
                    <input type="checkbox" 
                           name="co_mapping_bulk[]" 
                           value="${co.number}" 
                           style="margin-top: 2px; cursor: pointer;">
                    <span style="flex: 1; font-size: 14px; line-height: 1.5;">
                        <strong style="color: #1f2937;">CO${co.number}:</strong> 
                        <span style="color: #4b5563;">${co.description}</span>
                    </span>
                </label>
            `;
        });
        
        coCheckboxesHtml += `
                </div>
            </div>
        `;
    }

    const html = `
        <div id="bulk-add-col-modal" class="fgs-modal-overlay">
            <div class="fgs-modal" style="max-width: 600px;">
                <div class="fgs-modal-header">
                    <h3 class="fgs-modal-title">Bulk Add Items to ${compName}</h3>
                    <button onclick="closeModal('bulk-add-col-modal')" class="fgs-modal-close">&times;</button>
                </div>
                <form onsubmit="return submitBulkAddCol(event, ${compId})">
    <div class="fgs-modal-body" style="max-height: 60vh; overflow-y: auto;">
                        <div class="fgs-form-group">
                            <label class="fgs-form-label">Base Name *</label>
                            <input type="text" id="bulk-base-name" class="fgs-form-input" required placeholder="e.g., Quiz, Assignment, Activity" oninput="updateBulkPreview()">
                            <small class="fgs-form-hint">Items will be numbered automatically (e.g., Quiz 1, Quiz 2)</small>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <div class="fgs-form-group">
                                <label class="fgs-form-label">Starting Number *</label>
                                <input type="number" id="bulk-start-num" class="fgs-form-input" required min="1" value="1" oninput="updateBulkPreview()">
                            </div>
                            <div class="fgs-form-group">
                                <label class="fgs-form-label">How Many *</label>
                                <input type="number" id="bulk-count" class="fgs-form-input" required min="1" max="20" value="5" oninput="updateBulkPreview()">
                                <small class="fgs-form-hint">Max: 20 items</small>
                            </div>
                        </div>
                        <div class="fgs-form-group">
                            <label class="fgs-form-label">Max Score (for all items) *</label>
                            <input type="number" id="bulk-max-score" class="fgs-form-input" required min="1" value="10" oninput="updateBulkPreview()">
                        </div>
                        ${coCheckboxesHtml}
                        ${courseOutcomes.length > 0 ? `
    <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
        <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
            <input type="checkbox" id="is-summative-checkbox-bulk" style="cursor: pointer;" onchange="togglePerformanceTargetBulk()">
            <span style="font-weight: 600; color: #1f2937;">
                <i class="fas fa-graduation-cap" style="color: #3b82f6;"></i>
                Mark as Summative Assessment
            </span>
        </label>
        <div id="performance-target-section-bulk" style="display: none; margin-left: 24px;">
            <label class="fgs-form-label" style="font-size: 13px;">Performance Target (%)</label>
            <input type="number" id="performance-target-input-bulk" class="fgs-form-input" min="0" max="100" value="60" step="0.01" style="width: 120px;">
            <small class="fgs-form-hint">Minimum percentage to be considered successful</small>
        </div>
    </div>
` : ''}
                        <div class="fgs-form-group">
                            <label class="fgs-form-label">Preview:</label>
                            <div id="bulk-preview" style="background: #f9fafb; border: 2px dashed #e5e7eb; border-radius: 8px; padding: 12px; max-height: 200px; overflow-y: auto;">
                                <div style="color: #9ca3af; text-align: center; padding: 20px;">
                                    <i class="fas fa-eye"></i> Preview will appear here
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="fgs-modal-actions">
                        <button type="button" onclick="closeModal('bulk-add-col-modal')" class="fgs-modal-btn fgs-modal-btn-cancel">Cancel</button>
                        <button type="submit" class="fgs-modal-btn fgs-modal-btn-submit">
                            <i class="fas fa-layer-group"></i> Add All Items
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', html);
    updateBulkPreview();
}
function updateBulkPreview() {
    const baseName = document.getElementById('bulk-base-name')?.value || '';
    const startNum = parseInt(document.getElementById('bulk-start-num')?.value || 1);
    const count = parseInt(document.getElementById('bulk-count')?.value || 5);
    const maxScore = document.getElementById('bulk-max-score')?.value || '10';
    const preview = document.getElementById('bulk-preview');
    
    if (!preview) return;
    
    if (!baseName || count < 1 || count > 20) {
        preview.innerHTML = `
            <div style="color: #9ca3af; text-align: center; padding: 20px;">
                <i class="fas fa-eye"></i> Preview will appear here
            </div>
        `;
        return;
    }
    
    let html = '<div style="display: flex; flex-direction: column; gap: 6px;">';
    for (let i = 0; i < count; i++) {
        const num = startNum + i;
        html += `
            <div style="display: flex; align-items: center; gap: 8px; padding: 8px; background: white; border-radius: 6px; border: 1px solid #e5e7eb;">
                <i class="fas fa-check-circle" style="color: #10b981;"></i>
                <span style="flex: 1; font-weight: 600; color: #1f2937;">${baseName} ${num}</span>
                <span style="color: #6b7280; font-size: 13px;">(${maxScore} pts)</span>
            </div>
        `;
    }
    html += '</div>';
    preview.innerHTML = html;
}

async function submitBulkAddCol(e, compId) {
    e.preventDefault();
    
    const baseName = document.getElementById('bulk-base-name').value.trim();
    const startNum = parseInt(document.getElementById('bulk-start-num').value);
    const count = parseInt(document.getElementById('bulk-count').value);
    const maxScore = document.getElementById('bulk-max-score').value;
    const coCheckboxes = document.querySelectorAll('input[name="co_mapping_bulk[]"]:checked');
    const coMappings = Array.from(coCheckboxes).map(cb => cb.value);

     // Get summative assessment data
    const isSummative = document.getElementById('is-summative-checkbox-bulk')?.checked ? 'yes' : 'no';
    const performanceTarget = document.getElementById('performance-target-input-bulk')?.value || 60;

    if (count < 1 || count > 20) {
        toast('error', 'Count must be between 1 and 20');
        return false;
    }
    
    // Show loading state
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
    submitBtn.disabled = true;
    
    const fd = new FormData();
    fd.append('action', 'bulk_add_columns');
    fd.append('component_id', compId);
    fd.append('base_name', baseName);
    fd.append('start_number', startNum);
    fd.append('count', count);
    fd.append('max_score', maxScore);
    fd.append('csrf_token', window.csrfToken || APP.csrfToken);
    fd.append('co_mappings', JSON.stringify(coMappings));
    fd.append('is_summative', isSummative);
    fd.append('performance_target', performanceTarget);
    
    try {
        const res = await fetch(APP.apiPath + 'manage_grading_components.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            toast('success', `Successfully added ${count} items!`);
            closeModal('bulk-add-col-modal');
            await loadComponents(FGS.currentClassCode, FGS.currentTermType);
            renderUI();
        } else {
            toast('error', data.message);
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    } catch (error) {
        console.error('Bulk add error:', error);
        toast('error', 'Failed to add items');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
    
    return false;
}

async function submitAddCol(e, compId) {
    e.preventDefault();
    const name = document.getElementById('col-name').value.trim();
    const max = document.getElementById('col-max').value;

    const coCheckboxes = document.querySelectorAll('input[name="co_mapping[]"]:checked');
    const coMappings = Array.from(coCheckboxes).map(cb => cb.value);

  
    const isSummative = document.getElementById('is-summative-checkbox')?.checked ? 'yes' : 'no';
    const performanceTarget = document.getElementById('performance-target-input')?.value || 60;

    const fd = new FormData();
    fd.append('action', 'add_column');
    fd.append('component_id', compId);
    fd.append('column_name', name);
    fd.append('max_score', max);
    fd.append('co_mappings', JSON.stringify(coMappings));
    fd.append('is_summative', isSummative); 
    fd.append('performance_target', performanceTarget); 
    fd.append('csrf_token', window.csrfToken || APP.csrfToken);

    const res = await fetch(APP.apiPath + 'manage_grading_components.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.success) {
        toast('success', 'Item added successfully!');
        closeModal('add-col-modal');
        await loadComponents(FGS.currentClassCode, FGS.currentTermType);
        renderUI();
    } else {
        toast('error', data.message);
    }
    return false;
}

async function editColumn(id, name, max) {
    const m = document.getElementById('edit-col-modal');
    if (m) m.remove();

    // Get course code
    const courseCode = FGS.currentClassCode ? FGS.currentClassCode.split('_')[2] : '';
    
    // Load Course Outcomes
    let courseOutcomes = [];
    if (courseCode) {
        try {
            const response = await fetch(`${window.APP?.apiPath || "/faculty/ajax/"}get_subject_outcomes.php?code=${courseCode}`);
            const data = await response.json();
            if (data.success && data.outcomes) {
                courseOutcomes = data.outcomes;
            }
        } catch (error) {
            console.error('Error loading course outcomes:', error);
        }
    }
    
    // Get current column data from components
    const comp = FGS.components.find(c => c.id === FGS.currentComponentId);
    const column = comp?.columns.find(col => col.id === id);
    
    const currentCoMappings = column?.co_mappings || [];
    const currentIsSummative = column?.is_summative === 'yes';
    const currentPerformanceTarget = column?.performance_target || 60;
    
    // Build CO checkboxes HTML
    let coCheckboxesHtml = '';
    if (courseOutcomes.length > 0) {
        coCheckboxesHtml = `
            <div class="fgs-form-group" style="margin-top: 20px; padding: 15px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb;">
                <label class="fgs-form-label" style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                    <i class="fas fa-bullseye" style="color: #3b82f6;"></i>
                    Course Outcome Assessment (Optional)
                </label>
                <div id="co-checkboxes-edit" style="display: flex; flex-direction: column; gap: 8px;">
        `;
        
        courseOutcomes.forEach(co => {
            const isChecked = currentCoMappings.includes(co.number.toString()) || currentCoMappings.includes(co.number);
            coCheckboxesHtml += `
                <label style="display: flex; align-items: start; gap: 10px; padding: 8px; cursor: pointer; border-radius: 6px; transition: background 0.2s;" 
                       onmouseover="this.style.background='#f3f4f6'" 
                       onmouseout="this.style.background='transparent'">
                    <input type="checkbox" 
                           name="co_mapping_edit[]" 
                           value="${co.number}" 
                           ${isChecked ? 'checked' : ''}
                           style="margin-top: 2px; cursor: pointer;">
                    <span style="flex: 1; font-size: 14px; line-height: 1.5;">
                        <strong style="color: #1f2937;">CO${co.number}:</strong> 
                        <span style="color: #4b5563;">${co.description}</span>
                    </span>
                </label>
            `;
        });
        
        coCheckboxesHtml += `
                </div>
            </div>
        `;
        
        // Add summative assessment section
        coCheckboxesHtml += `
            <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer;">
                    <input type="checkbox" id="is-summative-checkbox-edit" ${currentIsSummative ? 'checked' : ''} style="cursor: pointer;" onchange="togglePerformanceTargetEdit()">
                    <span style="font-weight: 600; color: #1f2937;">
                        <i class="fas fa-graduation-cap" style="color: #3b82f6;"></i>
                        Mark as Summative Assessment
                    </span>
                </label>
                <div id="performance-target-section-edit" style="display: ${currentIsSummative ? 'block' : 'none'}; margin-left: 24px;">
                    <label class="fgs-form-label" style="font-size: 13px;">Performance Target (%)</label>
                    <input type="number" id="performance-target-input-edit" class="fgs-form-input" min="0" max="100" value="${currentPerformanceTarget}" step="0.01" style="width: 120px;">
                    <small class="fgs-form-hint">Minimum percentage to be considered successful</small>
                </div>
            </div>
        `;
    }

    const html = `
        <div id="edit-col-modal" class="fgs-modal-overlay">
            <div class="fgs-modal" style="max-width: 600px;">
                <div class="fgs-modal-header">
                    <h3 class="fgs-modal-title">Edit Item</h3>
                    <button onclick="closeModal('edit-col-modal')" class="fgs-modal-close">&times;</button>
                </div>
                <form onsubmit="return submitEditCol(event,${id})">
                    <div class="fgs-modal-body" style="max-height: 60vh; overflow-y: auto;">
                        <div class="fgs-form-group">
                            <label class="fgs-form-label">Item Name *</label>
                            <input type="text" id="edit-col-name" class="fgs-form-input" required value="${name}">
                        </div>
                        <div class="fgs-form-group">
                            <label class="fgs-form-label">Max Score *</label>
                            <input type="number" id="edit-col-max" class="fgs-form-input" required min="1" value="${max}">
                        </div>
                        ${coCheckboxesHtml}
                    </div>
                    <div class="fgs-modal-actions">
                        <button type="button" onclick="closeModal('edit-col-modal')" class="fgs-modal-btn fgs-modal-btn-cancel">Cancel</button>
                        <button type="submit" class="fgs-modal-btn fgs-modal-btn-update">Update</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', html);
}

async function submitEditCol(e, id) {
    e.preventDefault();
    const name = document.getElementById('edit-col-name').value.trim();
    const max = document.getElementById('edit-col-max').value;

    // Get CO mappings
    const coCheckboxes = document.querySelectorAll('input[name="co_mapping_edit[]"]:checked');
    const coMappings = Array.from(coCheckboxes).map(cb => cb.value);
    
    // Get summative assessment data
    const isSummative = document.getElementById('is-summative-checkbox-edit')?.checked ? 'yes' : 'no';
    const performanceTarget = document.getElementById('performance-target-input-edit')?.value || 60;

    const fd = new FormData();
    fd.append('action', 'update_column_full');
    fd.append('column_id', id);
    fd.append('column_name', name);
    fd.append('max_score', max);
    fd.append('co_mappings', JSON.stringify(coMappings));
    fd.append('is_summative', isSummative);
    fd.append('performance_target', performanceTarget);
    fd.append('csrf_token', window.csrfToken || APP.csrfToken);

    const res = await fetch(APP.apiPath + 'manage_grading_components.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.success) {
        toast('success', 'Item updated successfully!');
        closeModal('edit-col-modal');
        await loadComponents(FGS.currentClassCode, FGS.currentTermType);
        renderUI();
    } else {
        toast('error', data.message);
    }
    return false;
}

async function delColumn(id, name) {
    if (!confirm(`Delete "${name}"?\n\nThis will delete all grades for this item.`)) return;

    const fd = new FormData();
    fd.append('action', 'delete_column');
    fd.append('column_id', id);
    fd.append('csrf_token', window.csrfToken || APP.csrfToken);

    const res = await fetch(APP.apiPath + 'manage_grading_components.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.success) {
        toast('success', 'Item deleted successfully!');
        await loadComponents(FGS.currentClassCode, FGS.currentTermType);
        renderUI();
    } else {
        toast('error', data.message);
    }
}

function toggleSelectAllColumns(checkbox) {
    const colCheckboxes = document.querySelectorAll('input.fgs-col-checkbox');
    colCheckboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
    updateBulkDeleteBtn();
}

function updateBulkDeleteBtn() {
    const colCheckboxes = document.querySelectorAll('input.fgs-col-checkbox:checked');
    const bulkBtn = document.getElementById('bulk-delete-items-btn');
    if (bulkBtn) {
        bulkBtn.style.display = colCheckboxes.length > 0 ? 'block' : 'none';
    }
}

async function bulkDeleteColumns() {
    const colCheckboxes = document.querySelectorAll('input.fgs-col-checkbox:checked');
    if (colCheckboxes.length === 0) {
        toast('warning', 'Please select items to delete');
        return;
    }

    const columnIds = Array.from(colCheckboxes).map(cb => cb.getAttribute('data-col-id'));
    const itemCount = columnIds.length;
    
    if (!confirm(`Delete ${itemCount} item${itemCount > 1 ? 's' : ''}?\n\nThis will permanently delete all grades for these items.`)) return;

    const fd = new FormData();
    fd.append('action', 'delete_columns_bulk');
    fd.append('column_ids', JSON.stringify(columnIds));
    fd.append('csrf_token', window.csrfToken || APP.csrfToken);

    const bulkBtn = document.getElementById('bulk-delete-items-btn');
    if (bulkBtn) {
        const originalText = bulkBtn.innerHTML;
        bulkBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
        bulkBtn.disabled = true;
    }

    try {
        const res = await fetch(APP.apiPath + 'manage_grading_components.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            toast('success', `Deleted ${itemCount} item${itemCount > 1 ? 's' : ''}!`);
            await loadComponents(FGS.currentClassCode, FGS.currentTermType);
            renderUI();
        } else {
            toast('error', data.message);
            if (bulkBtn) {
                bulkBtn.innerHTML = originalText;
                bulkBtn.disabled = false;
            }
        }
    } catch (error) {
        console.error('Bulk delete error:', error);
        toast('error', 'Failed to delete items');
        if (bulkBtn) {
            bulkBtn.innerHTML = originalText;
            bulkBtn.disabled = false;
        }
    }
}

function closeModal(id) {
    const m = document.getElementById(id);
    if (m) m.remove();
}

async function renderSummary() {
    const container = document.getElementById('summary-grades-container');
    if (!container) {
        console.warn('Summary container not found');
        return;
    }
    
    if (!FGS.students || FGS.students.length === 0) {
        container.innerHTML = `
            <div class="fgs-empty-state">
                <i class="fas fa-chart-bar fgs-empty-icon"></i>
                <h3 class="fgs-empty-title">No Students Loaded</h3>
                <p class="fgs-empty-description">Load students first to see the summary</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = `
        <div class="fgs-loading-container">
            <i class="fas fa-spinner fa-spin fgs-loading-icon"></i>
            <p class="fgs-loading-text">Calculating grades...</p>
        </div>
    `;
    
    try {
        const midtermData = await loadTermData('midterm');
        const finalsData = await loadTermData('finals');
        
        const statusesData = await loadGradeStatuses();
        let html = `
            <div class="fgs-summary-container">
                <div class="fgs-summary-header">
                    <h3 class="fgs-summary-title">Grade Summary</h3>
                    <p class="fgs-summary-subtitle">Midterm: ${FGS.midtermWeight}% | Finals: ${FGS.finalsWeight}%</p>
                </div>
                <div class="fgs-summary-scroll">
                    <table class="fgs-summary-table">
                        <thead>
    <tr>
        <th class="student-col">Student</th>
        <th class="midterm-col">Midterm<br><small>(${FGS.midtermWeight}%)</small></th>
        <th class="finals-col">Finals<br><small>(${FGS.finalsWeight}%)</small></th>
        <th class="term-col">Term Grade</th>
        <th class="status-col">Status</th>
        <th class="actions-col">Actions</th>
    </tr>
</thead>
                        <tbody>
        `;
        
        FGS.students.forEach((student, idx) => {
            const midtermPct = calculateTermGrade(student.student_id, midtermData);
            const finalsPct = calculateTermGrade(student.student_id, finalsData);
            const termPct = (midtermPct * (FGS.midtermWeight / 100)) + (finalsPct * (FGS.finalsWeight / 100));
            
            const midtermGrade = toGrade(midtermPct);
            const finalsGrade = toGrade(finalsPct);
            const termGrade = toGrade(termPct);
            
            // Determine status based on term percentage (60% and above = passed, below 60% = failed)
            // Faculty can manually override by changing status to 'incomplete' if they give special exam/removals
            let gradeStatus = statusesData[student.student_id] || (termPct >= 60 ? 'passed' : 'failed');
            // Hard correction: never allow 'passed' below 60% unless manually frozen (will be handled by server flags later)
            if (gradeStatus === 'passed' && termPct < 60) {
                gradeStatus = (termPct < 57 ? 'failed' : 'incomplete');
            }
            // Status badge colors
const statusColors = {
    'passed': '#10b981',
    'failed': '#ef4444',
    'incomplete': '#f59e0b',
    'dropped': '#6b7280'
};

const statusLabels = {
    'passed': 'Passed',
    'failed': 'Failed',
    'incomplete': 'INC',
    'dropped': 'DRP'
};

html += `
    <tr>
        <td class="student-col">
            <div class="fgs-student-cell">
                <div class="fgs-student-avatar">${student.name.charAt(0)}</div>
                <div>
                    <div class="fgs-student-name">${student.name}</div>
                    <div class="fgs-student-id">${student.student_id}</div>
                </div>
            </div>
        </td>
        <td class="midterm-col">
            <div class="fgs-summary-percentage midterm">${midtermPct.toFixed(2)}%</div>
            <div class="fgs-summary-grade-badge" style="background:${gradeColor(midtermGrade)};">
                ${midtermGrade.toFixed(1)}
            </div>
        </td>
        <td class="finals-col">
            <div class="fgs-summary-percentage finals">${finalsPct.toFixed(2)}%</div>
            <div class="fgs-summary-grade-badge" style="background:${gradeColor(finalsGrade)};">
                ${finalsGrade.toFixed(1)}
            </div>
        </td>
        <td class="term-col">
            <div class="fgs-summary-percentage term">${termPct.toFixed(2)}%</div>
            <div class="fgs-summary-grade-badge term" style="background:${gradeColor(termGrade)};">
                ${termGrade.toFixed(1)}
            </div>
        </td>
        <td class="status-col">
            <span class="fgs-status-badge" id="status-badge-${student.student_id}" style="background:${statusColors[gradeStatus]}; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;" data-term-pct="${termPct.toFixed(2)}" data-original-status="${statusesData[student.student_id]||''}">
                ${statusLabels[gradeStatus]}
            </span>
        </td>
        <td class="actions-col">
            <button onclick="changeGradeStatus('${student.student_id}', '${student.name}')" class="btn btn-sm btn-outline" style="padding: 4px 8px; font-size: 12px;">
                <i class="fas fa-edit"></i> Change Status
            </button>
        </td>
    </tr>
`;
        });
        
html += '</tbody></table></div></div>';
        container.innerHTML = html;
        
    } catch (error) {
        console.error('Error rendering summary:', error);
        container.innerHTML = `
            <div class="fgs-error-state">
                <i class="fas fa-exclamation-circle fgs-error-icon"></i>
                <h3 class="fgs-error-title">Error Loading Summary</h3>
                <p class="fgs-error-message">${error.message}</p>
            </div>
        `;
    }
}

async function loadTermData(termType) {
    const fd = new FormData();
    fd.append('action', 'get_components');
    fd.append('class_code', FGS.currentClassCode);
    fd.append('term_type', termType);
    fd.append('csrf_token', window.csrfToken || APP.csrfToken);

    try {
        const res = await fetch(APP.apiPath + 'manage_grading_components.php', { method: 'POST', body: fd });
        const data = await res.json();
        
        if (!data.success) {
            console.warn(`Failed to load ${termType} components:`, data.message);
            return { components: [], grades: {} };
        }
        
        const components = data.components || [];
        const allGrades = {};
        
        for (const comp of components) {
            try {
                const grades = await loadGradesForComponent(comp.id);
                Object.assign(allGrades, grades);
            } catch (e) {
                console.error(`Error loading grades for component ${comp.id}:`, e);
            }
        }
        
        return { components, grades: allGrades };
    } catch (e) {
        console.error(`Error loading ${termType} data:`, e);
        return { components: [], grades: {} };
    }
}

async function loadGradesForComponent(componentId) {
    const fd = new FormData();
    fd.append('action', 'get_grades');
    fd.append('class_code', FGS.currentClassCode);
    fd.append('component_id', componentId);
    fd.append('csrf_token', window.csrfToken || APP.csrfToken);

    try {
        const res = await fetch(APP.apiPath + 'process_grades.php', { method: 'POST', body: fd });
        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }
        const data = await res.json();
        return data.success ? (data.grades || {}) : {};
    } catch (e) {
        console.error(`Error fetching grades for component ${componentId}:`, e);
        return {};
    }
}

function calculateTermGrade(studentId, termData) {
    const { components, grades } = termData;
    
    console.log(` Calculating for student ${studentId}`);
    console.log(' Components:', components);
    console.log(' Grades available:', grades);
    
    let totalWeightedScore = 0;
    let totalWeight = 0;
    
    components.forEach(comp => {
        const columns = comp.columns || [];
        let compScore = 0;
        let compMax = 0;
        
        console.log(` Component: ${comp.component_name} (${comp.percentage}%)`);
        console.log(' Columns:', columns);
        
        columns.forEach(col => {
            const key = `${studentId}_${col.id}`;
            const grade = grades[key] || {};
            const score = parseFloat(grade.score) || 0;
            
            console.log(` Key: ${key}, Grade:`, grade, 'Score:', score);
            
            if (score > 0) {
                compScore += score;
                compMax += parseFloat(col.max_score);
            }
        });
        
        if (compMax > 0) {
            const compPct = (compScore / compMax) * 100;
            const weighted = compPct * (parseFloat(comp.percentage) / 100);
            console.log(`🔍 Component ${comp.component_name}: ${compPct.toFixed(2)}% → Weighted: ${weighted.toFixed(2)}`);
            totalWeightedScore += weighted;
            totalWeight += parseFloat(comp.percentage);
        }
    });
    
    const finalGrade = totalWeight > 0 ? (totalWeightedScore / totalWeight) * 100 : 0;
    console.log(`🔍 Final: ${finalGrade.toFixed(2)}% (Total Weight: ${totalWeight}%)`);
    
    return finalGrade;
}
/**
 * Load grade statuses from database
 */
async function loadGradeStatuses() {
    const fd = new FormData();
    fd.append('action', 'get_grade_statuses');
    fd.append('class_code', FGS.currentClassCode);
    fd.append('csrf_token', window.csrfToken || APP.csrfToken);

    try {
        const res = await fetch(APP.apiPath + 'save_term_grades.php', { method: 'POST', body: fd });
        
        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }
        
        // Check if response is actually JSON
        const contentType = res.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            console.warn('Response is not JSON, received:', contentType);
            FGS.gradeStatuses = {};
            return {};
        }
        
        // Get response text first to debug
        const text = await res.text();
        if (!text || text.trim() === '') {
            console.warn('Empty response from loadGradeStatuses');
            FGS.gradeStatuses = {};
            return {};
        }
        
        // Try to parse JSON
        const data = JSON.parse(text);
        
        if (data.success) {
            const statuses = data.statuses || {};
            FGS.gradeStatuses = statuses;  // Store in global FGS object
            return statuses;
        }
        
        FGS.gradeStatuses = {};
        return {};
    } catch (e) {
        console.error('Error loading grade statuses:', e);
        FGS.gradeStatuses = {};
        return {};
    }
}
/**
 * Save Term Grades to Database
 */
async function saveTermGradesToDatabase() {
    if (!FGS.currentClassCode || !FGS.students || FGS.students.length === 0) {
        console.warn('Cannot save: No class or students loaded');
        return;
    }
    
    try {
        // Calculate grades for all students
        const midtermData = await loadTermData('midterm');
        const finalsData = await loadTermData('finals');
        
        const gradesData = FGS.students.map(student => {
            const midtermPct = calculateTermGrade(student.student_id, midtermData);
            const finalsPct = calculateTermGrade(student.student_id, finalsData);
            const termPct = (midtermPct * (FGS.midtermWeight / 100)) + (finalsPct * (FGS.finalsWeight / 100));
            const termGrade = toGrade(termPct);
            
            // Auto-calculate status based on term percentage (only if not manually set)
            // 60% and above = passed, below 60% = failed
            const autoStatus = (termPct >= 60) ? 'passed' : 'failed';
            
            return {
                student_id: student.student_id,
                midterm_percentage: midtermPct,
                finals_percentage: finalsPct,
                term_percentage: termPct,
                term_grade: termGrade,
                grade_status: autoStatus  // Include the calculated status
            };
        });
        
        // Send to API
        const response = await fetch(APP.apiPath + 'save_term_grades.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                class_code: FGS.currentClassCode,
                grades: gradesData
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            console.log(`✓ Saved ${data.saved_count} term grades to database`);
            return true;
        } else {
            console.error('Failed to save term grades:', data.message);
            return false;
        }
        
    } catch (error) {
        console.error('Error saving term grades:', error);
        return false;
    }
}
/**
 * Change Grade Status (INC/DRP/etc.)
 */
async function changeGradeStatus(studentId, studentName, termPctParam) {
    // Get current grades from the summary table
    const row = document.querySelector(`#status-badge-${studentId}`)?.closest('tr');
    let termGrade = 0;
    let termPct = termPctParam || 0;  // Use parameter if provided, otherwise try to extract
    let midtermPct = 0;
    let finalsPct = 0;
    
    if (row && !termPctParam) {
        // Extract percentages and grades from the row (if not provided as parameter)
        const cells = row.querySelectorAll('td');
        midtermPct = parseFloat(cells[1]?.querySelector('.fgs-summary-percentage')?.textContent) || 0;
        finalsPct = parseFloat(cells[2]?.querySelector('.fgs-summary-percentage')?.textContent) || 0;
        termPct = parseFloat(cells[3]?.querySelector('.fgs-summary-percentage')?.textContent) || 0;
        termGrade = parseFloat(cells[3]?.querySelector('.fgs-summary-grade-badge')?.textContent) || 0;
    } else if (termPctParam) {
        // If called from midterm tab, use the parameter and estimate grades
        termPct = termPctParam;
        termGrade = toGrade(termPct);  // Convert percentage to grade
    }
    
    // Get current status from badge
    const currentStatusBadge = document.getElementById(`status-badge-${studentId}`);
// Load existing status and lacking_requirements from database
let currentStatus = 'passed';
let existingLackingReqs = '';

// Get from database
const statusesData = await loadGradeStatuses();
if (statusesData[studentId]) {
    currentStatus = statusesData[studentId];
}

// Get lacking requirements
try {
    const lackingReqsResponse = await loadLackingRequirements(studentId);
    existingLackingReqs = lackingReqsResponse || '';
    console.log('Loaded lacking reqs:', existingLackingReqs); 
} catch (error) {
    console.error('Error loading lacking reqs:', error);
}

// Load grades for all components so we can check which columns have 0 grades
console.log('📚 Loading grades for all components...');
if (FGS.components && FGS.components.length > 0) {
    for (const comp of FGS.components) {
        await loadGrades(FGS.currentClassCode, comp.id);
    }
}

// Get components with 0 grades for this student
const componentsWith0Grade = [];
if (FGS.components && FGS.components.length > 0 && FGS.grades) {
    console.log('🔍 Checking components for zero grades - studentId:', studentId);
    console.log('   Available grades keys:', Object.keys(FGS.grades).slice(0, 5));
    
    FGS.components.forEach(comp => {
        if (!comp.columns || comp.columns.length === 0) return;
        
        // Check EACH column individually for 0 grades
        comp.columns.forEach(col => {
            // Grades are keyed as "student_columnId"
            const gradeKey = `${studentId}_${col.id}`;
            const gradeEntry = FGS.grades[gradeKey];
            
            if (gradeEntry !== undefined) {
                const gradeValue = parseFloat(gradeEntry.score) || 0;
                console.log(`   Column ${col.column_name} (${col.id}): grade=${gradeValue}, key=${gradeKey}`);
                
                if (gradeValue === 0) {
                    console.log(`✓ Column "${col.column_name}" has 0 grade - ADDING TO LIST`);
                    // Add the column object directly (not the component)
                    componentsWith0Grade.push({
                        id: col.id,
                        name: col.column_name,
                        displayName: col.column_name
                    });
                }
            }
        });
    });
}
console.log('📋 Final columns with 0 grade:', componentsWith0Grade.map(c => c.displayName));

let suggestedStatus = currentStatus;
if (currentStatus === 'passed' && termGrade < 1.0) {
    suggestedStatus = 'incomplete';
}
    
    Swal.fire({
        title: `<div style="color: #FDB81E; font-size: 24px; font-weight: 700;">Change Status for ${studentName}</div>`,
        html: `
            <div style="padding: 0 20px 20px 20px;">
                
                <!-- Grade Summary Cards -->
                <div style="background: #003082; padding: 18px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(0,48,130,0.15);">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; align-items: stretch;">
                        <div style="background: rgba(253,184,30,0.15); padding: 12px 10px; border-radius: 8px; border: 2px solid #FDB81E;">
                            <div style="color: #FDB81E; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px;">Midterm</div>
                            <div style="color: white; font-size: 22px; font-weight: 800; line-height: 1;" data-midterm-pct="${midtermPct}">${midtermPct.toFixed(1)}%</div>
                        </div>
                        <div style="background: #FDB81E; padding: 12px 10px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                            <div style="flex: 1; min-width: 0;">
                                <div style="color: #003082; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">Grade</div>
                                <div style="color: #003082; font-size: 22px; font-weight: 800; line-height: 1;">${termGrade.toFixed(1)}</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Grade Status Section -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 10px; font-weight: 700; color: #FDB81E; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Grade Status</label>
                    <select id="swal-status-select" style="width: 100%; padding: 14px 16px; font-size: 15px; border: 2px solid #FDB81E !important; border-radius: 8px; background: white; color: #1f2937; font-weight: 600; cursor: pointer; box-sizing: border-box; outline: none;" onchange="toggleLackingRequirements(); toggleComponentDropdown(${midtermPct})">
                        <option value="passed" ${suggestedStatus === 'passed' ? 'selected' : ''}>✓ Passed</option>
                        <option value="failed" ${suggestedStatus === 'failed' ? 'selected' : ''}>✗ Failed (0.0) - No Removals</option>
                        <option value="incomplete" ${suggestedStatus === 'incomplete' ? 'selected' : ''}>⚠ Incomplete (INC) - Can Take Removals</option>
                        <option value="dropped" ${suggestedStatus === 'dropped' ? 'selected' : ''}>⊗ Dropped (DRP)</option>
                    </select>
                </div>
                
                <!-- Component Selection Section (Show only for INC with 0 grade) -->
                <div id="component-selection-section" style="display: ${(suggestedStatus === 'incomplete' && midtermPct === 0) ? 'block' : 'none'}; margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 10px; font-weight: 700; color: #FDB81E; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Missing Component (Optional)</label>
                    <select id="swal-component-select" style="width: 100%; padding: 14px 16px; font-size: 15px; border: 2px solid #FDB81E !important; border-radius: 8px; background: white; color: #1f2937; font-weight: 600; cursor: pointer; box-sizing: border-box; outline: none;">
                        <option value="">-- Select a component (or leave blank) --</option>
                        ${componentsWith0Grade.map(col => `<option value="${col.displayName}">${col.displayName}</option>`).join('')}
                    </select>
                </div>
                
                <!-- Lacking Requirements Section -->
                <div id="lacking-requirements-section" style="display: ${suggestedStatus !== 'passed' ? 'block' : 'none'};">
                    <label style="display: block; margin-bottom: 10px; font-weight: 700; color: #FDB81E; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Lacking Requirements / Reason</label>
                    <textarea id="swal-lacking-requirements" style="width: 100%; padding: 14px 16px; font-size: 14px; border: 2px solid #FDB81E !important; border-radius: 8px; font-family: inherit; resize: none; height: 80px; line-height: 1.5; color: #1f2937; background: white; box-sizing: border-box; outline: none;" placeholder="e.g., Missing final exam, needs to submit project">${existingLackingReqs}</textarea>
                </div>
                
            </div>
        `,
        width: '750px',
        heightAuto: true,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-check-circle"></i> Update Status',
        cancelButtonText: '<i class="fas fa-times-circle"></i> Cancel',
        confirmButtonColor: '#003082',
        cancelButtonColor: '#6b7280',
        buttonsStyling: true,
        customClass: {
            popup: 'swal-wide',
            confirmButton: 'swal-confirm-btn',
            cancelButton: 'swal-cancel-btn'
        },
        didOpen: () => {
            // Force override SweetAlert2's default styles
            const select = document.getElementById('swal-status-select');
            const textarea = document.getElementById('swal-lacking-requirements');
            
            if (select) {
                select.style.border = '2px solid #003082';
                select.style.boxShadow = 'none';
            }
            if (textarea) {
                textarea.style.border = '2px solid #003082';
                textarea.style.boxShadow = 'none';
            }
        },
        preConfirm: () => {
            const status = document.getElementById('swal-status-select').value;
            const lackingReqs = document.getElementById('swal-lacking-requirements').value.trim();
            const selectedComponent = document.getElementById('swal-component-select')?.value || '';
            
            if (!status) {
                Swal.showValidationMessage('Please select a status');
                return false;
            }
            
            return { status, lackingReqs, selectedComponent };
        }
    }).then(async (result) => {
        if (result.isConfirmed) {
            await updateGradeStatus(studentId, result.value.status, studentName, result.value.lackingReqs, result.value.selectedComponent);
        }
    });
}

// UPDATED: Add lackingReqs and selectedComponent parameters
async function updateGradeStatus(studentId, status, studentName, lackingReqs, selectedComponent) {
    const fd = new FormData();
    fd.append('action', 'update_grade_status');
    fd.append('class_code', FGS.currentClassCode);
    fd.append('student_id', studentId);
    fd.append('grade_status', status);
    fd.append('lacking_requirements', lackingReqs || '');
    fd.append('missing_component', selectedComponent || '');
    fd.append('csrf_token', window.csrfToken || APP.csrfToken);
    
    try {
        const response = await fetch(APP.apiPath + 'save_term_grades.php', {
            method: 'POST',
            body: fd
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Update in-memory cache
            if (!FGS.gradeStatuses) FGS.gradeStatuses = {};
            FGS.gradeStatuses[studentId] = status;
            
            // Update UI immediately
            const statusBadge = document.getElementById(`status-badge-${studentId}`);
            if (statusBadge) {
                const statusColors = {
                    'passed': '#10b981',
                    'failed': '#ef4444',
                    'incomplete': '#f59e0b',
                    'dropped': '#6b7280'
                };
                const statusLabels = {
                    'passed': 'Passed',
                    'failed': 'Failed',
                    'incomplete': 'INC',
                    'dropped': 'DRP'
                };
                
                statusBadge.style.background = statusColors[status];
                statusBadge.textContent = statusLabels[status];
            }
            // Trigger fresh server authoritative recompute for full consistency
            if (typeof renderSummary === 'function') {
                renderSummary();
            }
            toast('success', `Status updated for ${studentName}`);
        } else {
            toast('error', data.message || 'Failed to update status');
        }
    } catch (error) {
        console.error('Error updating status:', error);
        toast('error', 'Failed to update status');
    }
}

/**
 * Update TOTAL % for a student row in real-time (no re-render needed)
 */
function updateStudentRowTotal(studentId) {
    // Determine which tab is active and get the correct container
    const isMidterm = FGS.currentTermType === 'midterm';
    const containerId = isMidterm ? 'flexible-table-container' : 'flexible-table-container-finals';
    const container = document.getElementById(containerId);
    
    if (!container) {
        console.warn(`Container ${containerId} not found`);
        return;
    }
    
    // Find the table within the container
    const table = container.querySelector('.fgs-summary-table tbody');
    if (!table) {
        console.warn(`Table tbody not found in ${containerId}`);
        return;
    }
    
    // Find the student's row
    const rows = table.querySelectorAll('tr');
    let studentRow = null;
    rows.forEach(row => {
        const idCell = row.querySelector('.fgs-student-id');
        if (idCell && idCell.textContent === studentId) {
            studentRow = row;
        }
    });
    
    if (!studentRow) {
        console.warn(`Student row for ${studentId} not found`);
        return;
    }
    
    // Get all input fields in this row
    const inputs = studentRow.querySelectorAll('.fgs-score-input');
    let totalScore = 0;
    let totalMax = 0;
    
    inputs.forEach(input => {
        const colId = input.getAttribute('data-column-id');
        const comp = FGS.components.find(c => c.id === FGS.currentComponentId);
        const col = comp?.columns?.find(c => c.id == colId);
        
        if (col) {
            const value = parseFloat(input.value) || 0;
            totalScore += value;
            totalMax += parseFloat(col.max_score);
        }
    });
    
    const totalPct = totalMax > 0 ? (totalScore / totalMax) * 100 : 0;
    
    // Update the TOTAL % cell (last cell in row)
    const totalCell = studentRow.querySelector('td:last-child');
    if (totalCell) {
        totalCell.textContent = totalPct.toFixed(1) + '%';
        totalCell.style.background = 'linear-gradient(180deg,#fffdf6 0%,#fff9e7 100%)';
        
        // Flash effect to show update
        totalCell.style.transition = 'all 0.3s ease';
        totalCell.style.transform = 'scale(1.05)';
        setTimeout(() => {
            totalCell.style.transform = 'scale(1)';
        }, 200);
        
        console.log(`✓ Updated TOTAL % for ${studentId}: ${totalPct.toFixed(1)}%`);
    }
}

// Real-time grade cell save (attach to inputs dynamically)
let saveTimeout = null;
async function saveRawScore(inputEl) {
    const columnId = inputEl.getAttribute('data-column-id');
    const studentId = inputEl.getAttribute('data-student-id');
    const classCode = FGS.currentClassCode;
    let raw = inputEl.value.trim();
    // Sanitize inputs: strip any percent sign before sending
    if (typeof raw === 'string' && raw.includes('%')) {
        console.debug(`DEBUG: Stripping '%' from input before sending for ${studentId}_${columnId} -> '${raw}'`);
        raw = raw.replace(/%/g, '').trim();
        // update the visible input to the sanitized value
        inputEl.value = raw;
    }
    if (!columnId || !studentId || !classCode) return;
    
    // Prevent duplicate rapid saves
    const saveKey = `${studentId}_${columnId}_${raw}`;
    if (inputEl.dataset.lastSaved === saveKey) {
        console.log(`⏭️ Skipping duplicate save: ${saveKey}`);
        return;
    }
    
    // Find the column info to show max score
    const comp = FGS.components.find(c => c.id === FGS.currentComponentId);
    const col = comp?.columns?.find(c => c.id == columnId);
    console.log(`💾 Column: ${col?.column_name || 'UNKNOWN'} / ${col?.max_score || '?'}`);
    
    // UPDATE TOTAL % IMMEDIATELY (before server save)
    updateStudentRowTotal(studentId);
    
    const fd = new FormData();
    fd.append('column_id', columnId);
    fd.append('grade', raw === '' ? 0 : raw);
    fd.append('student_id', studentId);
    fd.append('class_code', classCode);
    
    const token = window.csrfToken || window.csrf_token || APP?.csrfToken || document.querySelector('input[name="csrf_token"]')?.value || document.querySelector('meta[name="csrf_token"]')?.content || '';
    if (token) {
        fd.append('csrf_token', token);
    }
    
    try {
        console.log(`💾 Sending to server: grade='${raw}', student=${studentId}, column=${columnId}`);
        if (typeof raw === 'string' && raw.includes('%')) {
            console.debug(`DEBUG: Input contains '%' before sending for ${studentId}_${columnId} -> '${raw}'`);
        }
        const res = await fetch('/ajax/update_grade.php', { method:'POST', body: fd });
        if (!res.ok) {
            console.error(`💾 HTTP Error: ${res.status}`);
            return;
        }
        
        const resText = await res.text();
        if (!resText || resText.trim() === '') {
            console.error('💾 Empty response from server');
            return;
        }
        
        let data;
        try {
            data = JSON.parse(resText);
        } catch (parseErr) {
            console.error('💾 JSON Parse Error:', parseErr, 'Response was:', resText);
            return;
        }
        
        console.log(`💾 Response from server:`, data);
        
        if (!data.success) {
            console.error(`💾 Server error: ${data.message}`);
            return;
        }
        
        console.log(`💾 ✅ Save successful!`);
        if (typeof data.saved_raw !== 'undefined') {
            console.debug(`DEBUG: Server saved_raw=${data.saved_raw} for ${studentId}_${columnId}`);
        }
        
        // Mark as saved to prevent duplicates
        inputEl.dataset.lastSaved = saveKey;

        // Update in-memory cache so the table keeps showing the raw score the user entered
        try {
            const cacheKey = `${studentId}_${columnId}`;
            if (!FGS.grades) FGS.grades = {};
            
            // Check if this grade was previously marked as INC
            const wasINC = FGS.grades[cacheKey]?.status === 'inc';
            
            // Use server's saved_raw if present to avoid desync; otherwise fall back to the local input
            const numVal = (typeof data.saved_raw !== 'undefined') ? parseFloat(data.saved_raw) : (raw === '' ? 0 : parseFloat(raw));
            FGS.grades[cacheKey] = Object.assign(FGS.grades[cacheKey] || {}, { score: numVal, status: 'submitted' });

            // Ensure the input shows the saved raw value formatted consistently
            if (Number.isInteger(numVal)) {
                inputEl.value = String(numVal);
            } else {
                inputEl.value = numVal.toFixed(2);
            }

            // Update the row total immediately to reflect the raw value
            updateStudentRowTotal(studentId);
            
            // If this was previously INC and now has a grade, refresh the summary to recalculate status
            if (wasINC && raw !== '' && parseFloat(raw) > 0) {
                console.log('🔄 Previously INC grade now has value, refreshing summary');
                setTimeout(() => {
                    if (typeof loadGradeSummary === 'function') {
                        loadGradeSummary(FGS.currentClassCode);
                    }
                }, 500);
            }
        } catch (err) {
            console.warn('Failed to update local cache after save:', err);
        }
        
        // Silently update summary without animation
        if (data.recomputed && data.recomputed.student_id) {
            // IMPORTANT: Only look for summary table when we're NOT viewing a component
            // If currentComponentId is set, we're in component view, so skip this entirely
            if (!FGS.currentComponentId) {
                const table = document.querySelector('.fgs-summary-table tbody');
                if (table) {
                    const tr = Array.from(table.querySelectorAll('tr')).find(r => r.querySelector('.fgs-student-id') && r.querySelector('.fgs-student-id').textContent === data.recomputed.student_id);
                    if (tr) {
                        const cells = tr.querySelectorAll('td');
                        if (cells && cells.length >= 6) {
                            cells[1].innerHTML = `${data.recomputed.midterm_percentage}%`;
                            cells[2].innerHTML = `${data.recomputed.finals_percentage}%`;
                            cells[3].innerHTML = `${data.recomputed.term_percentage}%`;
                            
                            const gradeBadge = cells[4] ? cells[4].querySelector('.fgs-summary-grade-badge') : null;
                            if (gradeBadge) {
                                gradeBadge.textContent = data.recomputed.term_grade ? data.recomputed.term_grade : '--';
                                gradeBadge.style.background = gradeColor(parseFloat(data.recomputed.term_grade||0));
                            }
                            
                            const statusBadge = cells[5] ? cells[5].querySelector('.fgs-status-badge') : null;
                            if (statusBadge) {
                                statusBadge.dataset.status = data.recomputed.grade_status;
                                statusBadge.textContent = (data.recomputed.grade_status === 'passed' ? 'Passed' : data.recomputed.grade_status === 'failed' ? 'Failed' : data.recomputed.grade_status);
                            }
                        } else {
                            console.warn('Summary table row does not have expected number of cells:', cells ? cells.length : 0);
                        }
                    }
                }
            } else {
                console.debug('Skipping summary update - currently viewing component', FGS.currentComponentId);
            }
        }
    } catch (e) {
        // Silent fail
        console.error('💾 saveRawScore error:', e);
    }
}

function patchSummaryRow(row) {
    const table = document.querySelector('.fgs-summary-table tbody');
    if (!table) return;
    const tr = Array.from(table.querySelectorAll('tr')).find(r => r.querySelector('.fgs-student-id') && r.querySelector('.fgs-student-id').textContent === row.student_id);
    if (!tr) return;
    const cells = tr.querySelectorAll('td');
    
    if (!cells || cells.length < 6) {
        console.warn('Summary table row does not have expected number of cells:', cells ? cells.length : 0);
        return;
    }
    
    // Expected order: Student | Midterm % | Finals % | Term % | Discrete | Status | Freeze
    cells[1].innerHTML = `${row.midterm_percentage}%`; // midterm
    cells[2].innerHTML = `${row.finals_percentage}%`; // finals
    cells[3].innerHTML = `${row.term_percentage}%`; // term
    // Discrete grade
    const gradeBadge = cells[4] ? cells[4].querySelector('.fgs-summary-grade-badge') : null;
    if (gradeBadge) {
        gradeBadge.textContent = row.term_grade ? row.term_grade : '--';
        gradeBadge.style.background = gradeColor(parseFloat(row.term_grade||0));
    }
    // Status badge
    const statusBadge = cells[5] ? cells[5].querySelector('.fgs-status-badge') : null;
    if (statusBadge) {
        statusBadge.dataset.status = row.grade_status;
        statusBadge.textContent = (row.grade_status === 'PASSED' ? 'Passed' : row.grade_status === 'FAILED' ? 'Failed' : row.grade_status);
    }
    // Visual blink highlight
    tr.classList.add('fgs-row-updated');
    setTimeout(()=>tr.classList.remove('fgs-row-updated'),1200);
}

function updateStudentTotals(studentId) {
    // Make sure we have the current component
    if (!FGS.currentComponentId) {
        console.warn('No current component selected');
        return;
    }
    
    const comp = FGS.components.find(c => c.id === FGS.currentComponentId);
    if (!comp) {
        console.warn('Component not found:', FGS.currentComponentId);
        return;
    }
    
    const columns = comp.columns || [];
    
    console.log(`🔄 Updating totals for ${studentId} in component:`, comp.component_name);
    
    // Calculate totals for this student
    let totalScore = 0;
    let totalMax = 0;
    
    columns.forEach(col => {
        const key = `${studentId}_${col.id}`;
        const grade = FGS.grades[key] || {};
        const score = parseFloat(grade.score) || 0;
        
        console.log(`  Column ${col.column_name}: score=${score}, max=${col.max_score}`);
        
        if (score > 0 || score === 0) { // Include 0 scores
            totalScore += score;
            totalMax += parseFloat(col.max_score);
        }
    });
    
    const totalPct = totalMax > 0 ? (totalScore / totalMax * 100) : 0;
    const gradeVal = toGrade(totalPct);
    const gradeClr = gradeColor(gradeVal);
    
    console.log(`  ✓ Total: ${totalScore}/${totalMax} = ${totalPct.toFixed(2)}% = ${gradeVal.toFixed(1)}`);
    
    // Find the student's row in the CURRENT table
    const allInputs = document.querySelectorAll(`input[data-student-id="${studentId}"]`);
    
    allInputs.forEach(input => {
        const row = input.closest('tr');
        if (!row) return;
        
        // Update total cell
        const totalCell = row.querySelector('.total-cell');
        if (totalCell) {
            totalCell.textContent = `${totalPct.toFixed(2)}%`;
            console.log('  ✓ Updated total cell');
        }
        
        // Update grade badge
        const gradeCell = row.querySelector('.grade-cell .fgs-grade-badge');
        if (gradeCell) {
            gradeCell.style.background = gradeClr;
            gradeCell.textContent = gradeVal.toFixed(1);
            console.log('  ✓ Updated grade badge');
        }
    });
}

async function testCOPerformance() {
    if (!FGS.currentClassCode) {
        toast('error', 'Please load a class first');
        return;
    }
    
    try {
        const response = await fetch(`${APP.apiPath}get_co_performance.php?class_code=${FGS.currentClassCode}`);
        const data = await response.json();
        
        console.log('CO Performance Result:', data);
        
        if (data.success) {
            toast('success', `Got CO performance for ${data.co_performance.length} outcomes`);
            console.table(data.co_performance);
        } else {
            toast('error', data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        toast('error', 'Failed to get CO performance');
    }
}
/**
 * Open CAR Generation Modal
 */
async function openCARModal() {
    if (!FGS.currentClassCode) {
        toast('error', 'Please load a class first');
        return;
    }
    
    // Load existing metadata if any
    const metadata = await loadCARMetadata();
    
    const modal = document.getElementById('car-modal');
    if (modal) modal.remove();
    
    const html = `
        <div id="car-modal" class="fgs-modal-overlay">
            <div class="fgs-modal" style="max-width: 900px;">
                <div class="fgs-modal-header">
                    <h3 class="fgs-modal-title">Generate Course Assessment Report (CAR)</h3>
                    <button onclick="closeModal('car-modal')" class="fgs-modal-close">&times;</button>
                </div>
                <div class="fgs-modal-body" style="max-height: 70vh; overflow-y: auto;">
                    
                    <div class="fgs-form-group">
                        <label class="fgs-form-label">Teaching Strategies Employed</label>
                        <textarea id="car-teaching-strategies" class="fgs-form-input" rows="4" placeholder="List and describe teaching strategies used in class...">${metadata.teaching_strategies || ''}</textarea>
                    </div>
                    
                    <div class="fgs-form-group">
                        <label class="fgs-form-label">Intervention/Enrichment Activities</label>
                        <textarea id="car-interventions" class="fgs-form-input" rows="3" placeholder="Describe intervention or enrichment activities...">${metadata.interventions || ''}</textarea>
                    </div>
                    
                    <div class="fgs-form-group">
                        <label class="fgs-form-label">Number of Students Involved in Interventions</label>
                        <input type="number" id="car-intervention-count" class="fgs-form-input" min="0" value="${metadata.intervention_student_count || FGS.students.length}">
                    </div>
                    
                    <div class="fgs-form-group">
                        <label class="fgs-form-label">Problems Encountered</label>
                        <textarea id="car-problems" class="fgs-form-input" rows="3" placeholder="Brief description of problems encountered...">${metadata.problems_encountered || ''}</textarea>
                    </div>
                    
                    <div class="fgs-form-group">
                        <label class="fgs-form-label">Actions Taken</label>
                        <textarea id="car-actions" class="fgs-form-input" rows="3" placeholder="Actions taken to address problems...">${metadata.actions_taken || ''}</textarea>
                    </div>
                    
                    <div class="fgs-form-group">
                        <label class="fgs-form-label">Proposed Actions for Course Improvement</label>
                        <textarea id="car-improvements" class="fgs-form-input" rows="3" placeholder="Proposed improvements for future offerings...">${metadata.proposed_improvements || ''}</textarea>
                    </div>
                    
                </div>
                <div class="fgs-modal-actions">
                    <button onclick="saveCARMetadata()" class="fgs-modal-btn fgs-modal-btn-submit" style="background: #10b981;">
                        <i class="fas fa-save"></i> Save Inputs
                    </button>
                    <button onclick="generateCARDocument()" class="fgs-modal-btn fgs-modal-btn-submit">
                        <i class="fas fa-file-word"></i> Generate CAR Document
                    </button>
                    <button onclick="closeModal('car-modal')" class="fgs-modal-btn fgs-modal-btn-cancel">Cancel</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', html);
}

/**
 * Load existing CAR metadata
 */
async function loadCARMetadata() {
    try {
        const response = await fetch(`${APP.apiPath}get_car_metadata.php?class_code=${FGS.currentClassCode}`);
        const data = await response.json();
        return data.success ? data.metadata : {};
    } catch (error) {
        console.error('Error loading metadata:', error);
        return {};
    }
}

/**
 * Save CAR metadata
 */
async function saveCARMetadata() {
    console.log(' Saving CAR metadata...');
    
    const data = {
        class_code: FGS.currentClassCode,
        teaching_strategies: document.getElementById('car-teaching-strategies')?.value.trim() || '',
        interventions: document.getElementById('car-interventions')?.value.trim() || '',
        intervention_student_count: parseInt(document.getElementById('car-intervention-count')?.value) || 0,
        problems_encountered: document.getElementById('car-problems')?.value.trim() || '',
        actions_taken: document.getElementById('car-actions')?.value.trim() || '',
        proposed_improvements: document.getElementById('car-improvements')?.value.trim() || ''
    };
    
    console.log(' Data to send:', data);
    
    try {
        const response = await fetch((window.APP?.apiPath || '/faculty/ajax/') + 'save_car_metadata.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        console.log(' Response status:', response.status);
        const result = await response.json();
        console.log(' Response:', result);
        
        if (result.success) {
            toast('success', '✓ CAR inputs saved successfully!');
            return true;
        } else {
            toast('error', result.message || 'Failed to save');
            return false;
        }
    } catch (error) {
        console.error(' Error:', error);
        toast('error', 'Failed to save CAR inputs: ' + error.message);
        return false;
    }
}

/**
 * Generate CAR Document with DEBUG
 */
async function generateCARDocument() {
    console.log('🔍 DEBUG: FGS object:', FGS);
    console.log('🔍 DEBUG: currentClassCode:', FGS.currentClassCode);
    
    if (!FGS.currentClassCode) {
        toast('error', 'Please load a class first');
        console.warn('❌ currentClassCode is empty!');
        return;
    }
    
    console.log('✅ Generating CAR for:', FGS.currentClassCode);
    // FORCE REFRESH: Clear cache and reload all data
    FGS.grades = {};
    FGS.components = [];
    await loadComponents(FGS.currentClassCode, 'midterm');
    await loadComponents(FGS.currentClassCode, 'finals');
    console.log('✅ Reloaded components with fresh data');
    
    // Show loading
    Swal.fire({
        title: 'Generating CAR Document...',
        html: 'Please wait while we create your Course Assessment Report.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    try {
        const payload = {
            class_code: FGS.currentClassCode
        };
        
        console.log('📤 Sending payload:', payload);
        
       const response = await fetch((window.APP?.apiPath || '/faculty/ajax/') + 'generate_car_document.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });
        
        console.log('📥 Response status:', response.status);
        
        const text = await response.text();
        console.log('📥 Response text:', text);
        
        const data = JSON.parse(text);
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'CAR Generated!',
                html: `
                    <p>Your Course Assessment Report has been generated successfully.</p>
                    <a href="${data.download_url}" download="${data.filename}" class="btn btn-primary" style="display: inline-block; margin-top: 15px; padding: 10px 20px; background: #003082; color: white; text-decoration: none; border-radius: 6px;">
                        <i class="fas fa-download"></i> Download CAR Document
                    </a>
                `,
                showConfirmButton: false,
                showCloseButton: true
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Generation Failed',
                text: data.message || 'Failed to generate CAR document'
            });
        }
    } catch (error) {
        console.error('❌ Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to generate CAR document: ' + error.message
        });
    }
}
/**
 * Toggle lacking requirements textarea
 */
function toggleLackingRequirements() {
    const status = document.getElementById('swal-status-select').value;
    const section = document.getElementById('lacking-requirements-section');
    
    if (section) {
        section.style.display = status !== 'passed' ? 'block' : 'none';
    }
}

/**
 * Toggle component dropdown visibility
 * Show only when status is "incomplete" and midterm grade is 0
 */
function toggleComponentDropdown(midtermPct) {
    const status = document.getElementById('swal-status-select').value;
    const componentSection = document.getElementById('component-selection-section');
    
    if (componentSection) {
        // Show component dropdown only if status is "incomplete" and midterm percentage is 0
        componentSection.style.display = (status === 'incomplete' && midtermPct === 0) ? 'block' : 'none';
    }
}
/**
 * Load lacking requirements for a student
 */
async function loadLackingRequirements(studentId) {
    const fd = new FormData();
    fd.append('action', 'get_lacking_requirements');
    fd.append('class_code', FGS.currentClassCode);
    fd.append('student_id', studentId);
    fd.append('csrf_token', window.csrfToken || APP.csrfToken);

    try {
        const res = await fetch(APP.apiPath + 'save_term_grades.php', { method: 'POST', body: fd });
        
        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }
        
        // Check if response is actually JSON
        const contentType = res.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            console.warn('Response is not JSON, received:', contentType);
            return '';
        }
        
        // Get response text first to debug
        const text = await res.text();
        if (!text || text.trim() === '') {
            console.warn('Empty response from loadLackingRequirements');
            return '';
        }
        
        // Try to parse JSON
        const data = JSON.parse(text);
        
        if (data.success) {
            return data.lacking_requirements || '';
        }
    } catch (error) {
        console.error('Error loading lacking requirements:', error);
    }
    
    return '';
}
/**
 * Toggle performance target input visibility
 */
function togglePerformanceTarget() {
    const checkbox = document.getElementById('is-summative-checkbox');
    const section = document.getElementById('performance-target-section');
    
    if (section) {
        section.style.display = checkbox && checkbox.checked ? 'block' : 'none';
    }
}
/**
 * Toggle performance target input visibility for bulk add
 */
function togglePerformanceTargetBulk() {
    const checkbox = document.getElementById('is-summative-checkbox-bulk');
    const section = document.getElementById('performance-target-section-bulk');
    
    if (section) {
        section.style.display = checkbox && checkbox.checked ? 'block' : 'none';
    }
}
/**
 * Toggle performance target input visibility for edit modal
 */
function togglePerformanceTargetEdit() {
    const checkbox = document.getElementById('is-summative-checkbox-edit');
    const section = document.getElementById('performance-target-section-edit');
    
    if (section) {
        section.style.display = checkbox && checkbox.checked ? 'block' : 'none';
    }
}
async function exportFlexibleGradesToCSV() {
    if (!FGS || !FGS.students || FGS.students.length === 0) {
        toast('warning', 'No students to export');
        return;
    }

    try {
        console.log('🔄 Auto-saving grades before export...');
        await saveTermGradesToDatabase();
        
        // Wait for save to complete
        await new Promise(resolve => setTimeout(resolve, 500));
        
        let csv = 'Student ID,Name,Midterm %,Midterm Grade,Finals %,Finals Grade,Term %,Term Grade,Status\n';

        // Reload term data for export
        const midtermData = await loadTermData('midterm');
        const finalsData = await loadTermData('finals');
        const statusesData = await loadGradeStatuses();
        
        FGS.students.forEach(student => {
            const studentId = student.student_id;
            const name = (student.name || '').replace(/,/g, ' ').replace(/"/g, '""');
            
            // Calculate percentages
            const midtermPct = calculateTermGrade(studentId, midtermData);
            const finalsPct = calculateTermGrade(studentId, finalsData);
            const termPct = (midtermPct * (FGS.midtermWeight / 100)) + (finalsPct * (FGS.finalsWeight / 100));
            
            // Convert to grades
            const midtermGrade = toGrade(midtermPct);
            const finalsGrade = toGrade(finalsPct);
            const termGrade = toGrade(termPct);
            
            // Get status
            const status = statusesData[studentId] || 'passed';
            
            csv += `"${studentId}","${name}","${midtermPct.toFixed(2)}","${midtermGrade.toFixed(1)}","${finalsPct.toFixed(2)}","${finalsGrade.toFixed(1)}","${termPct.toFixed(2)}","${termGrade.toFixed(1)}","${status}"\n`;
        });
        
        // Download
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        link.setAttribute('href', url);
        link.setAttribute('download', `Grades_${FGS.currentClassCode}_${new Date().toISOString().split('T')[0]}.csv`);
        link.style.visibility = 'hidden';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        toast('success', `✓ Exported ${FGS.students.length} students`);
        
    } catch (error) {
        console.error('Export error:', error);
        toast('error', 'Export failed: ' + error.message);
    }
}

window.exportFlexibleGradesToCSV = exportFlexibleGradesToCSV;
window.updateStudentRowTotal = updateStudentRowTotal;
window.togglePerformanceTargetEdit = togglePerformanceTargetEdit;
window.togglePerformanceTargetBulk = togglePerformanceTargetBulk;
window.togglePerformanceTarget = togglePerformanceTarget;
window.loadLackingRequirements = loadLackingRequirements;
window.toggleLackingRequirements = toggleLackingRequirements;
window.toggleComponentDropdown = toggleComponentDropdown;
window.openCARModal = openCARModal;
window.saveCARMetadata = saveCARMetadata;
window.generateCARDocument = generateCARDocument;
window.testCOPerformance = testCOPerformance;
window.updateStudentTotals = updateStudentTotals;
window.changeGradeStatus = changeGradeStatus;
window.updateGradeStatus = updateGradeStatus;
window.initGrading = initGrading;

// Switch component and reload grading UI
function switchComponent(componentId) {
    FGS.currentComponentId = componentId;
    // Load grades for this component before rendering
    loadGrades(FGS.currentClassCode, componentId).then(() => {
        renderUI();
    });
}

// Switch term (midterm/finals) and reload components
function switchTerm(termType) {
    FGS.currentTermType = termType;
    loadComponents(FGS.currentClassCode, termType).then(() => {
        if (FGS.components.length > 0) {
            FGS.currentComponentId = FGS.components[0].id;
            // Load grades for first component in new term
            return loadGrades(FGS.currentClassCode, FGS.currentComponentId);
        }
    }).then(() => {
        renderUI();
    });
}

window.switchComponent = switchComponent;
window.switchTerm = switchTerm;
window.saveGrade = saveRawScore;  // Alias for backward compatibility
window.toggleEdit = toggleEdit;

// Toggle edit mode
function toggleEdit() {
    FGS.editMode = !FGS.editMode;
    renderUI();
}
window.validateInput = validateInput;
window.handleGradeInputKeydown = handleGradeInputKeydown;
window.addComponentModal = addComponentModal;
window.submitAddComp = submitAddComp;
window.editComponent = editComponent;
window.submitEditComp = submitEditComp;
window.delComponent = delComponent;
window.addColumnModal = addColumnModal;
window.submitAddCol = submitAddCol;
window.editColumn = editColumn;
window.submitEditCol = submitEditCol;
window.delColumn = delColumn;
window.toggleSelectAllColumns = toggleSelectAllColumns;
window.updateBulkDeleteBtn = updateBulkDeleteBtn;
window.bulkDeleteColumns = bulkDeleteColumns;
window.closeModal = closeModal;
window.renderSummary = renderSummary;
window.bulkAddColumnModal = bulkAddColumnModal;
window.updateBulkPreview = updateBulkPreview;
window.submitBulkAddCol = submitBulkAddCol;
window.saveTermGradesToDatabase = saveTermGradesToDatabase;

/**
 * Show context menu to mark component as INC
 */
function showComponentContextMenu(event, studentId, columnId) {
    event.preventDefault();
    
    // Remove existing menu if present
    const existing = document.getElementById('component-context-menu');
    if (existing) existing.remove();
    
    // Create context menu
    const menu = document.createElement('div');
    menu.id = 'component-context-menu';
    menu.style.cssText = `
        position: fixed;
        top: ${event.clientY}px;
        left: ${event.clientX}px;
        background: white;
        border: 1px solid #ccc;
        border-radius: 6px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        z-index: 10000;
        min-width: 200px;
    `;
    
    menu.innerHTML = `
        <div style="padding: 8px 0;">
            <div style="padding: 10px 16px; cursor: pointer; hover:background: #f0f0f0;" onmouseover="this.style.background='#f0f0f0'" onmouseout="this.style.background='transparent'" onclick="markComponentStatus(event, '${studentId}', ${columnId}, 'inc'); this.closest('#component-context-menu').remove();">
                <i class="fas fa-times-circle" style="color: #dc2626; margin-right: 8px;"></i>
                <strong>Mark as INC</strong>
            </div>
        </div>
    `;
    
    document.body.appendChild(menu);
    
    // Close menu when clicking elsewhere
    setTimeout(() => {
        document.addEventListener('click', function closeMenu(e) {
            if (!menu.contains(e.target)) {
                menu.remove();
                document.removeEventListener('click', closeMenu);
            }
        });
    }, 100);
}

/**
 * Mark a component as INC or submitted
 */
async function markComponentStatus(event, studentId, columnId, status) {
    event.preventDefault();
    
    // If marking as INC, prompt for lacking requirements
    if (status === 'inc') {
        // Find the item name from the table - simpler approach
        let itemName = 'Component item';
        
        // Get all table headers within the grading table (skip first which is "Student")
        const gradingTable = document.querySelector('table.fgs-summary-table');
        if (gradingTable) {
            const headers = gradingTable.querySelectorAll('thead th');
            const gradeInputs = gradingTable.querySelectorAll('input.fgs-score-input');
            
            // Find which column this input belongs to by checking the first occurrence
            let targetColumnIndex = -1;
            for (let i = 0; i < gradeInputs.length; i++) {
                if (gradeInputs[i].dataset.columnId == columnId) {
                    // Count how many cells are before this one in its row
                    const row = gradeInputs[i].closest('tr');
                    const cell = gradeInputs[i].closest('td');
                    const cells = Array.from(row.querySelectorAll('td'));
                    targetColumnIndex = cells.indexOf(cell); // Index matches header index (both start at 0 with student column)
                    break;
                }
            }
            
            console.log('🔍 Target column index:', targetColumnIndex, 'Total headers:', headers.length);
            
            if (targetColumnIndex > 0 && targetColumnIndex < headers.length) {
                const header = headers[targetColumnIndex];
                const spans = header.querySelectorAll('span');
                if (spans.length > 0) {
                    // First span should have the column name
                    itemName = spans[0].textContent.trim();
                }
                console.log('🔍 Header HTML:', header.innerHTML);
                console.log('🔍 Extracted item name:', itemName);
            }
        }
        
        console.log('🔍 Final item name:', itemName);
        
        // Get existing lacking requirements
        let existingLackingReqs = '';
        try {
            const lackingReqsResponse = await loadLackingRequirements(studentId);
            existingLackingReqs = lackingReqsResponse || '';
        } catch (error) {
            console.warn('Could not load existing lacking requirements:', error);
        }
        
        // Suggest adding the item name if not already present
        let suggestedText = existingLackingReqs;
        if (existingLackingReqs && !existingLackingReqs.includes(itemName)) {
            suggestedText = existingLackingReqs + (existingLackingReqs.endsWith('.') || existingLackingReqs.endsWith(',') ? ' ' : ', ') + itemName;
        } else if (!existingLackingReqs) {
            suggestedText = `Missing ${itemName}`;
        }
        
        const result = await Swal.fire({
            title: 'Mark Component as Incomplete',
            html: `
                <div style="text-align: left;">
                    <p style="margin-bottom: 15px; font-size: 14px; color: #1f2937;">Marking <strong>${itemName}</strong> as incomplete.</p>
                    <label style="display: block; margin-bottom: 10px; font-weight: 700; color: #FDB81E; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Lacking Requirements / Reason</label>
                    <textarea id="swal-inc-lacking-requirements" style="width: 100%; padding: 14px 16px; font-size: 14px; border: 2px solid #FDB81E !important; border-radius: 8px; font-family: inherit; resize: none; height: 100px; line-height: 1.5; color: #1f2937; background: white; box-sizing: border-box; outline: none;" placeholder="e.g., Missing quiz, needs to submit assignment">${suggestedText}</textarea>
                    <p style="margin-top: 10px; font-size: 12px; color: #6b7280;">This will appear in the Course Assessment Report (CAR).</p>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Mark as INC',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#FDB81E',
            cancelButtonColor: '#6b7280',
            preConfirm: () => {
                const lackingReqs = document.getElementById('swal-inc-lacking-requirements').value.trim();
                return lackingReqs;
            }
        });
        
        if (!result.isConfirmed) {
            return; // User cancelled
        }
        
        const lackingReqs = result.value || '';
        
        console.log('💾 Saving lacking requirements:', lackingReqs, 'for student:', studentId);
        
        // Save lacking requirements to grade_term table
        const lackingFd = new FormData();
        lackingFd.append('action', 'update_lacking_requirements');
        lackingFd.append('student_id', studentId);
        lackingFd.append('class_code', FGS.currentClassCode);
        lackingFd.append('lacking_requirements', lackingReqs);
        lackingFd.append('csrf_token', window.csrfToken || APP.csrfToken);
        
        try {
            const lackingResponse = await fetch(APP.apiPath + 'save_term_grades.php', {
                method: 'POST',
                body: lackingFd
            });
            
            const responseText = await lackingResponse.text();
            console.log('📡 Response text:', responseText);
            
            if (!responseText || responseText.trim() === '') {
                console.error('❌ Empty response from server');
                return;
            }
            
            const lackingData = JSON.parse(responseText);
            if (lackingData.success) {
                console.log('✅ Lacking requirements saved successfully');
            } else {
                console.error('❌ Failed to save lacking requirements:', lackingData.message);
            }
        } catch (error) {
            console.error('❌ Error saving lacking requirements:', error);
        }
        
        // Also update the overall grade status to 'incomplete'
        const statusFd = new FormData();
        statusFd.append('action', 'update_grade_status');
        statusFd.append('class_code', FGS.currentClassCode);
        statusFd.append('student_id', studentId);
        statusFd.append('grade_status', 'incomplete');
        statusFd.append('lacking_requirements', lackingReqs);
        statusFd.append('csrf_token', window.csrfToken || APP.csrfToken);
        
        try {
            await fetch(APP.apiPath + 'save_term_grades.php', {
                method: 'POST',
                body: statusFd
            });
            console.log('✅ Grade status updated to incomplete');
        } catch (error) {
            console.error('❌ Error updating grade status:', error);
        }
    }
    
    const fd = new FormData();
    fd.append('action', 'update_component_status');
    fd.append('student_id', studentId);
    fd.append('column_id', columnId);
    fd.append('class_code', FGS.currentClassCode);
    fd.append('status', status);
    fd.append('csrf_token', window.csrfToken || APP.csrfToken);
    
    try {
        const response = await fetch('/ajax/update_grade.php', {
            method: 'POST',
            body: fd
        });
        
        const data = await response.json();
        
        if (data.success) {
            console.log(`✅ Component status updated: ${studentId}, column ${columnId} -> ${status}`);
            
            // Update the cached grade
            const key = `${studentId}_${columnId}`;
            if (FGS.grades[key]) {
                FGS.grades[key].status = status;
            }
            
            // Re-render the table to show the change
            const currentComp = FGS.components.find(c => c.selected);
            if (currentComp) {
                renderTable(currentComp);
            }
            
            // If this was marking as INC, refresh the summary table to show updated status
            if (status === 'inc') {
                console.log('🔄 Refreshing summary table after marking as INC');
                // Call loadGradeSummary to refresh the summary view
                if (typeof loadGradeSummary === 'function') {
                    loadGradeSummary(FGS.currentClassCode);
                }
            }
            
            Toast.fire({
                icon: 'success',
                title: `Component marked as ${status === 'inc' ? 'Incomplete' : 'Submitted'}`
            });
        } else {
            Toast.fire({
                icon: 'error',
                title: 'Failed to update component status'
            });
        }
    } catch (error) {
        console.error('Error updating component status:', error);
        Toast.fire({
            icon: 'error',
            title: 'Error updating component status'
        });
    }
}

// Bulk flexible grade upload removed per requirements. Placeholder kept to avoid reference errors.
window.initBulkFlexibleUpload = function(){};
