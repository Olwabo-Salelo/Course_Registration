<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'student') {
    exit(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

$conn = mysqli_connect("localhost", "root", "", "student_course");
if (!$conn) {
    exit(json_encode(['success' => false, 'error' => 'Database connection failed']));
}

$course_id = (int)$_GET['course_id'];
$user_id = $_SESSION['user_id'];

$query = "SELECT student_id FROM Student WHERE user_id = $user_id";
$res = mysqli_query($conn, $query);
if ($res && mysqli_num_rows($res) > 0) {
    $student_id = mysqli_fetch_assoc($res)['student_id'];
} else {
    exit(json_encode(['success' => false, 'error' => 'Student record not found']));
}

$checkEnrolled = mysqli_query($conn, "SELECT registration_id FROM Registration WHERE student_id = $student_id AND course_id = $course_id");
if (mysqli_num_rows($checkEnrolled) == 0) {
    exit(json_encode(['success' => false, 'error' => 'You are not enrolled in this course.']));
}

$materials = [];
$query = "SELECT * FROM CourseMaterial WHERE course_id = $course_id ORDER BY upload_date DESC";
$res = mysqli_query($conn, $query);
while ($row = mysqli_fetch_assoc($res)) {
    $materials[] = $row;
}
echo json_encode(['success' => true, 'materials' => $materials]);
?>