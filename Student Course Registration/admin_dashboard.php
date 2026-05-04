<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$conn = mysqli_connect("localhost", "root", "", "student_course");
if (!$conn) die("Database connection failed: " . mysqli_connect_error());

$admin_id = null;
$user_id = $_SESSION['user_id'];
$admin_query = mysqli_query($conn, "SELECT admin_id FROM admin WHERE user_id = $user_id");
if ($admin_query && mysqli_num_rows($admin_query) > 0) {
    $admin_id = mysqli_fetch_assoc($admin_query)['admin_id'];
} else {
    mysqli_query($conn, "INSERT INTO admin (user_id) VALUES ($user_id)");
    $admin_id = mysqli_insert_id($conn);
    if (!$admin_id) die("Admin record could not be created.");
}
//This function helps send an otp email to the Lecturer currently logged to verify the action
function sendLecturerEmail($toEmail, $fullName, $staffId, $plainPassword) {
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
        $mail->Subject = 'Your Lecturer Account Credentials';
        $mail->Body = "<html><body><h2>Welcome, $fullName!</h2><p>Staff ID: $staffId<br>Password: $plainPassword</p><p><a href='change_password.php'>Change password</a></p></body></html>";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $new_full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $new_email = mysqli_real_escape_string($conn, $_POST['email']);
    $new_password = $_POST['password'];

    $update_sql = "UPDATE User SET full_name='$new_full_name', email='$new_email'";
    if (!empty($new_password)) {
        $update_sql .= ", password='$new_password'";
    }
    $update_sql .= " WHERE user_id=$user_id";
    if (mysqli_query($conn, $update_sql)) {
        $_SESSION['full_name'] = $new_full_name;
        $_SESSION['email'] = $new_email;
        $msg = "Profile updated successfully.";
        $type = "success";
    } else {
        $msg = "Error updating profile: " . mysqli_error($conn);
        $type = "error";
    }
    header("Location: admin_dashboard.php?msg=" . urlencode($msg) . "&type=$type");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    if (($action === 'approve_request' || $action === 'reject_request') && isset($_POST['request_id'])) {
        $request_id = (int)$_POST['request_id'];

        if ($action === 'approve_request') {
            $reqQuery = "SELECT student_id, course_id FROM RegistrationRequest WHERE request_id = $request_id AND status = 'pending'";
            $reqRes = mysqli_query($conn, $reqQuery);
            if (mysqli_num_rows($reqRes) > 0) {
                $req = mysqli_fetch_assoc($reqRes);
                $student_id = $req['student_id'];
                $course_id = $req['course_id'];

                $capRes = mysqli_query($conn, "SELECT capacity FROM Course WHERE course_id = $course_id");
                $capacity = mysqli_fetch_assoc($capRes)['capacity'];

                if ($capacity <= 0) {
                    $msg = "Cannot approve – course capacity is full (0 seats left).";
                    $type = "error";
                } else {
                    mysqli_begin_transaction($conn);
                    try {
                        mysqli_query($conn, "UPDATE Course SET capacity = capacity - 1 WHERE course_id = $course_id");
                        mysqli_query($conn, "UPDATE RegistrationRequest SET status='approved', reviewed_at=NOW() WHERE request_id=$request_id");
                        mysqli_query($conn, "INSERT INTO Registration (student_id, course_id) VALUES ($student_id, $course_id)");
                        mysqli_commit($conn);
                        $msg = "Request approved and student enrolled. Capacity updated.";
                        $type = "success";
                    } catch (Exception $e) {
                        mysqli_rollback($conn);
                        $msg = "Error: " . $e->getMessage();
                        $type = "error";
                    }
                }
            } else {
                $msg = "Request not found or already processed.";
                $type = "error";
            }
        } elseif ($action === 'reject_request') {
            $update = mysqli_query($conn, "UPDATE RegistrationRequest SET status='rejected', reviewed_at=NOW() WHERE request_id=$request_id");
            if ($update) {
                $msg = "Request rejected.";
                $type = "success";
            } else {
                $msg = "Error rejecting request: " . mysqli_error($conn);
                $type = "error";
            }
        }
        header("Location: admin_dashboard.php?msg=" . urlencode($msg) . "&type=$type");
        exit;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && isset($_POST['withdrawal_request_id'])) {
    $action = $_POST['action'];
    $withdrawal_request_id = (int)$_POST['withdrawal_request_id'];

    if ($action === 'approve_withdrawal') {
        $reqQuery = "SELECT wr.registration_id, wr.student_id, wr.course_id, r.registration_id AS reg_id 
                     FROM WithdrawalRequest wr
                     JOIN Registration r ON wr.registration_id = r.registration_id
                     WHERE wr.withdrawal_request_id = $withdrawal_request_id AND wr.status = 'pending'";
        $reqRes = mysqli_query($conn, $reqQuery);
        if (mysqli_num_rows($reqRes) > 0) {
            $req = mysqli_fetch_assoc($reqRes);
            $registration_id = $req['registration_id'];
            $student_id = $req['student_id'];
            $course_id = $req['course_id'];
            
            mysqli_begin_transaction($conn);
            try {
                mysqli_query($conn, "UPDATE WithdrawalRequest SET status='approved', reviewed_at=NOW() WHERE withdrawal_request_id=$withdrawal_request_id");
                mysqli_query($conn, "INSERT INTO Withdrawal (registration_id, student_id, course_id) VALUES ($registration_id, $student_id, $course_id)");
                mysqli_query($conn, "UPDATE Course SET capacity = capacity + 1 WHERE course_id = $course_id");
                mysqli_query($conn, "DELETE FROM Registration WHERE registration_id = $registration_id");
                mysqli_commit($conn);
                $msg = "Withdrawal approved. Student withdrawn. Capacity updated.";
                $type = "success";
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $msg = "Error: " . $e->getMessage();
                $type = "error";
            }
        } else {
            $msg = "Withdrawal request not found or already processed.";
            $type = "error";
        }
        header("Location: admin_dashboard.php?msg=" . urlencode($msg) . "&type=$type");
        exit;
    }

    if ($action === 'reject_withdrawal') {
        $update = mysqli_query($conn, "UPDATE WithdrawalRequest SET status='rejected', reviewed_at=NOW() WHERE withdrawal_request_id=$withdrawal_request_id");
        if ($update) {
            $msg = "Withdrawal request rejected. Student remains enrolled.";
            $type = "success";
        } else {
            $msg = "Error rejecting withdrawal request: " . mysqli_error($conn);
            $type = "error";
        }
        header("Location: admin_dashboard.php?msg=" . urlencode($msg) . "&type=$type");
        exit;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'add_lecturer') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $user_number = trim($_POST['user_number']);
    $title = trim($_POST['title']);
    $password = $_POST['password'];
    $role = 'lecturer';

    $checkEmail = mysqli_query($conn, "SELECT user_id FROM User WHERE email = '$email'");
    if (mysqli_num_rows($checkEmail) > 0) {
        $error = "Email address already exists. Please use a different email.";
    } else {

        $checkStaff = mysqli_query($conn, "SELECT user_id FROM User WHERE user_number = '$user_number'");
        if (mysqli_num_rows($checkStaff) > 0) {
            $error = "Staff ID already exists. Please use a unique staff ID.";
        } else {
        
            $sql = "INSERT INTO User (full_name, email, password, role, user_number) 
                    VALUES ('$full_name', '$email', '$password', '$role', '$user_number')";
            if (mysqli_query($conn, $sql)) {
                $user_id = mysqli_insert_id($conn);
                $sql2 = "INSERT INTO Lecturer (title, user_id, admin_id) VALUES ('$title', $user_id, $admin_id)";
                if (mysqli_query($conn, $sql2)) {
                    $mailSent = sendLecturerEmail($email, $full_name, $user_number, $password);
                    $success = $mailSent ? "Lecturer created and email sent!" : "Lecturer created but email failed.";
                } else {
                
                    mysqli_query($conn, "DELETE FROM User WHERE user_id = $user_id");
                    $error = "Failed to create lecturer record: " . mysqli_error($conn);
                }
            } else {
                $error = "Failed to create user: " . mysqli_error($conn);
            }
        }
    }
    header("Location: admin_dashboard.php?msg=" . urlencode($success ?? $error) . "&type=" . (isset($success) ? 'success' : 'error'));
    exit;
}

if (isset($_GET['delete_lecturer'])) {
    $user_id = (int)$_GET['delete_lecturer'];
    mysqli_query($conn, "DELETE FROM Lecturer WHERE user_id=$user_id");
    if (mysqli_affected_rows($conn) > 0) {
        mysqli_query($conn, "DELETE FROM User WHERE user_id=$user_id");
        $msg = "Lecturer deleted.";
    } else {
        $msg = "Lecturer not found.";
    }
    header("Location: admin_dashboard.php?msg=" . urlencode($msg) . "&type=success");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'edit_lecturer') {
    $user_id = (int)$_POST['user_id'];
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $user_number = mysqli_real_escape_string($conn, $_POST['user_number']);
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);

    $updateUser = "UPDATE User SET full_name='$full_name', email='$email', user_number='$user_number'";
    if (!empty($password)) $updateUser .= ", password='$password'";
    $updateUser .= " WHERE user_id=$user_id";
    mysqli_query($conn, $updateUser);
    mysqli_query($conn, "UPDATE Lecturer SET title='$title' WHERE user_id=$user_id");
    header("Location: admin_dashboard.php?msg=Lecturer updated&type=success");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'add_course') {
    $course_code = mysqli_real_escape_string($conn, $_POST['course_code']);
    $course_name = mysqli_real_escape_string($conn, $_POST['course_name']);
    $credits = (int)$_POST['credits'];
    $capacity = (int)$_POST['capacity'];
    $lecturer_id = (int)$_POST['lecturer_id'];
    $year_id = (int)$_POST['year_id'];

    $sql = "INSERT INTO Course (course_code, course_name, credits, capacity, lecturer_id, year_id, admin_id) 
            VALUES ('$course_code', '$course_name', $credits, $capacity, $lecturer_id, $year_id, $admin_id)";
    $result = mysqli_query($conn, $sql);
    $msg = $result ? "Course added." : "Error: " . mysqli_error($conn);
    $type = $result ? "success" : "error";
    header("Location: admin_dashboard.php?msg=" . urlencode($msg) . "&type=$type");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'edit_course') {
    $course_id = (int)$_POST['course_id'];
    $course_code = mysqli_real_escape_string($conn, $_POST['course_code']);
    $course_name = mysqli_real_escape_string($conn, $_POST['course_name']);
    $credits = (int)$_POST['credits'];
    $capacity = (int)$_POST['capacity'];
    $lecturer_id = (int)$_POST['lecturer_id'];
    $year_id = (int)$_POST['year_id'];

    $sql = "UPDATE Course SET course_code='$course_code', course_name='$course_name', credits=$credits, capacity=$capacity,
            lecturer_id=$lecturer_id, year_id=$year_id WHERE course_id=$course_id";
    $result = mysqli_query($conn, $sql);
    $msg = $result ? "Course updated." : "Error: " . mysqli_error($conn);
    $type = $result ? "success" : "error";
    header("Location: admin_dashboard.php?msg=" . urlencode($msg) . "&type=$type");
    exit;
}

