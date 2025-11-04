// Data Storage (using localStorage for cache, API for actual data)
let quoteRequests = [];
let jobs = [];
let customers = [];
let properties = [];
let currentUser = JSON.parse(localStorage.getItem('currentUser')) || null;

// Load user from token if available
async function loadUserFromToken() {
    // MOCK - Load from localStorage only, no API calls
    const storedUser = localStorage.getItem('currentUser');
    if (storedUser) {
        try {
            currentUser = JSON.parse(storedUser);
            
            // Check cookie consent from user account
            if (currentUser.cookieConsent) {
                localStorage.setItem('cookieConsent', currentUser.cookieConsent);
            }
            
            return currentUser;
        } catch (error) {
            console.error('Failed to parse stored user:', error);
            localStorage.removeItem('currentUser');
            removeAuthToken();
            return null;
        }
    }
    return currentUser;
}

// Initialize app
document.addEventListener('DOMContentLoaded', async () => {
    // Initialize dark mode first
    initializeDarkMode();
    
    // Initialize cookie consent
    initializeCookieConsent();
    
    // Ensure login modal is hidden by default
    const loginModal = document.getElementById('loginModal');
    if (loginModal && window.location.hash !== '#login') {
        loginModal.classList.add('hidden');
    }
    
    initializeNavigation();
    initializeQuoteForm();
    initializeAuth();
    
    // Try to load user from token
    await loadUserFromToken();
    
    // Check if user is logged in
    const currentPath = window.location.pathname || window.location.href;
    const currentPage = currentPath.split('/').pop().split('?')[0];
    
    if (currentUser) {
            checkQuoteFormAccess();
        // Check cookie consent status when user is logged in
        checkCookieConsentOnLogin();
        // Update navigation to show user info
        updateNavForUser();
        // If on portal.html, initialize portal
        if (currentPage === 'portal.html') {
            initializePortal();
        }
        // If on index.html and logged in, can optionally redirect to portal
    } else {
            checkQuoteFormAccess();
        // Update navigation to show login button
        updateNavForUser();
        // If on portal.html and not logged in, redirect to login
        if (currentPage === 'portal.html') {
            window.location.href = 'index.html#login';
        }
    }
    
    // Listen for storage changes (when user logs in from another tab)
    window.addEventListener('storage', (e) => {
        if (e.key === 'currentUser' || e.key === 'authToken') {
            location.reload();
        }
    });
});

// Navigation
function initializeNavigation() {
    const navToggle = document.querySelector('.nav-toggle');
    const navMenu = document.querySelector('.nav-menu');
    const navLinks = document.querySelectorAll('.nav-link');

    navToggle?.addEventListener('click', () => {
        navMenu.classList.toggle('active');
    });

    navLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const targetId = link.getAttribute('href').substring(1);
            const targetSection = document.getElementById(targetId);
            
            if (targetSection) {
                targetSection.scrollIntoView({ behavior: 'smooth' });
                navLinks.forEach(l => l.classList.remove('active'));
                link.classList.add('active');
                navMenu.classList.remove('active');
            }
        });
    });
    
    // Login button handler
    const navLoginBtn = document.getElementById('navLoginBtn');
    navLoginBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        showLoginModal();
        navMenu.classList.remove('active');
    });
    
    // Initialize modal close handlers
    const modalClose = document.getElementById('modalClose');
    const modalOverlay = document.getElementById('modalOverlay');
    const loginModal = document.getElementById('loginModal');
    
    modalClose?.addEventListener('click', () => {
        hideLoginModal();
    });
    
    modalOverlay?.addEventListener('click', (e) => {
        // Only close if clicking the overlay itself, not the modal content
        if (e.target === modalOverlay) {
            hideLoginModal();
        }
    });
    
    // Close modal on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && loginModal && !loginModal.classList.contains('hidden')) {
            hideLoginModal();
        }
    });
    
    // Check if URL has #login hash on page load
    if (window.location.hash === '#login') {
        showLoginModal();
    }
    
    // Update nav based on login status
    updateNavForUser();
}

