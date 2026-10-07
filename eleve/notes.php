<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'eleves') {
    header('Location: ../auth/login.php');
    exit();
}

$eleve_id = $_SESSION['user_id'];

// كل الأعداد المؤكدة
$stmt = $pdo->prepare("
    SELECT n.note, ep.titre, ep.matiere, ep.note_max, ep.date_epreuve, ep.trimestre
    FROM notes n
    JOIN epreuves ep ON n.epreuve_id = ep.id
    WHERE n.eleve_id = ? AND n.statut = 'confirme'
    ORDER BY ep.trimestre, ep.matiere, ep.date_epreuve
");
$stmt->execute([$eleve_id]);
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// تجميع حسب الفصل ثم المادة
$par_trimestre = [];
foreach ($notes as $n) {
    $par_trimestre[$n['trimestre']][$n['matiere']][] = $n;
}

// المعدلات
$stmt = $pdo->prepare("
    SELECT trimestre, moyenne 
    FROM moyennes 
    WHERE eleve_id = ? AND statut = 'confirme'
");
$stmt->execute([$eleve_id]);
$moyennes = $stmt->fetchAll(PDO::FETCH_ASSOC);
$moyennes_map = [];
foreach ($moyennes as $m) {
    $moyennes_map[$m['trimestre']] = $m['moyenne'];
}

$noms_trimestre = [1 => 'الفصل الأول', 2 => 'الفصل الثاني', 3 => 'الفصل الثالث'];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wassit — أعدادي</title>
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
        .trimestre-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e8f0fe;
        }
        .trimestre-header h2 { color: #333; font-size: 18px; }
        .moyenne-badge {
            background: #1a73e8;
            color: white;
            padding: 8px 18px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
        }
        .moyenne-badge.low { background: #c0392b; }
        .moyenne-badge.mid { background: #e65100; }
        .moyenne-badge.high { background: #2e7d32; }
        .moyenne-badge.empty { background: #aaa; }

        h3 {
            color: #1a73e8;
            font-size: 15px;
            margin-bottom: 12px;
            margin-top: 20px;
        }
        h3:first-of-type { margin-top: 0; }

        .notes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 10px;
            margin-bottom: 8px;
        }
        .note-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 14px;
            text-align: center;
        }
        .note-card .titre { font-size: 12px; color: #888; margin-bottom: 6px; }
        .note-card .val { font-size: 26px; font-weight: bold; }
        .note-card .max { font-size: 12px; color: #aaa; }
        .note-card .date { font-size: 11px; color: #aaa; margin-top: 4px; }
        .note-low { color: #c0392b; }
        .note-mid { color: #e65100; }
        .note-high { color: #2e7d32; }

        .empty { text-align: center; color: #aaa; padding: 24px; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">

    <?php if (empty($notes)): ?>
        <div class="card">
            <p class="empty">لا توجد أعداد مؤكدة بعد 📭</p>
        </div>
    <?php else: ?>
        <?php for ($t = 1; $t <= 3; $t++): ?>
            <?php if (isset($par_trimestre[$t])): ?>
                <div class="card">
                    <div class="trimestre-header">
                        <h2>📚 <?= $noms_trimestre[$t] ?></h2>
                        <?php if (isset($moyennes_map[$t])): ?>
                            <?php
                            $val = $moyennes_map[$t];
                            $cls = $val < 10 ? 'low' : ($val < 12 ? 'mid' : 'high');
                            ?>
                            <div class="moyenne-badge <?= $cls ?>">
                                المعدل: <?= number_format($val, 2) ?>/20
                            </div>
                        <?php else: ?>
                            <div class="moyenne-badge empty">المعدل: —</div>
                        <?php endif; ?>
                    </div>

                    <?php foreach ($par_trimestre[$t] as $matiere => $liste): ?>
                        <h3>📖 <?= htmlspecialchars($matiere) ?></h3>
                        <div class="notes-grid">
                            <?php foreach ($liste as $n): ?>
                                <?php
                                $pct = ($n['note'] / $n['note_max']) * 100;
                                $cls = $pct < 40 ? 'note-low' : ($pct < 60 ? 'note-mid' : 'note-high');
                                ?>
                                <div class="note-card">
                                    <div class="titre"><?= htmlspecialchars($n['titre']) ?></div>
                                    <div class="val <?= $cls ?>"><?= number_format($n['note'], 2) ?></div>
                                    <div class="max">من <?= $n['note_max'] ?></div>
                                    <div class="date">📅 <?= $n['date_epreuve'] ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endfor; ?>
    <?php endif; ?>

</div>
</body>
</html>