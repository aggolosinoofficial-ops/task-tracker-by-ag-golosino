// static/ui.js

document.addEventListener('DOMContentLoaded', () => {
    // --- JS Layout Optimization (Senior Dev Strategy) ---
    function adjustNavbar() {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main') || document.querySelector('.dashboard-container');
        if (!sidebar || !mainContent) return;

        const width = window.innerWidth;
        if (width <= 768) {
            // Mobile / Tablet Portrait
            sidebar.classList.add('topbar');
            if (!sidebar.classList.contains('mobile-expanded')) sidebar.classList.remove('sidebar-hover');
            mainContent.style.marginTop = '60px';
        } else {
            // Desktop
            sidebar.classList.remove('topbar');
            sidebar.classList.add('sidebar-hover');
            mainContent.style.marginTop = '0';
            mainContent.style.marginLeft = '60px';
        }
    }

    // --- Hamburger Menu Toggle ---
    const mobileNavToggle = document.getElementById('mobileNavToggle');
    if (mobileNavToggle) {
        mobileNavToggle.addEventListener('click', () => {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('mobile-expanded');
            
            // Change icon between Hamburger (☰) and Close (✕)
            const icon = mobileNavToggle.querySelector('i') || mobileNavToggle;
            icon.textContent = sidebar.classList.contains('mobile-expanded') ? '✕' : '☰';
        });
    }

    // Debounced Resize Observer
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(adjustNavbar, 200);
    });

    // Run once on load
    adjustNavbar();

    // --- Dark Mode Toggle with Neon Glow ---
    const darkModeToggle = document.getElementById('darkModeToggle');
    const body = document.body;
    const neonColors = [
        'var(--neon-cyan)',
        'var(--neon-lime)',
        'var(--neon-yellow)',
        'var(--neon-red)',
        'var(--neon-magenta)',
        'var(--neon-aqua)'
    ];

    // Function to apply random neon border to cards & summary cards
    function applyRandomNeonToCards() {
        const cards = document.querySelectorAll('.card, .summary-card');
        cards.forEach(card => {
            if (body.classList.contains('dark-mode')) {
                const randomColor = neonColors[Math.floor(Math.random() * neonColors.length)];
                card.style.setProperty('--card-neon-color', randomColor);
            } else {
                card.style.removeProperty('--card-neon-color');
            }
        });
    }

    // Count-Up Animation using requestAnimationFrame
    function animateValue(id, start, end, duration) {
        const obj = document.getElementById(id);
        if (!obj || start === end) return;
        
        let startTimestamp = null;
        const step = (timestamp) => {
            if (!startTimestamp) startTimestamp = timestamp;
            const progress = Math.min((timestamp - startTimestamp) / duration, 1);
            obj.innerHTML = Math.floor(progress * (end - start) + start);
            if (progress < 1) {
                window.requestAnimationFrame(step);
            } else {
                obj.classList.remove('count-glow');
                obj.innerHTML = end; // Ensure final value is exact
            }
        };
        obj.classList.add('count-glow');
        window.requestAnimationFrame(step);
    }

    // Listen for custom event to refresh dashboard
    document.addEventListener('taskUpdated', () => {
        // We now rely on the API-based updateDashboardSummary in script.js
        applyRandomNeonToCards();
    });

    // Load dark mode preference from localStorage on page load
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        body.classList.add('dark-mode');
        if (darkModeToggle) {
            darkModeToggle.innerHTML = '☀️ Light Mode'; // Update button text
        }
        applyRandomNeonToCards();
    } else if (darkModeToggle) {
        darkModeToggle.innerHTML = '🌙 Dark Mode'; // Default button text
    }

    if (darkModeToggle) {
        darkModeToggle.addEventListener('click', () => {
            body.classList.toggle('dark-mode');
            if (body.classList.contains('dark-mode')) {
                localStorage.setItem('theme', 'dark');
                darkModeToggle.innerHTML = '☀️ Light Mode';
            } else {
                localStorage.setItem('theme', 'light');
                darkModeToggle.innerHTML = '🌙 Dark Mode';
            }
            applyRandomNeonToCards(); // Always call to update/remove neon colors
        });
    }

    // Initial call to sync counts
});
