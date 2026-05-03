<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'lecturer') {
    header("Location: login.php");
    exit;
}

$conn = mysqli_connect("localhost", "root", "", "student_course");
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM Registration LIKE 'grade'");
if ($checkColumn && mysqli_num_rows($checkColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE Registration ADD COLUMN grade VARCHAR(5) DEFAULT NULL");
}

$tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'CourseMaterial'");
if (mysqli_num_rows($tableCheck) == 0) {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS CourseMaterial (
        material_id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        file_type VARCHAR(100),
        file_size INT,
        upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES Course(course_id) ON DELETE CASCADE
    )");
}

$uploadDir = "uploads/course_materials/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$user_id = $_SESSION['user_id'];
$lecturer = null;
$lecturer_id = null;

$query = "SELECT u.full_name, u.email, u.user_number, l.title, l.lecturer_id 
          FROM User u 
          JOIN Lecturer l ON u.user_id = l.user_id 
          WHERE u.user_id = $user_id";
$result = mysqli_query($conn, $query);
if ($result && mysqli_num_rows($result) > 0) {
    $lecturer = mysqli_fetch_assoc($result);
    $lecturer_id = $lecturer['lecturer_id'];
} else {
    $lecturer = ['full_name' => 'Lecturer', 'email' => '', 'title' => '', 'user_number' => ''];
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $new_full_name = mysqli_real_escape_string($conn, trim($_POST['full_name']));
    $new_email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $new_title = mysqli_real_escape_string($conn, trim($_POST['title']));
    
    $update_user = "UPDATE User SET full_name = '$new_full_name', email = '$new_email' WHERE user_id = $user_id";
    $update_lecturer = "UPDATE Lecturer SET title = '$new_title' WHERE user_id = $user_id";
    
    $user_ok = mysqli_query($conn, $update_user);
    $lecturer_ok = mysqli_query($conn, $update_lecturer);
    
    if ($user_ok && $lecturer_ok) {
        header("Location: lecturer_dashboard.php?profile_updated=1");
        exit;
    } else {
        $profile_error = "Failed to update profile: " . mysqli_error($conn);
    }
}

$profile_success = isset($_GET['profile_updated']) ? "Profile updated successfully!" : "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    
    if ($action === 'chat') {
        $question = trim($_POST['question']);
        $answer = processLecturerChatbotQuestion($question, $conn, $lecturer_id);
        echo json_encode(['success' => true, 'answer' => $answer]);
        exit;
    }
    
    if ($action === 'update_grade') {
        $registration_id = (int)$_POST['registration_id'];
        $grade = mysqli_real_escape_string($conn, $_POST['grade']);
        $update = "UPDATE Registration SET grade = '$grade' WHERE registration_id = $registration_id";
        if (mysqli_query($conn, $update)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
        }
        exit;
    }
    
    if ($action === 'delete_material') {
        $material_id = (int)$_POST['material_id'];
        $query = "SELECT file_name FROM CourseMaterial WHERE material_id = $material_id";
        $res = mysqli_query($conn, $query);
        if ($row = mysqli_fetch_assoc($res)) {
            $filePath = $uploadDir . $row['file_name'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $delete = mysqli_query($conn, "DELETE FROM CourseMaterial WHERE material_id = $material_id");
            if ($delete) {
                echo json_encode(['success' => true, 'message' => 'Material deleted']);
            } else {
                echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Material not found']);
        }
        exit;
    }
}

$upload_success = '';
$upload_error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_material'])) {
    $course_id = (int)$_POST['course_id'];
    $title = mysqli_real_escape_string($conn, trim($_POST['title']));
    
    $checkCourse = mysqli_query($conn, "SELECT course_id FROM Course WHERE course_id = $course_id AND lecturer_id = $lecturer_id");
    if (mysqli_num_rows($checkCourse) == 0) {
        $upload_error = "Invalid course selected.";
    } elseif (empty($_FILES['material_file']['name'])) {
        $upload_error = "Please select a file to upload.";
    } else {
        $file = $_FILES['material_file'];
        $originalName = basename($file['name']);
        $fileType = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedTypes = ['pdf', 'ppt', 'pptx', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'mp4', 'zip'];
        
        if (!in_array($fileType, $allowedTypes)) {
            $upload_error = "File type not allowed. Allowed: " . implode(', ', $allowedTypes);
        } elseif ($file['size'] > 50 * 1024 * 1024) {
            $upload_error = "File size too large (max 50MB).";
        } else {
            $newFileName = time() . '_' . uniqid() . '.' . $fileType;
            $destination = $uploadDir . $newFileName;
            
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $insert = "INSERT INTO CourseMaterial (course_id, title, file_name, original_name, file_type, file_size) 
                           VALUES ($course_id, '$title', '$newFileName', '$originalName', '$fileType', {$file['size']})";
                if (mysqli_query($conn, $insert)) {
                    $upload_success = "Material uploaded successfully!";
                } else {
                    $upload_error = "Database error: " . mysqli_error($conn);
                    unlink($destination);
                }
            } else {
                $upload_error = "Failed to move uploaded file.";
            }
        }
    }
}