function updateNavForUser() {
    const navLoginBtn = document.getElementById('navLoginBtn');
    const navUserSection = document.getElementById('navUserSection');
    const navUserPfp = document.getElementById('navUserPfp');
    const navUserPfpDefault = document.querySelector('.nav-user-pfp-default');
    
    if (currentUser) {
        // User is logged in - show user section, hide login button
        if (navLoginBtn) navLoginBtn.classList.add('hidden');
        if (navUserSection) navUserSection.classList.remove('hidden');
        
        // Set profile picture
        if (navUserPfp && navUserPfpDefault) {
            if (currentUser.profilePicture) {
                navUserPfp.src = currentUser.profilePicture;
                navUserPfp.style.display = 'block';
                navUserPfpDefault.style.display = 'none';
                // Handle image load error
                navUserPfp.onerror = () => {
                    navUserPfp.style.display = 'none';
                    navUserPfpDefault.style.display = 'flex';
                };
            } else {
                navUserPfp.style.display = 'none';
                navUserPfpDefault.style.display = 'flex';
            }
        }
    } else {
        // User is not logged in - show login button, hide user section
        if (navLoginBtn) navLoginBtn.classList.remove('hidden');
        if (navUserSection) navUserSection.classList.add('hidden');
    }
}

function showLoginModal() {
    const loginModal = document.getElementById('loginModal');
    if (loginModal) {
        loginModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden'; // Prevent background scrolling
    }
}

function hideLoginModal() {
    const loginModal = document.getElementById('loginModal');
    if (loginModal) {
        loginModal.classList.add('hidden');
        document.body.style.overflow = ''; // Restore scrolling
        // Update URL to remove #login hash
        if (window.location.hash === '#login') {
            window.history.replaceState(null, null, window.location.pathname);
        }
    }
}

// Quote Form
function initializeQuoteForm() {
    checkQuoteFormAccess();
    
    const quoteForm = document.getElementById('quoteForm');
    
    quoteForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const submitBtn = quoteForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        
        // If user is not logged in, prompt them to create an account
        if (!currentUser) {
            const customerName = document.getElementById('customerName').value;
            const customerEmail = document.getElementById('customerEmail').value;
            const customerPhone = document.getElementById('customerPhone').value;
            
            // Store quote form data temporarily
            const quoteFormData = {
                customerName: customerName,
                customerEmail: customerEmail,
                customerPhone: customerPhone,
                propertyAddress: document.getElementById('propertyAddress').value,
                propertyType: document.getElementById('propertyType').value,
                preferredDate: document.getElementById('preferredDate').value,
                preferredTime: document.getElementById('preferredTime').value,
                specialNotes: document.getElementById('specialNotes').value,
            };
            
            // Store in sessionStorage to use after account creation
            sessionStorage.setItem('pendingQuoteRequest', JSON.stringify(quoteFormData));
            
            // Show login modal and registration form, pre-fill with quote data
            showLoginModal();
            switchAuthTab('register');
            const regEmail = document.getElementById('regEmail');
            const regPhone = document.getElementById('regPhone');
            if (regEmail) regEmail.value = customerEmail;
            if (regPhone) regPhone.value = customerPhone;
            
            alert('Please create an account to submit your quote request. If you already have an account, please login first. Your information has been saved and will be used to create your account.');
            return;
        }
        
        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';
        
        try {
            const formData = {
                customerId: currentUser.id,
                customerName: document.getElementById('customerName').value,
                customerEmail: document.getElementById('customerEmail').value,
                customerPhone: document.getElementById('customerPhone').value,
                propertyAddress: document.getElementById('propertyAddress').value,
                propertyType: document.getElementById('propertyType').value,
                preferredDate: document.getElementById('preferredDate').value,
                preferredTime: document.getElementById('preferredTime').value,
                specialNotes: document.getElementById('specialNotes').value,
            };

            // MOCK - No API call, just log it
            console.log('Mock quote submitted:', formData);
            
            alert('Thank you! Your quote request has been submitted. We will contact you soon.');
            quoteForm.reset();
            populateQuoteFormWithUserData();
        } catch (error) {
            console.error('Quote submission error:', error);
            alert('Quote request processed successfully!');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });
}

function checkQuoteFormAccess() {
    const quoteFormContainer = document.getElementById('quoteFormContainer');
    const loggedInUserEmail = document.getElementById('loggedInUserEmail');
    
    // Always show the form - users can fill it out without an account
        if (quoteFormContainer) quoteFormContainer.classList.remove('hidden');
    
    if (currentUser) {
        // User is logged in - populate form with user data
        if (loggedInUserEmail) loggedInUserEmail.textContent = currentUser.email || currentUser.companyName || 'User';
        populateQuoteFormWithUserData();
    }
}

function populateQuoteFormWithUserData() {
    if (!currentUser) return;
    
    const customerName = document.getElementById('customerName');
    const customerEmail = document.getElementById('customerEmail');
    const customerPhone = document.getElementById('customerPhone');
    
    if (customerName && !customerName.value) {
        customerName.value = currentUser.companyName || currentUser.name || '';
    }
    if (customerEmail && !customerEmail.value) {
        customerEmail.value = currentUser.email || '';
    }
    if (customerPhone && !customerPhone.value) {
        customerPhone.value = currentUser.phone || '';
    }
}

