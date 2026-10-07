<?php
session_start();
require_once '../config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['mot_de_passe'];

// تحقق من utilisateurs أولاً (admin/professeur/parent)
    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['mot_de_passe'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['nom'] = $user['nom'];
        $_SESSION['ecole_id'] = $user['ecole_id'];
        $_SESSION['matiere'] = $user['matiere'] ?? '';

        switch ($user['role']) {
            case 'admin':
                header('Location: ../admin/dashboard.php');
                break;
            case 'professeur':
             header('Location: ../professeur/dashboard.php');
                break;
            case 'parent':
                header('Location: ../parent/dashboard.php');
                break;
        }
        exit();
    }

// تحقق من eleves (الطالب)
$stmt = $pdo->prepare("SELECT * FROM eleves WHERE email = ?");
$stmt->execute([$email]);
$eleves = $stmt->fetch(PDO::FETCH_ASSOC);

if ($eleves && $eleves['mot_de_passe'] && password_verify($password, $eleves['mot_de_passe'])) {
    $_SESSION['user_id'] = $eleve['id'];
    $_SESSION['role'] = 'eleves';
    $_SESSION['nom'] = $eleve['nom'];
    $_SESSION['ecole_id'] = $eleves['ecole_id'];
    $_SESSION['classe_id'] = $eleves['classe_id'];

    header('Location: ../eleves/dashboard.php');
    exit();
}

$error = "Email أو كلمة المرور غلط!";
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wassit — تسجيل الدخول</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f0f4f8;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .card {
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        h1 {
            text-align: center;
            color: #1a73e8;
            margin-bottom: 8px;
            font-size: 28px;
        }
        p.subtitle {
            text-align: center;
            color: #888;
            margin-bottom: 30px;
            font-size: 14px;
        }
        input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 15px;
            outline: none;
            transition: border 0.2s;
        }
        input:focus { border-color: #1a73e8; }
        button {
            width: 100%;
            padding: 13px;
            background: #1a73e8;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.2s;
        }
        button:hover { background: #1558b0; }
        .error {
            background: #fdecea;
            color: #c0392b;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 16px;
            text-align: center;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Wassit</h1>
        <p class="subtitle">منصة التواصل بين المعهد والأسرة</p>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="email" name="email" placeholder="البريد الإلكتروني" required>
            <input type="password" name="mot_de_passe" placeholder="كلمة المرور" required>
            <button type="submit">دخول</button>
        </form>
    </div>
</body>
</html>