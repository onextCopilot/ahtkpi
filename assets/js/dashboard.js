// Dashboard interactions and animations - ENHANCED VERSION
document.addEventListener('DOMContentLoaded', function () {
    // Initialize all features
    initAnimations();
    initTableFeatures();
    initMobileMenu();
    initScrollEffects();
});

// ========================================
// ANIMATIONS
// ========================================

function initAnimations() {
    // Animate stat numbers with count-up effect
    animateStatNumbers();

    // Add ripple effect to buttons
    addRippleEffects();

    // Animate cards on scroll
    animateOnScroll();

    // Add hover effects to navigation
    enhanceNavigation();
}

// Enhanced stat number animation with easing
function animateStatNumbers() {
    const statNumbers = document.querySelectorAll('.stat-number');

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const stat = entry.target;
                const finalValue = stat.textContent;

                // Check if it's a number or percentage
                const isPercentage = finalValue.includes('%');
                const numericValue = parseFloat(finalValue.replace(/[^0-9.]/g, ''));

                if (!isNaN(numericValue)) {
                    animateValue(stat, 0, numericValue, 1500, isPercentage);
                    observer.unobserve(stat);
                }
            }
        });
    }, { threshold: 0.5 });

    statNumbers.forEach(stat => observer.observe(stat));
}

function animateValue(element, start, end, duration, isPercentage) {
    const startTime = performance.now();

    function update(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);

        // Easing function for smooth animation
        const easeOutQuart = 1 - Math.pow(1 - progress, 4);
        const current = start + (end - start) * easeOutQuart;

        if (isPercentage) {
            element.textContent = current.toFixed(1) + '%';
        } else if (end >= 1000) {
            element.textContent = Math.floor(current).toLocaleString();
        } else {
            element.textContent = Math.floor(current);
        }

        if (progress < 1) {
            requestAnimationFrame(update);
        } else {
            // Ensure final value is exact
            element.textContent = isPercentage ? end.toFixed(1) + '%' :
                end >= 1000 ? Math.floor(end).toLocaleString() :
                    Math.floor(end);
        }
    }

    requestAnimationFrame(update);
}

// ========================================
// TABLE FEATURES
// ========================================

function initTableFeatures() {
    // Add hover effects to table rows
    const tableRows = document.querySelectorAll('.data-table tbody tr');

    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function () {
            this.style.transform = 'scale(1.01)';
        });

        row.addEventListener('mouseleave', function () {
            this.style.transform = 'scale(1)';
        });
    });

    // Add click animation to table rows
    tableRows.forEach(row => {
        row.addEventListener('click', function (e) {
            if (!e.target.closest('button, a')) {
                this.style.transform = 'scale(0.98)';
                setTimeout(() => {
                    this.style.transform = 'scale(1.01)';
                }, 100);
            }
        });
    });
}

// ========================================
// RIPPLE EFFECT
// ========================================

function addRippleEffects() {
    const buttons = document.querySelectorAll('button, .btn-primary, .nav-item');

    buttons.forEach(button => {
        button.addEventListener('click', function (e) {
            createRipple(e, this);
        });
    });
}

function createRipple(event, element) {
    const ripple = document.createElement('span');
    const rect = element.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const x = event.clientX - rect.left - size / 2;
    const y = event.clientY - rect.top - size / 2;

    ripple.style.width = ripple.style.height = size + 'px';
    ripple.style.left = x + 'px';
    ripple.style.top = y + 'px';
    ripple.classList.add('ripple');

    element.appendChild(ripple);

    setTimeout(() => {
        ripple.remove();
    }, 600);
}

// ========================================
// SCROLL EFFECTS
// ========================================

function animateOnScroll() {
    const cards = document.querySelectorAll('.stat-card, .table-card');

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                setTimeout(() => {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }, index * 100);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    cards.forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
        observer.observe(card);
    });
}

