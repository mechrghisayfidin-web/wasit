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

// رفع ملف
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $classe_id = $_POST['classe_id'];
    $titre = trim($_POST['titre']);

    if (!empty($classe_id) && !empty($titre) && isset($_FILES['fichier']) && $_FILES['fichier']['error'] === 0) {
        $ext = pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION);
        $filename = 'fichier_' . time() . '_' . rand(1000,9999) . '.' . $ext;
        $dest = '../uploads/fichiers/' . $filename;

        if (move_uploaded_file($_FILES['fichier']['tmp_name'], $dest)) {
            $stmt = $pdo->prepare("
                INSERT INTO fichiers (classe_id, professeur_id, matiere, titre, fichier_path)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$classe_id, $prof_id, $_SESSION['matiere'], $titre, $filename]);
            $success = "تم رفع الملف بنجاح ✅";
        } else {
            $error = "فشل رفع الملف!";
        }
    } else {
        $error = "املأ كل الحقول واختر ملف!";
    }
}

// حذف ملف
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = $_POST['fichier_id'];
    $stmt = $pdo->prepare("SELECT fichier_path FROM fichiers WHERE id = ? AND professeur_id = ?");
    $stmt->execute([$id, $prof_id]);
    $f = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($f) {
        @unlink('../uploads/fichiers/' . $f['fichier_path']);
        $stmt = $pdo->prepare("DELETE FROM fichiers WHERE id = ? AND professeur_id = ?");
        $stmt->execute([$id, $prof_id]);
        $success = "تم حذف الملف ✅";
    }
}

// جلب الأقسام
$stmt = $pdo->prepare("SELECT * FROM classes WHERE ecole_id = ? ORDER BY nom");
$stmt->execute([$_SESSION['ecole_id']]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب ملفات الأستاذ
$stmt = $pdo->prepare("
    SELECT f.*, c.nom AS classe_nom
    FROM fichiers f
    JOIN classes c ON f.classe_id = c.id
    WHERE f.professeur_id = ?
    ORDER BY f.date_ajout DESC
");
$stmt->execute([$prof_id]);
$fichiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// أيقونة حسب نوع الملف
function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $icons = [
        'pdf' => '📕', 'doc' => '📘', 'docx' => '📘',
        'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️',
        'ppt' => '📙', 'pptx' => '📙',
        'xls' => '📗', 'xlsx' => '📗',
        'zip' => '🗂️', 'rar' => '🗂️',
    ];
    return $icons[$ext] ?? '📄';
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wassit — مكتبة الملفات</title>
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
        h3 { color: #1a73e8; font-size: 15px; margin-bottom: 12px; margin-top: 20px; }
        h3:first-of-type { margin-top: 0; }
        label { display: block; font-size: 13px; color: #555; margin-bottom: 4px; font-weight: bold; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-grid .full { grid-column: 1 / -1; }
        select, input {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
            font-family: inherit;
            outline: none;
        }
        select:focus, input:focus { border-color: #1a73e8; }
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
        .files-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 12px;
        }
        .file-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 16px;
            text-align: center;
            position: relative;
        }
        .file-icon { font-size: 36px; margin-bottom: 8px; }
        .file-titre {
            font-size: 13px; font-weight: bold; color: #333;
            margin-bottom: 4px; word-break: break-word;
        }
        .file-date { font-size: 11px; color: #aaa; margin-bottom: 8px; }
        .file-link {
            display: inline-block;
            color: #1a73e8; text-decoration: none; font-size: 12px;
            background: #e8f0fe; padding: 5px 12px; border-radius: 6px;
        }
        .file-link:hover { background: #d2e3fc; }
        .btn-danger {
            background: #fdecea; color: #c0392b; padding: 5px 10px;
            font-size: 11px; border: none; border-radius: 6px; cursor: pointer;
            margin-top: 6px;
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

    <!-- رفع ملف -->
    <div class="card">
        <h2>📁 رفع ملف جديد</h2>
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
                    <label>عنوان الملف</label>
                    <input type="text" name="titre" placeholder="مثال: درس الفصل 3" required>
                </div>
                <div class="full">
                    <label>الملف</label>
                    <input type="file" name="fichier" required>
                </div>
                <div class="full">
                    <button type="submit" class="btn-primary">رفع الملف 📁</button>
                </div>
            </div>
        </form>
    </div>

    <!-- ملفاتي -->
    <div class="card">
        <h2>📋 ملفاتي</h2>
        <?php if (empty($fichiers)): ?>
            <p class="empty">لا توجد ملفات بعد</p>
        <?php else: ?>
            <div class="files-grid">
                <?php foreach ($fichiers as $f): ?>
                    <div class="file-card">
                        <div class="file-icon"><?= getFileIcon($f['fichier_path']) ?></div>
                        <div class="file-titre"><?= htmlspecialchars($f['titre']) ?></div>
                        <div class="file-date">📚 <?= htmlspecialchars($f['classe_nom']) ?></div>
                        <div class="file-date"><?= date('d/m/Y', strtotime($f['date_ajout'])) ?></div>
                        <a href="../uploads/fichiers/<?= htmlspecialchars($f['fichier_path']) ?>" 
                           target="_blank" class="file-link">⬇️ تحميل</a>
                        <br>
                        <form method="POST" onsubmit="return confirm('حذف هذا الملف؟')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="fichier_id" value="<?= $f['id'] ?>">
                            <button type="submit" class="btn-danger">حذف 🗑️</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>
</body>
</html>