-- Sample notifications for testing
-- Target user_id=3 (Jane Employee)

INSERT INTO Notifications (user_id, message, type, status, created_at) VALUES
(3, 'Your leave request for May 15-18 has been approved', 'system', 'unread', NOW() - INTERVAL 2 HOUR),
(3, 'Your sick leave requires a medical certificate', 'system', 'unread', NOW() - INTERVAL 1 DAY),
(3, 'New leave policy update available', 'system', 'read', NOW() - INTERVAL 3 DAY),
(3, 'Your annual leave balance has been updated for the new year', 'system', 'unread', NOW() - INTERVAL 5 DAY),
(3, 'Reminder: Submit your leave request early for holiday period', 'system', 'read', NOW() - INTERVAL 1 WEEK);