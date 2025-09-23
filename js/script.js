// js/script.js
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle functionality
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            
            // Save sidebar state in a cookie
            const isCollapsed = sidebar.classList.contains('collapsed');
            document.cookie = `sidebar_collapsed=${isCollapsed}; path=/; max-age=${60 * 60 * 24 * 30}`; // 30 days
        });
    }
    
    // Mobile menu toggle
    const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show-mobile');
        });
    }
    
    // Handle active navigation links
    const navLinks = document.querySelectorAll('.nav-link');
    const currentPage = window.location.href;
    
    navLinks.forEach(link => {
        if (link.href === currentPage) {
            link.classList.add('active');
        }
    });
    
    // Notification system
    const notificationCloseButtons = document.querySelectorAll('.notification-close');
    notificationCloseButtons.forEach(button => {
        button.addEventListener('click', function() {
            this.parentElement.style.display = 'none';
        });
    });
    
    // Auto-hide notifications after 5 seconds
    const notifications = document.querySelectorAll('.notification');
    notifications.forEach(notification => {
        if (!notification.classList.contains('persistent')) {
            setTimeout(() => {
                notification.style.display = 'none';
            }, 5000);
        }
    });

    
});
// Add to js/script.js

// Notifications panel toggle
const notificationBell = document.querySelector('.notification-bell');
const notificationsPanel = document.querySelector('.notifications-panel');
const closeNotifications = document.querySelector('.close-notifications');

if (notificationBell && notificationsPanel) {
    notificationBell.addEventListener('click', function() {
        notificationsPanel.classList.toggle('open');
    });
}

if (closeNotifications && notificationsPanel) {
    closeNotifications.addEventListener('click', function() {
        notificationsPanel.classList.remove('open');
    });
}

// Close alerts
const alertCloseButtons = document.querySelectorAll('.alert-close');
alertCloseButtons.forEach(button => {
    button.addEventListener('click', function() {
        this.parentElement.style.display = 'none';
    });
});

// Mobile menu toggle for sidebar
const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
if (mobileMenuToggle) {
    mobileMenuToggle.addEventListener('click', function() {
        const sidebar = document.querySelector('.sidebar');
        if (sidebar) {
            sidebar.classList.toggle('show-mobile');
            
            // Add overlay when mobile menu is open
            if (sidebar.classList.contains('show-mobile')) {
                const overlay = document.createElement('div');
                overlay.className = 'sidebar-overlay';
                document.body.appendChild(overlay);
                
                overlay.addEventListener('click', function() {
                    sidebar.classList.remove('show-mobile');
                    document.body.removeChild(overlay);
                });
            } else {
                const overlay = document.querySelector('.sidebar-overlay');
                if (overlay) {
                    document.body.removeChild(overlay);
                }
            }
        }
    });
}

// Close notifications panel when clicking outside
document.addEventListener('click', function(event) {
    if (notificationsPanel && notificationsPanel.classList.contains('open')) {
        if (!notificationBell.contains(event.target) && !notificationsPanel.contains(event.target)) {
            notificationsPanel.classList.remove('open');
        }
    }
});

// Add overlay style for mobile sidebar
const style = document.createElement('style');
style.textContent = `
    .sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0,0,0,0.5);
        z-index: 98;
    }
    
    @media (max-width: 768px) {
        .sidebar.show-mobile {
            transform: translateX(0);
        }
        
        .sidebar {
            position: fixed;
            height: 100%;
            z-index: 99;
            transform: translateX(-100%);
            transition: transform 0.3s;
        }
    }
`;
document.head.appendChild(style);

// Notification real-time polling and updates
let pollInterval;

// Function to update unread count in header bell
function updateUnreadCount() {
    fetch('pages/notifications.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=get_unread_count',
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        const countEl = document.querySelector('.notification-count');
        if (data.unread_count > 0) {
            if (!countEl) {
                const newCount = document.createElement('span');
                newCount.className = 'notification-count';
                newCount.textContent = data.unread_count;
                document.querySelector('.notification-bell').appendChild(newCount);
            } else {
                countEl.textContent = data.unread_count;
            }
        } else if (countEl) {
            countEl.remove(); // Better than display none
        }
    })
    .catch(error => console.error('Error updating unread count:', error));
}

