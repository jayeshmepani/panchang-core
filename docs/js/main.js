/**
 * Panchang Core Documentation - Main JavaScript
 */

document.addEventListener('DOMContentLoaded', () => {
    // Initialize theme
    initTheme();

    // Initialize navigation
    initNavigation();

    // Initialize copy buttons
    initCopyButtons();
});

/**
 * Theme Management
 */
function initTheme() {
    const themeToggle = document.getElementById('themeToggle');
    const themeText = themeToggle.querySelector('.theme-text');

    // Check for saved theme preference or default to light
    const currentTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', currentTheme);
    updateThemeText(themeText, currentTheme);

    themeToggle.addEventListener('click', () => {
        const newTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        updateThemeText(themeText, newTheme);
    });
}

function updateThemeText(element, theme) {
    element.textContent = theme === 'dark' ? 'Dark' : 'Light';
}

/**
 * Navigation Management
 */
function initNavigation() {
    const sidebar = document.getElementById('sidebar');
    const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const navLinks = document.querySelectorAll('.nav-link');

    // Mobile sidebar toggle
    if (mobileSidebarToggle) {
        mobileSidebarToggle.addEventListener('click', () => {
            sidebar.classList.add('open');
            sidebarOverlay.classList.add('active');
        });
    }

    // Close sidebar when clicking overlay
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('active');
        });
    }

    // Handle nav link clicks - smooth scroll to section
    navLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const targetId = link.getAttribute('href').substring(1);
            const targetElement = document.getElementById(targetId);

            if (targetElement) {
                // Update active state
                navLinks.forEach(l => l.classList.remove('active'));
                link.classList.add('active');

                // Smooth scroll to section
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });

                // Close mobile sidebar
                if (window.innerWidth <= 768) {
                    sidebar.classList.remove('open');
                    sidebarOverlay.classList.remove('active');
                }

                // Update URL hash
                history.pushState(null, null, `#${targetId}`);
            }
        });
    });

    // Handle initial load with hash
    const hash = window.location.hash.substring(1);
    if (hash) {
        const targetElement = document.getElementById(hash);
        if (targetElement) {
            setTimeout(() => {
                targetElement.scrollIntoView({
                    behavior: 'auto',
                    block: 'start'
                });
            }, 100);
            const activeLink = document.querySelector(`.nav-link[href="#${hash}"]`);
            if (activeLink) {
                activeLink.classList.add('active');
            }
        }
    }

    // Handle scroll spy - highlight active nav link
    initScrollSpy();
}

function initScrollSpy() {
    const sections = document.querySelectorAll('.content-section');
    const navLinks = document.querySelectorAll('.nav-link');

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && entry.boundingClientRect.top <= 100) {
                    const id = entry.target.getAttribute('id');
                    navLinks.forEach(link => {
                        link.classList.remove('active');
                        if (link.getAttribute('href') === `#${id}`) {
                            link.classList.add('active');
                        }
                    });
                }
            });
        },
        {
            rootMargin: '-100px 0px -60% 0px',
            threshold: 0
        }
    );

    sections.forEach(section => {
        observer.observe(section);
    });
}

/**
 * Copy Code Buttons
 */
function initCopyButtons() {
    const codeBlocks = document.querySelectorAll('pre');
    
    codeBlocks.forEach(block => {
        // Create copy button container
        const button = document.createElement('button');
        button.className = 'copy-btn';
        button.textContent = 'Copy';
        button.style.cssText = `
            position: relative;
            float: right;
            background: var(--code-bg);
            color: var(--text-color);
            border: 1px solid var(--border-color);
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            cursor: pointer;
            font-size: 0.75rem;
            margin-top: -2rem;
            margin-right: 0.5rem;
            transition: all 0.2s ease;
        `;
        
        button.addEventListener('click', async () => {
            const code = block.querySelector('code');
            if (code) {
                try {
                    await navigator.clipboard.writeText(code.textContent);
                    button.textContent = 'Copied!';
                    setTimeout(() => {
                        button.textContent = 'Copy';
                    }, 2000);
                } catch (err) {
                    button.textContent = 'Error';
                    setTimeout(() => {
                        button.textContent = 'Copy';
                    }, 2000);
                }
            }
        });
        
        block.style.position = 'relative';
        block.appendChild(button);
    });
}

/**
 * Responsive Tables
 */
function handleResponsiveTables() {
    const tables = document.querySelectorAll('.data-table');

    tables.forEach(table => {
        const wrapper = document.createElement('div');
        wrapper.style.overflowX = 'auto';
        wrapper.style.margin = 'var(--space-4) 0';
        table.parentNode.insertBefore(wrapper, table);
        wrapper.appendChild(table);
    });
}

// Initialize responsive tables
handleResponsiveTables();
