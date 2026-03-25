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
    
    // Initialize search (future enhancement)
    initSearch();
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
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const navLinks = document.querySelectorAll('.nav-link');
    
    // Desktop sidebar toggle (if needed in future)
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });
    }
    
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
    
    // Handle nav link clicks
    navLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            const targetId = link.getAttribute('href').substring(1);
            
            // Update active state
            navLinks.forEach(l => l.classList.remove('active'));
            link.classList.add('active');
            
            // Show corresponding section
            showSection(targetId);
            
            // Close mobile sidebar
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('open');
                sidebarOverlay.classList.remove('active');
            }
            
            // Update URL hash without scrolling
            history.pushState(null, null, `#${targetId}`);
        });
    });
    
    // Handle initial load with hash
    const hash = window.location.hash.substring(1);
    if (hash) {
        showSection(hash);
        const activeLink = document.querySelector(`.nav-link[href="#${hash}"]`);
        if (activeLink) {
            activeLink.classList.add('active');
        }
    }
    
    // Handle scroll spy
    initScrollSpy();
}

function showSection(sectionId) {
    const sections = document.querySelectorAll('.content-section');
    sections.forEach(section => {
        if (section.id === sectionId) {
            section.classList.add('active');
        } else {
            section.classList.remove('active');
        }
    });
}

function initScrollSpy() {
    const sections = document.querySelectorAll('.content-section');
    const navLinks = document.querySelectorAll('.nav-link');
    
    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
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
    const copyButtons = document.querySelectorAll('.copy-btn');
    
    copyButtons.forEach(button => {
        button.addEventListener('click', async () => {
            const targetId = button.getAttribute('data-copy-target');
            const codeElement = document.getElementById(targetId);
            
            if (codeElement) {
                try {
                    await navigator.clipboard.writeText(codeElement.textContent);
                    
                    // Show success feedback
                    const originalText = button.textContent;
                    button.textContent = 'Copied!';
                    button.style.backgroundColor = 'var(--success)';
                    
                    setTimeout(() => {
                        button.textContent = originalText;
                        button.style.backgroundColor = '';
                    }, 2000);
                } catch (err) {
                    console.error('Failed to copy:', err);
                    button.textContent = 'Error';
                    button.style.backgroundColor = 'var(--error)';
                    
                    setTimeout(() => {
                        button.textContent = 'Copy';
                        button.style.backgroundColor = '';
                    }, 2000);
                }
            }
        });
    });
}

/**
 * Search Functionality (Placeholder for future implementation)
 */
function initSearch() {
    // TODO: Implement search functionality
    // Could include:
    // - Fuzzy search across all sections
    // - Highlight matching terms
    // - Keyboard shortcuts (Ctrl/Cmd + K)
}

/**
 * Smooth Scroll to Anchor
 */
function smoothScrollTo(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }
}

/**
 * Highlight Code Syntax (Simple implementation)
 * For production, consider using Prism.js or Highlight.js
 */
function highlightCode() {
    const codeBlocks = document.querySelectorAll('pre code');
    
    codeBlocks.forEach(block => {
        const language = block.className.replace('language-', '');
        // Simple syntax highlighting could be added here
        // For now, we'll keep it minimal
    });
}

/**
 * Table of Contents Generator (for sections with many methods)
 */
function generateTOC(sectionId) {
    const section = document.getElementById(sectionId);
    if (!section) return;
    
    const methods = section.querySelectorAll('.method');
    if (methods.length === 0) return;
    
    const toc = document.createElement('div');
    toc.className = 'method-toc';
    toc.innerHTML = '<h4>Methods</h4><ul></ul>';
    
    const ul = toc.querySelector('ul');
    methods.forEach(method => {
        const methodName = method.querySelector('.method-name').textContent;
        const li = document.createElement('li');
        li.innerHTML = `<a href="#method-${methodName}">${methodName}</a>`;
        ul.appendChild(li);
    });
    
    section.insertBefore(toc, section.querySelector('.methods-section'));
}

/**
 * Responsive Table Handler
 */
function handleResponsiveTables() {
    const tables = document.querySelectorAll('.data-table');
    
    tables.forEach(table => {
        const wrapper = document.createElement('div');
        wrapper.className = 'table-wrapper';
        wrapper.style.overflowX = 'auto';
        
        table.parentNode.insertBefore(wrapper, table);
        wrapper.appendChild(table);
    });
}

/**
 * Keyboard Shortcuts
 */
document.addEventListener('keydown', (e) => {
    // Ctrl/Cmd + K: Focus search (when implemented)
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        // document.getElementById('search-input').focus();
    }
    
    // Escape: Close mobile sidebar
    if (e.key === 'Escape') {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
    }
});

/**
 * Performance Optimization: Lazy Load Sections
 */
function lazyLoadSections() {
    const sections = document.querySelectorAll('.content-section:not(.active)');
    
    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    // Load section content if needed
                    observer.unobserve(entry.target);
                }
            });
        },
        {
            rootMargin: '200px',
            threshold: 0
        }
    );
    
    sections.forEach(section => {
        observer.observe(section);
    });
}

/**
 * Analytics Tracking (Optional)
 */
function trackPageView(sectionId) {
    // Add analytics tracking here if needed
    // Example: gtag('event', 'page_view', { page_section: sectionId });
}

/**
 * Print Styles Handler
 */
window.addEventListener('beforeprint', () => {
    document.body.classList.add('printing');
});

window.addEventListener('afterprint', () => {
    document.body.classList.remove('printing');
});

/**
 * Initialize all components on load
 */
function init() {
    handleResponsiveTables();
    lazyLoadSections();
    highlightCode();
}

// Run initialization
init();

/**
 * Utility Functions
 */

/**
 * Debounce function for performance
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Throttle function for performance
 */
function throttle(func, limit) {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

/**
 * Local Storage Helper
 */
const storage = {
    get: (key) => {
        try {
            return JSON.parse(localStorage.getItem(key));
        } catch (e) {
            return null;
        }
    },
    set: (key, value) => {
        try {
            localStorage.setItem(key, JSON.stringify(value));
            return true;
        } catch (e) {
            return false;
        }
    }
};

/**
 * Event Bus for Component Communication
 */
const eventBus = {
    events: {},
    on(event, callback) {
        if (!this.events[event]) {
            this.events[event] = [];
        }
        this.events[event].push(callback);
    },
    off(event, callback) {
        if (this.events[event]) {
            this.events[event] = this.events[event].filter(cb => cb !== callback);
        }
    },
    emit(event, data) {
        if (this.events[event]) {
            this.events[event].forEach(callback => callback(data));
        }
    }
};

// Export for use in other scripts
window.PanchangDocs = {
    smoothScrollTo,
    showSection,
    storage,
    eventBus
};
