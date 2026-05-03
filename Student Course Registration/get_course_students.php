<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$conn = mysqli_connect("localhost", "root", "", "student_course");
if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$course_id = (int)$_GET['course_id'] ?? 0;
if (!$course_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid course ID']);
    exit;
}

$lecturer_id = $_SESSION['user_id'];
$check = mysqli_query($conn, "SELECT lecturer_id FROM Course WHERE course_id = $course_id");
$row = mysqli_fetch_assoc($check);
if (!$row || !isset($row['lecturer_id'])) {
    echo json_encode(['success' => false, 'error' => 'Course not found']);
    exit;
}

$students = [];
$query = "SELECT r.registration_id, u.full_name, u.user_number, r.grade
          FROM Registration r
          JOIN Student s ON r.student_id = s.student_id
          JOIN User u ON s.user_id = u.user_id
          WHERE r.course_id = $course_id";
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $students[] = $row;
    }
    echo json_encode(['success' => true, 'students' => $students]);
} else {
    echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
}
?>