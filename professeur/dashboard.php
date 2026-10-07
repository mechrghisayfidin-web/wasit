<?php
session_start();
require_once '../config/db.php';

// حماية الصفحة
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professeur') {
    header('Location: ../auth/login.php');
    exit();
}

$success = '';
$error = '';

// حفظ الملاحظة
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eleve_id = $_POST['eleve_id'] ?? '';
    $contenu = trim($_POST['contenu']);

    if (!empty($contenu) && !empty($eleve_id)) {
        $stmt = $pdo->prepare("INSERT INTO observations (professeur_id, eleve_id, contenu) VALUES (?, ?, ?)");
$stmt->execute([$_SESSION['user_id'], $eleve_id, $contenu]);
        $success = "تم حفظ الملاحظة بنجاح ✅";
    } else {
        $error = "اختر طالب واكتب ملاحظة!";
    }
}

// جلب الطلبة من نفس المعهد
$stmt = $pdo->prepare("SELECT * FROM eleves WHERE ecole_id = ?");
$stmt->execute([$_SESSION['ecole_id']]);
$eleves = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب آخر الملاحظات
$stmt = $pdo->prepare("
    SELECT o.contenu, o.date_obs, e.nom AS eleve_nom 
    FROM observations o
    JOIN eleves e ON o.eleve_id = e.id
    WHERE o.professeur_id = ?
    ORDER BY o.date_obs DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$observations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wassit — لوحة الأستاذ</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f0f4f8;
            min-height: 100vh;
        }
        header {
            background: #1a73e8;
            color: white;
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        header h1 { font-size: 20px; }
        header a {
            color: white;
            text-decoration: none;
            font-size: 14px;
            background: rgba(255,255,255,0.2);
            padding: 6px 12px;
            border-radius: 6px;
        }
        .container {
            max-width: 700px;
            margin: 30px auto;
            padding: 0 16px;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 24px;
        }
        h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 18px;
        }
        select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 14px;
            font-size: 15px;
            font-family: inherit;
            outline: none;
        }
        select:focus, textarea:focus { border-color: #1a73e8; }
        textarea { resize: vertical; min-height: 100px; }
        button {
            background: #1a73e8;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 15px;
            cursor: pointer;
            width: 100%;
        }
        button:hover { background: #1558b0; }
        .success {
            background: #e6f4ea;
            color: #2e7d32;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 16px;
            text-align: center;
        }
        .error {
            background: #fdecea;
            color: #c0392b;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 16px;
            text-align: center;
        }
        .obs-item {
            border-bottom: 1px solid #f0f0f0;
            padding: 12px 0;
        }
        .obs-item:last-child { border-bottom: none; }
        .obs-nom { font-weight: bold; color: #1a73e8; font-size: 14px; }
        .obs-contenu { color: #444; margin: 4px 0; font-size: 15px; }
        .obs-date { color: #aaa; font-size: 12px; }
        .empty { text-align: center; color: #aaa; padding: 20px; }
    </style>
</head>
<body>


<?php include 'sidebar.php'; ?>
<div class="main-content">

    <!-- فورم الملاحظة -->
    <div class="card">
        <h2>✏️ إضافة ملاحظة</h2>

        <?php if ($success): ?>
            <div class="success"><?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <select name="eleve_id" required>
                <option value="">— اختر الطالب —</option>
                <?php foreach ($eleves as $eleve): ?>
                    <option value="<?= $eleve['id'] ?>">
                        <?= htmlspecialchars($eleve['nom']) ?> — <?= htmlspecialchars($eleves['classe']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <textarea name="contenu" placeholder="اكتب ملاحظتك هنا..."></textarea>

            <button type="submit">حفظ الملاحظة</button>
        </form>
    </div>

    <!-- آخر الملاحظات -->
    <div class="card">
        <h2>📋 آخر ملاحظاتك</h2>
        <?php if (empty($observations)): ?>
            <p class="empty">لا توجد ملاحظات بعد</p>
        <?php else: ?>
            <?php foreach ($observations as $obs): ?>
                <div class="obs-item">
                    <div class="obs-nom"><?= htmlspecialchars($obs['eleve_nom']) ?></div>
                    <div class="obs-contenu"><?= htmlspecialchars($obs['contenu']) ?></div>
                    <div class="obs-date"><?= $obs['date_obs'] ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>
</body>
</html>