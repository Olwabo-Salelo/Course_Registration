<?php
session_start();
$conn = mysqli_connect("localhost", "root", "", "student_course");

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $fullName        = mysqli_real_escape_string($conn, $_POST['full_name']);
    $studentNumber   = mysqli_real_escape_string($conn, $_POST['student_number']);
    $email           = mysqli_real_escape_string($conn, $_POST['email']);
    $pwd             = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];
    $role = 'student';

    if (empty($fullName) || empty($studentNumber) || empty($email) || empty($pwd)) {
        $error = "All fields are required!";
    } 
    elseif ($pwd !== $confirmPassword) {
        $error = "Passwords do not match!";
    }
    elseif (!preg_match('/^\d{7,10}$/', $studentNumber)) {
        $error = "Student number must be 7-10 digits!";
    }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address!";
    }
    elseif (strlen($pwd) < 8) {
        $error = "Password must be at least 8 characters!";
    }
    elseif (!preg_match('/[A-Z]/', $pwd)) {
        $error = "Password must contain at least one uppercase letter!";
    }
    elseif (!preg_match('/[0-9]/', $pwd)) {
        $error = "Password must contain at least one number!";
    }
    else {
        $checkQuery = "SELECT email, user_number FROM User WHERE email='$email' OR user_number='$studentNumber'";
        $result = mysqli_query($conn, $checkQuery);

        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                if ($row['email'] === $email) {
                    $error = "Email already exists!";
                } elseif ($row['user_number'] === $studentNumber) {
                    $error = "Student number already exists!";
                }
            }
        } else {
        
            $sql = "INSERT INTO User (full_name, email, password, role, user_number) 
                    VALUES ('$fullName', '$email', '$pwd', '$role', '$studentNumber')";

            if (mysqli_query($conn, $sql)) {
                $success = "Student account created successfully! You can now log in.";
            } else {
                $error = "Error: " . mysqli_error($conn);
            }
        }
    }
    
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        if ($success) {
            echo json_encode(['success' => true, 'message' => $success]);
        } else {
            echo json_encode(['success' => false, 'message' => $error]);
        }
        exit;
    } else {
        if ($success) {
            header("Location: signup.php?success=" . urlencode($success));
            exit;
        } elseif ($error) {
            header("Location: signup.php?error=" . urlencode($error));
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>SCRSystem | Student Sign Up - Create Account</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #e9f2f8 0%, #dbe9f2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .signup-container {
            max-width: 560px;
            width: 100%;
            margin: 0 auto;
        }

        .signup-card {
            background: white;
            border-radius: 36px;
            box-shadow: 0 25px 45px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
        }

        .card-header {
            background: #0f2b3b;
            padding: 1.8rem 2rem 1.5rem;
            text-align: center;
            color: white;
        }

        .card-header h1 {
            font-size: 1.9rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .card-header h1 i {
            color: #6ab0de;
        }

        .card-header p {
            font-size: 0.85rem;
            opacity: 0.8;
            margin-top: 8px;
        }

        .student-badge {
            display: inline-block;
            background: rgba(106, 176, 222, 0.2);
            padding: 0.3rem 1rem;
            border-radius: 40px;
            font-size: 0.7rem;
            margin-top: 10px;
        }

        .card-body {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.4rem;
        }

        .form-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            color: #1e3a4d;
            margin-bottom: 6px;
        }

        .form-group label i {
            width: 20px;
            color: #1e6f9f;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-wrapper input {
            width: 100%;
            padding: 12px 16px 12px 42px;
            font-size: 0.95rem;
            font-family: 'Inter', sans-serif;
            border: 1.5px solid #e2e8f0;
            border-radius: 28px;
            transition: 0.2s;
            background: #fefefe;
            outline: none;
        }

        .input-wrapper i:first-child {
            position: absolute;
            left: 16px;
            color: #8ba9c2;
            font-size: 1rem;
            pointer-events: none;
            z-index: 1;
        }

        .input-wrapper input:focus {
            border-color: #1e6f9f;
            box-shadow: 0 0 0 3px rgba(30, 111, 159, 0.15);
        }

        .role-indicator {
            background: #eef5fc;
            border-radius: 28px;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 1.5rem;
            border: 1.5px solid #d4e4f0;
        }

        .role-indicator i {
            font-size: 1.5rem;
            color: #1e6f9f;
        }

        .role-indicator .role-info {
            flex: 1;
        }

        .role-indicator .role-info strong {
            display: block;
            color: #0f2b3b;
            font-size: 0.95rem;
        }

        .role-indicator .role-info small {
            font-size: 0.7rem;
            color: #5c7f9c;
        }

        .password-hint {
            font-size: 0.7rem;
            margin-top: 6px;
            margin-left: 12px;
            color: #5c7f9c;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .strength-meter {
            height: 4px;
            background: #e2e8f0;
            border-radius: 4px;
            margin-top: 8px;
            width: 100%;
        }

        .strength-fill {
            height: 4px;
            border-radius: 4px;
            width: 0%;
            background: #d9534f;
            transition: 0.2s;
        }

        .terms-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 1.5rem 0 1.2rem;
            font-size: 0.8rem;
        }

        .terms-group input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .btn-signup {
            background: #1e6f9f;
            width: 100%;
            border: none;
            padding: 14px;
            border-radius: 40px;
            font-weight: 700;
            font-size: 1rem;
            color: white;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-signup:hover {
            background: #0c587f;
            transform: translateY(-2px);
        }

        .btn-signup:disabled {
            background: #9ab3c4;
            cursor: not-allowed;
            transform: none;
        }

        .login-redirect {
            text-align: center;
            margin-top: 1.8rem;
            font-size: 0.85rem;
            border-top: 1px solid #edf2f7;
            padding-top: 1.5rem;
        }

        .login-redirect a {
            color: #1e6f9f;
            text-decoration: none;
            font-weight: 600;
        }

        .login-redirect a:hover {
            text-decoration: underline;
        }

        .error-msg {
            color: #c0392b;
            font-size: 0.7rem;
            margin-top: 5px;
            margin-left: 12px;
        }

        .alert-message {
            background: #d1fae5;
            color: #065f46;
            padding: 12px;
            border-radius: 16px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
        }

        .alert-error {
            background: #fee2e2;
            color: #b91c1c;
        }

        .info-note {
            font-size: 0.7rem;
            margin-top: 0.7rem;
            text-align: center;
            color: #4f7a9e;
            background: #f8fbfe;
            padding: 0.6rem;
            border-radius: 20px;
        }

        @media (max-width: 550px) {
            .card-body {
                padding: 1.5rem;
            }
            .card-header h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>

<div class="signup-container">
    <div class="signup-card">
        <div class="card-header">
            <h1><i class="fas fa-user-graduate"></i> Student Registration</h1>
            <p>Course Registration System - Create Your Student Account</p>
            <span class="student-badge"><i class="fas fa-graduation-cap"></i> Student Registration Only</span>
        </div>
        <div class="card-body">
            <div class="role-indicator">
                <i class="fas fa-user-graduate"></i>
                <div class="role-info">
                    <strong>Student Account Registration</strong>
                    <small>Fill in your details to create a student account</small>
                </div>
            </div>

            <div id="messageContainer">
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert-message">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['success']); ?>
                    </div>
                    <script>
                        setTimeout(function() {
                            window.location.href = 'login.php';
                        }, 2000);
                    </script>
                <?php endif; ?>
                
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert-message alert-error">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
                    </div>
                <?php endif; ?>
            </div>

            <form id="signupForm" method="POST" action="signup.php">
                
                <div class="form-group">
                    <label><i class="fas fa-user-circle"></i> Full Name <span style="color:#e74c3c;">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-user"></i>
                        <input type="text" id="full_name" name="full_name" placeholder="e.g., Thabo Nkosi" autocomplete="name" required>
                    </div>
                    <div class="error-msg" id="fullNameError"></div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-id-card"></i> Student Number <span style="color:#e74c3c;">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-hashtag"></i>
                        <input type="text" id="student_number" name="student_number" placeholder="e.g., 2024001234 (7-10 digits)" autocomplete="off" required>
                    </div>
                    <div class="error-msg" id="studentNumberError"></div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email Address <span style="color:#e74c3c;">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" placeholder="student@university.ac.za" autocomplete="email" required>
                    </div>
                    <div class="error-msg" id="emailError"></div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password <span style="color:#e74c3c;">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-key"></i>
                        <input type="password" id="password" name="password" placeholder="Create strong password" required>
                    </div>
                    <div class="password-hint">
                        <span><i class="fas fa-check-circle"></i> 8+ chars</span>
                        <span><i class="fas fa-check-circle"></i> 1 uppercase</span>
                        <span><i class="fas fa-check-circle"></i> 1 number</span>
                    </div>
                    <div class="strength-meter"><div class="strength-fill" id="strengthFill"></div></div>
                    <div class="error-msg" id="passwordError"></div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Confirm Password <span style="color:#e74c3c;">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-check-circle"></i>
                        <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm password" required>
                    </div>
                    <div class="error-msg" id="confirmError"></div>
                </div>

                <div class="terms-group">
                    <input type="checkbox" id="termsCheckbox" required>
                    <label for="termsCheckbox">I agree to the <a href="#">Student Terms of Use</a> and <a href="#">Privacy Policy</a>.</label>
                </div>
                <div class="error-msg" id="termsError"></div>

                <button type="submit" class="btn-signup" id="submitBtn">
                    <i class="fas fa-user-plus"></i> Create Student Account
                </button>
                
                <div class="login-redirect">
                    Already have an account? <a href="login.php"><i class="fas fa-sign-in-alt"></i> Sign in to portal</a>
                </div>
            </form>
            <div class="info-note">
                <i class="fas fa-shield-alt"></i> Your password is securely hashed using bcrypt.
            </div>
        </div>
    </div>
</div>

<script>
    const form = document.getElementById('signupForm');
    const submitBtn = document.getElementById('submitBtn');
    
    const fullNameInput = document.getElementById('full_name');
    const studentNumberInput = document.getElementById('student_number');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const confirmInput = document.getElementById('confirmPassword');
    const termsCheck = document.getElementById('termsCheckbox');

    const fullNameError = document.getElementById('fullNameError');
    const studentNumberError = document.getElementById('studentNumberError');
    const emailError = document.getElementById('emailError');
    const passwordError = document.getElementById('passwordError');
    const confirmError = document.getElementById('confirmError');
    const termsError = document.getElementById('termsError');
    
    const strengthFill = document.getElementById('strengthFill');
    
    function evaluatePasswordStrength(pw) {
        let strength = 0;
        if (pw.length >= 8) strength++;
        if (/[A-Z]/.test(pw)) strength++;
        if (/[0-9]/.test(pw)) strength++;
        let percent = strength === 1 ? 25 : strength === 2 ? 50 : strength === 3 ? 75 : 100;
        strengthFill.style.width = percent + '%';
        strengthFill.style.backgroundColor = percent <= 25 ? '#d9534f' : percent <= 50 ? '#f0ad4e' : percent <= 75 ? '#5bc0de' : '#5cb85c';
    }
    
    function validateFullName() {
        const name = fullNameInput.value.trim();
        if (name.length < 2) {
            fullNameError.innerText = 'Full name must be at least 2 characters.';
            return false;
        } else if (!/^[A-Za-zÀ-ÖØ-öø-ÿ\s\-']+$/.test(name)) {
            fullNameError.innerText = 'Use letters and spaces only.';
            return false;
        }
        fullNameError.innerText = '';
        return true;
    }
    
    function validateStudentNumber() {
        const studentNum = studentNumberInput.value.trim();
        if (studentNum === '') {
            studentNumberError.innerText = 'Student number is required.';
            return false;
        }
        if (!/^\d{7,10}$/.test(studentNum)) {
            studentNumberError.innerText = 'Student number must be 7-10 digits only.';
            return false;
        }
        studentNumberError.innerText = '';
        return true;
    }
    
    function validateEmail() {
        const email = emailInput.value.trim();
        const emailPattern = /^[^\s@]+@([^\s@]+\.)+[^\s@]+$/;
        if (!emailPattern.test(email)) {
            emailError.innerText = 'Enter a valid email address.';
            return false;
        }
        emailError.innerText = '';
        return true;
    }
    
    function validatePassword() {
        const pwd = passwordInput.value;
        let isValid = true;
        if (pwd.length < 8) {
            passwordError.innerText = 'Password must be at least 8 characters.';
            isValid = false;
        } else if (!/[A-Z]/.test(pwd)) {
            passwordError.innerText = 'Password must contain at least one uppercase letter.';
            isValid = false;
        } else if (!/[0-9]/.test(pwd)) {
            passwordError.innerText = 'Password must contain at least one number.';
            isValid = false;
        } else {
            passwordError.innerText = '';
        }
        evaluatePasswordStrength(pwd);
        if (confirmInput.value) validateConfirm();
        return isValid;
    }
    
    function validateConfirm() {
        if (passwordInput.value !== confirmInput.value) {
            confirmError.innerText = 'Passwords do not match.';
            return false;
        }
        confirmError.innerText = '';
        return true;
    }
    
    function validateTerms() {
        if (!termsCheck.checked) {
            termsError.innerText = 'You must agree to the terms & conditions.';
            return false;
        }
        termsError.innerText = '';
        return true;
    }
    
    studentNumberInput.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
        validateStudentNumber();
    });
    
    fullNameInput.addEventListener('input', validateFullName);
    studentNumberInput.addEventListener('input', validateStudentNumber);
    emailInput.addEventListener('input', validateEmail);
    passwordInput.addEventListener('input', () => { validatePassword(); validateConfirm(); });
    confirmInput.addEventListener('input', validateConfirm);
    termsCheck.addEventListener('change', validateTerms);
    
    form.addEventListener('submit', function(e) {
        if (!validateFullName() || !validateStudentNumber() || !validateEmail() || !validatePassword() || !validateConfirm() || !validateTerms()) {
            e.preventDefault();
            const messageContainer = document.getElementById('messageContainer');
            messageContainer.innerHTML = '<div class="alert-message alert-error"><i class="fas fa-exclamation-triangle"></i> Please fix the errors above.</div>';
        }
    });
</script>
</body>
</html>