if (isset($_GET['delete_course'])) {
    $course_id = (int)$_GET['delete_course'];
    $sql = "DELETE FROM Course WHERE course_id=$course_id";
    $msg = mysqli_query($conn, $sql) ? "Course deleted." : "Error: " . mysqli_error($conn);
    $type = mysqli_query($conn, $sql) ? "success" : "error";
    header("Location: admin_dashboard.php?msg=" . urlencode($msg) . "&type=$type");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'add_timetable') {
    $course_id = (int)$_POST['course_id'];
    $venue_id = (int)$_POST['venue_id'];
    $day = mysqli_real_escape_string($conn, $_POST['day']);
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];

    $conflict_sql = "SELECT timetable_id FROM Timetable WHERE day='$day' AND (start_time<'$end_time' AND end_time>'$start_time')";
    $conflict = mysqli_query($conn, $conflict_sql);
    if (mysqli_num_rows($conflict) > 0) {
        header("Location: admin_dashboard.php?msg=Conflict: time overlaps on $day&type=error");
        exit;
    }
    $sql = "INSERT INTO Timetable (course_id, venue_id, day, start_time, end_time) VALUES ($course_id, $venue_id, '$day', '$start_time', '$end_time')";
    $result = mysqli_query($conn, $sql);
    $msg = $result ? "Entry added." : "Error: " . mysqli_error($conn);
    $type = $result ? "success" : "error";
    header("Location: admin_dashboard.php?msg=" . urlencode($msg) . "&type=$type");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'edit_timetable') {
    $timetable_id = (int)$_POST['timetable_id'];
    $course_id = (int)$_POST['course_id'];
    $venue_id = (int)$_POST['venue_id'];
    $day = mysqli_real_escape_string($conn, $_POST['day']);
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];

    $conflict_sql = "SELECT timetable_id FROM Timetable WHERE day='$day' AND timetable_id!=$timetable_id AND (start_time<'$end_time' AND end_time>'$start_time')";
    $conflict = mysqli_query($conn, $conflict_sql);
    if (mysqli_num_rows($conflict) > 0) {
        header("Location: admin_dashboard.php?msg=Conflict: time overlaps on $day&type=error");
        exit;
    }
    $sql = "UPDATE Timetable SET course_id=$course_id, venue_id=$venue_id, day='$day', start_time='$start_time', end_time='$end_time' WHERE timetable_id=$timetable_id";
    $msg = mysqli_query($conn, $sql) ? "Entry updated." : "Error: " . mysqli_error($conn);
    $type = mysqli_query($conn, $sql) ? "success" : "error";
    header("Location: admin_dashboard.php?msg=" . urlencode($msg) . "&type=$type");
    exit;
}

