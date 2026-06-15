// Theme Toggle Script - Uses html element for early detection
function initThemeToggle() {
    const htmlElement = document.documentElement;
    const savedTheme = localStorage.getItem('theme') || 'light';

    if (savedTheme === 'dark') {
        htmlElement.classList.add('dark-mode');
        updateThemeButtonIcon('dark');
    } else {
        htmlElement.classList.remove('dark-mode');
        updateThemeButtonIcon('light');
    }
}

function toggleTheme() {
    const htmlElement = document.documentElement;
    const isDarkMode = htmlElement.classList.contains('dark-mode');

    if (isDarkMode) {
        htmlElement.classList.remove('dark-mode');
        localStorage.setItem('theme', 'light');
        updateThemeButtonIcon('light');
    } else {
        htmlElement.classList.add('dark-mode');
        localStorage.setItem('theme', 'dark');
        updateThemeButtonIcon('dark');
    }
}

function updateThemeButtonIcon(theme) {
    const button = document.querySelector('.theme-toggle-btn');
    if (!button) return;
    
    const icon = button.querySelector('i');
    if (!icon) return;
    
    if (theme === 'dark') {
        icon.classList.remove('fa-sun');
        icon.classList.add('fa-moon');
        button.title = 'Switch to Light Mode';
    } else {
        icon.classList.remove('fa-moon');
        icon.classList.add('fa-sun');
        button.title = 'Switch to Dark Mode';
    }
}

// Initialize theme on page load
document.addEventListener('DOMContentLoaded', function() {
    initThemeToggle();
    
    // Attach click event to theme toggle button
    const themeToggleBtn = document.querySelector('.theme-toggle-btn');
    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', toggleTheme);
    }
});

// Listen for storage changes from other tabs
window.addEventListener('storage', function(e) {
    if (e.key === 'theme') {
        initThemeToggle();
    }
});
