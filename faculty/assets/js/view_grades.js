// ========================================
// GRADE ANALYTICS MODULE
// ========================================

const GradeAnalytics = {
    currentData: null,
    charts: {},

    init() {
        console.log('✓ Grade Analytics initialized');
        this.setupEventListeners();
    },

    setupEventListeners() {
        const yearSelect = document.getElementById('grade-view-year');
        const termSelect = document.getElementById('grade-view-term');

        yearSelect?.addEventListener('change', () => {
            const term = termSelect.value;
            if (yearSelect.value && term) {
                this.loadClasses(yearSelect.value, term);
            } else {
                document.getElementById('grade-view-class').disabled = true;
                document.getElementById('grade-view-class').innerHTML = '<option value="">Select class...</option>';
            }
        });

        termSelect?.addEventListener('change', () => {
            const year = yearSelect.value;
            if (year && termSelect.value) {
                this.loadClasses(year, termSelect.value);
            } else {
                document.getElementById('grade-view-class').disabled = true;
                document.getElementById('grade-view-class').innerHTML = '<option value="">Select class...</option>';
            }
        });
    },

    async loadClasses(year, term) {
        const classSelect = document.getElementById('grade-view-class');
        classSelect.disabled = true;
        classSelect.innerHTML = '<option value="">Loading...</option>';

        const fd = new FormData();
        fd.append('action', 'get_classes');
        fd.append('academic_year', year);
        fd.append('term', term);
        fd.append('csrf_token', window.csrfToken);

        try {
            const res = await fetch((window.APP?.apiPath || '/faculty/ajax/') + 'process_grades.php', {
                method: 'POST',
                body: fd
            });
            const data = await res.json();

            if (data.success && data.classes.length > 0) {
                classSelect.innerHTML = '<option value="">Select class...</option>';
                data.classes.forEach(cls => {
                    const option = document.createElement('option');
                    option.value = cls.class_code;
                    option.textContent = `${cls.course_code} - ${cls.course_title} (${cls.section})`;
                    classSelect.appendChild(option);
                });
                classSelect.disabled = false;
            } else {
                classSelect.innerHTML = '<option value="">No classes found</option>';
            }
        } catch (error) {
            console.error('Error loading classes:', error);
            classSelect.innerHTML = '<option value="">Error loading classes</option>';
        }
    },

    async loadAnalytics() {
        const year = document.getElementById('grade-view-year').value;
        const term = document.getElementById('grade-view-term').value;
        const classCode = document.getElementById('grade-view-class').value;

        if (!year || !term || !classCode) {
            Toast.fire({
                icon: 'warning',
                title: 'Please select all filters'
            });
            return;
        }

        showLoading();

        const container = document.getElementById('grade-analytics-container');
        container.innerHTML = `
            <div class="grade-empty-state">
                <i class="fas fa-spinner fa-spin fa-3x" style="color: #3b82f6;"></i>
                <h3 style="margin-top: 20px;">Loading Grade Analytics...</h3>
                <p>Please wait while we fetch the data</p>
            </div>
        `;

        try {
            // Load summary data from the flexible grading system
            const summaryData = await this.fetchSummaryData(classCode);
            
            hideLoading();

            if (summaryData.students.length === 0) {
                container.innerHTML = `
                    <div class="grade-empty-state">
                        <i class="fas fa-info-circle fa-3x" style="color: #3b82f6;"></i>
                        <h3 style="margin-top: 20px;">No Grades Available</h3>
                        <p>No students or grades found for this class</p>
                    </div>
                `;
                return;
            }

            this.currentData = summaryData;
            this.updateStats(summaryData);
            this.renderResults(summaryData);

        } catch (error) {
            hideLoading();
            console.error('Error loading analytics:', error);
            container.innerHTML = `
                <div class="grade-empty-state">
                    <i class="fas fa-exclamation-circle fa-3x" style="color: #ef4444;"></i>
                    <h3 style="margin-top: 20px;">Error Loading Data</h3>
                    <p>${error.message}</p>
                </div>
            `;
        }
    },

    async fetchSummaryData(classCode) {
        // First, load students
        const studentsFd = new FormData();
        studentsFd.append('action', 'get_students');
        studentsFd.append('class_code', classCode);
        studentsFd.append('csrf_token', window.csrfToken);

        const studentsRes = await fetch((window.APP?.apiPath || '/faculty/ajax/') + 'process_grades.php', {
            method: 'POST',
            body: studentsFd
        });
        const studentsData = await studentsRes.json();

        if (!studentsData.success) {
            throw new Error('Failed to load students');
        }

        const students = studentsData.students;

        // Load midterm and finals data
        const midtermData = await this.loadTermData(classCode, 'midterm');
        const finalsData = await this.loadTermData(classCode, 'finals');

        // Get term weights
        const weightsFd = new FormData();
        weightsFd.append('action', 'get_term_weights');
        weightsFd.append('class_code', classCode);
        weightsFd.append('csrf_token', window.csrfToken);

        const weightsRes = await fetch((window.APP?.apiPath || '/faculty/ajax/') + 'manage_grading_components.php', {
            method: 'POST',
            body: weightsFd
        });
        const weightsData = await weightsRes.json();

        const midtermWeight = parseFloat(weightsData.midterm_weight || 40);
        const finalsWeight = parseFloat(weightsData.finals_weight || 60);

        // Calculate grades for each student
        const results = students.map(student => {
            const midtermPct = this.calculateTermGrade(student.student_id, midtermData);
            const finalsPct = this.calculateTermGrade(student.student_id, finalsData);
            const termPct = (midtermPct * (midtermWeight / 100)) + (finalsPct * (finalsWeight / 100));
            const termGrade = this.toGrade(termPct);

            return {
                student_id: student.student_id,
                name: student.name,
                midterm_percentage: midtermPct,
                finals_percentage: finalsPct,
                term_percentage: termPct,
                midterm_grade: this.toGrade(midtermPct),
                finals_grade: this.toGrade(finalsPct),
                term_grade: termGrade
            };
        });

        return {
            students: results,
            midterm_weight: midtermWeight,
            finals_weight: finalsWeight
        };
    },

    async loadTermData(classCode, termType) {
        const fd = new FormData();
        fd.append('action', 'get_components');
        fd.append('class_code', classCode);
        fd.append('term_type', termType);
        fd.append('csrf_token', window.csrfToken);

        const res = await fetch((window.APP?.apiPath || '/faculty/ajax/') + 'manage_grading_components.php', {
            method: 'POST',
            body: fd
        });
        const data = await res.json();

        if (!data.success) {
            return { components: [], grades: {} };
        }

        const components = data.components || [];
        const allGrades = {};

        for (const comp of components) {
            const grades = await this.loadGradesForComponent(classCode, comp.id);
            Object.assign(allGrades, grades);
        }

        return { components, grades: allGrades };
    },

    async loadGradesForComponent(classCode, componentId) {
        const fd = new FormData();
        fd.append('action', 'get_grades');
        fd.append('class_code', classCode);
        fd.append('component_id', componentId);
        fd.append('csrf_token', window.csrfToken);

        const res = await fetch((window.APP?.apiPath || '/faculty/ajax/') + 'process_grades.php', {
            method: 'POST',
            body: fd
        });
        const data = await res.json();

        return data.success ? (data.grades || {}) : {};
    },

    calculateTermGrade(studentId, termData) {
        const { components, grades } = termData;

        let totalWeightedScore = 0;
        let totalWeight = 0;

        components.forEach(comp => {
            const columns = comp.columns || [];
            let compScore = 0;
            let compMax = 0;

            columns.forEach(col => {
                const key = `${studentId}_${col.id}`;
                const grade = grades[key] || {};
                const score = parseFloat(grade.score) || 0;

                if (score > 0) {
                    compScore += score;
                    compMax += parseFloat(col.max_score);
                }
            });

            if (compMax > 0) {
                const compPct = (compScore / compMax) * 100;
                const weighted = compPct * (parseFloat(comp.percentage) / 100);
                totalWeightedScore += weighted;
                totalWeight += parseFloat(comp.percentage);
            }
        });

        return totalWeight > 0 ? (totalWeightedScore / totalWeight) * 100 : 0;
    },

    toGrade(pct) {
        const p = parseFloat(pct);
        if (isNaN(p) || p < 0) return 0.0;

        if (p >= 96) return 4.0;
        if (p >= 91) return 3.5;
        if (p >= 86) return 3.0;
        if (p >= 81) return 2.5;
        if (p >= 76) return 2.0;
        if (p >= 71) return 1.5;
        if (p >= 60) return 1.0;
        return 0.0;
    },

    gradeColor(grade) {
        const g = parseFloat(grade);
        if (g >= 3.5) return '#10b981';
        if (g >= 3.0) return '#3b82f6';
        if (g >= 2.0) return '#f59e0b';
        if (g >= 1.0) return '#ef4444';
        return '#991b1b';
    },

    updateStats(data) {
        const students = data.students;
        const totalStudents = students.length;

        // Calculate average grade
        const totalGrade = students.reduce((sum, s) => sum + s.term_grade, 0);
        const avgGrade = totalStudents > 0 ? (totalGrade / totalStudents) : 0;

        // Count honor students (3.5+)
        const honorCount = students.filter(s => s.term_grade >= 3.5).length;

        // Count at-risk students (<1.5)
        const atRiskCount = students.filter(s => s.term_grade < 1.5).length;

        // Update DOM
        document.getElementById('total-graded-students').textContent = totalStudents;
        document.getElementById('average-class-grade').textContent = avgGrade.toFixed(2);
        document.getElementById('top-performers').textContent = honorCount;
        document.getElementById('at-risk-students').textContent = atRiskCount;
    },

    renderResults(data) {
        const container = document.getElementById('grade-analytics-container');

        let html = `
            <div class="grade-results-card">
                <div class="grade-results-header">
                    <div class="grade-results-title">
                        <i class="fas fa-table"></i>
                        Student Grades (${data.students.length} students)
                    </div>
                    <div class="grade-results-actions">
                        <button class="btn-export" onclick="GradeAnalytics.exportToCSV()">
                            <i class="fas fa-download"></i> Export CSV
                        </button>
                    </div>
                </div>
                <div class="grade-table-container">
                    <table class="grade-results-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Midterm (${data.midterm_weight}%)</th>
                                <th>Finals (${data.finals_weight}%)</th>
                                <th>Term Grade</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
        `;

        data.students.forEach(student => {
            const gradeColor = this.gradeColor(student.term_grade);
            const remarks = student.term_grade >= 3.0 ? 'PASSED' : student.term_grade >= 1.0 ? 'CONDITIONAL' : 'FAILED';
            const remarksColor = student.term_grade >= 3.0 ? '#10b981' : student.term_grade >= 1.0 ? '#f59e0b' : '#ef4444';

            html += `
                <tr>
                    <td>
                        <div class="student-cell">
                            <div class="student-avatar-small">${student.name.charAt(0)}</div>
                            <div class="student-info-small">
                                <div class="student-name-small">${student.name}</div>
                                <div class="student-id-small">${student.student_id}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div>${student.midterm_percentage.toFixed(2)}%</div>
                        <span class="grade-badge-small" style="background:${this.gradeColor(student.midterm_grade)};">${student.midterm_grade.toFixed(1)}</span>
                    </td>
                    <td>
                        <div>${student.finals_percentage.toFixed(2)}%</div>
                        <span class="grade-badge-small" style="background:${this.gradeColor(student.finals_grade)};">${student.finals_grade.toFixed(1)}</span>
                    </td>
                    <td>
                        <div style="font-weight:600;">${student.term_percentage.toFixed(2)}%</div>
                        <span class="grade-badge-small" style="background:${gradeColor};">${student.term_grade.toFixed(1)}</span>
                    </td>
                    <td>
                        <span style="color:${remarksColor};font-weight:600;">${remarks}</span>
                    </td>
                </tr>
            `;
        });

        html += `
                        </tbody>
                    </table>
                </div>
            </div>
        `;

        container.innerHTML = html;
    },

    exportToCSV() {
        if (!this.currentData) return;

        let csv = 'Student ID,Name,Midterm %,Midterm Grade,Finals %,Finals Grade,Term %,Term Grade,Remarks\n';

        this.currentData.students.forEach(student => {
            const remarks = student.term_grade >= 3.0 ? 'PASSED' : student.term_grade >= 1.0 ? 'CONDITIONAL' : 'FAILED';
            csv += `${student.student_id},"${student.name}",${student.midterm_percentage.toFixed(2)},${student.midterm_grade.toFixed(1)},${student.finals_percentage.toFixed(2)},${student.finals_grade.toFixed(1)},${student.term_percentage.toFixed(2)},${student.term_grade.toFixed(1)},${remarks}\n`;
        });

        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `grades_${new Date().getTime()}.csv`;
        a.click();
        window.URL.revokeObjectURL(url);

        Toast.fire({
            icon: 'success',
            title: 'Grades exported successfully!'
        });
    },

    clear() {
        document.getElementById('grade-view-year').value = '';
        document.getElementById('grade-view-term').value = '';
        document.getElementById('grade-view-class').value = '';
        document.getElementById('grade-view-class').disabled = true;

        document.getElementById('total-graded-students').textContent = '0';
        document.getElementById('average-class-grade').textContent = '0.0';
        document.getElementById('top-performers').textContent = '0';
        document.getElementById('at-risk-students').textContent = '0';

        document.getElementById('grade-analytics-container').innerHTML = `
            <div class="grade-empty-state">
                <div class="empty-state-illustration">
                    <i class="fas fa-chart-bar fa-6x"></i>
                </div>
                <h3>Select Filters to View Grades</h3>
                <p>Choose academic year, term, and class to see detailed grade analytics</p>
            </div>
        `;

        this.currentData = null;
    }
};

// Global functions
function loadGradeAnalytics() {
    GradeAnalytics.loadAnalytics();
}

function clearGradeFilters() {
    GradeAnalytics.clear();
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    GradeAnalytics.init();
});

console.log('✓ View Grades module loaded');
