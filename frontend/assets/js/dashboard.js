document.addEventListener('DOMContentLoaded', () => {
    // Sidebar Toggle
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebar-toggle');

    sidebarToggle.addEventListener('click', () => {
        sidebar.classList.toggle('sidebar-expanded');
        sidebar.classList.toggle('sidebar-collapsed');
    });

    // Profile Dropdown
    const profileToggle = document.getElementById('profile-toggle');
    const profileDropdown = document.getElementById('profile-dropdown');

    profileToggle.addEventListener('click', () => {
        profileDropdown.classList.toggle('visible');
        profileDropdown.classList.toggle('hidden');
    });

    document.addEventListener('click', (e) => {
        if (!profileToggle.contains(e.target) && !profileDropdown.contains(e.target)) {
            profileDropdown.classList.remove('visible');
            profileDropdown.classList.add('hidden');
        }
    });

    // Toast Notifications
    const toastContainer = document.getElementById('toast-container');
    const showToast = (message, type) => {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <span>${message}</span>
            ${type !== 'success' ? `
                <button class="toast-close">
                    <i class="fas fa-times"></i>
                </button>
            ` : ''}
        `;
        toastContainer.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('visible');
        }, 10);

        if (type === 'success') {
            setTimeout(() => {
                toast.classList.remove('visible');
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        } else {
            const closeButton = toast.querySelector('.toast-close');
            closeButton.addEventListener('click', () => {
                toast.classList.remove('visible');
                setTimeout(() => toast.remove(), 300);
            });
        }
    };

    // Handle initial toast messages from PHP
    const initialToasts = document.querySelectorAll('.toast');
    initialToasts.forEach(toast => {
        setTimeout(() => {
            toast.classList.add('visible');
        }, 10);

        const type = toast.classList.contains('toast-success') ? 'success' : 'error';
        if (type !== 'success') {
            const closeButton = toast.querySelector('.toast-close');
            closeButton.addEventListener('click', () => {
                toast.classList.remove('visible');
                setTimeout(() => toast.remove(), 300);
            });
        } else {
            setTimeout(() => {
                toast.classList.remove('visible');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
    });

    // Notification Dropdown
    const notificationToggle = document.getElementById('notification-toggle');
    const notificationDropdown = document.getElementById('notification-dropdown');

    notificationToggle.addEventListener('click', () => {
        notificationDropdown.classList.toggle('visible');
        notificationDropdown.classList.toggle('hidden');
        if (!notificationDropdown.classList.contains('hidden')) {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '../backend/controllers/EmployeeDashboardController.php?action=mark_notifications_read', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = () => {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            const badge = notificationToggle.querySelector('.notification-badge');
                            if (badge) badge.remove();
                        }
                    } catch (e) {
                        console.error('Error parsing response:', e);
                    }
                }
            };
            xhr.send();
        }
    });

    document.addEventListener('click', (e) => {
        if (!notificationToggle.contains(e.target) && !notificationDropdown.contains(e.target)) {
            notificationDropdown.classList.remove('visible');
            notificationDropdown.classList.add('hidden');
        }
    });

    // Search Functionality
    const searchInput = document.getElementById('search-input');
    searchInput.addEventListener('input', (e) => {
        e.preventDefault();
        const searchTerm = searchInput.value.toLowerCase();
        // No table to filter, so this can be removed or adjusted for future use
    });
});