<?php
session_start();
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

$conn = mysqli_connect("localhost", "root", "", "student_course");
if (!$conn) die("Connection failed: " . mysqli_connect_error());

$error = "";
$success = "";
$step = 1;
$email = "";

function sendOTPEmail($toEmail, $otp) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'saleloolwabo99@gmail.com';
        $mail->Password   = 'vscgfedlhlnvgsfg';
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        $mail->setFrom('noreply@youruniversity.ac.za', 'Course Registration System');
        $mail->addAddress($toEmail);
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset OTP';
        $mail->Body    = "<h2>Password Reset Request</h2>
                          <p>Your OTP code is: <strong style='font-size:1.4em;color:#1e6f9f;'>$otp</strong></p>
                          <p>It will expire in <strong>10 minutes</strong>.</p>
                          <p>If you didn't request this, please ignore this email.</p>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST['email']) && !isset($_POST['otp'])) {
        $email = trim($_POST['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            $stmt = $conn->prepare("SELECT user_id FROM User WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows === 1) {
                $stmt->bind_result($user_id);
                $stmt->fetch();

                $otp = rand(100000, 999999);
                $expiry = date("Y-m-d H:i:s", strtotime("+10 minutes"));

                $stmt2 = $conn->prepare("UPDATE User SET reset_otp = ?, reset_expiry = ? WHERE user_id = ?");
                $stmt2->bind_param("ssi", $otp, $expiry, $user_id);
                $stmt2->execute();
                $stmt2->close();

                if (sendOTPEmail($email, $otp)) {
                    $success = "OTP sent successfully! Check your email.";
                    $step = 2;
                } else {
                    $error = "Failed to send OTP. Please try again later.";
                }
            } else {
                $error = "If your email is registered, an OTP has been sent.";
            }
            $stmt->close();
        }
    }

    if (isset($_POST['otp'])) {
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $otp = mysqli_real_escape_string($conn, $_POST['otp']);
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if ($new_password !== $confirm_password) {
            $error = "Passwords do not match.";
            $step = 2;
        } elseif (strlen($new_password) < 6) {
            $error = "Password must be at least 6 characters long.";
            $step = 2;
        } else {
            $stmt = $conn->prepare("SELECT user_id, reset_expiry FROM User WHERE email = ? AND reset_otp = ?");
            $stmt->bind_param("ss", $email, $otp);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                if (strtotime($row['reset_expiry']) < time()) {
                    $error = "OTP has expired. Please request a new one.";
                    $step = 1;
                } else {
                    $update = $conn->prepare("UPDATE User SET password = ?, reset_otp = NULL, reset_expiry = NULL WHERE user_id = ?");
                    $update->bind_param("si", $new_password, $row['user_id']);
                    if ($update->execute()) {
                        $success = "Password reset successful! You can now <a href='login.php' style='color:#065f46; font-weight:bold;'>login here</a>.";
                        $step = 1;
                    } else {
                        $error = "Database error. Please try again.";
                        $step = 2;
                    }
                    $update->close();
                }
            } else {
                $error = "Invalid OTP. Please try again.";
                $step = 2;
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Course Registration System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
        .card {
            background: white;
            max-width: 450px;
            width: 100%;
            border-radius: 48px;
            padding: 2rem;
            box-shadow: 0 30px 50px -20px rgba(0,0,0,0.25);
        }
        .card h2 {
            text-align: center;
            color: #0f2b3b;
            margin-bottom: 1.5rem;
        }
        .form-group {
            margin-bottom: 1.2rem;
        }
        label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: block;
            color: #1e3a4d;
        }
        input {
            width: 100%;
            padding: 12px 16px;
            border-radius: 40px;
            border: 1.5px solid #e2edf5;
            font-family: inherit;
            font-size: 0.95rem;
        }
        input:focus {
            outline: none;
            border-color: #1e6f9f;
            box-shadow: 0 0 0 3px rgba(30,111,159,0.12);
        }
        button {
            width: 100%;
            background: #1e6f9f;
            color: white;
            padding: 12px;
            border-radius: 40px;
            border: none;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: 0.2s;
        }
        button:hover {
            background: #0c587f;
            transform: translateY(-2px);
        }
        .error {
            background: #fee2e2;
            color: #b91c1c;
            padding: 10px;
            border-radius: 28px;
            margin-bottom: 1rem;
            font-size: 0.85rem;
            text-align: center;
        }
        .success {
            background: #d1fae5;
            color: #065f46;
            padding: 10px;
            border-radius: 28px;
            margin-bottom: 1rem;
            font-size: 0.85rem;
            text-align: center;
        }
        .back-link {
            text-align: center;
            margin-top: 1.2rem;
        }
        .back-link a {
            color: #1e6f9f;
            text-decoration: none;
            font-weight: 500;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="card">
    <h2><i class="fas fa-key"></i> Forgot Password</h2>

    <?php if ($error): ?>
        <div class="error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
    <?php endif; ?>

    <?php if ($step == 1): ?>
        <form method="POST">
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Your Email Address</label>
                <input type="email" name="email" placeholder="Enter your registered email" required value="<?php echo htmlspecialchars($email); ?>">
            </div>
            <button type="submit"><i class="fas fa-paper-plane"></i> Send OTP</button>
            <div class="back-link"><a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a></div>
        </form>
    <?php else: ?>
        <form method="POST">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
            <div class="form-group">
                <label><i class="fas fa-key"></i> OTP Code</label>
                <input type="text" name="otp" placeholder="Enter 6-digit OTP" required pattern="[0-9]{6}">
            </div>
            <div class="form-group">
                <label><i class="fas fa-lock"></i> New Password</label>
                <input type="password" name="new_password" placeholder="Minimum 6 characters" required minlength="6">
            </div>
            <div class="form-group">
                <label><i class="fas fa-check-circle"></i> Confirm Password</label>
                <input type="password" name="confirm_password" placeholder="Retype new password" required minlength="6">
            </div>
            <button type="submit"><i class="fas fa-save"></i> Reset Password</button>
            <div class="back-link"><a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a></div>
        </form>
    <?php endif; ?>
</div>
</body>
</html>