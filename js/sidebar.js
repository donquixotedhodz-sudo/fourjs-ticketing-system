// Common sidebar functionality for all pages
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebarCollapse');
    const sidebar = document.getElementById('sidebar');
    const content = document.getElementById('content');
    
    // Function to check if we're on mobile
    function isMobile() {
        return window.innerWidth <= 768;
    }
    
    // Function to initialize sidebar state based on screen size
    function initializeSidebar() {
        if (isMobile()) {
            // On mobile, sidebar should be hidden by default
            sidebar.classList.remove('active');
            content.classList.remove('expanded');
        } else {
            // On desktop, sidebar should be visible by default
            sidebar.classList.remove('active');
            content.classList.remove('expanded');
        }
    }
    
    if (sidebarToggle && sidebar && content) {
        // Initialize sidebar state
        initializeSidebar();
        
        // Handle sidebar toggle
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            content.classList.toggle('expanded');
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            // Reset classes on resize to ensure proper behavior
            if (isMobile()) {
                // On mobile, ensure sidebar is hidden unless explicitly shown
                if (!sidebar.classList.contains('active')) {
                    content.classList.remove('expanded');
                }
            } else {
                // On desktop, ensure proper layout
                if (!sidebar.classList.contains('active')) {
                    content.classList.remove('expanded');
                }
            }
        });
    }
});