function processLecturerChatbotQuestion($question, $conn, $lecturer_id) {
    $question = strtolower(trim($question));
    
    if (strpos($question, 'my courses') !== false || strpos($question, 'courses i teach') !== false || strpos($question, 'what courses') !== false) {
        $query = "SELECT course_code, course_name FROM Course WHERE lecturer_id = $lecturer_id";
        $res = mysqli_query($conn, $query);
        if (mysqli_num_rows($res) == 0) {
            return "You are not assigned to any courses yet. Contact the administrator.";
        }
        $output = "📚 You are teaching the following courses:\n";
        while ($row = mysqli_fetch_assoc($res)) {
            $output .= "• {$row['course_code']} - {$row['course_name']}\n";
        }
        return nl2br($output);
    }
    
    $day_mapping = [
        'monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday',
        'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday'
    ];
    $target_day = null;
    foreach ($day_mapping as $key => $day) {
        if (strpos($question, $key) !== false) {
            $target_day = $day;
            break;
        }
    }
    if (strpos($question, 'today') !== false) $target_day = date('l');
    if (strpos($question, 'tomorrow') !== false) $target_day = date('l', strtotime('+1 day'));
    
    if ($target_day) {
        $query = "SELECT t.start_time, t.end_time, c.course_code, c.course_name, v.venue_code, v.venue_name
                  FROM Timetable t
                  JOIN Course c ON t.course_id = c.course_id
                  JOIN Venue v ON t.venue_id = v.venue_id
                  WHERE c.lecturer_id = $lecturer_id AND t.day = '$target_day'
                  ORDER BY t.start_time";
        $res = mysqli_query($conn, $query);
        if (mysqli_num_rows($res) == 0) {
            return "You have no classes on <strong>$target_day</strong>.";
        }
        $output = "Your timetable for <strong>$target_day</strong>:<br>";
        while ($row = mysqli_fetch_assoc($res)) {
            $start = date("H:i", strtotime($row['start_time']));
            $end = date("H:i", strtotime($row['end_time']));
            $output .= "• <strong>{$row['course_code']}</strong> {$row['course_name']} - {$start}–{$end} at {$row['venue_code']} ({$row['venue_name']})<br>";
        }
        return $output;
    }
    
    if (strpos($question, 'next class') !== false || strpos($question, 'upcoming class') !== false) {
        $current_day = date('l');
        $current_time = date('H:i:s');
        $query = "SELECT t.start_time, t.end_time, c.course_code, c.course_name, v.venue_code, v.venue_name
                  FROM Timetable t
                  JOIN Course c ON t.course_id = c.course_id
                  JOIN Venue v ON t.venue_id = v.venue_id
                  WHERE c.lecturer_id = $lecturer_id AND t.day = '$current_day' AND t.start_time > '$current_time'
                  ORDER BY t.start_time LIMIT 1";
        $res = mysqli_query($conn, $query);
        if (mysqli_num_rows($res) > 0) {
            $row = mysqli_fetch_assoc($res);
            $start = date("H:i", strtotime($row['start_time']));
            $end = date("H:i", strtotime($row['end_time']));
            return "Your next class is <strong>{$row['course_code']}</strong> ({$row['course_name']}) today from {$start} to {$end} at {$row['venue_code']}.";
        } else {
            $days_order = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
            $today_index = array_search($current_day, $days_order);
            for ($i = $today_index + 1; $i < count($days_order); $i++) {
                $next_day = $days_order[$i];
                $query_next = "SELECT t.start_time, t.end_time, c.course_code, c.course_name, v.venue_code, v.venue_name
                               FROM Timetable t
                               JOIN Course c ON t.course_id = c.course_id
                               JOIN Venue v ON t.venue_id = v.venue_id
                               WHERE c.lecturer_id = $lecturer_id AND t.day = '$next_day'
                               ORDER BY t.start_time LIMIT 1";
                $res_next = mysqli_query($conn, $query_next);
                if (mysqli_num_rows($res_next) > 0) {
                    $row = mysqli_fetch_assoc($res_next);
                    $start = date("H:i", strtotime($row['start_time']));
                    $end = date("H:i", strtotime($row['end_time']));
                    return "⏰ Your next class is on <strong>$next_day</strong>: {$row['course_code']} ({$row['course_name']}) from {$start} to {$end} at {$row['venue_code']}.";
                }
            }
            return "You have no upcoming classes in the next few days.";
        }
    }
    
    if (strpos($question, 'students') !== false || strpos($question, 'enrolled') !== false) {
        $query = "SELECT c.course_code, c.course_name, COUNT(r.registration_id) as student_count
                  FROM Course c
                  LEFT JOIN Registration r ON c.course_id = r.course_id
                  WHERE c.lecturer_id = $lecturer_id
                  GROUP BY c.course_id";
        $res = mysqli_query($conn, $query);
        if (mysqli_num_rows($res) == 0) {
            return "You have no courses with enrolled students.";
        }
        $output = "👩‍🎓 Enrolled students in your courses:\n";
        while ($row = mysqli_fetch_assoc($res)) {
            $output .= "• {$row['course_code']} - {$row['course_name']}: {$row['student_count']} student(s)\n";
        }
        return nl2br($output);
    }
    
    return "I can help you with:\n• 'My courses'\n• 'Timetable for Monday' (or any day)\n• 'Timetable for today/tomorrow'\n• 'Next class'\n• 'How many students in my courses?'";
}

