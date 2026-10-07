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

// نشر واجب
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $classe_id = $_POST['classe_id'];
    $titre = trim($_POST['titre']);
    $description = trim($_POST['description']);
    $date_limite = $_POST['date_limite'];

    if (!empty($classe_id) && !empty($titre) && !empty($date_limite)) {
        $fichier_path = null;

        if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] === 0) {
            $ext = pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION);
            $filename = 'devoir_' . time() . '_' . rand(1000,9999) . '.' . $ext;
            $dest = '../uploads/devoirs/' . $filename;
            if (move_uploaded_file($_FILES['fichier']['tmp_name'], $dest)) {
                $fichier_path = $filename;
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO devoirs (classe_id, professeur_id, matiere, titre, description, date_limite, fichier_path)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$classe_id, $prof_id, $_SESSION['matiere'], $titre, $description, $date_limite, $fichier_path]);
        $success = "تم نشر الواجب ✅";
    } else {
        $error = "املأ كل الحقول المطلوبة!";
    }
}

// حذف واجب
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = $_POST['devoir_id'];
    $stmt = $pdo->prepare("DELETE FROM devoirs WHERE id = ? AND professeur_id = ?");
    $stmt->execute([$id, $prof_id]);
    $success = "تم حذف الواجب ✅";
}

