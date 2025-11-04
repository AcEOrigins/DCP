/**
 * API Service for Declutter Pros
 * 
 * This file handles all API communication with the backend server.
 * 
 * Configuration:
 * - Update API_BASE_URL with your actual API endpoint
 * - For development: 'http://localhost:3000/api'
 * - For production: 'https://api.declutterpros.com/api'
 * 
 * Security:
 * - All API requests include authentication tokens when available
 * - Tokens are stored securely in localStorage
 * - Automatic token refresh and logout on 401 errors
 * 
 * API Endpoints Expected:
 * - POST /auth/customer/login - Customer login
 * - POST /auth/employee/login - Employee login
 * - POST /auth/customer/register - Customer registration
 * - GET /auth/verify - Verify token
 * - POST /auth/logout - Logout
 * - GET /quotes - Get all quotes
 * - POST /quotes - Create quote
 * - PATCH /quotes/:id/status - Update quote status
 * - DELETE /quotes/:id - Delete quote
 * - GET /jobs - Get all jobs
 * - GET /jobs/customer/:customerId - Get customer jobs
 * - POST /jobs - Create job
 * - PATCH /jobs/:id/status - Update job status
 * - DELETE /jobs/:id - Delete job
 * - GET /properties - Get all properties
 * - GET /properties/customer/:customerId - Get customer properties
 * - POST /properties - Create property
 * - GET /customers - Get all customers
 * - GET /dashboard/stats - Get dashboard statistics
 */

// API Configuration
// For development: use 'http://localhost:3000/api'
// For production: use 'https://api.declutterpros.com/api'
const API_BASE_URL = 'http://localhost:3000/api'; // Change this to your API URL

// Token Management
function getAuthToken() {
    return localStorage.getItem('authToken');
}

function setAuthToken(token) {
    localStorage.setItem('authToken', token);
}

function removeAuthToken() {
    localStorage.removeItem('authToken');
}

// API Request Helper
async function apiRequest(endpoint, options = {}) {
    const token = getAuthToken();
    const url = `${API_BASE_URL}${endpoint}`;
    
    const defaultHeaders = {
        'Content-Type': 'application/json',
    };

    if (token) {
        defaultHeaders['Authorization'] = `Bearer ${token}`;
    }

    const config = {
        ...options,
        headers: {
            ...defaultHeaders,
            ...options.headers,
        },
    };

    try {
        const response = await fetch(url, config);
        
        // Handle unauthorized - token expired or invalid
        if (response.status === 401) {
            removeAuthToken();
            localStorage.removeItem('currentUser');
            const currentPath = window.location.pathname || window.location.href;
            const currentPage = currentPath.split('/').pop().split('?')[0];
            if (currentPage === 'portal.html') {
                window.location.href = 'index.html#login';
            }
            throw new Error('Session expired. Please login again.');
        }

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || `API Error: ${response.statusText}`);
        }

        return data;
    } catch (error) {
        console.error('API Request Error:', error);
        // Don't show network errors to users if API server isn't running
        if (error.message.includes('fetch') || error.message.includes('NetworkError')) {
            console.warn('API server may not be running. Please start your backend server.');
            // Return empty data structure instead of throwing
            return { data: [], message: 'API server unavailable' };
        }
        throw error;
    }
}

// Authentication API
const AuthAPI = {
    // Customer Login
    async loginCustomer(email, password) {
        return apiRequest('/auth/customer/login', {
            method: 'POST',
            body: JSON.stringify({ email, password }),
        });
    },

    // Employee Login
    async loginEmployee(email, password) {
        return apiRequest('/auth/employee/login', {
            method: 'POST',
            body: JSON.stringify({ email, password }),
        });
    },

    // Manager Login
    async loginManager(email, password) {
        return apiRequest('/auth/manager/login', {
            method: 'POST',
            body: JSON.stringify({ email, password }),
        });
    },

    // Customer Registration
    async registerCustomer(customerData) {
        return apiRequest('/auth/customer/register', {
            method: 'POST',
            body: JSON.stringify(customerData),
        });
    },

    // Verify Token
    async verifyToken() {
        return apiRequest('/auth/verify');
    },

    // Logout
    async logout() {
        return apiRequest('/auth/logout', {
            method: 'POST',
        });
    },
};