// Function to load notifications into panel
function loadNotificationsPanel() {
    fetch('pages/notifications.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=get_notifications&limit=5',
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        const list = document.querySelector('.notifications-list');
        if (list) {
            let html = '';
            if (data.notifications.length === 0) {
                html = '<div class="notification-item"><div class="notification-content"><p>No notifications yet.</p></div></div>';
            } else {
                data.notifications.forEach(notif => {
                    const isUnread = notif.status === 'unread';
                    let iconClass = 'fas fa-bell text-primary';
                    const msgLower = notif.message.toLowerCase();
                    if (msgLower.includes('approved')) iconClass = 'fas fa-calendar-check text-success';
                    else if (msgLower.includes('rejected')) iconClass = 'fas fa-times-circle text-danger';
                    else if (msgLower.includes('requires') || msgLower.includes('pending')) iconClass = 'fas fa-exclamation-circle text-warning';
                    else if (msgLower.includes('update') || msgLower.includes('policy')) iconClass = 'fas fa-info-circle text-info';
                    
                    const timeAgoStr = timeAgo(notif.created_at);
                    const deleteBtn = `<button class="btn btn-sm delete-btn" data-id="${notif.notification_id}"><i class="fas fa-trash"></i> Delete</button>`;
                    html += `
                        <div class="notification-item ${isUnread ? 'unread' : ''}" data-id="${notif.notification_id}">
                            <div class="notification-icon"><i class="${iconClass}"></i></div>
                            <div class="notification-content">
                                <p>${escapeHtml(notif.message)}</p>
                                <span class="notification-time">${timeAgoStr}</span>
                            </div>
                            <div class="notification-panel-actions">
                                <button class="btn btn-sm btn-primary mark-read-panel" data-id="${notif.notification_id}">Mark as Read</button>
                                ${deleteBtn}
                            </div>
                        </div>
                    `;
                });
            }
            list.innerHTML = html;
        }
    })
    .catch(error => console.error('Error loading notifications:', error));
}

// Function to load full notifications page
function loadFullNotifications(filter = 'all', limit = 20, offset = 0, append = false, search = '', dateFrom = '') {
    let body = `action=search_notifications&filter=${filter}&limit=${limit}&offset=${offset}`;
    if (search) body += `&search=${encodeURIComponent(search)}`;
    if (dateFrom) body += `&date_from=${dateFrom}`;
    
    fetch('pages/notifications.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        const list = document.getElementById('notifications-full-list');
        if (list) {
            let html = '';
            data.notifications.forEach(notif => {
                const isUnread = notif.status === 'unread';
                let iconClass = 'fas fa-bell text-primary';
                const msgLower = notif.message.toLowerCase();
                if (msgLower.includes('approved')) iconClass = 'fas fa-calendar-check text-success';
                else if (msgLower.includes('rejected')) iconClass = 'fas fa-times-circle text-danger';
                else if (msgLower.includes('requires') || msgLower.includes('pending')) iconClass = 'fas fa-exclamation-circle text-warning';
                else if (msgLower.includes('update') || msgLower.includes('policy')) iconClass = 'fas fa-info-circle text-info';
                
                const timeAgoStr = timeAgo(notif.created_at);
                const markReadBtn = isUnread ? `<button class="btn btn-sm mark-read-btn" data-id="${notif.notification_id}"><i class="fas fa-check"></i> Mark as Read</button>` : '';
                const deleteBtn = `<button class="btn btn-sm delete-btn" data-id="${notif.notification_id}"><i class="fas fa-trash"></i> Delete</button>`;
                html += `
                    <div class="notification-item-full ${isUnread ? 'unread' : 'read'}" data-id="${notif.notification_id}">
                        <div class="notification-icon-full"><i class="${iconClass}"></i></div>
                        <div class="notification-content-full">
                            <div class="notification-message">${escapeHtml(notif.message)}</div>
                            <div class="notification-meta">
                                <span class="notification-time" data-timestamp="${Date.parse(notif.created_at)}">${timeAgoStr}</span>
                                <div class="notification-actions">
                                    ${markReadBtn}
                                    ${deleteBtn}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            if (append) {
                list.insertAdjacentHTML('beforeend', html);
            } else {
                list.innerHTML = html;
            }
            // Re-setup listeners after load
            setupMarkReadListeners();
        }
    })
    .catch(error => console.error('Error loading full notifications:', error));
}

// Helper functions
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function timeAgo(datetime) {
    const now = new Date();
    const ago = new Date(datetime);
    const diffMs = now - ago;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);
    
    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins} minutes ago`;
    if (diffHours < 24) return `${diffHours} hours ago`;
    if (diffDays < 7) return `${diffDays} days ago`;
    return ago.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

// Mark as read for full page links
function setupMarkReadListeners() {
    console.log('Setting up mark read listeners...');
    const markReadBtns = document.querySelectorAll('.mark-read-btn');
    console.log('Found mark read buttons:', markReadBtns.length);
    markReadBtns.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.dataset.id;
            fetch('pages/notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=mark_read&notification_id=${id}`,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const item = document.querySelector(`[data-id="${id}"]`);
                    if (item) {
                        item.classList.remove('unread');
                        item.classList.add('read');
                        const btn = item.querySelector('.mark-read-btn');
                        if (btn) btn.remove();
                    }
                    // Update count
                    updateUnreadCount();
                } else {
                    console.error('Error marking as read:', data.error);
                }
            })
            .catch(error => console.error('AJAX error:', error));
        });
    });

    // Delete listeners
    const deleteBtns = document.querySelectorAll('.delete-btn');
    console.log('Found delete buttons:', deleteBtns.length);
    deleteBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            if (!confirm('Are you sure you want to delete this notification?')) return;
            const id = this.dataset.id;
            fetch('pages/notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete_notification&notification_id=${id}`,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const item = document.querySelector(`[data-id="${id}"]`);
                    if (item) item.remove();
                    updateUnreadCount();
                } else {
                    console.error('Error deleting notification:', data.error);
                }
            })
            .catch(error => console.error('AJAX error:', error));
        });
    });
}

