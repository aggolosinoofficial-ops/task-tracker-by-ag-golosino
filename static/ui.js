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

    // Function to apply random neon border to cards
    function applyRandomNeonToCards() {
        const cards = document.querySelectorAll('.card');
        cards.forEach(card => {
            if (body.classList.contains('dark-mode')) {
                const randomColor = neonColors[Math.floor(Math.random() * neonColors.length)];
                card.style.setProperty('--card-neon-color', randomColor);
            } else {
                // Remove custom property when not in dark mode to revert to default shadow
                card.style.removeProperty('--card-neon-color');
            }
        });
    }

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
});