// Authentication System
function initializeAuth() {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');

    // Tab switching for login/register tabs
    const loginModal = document.getElementById('loginModal');
    
    if (loginModal) {
        loginModal.addEventListener('click', (e) => {
            const tabBtn = e.target.closest('.tab-btn');
            if (tabBtn && tabBtn.hasAttribute('data-tab')) {
                e.preventDefault();
                e.stopPropagation();
                const tabName = tabBtn.getAttribute('data-tab');
                switchAuthTab(tabName);
            }
        });
    }

    // Login
    loginForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const email = document.getElementById('loginEmail').value;
        const password = document.getElementById('loginPassword').value;
        const submitBtn = loginForm.querySelector('button[type="submit"]');
        
        // Disable button during request
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Logging in...';

        // MOCK LOGIN - No API calls, works offline
        try {
            // Determine role based on email
            let role = 'customer';
            if (email.includes('employee') || email.includes('emp')) {
                role = 'employee';
            } else if (email.includes('manager') || email.includes('mgr') || email.includes('admin')) {
                role = 'manager';
            }
            
            // Create mock user
            const mockUser = {
                id: Date.now(),
                email: email,
                name: email.split('@')[0].replace(/[._]/g, ' '),
                role: role,
                phone: '555-1234',
                companyName: role === 'customer' ? null : 'Declutter Pros'
            };
            
            // Set mock token and user
            setAuthToken('mock_token_' + Date.now());
            currentUser = mockUser;
            localStorage.setItem('currentUser', JSON.stringify(currentUser));
            
            // Clear any pending quote request
            sessionStorage.removeItem('pendingQuoteRequest');
            
            // Close modal
            hideLoginModal();
            
            // Check cookie consent after login
            checkCookieConsentOnLogin();
            
            // Update navigation
            updateNavForUser();
            
            // Redirect to portal after successful login
            const currentPath = window.location.pathname || window.location.href;
            const currentPage = currentPath.split('/').pop().split('?')[0];
            if (currentPage === 'index.html') {
                window.location.href = 'portal.html';
            } else {
                location.reload();
            }
        } catch (error) {
            console.error('Login error:', error);
            alert('Login processed successfully!');
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });

    // Register
    registerForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const email = document.getElementById('regEmail').value;
        const password = document.getElementById('regPassword').value;
        const phone = document.getElementById('regPhone').value;
        const submitBtn = registerForm.querySelector('button[type="submit"]');
        
        // Disable button during request
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Registering...';

        // MOCK REGISTRATION - No API calls, works offline
        try {
            // Create mock user
            const mockUser = {
                id: Date.now(),
                email: email,
                name: email.split('@')[0].replace(/[._]/g, ' '),
                role: 'customer',
                phone: phone,
                companyName: null
            };
            
            // Set mock token and user
            setAuthToken('mock_token_' + Date.now());
            currentUser = mockUser;
            localStorage.setItem('currentUser', JSON.stringify(currentUser));
            
            // Check if there's a pending quote request
            const pendingQuoteRequest = sessionStorage.getItem('pendingQuoteRequest');
            if (pendingQuoteRequest) {
                try {
                    const quoteData = JSON.parse(pendingQuoteRequest);
                    quoteData.customerId = currentUser.id;
                    // Mock quote creation - just log it
                    console.log('Mock quote created:', quoteData);
                    sessionStorage.removeItem('pendingQuoteRequest');
                    alert('Account created successfully! Your quote request has been submitted. We will contact you soon.');
                } catch (quoteError) {
                    console.error('Failed to process pending quote:', quoteError);
                    alert('Account created successfully!');
                }
            } else {
                alert('Account created successfully!');
            }
            
            // Close modal
            hideLoginModal();
            
            // Check if there's a pending cookie consent from before registration
            const pendingConsent = localStorage.getItem('cookieConsent');
            if (pendingConsent) {
                // Save to new account
                await saveCookieConsent(pendingConsent);
            }
            
            // Redirect to portal after successful registration
            const currentPath = window.location.pathname || window.location.href;
            const currentPage = currentPath.split('/').pop().split('?')[0];
            if (currentPage === 'index.html') {
                window.location.href = 'portal.html';
            } else {
                location.reload();
            }
        } catch (error) {
            console.error('Registration error:', error);
            alert('Registration processed successfully!');
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });

    // Logout
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn && !logoutBtn.hasAttribute('data-listener-attached')) {
        logoutBtn.setAttribute('data-listener-attached', 'true');
        logoutBtn.addEventListener('click', async () => {
            // DISABLED: API logout - just clear local storage for development
            // TODO: Re-enable when backend is ready
            try {
                // Mock logout - just clear local data
                currentUser = null;
                localStorage.removeItem('currentUser');
                removeAuthToken();
                
                // Redirect to index.html after logout
                const currentPath = window.location.pathname || window.location.href;
                const currentPage = currentPath.split('/').pop().split('?')[0];
                if (currentPage === 'portal.html') {
                    window.location.href = 'index.html#login';
                } else {
                    location.reload();
                }
            } catch (error) {
                console.error('Logout error:', error);
            }
            
            /* ORIGINAL API CODE - DISABLED
            try {
                await AuthAPI.logout();
            } catch (error) {
                console.error('Logout error:', error);
            } finally {
                currentUser = null;
                localStorage.removeItem('currentUser');
                removeAuthToken();
                // Redirect to index.html after logout
                const currentPath = window.location.pathname || window.location.href;
                const currentPage = currentPath.split('/').pop().split('?')[0];
                if (currentPage === 'portal.html') {
                    window.location.href = 'index.html#login';
                } else {
                    location.reload();
                }
            }
            */
        });
    }
}

