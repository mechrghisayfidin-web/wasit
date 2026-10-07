<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'eleves') {
    header('Location: ../auth/login.php');
    exit();
}

$eleve_id = $_SESSION['user_id'];
$success = '';
$error = '';

// تسليم حل
if (isset($_POST['action']) && $_POST['action'] === 'soumettre') {
    $devoir_id = $_POST['devoir_id'];

    if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] === 0) {
        $ext = pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION);
        $filename = 'solution_' . time() . '_' . rand(1000,9999) . '.' . $ext;
        $dest = '../uploads/devoirs/' . $filename;

        if (move_uploaded_file($_FILES['fichier']['tmp_name'], $dest)) {
            // تحقق إذا سبق وسلّم
            $stmt = $pdo->prepare("SELECT id FROM devoirs_soumissions WHERE devoir_id = ? AND eleve_id = ?");
            $stmt->execute([$devoir_id, $eleve_id]);
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("UPDATE devoirs_soumissions SET fichier_path = ?, date_soumission = NOW() WHERE devoir_id = ? AND eleve_id = ?");
                $stmt->execute([$filename, $devoir_id, $eleve_id]);
                $success = "تم تحديث الحل ✅";
            } else {
                $stmt = $pdo->prepare("INSERT INTO devoirs_soumissions (devoir_id, eleve_id, fichier_path) VALUES (?, ?, ?)");
                $stmt->execute([$devoir_id, $eleve_id, $filename]);
                $success = "تم تسليم الحل بنجاح ✅";
            }
        } else {
            $error = "فشل رفع الملف!";
        }
    } else {
        $error = "اختر ملف الحل!";
    }
}

// معلومات الطالب (القسم)
$stmt = $pdo->prepare("SELECT classe_id FROM eleves WHERE id = ?");
$stmt->execute([$eleve_id]);
$classe_id = $stmt->fetchColumn();

// جلب الواجبات + حالة التسليم
$stmt = $pdo->prepare("
    SELECT d.*, u.nom AS prof_nom,
           s.fichier_path AS solution_path, s.date_soumission
    FROM devoirs d
    JOIN utilisateurs u ON d.professeur_id = u.id
    LEFT JOIN devoirs_soumissions s ON s.devoir_id = d.id AND s.eleve_id = ?
    WHERE d.classe_id = ?
    ORDER BY d.date_limite DESC
");
$stmt->execute([$eleve_id, $classe_id]);
$devoirs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$aujourd_hui = date('Y-m-d');
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
        .devoir-item.urgent { border-color: #ffcdd2; background: #fff5f5; }
        .devoir-item.done { border-color: #c8e6c9; background: #f5fff5; }
        .devoir-header {
            display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;
        }
        .devoir-titre { font-weight: bold; color: #333; font-size: 15px; margin-bottom: 4px; }
        .devoir-prof { font-size: 12px; color: #1a73e8; font-weight: bold; }
        .devoir-desc { color: #555; font-size: 14px; margin: 8px 0; line-height: 1.5; }
        .devoir-meta {
            display: flex; justify-content: space-between; align-items: center;
            margin-top: 10px; flex-wrap: wrap; gap: 8px;
        }
        .date-limite { font-size: 13px; color: #888; }
        .date-limite.urgent { color: #c0392b; font-weight: bold; }
        .badge {
            display: inline-block; padding: 4px 10px; border-radius: 20px;
            font-size: 12px; font-weight: bold;
        }
        .badge-done { background: #e6f4ea; color: #2e7d32; }
        .badge-pending { background: #fff3e0; color: #e65100; }
        .file-link {
            color: #1a73e8; text-decoration: none; font-size: 13px;
            background: #e8f0fe; padding: 6px 12px; border-radius: 6px; display: inline-block;
        }
        .file-link:hover { background: #d2e3fc; }
        .upload-form {
            display: flex; gap: 8px; align-items: center; margin-top: 10px;
        }
        .upload-form input[type="file"] {
            flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;
        }
        .btn-submit {
            background: #1a73e8; color: white; border: none; padding: 8px 18px;
            border-radius: 6px; font-size: 13px; cursor: pointer; white-space: nowrap;
        }
        .btn-submit:hover { background: #1558b0; }
        .empty { text-align: center; color: #aaa; padding: 40px; }
        .empty-icon { font-size: 48px; margin-bottom: 12px; }
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

    <div class="card">
        <h2>📝 واجباتي</h2>
        <?php if (empty($devoirs)): ?>
            <div class="empty">
                <div class="empty-icon">📭</div>
                <p>لا توجد واجبات بعد</p>
            </div>
        <?php else: ?>
            <?php foreach ($devoirs as $d): ?>
                <?php
                $is_done = $d['solution_path'] !== null;
                $is_urgent = !$is_done && $d['date_limite'] <= date('Y-m-d', strtotime('+2 days'));
                $is_late = !$is_done && $d['date_limite'] < $aujourd_hui;
                $class = $is_done ? 'done' : ($is_urgent ? 'urgent' : '');
                ?>
                <div class="devoir-item <?= $class ?>">
                    <div class="devoir-header">
                        <div>
                            <div class="devoir-titre"><?= htmlspecialchars($d['titre']) ?></div>
                            <div class="devoir-prof">
                                ✍️ <?= htmlspecialchars($d['prof_nom']) ?>
                                <?php if ($d['matiere']): ?> — <?= htmlspecialchars($d['matiere']) ?><?php endif; ?>
                            </div>
                        </div>
                        <?php if ($is_done): ?>
                            <span class="badge badge-done">✅ تم التسليم</span>
                        <?php else: ?>
                            <span class="badge badge-pending">⏳ لم تسلم</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($d['description']): ?>
                        <div class="devoir-desc"><?= htmlspecialchars($d['description']) ?></div>
                    <?php endif; ?>

                    <?php if ($d['fichier_path']): ?>
                        <a href="../uploads/devoirs/<?= htmlspecialchars($d['fichier_path']) ?>" 
                           target="_blank" class="file-link">📎 ملف الواجب</a>
                    <?php endif; ?>

                    <div class="devoir-meta">
                        <span class="date-limite <?= $is_late ? 'urgent' : '' ?>">
                            📅 آخر موعد: <?= $d['date_limite'] ?>
                            <?php if ($is_late): ?> (متأخر!)<?php endif; ?>
                        </span>

                        <?php if ($is_done): ?>
                            <a href="../uploads/devoirs/<?= htmlspecialchars($d['solution_path']) ?>" 
                               target="_blank" class="file-link">📄 حلي المُسلّم</a>
                        <?php endif; ?>
                    </div>

                    <form method="POST" enctype="multipart/form-data" class="upload-form">
                        <input type="hidden" name="action" value="soumettre">
                        <input type="hidden" name="devoir_id" value="<?= $d['id'] ?>">
                        <input type="file" name="fichier" required>
                        <button type="submit" class="btn-submit">
                            <?= $is_done ? '🔄 تحديث الحل' : '📤 تسليم الحل' ?>
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>
</body>
</html>