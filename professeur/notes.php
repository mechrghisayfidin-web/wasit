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

// إضافة فرض جديد
if (isset($_POST['action']) && $_POST['action'] === 'add_epreuve') {
    $classe_id = $_POST['classe_id'];
    $titre = trim($_POST['titre']);
    $date_epreuve = $_POST['date_epreuve'];
    $note_max = $_POST['note_max'];

    if (!empty($classe_id) && !empty($titre) && !empty($date_epreuve)) {
        $stmt = $pdo->prepare("
    INSERT INTO epreuves (classe_id, professeur_id, matiere, titre, date_epreuve, note_max, trimestre)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([$classe_id, $prof_id, $_SESSION['matiere'], $titre, $date_epreuve, $note_max, $_POST['trimestre']]);
$epreuve_id = $pdo->lastInsertId();
        // إنشاء أعداد فارغة لكل طلبة القسم
        $stmt = $pdo->prepare("SELECT id FROM eleves WHERE classe_id = ?");
        $stmt->execute([$classe_id]);
        $eleves = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($eleves as $eleve) {  // ✅ صح
            $stmt2 = $pdo->prepare("INSERT INTO notes (epreuve_id, eleve_id, note) VALUES (?, ?, NULL)");
            $stmt2->execute([$epreuve_id, $eleve['id']]);
        }
        $success = "تم إنشاء الفرض بنجاح ✅ — أدخل الأعداد الآن";
    } else {
        $error = "املأ كل الحقول!";
    }
}

// حفظ الأعداد
if (isset($_POST['action']) && $_POST['action'] === 'save_notes') {
    $epreuve_id = $_POST['epreuve_id'];
    $notes = $_POST['notes'];

    foreach ($notes as $note_id => $valeur) {
        $valeur = $valeur === '' ? null : floatval($valeur);
        $stmt = $pdo->prepare("UPDATE notes SET note = ?, statut = 'en_attente' WHERE id = ? AND epreuve_id = ?");
        $stmt->execute([$valeur, $note_id, $epreuve_id]);
    }
    $success = "تم حفظ الأعداد ✅ — في انتظار تأكيد المدير";
}

// جلب الأقسام المرتبطة بالأستاذ
$stmt = $pdo->prepare("
    SELECT DISTINCT c.* FROM classes c
    JOIN epreuves e ON e.classe_id = c.id
    WHERE e.professeur_id = ? AND c.ecole_id = ?
    UNION
    SELECT c.* FROM classes c
    WHERE c.ecole_id = ?
");
$stmt->execute([$prof_id, $_SESSION['ecole_id'], $_SESSION['ecole_id']]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب الفروض
$stmt = $pdo->prepare("
    SELECT e.*, c.nom AS classe_nom,
           COUNT(n.id) AS total_notes,
           SUM(CASE WHEN n.note IS NOT NULL THEN 1 ELSE 0 END) AS notes_saisies
    FROM epreuves e
    JOIN classes c ON e.classe_id = c.id
    LEFT JOIN notes n ON n.epreuve_id = e.id
    WHERE e.professeur_id = ?
    GROUP BY e.id
    ORDER BY e.date_epreuve DESC
");
$stmt->execute([$prof_id]);
$epreuves = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب فرض محدد لإدخال الأعداد
$epreuve_active = null;
$notes_eleves = [];
if (isset($_GET['epreuve_id'])) {
    $stmt = $pdo->prepare("SELECT e.*, c.nom AS classe_nom FROM epreuves e JOIN classes c ON e.classe_id = c.id WHERE e.id = ? AND e.professeur_id = ?");
    $stmt->execute([$_GET['epreuve_id'], $prof_id]);
    $epreuve_active = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($epreuve_active) {
        $stmt = $pdo->prepare("
            SELECT n.*, el.nom AS eleve_nom
            FROM notes n
            JOIN eleves el ON n.eleve_id = el.id
            WHERE n.epreuve_id = ?
            ORDER BY el.nom
        ");
        $stmt->execute([$_GET['epreuve_id']]);
        $notes_eleves = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wassit — الأعداد</title>
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
        .btn-sm {
            padding: 6px 14px;
            font-size: 13px;
            background: #e8f0fe;
            color: #1a73e8;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-sm:hover { background: #d2e3fc; }
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
        .badge-wait { background: #fff3e0; color: #e65100; }
        .badge-ok { background: #e6f4ea; color: #2e7d32; }
        .note-input {
            width: 80px;
            padding: 8px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 15px;
        }
        .note-input:focus { border-color: #1a73e8; outline: none; }
        .empty { text-align: center; color: #aaa; padding: 20px; }
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

    <?php if ($success): ?>
        <div class="success"><?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>

    <!-- إدخال أعداد فرض محدد -->
    <?php if ($epreuve_active): ?>
    <div class="card">
        <h2>✏️ إدخال أعداد — <?= htmlspecialchars($epreuve_active['titre']) ?> 
            (<?= htmlspecialchars($epreuve_active['classe_nom']) ?>)
        </h2>
        <form method="POST">
            <input type="hidden" name="action" value="save_notes">
            <input type="hidden" name="epreuve_id" value="<?= $epreuve_active['id'] ?>">
            <table>
                <thead>
                    <tr>
                        <th>الطالب</th>
                        <th>العدد / <?= $epreuve_active['note_max'] ?></th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notes_eleves as $n): ?>
                        <tr>
                        <td><?= htmlspecialchars($n['eleve_nom']) ?></td>
                            <td>
                                <input type="number" name="notes[<?= $n['id'] ?>]"
                                    class="note-input"
                                    min="0" max="<?= $epreuve_active['note_max'] ?>"
                                    step="0.25"
                                    value="<?= $n['note'] ?? '' ?>"
                                    placeholder="—">
                            </td>
                            <td>
                                <?php if ($n['statut'] === 'confirme'): ?>
                                    <span class="badge badge-ok">مؤكد ✅</span>
                                <?php else: ?>
                                    <span class="badge badge-wait">في الانتظار ⏳</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <br>
            <button type="submit" class="btn btn-primary">💾 حفظ الأعداد</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- إضافة فرض جديد -->
    <div class="card">
        <h2>➕ إضافة فرض جديد</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add_epreuve">
            <div class="form-grid">
                <div>
                    <label>القسم</label>
                    <select name="classe_id" required>
                        <option value="">— اختر القسم —</option>
                        <?php foreach ($classes as $classe): ?>
                            <option value="<?= $classe['id'] ?>">
                                <?= htmlspecialchars($classe['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>عنوان الفرض</label>
                    <input type="text" name="titre" placeholder="مثال: فرض 1 — الفصل الأول" required>
                </div>
                <div>
                    <label>التاريخ</label>
                    <input type="date" name="date_epreuve" required>
                </div>
                <div>
                    <label>الفصل</label>
                    <select name="trimestre" required>
                        <option value="1">الفصل الأول</option>
                        <option value="2">الفصل الثاني</option>
                        <option value="3">الفصل الثالث</option>
                    </select>
                </div>
                <div>
                    <label>العدد الأقصى</label>
                    <select name="note_max">
                        <option value="20">20</option>
                        <option value="10">10</option>
                    </select>
                </div>
                <div class="full">
                    <button type="submit" class="btn btn-primary">إضافة الفرض</button>
                </div>
               
            </div>
        </form>
    </div>

    <!-- قائمة الفروض -->
    <div class="card">
        <h2>📋 فروضي</h2>
        <?php if (empty($epreuves)): ?>
            <p class="empty">لا توجد فروض بعد</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>الفرض</th>
                        <th>القسم</th>
                        <th>التاريخ</th>
                        <th>الأعداد</th>
                        <th>إدخال</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($epreuves as $ep): ?>
                        <tr>
                            <td><?= htmlspecialchars($ep['titre']) ?></td>
                            <td><?= htmlspecialchars($ep['classe_nom']) ?></td>
                            <td><?= $ep['date_epreuve'] ?></td>
                            <td>
                                <span class="badge badge-<?= $ep['notes_saisies'] == $ep['total_notes'] ? 'ok' : 'wait' ?>">
                                    <?= $ep['notes_saisies'] ?>/<?= $ep['total_notes'] ?>
                                </span>
                            </td>
                            <td>
                                <a href="?epreuve_id=<?= $ep['id'] ?>" class="btn-sm">✏️ إدخال</a>
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