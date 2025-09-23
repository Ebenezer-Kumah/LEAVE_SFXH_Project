<?php
// index.php
session_start();
require_once 'includes/config.php';
require_once 'includes/database.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Process login form
$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    if (!empty($email) && !empty($password)) {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT user_id, name, email, password, role FROM Users WHERE email = :email AND is_active = TRUE";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // For demo purposes, we'll use a simple password check
            // In production, use password_verify($password, $user['password'])
            if ($password === 'password123' || password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                
                // Redirect to dashboard
                header('Location: dashboard.php');
                exit();
            } else {
                $error = 'Invalid password.';
            }
        } else {
            $error = 'No account found with that email.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ELMS | St. Francis Xavier Hospital</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            max-width: 400px; 
            margin: 5% auto; 
            background: rgba(255, 255, 255, 0.7); 
            padding: 20px; 
            border-radius: 10px; 
            box-shadow:  rgba(0,0,0,0.1);
        }
        
        .login-form {
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        
        .login-logo {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .login-logo img {
            width: 100px;
            height: 40px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #5bf5f5ff;
            border-radius: 4px;
            font-size: 1rem;
            background-color: #f9f9f9;
            
            

        }
        
        .btn-login {
            width: 100%;
            padding: 12px;
            background-color: #34d5dbff;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-login:hover {
            background-color: #8a162fff;
        }
        
        .error-message {
            color: #e74c3c;
            text-align: center;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #f8d7da;
            border-radius: 4px;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 20px;
            color: #777;
        }
        
        .demo-credentials {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .demo-credentials h4 {
            margin-top: 0;
            color: #2c3e50;
        }
        
        .demo-account {
            margin-bottom: 10px;
            padding: 10px;
            background-color: #e9ecef;
            border-radius: 4px;
        }
    </style>
</head>
<body style="background-image: url('assets/bg4.jpg'); background-size: cover; background-position: center;">
    <div class="login-container">
        <div class="login-form">
            <div class="login-logo">
                <img src="assets/logo.png" alt="St. Francis Xavier Hospital Logo">
                <h2>Employee Leave Management System</h2>
                <p>St. Francis Xavier Hospital</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn-login">Login</button>
            </form>
            
            <!--
                        <div class="demo-credentials">
                            <h4>Demo Credentials:</h4>
                            <div class="demo-account">
                                <strong>Admin:</strong> admin@sfxhospital.org / password123
                            </div>
                            <div class="demo-account">
                                <strong>Manager:</strong> manager@sfxhospital.org / password123
                            </div>
                            <div class="demo-account">
                                <strong>Employee:</strong> employee@sfxhospital.org / password123
                            </div>
                        </div>
            -->
            
            <div class="login-footer">
                <p>© <?php echo date('Y'); ?> St. Francis Xavier Hospital</p>
            </div>
        </div>
    </div>
</body>
</html>