// جلب الأقسام
$stmt = $pdo->prepare("SELECT * FROM classes WHERE ecole_id = ? ORDER BY nom");
$stmt->execute([$_SESSION['ecole_id']]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب واجبات الأستاذ مع عدد الحلول
$stmt = $pdo->prepare("
    SELECT d.*, c.nom AS classe_nom,
           (SELECT COUNT(*) FROM eleves WHERE classe_id = d.classe_id) AS total_eleves,
           (SELECT COUNT(*) FROM devoirs_soumissions WHERE devoir_id = d.id) AS total_soumissions
    FROM devoirs d
    JOIN classes c ON d.classe_id = c.id
    WHERE d.professeur_id = ?
    ORDER BY d.date_publication DESC
");
$stmt->execute([$prof_id]);
$devoirs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// عرض حلول واجب محدد
$devoir_active = null;
$soumissions = [];
if (isset($_GET['devoir_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM devoirs WHERE id = ? AND professeur_id = ?");
    $stmt->execute([$_GET['devoir_id'], $prof_id]);
    $devoir_active = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($devoir_active) {
        $stmt = $pdo->prepare("
            SELECT s.*, e.nom AS eleve_nom
            FROM devoirs_soumissions s
            JOIN eleves e ON s.eleve_id = e.id
            WHERE s.devoir_id = ?
            ORDER BY s.date_soumission DESC
        ");
        $stmt->execute([$_GET['devoir_id']]);
        $soumissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wassit — الواجبات</title>
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
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-grid .full { grid-column: 1 / -1; }
        select, input, textarea {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
            font-family: inherit;
            outline: none;
        }
        select:focus, input:focus, textarea:focus { border-color: #1a73e8; }
        textarea { resize: vertical; min-height: 90px; }
        .btn-primary {
            padding: 11px 24px;
            background: #1a73e8;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            cursor: pointer;
            width: 100%;
        }
        .btn-primary:hover { background: #1558b0; }
        .success {
            background: #e6f4ea; color: #2e7d32; padding: 10px;
            border-radius: 8px; margin-bottom: 16px; text-align: center;
        }
        .error {
            background: #fdecea; color: #c0392b; padding: 10px;
            border-radius: 8px; margin-bottom: 16px; text-align: center;
        }
        .devoir-item {
            border: 1px solid #f0f0f0;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 12px;
            background: #fafafa;
        }
        .devoir-header {
            display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;
        }
        .devoir-classe {
            display: inline-block; background: #e8f0fe; color: #1a73e8;
            padding: 3px 10px; border-radius: 20px; font-size: 12px; margin-bottom: 6px;
        }
        .devoir-titre { font-weight: bold; color: #333; font-size: 15px; }
        .devoir-desc { color: #555; font-size: 14px; margin-bottom: 8px; line-height: 1.5; }
        .devoir-meta { display: flex; gap: 16px; font-size: 12px; color: #888; }
        .badge {
            display: inline-block; padding: 4px 10px; border-radius: 20px;
            font-size: 12px; font-weight: bold;
        }
        .badge-blue { background: #e8f0fe; color: #1a73e8; }
        .btn-danger {
            background: #fdecea; color: #c0392b; padding: 6px 14px;
            font-size: 13px; border: none; border-radius: 6px; cursor: pointer;
        }
        .btn-danger:hover { background: #f5c6cb; }
        .btn-sm {
            padding: 6px 14px; font-size: 13px; background: #e8f0fe; color: #1a73e8;
            border: none; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block;
        }
        .btn-sm:hover { background: #d2e3fc; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8f9fa; padding: 12px; text-align: right; font-size: 14px; color: #555; border-bottom: 2px solid #eee; }
        td { padding: 12px; border-bottom: 1px solid #f0f0f0; font-size: 14px; color: #444; }
        .empty { text-align: center; color: #aaa; padding: 24px; }
        .file-link { color: #1a73e8; text-decoration: none; font-size: 13px; }
        .file-link:hover { text-decoration: underline; }
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

    <!-- عرض حلول واجب محدد -->
    <?php if ($devoir_active): ?>
    <div class="card">
        <h2>📥 حلول — <?= htmlspecialchars($devoir_active['titre']) ?></h2>
        <?php if (empty($soumissions)): ?>
            <p class="empty">لم يسلم أحد بعد</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>الطالب</th>
                        <th>تاريخ التسليم</th>
                        <th>الملف</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($soumissions as $s): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['eleve_nom']) ?></td>
                            <td><?= $s['date_soumission'] ?></td>
                            <td>
                                <a href="../uploads/devoirs/<?= htmlspecialchars($s['fichier_path']) ?>" 
                                   target="_blank" class="file-link">📄 عرض الملف</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- نشر واجب -->
    <div class="card">
        <h2>📝 نشر واجب جديد</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">
            <div class="form-grid">
                <div>
                    <label>القسم</label>
                    <select name="classe_id" required>
                        <option value="">— اختر القسم —</option>
                        <?php foreach ($classes as $classe): ?>
                            <option value="<?= $classe['id'] ?>"><?= htmlspecialchars($classe['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>آخر موعد للتسليم</label>
                    <input type="date" name="date_limite" min="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="full">
                    <label>عنوان الواجب</label>
                    <input type="text" name="titre" placeholder="مثال: تمارين الفصل الثالث" required>
                </div>
                <div class="full">
                    <label>الوصف</label>
                    <textarea name="description" placeholder="اشرح الواجب..."></textarea>
                </div>
                <div class="full">
                    <label>ملف مرفق (اختياري)</label>
                    <input type="file" name="fichier">
                </div>
                <div class="full">
                    <button type="submit" class="btn-primary">نشر الواجب 📝</button>
                </div>
            </div>
        </form>
    </div>

    <!-- واجباتي -->
    <div class="card">
        <h2>📋 واجباتي</h2>
        <?php if (empty($devoirs)): ?>
            <p class="empty">لا توجد واجبات بعد</p>
        <?php else: ?>
            <?php foreach ($devoirs as $d): ?>
                <div class="devoir-item">
                    <div class="devoir-header">
                        <div>
                            <span class="devoir-classe">📚 <?= htmlspecialchars($d['classe_nom']) ?></span>
                            <div class="devoir-titre"><?= htmlspecialchars($d['titre']) ?></div>
                        </div>
                        <form method="POST" onsubmit="return confirm('حذف هذا الواجب؟')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="devoir_id" value="<?= $d['id'] ?>">
                            <button type="submit" class="btn-danger">حذف 🗑️</button>
                        </form>
                    </div>
                    <?php if ($d['description']): ?>
                        <div class="devoir-desc"><?= htmlspecialchars($d['description']) ?></div>
                    <?php endif; ?>
                    <?php if ($d['fichier_path']): ?>
                        <div style="margin-bottom:8px;">
                            <a href="../uploads/devoirs/<?= htmlspecialchars($d['fichier_path']) ?>" 
                               target="_blank" class="file-link">📎 ملف الواجب</a>
                        </div>
                    <?php endif; ?>
                    <div class="devoir-meta">
                        <span>📅 آخر موعد: <?= $d['date_limite'] ?></span>
                        <span class="badge badge-blue"><?= $d['total_soumissions'] ?>/<?= $d['total_eleves'] ?> سلّموا</span>
                        <a href="?devoir_id=<?= $d['id'] ?>" class="btn-sm">📥 عرض الحلول</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>
</body>
</html>