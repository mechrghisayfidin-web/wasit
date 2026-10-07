<?php
session_start();

// إذا مسجل دخول، وجهه مباشرة
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: admin/dashboard.php');
            break;
        case 'professeur':
            header('Location: professeur/dashboard.php');
            break;
        case 'parent':
            header('Location: parent/dashboard.php');
            break;
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wassit</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f0f4f8;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .hero {
            text-align: center;
            padding: 40px 20px;
        }
        h1 {
            font-size: 48px;
            color: #1a73e8;
            margin-bottom: 8px;
        }
        .subtitle {
            color: #888;
            font-size: 18px;
            margin-bottom: 40px;
        }
        .features {
            display: flex;
            gap: 20px;
            margin-bottom: 40px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .feature {
            background: white;
            border-radius: 12px;
            padding: 24px;
            width: 200px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .feature .icon { font-size: 36px; margin-bottom: 12px; }
        .feature h3 { color: #333; font-size: 16px; margin-bottom: 6px; }
        .feature p { color: #888; font-size: 13px; }
        .btn {
            display: inline-block;
            background: #1a73e8;
            color: white;
            padding: 14px 40px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 17px;
            transition: background 0.2s;
        }
        .btn:hover { background: #1558b0; }
        footer {
            margin-top: 40px;
            color: #aaa;
            font-size: 13px;
        }
    </style>
</head>
<body>

<div class="hero">
    <h1>Wassit</h1>
    <p class="subtitle">منصة التواصل بين المعهد والأسرة</p>

    <div class="features">
        <div class="feature">
            <div class="icon">👨‍🏫</div>
            <h3>الأستاذ</h3>
            <p>يكتب ملاحظات دورية على الطلبة</p>
        </div>
        <div class="feature">
            <div class="icon">🏫</div>
            <h3>الإدارة</h3>
            <p>تراقب وتتابع كل شيء</p>
        </div>
        <div class="feature">
            <div class="icon">👨‍👦</div>
            <h3>الولي</h3>
            <p>يستقبل تقارير واضحة عن ولده</p>
        </div>
    </div>

    <a href="auth/login.php" class="btn">ادخل الآن</a>
</div>

<footer>Wassit © 2025 — جميع الحقوق محفوظة</footer>

</body>
</html>