$courses = [];
$courseQuery = "SELECT c.course_id, c.course_code, c.course_name, y.year_name 
                FROM Course c
                LEFT JOIN YearLevel y ON c.year_id = y.year_id
                WHERE c.lecturer_id = $lecturer_id
                ORDER BY c.course_code";
$courseResult = mysqli_query($conn, $courseQuery);
while ($row = mysqli_fetch_assoc($courseResult)) {
    $courses[] = $row;
}

$selected_course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : ($courses[0]['course_id'] ?? 0);
$students = [];
if ($selected_course_id) {
    $studentQuery = "SELECT r.registration_id, u.full_name, u.user_number, r.grade
                     FROM Registration r
                     JOIN Student s ON r.student_id = s.student_id
                     JOIN User u ON s.user_id = u.user_id
                     WHERE r.course_id = $selected_course_id";
    $studentResult = mysqli_query($conn, $studentQuery);
    while ($row = mysqli_fetch_assoc($studentResult)) {
        $students[] = $row;
    }
}

$timetable = [];
if ($lecturer_id) {
    $ttQuery = "SELECT t.day, t.start_time, t.end_time, c.course_code, c.course_name, v.venue_code, v.venue_name
                FROM Timetable t
                JOIN Course c ON t.course_id = c.course_id
                JOIN Venue v ON t.venue_id = v.venue_id
                WHERE c.lecturer_id = $lecturer_id
                ORDER BY FIELD(t.day, 'Monday','Tuesday','Wednesday','Thursday','Friday'), t.start_time";
    $ttResult = mysqli_query($conn, $ttQuery);
    while ($row = mysqli_fetch_assoc($ttResult)) {
        $timetable[] = $row;
    }
}

