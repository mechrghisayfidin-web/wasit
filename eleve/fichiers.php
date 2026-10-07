<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'eleves') {
    header('Location: ../auth/login.php');
    exit();
}

$stmt = $pdo->prepare("SELECT classe_id FROM eleves WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$classe_id = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT f.*, u.nom AS prof_nom
    FROM fichiers f
    JOIN utilisateurs u ON f.professeur_id = u.id
    WHERE f.classe_id = ?
    ORDER BY f.matiere, f.date_ajout DESC
");
$stmt->execute([$classe_id]);
$fichiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$par_matiere = [];
foreach ($fichiers as $f) {
    $par_matiere[$f['matiere'] ?? 'عام'][] = $f;
}

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
            background: white; border-radius: 12px; padding: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 24px;
        }
        h2 { color: #333; margin-bottom: 20px; font-size: 18px; }
        h3 { color: #1a73e8; font-size: 15px; margin-bottom: 12px; margin-top: 20px; }
        h3:first-of-type { margin-top: 0; }
        .files-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px;
        }
        .file-card {
            background: #f8f9fa; border-radius: 10px; padding: 16px; text-align: center;
        }
        .file-icon { font-size: 36px; margin-bottom: 8px; }
        .file-titre { font-size: 13px; font-weight: bold; color: #333; margin-bottom: 4px; word-break: break-word; }
        .file-meta { font-size: 11px; color: #aaa; margin-bottom: 8px; }
        .file-link {
            display: inline-block; color: #1a73e8; text-decoration: none; font-size: 12px;
            background: #e8f0fe; padding: 5px 12px; border-radius: 6px;
        }
        .file-link:hover { background: #d2e3fc; }
        .empty { text-align: center; color: #aaa; padding: 40px; }
        .empty-icon { font-size: 48px; margin-bottom: 12px; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="card">
        <h2>📁 مكتبة الملفات</h2>
        <?php if (empty($fichiers)): ?>
            <div class="empty">
                <div class="empty-icon">📭</div>
                <p>لا توجد ملفات بعد</p>
            </div>
        <?php else: ?>
            <?php foreach ($par_matiere as $matiere => $liste): ?>
                <h3>📚 <?= htmlspecialchars($matiere) ?></h3>
                <div class="files-grid">
                    <?php foreach ($liste as $f): ?>
                        <div class="file-card">
                            <div class="file-icon"><?= getFileIcon($f['fichier_path']) ?></div>
                            <div class="file-titre"><?= htmlspecialchars($f['titre']) ?></div>
                            <div class="file-meta">✍️ <?= htmlspecialchars($f['prof_nom']) ?></div>
                            <a href="../uploads/fichiers/<?= htmlspecialchars($f['fichier_path']) ?>" 
                               target="_blank" class="file-link">⬇️ تحميل</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>