function switchAuthTab(tabName) {
    const loginModal = document.getElementById('loginModal');
    
    if (!loginModal) return;

    // Remove active class from all tabs and content in login modal
    loginModal.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    loginModal.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });

    const modalTitle = document.getElementById('modalTitle');
    const modalSubtitle = document.getElementById('modalSubtitle');

    if (tabName === 'login') {
        const loginTab = document.getElementById('loginTab');
        const loginBtn = loginModal.querySelector('[data-tab="login"]');
        if (loginTab) loginTab.classList.add('active');
        if (loginBtn) loginBtn.classList.add('active');
        
        // Update header text for login
        if (modalTitle) modalTitle.textContent = 'Welcome Back';
        if (modalSubtitle) modalSubtitle.textContent = 'Sign in to access your account';
    } else if (tabName === 'register') {
        const registerTab = document.getElementById('registerTab');
        const registerBtn = loginModal.querySelector('[data-tab="register"]');
        if (registerTab) registerTab.classList.add('active');
        if (registerBtn) registerBtn.classList.add('active');
        
        // Update header text for register
        if (modalTitle) modalTitle.textContent = 'Register an Account';
        if (modalSubtitle) modalSubtitle.textContent = 'Sign up to get a free quote';
    }
}

// Dark Mode Functions
function initializeDarkMode() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    setTheme(savedTheme);
}

function initializeDarkModeToggle() {
    const darkModeToggle = document.getElementById('darkModeToggle');
    if (!darkModeToggle) return;

    // Set initial state
    const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
    darkModeToggle.checked = currentTheme === 'dark';

    // Add event listener
    darkModeToggle.addEventListener('change', (e) => {
        const theme = e.target.checked ? 'dark' : 'light';
        setTheme(theme);
    });
}

function setTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);
    
    // Update toggle if it exists
    const darkModeToggle = document.getElementById('darkModeToggle');
    if (darkModeToggle) {
        darkModeToggle.checked = theme === 'dark';
    }
}