$selected_material_course_id = isset($_GET['material_course_id']) ? (int)$_GET['material_course_id'] : ($courses[0]['course_id'] ?? 0);
$materials = [];
if ($selected_material_course_id) {
    $materialQuery = "SELECT * FROM CourseMaterial WHERE course_id = $selected_material_course_id ORDER BY upload_date DESC";
    $materialResult = mysqli_query($conn, $materialQuery);
    while ($row = mysqli_fetch_assoc($materialResult)) {
        $materials[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Lecturer Dashboard | Course Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', sans-serif; 
            background: #eef2f8; 
            color: #1a2c3e; 
        }
        .dashboard-wrapper { 
            display: flex; 
            min-height: 100vh; 
        }
        .sidebar { 
            width: 280px; 
            background: linear-gradient(180deg, #0a1e2c 0%, #0f2f42 100%); 
            color: #e0edf5; 
            flex-shrink: 0; 
            box-shadow: 4px 0 20px rgba(0,0,0,0.08); 
        }
        .sidebar-header { 
            padding: 1.8rem 1.5rem; 
            border-bottom: 1px solid rgba(255,255,255,0.1); 
        }
        .sidebar-header h2 { 
            font-size: 1.5rem; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
        }
        .sidebar-header p { 
            font-size: 0.7rem; 
            opacity: 0.7; 
            margin-top: 6px; 
        }
        .nav-menu { 
            padding: 1.5rem 0; 
        }
        .nav-item { 
            display: flex; 
            align-items: center; 
            gap: 14px; 
            padding: 0.8rem 1.8rem; 
            margin: 4px 12px; 
            border-radius: 40px; 
            cursor: pointer; 
            transition: 0.2s; 
            font-weight: 500; 
        }
        .nav-item i { 
            width: 24px; 
        }
        .nav-item.active { 
            background: rgba(30,111,159,0.5); 
            color: white; 
            box-shadow: 0 2px 6px rgba(0,0,0,0.2); 
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
            align-items: center; background: white; 
            padding: 0.8rem 1.8rem; 
            border-radius: 60px; 
            margin-bottom: 2rem; 
            box-shadow: 0 2px 6px rgba(0,0,0,0.03); 
        }
        .lecturer-badge { 
            background: #1e6f9f10; 
            padding: 6px 14px; 
            border-radius: 30px; 
            font-weight: 500; 
            color: #1e6f9f; 
        }
        .logout-btn { 
            cursor: pointer; 
            background: #f1f5f9; 
            padding: 8px 20px; 
            border-radius: 40px; 
            font-weight: 600; 
        }
        .data-table { 
            background: white; 
            border-radius: 28px; 
            padding: 1.2rem; 
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
        .btn-action { 
            padding: 5px 12px; 
            border-radius: 30px; 
            border: none; 
            font-weight: 500; 
            cursor: pointer; 
        }
        .btn-update { 
            background: #eef2ff; 
            color: #1e40af; 
        }
        .btn-save { 
            background: #10b981; 
            color: white; 
        }
        .btn-danger { 
            background: #fee2e2; 
            color: #b91c1c; 
        }
        .grade-input { 
            width: 70px; 
            padding: 6px; 
            border-radius: 20px; 
            border: 1px solid #ccc; 
            text-align: center; 
        }
        .course-selector { 
            margin-bottom: 1.5rem; 
            display: flex; 
            align-items: center; 
            gap: 1rem; 
            flex-wrap: wrap; 
        }
        .course-selector select, .course-selector input, .course-selector button { 
            padding: 8px 16px; 
            border-radius: 40px; 
            border: 1px solid #ccc; 
            font-family: inherit; 
        }
        .alert-msg { 
            padding: 12px; 
            border-radius: 20px; 
            margin-bottom: 1rem; 
            display: block; }
        .alert-success { 
            background: #d1fae5; 
            color: #065f46; 
        }
        .alert-error { 
            background: #fee2e2; 
            color: #991b1b; 
        }
        .admin-panel { 
            display: none; 
            animation: fadeIn 0.25s ease; 
        }
        .admin-panel.active-panel { 
            display: block; 
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(6px);} to { opacity: 1; transform: translateY(0);} 
        }
        .profile-form input, .profile-form select { 
            padding: 8px 12px; 
            border-radius: 30px; 
            border: 1px solid #ccc; 
            font-family: inherit; 
            width: 100%; 
            max-width: 300px; 
            margin-top: 4px; 
        }
        .profile-form label { 
            font-weight: 600; 
            margin-top: 12px; 
            display: inline-block; 
        }
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
        .chat-toggle:hover { 
            transform: scale(1.05); 
            background: #0f5a85; 
        }
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
        .chat-window.open { 
            display: flex; 
        }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } 
    }
        .chat-header { 
            background: #1e6f9f; 
            color: white; 
            padding: 15px; 
            font-weight: 600; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        .chat-header button { 
            background: none; 
            border: none; 
            color: white; 
            font-size: 20px; 
            cursor: pointer; 
        }
        .chat-messages { 
            flex: 1; 
            overflow-y: auto; 
            padding: 1rem; 
            background: #f9fafb; 
        }
        .message { 
            margin-bottom: 1rem; 
            display: flex; 
            align-items: flex-start; 
            gap: 8px; 
        }
        .user-message { 
            justify-content: flex-end; 
        }
        .user-message .bubble { 
            background: #1e6f9f; 
            color: white; 
            border-radius: 20px 20px 4px 20px; 
        }
        .bot-message .bubble { 
            background: #e5e7eb; 
            color: #1f2937; 
            border-radius: 20px 20px 20px 4px; 
        }
        .bubble { 
            max-width: 80%; 
            padding: 8px 14px; 
            font-size: 0.85rem; 
            line-height: 1.4; 
        }
        .chat-input-area { 
            display: flex; 
            padding: 12px; 
            border-top: 1px solid #e5e7eb; 
            background: white; 
        }
        .chat-input-area input { 
            flex: 1; 
            padding: 10px 14px; 
            border-radius: 40px; 
            border: 1px solid #d1d5db; 
            font-family: inherit; 
            font-size: 0.9rem; 
        }
        .chat-input-area button { 
            margin-left: 8px; 
            background: #1e6f9f; 
            color: white; 
            border: none; 
            border-radius: 40px; 
            padding: 0 18px; 
            cursor: pointer; 
            font-weight: 600; 
        }
        .typing-indicator { 
            font-style: italic; 
            color: #6b7280; 
            padding: 6px 12px; 
        }
        .material-item { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            padding: 10px 0; 
            border-bottom: 1px solid #edf2f7; 
        }
        .material-info a { 
            color: #1e6f9f; 
            text-decoration: none; 
            font-weight: 500; 
        }
        .material-info a:hover { 
            text-decoration: underline; 
        }
        .modal { 
            display: none; 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.5); 
            align-items: center; 
            justify-content: center; 
            z-index: 1000; 
        }
        .modal-content { 
            background: white; 
            max-width: 700px; 
            width: 90%; 
            border-radius: 28px; 
            padding: 1.5rem; 
            max-height: 80vh; 
            overflow-y: auto; 
        }
        .modal-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            border-bottom: 1px solid #e9eef3; 
            padding-bottom: 0.75rem; 
            margin-bottom: 1rem; 
        }
        .modal-header button { 
            background: none; 
            border: none; 
            font-size: 1.5rem; 
            cursor: pointer; 
        }
        @media (max-width: 640px) { .chat-window { width: 90vw; right: 5vw; bottom: 80px; } .chat-toggle { width: 50px; height: 50px; font-size: 24px; } }
        @media (max-width: 800px) { .dashboard-wrapper { flex-direction: column; } .sidebar { width: 100%; } .nav-menu { display: flex; flex-wrap: wrap; } }
    </style>
