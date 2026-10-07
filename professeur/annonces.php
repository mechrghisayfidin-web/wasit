<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professeur') {
    header('Location: ../auth/login.php');
    exit();
}

$success = '';
$error = '';
$prof_id = $_SESSION['user_id'];

// نشر إعلان
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $classe_id = $_POST['classe_id'];
    $titre = trim($_POST['titre']);
    $contenu = trim($_POST['contenu']);

    if (!empty($classe_id) && !empty($titre) && !empty($contenu)) {
        $stmt = $pdo->prepare("
            INSERT INTO annonces (classe_id, professeur_id, titre, contenu)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$classe_id, $prof_id, $titre, $contenu]);
        $success = "تم نشر الإعلان ✅";
    } else {
        $error = "املأ كل الحقول!";
    }
}

// حذف إعلان
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = $_POST['annonce_id'];
    $stmt = $pdo->prepare("DELETE FROM annonces WHERE id = ? AND professeur_id = ?");
    $stmt->execute([$id, $prof_id]);
    $success = "تم حذف الإعلان ✅";
}

// جلب الأقسام
$stmt = $pdo->prepare("SELECT * FROM classes WHERE ecole_id = ? ORDER BY nom");
$stmt->execute([$_SESSION['ecole_id']]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب إعلانات الأستاذ
$stmt = $pdo->prepare("
    SELECT a.*, c.nom AS classe_nom
    FROM annonces a
    JOIN classes c ON a.classe_id = c.id
    WHERE a.professeur_id = ?
    ORDER BY a.date_publication DESC
");
$stmt->execute([$prof_id]);
$annonces = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wassit — الإعلانات</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f4f8; }
        .card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 24px;
        }
        h2 { color: #333; margin-bottom: 20px; font-size: 18px; }
        label { display: block; font-size: 13px; color: #555; margin-bottom: 4px; font-weight: bold; }
        select, input, textarea {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
            font-family: inherit;
            outline: none;
            margin-bottom: 14px;
        }
        select:focus, input:focus, textarea:focus { border-color: #1a73e8; }
        textarea { resize: vertical; min-height: 100px; }
        .btn-primary {
            width: 100%;
            padding: 12px;
            background: #1a73e8;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            cursor: pointer;
        }
        .btn-primary:hover { background: #1558b0; }
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
        .annonce-item {
            border: 1px solid #f0f0f0;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 12px;
            background: #fafafa;
        }
        .annonce-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }
        .annonce-titre { font-weight: bold; color: #333; font-size: 15px; }
        .annonce-classe {
            display: inline-block;
            background: #e8f0fe;
            color: #1a73e8;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            margin-bottom: 6px;
        }
        .annonce-contenu {
            color: #555;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 8px;
        }
        .annonce-date { color: #aaa; font-size: 12px; }
        .btn-danger {
            background: #fdecea;
            color: #c0392b;
            padding: 6px 14px;
            font-size: 13px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        .btn-danger:hover { background: #f5c6cb; }
        .empty { text-align: center; color: #aaa; padding: 24px; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">

    <?php if ($success): ?>
        <div class="success"><?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>

    <!-- نشر إعلان -->
    <div class="card">
        <h2>📢 نشر إعلان جديد</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <label>القسم</label>
            <select name="classe_id" required>
                <option value="">— اختر القسم —</option>
                <?php foreach ($classes as $classe): ?>
                    <option value="<?= $classe['id'] ?>">
                        <?= htmlspecialchars($classe['nom']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label>عنوان الإعلان</label>
            <input type="text" name="titre" placeholder="مثال: تغيير موعد الفرض" required>
            <label>المحتوى</label>
            <textarea name="contenu" placeholder="اكتب الإعلان هنا..." required></textarea>
            <button type="submit" class="btn-primary">نشر الإعلان 📢</button>
        </form>
    </div>

    <!-- إعلاناتي -->
    <div class="card">
        <h2>📋 إعلاناتي</h2>
        <?php if (empty($annonces)): ?>
            <p class="empty">لا توجد إعلانات بعد</p>
        <?php else: ?>
            <?php foreach ($annonces as $a): ?>
                <div class="annonce-item">
                    <div class="annonce-header">
                        <div>
                            <span class="annonce-classe">📚 <?= htmlspecialchars($a['classe_nom']) ?></span>
                            <div class="annonce-titre"><?= htmlspecialchars($a['titre']) ?></div>
                        </div>
                        <form method="POST" onsubmit="return confirm('حذف هذا الإعلان؟')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="annonce_id" value="<?= $a['id'] ?>">
                            <button type="submit" class="btn-danger">حذف 🗑️</button>
                        </form>
                    </div>
                    <div class="annonce-contenu"><?= htmlspecialchars($a['contenu']) ?></div>
                    <div class="annonce-date">🕐 <?= $a['date_publication'] ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>
</body>
</html>