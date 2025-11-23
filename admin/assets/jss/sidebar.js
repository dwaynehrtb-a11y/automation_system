// MINIMAL SIDEBAR TEST - Replace your sidebar.js with this temporarily
console.log('🔄 Sidebar JS file loaded');

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const floatingToggle = document.querySelector('.floating-toggle');
    
    if (sidebar && mainContent) {
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('expanded');
        
        // Show floating button when sidebar is collapsed
        if (floatingToggle) {
            if (sidebar.classList.contains('collapsed')) {
                floatingToggle.style.display = 'flex';
            } else {
                // Small delay when hiding for smoother transition
                setTimeout(() => {
                    floatingToggle.style.display = 'none';
                }, 300); // Matches your CSS transition duration
            }
        }
        
        console.log('Sidebar toggled');
    }
}

function showSection(sectionName) {
    console.log(' showSection called:', sectionName);
    
    // Remove active from all nav links
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
    });

    // Add active to clicked nav link
    document.querySelectorAll('.nav-link').forEach(link => {
        const onclick = link.getAttribute('onclick');
        if (onclick && onclick.includes(`showSection('${sectionName}')`)) {
            link.classList.add('active');
            console.log(' Nav link activated:', link);
        }
    });

    // Hide all sections
    document.querySelectorAll('.section').forEach(sec => {
        sec.classList.remove('active');
    });

    // Show target section
    const targetSection = document.getElementById(sectionName);
    if (targetSection) {
        targetSection.classList.add('active');
        console.log(' Section shown:', sectionName);
    } else {
        console.error(' Section not found:', sectionName);
    }

    // Update breadcrumb
    const currentSection = document.getElementById('currentSection');
    if (currentSection) {
        const sectionNames = {
            'dashboard': 'Dashboard',
            'subjects': 'Manage Subjects',
            'faculty': 'Manage Faculty',
            'students': 'Manage Students',
            'sections': 'Manage Sections',
            'classes': 'Manage Classes',
            'reports': 'Reports & Analytics'
        };
        currentSection.textContent = sectionNames[sectionName] || sectionName;
        console.log(' Breadcrumb updated:', sectionNames[sectionName]);
    }

    
    setTimeout(() => {
        window.scrollTo(0, 0);
        document.body.scrollTop = 0;
        document.documentElement.scrollTop = 0;
        console.log(' Scrolled to top');
    }, 50);
}

// Make functions globally available
window.toggleSidebar = toggleSidebar;
window.showSection = showSection;

console.log('✅ Sidebar functions registered globally');

// Test on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ DOM loaded - testing sidebar elements...');
    
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const navLinks = document.querySelectorAll('.nav-link');
    const sections = document.querySelectorAll('.section');
    
    console.log('📊 Elements found:', {
        sidebar: !!sidebar,
        mainContent: !!mainContent,
        navLinks: navLinks.length,
        sections: sections.length
    });
    
    // Test toggleSidebar function
    if (typeof window.toggleSidebar === 'function') {
        console.log('✅ toggleSidebar is available globally');
    } else {
        console.error('❌ toggleSidebar not found globally');
    }
    
    // Test showSection function
    if (typeof window.showSection === 'function') {
        console.log('✅ showSection is available globally');
    } else {
        console.error('❌ showSection not found globally');
    }
});

window.faculty = {};
window.sections = {};
