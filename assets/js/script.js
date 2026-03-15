/*========================================
  FitTrack - Professional JavaScript
  Author: FitTrack Team
  Version: 2.0
========================================*/

// Immediately-invoked function expression to avoid global scope pollution
(function() {
    'use strict';

    // Wait for DOM to be fully loaded
    document.addEventListener('DOMContentLoaded', initApp);

    /**
     * Initialize all application features
     */
    function initApp() {
        // Auto-hide alerts after 5 seconds
        initAutoHideAlerts();

        // Confirm delete actions
        initDeleteConfirmation();

        // Password strength meter and confirmation
        initPasswordStrength();
        initPasswordMatch();

        // Bootstrap tooltips and popovers
        initBootstrapComponents();

        // Table search functionality
        initTableSearch();

        // Date range picker placeholder
        initDateRangePicker();

        // Sidebar toggle for mobile (if exists)
        initSidebarToggle();

        // Logout confirmation
        initLogout();

        // Loading spinner utility
        initLoadingSpinner();

        // AJAX form submissions
        initAjaxForms();
    }

    /*---------------------------------------
      Alert Auto-Hide
    ---------------------------------------*/
    function initAutoHideAlerts() {
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    }

    /*---------------------------------------
      Delete Confirmation
    ---------------------------------------*/
    function initDeleteConfirmation() {
        document.querySelectorAll('.btn-delete, [data-confirm]').forEach(button => {
            button.addEventListener('click', (e) => {
                const message = button.getAttribute('data-confirm') || 'Are you sure you want to delete this item? This action cannot be undone.';
                if (!confirm(message)) {
                    e.preventDefault();
                }
            });
        });
    }

    /*---------------------------------------
      Password Strength Meter
    ---------------------------------------*/
    function initPasswordStrength() {
        const passwordInput = document.getElementById('password');
        if (!passwordInput) return;

        // Create indicator element if not exists
        let indicator = document.getElementById('password-strength');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'password-strength';
            indicator.className = 'progress mt-2';
            indicator.style.height = '5px';

            const bar = document.createElement('div');
            bar.className = 'progress-bar';
            bar.id = 'password-strength-bar';
            indicator.appendChild(bar);

            passwordInput.parentNode.appendChild(indicator);
        }

        passwordInput.addEventListener('input', () => {
            const strength = checkPasswordStrength(passwordInput.value);
            updatePasswordStrengthBar(strength);
        });
    }

    function checkPasswordStrength(password) {
        let score = 0;
        if (password.length >= 8) score++;
        if (/[a-z]/.test(password)) score++;
        if (/[A-Z]/.test(password)) score++;
        if (/[0-9]/.test(password)) score++;
        if (/[$@#&!]/.test(password)) score++;
        return score;
    }

    function updatePasswordStrengthBar(score) {
        const bar = document.getElementById('password-strength-bar');
        if (!bar) return;

        const percentage = (score / 5) * 100;
        bar.style.width = percentage + '%';
        bar.style.transition = 'width 0.3s';

        if (percentage <= 20) {
            bar.className = 'progress-bar bg-danger';
        } else if (percentage <= 40) {
            bar.className = 'progress-bar bg-warning';
        } else if (percentage <= 60) {
            bar.className = 'progress-bar bg-info';
        } else {
            bar.className = 'progress-bar bg-success';
        }
    }

    /*---------------------------------------
      Password Match Validation
    ---------------------------------------*/
    function initPasswordMatch() {
        const password = document.getElementById('password');
        const confirm = document.getElementById('confirm_password');
        if (!password || !confirm) return;

        [password, confirm].forEach(input => {
            input.addEventListener('input', () => {
                if (password.value !== confirm.value) {
                    confirm.setCustomValidity('Passwords do not match');
                    confirm.classList.add('is-invalid');
                    confirm.classList.remove('is-valid');
                } else {
                    confirm.setCustomValidity('');
                    confirm.classList.remove('is-invalid');
                    confirm.classList.add('is-valid');
                }
            });
        });
    }

    /*---------------------------------------
      Bootstrap Tooltips & Popovers
    ---------------------------------------*/
    function initBootstrapComponents() {
        // Tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(el => new bootstrap.Tooltip(el));

        // Popovers
        const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(el => new bootstrap.Popover(el));
    }

    /*---------------------------------------
      Table Search (simple client-side)
    ---------------------------------------*/
    function initTableSearch() {
        const searchInput = document.getElementById('tableSearch');
        if (!searchInput) return;

        searchInput.addEventListener('keyup', () => {
            const searchTerm = searchInput.value.toLowerCase();
            const table = document.getElementById('searchableTable');
            if (!table) return;

            const rows = table.getElementsByTagName('tr');
            for (let i = 1; i < rows.length; i++) { // skip header
                const rowData = rows[i].textContent.toLowerCase();
                rows[i].style.display = rowData.includes(searchTerm) ? '' : 'none';
            }
        });
    }

    /*---------------------------------------
      Date Range Picker (placeholder)
    ---------------------------------------*/
    function initDateRangePicker() {
        // If you have date range inputs that need a library, initialize here
        // For now, we do nothing
    }

    /*---------------------------------------
      Sidebar Toggle for Mobile
    ---------------------------------------*/
    function initSidebarToggle() {
        const toggleBtn = document.getElementById('sidebarCollapse');
        const sidebar = document.getElementById('sidebar');
        if (!toggleBtn || !sidebar) return;

        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('show');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (event) => {
            if (window.innerWidth < 992 &&
                sidebar.classList.contains('show') &&
                !sidebar.contains(event.target) &&
                !toggleBtn.contains(event.target)) {
                sidebar.classList.remove('show');
            }
        });
    }

    /*---------------------------------------
      Logout Confirmation (Bootstrap Modal)
    ---------------------------------------*/
    function initLogout() {
        // This function is called by the logout links via onclick attribute
        window.confirmLogout = function(event) {
            event.preventDefault();
            const modal = new bootstrap.Modal(document.getElementById('logoutModal'));
            modal.show();
        };
    }

    /*---------------------------------------
      Loading Spinner
    ---------------------------------------*/
    function initLoadingSpinner() {
        window.showLoading = function() {
            document.getElementById('loadingSpinner')?.classList.add('active');
        };
        window.hideLoading = function() {
            document.getElementById('loadingSpinner')?.classList.remove('active');
        };
    }

    /*---------------------------------------
      AJAX Form Submissions
    ---------------------------------------*/
    function initAjaxForms() {
        // Automatically handle forms with data-ajax attribute
        document.querySelectorAll('form[data-ajax]').forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const url = form.getAttribute('action') || window.location.href;
                const method = form.getAttribute('method') || 'POST';
                const formData = new FormData(form);

                showLoading();

                try {
                    const response = await fetch(url, {
                        method: method,
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const data = await response.json();
                    hideLoading();

                    if (data.success) {
                        showSuccess(data.message || 'Operation completed successfully.');
                        // If there's a redirect URL, go there
                        if (data.redirect) {
                            setTimeout(() => { window.location.href = data.redirect; }, 1500);
                        }
                    } else {
                        showError(data.message || 'An error occurred.');
                    }
                } catch (error) {
                    hideLoading();
                    showError('Network error: ' + error.message);
                }
            });
        });
    }

    /*---------------------------------------
      Notification Helpers (using SweetAlert2)
    ---------------------------------------*/
    window.showSuccess = (message) => {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: message,
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            alert('Success: ' + message);
        }
    };

    window.showError = (message) => {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: message
            });
        } else {
            alert('Error: ' + message);
        }
    };

    window.showWarning = (message) => {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'Warning!',
                text: message
            });
        } else {
            alert('Warning: ' + message);
        }
    };

    window.showInfo = (message) => {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'info',
                title: 'Info',
                text: message,
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            alert('Info: ' + message);
        }
    };

    /*---------------------------------------
      Utility Functions (exposed globally)
    ---------------------------------------*/

    /**
     * Format currency as USD
     */
    window.formatCurrency = (amount) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(amount);
    };

    /**
     * Format date as MMM DD, YYYY
     */
    window.formatDate = (dateString) => {
        const date = new Date(dateString);
        if (isNaN(date)) return 'Invalid date';
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    };

    /**
     * Export HTML table to CSV
     */
    window.exportTableToCSV = (tableId, filename = 'export.csv') => {
        const table = document.getElementById(tableId);
        if (!table) return;

        const rows = table.querySelectorAll('tr');
        const csv = [];

        rows.forEach(row => {
            const cols = row.querySelectorAll('td, th');
            const rowData = [];
            cols.forEach(col => {
                let text = col.innerText.replace(/"/g, '""');
                rowData.push('"' + text + '"');
            });
            csv.push(rowData.join(','));
        });

        const csvContent = csv.join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', filename);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    };

    /**
     * Confirm action with SweetAlert2 (if available) or native confirm
     */
    window.confirmAction = (message, callback) => {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Are you sure?',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, do it!'
            }).then((result) => {
                if (result.isConfirmed && callback) callback();
            });
        } else {
            if (confirm(message)) callback();
        }
    };

})(); // End of IIFE