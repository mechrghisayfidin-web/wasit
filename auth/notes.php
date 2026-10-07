<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$success = '';
$error = '';

// تأكيد أعداد فرض كامل
if (isset($_POST['action']) && $_POST['action'] === 'confirmer') {
    $epreuve_id = $_POST['epreuve_id'];
    $stmt = $pdo->prepare("
        UPDATE notes SET statut = 'confirme' 
        WHERE epreuve_id = ? AND note IS NOT NULL
    ");
    $stmt->execute([$epreuve_id]);
    $success = "تم تأكيد الأعداد ✅ — سيصلها الأولياء الآن";
}

// جلب كل الفروض
$stmt = $pdo->prepare("
    SELECT e.*, c.nom AS classe_nom, u.nom AS prof_nom,
           COUNT(n.id) AS total_notes,
           SUM(CASE WHEN n.note IS NOT NULL THEN 1 ELSE 0 END) AS notes_saisies,
           SUM(CASE WHEN n.statut = 'confirme' THEN 1 ELSE 0 END) AS notes_confirmees
    FROM epreuves e
    JOIN classes c ON e.classe_id = c.id
    JOIN utilisateurs u ON e.professeur_id = u.id
    LEFT JOIN notes n ON n.epreuve_id = e.id
    WHERE c.ecole_id = ?
    GROUP BY e.id
    ORDER BY e.date_epreuve DESC
");
$stmt->execute([$_SESSION['ecole_id']]);
$epreuves = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب أعداد فرض محدد
$epreuve_active = null;
$notes_detail = [];
if (isset($_GET['epreuve_id'])) {
    $stmt = $pdo->prepare("
        SELECT e.*, c.nom AS classe_nom, u.nom AS prof_nom
        FROM epreuves e
        JOIN classes c ON e.classe_id = c.id
        JOIN utilisateurs u ON e.professeur_id = u.id
        WHERE e.id = ?
    ");
    $stmt->execute([$_GET['epreuve_id']]);
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
        $notes_detail = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wassit — تأكيد الأعداد</title>
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
        h3 { color: #1a73e8; font-size: 16px; margin-bottom: 16px; }
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
        .badge-partial { background: #e8f0fe; color: #1a73e8; }
        .btn {
            padding: 8px 18px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
        }
        .btn-confirm { background: #1a73e8; color: white; }
        .btn-confirm:hover { background: #1558b0; }
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
        .note-cell {
            font-size: 16px;
            font-weight: bold;
            color: #333;
        }
        .note-low { color: #c0392b; }
        .note-mid { color: #e65100; }
        .note-high { color: #2e7d32; }
        .empty { text-align: center; color: #aaa; padding: 20px; }
        .info-bar {
            background: #e8f0fe;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #1a73e8;
        }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">

    <?php if ($success): ?>
        <div class="success"><?= $success ?></div>
    <?php endif; ?>

    <!-- تفصيل فرض محدد -->
    <?php if ($epreuve_active): ?>
    <div class="card">
        <h2>🔍 مراجعة أعداد — <?= htmlspecialchars($epreuve_active['titre']) ?></h2>
        <div class="info-bar">
            📚 <?= htmlspecialchars($epreuve_active['classe_nom']) ?> &nbsp;|&nbsp;
            👨‍🏫 <?= htmlspecialchars($epreuve_active['prof_nom']) ?> &nbsp;|&nbsp;
            📅 <?= $epreuve_active['date_epreuve'] ?> &nbsp;|&nbsp;
            🎯 من <?= $epreuve_active['note_max'] ?>
        </div>

        <table>
            <thead>
                <tr>
                    <th>الطالب</th>
                    <th>العدد</th>
                    <th>الحالة</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($notes_detail as $n): ?>
                    <tr>
                        <td><?= htmlspecialchars($n['eleve_nom']) ?></td>
                        <td>
                            <?php if ($n['note'] !== null): ?>
                                <?php
                                $pct = ($n['note'] / $epreuve_active['note_max']) * 100;
                                $cls = $pct < 40 ? 'note-low' : ($pct < 60 ? 'note-mid' : 'note-high');
                                ?>
                                <span class="note-cell <?= $cls ?>">
                                    <?= number_format($n['note'], 2) ?>
                                </span>
                            <?php else: ?>
                                <span style="color:#aaa">—</span>
                            <?php endif; ?>
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
        <?php
        $all_confirmed = array_filter($notes_detail, fn($n) => $n['statut'] !== 'confirme' && $n['note'] !== null);
        if (!empty($all_confirmed)):
        ?>
        <form method="POST">
            <input type="hidden" name="action" value="confirmer">
            <input type="hidden" name="epreuve_id" value="<?= $epreuve_active['id'] ?>">
            <button type="submit" class="btn btn-confirm"
                onclick="return confirm('تأكيد هذه الأعداد وإرسالها للأولياء؟')">
                ✅ تأكيد وإرسال للأولياء
            </button>
        </form>
        <?php else: ?>
            <span class="badge badge-ok">كل الأعداد مؤكدة ✅</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- قائمة الفروض -->
    <div class="card">
        <h2>📊 كل الفروض</h2>
        <?php if (empty($epreuves)): ?>
            <p class="empty">لا توجد فروض بعد</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>الفرض</th>
                        <th>القسم</th>
                        <th>الأستاذ</th>
                        <th>التاريخ</th>
                        <th>الحالة</th>
                        <th>مراجعة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($epreuves as $ep): ?>
                        <tr>
                            <td><?= htmlspecialchars($ep['titre']) ?></td>
                            <td><?= htmlspecialchars($ep['classe_nom']) ?></td>
                            <td><?= htmlspecialchars($ep['prof_nom']) ?></td>
                            <td><?= $ep['date_epreuve'] ?></td>
                            <td>
                                <?php if ($ep['notes_confirmees'] == $ep['total_notes'] && $ep['total_notes'] > 0): ?>
                                    <span class="badge badge-ok">مؤكد كامل ✅</span>
                                <?php elseif ($ep['notes_saisies'] == 0): ?>
                                    <span class="badge badge-wait">لم تُدخل ⏳</span>
                                <?php else: ?>
                                    <span class="badge badge-partial">في الانتظار 🔍</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?epreuve_id=<?= $ep['id'] ?>" class="btn-sm">🔍 مراجعة</a>
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