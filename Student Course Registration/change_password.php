<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
//$conn is a connection string used to connect to a databse named student_course
$conn = mysqli_connect("localhost", "root", "", "student_course");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$error = "";
$success = "";
$user_id = $_SESSION['user_id'];
//the code bellow allows accessing and performing functions to the database byt authorised users
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $query = "SELECT password FROM User WHERE user_id = $user_id";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $stored_password = $row['password'];

        if ($current_password !== $stored_password) {
            $error = "Current password is incorrect.";
        } elseif ($new_password !== $confirm_password) {
            $error = "New passwords do not match.";
        } elseif (strlen($new_password) < 8) {
            $error = "Password must be at least 8 characters.";
        } else {
            
            $update = "UPDATE User SET password = '$new_password' WHERE user_id = $user_id";
            if (mysqli_query($conn, $update)) {
                $success = "Password changed successfully!";
                
            } else {
                $error = "Database error: " . mysqli_error($conn);
            }
        }
    } else {
        $error = "User not found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password | Course Registration System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(145deg, #eef5fa 0%, #dce8f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .change-pwd-container {
            max-width: 460px;
            width: 100%;
        }
        .card {
            background: white;
            border-radius: 48px;
            box-shadow: 0 30px 50px -20px rgba(0, 0, 0, 0.25);
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, #0f2b3b 0%, #1a4b6e 100%);
            padding: 1.8rem 2rem;
            text-align: center;
            color: white;
        }
        .card-header h1 { font-size: 1.8rem; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .card-body { padding: 2rem; }
        .form-group { margin-bottom: 1.2rem; }
        input {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #e2e8f0;
            border-radius: 40px;
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
        }
        button {
            background: #1e6f9f;
            width: 100%;
            border: none;
            padding: 12px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 1rem;
            color: white;
            cursor: pointer;
            transition: 0.2s;
        }
        button:hover { 
            background: #0c587f; 
            transform: translateY(-2px); 
        }
        .alert { 
            padding: 12px; 
            border-radius: 20px; 
            margin-bottom: 1.2rem; 
            font-size: 0.85rem; 
        }
        .alert-success { 
            background: #d1fae5; 
            color: #065f46; 
        }
        .alert-error { 
            background: #fee2e2; 
            color: #b91c1c; 
        }
        .back-link { 
            text-align: center; 
            margin-top: 1rem; 
        }
        .back-link a { 
            color: #1e6f9f; 
            text-decoration: none; 
        }
    </style>
</head>
<body>
<div class="change-pwd-container">
    <div class="card">
        <div class="card-header">
            <h1><i class="fas fa-key"></i> Change Password</h1>
            <p>Update your password securely</p>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group"><input type="password" name="current_password" placeholder="Current Password" required></div>
                <div class="form-group"><input type="password" name="new_password" placeholder="New Password (min 8 chars)" required></div>
                <div class="form-group"><input type="password" name="confirm_password" placeholder="Confirm New Password" required></div>
                <button type="submit"><i class="fas fa-save"></i> Change Password</button>
            </form>
            <div class="back-link"><a href="<?php
                $role = $_SESSION['role'] ?? '';
                if ($role == 'student') echo 'student_dashboard.php';
                elseif ($role == 'lecturer') echo 'lecturer_dashboard.php';
                elseif ($role == 'admin') echo 'admin_dashboard.php';
                else echo 'login.php';
            ?>"><i class="fas fa-arrow-left"></i> Back to Dashboard</a></div>
        </div>
    </div>
</div>
</body>
</html>
