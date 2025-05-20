document.addEventListener('DOMContentLoaded', () => {
    console.log('dashboard.js loaded');

    // Function to show toast with auto-close
    window.showToast = function (message, type, duration = 5000) {
        console.log('showToast called:', { message, type, duration });

        // Ensure toast container exists
        let toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.classList.add('toast-container');
            document.body.appendChild(toastContainer);
        }

        // Create toast element
        const toast = document.createElement('div');
        toast.classList.add('toast', `toast-${type}`, 'visible');
        toast.innerHTML = `<span>${message}</span>`;
        toastContainer.appendChild(toast);

        // Auto-close after duration
        setTimeout(() => {
            toast.classList.remove('visible');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 300); // Wait for fade-out transition
        }, duration);
    };

    // Function to initialize event listeners with retries
    function initEventListeners(attempts = 3, delay = 100) {
        // Sidebar Toggle
        const sidebar = document.querySelector('.sidebar');
        const sidebarToggle = document.querySelector('.sidebar-toggle');
        if (sidebar && sidebarToggle) {
            console.log('Sidebar elements found');
            sidebarToggle.addEventListener('click', () => {
                console.log('Sidebar toggle clicked');
                sidebar.classList.toggle('sidebar-expanded');
                sidebar.classList.toggle('sidebar-collapsed');
            });
        } else {
            console.warn('Sidebar elements not found:', { sidebar, sidebarToggle });
        }

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
        const notificationButton = document.querySelector('.notification-button');
        const notificationDropdown = document.querySelector('.notification-dropdown');
        if (notificationButton && notificationDropdown) {
            console.log('Notification elements found');
            notificationButton.addEventListener('click', () => {
                console.log('Notification button clicked');
                notificationDropdown.classList.toggle('visible');
            });
        } else {
            console.log('Notification elements not found (normal if not on dashboard)');
        }

        // Profile Dropdown
        const profileButton = document.querySelector('.profile-button');
        const profileDropdown = document.querySelector('.profile-dropdown');
        if (profileButton && profileDropdown) {
            console.log('Profile elements found');
            profileButton.addEventListener('click', () => {
                console.log('Profile button clicked');
                profileDropdown.classList.toggle('visible');
            });
        } else {
            console.warn('Profile elements not found:', { profileButton, profileDropdown });
        }

        // Close Dropdowns on Outside Click
        document.addEventListener('click', (e) => {
            if (notificationButton && notificationDropdown && 
                !notificationButton.contains(e.target) && !notificationDropdown.contains(e.target)) {
                notificationDropdown.classList.remove('visible');
            }
            if (profileButton && profileDropdown && 
                !profileButton.contains(e.target) && !profileDropdown.contains(e.target)) {
                profileDropdown.classList.remove('visible');
            }
        });

        // Mark Notifications as Read
        const markReadBtn = document.getElementById('mark-read-btn');
        if (markReadBtn) {
            console.log('Mark read button found');
            markReadBtn.addEventListener('click', () => {
                console.log('Mark read button clicked');
                fetch('../controllers/ManagerDashboardController.php?action=mark_notifications_read', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.querySelectorAll('.notification-item').forEach(item => item.classList.add('read'));
                        const badge = document.querySelector('.notification-badge');
                        if (badge) badge.remove();
                        showToast(data.message, 'success', 5000);
                    } else {
                        showToast(data.message, 'error', 5000);
                    }
                })
                .catch(error => showToast('Error marking notifications as read: ' + error, 'error', 5000));
            });
        } else {
            console.log('Mark read button not found (normal if no notifications)');
        }

        // Initialize Charts
        const currentYear = window.dashboardData?.currentYear || new Date().getFullYear();

        // Leave Insights Chart
        const insightsCtx = document.getElementById('leaveInsightsChart');
        if (insightsCtx) {
            console.log('Insights chart canvas found');
            const leaveInsightsChart = new Chart(insightsCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: window.dashboardData.insightsLabels,
                    datasets: [{
                        label: 'Leave Requests',
                        data: window.dashboardData.insightsCounts,
                        backgroundColor: window.dashboardData.insightsColors,
                        borderColor: window.dashboardData.insightsColors.map(color => {
                            const hex = color.replace('#', '');
                            const r = parseInt(hex.substr(0, 2), 16) * 0.8;
                            const g = parseInt(hex.substr(2, 2), 16) * 0.8;
                            const b = parseInt(hex.substr(4, 2), 16) * 0.8;
                            return `rgb(${Math.round(r)}, ${Math.round(g)}, ${Math.round(b)})`;
                        }),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Number of Requests' },
                            ticks: { stepSize: 1 }
                        },
                        x: {
                            title: { display: true, text: 'Leave Type' }
                        }
                    },
                    plugins: {
                        legend: { display: true, position: 'top' },
                        tooltip: { enabled: true },
                        title: { display: true, text: `Leave Requests by Type (${currentYear})` }
                    }
                }
            });

            // Prevent canvas clicks from triggering card redirect
            insightsCtx.addEventListener('click', (e) => {
                e.stopPropagation();
            });
        } else {
            console.log('Insights chart canvas not found (normal if not on dashboard)');
        }

        // Leave Trends Chart
        const trendsCtx = document.getElementById('leaveTrendsChart');
        if (trendsCtx) {
            console.log('Trends chart canvas found');
            const leaveTrendsChart = new Chart(trendsCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: window.dashboardData.trendsLabels,
                    datasets: [{
                        label: 'Total Requests',
                        data: window.dashboardData.trendsData,
                        backgroundColor: '#4a90e2',
                        borderColor: '#357abd',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Number of Requests' },
                            ticks: { stepSize: 1 }
                        },
                        x: {
                            title: { display: true, text: 'Month' }
                        }
                    },
                    plugins: {
                        legend: { display: true, position: 'top' },
                        tooltip: { enabled: true },
                        title: { display: true, text: `Leave Request Trends (${currentYear})` }
                    }
                }
            });

            // Prevent canvas clicks from triggering card redirect
            trendsCtx.addEventListener('click', (e) => {
                e.stopPropagation();
            });
        } else {
            console.log('Trends chart canvas not found (normal if not on dashboard)');
        }

        // Retry if critical elements are missing
        if (attempts > 1 && (!sidebar || !sidebarToggle || !profileButton || !profileDropdown)) {
            console.log(`Retrying initialization, attempts left: ${attempts - 1}`);
            setTimeout(() => initEventListeners(attempts - 1, delay * 2), delay);
        }
    }

    // Initialize with retries
    initEventListeners();
});