/**
 * Dark Mode Toggle Functionality
 * Handles theme switching and persistence across all pages
 */

// Initialize dark mode functionality when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeDarkMode();
    
    // Ensure transitions are enabled after page load with proper timing
    setTimeout(function() {
        if (document.body) {
            document.body.classList.remove('no-transition');
            document.body.classList.add('loaded');
        }
    }, 200); // Increased delay for better stability
});

function initializeDarkMode() {
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');
    const themeText = document.getElementById('themeText');
    const body = document.body;
    
    // Check if theme toggle elements exist on the page
    if (!themeToggle || !themeIcon || !themeText) {
        return; // Exit if elements don't exist
    }
    
    // Prevent multiple initializations by checking if already initialized
    if (themeToggle.hasAttribute('data-initialized')) {
        return; // Exit if already initialized
    }
    
    // Mark as initialized to prevent duplicate event listeners
    themeToggle.setAttribute('data-initialized', 'true');
    
    // Check for saved theme preference or default to light mode
    const savedTheme = localStorage.getItem('theme') || 'light';
    
    // Apply saved theme
    applyTheme(savedTheme);
    
    // Theme toggle event listener
    themeToggle.addEventListener('click', function() {
        const currentTheme = body.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        applyTheme(newTheme);
        localStorage.setItem('theme', newTheme);
    });
}

function applyTheme(theme) {
    const body = document.body;
    const themeIcon = document.getElementById('themeIcon');
    const themeText = document.getElementById('themeText');
    const themeToggle = document.getElementById('themeToggle');
    
    if (theme === 'dark') {
        body.setAttribute('data-theme', 'dark');
        if (themeIcon) themeIcon.className = 'fas fa-moon';
        if (themeText) themeText.textContent = 'Dark';
        if (themeToggle) themeToggle.classList.add('dark-mode');
    } else {
        body.setAttribute('data-theme', 'light');
        if (themeIcon) themeIcon.className = 'fas fa-sun';
        if (themeText) themeText.textContent = 'Light';
        if (themeToggle) themeToggle.classList.remove('dark-mode');
    }
}

// Enhanced theme application - works with inline scripts to prevent FOUC
(function() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    
    // Ensure theme is applied to both document and body
    function ensureThemeApplied() {
        document.documentElement.setAttribute('data-theme', savedTheme);
        if (document.body) {
            document.body.setAttribute('data-theme', savedTheme);
        }
    }
    
    // Apply theme immediately
    ensureThemeApplied();
    
    // Handle DOM ready state
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            ensureThemeApplied();
            // Remove no-transition class and add loaded class after a brief delay
            setTimeout(function() {
                if (document.body) {
                    document.body.classList.remove('no-transition');
                    document.body.classList.add('loaded');
                }
            }, 200); // Consistent timing for better stability
        });
    } else {
        // If DOM is already loaded
        setTimeout(function() {
            if (document.body) {
                document.body.classList.remove('no-transition');
                document.body.classList.add('loaded');
            }
        }, 200); // Consistent timing
    }
})();