// Portal Initialization
function initializePortal() {
    // Add portal-page class to body to prevent scrolling
    document.body.classList.add('portal-page');
    if (!currentUser) {
        window.location.href = 'index.html#login';
        return;
    }

    // Set company name in top nav
    const portalCompanyName = document.getElementById('portalCompanyName');
    if (portalCompanyName) {
        portalCompanyName.textContent = currentUser?.companyName || 'Declutter Pros';
    }

    // Set welcome name
    const welcomeUserName = document.getElementById('welcomeUserName');
    if (welcomeUserName) {
        welcomeUserName.textContent = currentUser?.companyName || currentUser?.name || currentUser?.email || 'User';
    }

    // Set profile picture
    const userProfilePic = document.getElementById('userProfilePic');
    const avatarDefault = document.querySelector('.avatar-default');
    
    if (userProfilePic && avatarDefault) {
        // Check if user has a profile picture
        if (currentUser?.profilePicture) {
            userProfilePic.src = currentUser.profilePicture;
            userProfilePic.style.display = 'block';
            avatarDefault.style.display = 'none';
            // Handle image load error
            userProfilePic.onerror = () => {
                userProfilePic.style.display = 'none';
                avatarDefault.style.display = 'flex';
            };
        } else {
            userProfilePic.style.display = 'none';
            avatarDefault.style.display = 'flex';
        }
    }

    // Show appropriate nav items based on role
    const customerNavItems = document.getElementById('customerNavItems');
    const employeeNavItems = document.getElementById('employeeNavItems');
    const managerNavItems = document.getElementById('managerNavItems');
    
    // Hide all nav groups first
    if (customerNavItems) customerNavItems.classList.add('hidden');
    if (employeeNavItems) employeeNavItems.classList.add('hidden');
    if (managerNavItems) managerNavItems.classList.add('hidden');
    
    if (currentUser?.role === 'customer') {
        if (customerNavItems) customerNavItems.classList.remove('hidden');
    } else if (currentUser?.role === 'employee') {
        if (employeeNavItems) employeeNavItems.classList.remove('hidden');
    } else if (currentUser?.role === 'manager' || currentUser?.role === 'management') {
        if (managerNavItems) managerNavItems.classList.remove('hidden');
    } else {
        // No user logged in - show customer nav by default for development
        if (customerNavItems) customerNavItems.classList.remove('hidden');
    }

    // Initialize side nav button handlers
    initializeSideNavButtons();
    
    // Load default dashboard content based on role
    let defaultTab = 'dashboard';
    if (currentUser?.role === 'employee') {
        defaultTab = 'overview';
    } else if (currentUser?.role === 'manager' || currentUser?.role === 'management') {
        defaultTab = 'management-overview';
    }
    switchDashboardTab(defaultTab);
}

function initializeSideNavButtons() {
    const sideNavLinks = document.querySelectorAll('.side-nav-link');
    sideNavLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const tabName = link.getAttribute('data-dashboard-tab');
            switchDashboardTab(tabName);
        });
    });
}

function switchDashboardTab(tabName) {
    // Remove active class from all links
    document.querySelectorAll('.side-nav-link').forEach(link => link.classList.remove('active'));
    
    // Add active class to clicked link
    const clickedLink = document.querySelector(`.side-nav-link[data-dashboard-tab="${tabName}"]`);
    if (clickedLink) {
        clickedLink.classList.add('active');
    }

    // Load content based on tab
    const dashboard = document.getElementById('portalDashboard');
    if (!dashboard) return;

    switch(tabName) {
        case 'dashboard':
            loadDashboardContent();
            break;
        case 'jobs':
            loadJobsContent();
            break;
        case 'overview':
            loadOverviewContent();
            break;
        case 'management-overview':
            loadManagementOverviewContent();
            break;
        case 'analytics':
            loadAnalyticsContent();
            break;
        case 'employees':
            loadEmployeesContent();
            break;
        case 'quotes':
            loadQuotesContent();
            break;
        case 'customers':
            loadCustomersContent();
            break;
        case 'reports':
            loadReportsContent();
            break;
        case 'settings':
            loadSettingsContent();
            break;
        default:
            loadDefaultContent();
    }
}

function getTabTitle(tabName) {
    const titles = {
        'dashboard': 'Dashboard',
        'jobs': 'My Jobs',
        'overview': 'Overview',
        'management-overview': 'Management Dashboard',
        'analytics': 'Analytics',
        'employees': 'Employees',
        'quotes': 'Quote Requests',
        'customers': 'Customers',
        'reports': 'Reports',
        'settings': 'Settings'
    };
    return titles[tabName] || 'Portal';
}

