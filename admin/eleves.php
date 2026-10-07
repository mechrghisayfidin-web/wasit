<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$success = '';
$error = '';

// إضافة ولي أمر + طالب
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $nom_eleves = trim($_POST['nom_eleves']);
    $classe_id = $_POST['classe_id'];
    $nom_parent = trim($_POST['nom_parent']);
    $email_parent = trim($_POST['email_parent']);
    $password = password_hash('123456', PASSWORD_DEFAULT);
    $email_eleves = trim($_POST['email_eleves'] ?? '');

    if (!empty($nom_eleves) && !empty($classe_id) && !empty($nom_parent) && !empty($email_parent)) {
        // تحقق من الإيميل
        $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email_parent]);
        if ($stmt->fetch()) {
            $error = "هذا الإيميل موجود مسبقاً!";
        } else {
            // إضافة الولي
            $stmt = $pdo->prepare("
                INSERT INTO utilisateurs (ecole_id, nom, email, mot_de_passe, role) 
                VALUES (?, ?, ?, ?, 'parent')
            ");
            $stmt->execute([$_SESSION['ecole_id'], $nom_parent, $email_parent, $password]);
            $parent_id = $pdo->lastInsertId();

            // إضافة الطالب
            // إضافة الطالب
            $eleves_email = !empty($email_eleves) ? $email_eleves : null;
            $eleves_password = !empty($email_eleves) ? $password : null;

            $stmt = $pdo->prepare("
                INSERT INTO eleves (ecole_id, nom, parent_id, classe_id, email, mot_de_passe) 
            VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$_SESSION['ecole_id'], $nom_eleves, $parent_id, $classe_id, $eleves_email, $eleves_password]);
            $success = "تم إضافة الطالب وولي أمره بنجاح ✅ — كلمة المرور: 123456";
        }
    } else {
        $error = "املأ كل الحقول!";
    }
}

// حذف طالب
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = $_POST['eleve_id'];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM observations WHERE eleve_id = ?");
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() > 0) {
        $error = "لا يمكن حذف طالب عنده ملاحظات!";
    } else {
        // جلب parent_id باش نحذفوه كذلك
        $stmt = $pdo->prepare("SELECT parent_id FROM eleves WHERE id = ?");
        $stmt->execute([$id]);
        $parent_id = $stmt->fetchColumn();

        $stmt = $pdo->prepare("DELETE FROM eleves WHERE id = ? AND ecole_id = ?");
        $stmt->execute([$id, $_SESSION['ecole_id']]);

        // حذف الولي إذا ما عندوش أولاد أخرين
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM eleves WHERE parent_id = ?");
        $stmt->execute([$parent_id]);
        if ($stmt->fetchColumn() == 0) {
            $stmt = $pdo->prepare("DELETE FROM utilisateurs WHERE id = ?");
            $stmt->execute([$parent_id]);
        }

        $success = "تم حذف الطالب ✅";
    }
}

