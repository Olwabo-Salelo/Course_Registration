
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>SCRSystem | Course Registration Portal</title>
    <!-- Google Fonts + Font Awesome -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(145deg, #eef5fa 0%, #dce8f0 100%);
            color: #1a2c3e;
            line-height: 1.5;
        }

        /* header / navigation */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.2rem 2rem;
            background: rgba(255,255,255,0.96);
            backdrop-filter: blur(4px);
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .logo {
            font-size: 1.6rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #0f2b3b;
        }
        .logo i {
            color: #1e6f9f;
            font-size: 1.8rem;
        }
        .nav-buttons {
            display: flex;
            gap: 1rem;
        }
        .btn-outline {
            background: transparent;
            border: 1.5px solid #1e6f9f;
            padding: 0.5rem 1.2rem;
            border-radius: 40px;
            font-weight: 600;
            color: #1e6f9f;
            text-decoration: none;
            transition: 0.2s;
        }
        .btn-outline:hover {
            background: #1e6f9f10;
            transform: translateY(-2px);
        }
        .btn-primary {
            background: #1e6f9f;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 40px;
            font-weight: 600;
            color: white;
            text-decoration: none;
            transition: 0.2s;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        .btn-primary:hover {
            background: #0c587f;
            transform: translateY(-2px);
            box-shadow: 0 6px 14px rgba(0,0,0,0.1);
        }

        /* hero section */
        .hero {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            padding: 3rem 4rem;
            max-width: 1400px;
            margin: 0 auto;
            gap: 2rem;
        }
        .hero-text {
            flex: 1;
            min-width: 280px;
        }
        .hero-text h1 {
            font-size: 3.2rem;
            font-weight: 800;
            letter-spacing: -1px;
            background: linear-gradient(135deg, #0f2b3b, #1e6f9f);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 1.2rem;
        }
        .hero-text p {
            font-size: 1.1rem;
            color: #2c4e6e;
            margin-bottom: 2rem;
            max-width: 550px;
        }
        .hero-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .hero-image {
            flex: 1;
            text-align: center;
        }
        .hero-image i {
            font-size: 12rem;
            color: #1e6f9f;
            opacity: 0.8;
        }

        /* features */
        .features {
            background: white;
            padding: 4rem 2rem;
            border-radius: 48px 48px 0 0;
        }
        .section-title {
            text-align: center;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 2.5rem;
            color: #0f2b3b;
        }
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 2rem;
            max-width: 1300px;
            margin: 0 auto;
        }
        .card {
            background: #f9fbfe;
            border-radius: 32px;
            padding: 1.8rem;
            transition: 0.2s;
            border: 1px solid #e9eef3;
        }
        .card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 25px -12px rgba(0,0,0,0.1);
        }
        .card i {
            font-size: 2.5rem;
            color: #1e6f9f;
            margin-bottom: 1rem;
        }
        .card h3 {
            margin-bottom: 0.8rem;
            font-weight: 700;
        }
        .card p {
            color: #4a627a;
            line-height: 1.4;
        }

        /* footer */
        .footer {
            text-align: center;
            padding: 2rem;
            color: #5c7c94;
            font-size: 0.85rem;
            border-top: 1px solid #e2edf5;
            margin-top: 3rem;
        }

        /* responsive */
        @media (max-width: 800px) {
            .hero {
                flex-direction: column;
                text-align: center;
                padding: 2rem;
            }
            .hero-text h1 {
                font-size: 2.4rem;
            }
            .hero-buttons {
                justify-content: center;
            }
            .navbar {
                padding: 1rem;
            }
            .logo {
                font-size: 1.3rem;
            }
        }
        @media (max-width: 500px) {
            .section-title {
                font-size: 1.6rem;
            }
            .hero-text h1 {
                font-size: 1.9rem;
            }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="logo">
        <i class="fas fa-graduation-cap"></i> SCRSystem
    </div>
    <div class="nav-buttons">
        <a href="login.php" class="btn-outline"><i class="fas fa-sign-in-alt"></i> Login</a>
        <a href="signup.php" class="btn-primary"><i class="fas fa-user-plus"></i> Sign Up</a>
    </div>
</nav>

<section class="hero">
    <div class="hero-text">
        <h1>Manage your academic journey<br>seamlessly</h1>
        <p>Student Course Registration System – request enrollments, view timetables, access learning materials, and track your progress. Designed for students, lecturers, and administrators.</p>
        <div class="hero-buttons">
            <a href="login.php" class="btn-primary"><i class="fas fa-arrow-right"></i> Get Started</a>
            <a href="#" style="color:#1e6f9f; font-weight:500;">Learn more →</a>
        </div>
    </div>
    <div class="hero-image">
        <i class="fas fa-laptop-code"></i>
    </div>
</section>

<section class="features">
    <div class="section-title">
        <i class="fas fa-star-of-life" style="color:#1e6f9f;"></i> Powerful Features
    </div>
    <div class="cards-grid">
        <div class="card">
            <i class="fas fa-user-graduate"></i>
            <h3>For Students</h3>
            <p>Browse available courses, request registration, view personalised timetable, access course materials, and track credit load.</p>
        </div>
        <div class="card">
            <i class="fas fa-chalkboard-user"></i>
            <h3>For Lecturers</h3>
            <p>Upload course materials, manage grades, view enrolled students, and keep track of your teaching schedule.</p>
        </div>
        <div class="card">
            <i class="fas fa-shield-alt"></i>
            <h3>Admin Control</h3>
            <p>Manage users (students/lecturers), create courses, assign timetables, approve registration/withdrawal requests, and oversee system.</p>
        </div>
        <div class="card">
            <i class="fas fa-clock"></i>
            <h3>Conflict Detection</h3>
            <p>Intelligent system prevents timetable clashes and enforces credit limits, ensuring a smooth academic experience.</p>
        </div>
        <div class="card">
            <i class="fas fa-cloud-upload-alt"></i>
            <h3>Digital Materials</h3>
            <p>Lecturers can upload PDFs, slides, and other resources – students download them directly from their dashboard.</p>
        </div>
        <div class="card">
            <i class="fas fa-robot"></i>
            <h3>AI Assistant</h3>
            <p>Built‑in chatbot helps you find course info, check timetables, and answer common questions instantly.</p>
        </div>
    </div>
</section>

<footer class="footer">
    <p><i class="fas fa-copyright"></i> <?php echo date('Y'); ?> Course Registration System – iYunivesithi Sisulu University | Secure Role‑Based Access</p>
</footer>

</body>
</html>