</head>
<body>
<div class="dashboard-wrapper">
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-chalkboard-user"></i> EduPortal</h2>
            <p>Lecturer Access</p>
        </div>
        <div class="nav-menu">
            <div class="nav-item active" data-panel="coursesPanel"><i class="fas fa-book-open"></i> My Courses</div>
            <div class="nav-item" data-panel="studentsPanel"><i class="fas fa-users"></i> Enrolled Students</div>
            <div class="nav-item" data-panel="timetablePanel"><i class="fas fa-calendar-week"></i> My Timetable</div>
            <div class="nav-item" data-panel="materialsPanel"><i class="fas fa-folder-open"></i> Course Materials</div>
            <div class="nav-item" data-panel="profilePanel"><i class="fas fa-user-edit"></i> My Profile</div>
        </div>
    </aside>
    <main class="main-content">
        <div class="top-bar">
            <div>
                <i class="fas fa-user-graduate"></i> 
                <strong><?php echo htmlspecialchars($lecturer['title'] . ' ' . $lecturer['full_name']); ?></strong>
                <span class="lecturer-badge"><i class="fas fa-id-card"></i> Staff ID: <?php echo htmlspecialchars($lecturer['user_number']); ?></span>
            </div>
            <div class="logout-btn" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</div>
        </div>

        <?php if ($profile_success): ?>
            <div class="alert-msg alert-success"><?php echo $profile_success; ?></div>
        <?php endif; ?>
        <?php if (isset($profile_error)): ?>
            <div class="alert-msg alert-error"><?php echo $profile_error; ?></div>
        <?php endif; ?>
        <?php if ($upload_success): ?>
            <div class="alert-msg alert-success"><?php echo $upload_success; ?></div>
        <?php endif; ?>
        <?php if ($upload_error): ?>
            <div class="alert-msg alert-error"><?php echo $upload_error; ?></div>
        <?php endif; ?>

        <div id="coursesPanel" class="admin-panel active-panel">
            <h3><i class="fas fa-chalkboard"></i> Courses I Teach</h3>
            <div class="data-table">
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr><th>Course Code</th><th>Course Name</th><th>Year Level</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php if (count($courses) === 0): ?>
                                <tr><td colspan="4">No courses assigned yet. Contact administrator.</td></tr>
                            <?php else: ?>
                                <?php foreach ($courses as $course): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                    <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                    <td><?php echo htmlspecialchars($course['year_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <button class="btn-action btn-update view-students-btn" 
                                                data-course-id="<?php echo $course['course_id']; ?>"
                                                data-course-name="<?php echo htmlspecialchars($course['course_name']); ?>">
                                            View Students
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="studentsPanel" class="admin-panel">
            <h3><i class="fas fa-users"></i> Enrolled Students</h3>
            <div class="course-selector">
                <label for="courseSelect">Select Course:</label>
                <select id="courseSelect">
                    <?php foreach ($courses as $course): ?>
                        <option value="<?php echo $course['course_id']; ?>" <?php echo ($selected_course_id == $course['course_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button id="refreshStudentsBtn" class="btn-action btn-update"><i class="fas fa-sync-alt"></i> Refresh</button>
            </div>
            <div class="data-table">
                <table id="studentsTable">
                    <thead><tr><th>Student Name</th><th>Student Number</th><th>Grade</th><th>Action</th></tr></thead>
                    <tbody id="studentsTableBody">
                        <?php foreach ($students as $student): ?>
                        <tr data-registration-id="<?php echo $student['registration_id']; ?>">
                            <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($student['user_number']); ?></td>
                            <td><input type="text" class="grade-input" value="<?php echo htmlspecialchars($student['grade'] ?? ''); ?>" placeholder="e.g., A, B+"></td>
                            <td><button class="btn-action btn-update update-grade">Update</button></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($students)): ?>
                        <tr><td colspan="4">No students enrolled in this course.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="timetablePanel" class="admin-panel">
            <h3><i class="fas fa-calendar-week"></i> My Teaching Timetable</h3>
            <div class="data-table">
                <?php if (empty($timetable)): ?>
                    <p>No timetable entries for your courses.</p>
                <?php else: ?>
                    <table>
                        <thead><tr><th>Day</th><th>Time</th><th>Course</th><th>Venue</th></tr></thead>
                        <tbody>
                            <?php foreach ($timetable as $tt): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($tt['day']); ?></td>
                                <td><?php echo date("H:i", strtotime($tt['start_time'])) . " – " . date("H:i", strtotime($tt['end_time'])); ?></td>
                                <td><?php echo htmlspecialchars($tt['course_code'] . " - " . $tt['course_name']); ?></td>
                                <td><?php echo htmlspecialchars($tt['venue_code'] . " - " . $tt['venue_name']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div id="materialsPanel" class="admin-panel">
            <h3><i class="fas fa-folder-open"></i> Course Materials</h3>
            <div class="data-table" style="margin-bottom: 1rem;">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="course-selector" style="margin-bottom: 0;">
                        <label>Select Course:</label>
                        <select name="course_id" required>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['course_id']; ?>" <?php echo ($selected_material_course_id == $course['course_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label>Title:</label>
                        <input type="text" name="title" placeholder="e.g., Lecture 1 Slides" required style="min-width: 200px;">
                        <label>File:</label>
                        <input type="file" name="material_file" required>
                        <button type="submit" name="upload_material" class="btn-action btn-save"><i class="fas fa-upload"></i> Upload</button>
                    </div>
                </form>
                <small>Allowed: PDF, PPT, DOC, DOCX, TXT, JPG, PNG, MP4, ZIP (max 50MB)</small>
            </div>
            <div class="course-selector">
                <label>View materials for:</label>
                <select id="materialCourseSelect">
                    <?php foreach ($courses as $course): ?>
                        <option value="<?php echo $course['course_id']; ?>" <?php echo ($selected_material_course_id == $course['course_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button id="refreshMaterialsBtn" class="btn-action btn-update"><i class="fas fa-sync-alt"></i> Refresh</button>
            </div>
            <div class="data-table">
                <div id="materialsList">
                    <?php if (empty($materials)): ?>
                        <p>No materials uploaded for this course yet.</p>
                    <?php else: ?>
                        <?php foreach ($materials as $material): ?>
                            <div class="material-item" data-material-id="<?php echo $material['material_id']; ?>">
                                <div class="material-info">
                                    <strong><?php echo htmlspecialchars($material['title']); ?></strong><br>
                                    <a href="<?php echo $uploadDir . $material['file_name']; ?>" target="_blank"><?php echo htmlspecialchars($material['original_name']); ?></a>
                                    <small>(<?php echo round($material['file_size'] / 1024, 2); ?> KB, <?php echo $material['upload_date']; ?>)</small>
                                </div>
                                <div><button class="btn-action btn-danger delete-material" data-id="<?php echo $material['material_id']; ?>">Delete</button></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div id="profilePanel" class="admin-panel">
            <h3><i class="fas fa-user-edit"></i> Edit My Profile</h3>
            <div class="data-table">
                <form method="POST" action="" class="profile-form">
                    <input type="hidden" name="action" value="update_profile">
                    <label>Full Name:</label><br>
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($lecturer['full_name']); ?>" required><br>
                    <label>Email:</label><br>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($lecturer['email']); ?>" required><br>
                    <label>Title:</label><br>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($lecturer['title']); ?>" required><br>
                    <label>Staff ID (read‑only):</label><br>
                    <input type="text" value="<?php echo htmlspecialchars($lecturer['user_number']); ?>" disabled><br><br>
                    <button type="submit" class="btn-action btn-save"><i class="fas fa-save"></i> Save Changes</button>
                </form>
            </div>
        </div>
    </main>
</div>

<div class="chat-widget">
    <div class="chat-toggle" id="chatToggle"><i class="fas fa-robot"></i></div>
    <div class="chat-window" id="chatWindow">
        <div class="chat-header"><span><i class="fas fa-robot"></i> Lecturer Assistant</span><button id="chatCloseBtn">&times;</button></div>
        <div class="chat-messages" id="chatMessages">
            <div class="message bot-message"><div class="bubble">Hi! I can help you with:<br>• "My courses"<br>• "Timetable for Monday"<br>• "Timetable for today/tomorrow"<br>• "Next class"<br>• "How many students in my courses?"</div></div>
        </div>
        <div class="chat-input-area">
            <input type="text" id="chatInput" placeholder="Ask me..." autocomplete="off">
            <button id="sendChatBtn">Send</button>
        </div>
    </div>
</div>

<div id="studentsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalCourseTitle">Enrolled Students</h3>
            <button id="closeStudentsModalBtn">&times;</button>
        </div>
        <div class="data-table" style="margin-top: 0;">
            <div style="overflow-x: auto;">
                <table style="width: 100%;">
                    <thead>
                        <tr><th>Student Name</th><th>Student Number</th><th>Grade</th></tr>
                    </thead>
                    <tbody id="modalStudentsBody">
                        <tr><td colspan="3">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    const panels = ['coursesPanel', 'studentsPanel', 'timetablePanel', 'materialsPanel', 'profilePanel'];
    function switchPanel(panelId) {
        panels.forEach(pid => document.getElementById(pid).classList.remove('active-panel'));
        document.getElementById(panelId).classList.add('active-panel');
        document.querySelectorAll('.nav-item').forEach(item => {
            const target = item.getAttribute('data-panel');
            if (target === panelId) item.classList.add('active');
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
    function removeTypingIndicator() { const typing = document.getElementById('typingIndicator'); if (typing) typing.remove(); }

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
            formData.append('action', 'chat');
            formData.append('question', question);
            const response = await fetch('', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
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

    const courseSelect = document.getElementById('courseSelect');
    const refreshBtn = document.getElementById('refreshStudentsBtn');
    const studentsTableBody = document.getElementById('studentsTableBody');

    function loadStudents(courseId) {
        fetch(`get_course_students.php?course_id=${courseId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    studentsTableBody.innerHTML = '';
                    if (data.students.length === 0) {
                        studentsTableBody.innerHTML = '<tr><td colspan="4">No students enrolled in this course.</td></tr>';
                    } else {
                        data.students.forEach(student => {
                            const row = document.createElement('tr');
                            row.setAttribute('data-registration-id', student.registration_id);
                            row.innerHTML = `
                                <td>${escapeHtml(student.full_name)}</td>
                                <td>${escapeHtml(student.user_number)}</td>
                                <td><input type="text" class="grade-input" value="${escapeHtml(student.grade || '')}" placeholder="e.g., A, B+"></td>
                                <td><button class="btn-action btn-update update-grade">Update</button></td>
                            `;
                            studentsTableBody.appendChild(row);
                        });
                        attachGradeUpdateEvents();
                    }
                } else {
                    alert('Error loading students: ' + data.error);
                }
            })
            .catch(error => console.error('Error:', error));
    }

    function attachGradeUpdateEvents() {
        document.querySelectorAll('.update-grade').forEach(btn => {
            btn.removeEventListener('click', handleGradeUpdate);
            btn.addEventListener('click', handleGradeUpdate);
        });
    }

    function handleGradeUpdate(event) {
        const button = event.currentTarget;
        const row = button.closest('tr');
        const registrationId = row.getAttribute('data-registration-id');
        const gradeInput = row.querySelector('.grade-input');
        const grade = gradeInput.value.trim();

        fetch('lecturer_dashboard.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=update_grade&registration_id=${registrationId}&grade=${encodeURIComponent(grade)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) showTemporaryMessage('Grade updated successfully!', 'success');
            else alert('Error updating grade: ' + data.error);
        })
        .catch(error => alert('Network error.'));
    }

    function showTemporaryMessage(msg, type) {
        const msgDiv = document.createElement('div');
        msgDiv.className = `alert-msg alert-${type}`;
        msgDiv.innerHTML = `<i class="fas fa-check-circle"></i> ${msg}`;
        const container = document.querySelector('.main-content');
        container.insertBefore(msgDiv, container.firstChild);
        setTimeout(() => msgDiv.remove(), 3000);
    }

    if (refreshBtn) refreshBtn.addEventListener('click', () => { const cid = courseSelect.value; if (cid) loadStudents(cid); });
    if (courseSelect) courseSelect.addEventListener('change', () => { const cid = courseSelect.value; if (cid) loadStudents(cid); });
    if (courseSelect && courseSelect.value) loadStudents(courseSelect.value);
    else if (studentsTableBody && studentsTableBody.children.length === 0) studentsTableBody.innerHTML = '<tr><td colspan="4">No courses assigned. Contact admin.</td></tr>';
    attachGradeUpdateEvents();

    const materialCourseSelect = document.getElementById('materialCourseSelect');
    const refreshMaterialsBtn = document.getElementById('refreshMaterialsBtn');
    const materialsList = document.getElementById('materialsList');

    function loadMaterials(courseId) {
        fetch(`get_course_materials.php?course_id=${courseId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    materialsList.innerHTML = '';
                    if (data.materials.length === 0) materialsList.innerHTML = '<p>No materials uploaded for this course yet.</p>';
                    else {
                        data.materials.forEach(mat => {
                            const div = document.createElement('div');
                            div.className = 'material-item';
                            div.setAttribute('data-material-id', mat.material_id);
                            div.innerHTML = `
                                <div class="material-info">
                                    <strong>${escapeHtml(mat.title)}</strong><br>
                                    <a href="uploads/course_materials/${escapeHtml(mat.file_name)}" target="_blank">${escapeHtml(mat.original_name)}</a>
                                    <small>(${Math.round(mat.file_size / 1024)} KB, ${mat.upload_date})</small>
                                </div>
                                <div><button class="btn-action btn-danger delete-material" data-id="${mat.material_id}">Delete</button></div>
                            `;
                            materialsList.appendChild(div);
                        });
                        attachDeleteEvents();
                    }
                } else alert('Error loading materials: ' + data.error);
            })
            .catch(error => console.error('Error:', error));
    }

    function attachDeleteEvents() {
        document.querySelectorAll('.delete-material').forEach(btn => {
            btn.removeEventListener('click', handleDeleteMaterial);
            btn.addEventListener('click', handleDeleteMaterial);
        });
    }

    async function handleDeleteMaterial(event) {
        const button = event.currentTarget;
        const materialId = button.getAttribute('data-id');
        if (!confirm('Are you sure you want to delete this material? This action cannot be undone.')) return;
        try {
            const formData = new URLSearchParams();
            formData.append('action', 'delete_material');
            formData.append('material_id', materialId);
            const response = await fetch('', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
            const data = await response.json();
            if (data.success) {
                const courseId = materialCourseSelect.value;
                loadMaterials(courseId);
                showTemporaryMessage('Material deleted successfully', 'success');
            } else alert('Error: ' + data.error);
        } catch (error) { alert('Network error.'); }
    }

    if (refreshMaterialsBtn) refreshMaterialsBtn.addEventListener('click', () => { const cid = materialCourseSelect.value; if (cid) loadMaterials(cid); });
    if (materialCourseSelect) materialCourseSelect.addEventListener('change', () => { const cid = materialCourseSelect.value; if (cid) loadMaterials(cid); });
    if (materialCourseSelect && materialCourseSelect.value) loadMaterials(materialCourseSelect.value);
    attachDeleteEvents();

    const studentsModal = document.getElementById('studentsModal');
    const closeStudentsModalBtn = document.getElementById('closeStudentsModalBtn');
    const modalCourseTitle = document.getElementById('modalCourseTitle');
    const modalStudentsBody = document.getElementById('modalStudentsBody');

    function openStudentsModal(courseId, courseName) {
        modalCourseTitle.innerText = `Enrolled Students – ${courseName}`;
        modalStudentsBody.innerHTML = '<tr><td colspan="3">Loading...</td></tr>';
        studentsModal.style.display = 'flex';

        fetch(`get_course_students.php?course_id=${courseId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.students.length > 0) {
                    modalStudentsBody.innerHTML = '';
                    data.students.forEach(s => {
                        const row = `
                            <tr>
                                <td>${escapeHtml(s.full_name)}</td>
                                <td>${escapeHtml(s.user_number)}</td>
                                <td>${escapeHtml(s.grade || 'Not graded')}</td>
                            </tr>
                        `;
                        modalStudentsBody.insertAdjacentHTML('beforeend', row);
                    });
                } else {
                    modalStudentsBody.innerHTML = '<tr><td colspan="3">No students enrolled in this course.</td></tr>';
                }
            })
            .catch(error => {
                console.error('Error fetching students:', error);
                modalStudentsBody.innerHTML = '<tr><td colspan="3">Error loading data.</td></tr>';
            });
    }

    document.body.addEventListener('click', (e) => {
        const btn = e.target.closest('.view-students-btn');
        if (!btn) return;
        const courseId = btn.dataset.courseId;
        const courseName = btn.dataset.courseName;
        openStudentsModal(courseId, courseName);
    });

    closeStudentsModalBtn.addEventListener('click', () => {
        studentsModal.style.display = 'none';
    });
    window.addEventListener('click', (e) => {
        if (e.target === studentsModal) studentsModal.style.display = 'none';
    });

    document.getElementById('logoutBtn')?.addEventListener('click', () => { window.location.href = 'login.php'; });
    function handleGradeUpdate(event) {
    const button = event.currentTarget;
    const row = button.closest('tr');
    const registrationId = row.getAttribute('data-registration-id');
    const gradeInput = row.querySelector('.grade-input');
    const grade = gradeInput.value.trim();

    fetch('lecturer_dashboard.php', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: `action=update_grade&registration_id=${registrationId}&grade=${encodeURIComponent(grade)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showTemporaryMessage('Grade updated successfully!', 'success');
        } else {
            alert('Error updating grade: ' + data.error);
        }
    })
    .catch(error => alert('Network error.'));
}
</script>
</body>
</html>