// جلب الأقسام
$stmt = $pdo->prepare("SELECT * FROM classes WHERE ecole_id = ? ORDER BY niveau, nom");
$stmt->execute([$_SESSION['ecole_id']]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب الطلبة
$stmt = $pdo->prepare("
    SELECT e.*, c.nom AS classe_nom, c.niveau,
           u.nom AS parent_nom, u.email AS parent_email,
           COUNT(o.id) AS total_obs
    FROM eleves e
    LEFT JOIN classes c ON e.classe_id = c.id
    LEFT JOIN utilisateurs u ON e.parent_id = u.id
    LEFT JOIN observations o ON o.eleve_id = e.id
    WHERE e.ecole_id = ?
    GROUP BY e.id
    ORDER BY c.nom, e.nom
");
$stmt->execute([$_SESSION['ecole_id']]);
$eleves = $stmt->fetchAll(PDO::FETCH_ASSOC);

// تجميع حسب القسم
$eleves_par_classe = [];
foreach ($eleves as $eleve) {
    $classe_nom = $eleve['classe_nom'] ?? 'بدون قسم';
    $eleves_par_classe[$classe_nom][] = $eleves;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wassit — الطلبة</title>
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
        h3 {
            color: #1a73e8;
            font-size: 16px;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e8f0fe;
        }
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
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-blue { background: #e8f0fe; color: #1a73e8; }
        .badge-green { background: #e6f4ea; color: #2e7d32; }
        .section { margin-bottom: 32px; }
        .empty { text-align: center; color: #aaa; padding: 20px; }
        .divider {
            border: none;
            border-top: 2px solid #f0f0f0;
            margin: 20px 0;
        }
        label {
            display: block;
            font-size: 13px;
            color: #555;
            margin-bottom: 4px;
            font-weight: bold;
        }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">

    <!-- إضافة طالب -->
    <div class="card">
        <h2>🎓 إضافة طالب جديد</h2>

        <?php if ($success): ?>
            <div class="success"><?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>

        <?php if (empty($classes)): ?>
            <div class="error">⚠️ أضف قسم أولاً من صفحة الأقسام!</div>
        <?php else: ?>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-grid">
                <div>
                    <label>اسم الطالب</label>
                    <input type="text" name="nom_eleves" placeholder="الاسم الكامل" required>
                </div>
                <div>
                    <label>القسم</label>
                    <select name="classe_id" required>
                        <option value="">— اختر القسم —</option>
                        <?php foreach ($classes as $classe): ?>
                            <option value="<?= $classe['id'] ?>">
                                <?= htmlspecialchars($classe['nom']) ?> — <?= htmlspecialchars($classe['niveau']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <hr class="full divider">
                <div>
                    <label>اسم ولي الأمر</label>
                    <input type="text" name="nom_parent" placeholder="الاسم الكامل" required>
                </div>
                <div>
                    <label>إيميل ولي الأمر</label>
                    <input type="email" name="email_parent" placeholder="للدخول للمنصة" required>
                </div>

                <hr class="full divider">
                <div>
                    <label>إيميل الطالب (اختياري)</label>
                    <input type="email" name="email_eleves" placeholder="للدخول لمنصته الخاصة">
                </div>
                <div>
                    <label>&nbsp;</label>
                    <p style="font-size:13px; color:#888; padding-top:10px;">
                    كلمة المرور الافتراضية: 123456              
                    </p>
                </div>              
                <div class="full">
                    <button type="submit" class="btn btn-primary">إضافة الطالب وولي أمره</button>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <!-- قائمة الطلبة حسب القسم -->
    <div class="card">
        <h2>📋 قائمة الطلبة</h2>
        <?php if (empty($eleves)): ?>
            <p class="empty">لا يوجد طلبة بعد</p>
        <?php else: ?>
            <?php foreach ($eleves_par_classe as $classe_nom => $liste): ?>
                <div class="section">
                    <h3>🏫 <?= htmlspecialchars($classe_nom) ?> 
                        <span class="badge badge-blue"><?= count($liste) ?> طالب</span>
                    </h3>
                    <table>
                        <thead>
                            <tr>
                                <th>الطالب</th>
                                <th>ولي الأمر</th>
                                <th>إيميل الولي</th>
                                <th>الملاحظات</th>
                                
                                <th>حذف</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($liste as $eleves): ?>
                                <tr>
                                    <td><?= htmlspecialchars($eleve['nom']) ?></td>
                                    <td><?= htmlspecialchars($eleves['parent_nom'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($eleves['parent_email'] ?? '—') ?></td>
                                    <td><span class="badge badge-green"><?= $eleves['total_obs'] ?></span></td>
                                    <td style="display:flex; gap:8px; align-items:center;">
    <a href="eleves_profile.php?id=<?= $eleve['id'] ?>" 
       class="btn-sm">👤 الملف</a>
    <?php if ($eleves['total_obs'] == 0): ?>
        <form method="POST" style="display:inline"
            onsubmit="return confirm('حذف هذا الطالب وولي أمره؟')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="eleve_id" value="<?= $eleve['id'] ?>">
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
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>
</body>
</html>