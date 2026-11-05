// ============================================
// ERP System - Core JavaScript
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle
    window.toggleSidebar = function() {
        document.getElementById('sidebar').classList.toggle('open');
    };

    // Close sidebar on outside click (mobile)
    document.addEventListener('click', function(e) {
        const sidebar = document.getElementById('sidebar');
        const toggle = document.querySelector('.menu-toggle');
        if (window.innerWidth <= 768 && 
            !sidebar.contains(e.target) && 
            !toggle.contains(e.target) &&
            sidebar.classList.contains('open')) {
            sidebar.classList.remove('open');
        }
    });

    // Auto-dismiss alerts
    document.querySelectorAll('.alert-dismissible').forEach(function(alert) {
        setTimeout(function() {
            alert.style.opacity = '0';
            setTimeout(function() { alert.remove(); }, 300);
        }, 5000);
    });

    // Form validation
    document.querySelectorAll('form[data-validate]').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            var valid = true;
            form.querySelectorAll('[required]').forEach(function(field) {
                if (!field.value.trim()) {
                    field.style.borderColor = '#ef4444';
                    valid = false;
                } else {
                    field.style.borderColor = '';
                }
            });
            if (!valid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
    });

    // File upload preview
    document.querySelectorAll('.file-upload input[type="file"]').forEach(function(input) {
        input.addEventListener('change', function() {
            var label = this.closest('.file-upload').querySelector('.file-upload-label');
            if (this.files && this.files[0]) {
                label.textContent = this.files[0].name;
            } else {
                label.textContent = 'Click to upload file';
            }
        });
    });

    // Confirm dialogs
    document.querySelectorAll('[data-confirm]').forEach(function(el) {
        el.addEventListener('click', function(e) {
            if (!confirm(this.getAttribute('data-confirm') || 'Are you sure?')) {
                e.preventDefault();
            }
        });
    });

    // Print
    document.querySelectorAll('[data-print]').forEach(function(el) {
        el.addEventListener('click', function() {
            window.print();
        });
    });

    // Search with debounce
    var searchTimeout;
    document.querySelectorAll('[data-search]').forEach(function(input) {
        input.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            var term = this.value;
            searchTimeout = setTimeout(function() {
                // Trigger table filter
                filterTable(term);
            }, 300);
        });
    });
});

// Table filtering
function filterTable(term) {
    term = term.toLowerCase();
    document.querySelectorAll('.table tbody tr').forEach(function(row) {
        var text = row.textContent.toLowerCase();
        row.style.display = text.includes(term) ? '' : 'none';
    });
}

// Tab switching
function switchTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(function(el) {
        el.style.display = 'none';
    });
    document.querySelectorAll('.tab').forEach(function(el) {
        el.classList.remove('active');
    });
    document.getElementById(tabId).style.display = 'block';
    document.querySelector('[data-tab="' + tabId + '"]').classList.add('active');
}

// Modal control
function openModal(id) {
    document.getElementById(id).classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
    document.body.style.overflow = '';
}

function closeAllModals() {
    document.querySelectorAll('.modal-overlay').forEach(function(el) {
        el.classList.remove('active');
    });
    document.body.style.overflow = '';
}

// Close modal on overlay click
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        closeAllModals();
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAllModals();
    }
});

// Format currency
function formatCurrency(amount) {
    return '₹ ' + parseFloat(amount).toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// Generate random string
function randomString(length) {
    var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    var result = '';
    for (var i = 0; i < length; i++) {
        result += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return result;
}

// Preview image before upload
function previewImage(input, imgId) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById(imgId).src = e.target.result;
            document.getElementById(imgId).style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Calculate invoice totals
function calcInvoiceTotal() {
    var subtotal = 0;
    document.querySelectorAll('.invoice-item-total').forEach(function(el) {
        subtotal += parseFloat(el.value) || 0;
    });
    
    var taxPercent = parseFloat(document.getElementById('tax_percentage')?.value) || 0;
    var discount = parseFloat(document.getElementById('discount')?.value) || 0;
    var taxAmount = subtotal * (taxPercent / 100);
    var total = subtotal + taxAmount - discount;
    
    document.getElementById('subtotal')?.value = subtotal.toFixed(2);
    document.getElementById('tax_amount')?.value = taxAmount.toFixed(2);
    document.getElementById('total')?.value = total.toFixed(2);
    document.getElementById('amount_due')?.value = total.toFixed(2);
}

// Dark mode toggle
function toggleTheme() {
    var html = document.documentElement;
    var current = html.getAttribute('data-theme');
    var newTheme = current === 'dark' ? '' : 'dark';
    html.setAttribute('data-theme', newTheme);
    try { localStorage.setItem('erp-theme', newTheme); } catch(e) {}
    updateToggleIcon();
}

function updateToggleIcon() {
    var btn = document.getElementById('themeToggle');
    if (!btn) return;
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    var sun = btn.querySelector('.sun-icon');
    var moon = btn.querySelector('.moon-icon');
    if (sun) sun.style.display = isDark ? 'none' : 'block';
    if (moon) moon.style.display = isDark ? 'block' : 'none';
}

// Login page - Enter key support
document.addEventListener('DOMContentLoaded', function() {
    var loginForm = document.querySelector('.login-form');
    if (loginForm) {
        var passwordField = loginForm.querySelector('#password');
        if (passwordField) {
            passwordField.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    loginForm.querySelector('button[type="submit"]').click();
                }
            });
        }
    }
});
