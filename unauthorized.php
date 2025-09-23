<?php
// unauthorized.php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - ELMS | St. Francis Xavier Hospital</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .unauthorized-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #f5f7f9 0%, #e8ecf1 100%);
            padding: 20px;
        }
        
        .unauthorized-content {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        
        .unauthorized-icon {
            font-size: 5rem;
            color: #e74c3c;
            margin-bottom: 20px;
            animation: bounce 2s infinite;
        }
        
        .unauthorized-title {
            color: #2c3e50;
            font-size: 2.2rem;
            margin-bottom: 15px;
            font-weight: 700;
        }
        
        .unauthorized-message {
            color: #7f8c8d;
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        
        .unauthorized-details {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            text-align: left;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .detail-item:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .detail-value {
            color: #6c757d;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background-color: #3498db;
            color: white;
            border: none;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background-color: #95a5a6;
            color: white;
            border: none;
        }
        
        .btn-secondary:hover {
            background-color: #7f8c8d;
            transform: translateY(-2px);
        }
        
        .btn-outline {
            background-color: transparent;
            color: #3498db;
            border: 2px solid #3498db;
        }
        
        .btn-outline:hover {
            background-color: #3498db;
            color: white;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-20px);
            }
            60% {
                transform: translateY(-10px);
            }
        }
        
        .contact-support {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        
        .contact-support p {
            color: #6c757d;
            margin-bottom: 10px;
        }
        
        .support-link {
            color: #3498db;
            text-decoration: none;
            font-weight: 600;
        }
        
        .support-link:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 576px) {
            .unauthorized-content {
                padding: 30px 20px;
            }
            
            .unauthorized-title {
                font-size: 1.8rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="unauthorized-container">
        <div class="unauthorized-content">
            <div class="unauthorized-icon">
                <i class="fas fa-ban"></i>
            </div>
            
            <h1 class="unauthorized-title">Access Denied</h1>
            
            <p class="unauthorized-message">
                You don't have permission to access this page. This area is restricted to authorized personnel only.
            </p>
            
            <div class="unauthorized-details">
                <div class="detail-item">
                    <span class="detail-label">Attempted Access:</span>
                    <span class="detail-value"><?php echo isset($_SERVER['REQUEST_URI']) ? htmlspecialchars($_SERVER['REQUEST_URI']) : 'Unknown Page'; ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Your Role:</span>
                    <span class="detail-value">
                        <?php 
                        if (isset($_SESSION['user_role'])) {
                            echo ucfirst($_SESSION['user_role']);
                        } else {
                            echo 'Not logged in';
                        }
                        ?>
                    </span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Timestamp:</span>
                    <span class="detail-value"><?php echo date('Y-m-d H:i:s'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">IP Address:</span>
                    <span class="detail-value"><?php echo $_SERVER['REMOTE_ADDR']; ?></span>
                </div>
            </div>
            
            <div class="action-buttons">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="dashboard.php" class="btn btn-primary">
                        <i class="fas fa-tachometer-alt"></i> Return to Dashboard
                    </a>
                <?php else: ?>
                    <a href="index.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                <?php endif; ?>
                
                <a href="javascript:history.back()" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Go Back
                </a>
                
                <a href="?page=help" class="btn btn-outline">
                    <i class="fas fa-question-circle"></i> Get Help
                </a>
            </div>
            
            <div class="contact-support">
                <p>If you believe this is an error, please contact:</p>
                <a href="mailto:support@sfxhospital.org?subject=Access%20Denied%20Error" class="support-link">
                    <i class="fas fa-envelope"></i> support@sfxhospital.org
                </a>
                <span style="margin: 0 10px; color: #ddd;">|</span>
                <a href="tel:+15551234567" class="support-link">
                    <i class="fas fa-phone"></i> (555) 123-4567
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Log the unauthorized access attempt
            console.warn('Unauthorized access attempt detected:', {
                page: '<?php echo isset($_SERVER['REQUEST_URI']) ? addslashes($_SERVER['REQUEST_URI']) : 'Unknown'; ?>',
                role: '<?php echo isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'Not logged in'; ?>',
                timestamp: '<?php echo date('Y-m-d H:i:s'); ?>',
                ip: '<?php echo $_SERVER['REMOTE_ADDR']; ?>'
            });
            
            // Add fade-in animation
            document.querySelector('.unauthorized-content').style.opacity = '0';
            document.querySelector('.unauthorized-content').style.transition = 'opacity 0.5s ease-in';
            
            setTimeout(() => {
                document.querySelector('.unauthorized-content').style.opacity = '1';
            }, 100);
            
            // Auto-redirect to dashboard after 30 seconds if logged in
            <?php if (isset($_SESSION['user_id'])): ?>
            setTimeout(() => {
                window.location.href = 'dashboard.php';
            }, 30000);
            <?php endif; ?>
        });
    </script>
</body>
</html>