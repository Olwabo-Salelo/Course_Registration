<?php
session_start();

$conn = mysqli_connect("localhost", "root", "", "student_course");
if (!$conn) {
    $error = "Connection failed: " . mysqli_connect_error();
}

$error = '';
$response = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (!isset($_POST['role_id']) || !isset($_POST['password']) || !isset($_POST['role'])) {
        $error = "Missing login credentials.";
    } else {
        $user_number = mysqli_real_escape_string($conn, $_POST['role_id']);
        $password = $_POST['password'];
        $role = mysqli_real_escape_string($conn, $_POST['role']);

        $sql = "SELECT * FROM User WHERE user_number='$user_number' AND role='$role'";
        $result = mysqli_query($conn, $sql);

        if ($result && mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);

            if ($password === $user['password']) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['user_number'] = $user['user_number'];
                $_SESSION['logged_in'] = true;

                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => 'Login successful',
                        'role' => $user['role']
                    ]);
                    exit;
                } else {
                    if ($user['role'] == "admin") {
                        header("Location: admin_dashboard.php");
                    } elseif ($user['role'] == "lecturer") {
                        header("Location: lecturer_dashboard.php");
                    } elseif ($user['role'] == "student") {
                        header("Location: student_dashboard.php");
                    }
                    exit;
                }
            } else {
                $error = "Invalid password!";
            }
        } else {
            $error = "No account found with " . ucfirst($role) . " ID: " . htmlspecialchars($user_number);
        }
    }
}

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' && $_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $error]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>SCRSystem | Login - Course Registration Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
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
            padding: 2rem 1.5rem;
            position: relative;
        }
        .bg-shape {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            overflow: hidden;
        }
        .bg-shape::before {
            content: "";
            position: absolute;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(30,111,159,0.08) 0%, rgba(30,111,159,0) 70%);
            border-radius: 50%;
            top: 10%;
            left: -100px;
        }
        .bg-shape::after {
            content: "";
            position: absolute;
            width: 450px;
            height: 450px;
            background: radial-gradient(circle, rgba(15,43,59,0.06) 0%, rgba(15,43,59,0) 70%);
            border-radius: 50%;
            bottom: -150px;
            right: -100px;
        }
        .login-container {
            width: 100%;
            max-width: 460px;
            z-index: 2;
            position: relative;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(2px);
            border-radius: 48px;
            box-shadow: 0 30px 50px -20px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(255,255,255,0.5);
            overflow: hidden;
        }
        .brand-header {
            background: linear-gradient(135deg, #0f2b3b 0%, #1a4b6e 100%);
            padding: 2rem 2rem 1.8rem;
            text-align: center;
            color: white;
        }
        .brand-header h1 {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        .brand-header h1 i { font-size: 2rem; color: #7fc4e8; }
        .brand-header p { font-size: 0.85rem; opacity: 0.85; margin-top: 10px; }
        .role-badge {
            background: rgba(255,255,255,0.2);
            display: inline-block;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 500;
            margin-top: 12px;
        }
        .form-body { padding: 2rem 2rem 2rem; }
        .input-group { margin-bottom: 1.5rem; }
        .input-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            color: #1e3a4d;
            margin-bottom: 8px;
        }
        .input-label i { color: #1e6f9f; width: 20px; }
        .input-field { position: relative; }
        .input-field input, .input-field select {
            width: 100%;
            padding: 14px 16px 14px 46px;
            font-size: 0.95rem;
            font-family: 'Inter', sans-serif;
            border: 1.5px solid #e2edf5;
            border-radius: 30px;
            background: #ffffff;
            transition: all 0.2s;
            outline: none;
        }
        .input-field select {
            padding: 14px 46px 14px 46px;
            appearance: none;
            cursor: pointer;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="%234a6f8f" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>');
            background-repeat: no-repeat;
            background-position: right 18px center;
        }
        .input-field i:first-child {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #8ba9c2;
            font-size: 1rem;
            pointer-events: none;
        }
        .input-field input:focus, .input-field select:focus {
            border-color: #1e6f9f;
            box-shadow: 0 0 0 3px rgba(30, 111, 159, 0.12);
        }
        .options-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 1rem 0 1.5rem;
            font-size: 0.8rem;
        }
        .checkbox-group { display: flex; align-items: center; gap: 8px; }
        .checkbox-group input { width: 16px; height: 16px; cursor: pointer; }
        .forgot-link { color: #1e6f9f; text-decoration: none; font-weight: 500; }
        .forgot-link:hover { text-decoration: underline; }
        .btn-login {
            background: #1e6f9f;
            width: 100%;
            border: none;
            padding: 14px;
            border-radius: 40px;
            font-weight: 700;
            font-size: 1rem;
            color: white;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 8px 18px rgba(0,0,0,0.08);
        }
        .btn-login:hover { background: #0c587f; transform: translateY(-2px); box-shadow: 0 12px 22px rgba(0,0,0,0.12); }
        .signup-prompt {
            text-align: center;
            margin-top: 1.8rem;
            font-size: 0.85rem;
            border-top: 1px solid #eef2f8;
            padding-top: 1.5rem;
        }
        .signup-prompt a { color: #1e6f9f; text-decoration: none; font-weight: 700; }
        .signup-prompt a:hover { text-decoration: underline; }
        .error-message {
            background: #fff5f5;
            border-left: 4px solid #e53e3e;
            padding: 12px 16px;
            border-radius: 20px;
            margin-bottom: 1.5rem;
            font-size: 0.8rem;
            color: #c53030;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .loading-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid white;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 0.6s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (max-width: 500px) {
            .form-body { padding: 1.5rem; }
            .brand-header { padding: 1.5rem; }
        }
    </style>
</head>
<body>
<div class="bg-shape"></div>
<div class="login-container">
    <div class="login-card">
        <div class="brand-header">
            <h1><i class="fas fa-graduation-cap"></i> SCRSystem</h1>
            <p>Course Registration Portal</p>
            <div class="role-badge"><i class="fas fa-shield-alt"></i> Secure Role-Based Authentication</div>
        </div>
        <div class="form-body">
            <?php if ($error && empty($_SERVER['HTTP_X_REQUESTED_WITH'])): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <div id="loginError" class="error-message" style="display: none;">
                <i class="fas fa-exclamation-triangle"></i>
                <span id="errorText">Invalid credentials</span>
            </div>

            <form id="loginForm" method="POST" action="login.php">
                <div class="input-group">
                    <div class="input-label"><i class="fas fa-user-tag"></i> Login as</div>
                    <div class="input-field">
                        <i class="fas fa-badge"></i>
                        <select id="roleSelect" name="role" required>
                            <option value="student">Student</option>
                            <option value="lecturer">Lecturer</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                </div>

                <div class="input-group">
                    <div class="input-label" id="usernameLabel"><i class="fas fa-id-card"></i> Student Number</div>
                    <div class="input-field">
                        <i class="fas fa-user"></i>
                        <input type="text" id="role_id" name="role_id" placeholder="e.g., 2024001234 (9 digits)" autocomplete="username" required>
                    </div>
                </div>

                <div class="input-group">
                    <div class="input-label"><i class="fas fa-lock"></i> Password</div>
                    <div class="input-field">
                        <i class="fas fa-key"></i>
                        <input type="password" id="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                    </div>
                </div>

                <div class="options-row">
                    <label class="checkbox-group">
                        <input type="checkbox" id="rememberMe"> <span>Remember me</span>
                    </label>
                    <a href="forgot_password.php" class="forgot-link" id="forgotPasswordLink"><i class="fas fa-question-circle"></i> Forgot password?</a>
                </div>

                <button type="submit" class="btn-login" id="loginBtn">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>

                <div class="signup-prompt">
                    Don't have an account? <a href="signup.php"><i class="fas fa-user-plus"></i> Create account</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const roleSelect = document.getElementById('roleSelect');
    const roleIdInput = document.getElementById('role_id');
    const usernameLabel = document.getElementById('usernameLabel');
    const passwordInput = document.getElementById('password');
    const loginForm = document.getElementById('loginForm');
    const errorBox = document.getElementById('loginError');
    const errorTextSpan = document.getElementById('errorText');
    const rememberCheck = document.getElementById('rememberMe');
    const loginBtn = document.getElementById('loginBtn');
    
    function updateUsernameField() {
        const role = roleSelect.value;
        if (role === 'student') {
            usernameLabel.innerHTML = '<i class="fas fa-id-card"></i> Student Number';
            roleIdInput.placeholder = 'e.g., 2024001234 (7-10 digits)';
        } else if (role === 'lecturer') {
            usernameLabel.innerHTML = '<i class="fas fa-chalkboard-user"></i> Lecturer ID';
            roleIdInput.placeholder = 'e.g., LEC1001, LEC-001, or 1001';
        } else if (role === 'admin') {
            usernameLabel.innerHTML = '<i class="fas fa-user-shield"></i> Admin ID';
            roleIdInput.placeholder = 'e.g., ADM001, ADMIN-01, or ADM-001';
        }
        hideError();
    }
    
    function showError(message) {
        errorTextSpan.innerText = message;
        errorBox.style.display = 'flex';
        setTimeout(() => {
            if (errorBox.style.display === 'flex') errorBox.style.display = 'none';
        }, 5000);
    }
    
    function hideError() {
        errorBox.style.display = 'none';
        errorTextSpan.innerText = '';
    }
    
    function setLoading(isLoading) {
        if (isLoading) {
            loginBtn.disabled = true;
            loginBtn.innerHTML = '<span class="loading-spinner"></span> Authenticating...';
        } else {
            loginBtn.disabled = false;
            loginBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Sign In';
        }
    }
    
    function validateInput(role, roleId) {
        if (role === 'student') {
            if (!/^\d{7,10}$/.test(roleId)) {
                showError("Student number must be 7-10 digits.");
                return false;
            }
        } else if (role === 'lecturer') {
            if (roleId.length < 3) {
                showError("Lecturer ID must be at least 3 characters.");
                return false;
            }
        } else if (role === 'admin') {
            if (roleId.length < 3) {
                showError("Admin ID must be at least 3 characters.");
                return false;
            }
        }
        return true;
    }
    
    async function handleLogin(event) {
        event.preventDefault();
        hideError();
        
        const selectedRole = roleSelect.value;
        const roleId = roleIdInput.value.trim();
        const password = passwordInput.value;
        
        if (!roleId || !password) {
            showError("Please enter both ID/Number and password.");
            return;
        }
        
        if (!validateInput(selectedRole, roleId)) return;
        
        setLoading(true);
        
        const formData = new URLSearchParams();
        formData.append('role', selectedRole);
        formData.append('role_id', roleId);
        formData.append('password', password);
        
        try {
            const response = await fetch('login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                if (rememberCheck.checked) {
                    localStorage.setItem('remembered_role', selectedRole);
                    localStorage.setItem('remembered_id', roleId);
                } else {
                    localStorage.removeItem('remembered_role');
                    localStorage.removeItem('remembered_id');
                }
                
                showSuccessToast(selectedRole);
                
                setTimeout(() => {
                    if (selectedRole === 'student') window.location.href = 'student_dashboard.php';
                    else if (selectedRole === 'lecturer') window.location.href = 'lecturer_dashboard.php';
                    else if (selectedRole === 'admin') window.location.href = 'admin_dashboard.php';
                }, 1000);
            } else {
                showError(result.message || "Invalid credentials. Please try again.");
                setLoading(false);
            }
        } catch (error) {
            console.error('Login error:', error);
            showError("Network error. Please check your connection and try again.");
            setLoading(false);
        }
    }
    
    function showSuccessToast(role) {
        const toastDiv = document.createElement('div');
        toastDiv.style.position = 'fixed';
        toastDiv.style.bottom = '20px';
        toastDiv.style.left = '50%';
        toastDiv.style.transform = 'translateX(-50%)';
        toastDiv.style.backgroundColor = '#2e7d64';
        toastDiv.style.color = 'white';
        toastDiv.style.padding = '12px 24px';
        toastDiv.style.borderRadius = '60px';
        toastDiv.style.fontWeight = '500';
        toastDiv.style.fontSize = '0.85rem';
        toastDiv.style.zIndex = '9999';
        toastDiv.style.boxShadow = '0 10px 20px rgba(0,0,0,0.15)';
        toastDiv.style.fontFamily = "'Inter', sans-serif";
        let roleDisplay = role === 'student' ? 'Student' : (role === 'lecturer' ? 'Lecturer' : 'Admin');
        toastDiv.innerHTML = `<i class="fas fa-check-circle"></i> Welcome! Redirecting to ${roleDisplay} dashboard...`;
        document.body.appendChild(toastDiv);
        setTimeout(() => {
            toastDiv.style.opacity = '0';
            setTimeout(() => toastDiv.remove(), 500);
        }, 1500);
    }
    
    function loadRemembered() {
        const rememberedRole = localStorage.getItem('remembered_role');
        const rememberedId = localStorage.getItem('remembered_id');
        if (rememberedRole && rememberedId) {
            roleSelect.value = rememberedRole;
            updateUsernameField();
            roleIdInput.value = rememberedId;
            rememberCheck.checked = true;
        }
    }
    
    roleSelect.addEventListener('change', updateUsernameField);
    loginForm.addEventListener('submit', handleLogin);
    
    updateUsernameField();
    loadRemembered();
</script>
</body>
</html>
