/**
 * Analytics Dashboard
 * Handles data visualizations and charts
 */

// Initialize charts when page loads
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('reports').classList.contains('active')) {
        initializeAnalytics();
    }
});

// Also initialize when Reports section is shown
const originalShowSection = window.showSection;
window.showSection = function(sectionId) {
    originalShowSection(sectionId);
    if (sectionId === 'reports') {
        setTimeout(() => initializeAnalytics(), 100);
    }
};

function initializeAnalytics() {
    console.log('Initializing analytics...');
    loadFacultyWorkloadChart();
    loadEnrollmentTrendsChart();
}

/**
 * Faculty Workload Chart
 */
function loadFacultyWorkloadChart() {
    const ctx = document.getElementById('facultyWorkloadChart');
    if (!ctx) {
        console.error('facultyWorkloadChart canvas not found');
        return;
    }

    // Fetch data from server
    fetch('ajax/get_analytics_data.php?type=faculty_workload')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                createFacultyWorkloadChart(ctx, data.data);
            } else {
                console.error('Failed to load faculty workload data:', data.message);
                showChartError(ctx, 'Failed to load data');
            }
        })
        .catch(error => {
            console.error('Error loading faculty workload:', error);
            showChartError(ctx, 'Network error');
        });
}

function createFacultyWorkloadChart(ctx, data) {
    // Destroy existing chart if any
    if (window.facultyWorkloadChartInstance) {
        window.facultyWorkloadChartInstance.destroy();
    }

    // Prepare data
    const labels = data.map(item => item.faculty_name);
    const classCounts = data.map(item => item.class_count);

    // Create chart
    window.facultyWorkloadChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Number of Classes',
                data: classCounts,
                backgroundColor: 'rgba(0, 48, 130, 0.8)',
                borderColor: 'rgba(0, 48, 130, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Classes: ' + context.parsed.y;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    },
                    title: {
                        display: true,
                        text: 'Number of Classes'
                    }
                },
                x: {
                    ticks: {
                        maxRotation: 45,
                        minRotation: 45
                    }
                }
            }
        }
    });
}

/**
 * Enrollment Trends Chart
 */
function loadEnrollmentTrendsChart() {
    const ctx = document.getElementById('enrollmentTrendsChart');
    if (!ctx) {
        console.error('enrollmentTrendsChart canvas not found');
        return;
    }

    // Fetch data from server
    fetch('ajax/get_analytics_data.php?type=enrollment_trends')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                createEnrollmentTrendsChart(ctx, data.data);
            } else {
                console.error('Failed to load enrollment trends:', data.message);
                showChartError(ctx, 'Failed to load data');
            }
        })
        .catch(error => {
            console.error('Error loading enrollment trends:', error);
            showChartError(ctx, 'Network error');
        });
}

function createEnrollmentTrendsChart(ctx, data) {
    // Destroy existing chart if any
    if (window.enrollmentTrendsChartInstance) {
        window.enrollmentTrendsChartInstance.destroy();
    }

    // Prepare data
    const labels = data.map(item => {
        const date = new Date(item.month + '-01');
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short' });
    });
    const studentCounts = data.map(item => item.student_count);

    // Create chart
    window.enrollmentTrendsChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'New Students',
                data: studentCounts,
                borderColor: 'rgba(212, 175, 55, 1)',
                backgroundColor: 'rgba(212, 175, 55, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 5,
                pointHoverRadius: 7,
                pointBackgroundColor: 'rgba(212, 175, 55, 1)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Students: ' + context.parsed.y;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    },
                    title: {
                        display: true,
                        text: 'Number of Students'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Month'
                    }
                }
            }
        }
    });
}

/**
 * Show error message in chart canvas
 */
function showChartError(canvas, message) {
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.font = '14px Arial';
    ctx.fillStyle = '#dc2626';
    ctx.textAlign = 'center';
    ctx.fillText(message, canvas.width / 2, canvas.height / 2);
}

// Export functions if needed
window.initializeAnalytics = initializeAnalytics;
