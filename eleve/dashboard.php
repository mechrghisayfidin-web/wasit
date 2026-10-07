<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'eleves') {
    header('Location: ../auth/login.php');
    exit();
}

$eleve_id = $_SESSION['user_id'];

// معلومات الطالب
$stmt = $pdo->prepare("
    SELECT e.*, c.nom AS classe_nom, c.niveau
    FROM eleves e
    LEFT JOIN classes c ON e.classe_id = c.id
    WHERE e.id = ?
");
$stmt->execute([$eleve_id]);
$eleves = $stmt->fetch(PDO::FETCH_ASSOC);

// معدلاته المؤكدة
$stmt = $pdo->prepare("
    SELECT trimestre, moyenne 
    FROM moyennes 
    WHERE eleve_id = ? AND statut = 'confirme'
    ORDER BY trimestre
");
$stmt->execute([$eleve_id]);
$moyennes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$moyennes_map = [];
foreach ($moyennes as $m) {
    $moyennes_map[$m['trimestre']] = $m['moyenne'];
}

// آخر الأعداد المؤكدة
$stmt = $pdo->prepare("
    SELECT n.note, ep.titre, ep.matiere, ep.note_max, ep.date_epreuve
    FROM notes n
    JOIN epreuves ep ON n.epreuve_id = ep.id
    WHERE n.eleve_id = ? AND n.statut = 'confirme'
    ORDER BY ep.date_epreuve DESC
    LIMIT 5
");
$stmt->execute([$eleve_id]);
$dernieres_notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wassit — لوحتي</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f4f8; }
        .welcome {
            background: linear-gradient(135deg, #1a73e8, #0d47a1);
            color: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }
        .welcome h1 { font-size: 22px; margin-bottom: 4px; }
        .welcome p { opacity: 0.85; font-size: 14px; }
        .card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 24px;
        }
        h2 { color: #333; margin-bottom: 20px; font-size: 18px; }

        /* المعدلات */
        .moyennes-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }
        .moy-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
        .moy-card .trimestre { font-size: 13px; color: #888; margin-bottom: 8px; }
        .moy-card .val { font-size: 32px; font-weight: bold; }
        .moy-card .max { font-size: 13px; color: #aaa; }
        .moy-card.empty .val { color: #ccc; font-size: 18px; }
        .note-low { color: #c0392b; }
        .note-mid { color: #e65100; }
        .note-high { color: #2e7d32; }

        /* آخر الأعداد */
        .note-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #f0f0f0;
            padding: 12px 0;
        }
        .note-item:last-child { border-bottom: none; }
        .note-info .titre { font-weight: bold; color: #333; font-size: 14px; }
        .note-info .matiere { color: #888; font-size: 12px; }
        .note-val { font-size: 20px; font-weight: bold; }
        .empty { text-align: center; color: #aaa; padding: 24px; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">

<?php
// جلب الإعلانات لقسم الطالب
$stmt = $pdo->prepare("
    SELECT a.*, u.nom AS prof_nom
    FROM annonces a
    JOIN utilisateurs u ON a.professeur_id = u.id
    WHERE a.classe_id = ?
    ORDER BY a.date_publication DESC
    LIMIT 5
");
$stmt->execute([$eleves['classe_id']]);
$annonces = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php if (!empty($annonces)): ?>
<div class="card">
    <h2>📢 آخر الإعلانات</h2>
    <?php foreach ($annonces as $a): ?>
        <div style="border-bottom:1px solid #f0f0f0; padding:10px 0;">
            <div style="font-size:12px; color:#1a73e8; font-weight:bold; margin-bottom:4px;">
                ✍️ <?= htmlspecialchars($a['prof_nom']) ?>
            </div>
            <div style="font-weight:bold; font-size:14px; color:#333; margin-bottom:4px;">
                <?= htmlspecialchars($a['titre']) ?>
            </div>
            <div style="font-size:13px; color:#666; line-height:1.5;">
                <?= htmlspecialchars($a['contenu']) ?>
            </div>
            <div style="font-size:11px; color:#aaa; margin-top:4px;">
                🕐 <?= $a['date_publication'] ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>


    <!-- ترحيب -->
    <div class="welcome">
        <h1>أهلاً، <?= htmlspecialchars($eleve['nom']) ?> 👋</h1>
        <p>📚 <?= htmlspecialchars($eleve['classe_nom'] ?? '—') ?> — <?= htmlspecialchars($eleve['niveau'] ?? '') ?></p>
    </div>

    <!-- المعدلات -->
    <div class="card">
        <h2>📊 معدلاتي</h2>
        <div class="moyennes-grid">
            <?php for ($t = 1; $t <= 3; $t++): ?>
                <?php if (isset($moyennes_map[$t])): ?>
                    <?php
                    $val = $moyennes_map[$t];
                    $cls = $val < 10 ? 'note-low' : ($val < 12 ? 'note-mid' : 'note-high');
                    ?>
                    <div class="moy-card">
                        <div class="trimestre">الفصل <?= $t ?></div>
                        <div class="val <?= $cls ?>"><?= number_format($val, 2) ?></div>
                        <div class="max">من 20</div>
                    </div>
                <?php else: ?>
                    <div class="moy-card empty">
                        <div class="trimestre">الفصل <?= $t ?></div>
                        <div class="val">—</div>
                    </div>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
    </div>

    <!-- آخر الأعداد -->
    <div class="card">
        <h2>📈 آخر الأعداد</h2>
        <?php if (empty($dernieres_notes)): ?>
            <p class="empty">لا توجد أعداد بعد</p>
        <?php else: ?>
            <?php foreach ($dernieres_notes as $n): ?>
                <?php
                $pct = ($n['note'] / $n['note_max']) * 100;
                $cls = $pct < 40 ? 'note-low' : ($pct < 60 ? 'note-mid' : 'note-high');
                ?>
                <div class="note-item">
                    <div class="note-info">
                        <div class="titre"><?= htmlspecialchars($n['titre']) ?></div>
                        <div class="matiere">📚 <?= htmlspecialchars($n['matiere']) ?> — <?= $n['date_epreuve'] ?></div>
                    </div>
                    <div class="note-val <?= $cls ?>">
                        <?= number_format($n['note'], 2) ?>/<?= $n['note_max'] ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>
</body>
</html>