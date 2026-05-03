<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit;
}

$conn = mysqli_connect("localhost", "root", "", "student_course");
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$email = $_SESSION['email'];
$student_number = $_SESSION['user_number'];

$student_id = null;
$query = "SELECT student_id FROM Student WHERE user_id = $user_id";
$result = mysqli_query($conn, $query);
if ($result && mysqli_num_rows($result) > 0) {
    $student_id = mysqli_fetch_assoc($result)['student_id'];
} else {
    mysqli_query($conn, "INSERT INTO Student (user_id) VALUES ($user_id)");
    $student_id = mysqli_insert_id($conn);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax']) && $_POST['ajax'] == 1) {
    ob_clean();
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    
    function send_json($resp) {
        echo json_encode($resp);
        exit;
    }
    
    if ($action === 'request_registration') {
        $course_id = (int)$_POST['course_id'];
        
        $checkEnrolled = mysqli_query($conn, "SELECT registration_id FROM Registration WHERE student_id = $student_id AND course_id = $course_id");
        if (mysqli_num_rows($checkEnrolled) > 0) {
            send_json(['success' => false, 'message' => 'You are already enrolled in this course.']);
        }
        
        $checkReq = mysqli_query($conn, "SELECT request_id FROM RegistrationRequest WHERE student_id = $student_id AND course_id = $course_id AND status = 'pending'");
        if (mysqli_num_rows($checkReq) > 0) {
            send_json(['success' => false, 'message' => 'You already have a pending request for this course.']);
        }
        
        $insert = mysqli_query($conn, "INSERT INTO RegistrationRequest (student_id, course_id, status) VALUES ($student_id, $course_id, 'pending')");
        if ($insert) {
            send_json(['success' => true, 'message' => 'Registration request submitted. Please wait for admin approval.']);
        } else {
            send_json(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
        }
    }
    
    if ($action === 'request_withdrawal') {
        $course_id = (int)$_POST['course_id'];
        
        $getReg = mysqli_query($conn, "SELECT registration_id FROM Registration WHERE student_id = $student_id AND course_id = $course_id");
        if (mysqli_num_rows($getReg) == 0) {
            send_json(['success' => false, 'message' => 'You are not enrolled in this course.']);
        }
        $reg = mysqli_fetch_assoc($getReg);
        $registration_id = $reg['registration_id'];
        
        $checkPending = mysqli_query($conn, "SELECT withdrawal_request_id FROM WithdrawalRequest WHERE registration_id = $registration_id AND status = 'pending'");
        if (mysqli_num_rows($checkPending) > 0) {
            send_json(['success' => false, 'message' => 'You already have a pending withdrawal request for this course.']);
        }
        
        $insert = mysqli_query($conn, "INSERT INTO WithdrawalRequest (registration_id, student_id, course_id, status) VALUES ($registration_id, $student_id, $course_id, 'pending')");
        if ($insert) {
            send_json(['success' => true, 'message' => 'Withdrawal request submitted. Please wait for admin approval.']);
        } else {
            send_json(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
        }
    }
    
    if ($action === 'chat') {
        $question = trim($_POST['question']);
        $answer = processChatbotQuestion($question, $conn, $student_id);
        send_json(['success' => true, 'answer' => $answer]);
    }
    
    send_json(['success' => false, 'message' => 'Unknown action']);
}

$profile_success = '';
$profile_error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $new_full_name = mysqli_real_escape_string($conn, trim($_POST['full_name']));
    $new_email = mysqli_real_escape_string($conn, trim($_POST['email']));
    
    $update_query = "UPDATE User SET full_name = '$new_full_name', email = '$new_email' WHERE user_id = $user_id";
    if (mysqli_query($conn, $update_query)) {
        $_SESSION['full_name'] = $new_full_name;
        $_SESSION['email'] = $new_email;
        $full_name = $new_full_name;
        $email = $new_email;
        $profile_success = "Profile updated successfully!";
    } else {
        $profile_error = "Failed to update profile: " . mysqli_error($conn);
    }
}

function processChatbotQuestion($question, $conn, $student_id) {
    $question = strtolower(trim($question));
    
    if (strpos($question, 'my courses') !== false || strpos($question, 'enrolled courses') !== false) {
        $query = "SELECT c.course_code, c.course_name, c.credits
                  FROM Registration r
                  JOIN Course c ON r.course_id = c.course_id
                  WHERE r.student_id = $student_id";
        $res = mysqli_query($conn, $query);
        if (mysqli_num_rows($res) == 0) return "You are not enrolled in any courses yet.";
        $output = "📚 You are enrolled in:\n";
        while ($row = mysqli_fetch_assoc($res)) {
            $output .= "• {$row['course_code']} - {$row['course_name']} ({$row['credits']} credits)\n";
        }
        return nl2br($output);
    }
    
    if (strpos($question, 'credits') !== false) {
        $query = "SELECT SUM(c.credits) as total FROM Registration r JOIN Course c ON r.course_id = c.course_id WHERE r.student_id = $student_id";
        $res = mysqli_query($conn, $query);
        $row = mysqli_fetch_assoc($res);
        $total = $row['total'] ?? 0;
        return "🎓 Your total credit load is <strong>{$total} / 60</strong> credits.";
    }
    
    $day_mapping = ['monday'=>'Monday','tuesday'=>'Tuesday','wednesday'=>'Wednesday','thursday'=>'Thursday','friday'=>'Friday'];
    $target_day = null;
    foreach ($day_mapping as $key => $day) {
        if (strpos($question, $key) !== false) $target_day = $day;
    }
    if (strpos($question, 'today') !== false) $target_day = date('l');
    if (strpos($question, 'tomorrow') !== false) $target_day = date('l', strtotime('+1 day'));
    
    if ($target_day) {
        $query = "SELECT t.start_time, t.end_time, c.course_code, c.course_name, v.venue_code, v.venue_name
                  FROM Registration r
                  JOIN Course c ON r.course_id = c.course_id
                  JOIN Timetable t ON c.course_id = t.course_id
                  JOIN Venue v ON t.venue_id = v.venue_id
                  WHERE r.student_id = $student_id AND t.day = '$target_day'
                  ORDER BY t.start_time";
        $res = mysqli_query($conn, $query);
        if (mysqli_num_rows($res) == 0) return "📅 No classes on $target_day.";
        $output = "📅 Your timetable for $target_day:<br>";
        while ($row = mysqli_fetch_assoc($res)) {
            $start = date("H:i", strtotime($row['start_time']));
            $end = date("H:i", strtotime($row['end_time']));
            $output .= "• <strong>{$row['course_code']}</strong> {$row['course_name']} - {$start}–{$end} at {$row['venue_code']}<br>";
        }
        return $output;
    }
    
    if (strpos($question, 'next class') !== false) {
        $current_day = date('l');
        $current_time = date('H:i:s');
        $query = "SELECT t.start_time, t.end_time, c.course_code, c.course_name, v.venue_code
                  FROM Registration r
                  JOIN Course c ON r.course_id = c.course_id
                  JOIN Timetable t ON c.course_id = t.course_id
                  JOIN Venue v ON t.venue_id = v.venue_id
                  WHERE r.student_id = $student_id AND t.day = '$current_day' AND t.start_time > '$current_time'
                  ORDER BY t.start_time LIMIT 1";
        $res = mysqli_query($conn, $query);
        if ($row = mysqli_fetch_assoc($res)) {
            $start = date("H:i", strtotime($row['start_time']));
            $end = date("H:i", strtotime($row['end_time']));
            return "⏰ Your next class is {$row['course_code']} today from $start to $end at {$row['venue_code']}.";
        }
        return "📅 No upcoming classes today.";
    }
    
    return "🤖 I can help with:\n• 'My courses'\n• 'Total credits'\n• 'Timetable for Monday'\n• 'Next class'";
}

$enrolled_courses = [];
$query = "SELECT c.course_id, c.course_code, c.course_name, c.credits,
                 COALESCE(wr.status, '') as withdrawal_status
          FROM Registration r
          JOIN Course c ON r.course_id = c.course_id
          LEFT JOIN WithdrawalRequest wr ON r.registration_id = wr.registration_id AND wr.status = 'pending'
          WHERE r.student_id = $student_id";
$res = mysqli_query($conn, $query);
while ($row = mysqli_fetch_assoc($res)) {
    $enrolled_courses[] = $row;
}
$total_credits = array_sum(array_column($enrolled_courses, 'credits'));

$available_courses = [];
$query = "SELECT c.course_id, c.course_code, c.course_name, c.credits,
                 GROUP_CONCAT(CONCAT(t.day, ' ', TIME_FORMAT(t.start_time, '%H:%i'), '-', TIME_FORMAT(t.end_time, '%H:%i')) SEPARATOR ', ') as schedule,
                 COALESCE(rr.status, '') as request_status
          FROM Course c
          LEFT JOIN Timetable t ON c.course_id = t.course_id
          LEFT JOIN RegistrationRequest rr ON c.course_id = rr.course_id AND rr.student_id = $student_id
          WHERE c.course_id NOT IN (
              SELECT course_id FROM Registration WHERE student_id = $student_id
          )
          AND c.course_id NOT IN (
              SELECT wr.course_id
              FROM WithdrawalRequest wr
              JOIN Registration r ON wr.registration_id = r.registration_id
              WHERE r.student_id = $student_id AND wr.status = 'pending'
          )
          GROUP BY c.course_id
          ORDER BY c.course_code";
$res = mysqli_query($conn, $query);
while ($row = mysqli_fetch_assoc($res)) {
    $available_courses[] = $row;
}

$timetable_entries = [];
$query = "SELECT t.day, t.start_time, t.end_time, c.course_code, c.course_name, v.venue_code, v.venue_name
          FROM Registration r
          JOIN Course c ON r.course_id = c.course_id
          JOIN Timetable t ON c.course_id = t.course_id
          JOIN Venue v ON t.venue_id = v.venue_id
          WHERE r.student_id = $student_id
          AND r.registration_id NOT IN (
              SELECT registration_id FROM WithdrawalRequest WHERE status = 'pending'
          )
          ORDER BY FIELD(t.day, 'Monday','Tuesday','Wednesday','Thursday','Friday'), t.start_time";
$res = mysqli_query($conn, $query);
while ($row = mysqli_fetch_assoc($res)) {
    $timetable_entries[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | Course Registration System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* All styles as before – ensure .materials-container etc. exist */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f4f9; color: #1a2c3e; }
        .dashboard-wrapper { display: flex; min-height: 100vh; }
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #0f2b3b 0%, #1a4b6e 100%);
            color: white;
            flex-shrink: 0;
            box-shadow: 2px 0 12px rgba(0,0,0,0.08);
        }
        .sidebar-header { 
            padding: 2rem 1.5rem 1.5rem; 
            border-bottom: 1px solid rgba(255,255,255,0.15); 
        }
        .sidebar-header h2 { 
            font-size: 1.5rem; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
        }
        .nav-menu { 
            padding: 1.8rem 0; 
        }
        .nav-item {
            display: flex; 
            align-items: center; 
            gap: 14px; 
            padding: 0.85rem 1.8rem;
            margin: 4px 12px; 
            border-radius: 40px; 
            cursor: pointer; 
            transition: 0.2s;
            color: #e0edf5; 
            font-weight: 500;
        }
        .nav-item i { 
            width: 24px; 
            font-size: 1.2rem; 
        }
        .nav-item.active { 
            background: rgba(255,255,255,0.2); 
            color: white; 
        }
        .nav-item:hover:not(.active) { 
            background: rgba(255,255,255,0.1); 
        }
        .main-content { 
            flex: 1; 
            padding: 1.8rem 2rem; 
            overflow-x: auto; 
        }
        .top-bar {
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            flex-wrap: wrap;
            background: white; 
            padding: 1rem 1.5rem; 
            border-radius: 28px; 
            margin-bottom: 2rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.03);
        }
        .logout-btn { 
            background: #eef2f8; 
            padding: 8px 20px; 
            border-radius: 40px; 
            color: #1e6f9f; 
            font-weight: 600; 
            text-decoration: none; 
            display: inline-block; 
        }
        .panel { 
            display: none; 
            animation: fadeIn 0.25s ease; 
        }
        .panel.active-panel { 
            display: block; 
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px);} to { opacity: 1; transform: translateY(0);} 
        }
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); 
            gap: 1.5rem; 
            margin-bottom: 2rem; 
        }
        .stat-card { 
            background: white; 
            border-radius: 28px; 
            padding: 1.3rem; 
            box-shadow: 0 5px 12px rgba(0,0,0,0.03); 
            border: 1px solid #e9eef3; 
        }
        .course-table, .timetable-grid, .materials-container { 
            background: white; 
            border-radius: 28px; 
            padding: 1.5rem; 
            margin-top: 1rem; 
            overflow-x: auto; 
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
        }
        th, td { 
            text-align: left; 
            padding: 12px 8px; 
            border-bottom: 1px solid #edf2f7; 
        }
        .btn-register, .btn-withdraw { 
            padding: 6px 16px; 
            border-radius: 40px; 
            border: none; 
            font-weight: 600; 
            cursor: pointer; 
        }
        .btn-register { 
            background: #1e6f9f; 
            color: white; 
        }
        .btn-withdraw { 
            background: #fee2e2; 
            color: #b91c1c; 
        }
        .badge-pending, .badge-withdraw-pending { 
            background: #fff3cd; 
            color: #856404; 
            padding: 4px 10px; 
            border-radius: 40px; 
            font-size: 0.75rem; 
            display: inline-block; 
        }
        .alert-message { background: #fff7e5; border-left: 4px solid #f1c40f; padding: 12px 18px; border-radius: 20px; margin-bottom: 1rem; }
        .profile-form input { padding: 8px 12px; border-radius: 30px; border: 1px solid #ccc; font-family: inherit; width: 100%; max-width: 300px; margin-top: 4px; }
        .profile-form label { font-weight: 600; margin-top: 12px; display: inline-block; }
        .btn-save { background: #10b981; color: white; padding: 8px 20px; border-radius: 40px; border: none; font-weight: 600; cursor: pointer; }
        .course-selector { margin-bottom: 1.5rem; display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
        .course-selector select { padding: 8px 16px; border-radius: 40px; border: 1px solid #ccc; font-family: inherit; }
        .material-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #edf2f7; }
        .material-info a { color: #1e6f9f; text-decoration: none; font-weight: 500; }
        .material-info a:hover { text-decoration: underline; }
        /* Chatbot */
        .chat-widget {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        .chat-toggle {
            width: 60px;
            height: 60px;
            background: #1e6f9f;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: transform 0.2s;
            color: white;
            font-size: 28px;
        }
        .chat-toggle:hover { transform: scale(1.05); background: #0f5a85; }
        .chat-window {
            position: absolute;
            bottom: 80px;
            right: 0;
            width: 380px;
            height: 500px;
            background: white;
            border-radius: 24px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            display: none;
            flex-direction: column;
            overflow: hidden;
            animation: slideUp 0.2s ease;
        }
        .chat-window.open { display: flex; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .chat-header {
            background: #1e6f9f;
            color: white;
            padding: 15px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .chat-header button { background: none; border: none; color: white; font-size: 20px; cursor: pointer; }
        .chat-messages { flex: 1; overflow-y: auto; padding: 1rem; background: #f9fafb; }
        .message { margin-bottom: 1rem; display: flex; align-items: flex-start; gap: 8px; }
        .user-message { justify-content: flex-end; }
        .user-message .bubble { background: #1e6f9f; color: white; border-radius: 20px 20px 4px 20px; }
        .bot-message .bubble { background: #e5e7eb; color: #1f2937; border-radius: 20px 20px 20px 4px; }
        .bubble { max-width: 80%; padding: 8px 14px; font-size: 0.85rem; line-height: 1.4; }
        .chat-input-area { display: flex; padding: 12px; border-top: 1px solid #e5e7eb; background: white; }
        .chat-input-area input { flex: 1; padding: 10px 14px; border-radius: 40px; border: 1px solid #d1d5db; }
        .chat-input-area button { margin-left: 8px; background: #1e6f9f; color: white; border: none; border-radius: 40px; padding: 0 18px; cursor: pointer; font-weight: 600; }
        .typing-indicator { font-style: italic; color: #6b7280; padding: 6px 12px; }
        @media (max-width: 800px) { .dashboard-wrapper { flex-direction: column; } .sidebar { width: 100%; } .nav-menu { display: flex; flex-wrap: wrap; } }
        @media (max-width: 640px) { .chat-window { width: 90vw; right: 5vw; bottom: 80px; } .chat-toggle { width: 50px; height: 50px; font-size: 24px; } }
    .btn-refresh {
    background: #1e6f9f;
    color: white;
    padding: 6px 16px;
    border-radius: 40px;
    border: none;
    font-weight: 600;
    cursor: pointer;
}
.btn-refresh:hover {
    background: #0c587f;
    transform: translateY(-2px);
}
    #timetableContainer table {
    width: 100%;
    border-collapse: collapse;
}
#timetableContainer th, #timetableContainer td {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: left;
}
.btn-export {
    background: #2c7da0;
    color: white;
    padding: 6px 16px;
    border-radius: 40px;
    border: none;
    font-weight: 600;
    cursor: pointer;
}
.btn-export:hover {
    background: #1e5a7a;
    transform: translateY(-2px);
}
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
</head>
<body>
<div class="dashboard-wrapper">
    <aside class="sidebar">
        <div class="sidebar-header"><h2><i class="fas fa-graduation-cap"></i> SCRSystem</h2><p>Student Portal</p></div>
        <div class="nav-menu">
            <div class="nav-item active" data-panel="dashboardPanel"><i class="fas fa-tachometer-alt"></i> Dashboard</div>
            <div class="nav-item" data-panel="coursesPanel"><i class="fas fa-book-open"></i> Course Registration</div>
            <div class="nav-item" data-panel="timetablePanel"><i class="fas fa-calendar-week"></i> My Timetable</div>
            <div class="nav-item" data-panel="enrolledPanel"><i class="fas fa-list-check"></i> Enrolled Courses</div>
            <div class="nav-item" data-panel="materialsPanel"><i class="fas fa-download"></i> Course Materials</div>
            <div class="nav-item" data-panel="profilePanel"><i class="fas fa-user-edit"></i> My Profile</div>
        </div>
    </aside>
    <main class="main-content">
        <div class="top-bar">
            <div><h3>Hello, <?php echo htmlspecialchars($full_name); ?> <i class="fas fa-smile-wink"></i></h3><p>Student Number: <?php echo htmlspecialchars($student_number); ?> | Email: <?php echo htmlspecialchars($email); ?></p></div>
            <a href="login.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>

        <div id="dashboardPanel" class="panel active-panel">
            <div class="stats-grid">
                <div class="stat-card"><i class="fas fa-check-circle" style="color:#1e6f9f; font-size: 2rem;"></i> <h3><?php echo count($enrolled_courses); ?></h3><p>Enrolled Courses</p></div>
                <div class="stat-card"><i class="fas fa-clock"></i> <h3>Auto Conflict</h3> <p>Real-time detection</p></div>
                <div class="stat-card"><i class="fas fa-chalkboard"></i> <h3>Credit Load</h3> <p><?php echo $total_credits; ?> / 60 credits</p></div>
            </div>
            <div class="course-table"><h4><i class="fas fa-bell"></i> Recent Activity</h4><p>System enforces academic rules: max 60 credits, timetable conflict detection.</p><div class="alert-message">✅ You are registered for <?php echo count($enrolled_courses); ?> course(s). Total credits: <?php echo $total_credits; ?>/60.</div></div>
        </div>

        <div id="coursesPanel" class="panel">
            <h3><i class="fas fa-plus-circle"></i> Available Courses</h3>
            <div id="registrationAlert" class="alert-message" style="display: none;"></div>
            <div class="course-table">
                <div style="overflow-x: auto;">
                    <?php if (empty($available_courses)): ?>
                        <p>No available courses at the moment.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr><th>Code</th><th>Course Name</th><th>Credits</th><th>Action</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($available_courses as $course): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                        <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                        <td><?php echo $course['credits']; ?></td>
                                        
                                        <td>
                                            <?php if ($course['request_status'] === 'pending'): ?>
                                                <span class="badge-pending"><i class="fas fa-hourglass-half"></i> Pending Approval</span>
                                            <?php else: ?>
                                                <button class="btn-register" data-id="<?php echo $course['course_id']; ?>">Request Registration</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

      <div id="timetablePanel" class="panel">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 1rem;">
        <h3><i class="fas fa-calendar-alt"></i> My Timetable</h3>
        <button id="exportTimetableBtn" class="btn-export" style="background:#2c7da0; padding: 6px 16px;">
    <i class="fas fa-download"></i> Export as PDF
</button>
    </div>
    <div class="course-table" id="timetableContainer">
        <div style="overflow-x: auto;">
            <?php if (empty($timetable_entries)): ?>
                <p>No timetable entries. Enroll in courses to see your schedule.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr><th>Day</th><th>Time</th><th>Course</th><th>Venue</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($timetable_entries as $entry): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($entry['day']); ?></td>
                                <td><?php echo date("H:i", strtotime($entry['start_time'])) . " – " . date("H:i", strtotime($entry['end_time'])); ?></td>
                                <td><?php echo htmlspecialchars($entry['course_code'] . " - " . $entry['course_name']); ?></td>
                                <td><?php echo htmlspecialchars($entry['venue_code'] . " - " . $entry['venue_name']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

        <div id="enrolledPanel" class="panel">
            <h3><i class="fas fa-user-graduate"></i> My Enrolled Courses</h3>
            <div class="course-table">
                <div style="overflow-x: auto;">
                    <?php if (empty($enrolled_courses)): ?>
                        <p>No courses enrolled yet. Request registration from available courses.</p>
                    <?php else: ?>
                        <table>
                            <thead><tr><th>Code</th><th>Course Name</th><th>Credits</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php foreach ($enrolled_courses as $course): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                        <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                        <td><?php echo $course['credits']; ?></td>
                                        <td>
                                            <?php if ($course['withdrawal_status'] === 'pending'): ?>
                                                <span class="badge-withdraw-pending"><i class="fas fa-hourglass-half"></i> Pending Withdrawal</span>
                                            <?php else: ?>
                                                <button class="btn-withdraw" data-id="<?php echo $course['course_id']; ?>">Request Withdrawal</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div id="materialsPanel" class="panel">
            <h3><i class="fas fa-download"></i> Course Materials</h3>
            <div class="course-selector">
                <label for="materialCourseSelect">Select Course:</label>
                <select id="materialCourseSelect">
                    <?php 
    
                    $activeCourses = array_filter($enrolled_courses, function($c) {
                        return $c['withdrawal_status'] !== 'pending';
                    });
                    foreach ($activeCourses as $course): ?>
                        <option value="<?php echo $course['course_id']; ?>">
                            <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                        </option>
                    <?php endforeach; ?>
                    <?php if (empty($activeCourses)): ?>
                        <option disabled>No active enrolled courses</option>
                    <?php endif; ?>
                </select>
                <button id="refreshMaterialsBtn" class="btn-refresh"><i class="fas fa-sync-alt"></i> Refresh</button>
            </div>
            <div class="materials-container">
                <div id="materialsList">
                    <p>Select a course to view materials.</p>
                </div>
            </div>
        </div>

        <div id="profilePanel" class="panel">
            <h3><i class="fas fa-user-edit"></i> Edit My Profile</h3>
            <?php if ($profile_success): ?>
                <div class="alert-message alert-success"><?php echo $profile_success; ?></div>
            <?php endif; ?>
            <?php if ($profile_error): ?>
                <div class="alert-message alert-error"><?php echo $profile_error; ?></div>
            <?php endif; ?>
            <div class="course-table">
                <form method="POST" action="" class="profile-form">
                    <input type="hidden" name="action" value="update_profile">
                    <label>Full Name:</label><br>
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required><br>
                    <label>Email:</label><br>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required><br>
                    <label>Student Number (read‑only):</label><br>
                    <input type="text" value="<?php echo htmlspecialchars($student_number); ?>" disabled><br><br>
                    <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Changes</button>
                </form>
            </div>
        </div>
    </main>
</div>

<div class="chat-widget">
    <div class="chat-toggle" id="chatToggle"><i class="fas fa-robot"></i></div>
    <div class="chat-window" id="chatWindow">
        <div class="chat-header"><span><i class="fas fa-robot"></i> Ask Me Anything</span><button id="chatCloseBtn">&times;</button></div>
        <div class="chat-messages" id="chatMessages">
            <div class="message bot-message"><div class="bubble">Try:<br>• "My courses"<br>• "Timetable for Monday"<br>• "Total credits"<br>• "Next class"</div></div>
        </div>
        <div class="chat-input-area">
            <input type="text" id="chatInput" placeholder="Type your question..." autocomplete="off">
            <button id="sendChatBtn">Send</button>
        </div>
    </div>
</div>

<script>
    
    const panels = ['dashboardPanel', 'coursesPanel', 'timetablePanel', 'enrolledPanel', 'materialsPanel', 'profilePanel'];
    function switchPanel(panelId) {
        panels.forEach(pid => document.getElementById(pid).classList.remove('active-panel'));
        document.getElementById(panelId).classList.add('active-panel');
        document.querySelectorAll('.nav-item').forEach(item => {
            if (item.getAttribute('data-panel') === panelId) item.classList.add('active');
            else item.classList.remove('active');
        });
    }
    document.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', () => switchPanel(item.getAttribute('data-panel')));
    });

    const chatToggle = document.getElementById('chatToggle');
    const chatWindow = document.getElementById('chatWindow');
    const chatCloseBtn = document.getElementById('chatCloseBtn');
    const chatMessages = document.getElementById('chatMessages');
    const chatInput = document.getElementById('chatInput');
    const sendChatBtn = document.getElementById('sendChatBtn');

    function toggleChat() { chatWindow.classList.toggle('open'); }
    function closeChat() { chatWindow.classList.remove('open'); }
    chatToggle.addEventListener('click', toggleChat);
    chatCloseBtn.addEventListener('click', closeChat);

    function addMessage(text, isUser) {
        const msgDiv = document.createElement('div');
        msgDiv.className = `message ${isUser ? 'user-message' : 'bot-message'}`;
        msgDiv.innerHTML = `<div class="bubble">${text}</div>`;
        chatMessages.appendChild(msgDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function showTypingIndicator() {
        const typingDiv = document.createElement('div');
        typingDiv.id = 'typingIndicator';
        typingDiv.className = 'message bot-message';
        typingDiv.innerHTML = '<div class="bubble typing-indicator">🤖 Typing...</div>';
        chatMessages.appendChild(typingDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function removeTypingIndicator() {
        const typing = document.getElementById('typingIndicator');
        if (typing) typing.remove();
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        }).replace(/\n/g, '<br>');
    }

    async function sendQuestion() {
        const question = chatInput.value.trim();
        if (!question) return;
        addMessage(escapeHtml(question), true);
        chatInput.value = '';
        showTypingIndicator();
        try {
            const formData = new URLSearchParams();
            formData.append('ajax', '1');
            formData.append('action', 'chat');
            formData.append('question', question);
            const response = await fetch('', { method: 'POST', body: formData });
            const data = await response.json();
            removeTypingIndicator();
            if (data.success) addMessage(data.answer, false);
            else addMessage('Sorry, I encountered an error.', false);
        } catch (error) {
            removeTypingIndicator();
            addMessage('Network error. Please refresh.', false);
        }
    }

    sendChatBtn.addEventListener('click', sendQuestion);
    chatInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') sendQuestion(); });

    function attachEvents() {
        document.querySelectorAll('.btn-register').forEach(btn => {
            btn.onclick = () => registerCourse(btn.dataset.id);
        });
        document.querySelectorAll('.btn-withdraw').forEach(btn => {
            btn.onclick = () => requestWithdrawal(btn.dataset.id);
        });
    }

    async function registerCourse(courseId) {
        if (!confirm('Request registration for this course? Admin will review.')) return;
        const formData = new URLSearchParams();
        formData.append('ajax', '1');
        formData.append('action', 'request_registration');
        formData.append('course_id', courseId);
        try {
            const response = await fetch('', { method: 'POST', body: formData });
            const data = await response.json();
            showAlert(data.message, data.success);
            if (data.success) setTimeout(() => location.reload(), 2000);
        } catch(e) {
            console.error('Fetch error:', e);
            showAlert('Network error. Check console and server logs.', false);
        }
    }

    async function requestWithdrawal(courseId) {
        if (!confirm('Request withdrawal from this course? Admin will review and approve.')) return;
        const formData = new URLSearchParams();
        formData.append('ajax', '1');
        formData.append('action', 'request_withdrawal');
        formData.append('course_id', courseId);
        try {
            const response = await fetch('', { method: 'POST', body: formData });
            const data = await response.json();
            showAlert(data.message, data.success);
            if (data.success) setTimeout(() => location.reload(), 2000);
        } catch(e) { showAlert('Network error', false); }
    }

    function showAlert(msg, isSuccess) {
        const alertDiv = document.getElementById('registrationAlert');
        alertDiv.style.display = 'block';
        alertDiv.style.background = isSuccess ? '#e0f2fe' : '#fff3e0';
        alertDiv.style.borderLeftColor = isSuccess ? '#10b981' : '#f59e0b';
        alertDiv.innerHTML = `<i class="fas ${isSuccess ? 'fa-check-circle' : 'fa-exclamation-triangle'}"></i> ${msg}`;
        setTimeout(() => alertDiv.style.display = 'none', 4000);
    }

    attachEvents();

    const materialCourseSelect = document.getElementById('materialCourseSelect');
    const refreshMaterialsBtn = document.getElementById('refreshMaterialsBtn');
    const materialsList = document.getElementById('materialsList');

    function loadMaterials(courseId) {
        if (!courseId) {
            materialsList.innerHTML = '<p>Select a course to view materials.</p>';
            return;
        }
        materialsList.innerHTML = '<p>Loading materials...</p>';
        fetch(`get_course_materials_student.php?course_id=${courseId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.materials.length === 0) {
                        materialsList.innerHTML = '<p>No materials uploaded for this course yet.</p>';
                    } else {
                        let html = '';
                        data.materials.forEach(mat => {
                            html += `
                                <div class="material-item">
                                    <div class="material-info">
                                        <strong>${escapeHtml(mat.title)}</strong><br>
                                        <a href="uploads/course_materials/${escapeHtml(mat.file_name)}" target="_blank">${escapeHtml(mat.original_name)}</a>
                                        <small>(${Math.round(mat.file_size / 1024)} KB, ${mat.upload_date})</small>
                                    </div>
                                </div>
                            `;
                        });
                        materialsList.innerHTML = html;
                    }
                } else {
                    materialsList.innerHTML = '<p>Error: ' + escapeHtml(data.error) + '</p>';
                }
            })
            .catch(error => {
                console.error('Error loading materials:', error);
                materialsList.innerHTML = '<p>Network error. Please try again.</p>';
            });
    }

    if (refreshMaterialsBtn) {
        refreshMaterialsBtn.addEventListener('click', () => {
            const courseId = materialCourseSelect.value;
            if (courseId) loadMaterials(courseId);
        });
    }
    if (materialCourseSelect) {
        materialCourseSelect.addEventListener('change', () => {
            const courseId = materialCourseSelect.value;
            if (courseId) loadMaterials(courseId);
        })
        if (materialCourseSelect.value && materialCourseSelect.options[0]?.disabled !== true) {
            loadMaterials(materialCourseSelect.value);
        }
    }
    
const exportTimetableBtn = document.getElementById('exportTimetableBtn');
if (exportTimetableBtn) {
    exportTimetableBtn.addEventListener('click', function() {
        const element = document.getElementById('timetableContainer');
        if (!element) {
            alert('Timetable container not found.');
            return;
        }
        const originalText = exportTimetableBtn.innerHTML;
        exportTimetableBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating PDF...';
        exportTimetableBtn.disabled = true;
        
        const opt = {
            margin:        [0.5, 0.5, 0.5, 0.5],
            filename:     'my_timetable.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, letterRendering: true },
            jsPDF:        { unit: 'in', format: 'a4', orientation: 'landscape' }
        };
        
        html2pdf().set(opt).from(element).save()
            .then(() => {
                exportTimetableBtn.innerHTML = originalText;
                exportTimetableBtn.disabled = false;
            })
            .catch(err => {
                console.error('PDF export error:', err);
                alert('Failed to generate PDF. Please try again.');
                exportTimetableBtn.innerHTML = originalText;
                exportTimetableBtn.disabled = false;
            });
    });
}
</script>
</body>
</html>