<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$success = '';
$error = '';

// إضافة أستاذ
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $nom = trim($_POST['nom']);
    $email = trim($_POST['email']);
    $matiere = trim($_POST['matiere']);
    $password = password_hash('123456', PASSWORD_DEFAULT);

    if (!empty($nom) && !empty($email) && !empty($matiere)) {
        // تحقق من الإيميل
        $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "هذا الإيميل موجود مسبقاً!";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO utilisateurs (ecole_id, nom, email, mot_de_passe, role, matiere) 
                VALUES (?, ?, ?, ?, 'professeur', ?)
            ");
            $stmt->execute([$_SESSION['ecole_id'], $nom, $email, $password, $matiere]);
            $success = "تم إضافة الأستاذ بنجاح ✅ — كلمة المرور الافتراضية: 123456";
        }
    } else {
        $error = "املأ كل الحقول!";
    }
}

// حذف أستاذ
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = $_POST['prof_id'];
    // تحقق ما عندوش ملاحظات
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM observations WHERE professeur_id = ?");
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() > 0) {
        $error = "لا يمكن حذف أستاذ عنده ملاحظات!";
    } else {
        $stmt = $pdo->prepare("DELETE FROM utilisateurs WHERE id = ? AND ecole_id = ? AND role = 'professeur'");
        $stmt->execute([$id, $_SESSION['ecole_id']]);
        $success = "تم حذف الأستاذ ✅";
    }
}

// جلب الأساتذة
$stmt = $pdo->prepare("
    SELECT u.*, COUNT(o.id) AS total_obs,
           MAX(o.date_obs) AS derniere_obs
    FROM utilisateurs u
    LEFT JOIN observations o ON o.professeur_id = u.id
    WHERE u.ecole_id = ? AND u.role = 'professeur'
    GROUP BY u.id
    ORDER BY u.nom
");
$stmt->execute([$_SESSION['ecole_id']]);
$professeurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wassit — الأساتذة</title>
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
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .form-grid .full { grid-column: 1 / -1; }
        input, select {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
            font-family: inherit;
            outline: none;
        }
        input:focus, select:focus { border-color: #1a73e8; }
        .btn {
            padding: 11px 24px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            cursor: pointer;
        }
        .btn-primary { background: #1a73e8; color: white; width: 100%; }
        .btn-primary:hover { background: #1558b0; }
        .btn-danger {
            background: #fdecea;
            color: #c0392b;
            padding: 6px 14px;
            font-size: 13px;
        }
        .btn-danger:hover { background: #f5c6cb; }
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
        table { width: 100%; border-collapse: collapse; }
        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: right;
            font-size: 14px;
            color: #555;
            border-bottom: 2px solid #eee;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
            color: #444;
        }
        .badge-matiere {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            background: #e8f0fe;
            color: #1a73e8;
            font-weight: bold;
        }
        .badge-obs {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            background: #e6f4ea;
            color: #2e7d32;
            font-weight: bold;
        }
        .empty { text-align: center; color: #aaa; padding: 20px; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">

    <!-- إضافة أستاذ -->
    <div class="card">
        <h2>👨‍🏫 إضافة أستاذ جديد</h2>

        <?php if ($success): ?>
            <div class="success"><?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-grid">
                <input type="text" name="nom" placeholder="الاسم الكامل" required>
                <input type="email" name="email" placeholder="البريد الإلكتروني" required>
                <div class="full">
                    <select name="matiere" required>
                        <option value="">— اختر المادة —</option>
                        <option value="رياضيات">رياضيات</option>
                        <option value="علوم إعلامية">علوم إعلامية</option>
                        <option value="فيزياء">فيزياء</option>
                        <option value="عربية">عربية</option>
                        <option value="فرنسية">فرنسية</option>
                        <option value="إنجليزية">إنجليزية</option>
                        <option value="تاريخ وجغرافيا">تاريخ وجغرافيا</option>
                        <option value="فلسفة">فلسفة</option>
                        <option value="علوم طبيعية">علوم طبيعية</option>
                        <option value="تربية بدنية">تربية بدنية</option>
                    </select>
                </div>
                <div class="full">
                    <button type="submit" class="btn btn-primary">إضافة الأستاذ</button>
                </div>
            </div>
        </form>
    </div>

    <!-- قائمة الأساتذة -->
    <div class="card">
        <h2>📋 قائمة الأساتذة</h2>
        <?php if (empty($professeurs)): ?>
            <p class="empty">لا يوجد أساتذة بعد</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>الاسم</th>
                        <th>الإيميل</th>
                        <th>المادة</th>
                        <th>الملاحظات</th>
                        <th>آخر نشاط</th>
                        <th>حذف</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($professeurs as $prof): ?>
                        <tr>
                            <td><?= htmlspecialchars($prof['nom']) ?></td>
                            <td><?= htmlspecialchars($prof['email']) ?></td>
                            <td><span class="badge-matiere"><?= htmlspecialchars($prof['matiere']) ?></span></td>
                            <td><span class="badge-obs"><?= $prof['total_obs'] ?></span></td>
                            <td><?= $prof['derniere_obs'] ?? '—' ?></td>
                            <td>
                                <?php if ($prof['total_obs'] == 0): ?>
                                    <form method="POST" style="display:inline"
                                        onsubmit="return confirm('حذف هذا الأستاذ؟')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="prof_id" value="<?= $prof['id'] ?>">
                                        <button type="submit" class="btn btn-danger">حذف 🗑️</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color:#aaa; font-size:13px;">عنده ملاحظات</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>
</body>
</html>