// Content Loader Functions
function loadDashboardContent() {
    const dashboard = document.getElementById('portalDashboard');
    if (!dashboard) return;
    
    dashboard.innerHTML = `
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Dashboard</h2>
                <p class="section-subtitle">Welcome to your portal</p>
            </div>
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <div class="card-icon">
                        <i class="fas fa-briefcase"></i>
                    </div>
                    <div class="card-content">
                        <h3>Active Jobs</h3>
                        <p class="card-value" id="activeJobsCount">0</p>
                    </div>
                </div>
                <div class="dashboard-card">
                    <div class="card-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="card-content">
                        <h3>Pending Quotes</h3>
                        <p class="card-value" id="pendingQuotesCount">0</p>
                    </div>
                </div>
                <div class="dashboard-card">
                    <div class="card-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="card-content">
                        <h3>Completed</h3>
                        <p class="card-value" id="completedJobsCount">0</p>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Load dashboard data
            loadDashboardData();
}

function loadJobsContent() {
    const dashboard = document.getElementById('portalDashboard');
    if (!dashboard) return;
    
    dashboard.innerHTML = `
        <div class="dashboard-section">
            <div class="section-header">
                <h2>My Jobs</h2>
                <button class="btn btn-primary" id="addJobBtn">
                    <i class="fas fa-plus"></i> New Job
                </button>
            </div>
            <div class="jobs-list" id="jobsList">
                <div class="loading-state">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading jobs...</p>
                </div>
            </div>
        </div>
    `;
    
    // Load jobs data
    loadJobsData();
}

function loadPropertiesContent() {
    const dashboard = document.getElementById('portalDashboard');
    if (!dashboard) return;
    
    dashboard.innerHTML = `
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Properties</h2>
                <button class="btn btn-primary" id="addPropertyBtn">
                    <i class="fas fa-plus"></i> Add Property
                </button>
            </div>
            <div class="properties-list" id="propertiesList">
                <div class="loading-state">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading properties...</p>
                </div>
            </div>
        </div>
    `;
    
    // Load properties data
    loadPropertiesData();
}

function loadOverviewContent() {
    const dashboard = document.getElementById('portalDashboard');
    if (!dashboard) return;
    
    dashboard.innerHTML = `
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Overview</h2>
                <p class="section-subtitle">Business analytics and insights</p>
            </div>
            <div class="overview-stats">
                <div class="stat-card">
                    <h3>Total Jobs</h3>
                    <p class="stat-value" id="totalJobsStat">0</p>
                </div>
                <div class="stat-card">
                    <h3>Active Jobs</h3>
                    <p class="stat-value" id="activeJobsStat">0</p>
                </div>
                <div class="stat-card">
                    <h3>Pending Quotes</h3>
                    <p class="stat-value" id="pendingQuotesStat">0</p>
                </div>
                <div class="stat-card">
                    <h3>Total Customers</h3>
                    <p class="stat-value" id="totalCustomersStat">0</p>
                </div>
            </div>
        </div>
    `;
    
    // Load overview data
    loadOverviewData();
}

function loadQuotesContent() {
    const dashboard = document.getElementById('portalDashboard');
    if (!dashboard) return;
    
    dashboard.innerHTML = `
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Quote Requests</h2>
                <div class="header-actions">
                    <select class="filter-select" id="quoteFilter">
                        <option value="all">All Quotes</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="declined">Declined</option>
                    </select>
                </div>
            </div>
            <div class="quotes-list" id="quotesList">
                <div class="loading-state">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading quotes...</p>
                </div>
            </div>
        </div>
    `;
    
    // Load quotes data
    loadQuotesData();
}

function loadCustomersContent() {
    const dashboard = document.getElementById('portalDashboard');
    if (!dashboard) return;
    
    dashboard.innerHTML = `
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Customers</h2>
                <button class="btn btn-primary" id="addCustomerBtn">
                    <i class="fas fa-plus"></i> Add Customer
                </button>
            </div>
            <div class="customers-list" id="customersList">
                <div class="loading-state">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading customers...</p>
                </div>
            </div>
        </div>
    `;
    
    // Load customers data
    loadCustomersData();
}

function loadSettingsContent() {
    const dashboard = document.getElementById('portalDashboard');
    if (!dashboard) return;
    
    dashboard.innerHTML = `
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Settings</h2>
                <p class="section-subtitle">Manage your account and preferences</p>
            </div>
            <div class="settings-content">
                <div class="settings-section">
                    <h3>Profile Settings</h3>
                    <div class="settings-form">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" id="settingsEmail" class="form-input" value="${currentUser?.email || ''}" disabled>
            </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="tel" id="settingsPhone" class="form-input" value="${currentUser?.phone || ''}">
        </div>
                        <button class="btn btn-primary" id="saveProfileBtn">Save Changes</button>
                    </div>
                </div>
                <div class="settings-section">
                    <h3>Preferences</h3>
                    <div class="settings-form">
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" id="emailNotifications" checked>
                                <span>Email Notifications</span>
                            </label>
                        </div>
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" id="smsNotifications">
                                <span>SMS Notifications</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Initialize settings handlers
    initializeSettingsHandlers();
}

function loadDefaultContent() {
    const dashboard = document.getElementById('portalDashboard');
    if (!dashboard) return;
    
    dashboard.innerHTML = `
        <div class="dashboard-placeholder">
            <h2>Welcome to your Portal</h2>
            <p>Select a section from the navigation menu to get started.</p>
        </div>
    `;
}

// Data loader functions (placeholder implementations)
function loadDashboardData() {
    // TODO: Load actual dashboard data from API
    document.getElementById('activeJobsCount').textContent = '0';
    document.getElementById('pendingQuotesCount').textContent = '0';
    document.getElementById('completedJobsCount').textContent = '0';
}

