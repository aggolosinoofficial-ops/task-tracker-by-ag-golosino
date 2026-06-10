// static/ui.js

document.addEventListener('DOMContentLoaded', () => {
    // --- Navbar Collapse Toggle (Hamburger Menu) ---
    const mobileNavToggle = document.getElementById('mobileNavToggle');
    const appSidebar = document.getElementById('appSidebar'); // Assuming your sidebar has this ID

    if (mobileNavToggle && appSidebar) {
        mobileNavToggle.addEventListener('click', () => {
            appSidebar.classList.toggle('mobile-open');
            // Optional: Toggle body overflow to prevent scrolling when sidebar is open
            document.body.classList.toggle('no-scroll', appSidebar.classList.contains('mobile-open'));
        });

        // Close sidebar if clicking outside of it on mobile
        document.addEventListener('click', (event) => {
            if (window.innerWidth <= 800 && appSidebar.classList.contains('mobile-open')) {
                // Check if the click is outside the sidebar and not on the toggle button itself
                if (!appSidebar.contains(event.target) && !mobileNavToggle.contains(event.target)) {
                    appSidebar.classList.remove('mobile-open');
                    document.body.classList.remove('no-scroll');
                }
            }
        });
    }

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

    function updateSummaryCounts() {
        const taskCards = document.querySelectorAll('.card[data-status], .task-card[data-status]');
        const stats = { total: 0, completed: 0, pending: 0, archived: 0 };
        
        // Note: Real apps would fetch this from an API, 
        // but we'll calculate from DOM elements currently visible for immediate feedback.
        taskCards.forEach(card => {
            const status = card.getAttribute('data-status');
            if (status in stats) {
                stats[status]++;
                if (status !== 'archived') stats.total++;
            }
        });

        // Animate from current value to new value
        const getVal = (id) => parseInt(document.getElementById(id)?.textContent || "0");

        animateValue('total-count', getVal('total-count'), stats.total, 800);
        animateValue('completed-count', getVal('completed-count'), stats.completed, 800);
        animateValue('pending-count', getVal('pending-count'), stats.pending, 800);
        animateValue('archived-count', getVal('archived-count'), stats.archived, 800);
    }

    // Listen for custom event to refresh dashboard
    document.addEventListener('taskUpdated', () => {
        updateSummaryCounts();
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
    updateSummaryCounts();
});
