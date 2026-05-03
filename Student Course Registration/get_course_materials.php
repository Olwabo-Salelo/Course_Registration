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

$materials = [];
$query = "SELECT material_id, title, file_name, original_name, file_size, upload_date
          FROM CourseMaterial
          WHERE course_id = $course_id
          ORDER BY upload_date DESC";
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $materials[] = $row;
    }
    echo json_encode(['success' => true, 'materials' => $materials]);
} else {
    echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
}
?>