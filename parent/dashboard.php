<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header('Location: ../auth/login.php');
    exit();
}

// جلب أولاد الولي
$stmt = $pdo->prepare("
    SELECT e.*, c.nom AS classe_nom, c.niveau
    FROM eleves e
    LEFT JOIN classes c ON e.classe_id = c.id
    WHERE e.parent_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$eleves = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب الملاحظات والأعداد لكل ولد
$observations = [];
$notes_par_eleve = [];

foreach ($eleves as $eleve) {
    // الملاحظات
    $stmt = $pdo->prepare("
        SELECT o.contenu, o.date_obs, u.nom AS prof_nom, u.matiere
        FROM observations o
        JOIN utilisateurs u ON o.professeur_id = u.id
        WHERE o.eleve_id = ?
        ORDER BY o.date_obs DESC
        LIMIT 10
    ");
    $stmt->execute([$eleve['id']]);
    $observations[$eleve['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // الأعداد المؤكدة فقط
    $stmt = $pdo->prepare("
        SELECT n.note, n.statut,
               ep.titre, ep.note_max, ep.date_epreuve,
               ep.matiere, u.nom AS prof_nom
        FROM notes n
        JOIN epreuves ep ON n.epreuve_id = ep.id
        JOIN utilisateurs u ON ep.professeur_id = u.id
        WHERE n.eleve_id = ? AND n.statut = 'confirme'
        ORDER BY ep.date_epreuve DESC
    ");
    $stmt->execute([$eleve['id']]);
    $notes_par_eleve[$eleve['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wassit — لوحة الولي</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f0f4f8;
            min-height: 100vh;
        }
        header {
            background: #1a73e8;
            color: white;
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        header h1 { font-size: 20px; }
        header a {
            color: white;
            text-decoration: none;
            font-size: 14px;
            background: rgba(255,255,255,0.2);
            padding: 6px 12px;
            border-radius: 6px;
        }
        .container {
            max-width: 800px;
            margin: 30px auto;
            padding: 0 16px;
        }
        .eleves-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 32px;
            overflow: hidden;
        }
        .eleve-header {
            background: linear-gradient(135deg, #1a73e8, #0d47a1);
            color: white;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .eleve-avatar {
            width: 52px;
            height: 52px;
            background: rgba(255,255,255,0.25);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: bold;
        }
        .eleve-info h2 { font-size: 20px; margin-bottom: 4px; }
        .eleve-info span { opacity: 0.85; font-size: 14px; }

        /* تبويبات */
        .tabs {
            display: flex;
            border-bottom: 2px solid #f0f0f0;
        }
        .tab {
            padding: 14px 24px;
            font-size: 15px;
            cursor: pointer;
            color: #888;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
        }
        .tab.active {
            color: #1a73e8;
            border-bottom-color: #1a73e8;
            font-weight: bold;
        }
        .tab-content { display: none; padding: 20px 24px; }
        .tab-content.active { display: block; }

        /* الملاحظات */
        .obs-item {
            border-bottom: 1px solid #f0f0f0;
            padding: 12px 0;
        }
        .obs-item:last-child { border-bottom: none; }
        .obs-prof {
            font-size: 13px;
            color: #1a73e8;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .obs-contenu { color: #444; font-size: 15px; line-height: 1.5; }
        .obs-date { color: #aaa; font-size: 12px; margin-top: 4px; }

        /* الأعداد */
        .notes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
        }
        .note-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 16px;
            text-align: center;
        }
        .note-card .matiere {
            font-size: 13px;
            color: #888;
            margin-bottom: 4px;
        }
        .note-card .titre {
            font-size: 13px;
            color: #555;
            margin-bottom: 8px;
        }
        .note-card .note-val {
            font-size: 28px;
            font-weight: bold;
        }
        .note-card .note-max {
            font-size: 13px;
            color: #888;
        }
        .note-card .date {
            font-size: 12px;
            color: #aaa;
            margin-top: 6px;
        }
        .note-low { color: #c0392b; }
        .note-mid { color: #e65100; }
        .note-high { color: #2e7d32; }

        .empty { text-align: center; color: #aaa; padding: 24px; }
        .no-eleves { text-align: center; color: #aaa; padding: 40px; font-size: 16px; }
    </style>
</head>
<body>

<header>
    <h1>👨‍👦 مرحباً، <?= htmlspecialchars($_SESSION['nom']) ?></h1>
    <a href="../auth/logout.php">خروج</a>
    <a href="messages.php">✉️ الرسائل</a>
    <a href="rendez_vous.php">📅 المواعيد</a>
</header>

<div class="container">



<?php
// جلب الإعلانات لأقسام أبناء الولي
$classe_ids = array_unique(array_column($eleves, 'classe_id'));
$annonces = [];
if (!empty($classe_ids)) {
    $placeholders = implode(',', array_fill(0, count($classe_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT a.*, c.nom AS classe_nom, u.nom AS prof_nom
        FROM annonces a
        JOIN classes c ON a.classe_id = c.id
        JOIN utilisateurs u ON a.professeur_id = u.id
        WHERE a.classe_id IN ($placeholders)
        ORDER BY a.date_publication DESC
        LIMIT 5
    ");
    $stmt->execute($classe_ids);
    $annonces = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<?php if (!empty($annonces)): ?>
<div class="eleves-card" style="padding: 20px 24px;">
    <h2 style="font-size:16px; color:#333; margin-bottom:12px;">📢 آخر الإعلانات</h2>
    <?php foreach ($annonces as $a): ?>
        <div style="border-bottom:1px solid #f0f0f0; padding:10px 0;">
            <div style="font-size:12px; color:#1a73e8; font-weight:bold; margin-bottom:4px;">
                📚 <?= htmlspecialchars($a['classe_nom']) ?> — ✍️ <?= htmlspecialchars($a['prof_nom']) ?>
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

    <?php if (empty($eleves)): ?>
        <div class="eleves-card">
            <p class="no-eleves">لا يوجد أبناء مسجلون بعد</p>
        </div>
    <?php else: ?>
        <?php foreach ($eleves as $eleve): ?>
            <div class="eleves-card">

                <!-- رأس بطاقة الطالب -->
                <div class="eleve-header">
                    <div class="eleve-avatar">
                        <?= mb_substr($eleve['nom'], 0, 1) ?>
                    </div>
                    <div class="eleve-info">
                        <h2><?= htmlspecialchars($eleve['nom']) ?></h2>
                        <span>📚 <?= htmlspecialchars($eleve['classe_nom'] ?? '—') ?> — <?= htmlspecialchars($eleve['niveau'] ?? '') ?></span>
                    </div>
                </div>

                <!-- تبويبات -->
                <div class="tabs">
                    <div class="tab active" onclick="switchTab(this, 'obs-<?= $eleve['id'] ?>')">
                        📋 الملاحظات
                        (<?= count($observations[$eleve['id']]) ?>)
                    </div>
                    <div class="tab" onclick="switchTab(this, 'notes-<?= $eleve['id'] ?>')">
                        📊 الأعداد
                        (<?= count($notes_par_eleve[$eleve['id']]) ?>)
                    </div>
                </div>

                <!-- الملاحظات -->
                <div id="obs-<?= $eleve['id'] ?>" class="tab-content active">
                    <?php if (empty($observations[$eleve['id']])): ?>
                        <p class="empty">لا توجد ملاحظات بعد</p>
                    <?php else: ?>
                        <?php foreach ($observations[$eleve['id']] as $obs): ?>
                            <div class="obs-item">
                                <div class="obs-prof">
                                    ✍️ <?= htmlspecialchars($obs['prof_nom']) ?>
                                    <?php if ($obs['matiere']): ?>
                                        — <?= htmlspecialchars($obs['matiere']) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="obs-contenu"><?= htmlspecialchars($obs['contenu']) ?></div>
                                <div class="obs-date">🕐 <?= $obs['date_obs'] ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- الأعداد -->
                <div id="notes-<?= $eleve['id'] ?>" class="tab-content">
                    <?php if (empty($notes_par_eleve[$eleve['id']])): ?>
                        <p class="empty">لا توجد أعداد مؤكدة بعد</p>
                    <?php else: ?>
                        <div class="notes-grid">
                            <?php foreach ($notes_par_eleve[$eleve['id']] as $n): ?>
                                <?php
                                $pct = ($n['note'] / $n['note_max']) * 100;
                                $cls = $pct < 40 ? 'note-low' : ($pct < 60 ? 'note-mid' : 'note-high');
                                ?>
                                <div class="note-card">
                                    <div class="matiere">📚 <?= htmlspecialchars($n['matiere']) ?></div>
                                    <div class="titre"><?= htmlspecialchars($n['titre']) ?></div>
                                    <div class="note-val <?= $cls ?>">
                                        <?= number_format($n['note'], 2) ?>
                                    </div>
                                    <div class="note-max">من <?= $n['note_max'] ?></div>
                                    <div class="date">📅 <?= $n['date_epreuve'] ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

<script>
function switchTab(el, targetId) {
    const card = el.closest('.eleves-card');
    card.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    card.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    document.getElementById(targetId).classList.add('active');
}
</script>

</body>
</html>