function initScrollEffects() {
    // Smooth scroll behavior
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

// ========================================
// NAVIGATION ENHANCEMENTS
// ========================================

function enhanceNavigation() {
    const navItems = document.querySelectorAll('.nav-item');

    navItems.forEach(item => {
        item.addEventListener('click', function (e) {
            // Remove active class from all items
            navItems.forEach(nav => nav.classList.remove('active'));

            // Add active class to clicked item
            if (!this.classList.contains('logout')) {
                this.classList.add('active');
            }
        });

        // Add icon animation on hover
        item.addEventListener('mouseenter', function () {
            const icon = this.querySelector('svg');
            if (icon) {
                icon.style.transform = 'scale(1.1) rotate(5deg)';
            }
        });

        item.addEventListener('mouseleave', function () {
            const icon = this.querySelector('svg');
            if (icon) {
                icon.style.transform = 'scale(1) rotate(0deg)';
            }
        });
    });
}

// ========================================
// MOBILE MENU
// ========================================

function initMobileMenu() {
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');

    // Create toggle button for mobile
    const toggleBtn = document.createElement('button');
    toggleBtn.className = 'mobile-menu-toggle';
    toggleBtn.innerHTML = `
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M3 12H21M3 6H21M3 18H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    `;

    const topBar = document.querySelector('.top-bar');
    if (topBar && window.innerWidth <= 1024) {
        topBar.insertBefore(toggleBtn, topBar.firstChild);

        toggleBtn.addEventListener('click', function () {
            sidebar.classList.toggle('mobile-open');
            document.body.classList.toggle('sidebar-open');
        });

        // Close sidebar when clicking outside
        mainContent.addEventListener('click', function () {
            if (sidebar.classList.contains('mobile-open')) {
                sidebar.classList.remove('mobile-open');
                document.body.classList.remove('sidebar-open');
            }
        });
    }
}

// ========================================
// UTILITY FUNCTIONS
// ========================================

// Add loading state to buttons
function addLoadingState(button, isLoading) {
    if (isLoading) {
        button.disabled = true;
        button.innerHTML = `
            <svg class="spinner" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity="0.25"/>
                <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
            </svg>
            <span>Loading...</span>
        `;
    } else {
        button.disabled = false;
    }
}

// Toast notification system
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;

    document.body.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('show');
    }, 10);

    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ========================================
// DYNAMIC STYLES
// ========================================

const style = document.createElement('style');
style.textContent = `
    .ripple {
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.3);
        transform: scale(0);
        animation: ripple-animation 0.6s ease-out;
        pointer-events: none;
    }
    
    @keyframes ripple-animation {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
    
    button, .btn-primary, .nav-item {
        position: relative;
        overflow: hidden;
    }
    
    .mobile-menu-toggle {
        display: none;
        width: 40px;
        height: 40px;
        background: rgba(99, 102, 241, 0.1);
        border: 1px solid rgba(99, 102, 241, 0.2);
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-right: 1rem;
    }
    
    .mobile-menu-toggle svg {
        width: 24px;
        height: 24px;
        color: var(--primary-light);
    }
    
    .mobile-menu-toggle:hover {
        background: rgba(99, 102, 241, 0.2);
        transform: scale(1.05);
    }
    
    @media (max-width: 1024px) {
        .mobile-menu-toggle {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .sidebar.mobile-open {
            transform: translateX(0);
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.3);
        }
        
        body.sidebar-open::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 99;
        }
    }
    
    .spinner {
        width: 20px;
        height: 20px;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    .toast {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        padding: 1rem 1.5rem;
        background: rgba(30, 41, 59, 0.95);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(148, 163, 184, 0.1);
        border-radius: 12px;
        color: var(--text-primary);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: 1000;
    }
    
    .toast.show {
        transform: translateY(0);
        opacity: 1;
    }
    
    .toast-success {
        border-left: 3px solid var(--success-color);
    }
    
    .toast-error {
        border-left: 3px solid var(--error-color);
    }
    
    .toast-warning {
        border-left: 3px solid var(--warning-color);
    }
    
    .toast-info {
        border-left: 3px solid var(--info-color);
    }
`;
document.head.appendChild(style);

// ========================================
// PERFORMANCE OPTIMIZATION
// ========================================

// Debounce function for scroll events
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

// Throttle function for resize events
function throttle(func, limit) {
    let inThrottle;
    return function () {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

console.log('✨ Dashboard Enhanced - All animations loaded!');