if (isset($_GET['delete_timetable'])) {
    $timetable_id = (int)$_GET['delete_timetable'];
    $sql = "DELETE FROM Timetable WHERE timetable_id=$timetable_id";
    $msg = mysqli_query($conn, $sql) ? "Entry deleted." : "Error: " . mysqli_error($conn);
    $type = mysqli_query($conn, $sql) ? "success" : "error";
    header("Location: admin_dashboard.php?msg=" . urlencode($msg) . "&type=$type");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'add_student') {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $student_number = mysqli_real_escape_string($conn, $_POST['student_number']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $role = 'student';

    $check = mysqli_query($conn, "SELECT user_id FROM User WHERE email='$email' OR user_number='$student_number'");
    if (mysqli_num_rows($check) > 0) {
        $error = "Email or Student Number already exists.";
    } else {
        $sql = "INSERT INTO User (full_name, email, password, role, user_number) VALUES ('$full_name', '$email', '$password', '$role', '$student_number')";
        if (mysqli_query($conn, $sql)) {
            $user_id = mysqli_insert_id($conn);
            $sql2 = "INSERT INTO Student (user_id) VALUES ($user_id)";
            if (mysqli_query($conn, $sql2)) $success = "Student created.";
            else $error = "Failed to create student record.";
        } else $error = "Failed to create user.";
    }
    header("Location: admin_dashboard.php?msg=" . urlencode($success ?? $error) . "&type=" . (isset($success) ? 'success' : 'error'));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'edit_student') {
    $user_id = (int)$_POST['user_id'];
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $student_number = mysqli_real_escape_string($conn, $_POST['student_number']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);

    $update = "UPDATE User SET full_name='$full_name', email='$email', user_number='$student_number'";
    if (!empty($password)) $update .= ", password='$password'";
    $update .= " WHERE user_id=$user_id AND role='student'";
    $msg = mysqli_query($conn, $update) ? "Student updated." : "Error: " . mysqli_error($conn);
    $type = mysqli_query($conn, $update) ? "success" : "error";
    header("Location: admin_dashboard.php?msg=" . urlencode($msg) . "&type=$type");
    exit;
}


if (isset($_GET['delete_student'])) {
    $user_id = (int)$_GET['delete_student'];
    
    $coursesRes = mysqli_query($conn, "SELECT c.course_id 
                                       FROM Registration r 
                                       JOIN Student s ON r.student_id = s.student_id 
                                       WHERE s.user_id = $user_id");
    while ($row = mysqli_fetch_assoc($coursesRes)) {
        mysqli_query($conn, "UPDATE Course SET capacity = capacity + 1 WHERE course_id = {$row['course_id']}");
    }
    
    $sql = "DELETE FROM User WHERE user_id=$user_id AND role='student'";
    $msg = (mysqli_query($conn, $sql) && mysqli_affected_rows($conn) > 0) ? "Student deleted. Capacities updated." : "Student not found.";
    $type = (mysqli_affected_rows($conn) > 0) ? "success" : "error";
    header("Location: admin_dashboard.php?msg=" . urlencode($msg) . "&type=$type");
    exit;
}

if (isset($_GET['fetch_enrolled_students']) && isset($_GET['course_id'])) {
    $course_id = (int)$_GET['course_id'];
    $students = [];
    $query = "SELECT u.full_name, u.user_number, u.email, r.grade
              FROM Registration r
              JOIN Student s ON r.student_id = s.student_id
              JOIN User u ON s.user_id = u.user_id
              WHERE r.course_id = $course_id";
    $res = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($res)) {
        $students[] = $row;
    }
    echo json_encode(['success' => true, 'students' => $students]);
    exit;
}

$totalStudents = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) FROM User WHERE role='student'"))['COUNT(*)'];
$totalLecturers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) FROM User WHERE role='lecturer'"))['COUNT(*)'];
$totalCourses = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) FROM Course"))['COUNT(*)'];
$totalEnrollments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) FROM Registration"))['COUNT(*)'] ?? 0;