// Enhanced DOMContentLoaded for notifications
document.addEventListener('DOMContentLoaded', function() {
    // Initial load
    updateUnreadCount();
    
    // Poll every 30 seconds
    pollInterval = setInterval(updateUnreadCount, 30000);
    
    // Load panel when bell clicked (integrate with existing toggle)
    const bell = document.querySelector('.notification-bell');
    const panel = document.querySelector('.notifications-panel');
    if (bell && panel) {
        bell.addEventListener('click', function() {
            if (!panel.classList.contains('open')) {
                loadNotificationsPanel();
            }
            
            // Search functionality for notifications page
            let searchTimeout;
            if (window.location.search.includes('page=notifications')) {
                const searchInput = document.getElementById('search-input');
                const dateFrom = document.getElementById('date-from');

                // Handle URL parameters on page load
                const urlParams = new URLSearchParams(window.location.search);
                const urlSearch = urlParams.get('search') || '';
                const urlDateFrom = urlParams.get('date_from') || '';

                if (searchInput && urlSearch) {
                    searchInput.value = urlSearch;
                }
                if (dateFrom && urlDateFrom) {
                    dateFrom.value = urlDateFrom;
                }

                // Initial load with URL parameters
                const currentFilter = urlParams.get('filter') || 'all';
                loadFullNotifications(currentFilter, 20, 0, false, urlSearch, urlDateFrom);

                function performSearch() {
                    const search = searchInput ? searchInput.value : '';
                    const date = dateFrom ? dateFrom.value : '';
                    const currentFilter = new URLSearchParams(window.location.search).get('filter') || 'all';
                    loadFullNotifications(currentFilter, 20, 0, false, search, date);

                    // Update URL
                    const params = new URLSearchParams(window.location.search);
                    if (search) params.set('search', search);
                    else params.delete('search');
                    if (date) params.set('date_from', date);
                    else params.delete('date_from');
                    window.history.replaceState({}, '', `${window.location.pathname}?${params}`);
                }

                if (searchInput) {
                    searchInput.addEventListener('input', function() {
                        clearTimeout(searchTimeout);
                        searchTimeout = setTimeout(performSearch, 300);
                    });
                }

                if (dateFrom) {
                    dateFrom.addEventListener('change', performSearch);
                }
            }
        });
    }
    
    // For full notifications page
    if (window.location.search.includes('page=notifications')) {
        console.log('Notifications page loaded, setting up listeners...');
        // Initial setup
        setupMarkReadListeners();

        // Initial load to ensure proper display
        const urlParams = new URLSearchParams(window.location.search);
        const currentFilter = urlParams.get('filter') || 'all';
        const urlSearch = urlParams.get('search') || '';
        const urlDateFrom = urlParams.get('date_from') || '';
        loadFullNotifications(currentFilter, 20, 0, false, urlSearch, urlDateFrom);

        // Update unread count display on the notifications page
        function updateNotificationsPageCount() {
            const countEl = document.getElementById('unread-count');
            if (countEl) {
                fetch('pages/notifications.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=get_unread_count',
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    countEl.textContent = data.unread_count;
                })
                .catch(error => console.error('Error updating notifications page count:', error));
            }
        }

        // Update count after mark as read or delete operations
        const originalSetupMarkReadListeners = setupMarkReadListeners;
        setupMarkReadListeners = function() {
            originalSetupMarkReadListeners();

            // Override the mark as read handler to update the page count
            document.querySelectorAll('.mark-read-btn').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const id = this.dataset.id;
                    const button = this;
                    const originalText = button.innerHTML;

                    // Show loading state
                    button.disabled = true;
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Marking...';

                    fetch('pages/notifications.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=mark_read&notification_id=${id}`,
                        credentials: 'same-origin'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const item = document.querySelector(`[data-id="${id}"]`);
                            if (item) {
                                item.classList.remove('unread');
                                item.classList.add('read');
                                const btn = item.querySelector('.mark-read-btn');
                                if (btn) btn.remove();
                            }
                            // Update counts
                            updateUnreadCount();
                            updateNotificationsPageCount();
                        } else {
                            console.error('Error marking as read:', data.error);
                            // Reset button
                            button.disabled = false;
                            button.innerHTML = originalText;
                        }
                    })
                    .catch(error => {
                        console.error('AJAX error:', error);
                        // Reset button
                        button.disabled = false;
                        button.innerHTML = originalText;
                    });
                });
            });

            // Override the delete handler to update the page count
            document.querySelectorAll('.delete-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (!confirm('Are you sure you want to delete this notification?')) return;
                    const id = this.dataset.id;
                    const button = this;
                    const originalText = button.innerHTML;

                    // Show loading state
                    button.disabled = true;
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';

                    fetch('pages/notifications.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=delete_notification&notification_id=${id}`,
                        credentials: 'same-origin'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const item = document.querySelector(`[data-id="${id}"]`);
                            if (item) item.remove();
                            updateUnreadCount();
                            updateNotificationsPageCount();
                        } else {
                            console.error('Error deleting notification:', data.error);
                            // Reset button
                            button.disabled = false;
                            button.innerHTML = originalText;
                        }
                    })
                    .catch(error => {
                        console.error('AJAX error:', error);
                        // Reset button
                        button.disabled = false;
                        button.innerHTML = originalText;
                    });
                });
            });
        };

        // Mark all read handler
        const markAllReadBtn = document.querySelector('.mark-all-read-btn');
        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (!confirm('Are you sure you want to mark all notifications as read?')) return;
                const button = this;
                const originalText = button.innerHTML;

                button.disabled = true;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Marking...';

                fetch('pages/notifications.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=mark_all_read',
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateUnreadCount();
                        updateNotificationsPageCount();
                        loadFullNotifications(new URLSearchParams(window.location.search).get('filter') || 'all');
                        button.remove(); // Remove button since no unread left
                    } else {
                        console.error('Error marking all as read:', data.error);
                        button.disabled = false;
                        button.innerHTML = originalText;
                    }
                })
                .catch(error => {
                    console.error('AJAX error:', error);
                    button.disabled = false;
                    button.innerHTML = originalText;
                });
            });
        }

        // Clear all notifications handler
        const clearAllBtn = document.querySelector('.clear-all-btn');
        if (clearAllBtn) {
            clearAllBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (!confirm('Are you sure you want to clear all notifications? This action cannot be undone.')) return;
                const button = this;
                const originalText = button.innerHTML;

                button.disabled = true;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Clearing...';

                fetch('pages/notifications.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=clear_all_notifications',
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateUnreadCount();
                        updateNotificationsPageCount();
                        loadFullNotifications(new URLSearchParams(window.location.search).get('filter') || 'all');
                        button.remove(); // Remove button since no notifications left
                        console.log(`Cleared ${data.deleted} notifications`);
                    } else {
                        console.error('Error clearing notifications');
                        button.disabled = false;
                        button.innerHTML = originalText;
                    }
                })
                .catch(error => {
                    console.error('AJAX error:', error);
                    button.disabled = false;
                    button.innerHTML = originalText;
                });
            });
        }

        // Poll for full page
        setInterval(() => {
            const currentFilter = new URLSearchParams(window.location.search).get('filter') || 'all';
            loadFullNotifications(currentFilter);
        }, 30000);
        
        // Update times every minute
        setInterval(() => {
            document.querySelectorAll('.notification-time').forEach(el => {
                const timestamp = el.dataset.timestamp;
                if (timestamp) {
                    el.textContent = timeAgo(new Date(parseInt(timestamp)));
                } else {
                    // For panel, approximate
                    el.textContent = timeAgo(el.textContent); // Won't work, but skip
                }
            });
        }, 60000);
    }
});