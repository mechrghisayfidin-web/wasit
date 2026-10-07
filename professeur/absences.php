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

// تسجيل غياب
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $eleve_id = $_POST['eleve_id'];
    $date_absence = $_POST['date_absence'];
    $heure = trim($_POST['heure']);

    if (!empty($eleve_id) && !empty($date_absence) && !empty($heure)) {
        // تحقق ما تسجلش نفس الغياب مرتين
        $stmt = $pdo->prepare("
            SELECT id FROM absences 
            WHERE eleve_id = ? AND professeur_id = ? AND date_absence = ? AND heure = ?
        ");
        $stmt->execute([$eleve_id, $prof_id, $date_absence, $heure]);
        if ($stmt->fetch()) {
            $error = "هذا الغياب مسجل مسبقاً!";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO absences (eleve_id, professeur_id, date_absence, heure, matiere)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$eleve_id, $prof_id, $date_absence, $heure, $_SESSION['matiere']]);
            $success = "تم تسجيل الغياب ✅";
        }
    } else {
        $error = "املأ كل الحقول!";
    }
}

// تبرير غياب
if (isset($_POST['action']) && $_POST['action'] === 'justifier') {
    $abs_id = $_POST['abs_id'];
    $stmt = $pdo->prepare("UPDATE absences SET justifie = 'oui' WHERE id = ?");
    $stmt->execute([$abs_id]);
    $success = "تم تبرير الغياب ✅";
}

// جلب الأقسام
$stmt = $pdo->prepare("SELECT * FROM classes WHERE ecole_id = ? ORDER BY nom");
$stmt->execute([$_SESSION['ecole_id']]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب الطلبة حسب القسم المختار
$eleves = [];
$classe_selectionnee = $_GET['classe_id'] ?? null;
if ($classe_selectionnee) {
    $stmt = $pdo->prepare("SELECT * FROM eleves WHERE classe_id = ? ORDER BY nom");
    $stmt->execute([$classe_selectionnee]);
    $eleves = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// جلب الغيابات
$stmt = $pdo->prepare("
    SELECT a.*, e.nom AS eleve_nom, c.nom AS classe_nom
    FROM absences a
    JOIN eleves e ON a.eleve_id = e.id
    JOIN classes c ON e.classe_id = c.id
    WHERE a.professeur_id = ?
    ORDER BY a.date_absence DESC, a.heure
");
$stmt->execute([$prof_id]);
$absences = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wassit — الغيابات</title>
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
        label {
            display: block;
            font-size: 13px;
            color: #555;
            margin-bottom: 4px;
            font-weight: bold;
        }
        select, input {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
            font-family: inherit;
            outline: none;
            margin-bottom: 14px;
        }
        select:focus, input:focus { border-color: #1a73e8; }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .form-grid .full { grid-column: 1 / -1; }
        .btn {
            padding: 11px 24px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            cursor: pointer;
            font-family: inherit;
        }
        .btn-primary { background: #1a73e8; color: white; width: 100%; }
        .btn-primary:hover { background: #1558b0; }
        .btn-justify {
            background: #e6f4ea;
            color: #2e7d32;
            padding: 5px 12px;
            font-size: 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        .btn-justify:hover { background: #c8e6c9; }
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
        .badge-abs { background: #fdecea; color: #c0392b; }
        .badge-just { background: #e6f4ea; color: #2e7d32; }
        .empty { text-align: center; color: #aaa; padding: 24px; }
        .filter-bar {
            display: flex;
            gap: 12px;
            align-items: flex-end;
            margin-bottom: 20px;
        }
        .filter-bar select {
            margin-bottom: 0;
            flex: 1;
        }
        .btn-filter {
            padding: 11px 20px;
            background: #1a73e8;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            white-space: nowrap;
        }
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

    <!-- تسجيل غياب -->
    <div class="card">
        <h2>📝 تسجيل غياب</h2>

        <!-- فلتر القسم -->
        <form method="GET" style="margin-bottom: 20px;">
            <div class="filter-bar">
                <select name="classe_id" onchange="this.form.submit()">
                    <option value="">— اختر القسم أولاً —</option>
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?= $classe['id'] ?>"
                            <?= $classe_selectionnee == $classe['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($classe['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <?php if ($classe_selectionnee && !empty($eleves)): ?>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-grid">
                <div>
                    <label>الطالب</label>
                    <select name="eleve_id" required>
                        <option value="">— اختر الطالب —</option>
                        <?php foreach ($eleves as $eleve): ?>
                            <option value="<?= $eleve['id'] ?>">
                                <?= htmlspecialchars($eleve['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>التاريخ</label>
                    <input type="date" name="date_absence"
                        value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="full">
                    <label>الحصة</label>
                    <select name="heure" required>
                        <option value="">— اختر الحصة —</option>
                        <option value="08:00 - 09:00">08:00 - 09:00</option>
                        <option value="09:00 - 10:00">09:00 - 10:00</option>
                        <option value="10:00 - 11:00">10:00 - 11:00</option>
                        <option value="11:00 - 12:00">11:00 - 12:00</option>
                        <option value="14:00 - 15:00">14:00 - 15:00</option>
                        <option value="15:00 - 16:00">15:00 - 16:00</option>
                        <option value="16:00 - 17:00">16:00 - 17:00</option>
                    </select>
                </div>
                <div class="full">
                    <button type="submit" class="btn btn-primary">تسجيل الغياب</button>
                </div>
            </div>
        </form>
        <?php elseif ($classe_selectionnee && empty($eleves)): ?>
            <p class="empty">لا يوجد طلبة في هذا القسم</p>
        <?php endif; ?>
    </div>

    <!-- قائمة الغيابات -->
    <div class="card">
        <h2>📋 سجل الغيابات</h2>
        <?php if (empty($absences)): ?>
            <p class="empty">لا توجد غيابات مسجلة بعد</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>الطالب</th>
                        <th>القسم</th>
                        <th>التاريخ</th>
                        <th>الحصة</th>
                        <th>الحالة</th>
                        <th>تبرير</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($absences as $abs): ?>
                        <tr>
                            <td><?= htmlspecialchars($abs['eleve_nom']) ?></td>
                            <td><?= htmlspecialchars($abs['classe_nom']) ?></td>
                            <td><?= $abs['date_absence'] ?></td>
                            <td><?= htmlspecialchars($abs['heure']) ?></td>
                            <td>
                                <?php if ($abs['justifie'] === 'oui'): ?>
                                    <span class="badge badge-just">مبرر ✅</span>
                                <?php else: ?>
                                    <span class="badge badge-abs">غير مبرر ❌</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($abs['justifie'] === 'non'): ?>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="action" value="justifier">
                                        <input type="hidden" name="abs_id" value="<?= $abs['id'] ?>">
                                        <button type="submit" class="btn-justify">تبرير ✅</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color:#aaa; font-size:13px;">—</span>
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