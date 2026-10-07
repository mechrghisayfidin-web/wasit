<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$success = '';
$error = '';

// إضافة قسم
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $nom = trim($_POST['nom']);
    $niveau = trim($_POST['niveau']);

    if (!empty($nom) && !empty($niveau)) {
        $stmt = $pdo->prepare("INSERT INTO classes (ecole_id, nom, niveau) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['ecole_id'], $nom, $niveau]);
        $success = "تم إضافة القسم بنجاح ✅";
    } else {
        $error = "املأ كل الحقول!";
    }
}

// حذف قسم
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = $_POST['classe_id'];
    $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ? AND ecole_id = ?");
    $stmt->execute([$id, $_SESSION['ecole_id']]);
    $success = "تم حذف القسم ✅";
}

// جلب الأقسام
$stmt = $pdo->prepare("
    SELECT c.*, COUNT(e.id) AS total_eleves 
    FROM classes c
    LEFT JOIN eleves e ON e.classe_id = c.id
    WHERE c.ecole_id = ?
    GROUP BY c.id
    ORDER BY c.niveau, c.nom
");
$stmt->execute([$_SESSION['ecole_id']]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wassit — الأقسام</title>
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
        input, select {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 12px;
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
            width: 100%;
        }
        .btn-primary { background: #1a73e8; color: white; }
        .btn-primary:hover { background: #1558b0; }
        .btn-danger {
            background: #fdecea;
            color: #c0392b;
            padding: 6px 14px;
            width: auto;
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
            background: #e8f0fe;
            color: #1a73e8;
            font-weight: bold;
        }
        .empty { text-align: center; color: #aaa; padding: 20px; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">

    <!-- إضافة قسم -->
    <div class="card">
        <h2>🏫 إضافة قسم جديد</h2>

        <?php if ($success): ?>
            <div class="success"><?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="add">
            <input type="text" name="nom" placeholder="اسم القسم — مثال: باك علوم إعلامية أ" required>
            <select name="niveau" required>
                <option value="">— اختر المستوى —</option>
                <option value="السابعة">السابعة</option>
                <option value="الثامنة">الثامنة</option>
                <option value="التاسعة">التاسعة</option>
                <option value="أولى ثانوي">أولى ثانوي</option>
                <option value="ثانية ثانوي">ثانية ثانوي</option>
                <option value="ثالثة ثانوي">ثالثة ثانوي</option>
                <option value="رابعة ثانوي">رابعة ثانوي</option>
                <option value="باكالوريا">باكالوريا</option>
            </select>
            <button type="submit" class="btn btn-primary">إضافة القسم</button>
        </form>
    </div>

    <!-- قائمة الأقسام -->
    <div class="card">
        <h2>📋 قائمة الأقسام</h2>
        <?php if (empty($classes)): ?>
            <p class="empty">لا توجد أقسام بعد</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>اسم القسم</th>
                        <th>المستوى</th>
                        <th>عدد الطلبة</th>
                        <th>حذف</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($classes as $classe): ?>
                        <tr>
                            <td><?= htmlspecialchars($classe['nom']) ?></td>
                            <td><?= htmlspecialchars($classe['niveau']) ?></td>
                            <td><span class="badge"><?= $classe['total_eleves'] ?> طالب</span></td>
                            <td>
                                <?php if ($classe['total_eleves'] == 0): ?>
                                    <form method="POST" style="display:inline"
                                        onsubmit="return confirm('حذف هذا القسم؟')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="classe_id" value="<?= $classe['id'] ?>">
                                        <button type="submit" class="btn btn-danger">حذف 🗑️</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color:#aaa; font-size:13px;">فيه طلبة</span>
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