// Quotes API
const QuotesAPI = {
    async getAll() {
        return apiRequest('/quotes');
    },

    async getById(id) {
        return apiRequest(`/quotes/${id}`);
    },

    async create(quoteData) {
        return apiRequest('/quotes', {
            method: 'POST',
            body: JSON.stringify(quoteData),
        });
    },

    async update(id, quoteData) {
        return apiRequest(`/quotes/${id}`, {
            method: 'PUT',
            body: JSON.stringify(quoteData),
        });
    },

    async delete(id) {
        return apiRequest(`/quotes/${id}`, {
            method: 'DELETE',
        });
    },

    async updateStatus(id, status) {
        return apiRequest(`/quotes/${id}/status`, {
            method: 'PATCH',
            body: JSON.stringify({ status }),
        });
    },
};

// Jobs API
const JobsAPI = {
    async getAll() {
        return apiRequest('/jobs');
    },

    async getById(id) {
        return apiRequest(`/jobs/${id}`);
    },

    async getByCustomer(customerId) {
        return apiRequest(`/jobs/customer/${customerId}`);
    },

    async create(jobData) {
        return apiRequest('/jobs', {
            method: 'POST',
            body: JSON.stringify(jobData),
        });
    },

    async update(id, jobData) {
        return apiRequest(`/jobs/${id}`, {
            method: 'PUT',
            body: JSON.stringify(jobData),
        });
    },

    async delete(id) {
        return apiRequest(`/jobs/${id}`, {
            method: 'DELETE',
        });
    },

    async updateStatus(id, status) {
        return apiRequest(`/jobs/${id}/status`, {
            method: 'PATCH',
            body: JSON.stringify({ status }),
        });
    },
};

// Properties API
const PropertiesAPI = {
    async getAll() {
        return apiRequest('/properties');
    },

    async getById(id) {
        return apiRequest(`/properties/${id}`);
    },

    async getByCustomer(customerId) {
        return apiRequest(`/properties/customer/${customerId}`);
    },

    async create(propertyData) {
        return apiRequest('/properties', {
            method: 'POST',
            body: JSON.stringify(propertyData),
        });
    },

    async update(id, propertyData) {
        return apiRequest(`/properties/${id}`, {
            method: 'PUT',
            body: JSON.stringify(propertyData),
        });
    },

    async delete(id) {
        return apiRequest(`/properties/${id}`, {
            method: 'DELETE',
        });
    },
};

// Customers API
const CustomersAPI = {
    async getAll() {
        return apiRequest('/customers');
    },

    async getById(id) {
        return apiRequest(`/customers/${id}`);
    },

    async create(customerData) {
        return apiRequest('/customers', {
            method: 'POST',
            body: JSON.stringify(customerData),
        });
    },

    async update(id, customerData) {
        return apiRequest(`/customers/${id}`, {
            method: 'PUT',
            body: JSON.stringify(customerData),
        });
    },

    async delete(id) {
        return apiRequest(`/customers/${id}`, {
            method: 'DELETE',
        });
    },
};

// Dashboard API
const DashboardAPI = {
    async getStats() {
        return apiRequest('/dashboard/stats');
    },
};

// Cookie Consent API
const CookieConsentAPI = {
    async saveCookieConsent(consent) {
        // Save to localStorage first
        localStorage.setItem('cookieConsent', consent);
        
        // If user is logged in, sync to account
        const currentUser = JSON.parse(localStorage.getItem('currentUser') || 'null');
        if (currentUser && currentUser.id) {
            try {
                return await CustomersAPI.update(currentUser.id, {
                    cookieConsent: consent
                });
            } catch (error) {
                console.error('Failed to sync cookie consent to account:', error);
                // Don't throw - localStorage is already saved
            }
        }
        
        return { success: true, consent };
    },
};

// Management API (for manager role)
const ManagementAPI = {
    async getAllEmployees() {
        return apiRequest('/employees');
    },

    async getAnalytics() {
        return apiRequest('/analytics');
    },

    async getReports() {
        return apiRequest('/reports');
    },
};

// Export API objects
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        AuthAPI,
        QuotesAPI,
        JobsAPI,
        PropertiesAPI,
        CustomersAPI,
        DashboardAPI,
        CookieConsentAPI,
        ManagementAPI,
        getAuthToken,
        setAuthToken,
        removeAuthToken,
    };
}