$lecturers = [];
$res = mysqli_query($conn, "SELECT u.user_id, u.full_name, u.email, u.user_number, l.title, l.lecturer_id,
       COALESCE(GROUP_CONCAT(DISTINCT CONCAT(c.course_code, ' - ', c.course_name) ORDER BY c.course_code SEPARATOR ', '), 'No courses assigned') as courses,
       COALESCE(GROUP_CONCAT(DISTINCT y.year_name ORDER BY y.year_id SEPARATOR ', '), 'N/A') as years
       FROM User u 
       JOIN Lecturer l ON u.user_id = l.user_id 
       LEFT JOIN Course c ON c.lecturer_id = l.lecturer_id
       LEFT JOIN YearLevel y ON c.year_id = y.year_id
       WHERE u.role = 'lecturer'
       GROUP BY u.user_id
       ORDER BY u.user_id");
while($row = mysqli_fetch_assoc($res)) {
    $lecturers[] = $row;
}

$yearLevels = [];
$res = mysqli_query($conn, "SELECT year_id, year_name FROM YearLevel ORDER BY year_id");
while($row = mysqli_fetch_assoc($res)) $yearLevels[] = $row;

$courses = [];
$courses = [];
$res = mysqli_query($conn, "SELECT c.course_id, c.course_code, c.course_name, c.credits, c.capacity, l.lecturer_id, u.full_name as lecturer_name, y.year_id, y.year_name FROM Course c LEFT JOIN Lecturer l ON c.lecturer_id=l.lecturer_id LEFT JOIN User u ON l.user_id=u.user_id LEFT JOIN YearLevel y ON c.year_id=y.year_id ORDER BY c.course_code");
while($row = mysqli_fetch_assoc($res)) $courses[] = $row;

$students = [];
$res = mysqli_query($conn, "SELECT u.user_id, u.full_name, u.email, u.user_number, s.student_id FROM User u JOIN Student s ON u.user_id=s.user_id WHERE u.role='student'");
while($row = mysqli_fetch_assoc($res)) $students[] = $row;

$allCourses = [];
$res = mysqli_query($conn, "SELECT course_id, course_code, course_name FROM Course ORDER BY course_code");
while($row = mysqli_fetch_assoc($res)) $allCourses[] = $row;

$venues = [];
$res = mysqli_query($conn, "SELECT venue_id, venue_code, venue_name FROM Venue ORDER BY venue_code");
while($row = mysqli_fetch_assoc($res)) $venues[] = $row;

$timetableEntries = [];
$res = mysqli_query($conn, "SELECT t.timetable_id, t.day, t.start_time, t.end_time, c.course_id, c.course_code, c.course_name, v.venue_id, v.venue_code, v.venue_name FROM Timetable t JOIN Course c ON t.course_id=c.course_id JOIN Venue v ON t.venue_id=v.venue_id ORDER BY FIELD(t.day,'Monday','Tuesday','Wednesday','Thursday','Friday'), t.start_time");
while($row = mysqli_fetch_assoc($res)) $timetableEntries[] = $row;

$pendingRequests = [];
$res = mysqli_query($conn, "SELECT rr.request_id, u.full_name as student_name, c.course_code, c.course_name, rr.requested_at FROM RegistrationRequest rr JOIN Student s ON rr.student_id=s.student_id JOIN User u ON s.user_id=u.user_id JOIN Course c ON rr.course_id=c.course_id WHERE rr.status='pending' ORDER BY rr.requested_at");
while($row = mysqli_fetch_assoc($res)) $pendingRequests[] = $row;

$pendingWithdrawals = [];
$res = mysqli_query($conn, "SELECT wr.withdrawal_request_id, u.full_name as student_name, c.course_code, c.course_name, wr.requested_at 
                           FROM WithdrawalRequest wr
                           JOIN Student s ON wr.student_id = s.student_id
                           JOIN User u ON s.user_id = u.user_id
                           JOIN Course c ON wr.course_id = c.course_id
                           WHERE wr.status = 'pending'
                           ORDER BY wr.requested_at");
while($row = mysqli_fetch_assoc($res)) $pendingWithdrawals[] = $row;

$withdrawalHistory = [];
$res = mysqli_query($conn, "SELECT w.withdrawal_id, u.full_name as student_name, c.course_code, c.course_name, w.withdrawn_at 
                           FROM Withdrawal w
                           JOIN Student s ON w.student_id = s.student_id
                           JOIN User u ON s.user_id = u.user_id
                           JOIN Course c ON w.course_id = c.course_id
                           ORDER BY w.withdrawn_at DESC
                           LIMIT 50");
while($row = mysqli_fetch_assoc($res)) $withdrawalHistory[] = $row;

$msg = $_GET['msg'] ?? '';
$msg_type = $_GET['type'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Course Registration System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', sans-serif; 
            background: #eef2f8; 
            color: #1a2c3e; }
        .admin-wrapper { 
            display: flex; 
            min-height: 100vh; }
        .admin-sidebar {
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
        .admin-nav { 
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
        .nav-parent { 
            cursor: pointer; 
        }
        .nav-parent .arrow { 
            margin-left: auto; 
            transition: transform 0.2s; 
        }
        .nav-parent.open .arrow { 
            transform: rotate(180deg); 
        }
        .submenu { 
            list-style: none; 
            margin-left: 1rem; 
            display: none; 
        }
        .nav-parent.open .submenu { 
            display: block; 
        }
        .submenu .nav-item { 
            padding-left: 2.5rem; 
        }
        .admin-main { 
            flex: 1; 
            padding: 1.8rem 2rem; 
            overflow-x: auto; 
        }
        .top-bar {
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            background: white;
            padding: 0.8rem 1.8rem; 
            border-radius: 60px; 
            margin-bottom: 2rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.03);
        }
        .admin-badge { 
            background: #1e6f9f10; 
            padding: 6px 14px; 
            border-radius: 30px; 
            font-weight: 500; color: #1e6f9f; 
        }
        .logout-btn { 
            cursor: pointer; 
            background: #f1f5f9; 
            padding: 8px 20px; 
            border-radius: 40px; 
            font-weight: 600; 
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
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); 
            gap: 1.5rem; margin-bottom: 2rem; 
        }
        .stat-card { 
            background: white; 
            border-radius: 28px; 
            padding: 1.3rem; 
            box-shadow: 0 5px 12px rgba(0,0,0,0.05); 
            border: 1px solid #e9eef3; 
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
            margin: 0 4px; 
            display: inline-block; 
            white-space: nowrap; 
        }
        .btn-edit { 
            background: #eef2ff; 
            color: #1e40af; 
        }
        .btn-delete { 
            background: #fee2e2; 
            color: #b91c1c; 
        }
        .btn-approve { 
            background: #d4edda; 
            color: #155724; 
        }
        .btn-reject { 
            background: #f8d7da; 
            color: #721c24; 
        }
        .btn-add { 
            background: #1e6f9f; 
            color: white; 
            padding: 8px 18px; 
            border-radius: 40px; 
            border: none; 
            cursor: pointer; 
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
            max-width: 500px; 
            width: 90%; 
            border-radius: 36px; 
            padding: 2rem; }
        .form-group { 
            margin-bottom: 1rem; 
        }
        .form-group input, .form-group select { 
            width: 100%; 
            padding: 12px; 
            border-radius: 28px; 
            border: 1px solid #ccc; 
        }
        .alert-msg { 
            padding: 12px; 
            border-radius: 20px; 
            margin-bottom: 1rem; 
            display: block; 
        }
        .alert-success { 
            background: #d1fae5; 
            color: #065f46; 
        }
        .alert-error { 
            background: #fee2e2; 
            color: #b91c1c; }
        .sub-section { 
            margin-top: 2rem; 
            border-top: 2px solid #e9eef3; 
            padding-top: 1.5rem; 
        }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <aside class="admin-sidebar">
        <div class="sidebar-header"><h2><i class="fas fa-shield-alt"></i> AdminHub</h2><p>Course Registration System</p></div>
        
        <div class="admin-nav">
            <div class="nav-item" data-panel="overviewPanel"><i class="fas fa-chart-line"></i> Overview</div>
            <div class="nav-item" data-panel="lecturersPanel"><i class="fas fa-chalkboard-user"></i> Manage Lecturers</div>
            <div class="nav-parent" id="studentParent">
                <div class="nav-item"><i class="fas fa-user-graduate"></i> Student <i class="fas fa-chevron-down arrow"></i></div>
                <ul class="submenu">
                    <li><div class="nav-item" data-panel="studentsListPanel"><i class="fas fa-list"></i> List Students</div></li>
                    <li><div class="nav-item" data-panel="requestsPanel"><i class="fas fa-clipboard-list"></i> Registration Requests</div></li>
                    <li><div class="nav-item" data-panel="withdrawalPanel"><i class="fas fa-download"></i> Withdrawal Requests</div></li>
                </ul>
            </div>
            <div class="nav-item" data-panel="coursesPanel"><i class="fas fa-book"></i> Manage Courses</div>
            <div class="nav-item" data-panel="timetablePanel"><i class="fas fa-calendar-week"></i> Manage Timetable</div>
            <div class="nav-item" data-panel="profilePanel"><i class="fas fa-user-edit"></i> My Profile</div>
            <div class="nav-item" data-panel="reportsPanel"><i class="fas fa-chart-pie"></i> Reports</div>
        </div>
    </aside>
    <main class="admin-main">
        <div class="top-bar">
            <div><i class="fas fa-user-cog"></i> <strong>Admin:</strong> <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?> <span class="admin-badge">Full Access</span></div>
            <div style="display: flex; gap: 10px;"><a href="change_password.php" style="background:#f1f5f9; padding:8px 20px; border-radius:40px; text-decoration:none; color:#1e6f9f;">Change Password</a><div class="logout-btn" id="logoutAdmin">Logout</div></div>
        </div>
        <?php if ($msg): ?><div class="alert-msg alert-<?php echo $msg_type === 'success' ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

        
        <div id="overviewPanel" class="admin-panel active-panel">
            <div class="stats-grid">
                <div class="stat-card"><i class="fas fa-user-graduate fa-2x"></i><h2><?php echo $totalStudents; ?></h2><p>Students</p></div>
                <div class="stat-card"><i class="fas fa-chalkboard-user fa-2x"></i><h2><?php echo $totalLecturers; ?></h2><p>Lecturers</p></div>
                <div class="stat-card"><i class="fas fa-book-open fa-2x"></i><h2><?php echo $totalCourses; ?></h2><p>Courses</p></div>
                <div class="stat-card"><i class="fas fa-check-circle fa-2x"></i><h2><?php echo $totalEnrollments; ?></h2><p>Enrollments</p></div>
            </div>
            <div class="data-table"><h4><i class="fas fa-bullhorn"></i> System Status</h4><p>Real‑time data integrity, conflict detection active.</p></div>
        </div>

        
        <div id="lecturersPanel" class="admin-panel">
    <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
        <h3><i class="fas fa-chalkboard-user"></i> Lecturers</h3>
        <button class="btn-add" id="addLecturerBtn">+ Add Lecturer</button>
    </div>
    <div class="data-table">
        <div style="overflow-x: auto;">
            <?php if (empty($lecturers)): ?>
                <p>No lecturers found.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Course(s)</th>
                            <th>Year(s)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lecturers as $lec): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($lec['title']); ?></td>
                            <td><?php echo htmlspecialchars($lec['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($lec['email']); ?></td>
                            <td><?php echo htmlspecialchars($lec['courses']); ?></td>
                            <td><?php echo htmlspecialchars($lec['years']); ?></td>
                            <td>
                                <button class="btn-action btn-edit" onclick="editLecturer(<?php echo $lec['user_id']; ?>, '<?php echo addslashes($lec['full_name']); ?>', '<?php echo addslashes($lec['email']); ?>', '<?php echo addslashes($lec['user_number']); ?>', '<?php echo addslashes($lec['title']); ?>')">Edit</button>
                                <a href="?delete_lecturer=<?php echo $lec['user_id']; ?>" class="btn-action btn-delete" onclick="return confirm('Delete this lecturer?')">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

        
        <div id="studentsListPanel" class="admin-panel">
            <div style="display: flex; justify-content: space-between;"><h3><i class="fas fa-user-graduate"></i> Students List</h3><button class="btn-add" id="addStudentBtn">+ Add New Student</button></div>
            <div class="data-table">
                <div style="overflow-x: auto;">
                    <table>
                        <thead><tr><th>ID</th><th>Full Name</th><th>Email</th><th>Student Number</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($students as $stu): ?>
                            <tr>
                                <td><?php echo $stu['user_id']; ?></td>
                                <td><?php echo htmlspecialchars($stu['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($stu['email']); ?></td>
                                <td><?php echo htmlspecialchars($stu['user_number']); ?></td>
                                <td>
                                    <button class="btn-action btn-edit edit-student" data-id="<?php echo $stu['user_id']; ?>" data-name="<?php echo htmlspecialchars($stu['full_name']); ?>" data-email="<?php echo htmlspecialchars($stu['email']); ?>" data-number="<?php echo htmlspecialchars($stu['user_number']); ?>">Edit</button>
                                    <a href="?delete_student=<?php echo $stu['user_id']; ?>" class="btn-action btn-delete" onclick="return confirm('Delete this student?')">Delete</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($students)): ?>
                                <tr><td colspan="5">No students found.<?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        
        <div id="requestsPanel" class="admin-panel">
            <h3><i class="fas fa-clipboard-list"></i> Pending Registration Requests</h3>
            <div class="data-table">
                <div style="overflow-x: auto;">
                    <?php if (empty($pendingRequests)): ?>
                        <p>No pending requests.</p>
                    <?php else: ?>
                        <table>
                            <thead><tr><th>Student</th><th>Course</th><th>Requested On</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php foreach ($pendingRequests as $req): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($req['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($req['course_code'] . " - " . $req['course_name']); ?></td>
                                        <td><?php echo $req['requested_at']; ?></td>
                                        <td>
                                            <button class="btn-action btn-approve" data-id="<?php echo $req['request_id']; ?>">Approve</button>
                                            <button class="btn-action btn-reject" data-id="<?php echo $req['request_id']; ?>">Reject</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        
        <div id="withdrawalPanel" class="admin-panel">
            <h3><i class="fas fa-download"></i> Pending Withdrawal Requests</h3>
            <div class="data-table">
                <div style="overflow-x: auto;">
                    <?php if (empty($pendingWithdrawals)): ?>
                        <p>No pending withdrawal requests.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr><th>Student</th><th>Course</th><th>Requested On</th><th>Action</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingWithdrawals as $wr): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($wr['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($wr['course_code'] . " - " . $wr['course_name']); ?></td>
                                        <td><?php echo $wr['requested_at']; ?></td>
                                        <td>
                                            <button class="btn-action btn-approve approve-withdrawal" data-id="<?php echo $wr['withdrawal_request_id']; ?>">Approve Withdrawal</button>
                                            <button class="btn-action btn-reject reject-withdrawal" data-id="<?php echo $wr['withdrawal_request_id']; ?>">Reject</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            <div class="sub-section">
                <h4><i class="fas fa-history"></i> Withdrawal History (Last 50)</h4>
                <?php if (empty($withdrawalHistory)): ?>
                    <p>No withdrawals recorded yet.</p>
                <?php else: ?>
                    <div class="data-table" style="margin-top: 0.5rem;">
                        <div style="overflow-x: auto;">
                            <table>
                                <thead><tr><th>Student</th><th>Course</th><th>Withdrawn On</th></tr></thead>
                                <tbody>
                                    <?php foreach ($withdrawalHistory as $wh): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($wh['student_name']); ?></td>
                                            <td><?php echo htmlspecialchars($wh['course_code'] . " - " . $wh['course_name']); ?></td>
                                            <td><?php echo $wh['withdrawn_at']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    
        <div id="coursesPanel" class="admin-panel">
            <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;"><h3><i class="fas fa-chalkboard"></i> Course Management</h3><button class="btn-add" id="addCourseBtn">+ Create Course</button></div>
            <div class="data-table">
                <div style="overflow-x: auto;">
                    <table>
                        <thead><tr><th>Code</th><th>Name</th><th>Credits</th><th>Capacity</th><th>Lecturer</th><th>Year</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach($courses as $c): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($c['course_code']); ?></td>
                                <td><?php echo htmlspecialchars($c['course_name']); ?></td>
                                <td><?php echo $c['credits']; ?></td>
                                <td><?php echo $c['capacity']; ?></td>
                                <td><?php echo htmlspecialchars($c['lecturer_name'] ?? 'Unassigned'); ?></td>
                                <td><?php echo htmlspecialchars($c['year_name'] ?? 'None'); ?></td>
                                <td>
                                    <button class="btn-action btn-edit" onclick="editCourse(<?php echo $c['course_id']; ?>, '<?php echo addslashes($c['course_code']); ?>', '<?php echo addslashes($c['course_name']); ?>', <?php echo $c['credits']; ?>, <?php echo $c['capacity']; ?>, <?php echo $c['lecturer_id'] ?? 0; ?>, <?php echo $c['year_id'] ?? 0; ?>)">Edit</button>
                                    <a href="?delete_course=<?php echo $c['course_id']; ?>" class="btn-action btn-delete" onclick="return confirm('Delete this course?')">Delete</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div style="margin-top: 2rem; border-top: 2px solid #e9eef3; padding-top: 1.5rem;">
    <h4><i class="fas fa-users"></i> View Enrolled Students by Course</h4>
    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap;">
        <label for="courseEnrollSelect">Select Course:</label>
        <select id="courseEnrollSelect" style="padding: 8px 16px; border-radius: 40px; border: 1px solid #ccc;">
            <option value="">-- Select Course --</option>
            <?php foreach ($courses as $c): ?>
                <option value="<?php echo $c['course_id']; ?>"><?php echo htmlspecialchars($c['course_code'] . " - " . $c['course_name']); ?></option>
            <?php endforeach; ?>
        </select>
        <button id="fetchEnrolledBtn" class="btn-add" style="padding: 6px 16px;">Show Students</button>
        <span id="enrolledLoading" style="display: none;"><i class="fas fa-spinner fa-spin"></i> Loading...</span>
    </div>
    <div id="enrolledStudentsContainer" style="display: none;">
        <div class="data-table">
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr><th>Student Name</th><th>Student Number</th><th>Email</th><th>Grade</th></tr>
                    </thead>
                    <tbody id="enrolledStudentsBody">
                        <tr><td colspan="4">Select a course and click "Show Students".</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
        </div>

     <!--The fnction bellow is for editting the timetable-->   
       <div id="timetablePanel" class="admin-panel">
    <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
        <h3><i class="fas fa-calendar-week"></i> Timetable</h3>
        <button class="btn-add" id="addTimetableBtn">+ Add Entry</button>
    </div>
    <div class="data-table">
        <div style="overflow-x: auto;">
            <?php if (empty($timetableEntries)): ?>
                <p>No timetable entries.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Day</th>
                            <th>Time</th>
                            <th>Course</th>
                            <th>Venue</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($timetableEntries as $tt): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($tt['day']); ?></td>
                                <td><?php echo date("H:i", strtotime($tt['start_time'])) . " – " . date("H:i", strtotime($tt['end_time'])); ?></td>
                                <td><?php echo htmlspecialchars($tt['course_code'] . " - " . $tt['course_name']); ?></td>
                                <td><?php echo htmlspecialchars($tt['venue_code'] . " - " . $tt['venue_name']); ?></td>
                                <td>
                                    <button class="btn-action btn-edit" onclick="editTimetable(<?php echo $tt['timetable_id']; ?>, <?php echo $tt['course_id']; ?>, <?php echo $tt['venue_id']; ?>, '<?php echo $tt['day']; ?>', '<?php echo $tt['start_time']; ?>', '<?php echo $tt['end_time']; ?>')">Edit</button>
                                    <a href="?delete_timetable=<?php echo $tt['timetable_id']; ?>" class="btn-action btn-delete" onclick="return confirm('Delete entry?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

        
        <div id="profilePanel" class="admin-panel">
            <h3><i class="fas fa-user-edit"></i> My Profile</h3>
            <div class="profile-form" style="max-width: 500px; margin: 0 auto;">
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-group"><label>Full Name</label><input type="text" name="full_name" value="<?php echo htmlspecialchars($_SESSION['full_name']); ?>" required></div>
                    <div class="form-group"><label>Email</label><input type="email" name="email" value="<?php echo htmlspecialchars($_SESSION['email']); ?>" required></div>
                    <div class="form-group"><label>New Password (leave blank to keep current)</label><input type="password" name="password" placeholder="Enter new password to change"></div>
                    <button type="submit" class="btn-add">Update Profile</button>
                </form>
            </div>
        </div>

        
        <div id="reportsPanel" class="admin-panel"><h3><i class="fas fa-chart-simple"></i> Enrollment Statistics</h3><canvas id="enrollmentChart" width="300" height="200"></canvas><br><button id="exportReportBtn" class="btn-add">Export CSV</button></div>
    </main>
</div>


<div id="lecturerModal" class="modal"><div class="modal-content"><h3 id="modalTitle">Add Lecturer</h3><form id="lecturerForm" method="POST"><input type="hidden" name="action" id="formAction" value="add_lecturer"><input type="hidden" name="user_id" id="editUserId"><div class="form-group"><label>Full Name *</label><input type="text" name="full_name" id="full_name" required></div><div class="form-group"><label>Email *</label><input type="email" name="email" id="email" required></div><div class="form-group"><label>Staff ID *</label><input type="text" name="user_number" id="user_number" required></div><div class="form-group"><label>Title</label><input type="text" name="title" id="title" placeholder="Dr."></div><div class="form-group"><label>Password *</label><input type="password" name="password" id="password" required></div><button type="submit" class="btn-add">Save</button><button type="button" id="closeModalBtn" class="btn-delete">Cancel</button></form></div></div>

<div id="courseModal" class="modal"><div class="modal-content"><h3 id="courseModalTitle">Add Course</h3><form id="courseForm" method="POST"><input type="hidden" name="action" id="courseAction" value="add_course"><input type="hidden" name="course_id" id="courseId"><div class="form-group"><label>Course Code *</label><input type="text" name="course_code" id="course_code" required></div><div class="form-group"><label>Course Name *</label><input type="text" name="course_name" id="course_name" required></div><div class="form-group"><label>Credits *</label><input type="number" name="credits" id="credits" required min="1" max="30" value="10"></div><div class="form-group"><label>Capacity *</label><input type="number" name="capacity" id="capacity" required min="1" value="30"></div><div class="form-group"><label>Assign Lecturer</label><select name="lecturer_id" id="lecturer_id"><option value="">-- None --</option><?php foreach ($lecturers as $lec): ?><option value="<?php echo $lec['lecturer_id']; ?>"><?php echo htmlspecialchars($lec['full_name']); ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Year Level</label><select name="year_id" id="year_id"><option value="">-- Select --</option><?php foreach ($yearLevels as $yl): ?><option value="<?php echo $yl['year_id']; ?>"><?php echo htmlspecialchars($yl['year_name']); ?></option><?php endforeach; ?></select></div><button type="submit" class="btn-add">Save</button><button type="button" id="closeCourseModalBtn" class="btn-delete">Cancel</button></form></div></div>

<div id="timetableModal" class="modal"><div class="modal-content"><h3 id="timetableModalTitle">Add Timetable Entry</h3><form id="timetableForm" method="POST"><input type="hidden" name="action" id="timetableAction" value="add_timetable"><input type="hidden" name="timetable_id" id="timetableId"><div class="form-group"><label>Course *</label><select name="course_id" id="timetable_course_id" required><option value="">-- Select Course --</option><?php foreach ($allCourses as $c): ?><option value="<?php echo $c['course_id']; ?>"><?php echo htmlspecialchars($c['course_code'] . " - " . $c['course_name']); ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Venue *</label><select name="venue_id" id="timetable_venue_id" required><option value="">-- Select Venue --</option><?php foreach ($venues as $v): ?><option value="<?php echo $v['venue_id']; ?>"><?php echo htmlspecialchars($v['venue_code'] . " - " . $v['venue_name']); ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Day *</label><select name="day" id="timetable_day" required><option>Monday</option><option>Tuesday</option><option>Wednesday</option><option>Thursday</option><option>Friday</option></select></div><div class="form-group"><label>Start Time *</label><input type="time" name="start_time" id="timetable_start" required></div><div class="form-group"><label>End Time *</label><input type="time" name="end_time" id="timetable_end" required></div><button type="submit" class="btn-add">Save</button><button type="button" id="closeTimetableModalBtn" class="btn-delete">Cancel</button></form></div></div>

<div id="studentModal" class="modal"><div class="modal-content"><h3 id="studentModalTitle">Add Student</h3><form id="studentForm" method="POST"><input type="hidden" name="action" id="studentAction" value="add_student"><input type="hidden" name="user_id" id="studentUserId"><div class="form-group"><label>Full Name *</label><input type="text" name="full_name" id="studentFullName" required></div><div class="form-group"><label>Email *</label><input type="email" name="email" id="studentEmail" required></div><div class="form-group"><label>Student Number *</label><input type="text" name="student_number" id="studentNumber" required></div><div class="form-group"><label>Password</label><input type="password" name="password" id="studentPassword" placeholder="Leave blank to keep unchanged (for edit)"></div><button type="submit" class="btn-add">Save</button><button type="button" id="closeStudentModalBtn" class="btn-delete">Cancel</button></form></div></div>

<script>
    
    const lecturerModal = document.getElementById('lecturerModal');
    const courseModal = document.getElementById('courseModal');
    const timetableModal = document.getElementById('timetableModal');
    const studentModal = document.getElementById('studentModal');

    const modalTitle = document.getElementById('modalTitle');
    const lecturerForm = document.getElementById('lecturerForm');
    const formAction = document.getElementById('formAction');
    const editUserId = document.getElementById('editUserId');
    const fullName = document.getElementById('full_name');
    const email = document.getElementById('email');
    const userNumber = document.getElementById('user_number');
    const title = document.getElementById('title');
    const password = document.getElementById('password');
    document.getElementById('addLecturerBtn').addEventListener('click', () => {
        modalTitle.innerText = 'Add Lecturer';
        formAction.value = 'add_lecturer';
        editUserId.value = '';
        fullName.value = '';
        email.value = '';
        userNumber.value = '';
        title.value = '';
        password.value = '';
        password.required = true;
        lecturerModal.style.display = 'flex';
    });
    window.editLecturer = (id, name, mail, num, tit) => {
        modalTitle.innerText = 'Edit Lecturer';
        formAction.value = 'edit_lecturer';
        editUserId.value = id;
        fullName.value = name;
        email.value = mail;
        userNumber.value = num;
        title.value = tit;
        password.value = '';
        password.required = false;
        lecturerModal.style.display = 'flex';
    };
    document.getElementById('closeModalBtn').addEventListener('click', () => lecturerModal.style.display = 'none');
    window.onclick = (e) => { if (e.target === lecturerModal) lecturerModal.style.display = 'none'; };

    const courseModalTitle = document.getElementById('courseModalTitle');
    const courseForm = document.getElementById('courseForm');
    const courseAction = document.getElementById('courseAction');
    const courseId = document.getElementById('courseId');
    const courseCode = document.getElementById('course_code');
    const courseName = document.getElementById('course_name');
    const creditsField = document.getElementById('credits');
    const capacityField = document.getElementById('capacity');
    const lecturerSelect = document.getElementById('lecturer_id');
    const yearSelect = document.getElementById('year_id');
    document.getElementById('addCourseBtn').addEventListener('click', () => {
        courseModalTitle.innerText = 'Add Course';
        courseAction.value = 'add_course';
        courseId.value = '';
        courseCode.value = '';
        courseName.value = '';
        creditsField.value = '10';
        capacityField.value = '30';
        lecturerSelect.value = '';
        yearSelect.value = '';
        courseModal.style.display = 'flex';
    });
    window.editCourse = (id, code, name, credits, capacity, lecId, yrId) => {
        courseModalTitle.innerText = 'Edit Course';
        courseAction.value = 'edit_course';
        courseId.value = id;
        courseCode.value = code;
        courseName.value = name;
        creditsField.value = credits;
        capacityField.value = capacity;
        lecturerSelect.value = lecId;
        yearSelect.value = yrId;
        courseModal.style.display = 'flex';
    };
    document.getElementById('closeCourseModalBtn').addEventListener('click', () => courseModal.style.display = 'none');
    window.onclick = (e) => { if (e.target === courseModal) courseModal.style.display = 'none'; };

    const timetableModalTitle = document.getElementById('timetableModalTitle');
    const timetableForm = document.getElementById('timetableForm');
    const timetableAction = document.getElementById('timetableAction');
    const timetableId = document.getElementById('timetableId');
    const ttCourse = document.getElementById('timetable_course_id');
    const ttVenue = document.getElementById('timetable_venue_id');
    const ttDay = document.getElementById('timetable_day');
    const ttStart = document.getElementById('timetable_start');
    const ttEnd = document.getElementById('timetable_end');
    document.getElementById('addTimetableBtn').addEventListener('click', () => {
        timetableModalTitle.innerText = 'Add Timetable Entry';
        timetableAction.value = 'add_timetable';
        timetableId.value = '';
        ttCourse.value = '';
        ttVenue.value = '';
        ttDay.value = 'Monday';
        ttStart.value = '';
        ttEnd.value = '';
        timetableModal.style.display = 'flex';
    });
    window.editTimetable = (id, courseId, venueId, day, start, end) => {
        timetableModalTitle.innerText = 'Edit Timetable Entry';
        timetableAction.value = 'edit_timetable';
        timetableId.value = id;
        ttCourse.value = courseId;
        ttVenue.value = venueId;
        ttDay.value = day;
        ttStart.value = start;
        ttEnd.value = end;
        timetableModal.style.display = 'flex';
    };
    document.getElementById('closeTimetableModalBtn').addEventListener('click', () => timetableModal.style.display = 'none');
    window.onclick = (e) => { if (e.target === timetableModal) timetableModal.style.display = 'none'; };

    const studentModalTitle = document.getElementById('studentModalTitle');
    const studentForm = document.getElementById('studentForm');
    const studentAction = document.getElementById('studentAction');
    const studentUserId = document.getElementById('studentUserId');
    const studentFullName = document.getElementById('studentFullName');
    const studentEmail = document.getElementById('studentEmail');
    const studentNumber = document.getElementById('studentNumber');
    const studentPassword = document.getElementById('studentPassword');
    document.getElementById('addStudentBtn').addEventListener('click', () => {
        studentModalTitle.innerText = 'Add Student';
        studentAction.value = 'add_student';
        studentUserId.value = '';
        studentFullName.value = '';
        studentEmail.value = '';
        studentNumber.value = '';
        studentPassword.value = '';
        studentPassword.placeholder = 'Password *';
        studentPassword.required = true;
        studentModal.style.display = 'flex';
    });
    function attachStudentEditEvents() {
        document.querySelectorAll('.edit-student').forEach(btn => {
            btn.removeEventListener('click', studentEditHandler);
            btn.addEventListener('click', studentEditHandler);
        });
    }
    function studentEditHandler() {
        studentModalTitle.innerText = 'Edit Student';
        studentAction.value = 'edit_student';
        studentUserId.value = this.dataset.id;
        studentFullName.value = this.dataset.name;
        studentEmail.value = this.dataset.email;
        studentNumber.value = this.dataset.number;
        studentPassword.value = '';
        studentPassword.placeholder = 'Leave blank to keep current';
        studentPassword.required = false;
        studentModal.style.display = 'flex';
    }
    document.getElementById('closeStudentModalBtn').addEventListener('click', () => studentModal.style.display = 'none');
    window.onclick = (e) => { if (e.target === studentModal) studentModal.style.display = 'none'; };
    attachStudentEditEvents();

    document.querySelectorAll('.btn-approve[data-id]:not(.approve-withdrawal)').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!confirm('Approve this registration request? The student will be enrolled.')) return;
            const f = document.createElement('form'); f.method='POST'; f.action='';
            const i1 = document.createElement('input'); i1.type='hidden'; i1.name='action'; i1.value='approve_request';
            const i2 = document.createElement('input'); i2.type='hidden'; i2.name='request_id'; i2.value=this.dataset.id;
            f.appendChild(i1); f.appendChild(i2); document.body.appendChild(f); f.submit();
        });
    });
    document.querySelectorAll('.btn-reject[data-id]:not(.reject-withdrawal)').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!confirm('Reject this registration request?')) return;
            const f = document.createElement('form'); f.method='POST'; f.action='';
            const i1 = document.createElement('input'); i1.type='hidden'; i1.name='action'; i1.value='reject_request';
            const i2 = document.createElement('input'); i2.type='hidden'; i2.name='request_id'; i2.value=this.dataset.id;
            f.appendChild(i1); f.appendChild(i2); document.body.appendChild(f); f.submit();
        });
    });

    document.querySelectorAll('.approve-withdrawal').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!confirm('Approve this withdrawal request? The student will be withdrawn from the course.')) return;
            const f = document.createElement('form'); f.method='POST'; f.action='';
            const i1 = document.createElement('input'); i1.type='hidden'; i1.name='action'; i1.value='approve_withdrawal';
            const i2 = document.createElement('input'); i2.type='hidden'; i2.name='withdrawal_request_id'; i2.value=this.dataset.id;
            f.appendChild(i1); f.appendChild(i2); document.body.appendChild(f); f.submit();
        });
    });
    document.querySelectorAll('.reject-withdrawal').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!confirm('Reject this withdrawal request? The student will remain enrolled.')) return;
            const f = document.createElement('form'); f.method='POST'; f.action='';
            const i1 = document.createElement('input'); i1.type='hidden'; i1.name='action'; i1.value='reject_withdrawal';
            const i2 = document.createElement('input'); i2.type='hidden'; i2.name='withdrawal_request_id'; i2.value=this.dataset.id;
            f.appendChild(i1); f.appendChild(i2); document.body.appendChild(f); f.submit();
        });
    });

    const courseEnrollSelect = document.getElementById('courseEnrollSelect');
    const fetchEnrolledBtn = document.getElementById('fetchEnrolledBtn');
    const enrolledContainer = document.getElementById('enrolledStudentsContainer');
    const enrolledBody = document.getElementById('enrolledStudentsBody');
    const enrolledLoading = document.getElementById('enrolledLoading');

    async function fetchEnrolledStudents(courseId) {
        if (!courseId) {
            alert('Please select a course.');
            return;
        }
        enrolledLoading.style.display = 'inline-block';
        enrolledBody.innerHTML = '<tr><td colspan="4">Loading...</td></tr>';
        enrolledContainer.style.display = 'block';
        try {
            const response = await fetch(`?fetch_enrolled_students=1&course_id=${courseId}`);
            const data = await response.json();
            if (data.success && data.students.length > 0) {
                enrolledBody.innerHTML = '';
                data.students.forEach(s => {
                    const row = `
                        <tr>
                            <td>${escapeHtml(s.full_name)}</td>
                            <td>${escapeHtml(s.user_number)}</td>
                            <td>${escapeHtml(s.email)}</td>
                            <td>${escapeHtml(s.grade || '-')}</td>
                        </tr>
                    `;
                    enrolledBody.insertAdjacentHTML('beforeend', row);
                });
            } else {
                enrolledBody.innerHTML = '<tr><td colspan="4">No students enrolled in this course.</td></tr>';
            }
        } catch (error) {
            console.error('Fetch error:', error);
            enrolledBody.innerHTML = '<tr><td colspan="4">Error loading data. Please try again.</td></tr>';
        } finally {
            enrolledLoading.style.display = 'none';
        }
    }

    courseEnrollSelect.addEventListener('change', () => {
        const courseId = courseEnrollSelect.value;
        if (courseId) fetchEnrolledStudents(courseId);
        else {
            enrolledContainer.style.display = 'none';
            enrolledBody.innerHTML = '<tr><td colspan="4">Select a course and click "Show Students".</td></tr>';
        }
    });
    fetchEnrolledBtn.addEventListener('click', () => {
        const courseId = courseEnrollSelect.value;
        if (courseId) fetchEnrolledStudents(courseId);
        else alert('Please select a course.');
    });

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }
    const studentParent = document.getElementById('studentParent');
    const parentToggle = studentParent.querySelector('.nav-item');
    parentToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        studentParent.classList.toggle('open');
    });

    const panels = ['overviewPanel','lecturersPanel','studentsListPanel','requestsPanel','withdrawalPanel','coursesPanel','timetablePanel','profilePanel','reportsPanel'];
    function switchPanel(panelId) {
        panels.forEach(pid => document.getElementById(pid).classList.remove('active-panel'));
        document.getElementById(panelId).classList.add('active-panel');
        document.querySelectorAll('.nav-item[data-panel]').forEach(item => {
            if (item.getAttribute('data-panel') === panelId) item.classList.add('active');
            else item.classList.remove('active');
        });
        if (panelId === 'reportsPanel') initChart();
    }
    document.querySelectorAll('.nav-item[data-panel]').forEach(item => {
        item.addEventListener('click', (e) => {
            e.stopPropagation();
            const panelId = item.getAttribute('data-panel');
            if (panelId) switchPanel(panelId);
        });
    });

    let chart;
    function initChart() {
        const ctx = document.getElementById('enrollmentChart')?.getContext('2d');
        if (!ctx) return;
        if (chart) chart.destroy();
        chart = new Chart(ctx, { type: 'bar', data: { labels: ['Students', 'Lecturers'], datasets: [{ label: 'Count', data: [<?php echo $totalStudents; ?>, <?php echo $totalLecturers; ?>], backgroundColor: '#1e6f9f' }] } });
    }
    document.getElementById('exportReportBtn')?.addEventListener('click', () => alert('CSV export not implemented yet.'));
    document.getElementById('logoutAdmin').addEventListener('click', () => window.location.href = 'login.php');
    initChart();
</script>
</body>
</html>
