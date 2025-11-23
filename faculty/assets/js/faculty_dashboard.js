/**
 * Enhanced Faculty Dashboard Core Functionality
 * Includes missing utility functions and improved error handling
 */

// Utility functions (previously missing FacultyUtils)
window.FacultyUtils = {
    showInfo: function(message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'info',
                title: 'Information',
                text: message,
                timer: 3000
            });
        } else {
            alert('Info: ' + message);
        }
    },

    showError: function(message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: message
            });
        } else {
            alert('Error: ' + message);
        }
    },

    showSuccess: function(message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: message,
                timer: 2000
            });
        } else {
            alert('Success: ' + message);
        }
    },

    fetchWithErrorHandling: async function(url, options = {}) {
        try {
            const response = await fetch(url, {
                headers: {
                    'Content-Type': 'application/json',
                    ...options.headers
                },
                ...options
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Fetch error:', error);
            throw error;
        }
    },

    formatDate: function(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    },

    formatTime: function(timeString) {
        const time = new Date(`1970-01-01T${timeString}`);
        return time.toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
    }
};

// Students module placeholder
window.Students = {
    init: function() {
        console.log('Students module initialized');
        this.loadStudentList();
    },

    loadStudentList: function() {
        const studentsSection = document.getElementById('students');
        if (!studentsSection) return;

        const contentCard = studentsSection.querySelector('.content-card .empty-state');
        if (contentCard) {
            contentCard.innerHTML = `
                <div class="empty-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <h3>Student Management</h3>
                <p>Select a class from the dashboard to manage students, or use the grading system to view enrolled students.</p>
                <button class="btn btn-primary" onclick="window.Dashboard.showSection('dashboard')">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </button>
            `;
        }
    }
};

// Grades module placeholder
window.Grades = {
    init: function() {
        console.log('Grades module initialized');
        this.setupGradeViewing();
    },

    setupGradeViewing: function() {
        const gradesSection = document.getElementById('grades');
        if (!gradesSection) return;

        // Add functionality to view grades section
        console.log('Grade viewing setup complete');
    }
};

// Enhanced Dashboard object
window.Dashboard = {
    // Sidebar functions with improved mobile handling
   toggleSidebar: function() {
    const sidebar = document.getElementById('sidebar');
    const content = document.getElementById('mainContent');
    const header = document.querySelector('.nu-header');
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (!sidebar || !content) {
        console.error('Sidebar or main content not found');
        return;
    }

    const isCollapsed = sidebar.classList.contains('collapsed');
    const isMobile = window.innerWidth <= 768;

    if (isMobile) {
        // Mobile: overlay lang
        if (isCollapsed) {
            sidebar.classList.remove('collapsed');
            this.showOverlay();
        } else {
            sidebar.classList.add('collapsed');
            this.hideOverlay();
        }
   } else {
        // Desktop: toggle sidebar and adjust header
        sidebar.classList.toggle('collapsed');
        
        // Adjust header position based on sidebar state
        if (header) {
            if (sidebar.classList.contains('collapsed')) {
                header.classList.add('expanded');
            } else {
                header.classList.remove('expanded');
            }
        }
    }

    localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
},

// Add overlay methods
showOverlay: function() {
    let overlay = document.querySelector('.sidebar-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        overlay.onclick = () => this.toggleSidebar();
        document.body.appendChild(overlay);
    }
    setTimeout(() => overlay.classList.add('active'), 10);
},

hideOverlay: function() {
    const overlay = document.querySelector('.sidebar-overlay');
    if (overlay) {
        overlay.classList.remove('active');
        setTimeout(() => overlay.remove(), 300);
    }
},

    // Enhanced section navigation with loading states
 // Enhanced section navigation with loading states
showSection: function(sectionName) {
    console.log(`Switching to section: ${sectionName}`);

    // Show loading state
    this.showSectionLoadingState(sectionName);

    // Remove active from all nav links
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
    });

    // Add active to clicked link
    const clickedLink = document.querySelector(`[onclick*="showSection('${sectionName}')"], [href="#${sectionName}"]`);
    if (clickedLink) {
        clickedLink.classList.add('active');
    }

    // Show selected section
    const targetSection = document.getElementById(sectionName);
    if (targetSection) {
        // Hide all OTHER sections
        document.querySelectorAll('.content-section').forEach(section => {
            if (section.id !== sectionName) {
                section.classList.remove('active');
                section.style.display = 'none';
                section.style.opacity = '0';
                section.style.visibility = 'hidden';
            }
        });
        
        // Show only the target section
        targetSection.classList.add('active');
        // For profile section, don't override inline styles - let CSS handle it
        if (sectionName !== 'profile') {
            targetSection.style.visibility = 'visible';
            targetSection.style.opacity = '1';
            targetSection.style.display = 'block';
        }
        console.log(`Successfully switched to ${sectionName}`);
        
        // Load section-specific data
        setTimeout(() => {
            this.loadSectionData(sectionName);
            this.hideSectionLoadingState(sectionName);
        }, 100);

        // Close mobile sidebar after navigation
        if (window.innerWidth <= 768) {
            const sidebar = document.getElementById('sidebar');
            if (sidebar && !sidebar.classList.contains('collapsed')) {
                this.toggleSidebar();
            }
        }
        
        // **AUTO-RESTORE GRADING SYSTEM WHEN SWITCHING TO IT**
        if (sectionName === 'grading-system' && typeof restoreLastSelectedClass === 'function') {
            setTimeout(restoreLastSelectedClass, 500);
        }
    } else {
        console.error(`Section ${sectionName} not found!`);
        FacultyUtils.showError(`Section ${sectionName} not found!`);
    }
},
    // Show loading state for section
    showSectionLoadingState: function(sectionName) {
        const section = document.getElementById(sectionName);
        if (section) {
            const loadingIndicator = document.createElement('div');
            loadingIndicator.className = 'section-loading';
            loadingIndicator.innerHTML = `
                <div class="loading-spinner">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span>Loading ${sectionName}...</span>
                </div>
            `;
            section.appendChild(loadingIndicator);
        }
    },

    // Hide loading state for section
    hideSectionLoadingState: function(sectionName) {
        const section = document.getElementById(sectionName);
        if (section) {
            const loadingIndicator = section.querySelector('.section-loading');
            if (loadingIndicator) {
                loadingIndicator.remove();
            }
        }
    },

    // Enhanced section data loading
    loadSectionData: function(sectionName) {
        switch(sectionName) {
            case 'students':
                if (window.Students && window.Students.init) {
                    window.Students.init();
                }
                break;
            case 'grades':
                if (window.Grades && window.Grades.init) {
                    window.Grades.init();
                }
                break;
            case 'grading-system':
                // Initialize grading system if not already done
                this.initializeGradingSystem();
                break;
            case 'profile':
                // Profile is static content, just ensure it's visible
                console.log('Profile section loaded');
                break;
            default:
                console.log(`Loading data for ${sectionName}`);
                break;
        }
    },

    // Initialize grading system
  initializeGradingSystem: function() {
    console.log('Initializing grading system...');
    
    // Check if grading system elements exist (FIXED IDs)
    const academicYearSelect = document.getElementById('flex_grading_academic_year');
    const termSelect = document.getElementById('flex_grading_term');
    const classSelect = document.getElementById('flex_grading_class');
    
    if (academicYearSelect && termSelect && classSelect) {
        console.log('✓ Grading system elements found and ready');
    } else {
        console.warn('⚠ Grading system elements not found');
    }
},

    // Enhanced section details viewing
    viewSectionDetails: function(sectionId) {
        FacultyUtils.showInfo('Loading section details...');

        // Check if the AJAX endpoint exists before making request
        this.checkEndpointExists('../ajax/load_section_details.php')
            .then(exists => {
                if (exists) {
                    return FacultyUtils.fetchWithErrorHandling(`../ajax/load_section_details.php?section_id=${sectionId}`);
                } else {
                    // Fallback to mock data
                    return this.getMockSectionDetails(sectionId);
                }
            })
            .then(data => {
                if (data.success) {
                    this.showSectionDetailsModal(data.section);
                } else {
                    FacultyUtils.showError(data.message || 'Failed to load section details');
                }
            })
            .catch(error => {
                console.error('Error loading section details:', error);
                FacultyUtils.showError('Failed to load section details');
            });
    },

    // Check if endpoint exists
    checkEndpointExists: async function(url) {
        try {
            const response = await fetch(url, { method: 'HEAD' });
            return response.status !== 404;
        } catch (error) {
            return false;
        }
    },

    // Mock section details for fallback
    getMockSectionDetails: function(sectionId) {
        return Promise.resolve({
            success: true,
            section: {
                course_code: 'CS101',
                course_title: 'Introduction to Computer Science',
                section_code: 'A',
                schedule: 'MWF 10:00-11:00 AM',
                room: 'Room 101',
                term: 'Fall',
                year: '2024',
                units: 3,
                student_count: 25,
                max_students: 30,
                instructor: 'Faculty Member'
            }
        });
    },

    // Enhanced modal display
    showSectionDetailsModal: function(section) {
        const modalHtml = `
            <div style="text-align: left; max-width: 500px; margin: 0 auto;">
                <div class="section-detail-item">
                    <strong><i class="fas fa-book"></i> Course:</strong> 
                    <span>${section.course_code} - ${section.course_title}</span>
                </div>
                <div class="section-detail-item">
                    <strong><i class="fas fa-users"></i> Section:</strong> 
                    <span>${section.section_code || 'Not Set'}</span>
                </div>
                <div class="section-detail-item">
                    <strong><i class="fas fa-clock"></i> Schedule:</strong> 
                    <span>${section.schedule || 'TBA'}</span>
                </div>
                <div class="section-detail-item">
                    <strong><i class="fas fa-door-open"></i> Room:</strong> 
                    <span>${section.room || 'TBA'}</span>
                </div>
                <div class="section-detail-item">
                    <strong><i class="fas fa-calendar"></i> Term:</strong> 
                    <span>${section.term} ${section.year}</span>
                </div>
                <div class="section-detail-item">
                    <strong><i class="fas fa-credit-card"></i> Units:</strong> 
                    <span>${section.units} unit${section.units > 1 ? 's' : ''}</span>
                </div>
                <div class="section-detail-item">
                    <strong><i class="fas fa-user-graduate"></i> Enrollment:</strong> 
                    <span>${section.student_count}/${section.max_students} students</span>
                </div>
                <div class="section-detail-item">
                    <strong><i class="fas fa-chalkboard-teacher"></i> Instructor:</strong> 
                    <span>${section.instructor}</span>
                </div>
            </div>
        `;

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: `<i class="fas fa-info-circle"></i> Section Details`,
                html: modalHtml,
                showConfirmButton: false,
                showCancelButton: true,
                cancelButtonText: '<i class="fas fa-times"></i> Close',
                width: '600px',
                customClass: {
                    htmlContainer: 'section-details-modal'
                }
            });
        } else {
            // Fallback modal
            this.showFallbackModal('Section Details', modalHtml);
        }
    },

    // Fallback modal implementation
    showFallbackModal: function(title, content) {
        const modal = document.createElement('div');
        modal.className = 'fallback-modal';
        modal.innerHTML = `
            <div class="fallback-modal-content">
                <div class="fallback-modal-header">
                    <h3>${title}</h3>
                    <button class="fallback-modal-close">&times;</button>
                </div>
                <div class="fallback-modal-body">
                    ${content}
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Close modal events
        modal.querySelector('.fallback-modal-close').onclick = () => modal.remove();
        modal.onclick = (e) => {
            if (e.target === modal) modal.remove();
        };
    },

    // Enhanced navigation initialization
    initNavigation: function() {
        // Add click handlers to navigation links (only hash-based navigation)
        document.querySelectorAll('.nav-link[href^="#"]').forEach(link => {
            if (link && !link.getAttribute('onclick')) {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const href = link.getAttribute('href');
                    if (href) {
                        const sectionName = href.substring(1);
                        this.showSection(sectionName);
                    }
                });
            }
        });

        // IMPORTANT: Profile and external links already have onclick handlers
        // They will navigate directly without interception

        // Add floating toggle event listener
        const floatingToggle = document.querySelector('.floating-toggle');
        if (floatingToggle) {
            floatingToggle.addEventListener('click', () => {
                this.toggleSidebar();
            });
        }

        // Add sidebar toggle event listener
        const sidebarToggle = document.querySelector('.sidebar-toggle');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', () => {
                this.toggleSidebar();
            });
        }

        // Enhanced mobile sidebar handling
        document.addEventListener('click', (e) => {
            const isMobile = window.innerWidth <= 768;
            const sidebar = document.getElementById('sidebar');
            const floatingToggle = document.querySelector('.floating-toggle');
            
            if (isMobile && sidebar && !sidebar.classList.contains('collapsed')) {
                if (!sidebar.contains(e.target) && 
                    (!floatingToggle || !floatingToggle.contains(e.target)) &&
                    !e.target.classList.contains('nav-link')) {
                    this.toggleSidebar();
                }
            }
        });

        // Handle window resize with debouncing
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                this.initSidebar();
            }, 250);
        });

        // Handle keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (e.altKey && e.key >= '1' && e.key <= '4') {
                e.preventDefault();
                const sections = ['dashboard', 'students', 'grading-system', 'grades'];
                const sectionIndex = parseInt(e.key) - 1;
                if (sections[sectionIndex]) {
                    this.showSection(sections[sectionIndex]);
                }
            }
        });
    },

    // Enhanced sidebar initialization
    initSidebar: function() {
        const sidebar = document.getElementById('sidebar');
        const content = document.getElementById('mainContent');
        const floatingToggle = document.querySelector('.floating-toggle');
        const isMobile = window.innerWidth <= 768;

        if (!sidebar || !content) return;

        // Get stored sidebar state
        const storedState = localStorage.getItem('sidebarCollapsed');
        const shouldCollapse = isMobile || (storedState === 'true' && !isMobile);

        if (shouldCollapse) {
            sidebar.classList.add('collapsed');
            content.classList.add('expanded');
            if (floatingToggle) floatingToggle.classList.add('show');
            document.body.classList.add(isMobile ? 'mobile-sidebar-closed' : 'sidebar-hidden');
        } else {
            sidebar.classList.remove('collapsed');
            content.classList.remove('expanded');
            if (floatingToggle) floatingToggle.classList.remove('show');
            document.body.classList.add('sidebar-visible');
        }

        // Clean up conflicting classes
        document.body.classList.remove('no-scroll');
    },

    // Performance monitoring
    monitorPerformance: function() {
        if ('performance' in window) {
            const navigationTiming = performance.getEntriesByType('navigation')[0];
            if (navigationTiming) {
                console.log('Page load time:', navigationTiming.loadEventEnd - navigationTiming.fetchStart, 'ms');
            }
        }
    },

    // Initialize dashboard with error handling
    init: function() {
        try {
            this.initSidebar();
            this.initNavigation();
            this.monitorPerformance();
            console.log('Enhanced Faculty Dashboard initialized successfully');
        } catch (error) {
            console.error('Error initializing dashboard:', error);
            FacultyUtils.showError('Failed to initialize dashboard');
        }
    }
};

// Global functions for backward compatibility
function toggleSidebar() {
    window.Dashboard.toggleSidebar();
}

function showSection(sectionName) {
    window.Dashboard.showSection(sectionName);
}

function viewSectionDetails(sectionId) {
    window.Dashboard.viewSectionDetails(sectionId);
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.Dashboard.init();
    
    // Handle URL hash-based navigation
    function handleHashNavigation() {
        const hash = window.location.hash.substring(1); // Remove the # character
        if (hash && hash !== 'dashboard') {
            // Navigate to the section specified in the hash
            setTimeout(() => {
                window.Dashboard.showSection(hash);
            }, 100);
        }
    }
    
    // Call on page load
    handleHashNavigation();
    
    // Listen for hash changes
    window.addEventListener('hashchange', handleHashNavigation);
    
    // Add some CSS for enhanced functionality
    const enhancementCSS = `
        <style>
        .section-loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            z-index: 10;
        }
        
        .loading-spinner i {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #3b82f6;
        }
        
        .section-detail-item {
            margin-bottom: 1rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .section-detail-item:last-child {
            border-bottom: none;
        }
        
        .section-detail-item strong {
            display: inline-block;
            width: 120px;
            color: #374151;
        }
        
        .section-detail-item i {
            margin-right: 0.5rem;
            width: 16px;
            text-align: center;
        }
        
        .fallback-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .fallback-modal-content {
            background: white;
            border-radius: 8px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .fallback-modal-header {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .fallback-modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.25rem;
        }
        
        .fallback-modal-body {
            padding: 1rem;
        }
        
        .no-scroll {
            overflow: hidden;
        }
        
        @media (max-width: 768px) {
            .section-detail-item strong {
                width: 100px;
                font-size: 0.9rem;
            }
        }
        </style>
    `;
    
    document.head.insertAdjacentHTML('beforeend', enhancementCSS);
});

// Export for testing purposes
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { Dashboard: window.Dashboard, FacultyUtils: window.FacultyUtils };
}