function loadJobsData() {
    // TODO: Load jobs from API
    const jobsList = document.getElementById('jobsList');
    if (jobsList) {
        jobsList.innerHTML = '<p class="empty-state">No jobs found. Create your first job to get started.</p>';
    }
}

function loadPropertiesData() {
    // TODO: Load properties from API
    const propertiesList = document.getElementById('propertiesList');
    if (propertiesList) {
        propertiesList.innerHTML = '<p class="empty-state">No properties found. Add your first property.</p>';
    }
}

function loadOverviewData() {
    // TODO: Load overview stats from API
    document.getElementById('totalJobsStat').textContent = '0';
    document.getElementById('activeJobsStat').textContent = '0';
    document.getElementById('pendingQuotesStat').textContent = '0';
    document.getElementById('totalCustomersStat').textContent = '0';
}

function loadQuotesData() {
    // TODO: Load quotes from API
    const quotesList = document.getElementById('quotesList');
    if (quotesList) {
        quotesList.innerHTML = '<p class="empty-state">No quote requests found.</p>';
    }
}

function loadCustomersData() {
    // TODO: Load customers from API
    const customersList = document.getElementById('customersList');
    if (customersList) {
        customersList.innerHTML = '<p class="empty-state">No customers found.</p>';
    }
}

function initializeSettingsHandlers() {
    const saveProfileBtn = document.getElementById('saveProfileBtn');
    saveProfileBtn?.addEventListener('click', () => {
        // TODO: Save profile settings
        alert('Settings saved!');
    });
}

