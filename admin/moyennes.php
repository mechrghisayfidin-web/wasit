<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$success = '';

// تأكيد المعدلات لقسم/فصل
if (isset($_POST['action']) && $_POST['action'] === 'confirmer_moyennes') {
    $classe_id = $_POST['classe_id'];
    $trimestre = $_POST['trimestre'];

    // جلب طلبة القسم
    $stmt = $pdo->prepare("SELECT id FROM eleves WHERE classe_id = ?");
    $stmt->execute([$classe_id]);
    $eleves = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($eleves as $eleve) {
        // حساب المعدل من الأعداد المؤكدة فقط
        $stmt = $pdo->prepare("
            SELECT AVG(n.note / ep.note_max * 20) AS moyenne
            FROM notes n
            JOIN epreuves ep ON n.epreuve_id = ep.id
            WHERE n.eleve_id = ? AND ep.trimestre = ? 
            AND n.statut = 'confirme' AND n.note IS NOT NULL
        ");
        $stmt->execute([$eleve['id'], $trimestre]);
        $moyenne = $stmt->fetchColumn();

        if ($moyenne !== null) {
            // تحقق إذا موجود مسبقاً
            $stmt = $pdo->prepare("
                SELECT id FROM moyennes WHERE eleve_id = ? AND trimestre = ?
            ");
            $stmt->execute([$eleve['id'], $trimestre]);
            $existe = $stmt->fetch();

            if ($existe) {
                $stmt = $pdo->prepare("
                    UPDATE moyennes SET moyenne = ?, statut = 'confirme', date_calcul = NOW() 
                    WHERE eleve_id = ? AND trimestre = ?
                ");
                $stmt->execute([$moyenne, $eleve['id'], $trimestre]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO moyennes (eleve_id, trimestre, moyenne, statut, date_calcul)
                    VALUES (?, ?, ?, 'confirme', NOW())
                ");
                $stmt->execute([$eleve['id'], $trimestre, $moyenne]);
            }
        }
    }

    $success = "تم حساب وتأكيد المعدلات ✅ — تأكد من صحتها قبل الإرسال للأولياء";
}

// جلب الأقسام
$stmt = $pdo->prepare("SELECT * FROM classes WHERE ecole_id = ? ORDER BY nom");
$stmt->execute([$_SESSION['ecole_id']]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// عرض معدلات قسم/فصل محدد
$classe_selectionnee = $_GET['classe_id'] ?? null;
$trimestre_selectionne = $_GET['trimestre'] ?? null;
$resultats = [];

if ($classe_selectionnee && $trimestre_selectionne) {
    $stmt = $pdo->prepare("
        SELECT e.id, e.nom,
               m.moyenne, m.statut, m.date_calcul,
               (SELECT COUNT(*) FROM notes n 
                JOIN epreuves ep ON n.epreuve_id = ep.id 
                WHERE n.eleve_id = e.id AND ep.trimestre = ? AND n.statut = 'confirme') AS total_notes
        FROM eleves e
        LEFT JOIN moyennes m ON m.eleve_id = e.id AND m.trimestre = ?
        WHERE e.classe_id = ?
        ORDER BY e.nom
    ");
    $stmt->execute([$trimestre_selectionne, $trimestre_selectionne, $classe_selectionnee]);
    $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wassit — المعدلات</title>
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
        .filter-bar {
            display: flex;
            gap: 12px;
            align-items: flex-end;
            margin-bottom: 20px;
        }
        select {
            padding: 11px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
            font-family: inherit;
            outline: none;
            flex: 1;
        }
        select:focus { border-color: #1a73e8; }
        .btn {
            padding: 11px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            font-family: inherit;
            white-space: nowrap;
        }
        .btn-primary { background: #1a73e8; color: white; }
        .btn-primary:hover { background: #1558b0; }
        .btn-confirm {
            background: #2e7d32;
            color: white;
            width: 100%;
            padding: 12px;
            font-size: 15px;
            margin-top: 16px;
        }
        .btn-confirm:hover { background: #256428; }
        .success {
            background: #e6f4ea;
            color: #2e7d32;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 16px;
            text-align: center;
        }
        .warning {
            background: #fff3e0;
            color: #e65100;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
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
        .moyenne-val {
            font-size: 20px;
            font-weight: bold;
        }
        .note-low { color: #c0392b; }
        .note-mid { color: #e65100; }
        .note-high { color: #2e7d32; }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-ok { background: #e6f4ea; color: #2e7d32; }
        .badge-wait { background: #fff3e0; color: #e65100; }
        .badge-empty { background: #f0f0f0; color: #999; }
        .empty { text-align: center; color: #aaa; padding: 24px; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">

    <?php if ($success): ?>
        <div class="success"><?= $success ?></div>
    <?php endif; ?>

    <!-- فلتر -->
    <div class="card">
        <h2>📊 معدلات الطلبة</h2>
        <form method="GET">
            <div class="filter-bar">
                <select name="classe_id" required>
                    <option value="">— اختر القسم —</option>
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?= $classe['id'] ?>"
                            <?= $classe_selectionnee == $classe['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($classe['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="trimestre" required>
                    <option value="">— اختر الفصل —</option>
                    <option value="1" <?= $trimestre_selectionne == 1 ? 'selected' : '' ?>>الفصل الأول</option>
                    <option value="2" <?= $trimestre_selectionne == 2 ? 'selected' : '' ?>>الفصل الثاني</option>
                    <option value="3" <?= $trimestre_selectionne == 3 ? 'selected' : '' ?>>الفصل الثالث</option>
                </select>
                <button type="submit" class="btn btn-primary">عرض</button>
            </div>
        </form>

        <?php if ($classe_selectionnee && $trimestre_selectionne): ?>
            <?php if (empty($resultats)): ?>
                <p class="empty">لا يوجد طلبة في هذا القسم</p>
            <?php else: ?>
                <div class="warning">
                    ⚠️ راجع المعدلات بدقة قبل التأكيد. بعد التأكيد سترسل المعدلات للأولياء والأساتذة المعنيين.
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>الطالب</th>
                            <th>عدد الفروض المعتمدة</th>
                            <th>المعدل</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultats as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['nom']) ?></td>
                                <td><?= $r['total_notes'] ?></td>
                                <td>
                                    <?php if ($r['moyenne'] !== null): ?>
                                        <?php
                                        $cls = $r['moyenne'] < 10 ? 'note-low' : ($r['moyenne'] < 12 ? 'note-mid' : 'note-high');
                                        ?>
                                        <span class="moyenne-val <?= $cls ?>">
                                            <?= number_format($r['moyenne'], 2) ?>/20
                                        </span>
                                    <?php else: ?>
                                        <span style="color:#aaa">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($r['total_notes'] == 0): ?>
                                        <span class="badge badge-empty">لا توجد أعداد</span>
                                    <?php elseif ($r['statut'] === 'confirme'): ?>
                                        <span class="badge badge-ok">مؤكد ✅</span>
                                        <?php if ($r['date_calcul']): ?>
                                            <div style="font-size:11px; color:#aaa; margin-top:4px;">
                                                🕐 <?= $r['date_calcul'] ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge badge-wait">غير محسوب</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <form method="POST">
                    <input type="hidden" name="action" value="confirmer_moyennes">
                    <input type="hidden" name="classe_id" value="<?= $classe_selectionnee ?>">
                    <input type="hidden" name="trimestre" value="<?= $trimestre_selectionne ?>">
                    <button type="submit" class="btn-confirm"
                        onclick="return confirm('احسب وأكد المعدلات؟ تأكد من مراجعتها بعد ذلك.')">
                        🔄 حساب وتأكيد المعدلات
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>

</div>
</body>
</html>