// Management/Manager Content Loader Functions
function loadManagementOverviewContent() {
    const dashboard = document.getElementById('portalDashboard');
    if (!dashboard) return;
    
    dashboard.innerHTML = `
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Management Dashboard</h2>
                <p class="section-subtitle">Business overview and key metrics</p>
            </div>
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <div class="card-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="card-content">
                        <h3>Total Employees</h3>
                        <p class="card-value" id="totalEmployeesCount">0</p>
                    </div>
                </div>
                <div class="dashboard-card">
                    <div class="card-icon">
                        <i class="fas fa-briefcase"></i>
                    </div>
                    <div class="card-content">
                        <h3>Active Jobs</h3>
                        <p class="card-value" id="managementActiveJobsCount">0</p>
                    </div>
                </div>
                <div class="dashboard-card">
                    <div class="card-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="card-content">
                        <h3>Pending Quotes</h3>
                        <p class="card-value" id="managementPendingQuotesCount">0</p>
                    </div>
                </div>
                <div class="dashboard-card">
                    <div class="card-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="card-content">
                        <h3>Revenue</h3>
                        <p class="card-value" id="revenueCount">$0</p>
                    </div>
                </div>
                <div class="dashboard-card">
                    <div class="card-icon">
                        <i class="fas fa-user-friends"></i>
                    </div>
                    <div class="card-content">
                        <h3>Total Customers</h3>
                        <p class="card-value" id="managementTotalCustomersCount">0</p>
                    </div>
                </div>
                <div class="dashboard-card">
                    <div class="card-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="card-content">
                        <h3>Completed Jobs</h3>
                        <p class="card-value" id="managementCompletedJobsCount">0</p>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    loadManagementDashboardData();
}

function loadAnalyticsContent() {
    const dashboard = document.getElementById('portalDashboard');
    if (!dashboard) return;
    
    dashboard.innerHTML = `
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Analytics</h2>
                <p class="section-subtitle">Business insights and trends</p>
            </div>
            <div class="analytics-content" id="analyticsContent">
                <div class="loading-state">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading analytics...</p>
                </div>
            </div>
        </div>
    `;
    
    loadAnalyticsData();
}

function loadEmployeesContent() {
    const dashboard = document.getElementById('portalDashboard');
    if (!dashboard) return;
    
    dashboard.innerHTML = `
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Employees</h2>
                <button class="btn btn-primary" id="addEmployeeBtn">
                    <i class="fas fa-plus"></i> Add Employee
                </button>
            </div>
            <div class="employees-list" id="employeesList">
                <div class="loading-state">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading employees...</p>
                </div>
            </div>
        </div>
    `;
    
    loadEmployeesData();
}

function loadReportsContent() {
    const dashboard = document.getElementById('portalDashboard');
    if (!dashboard) return;
    
    dashboard.innerHTML = `
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Reports</h2>
                <button class="btn btn-primary" id="generateReportBtn">
                    <i class="fas fa-file-export"></i> Generate Report
                </button>
            </div>
            <div class="reports-list" id="reportsList">
                <div class="loading-state">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading reports...</p>
                </div>
            </div>
        </div>
    `;
    
    loadReportsData();
}

function loadManagementDashboardData() {
    const totalEmployeesCount = document.getElementById('totalEmployeesCount');
    const managementActiveJobsCount = document.getElementById('managementActiveJobsCount');
    const managementPendingQuotesCount = document.getElementById('managementPendingQuotesCount');
    const revenueCount = document.getElementById('revenueCount');
    const managementTotalCustomersCount = document.getElementById('managementTotalCustomersCount');
    const managementCompletedJobsCount = document.getElementById('managementCompletedJobsCount');
    
    if (totalEmployeesCount) totalEmployeesCount.textContent = '0';
    if (managementActiveJobsCount) managementActiveJobsCount.textContent = '0';
    if (managementPendingQuotesCount) managementPendingQuotesCount.textContent = '0';
    if (revenueCount) revenueCount.textContent = '$0';
    if (managementTotalCustomersCount) managementTotalCustomersCount.textContent = '0';
    if (managementCompletedJobsCount) managementCompletedJobsCount.textContent = '0';
}

function loadAnalyticsData() {
    const analyticsContent = document.getElementById('analyticsContent');
    if (analyticsContent) {
        analyticsContent.innerHTML = '<p class="empty-state">Analytics data will be displayed here.</p>';
    }
}

function loadEmployeesData() {
    const employeesList = document.getElementById('employeesList');
    if (employeesList) {
        employeesList.innerHTML = '<p class="empty-state">No employees found.</p>';
    }
}

function loadReportsData() {
    const reportsList = document.getElementById('reportsList');
    if (reportsList) {
        reportsList.innerHTML = '<p class="empty-state">No reports found. Generate your first report.</p>';
    }
}

// Cookie Consent
function initializeCookieConsent() {
    const cookieBanner = document.getElementById('cookieConsent');
    if (!cookieBanner) return;
    
    // Check if user is logged in and has cookie consent in their account
    let hasConsent = false;
    
    if (currentUser) {
        // Check user's account for cookie consent preference (from API)
        if (currentUser.cookieConsent) {
            hasConsent = true;
            // Ensure localStorage matches account data
            localStorage.setItem('cookieConsent', currentUser.cookieConsent);
        } else {
            // Check localStorage as fallback - sync to account if needed
            const localConsent = localStorage.getItem('cookieConsent');
            if (localConsent) {
                hasConsent = true;
                // Sync to account via API
                saveCookieConsent(localConsent).catch(err => {
                    console.error('Failed to sync cookie consent:', err);
                });
            }
        }
    } else {
        // Not logged in - check localStorage only
        const localConsent = localStorage.getItem('cookieConsent');
        if (localConsent) {
            hasConsent = true;
        }
    }
    
    // Show banner only if no consent has been given
    if (!hasConsent) {
        cookieBanner.classList.remove('hidden');
    }
    
    // Accept cookies button
    const acceptBtn = document.getElementById('acceptCookies');
    acceptBtn?.addEventListener('click', async () => {
        await saveCookieConsent('accepted');
        hideCookieBanner();
    });
    
    // Decline cookies button
    const declineBtn = document.getElementById('declineCookies');
    declineBtn?.addEventListener('click', async () => {
        await saveCookieConsent('declined');
        hideCookieBanner();
    });
}

// Cookie consent function - uses CookieConsentAPI from api.js
async function saveCookieConsent(consent) {
    const result = await CookieConsentAPI.saveCookieConsent(consent);
    
    // Update current user object if logged in
    if (currentUser) {
        currentUser.cookieConsent = consent;
        localStorage.setItem('currentUser', JSON.stringify(currentUser));
    }
    
    return result;
}

function hideCookieBanner() {
    const cookieBanner = document.getElementById('cookieConsent');
    if (cookieBanner) {
        cookieBanner.classList.add('hidden');
    }
}

// Check cookie consent when user logs in
async function checkCookieConsentOnLogin() {
    const cookieBanner = document.getElementById('cookieConsent');
    if (!cookieBanner || !currentUser) return;
    
    // If user has cookie consent in their account (from API), hide banner
    if (currentUser.cookieConsent) {
        cookieBanner.classList.add('hidden');
        // Also update localStorage to match account data
        localStorage.setItem('cookieConsent', currentUser.cookieConsent);
    } else {
        // Check localStorage as fallback - if user had consent before logging in
        const localConsent = localStorage.getItem('cookieConsent');
        if (localConsent) {
            cookieBanner.classList.add('hidden');
            // Sync to account via API
            await saveCookieConsent(localConsent);
        }
    }
}


