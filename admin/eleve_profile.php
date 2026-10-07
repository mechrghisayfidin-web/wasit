<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// لازم يكون فيه id
if (!isset($_GET['id'])) {
    header('Location: eleves.php');
    exit();
}

$eleve_id = $_GET['id'];

// معلومات الطالب
$stmt = $pdo->prepare("
    SELECT e.*, c.nom AS classe_nom, c.niveau,
           u.nom AS parent_nom, u.email AS parent_email
    FROM eleves e
    LEFT JOIN classes c ON e.classe_id = c.id
    LEFT JOIN utilisateurs u ON e.parent_id = u.id
    WHERE e.id = ? AND e.ecole_id = ?
");
$stmt->execute([$eleve_id, $_SESSION['ecole_id']]);
$eleves = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$eleves) {
    header('Location: eleves.php');
    exit();
}

// الملاحظات
$stmt = $pdo->prepare("
    SELECT o.contenu, o.date_obs, u.nom AS prof_nom, u.matiere
    FROM observations o
    JOIN utilisateurs u ON o.professeur_id = u.id
    WHERE o.eleve_id = ?
    ORDER BY o.date_obs DESC
");
$stmt->execute([$eleve_id]);
$observations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// الأعداد
$stmt = $pdo->prepare("
    SELECT n.note, n.statut,
           ep.titre, ep.note_max, ep.date_epreuve, ep.matiere,
           u.nom AS prof_nom
    FROM notes n
    JOIN epreuves ep ON n.epreuve_id = ep.id
    JOIN utilisateurs u ON ep.professeur_id = u.id
    WHERE n.eleve_id = ?
    ORDER BY ep.matiere, ep.date_epreuve
");
$stmt->execute([$eleve_id]);
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// تجميع الأعداد حسب المادة
$notes_par_matiere = [];
foreach ($notes as $note) {
    $notes_par_matiere[$note['matiere']][] = $note;
}

// الغيابات
$stmt = $pdo->prepare("
    SELECT a.*, u.nom AS prof_nom, u.matiere
    FROM absences a
    JOIN utilisateurs u ON a.professeur_id = u.id
    WHERE a.eleve_id = ?
    ORDER BY a.date_absence DESC
");
$stmt->execute([$eleve_id]);
$absences = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_abs = count($absences);
$abs_non_just = count(array_filter($absences, fn($a) => $a['justifie'] === 'non'));
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wassit — ملف <?= htmlspecialchars($eleve['nom']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f4f8; }

        /* بطاقة الطالب */
        .profile-header {
            background: linear-gradient(135deg, #1a73e8, #0d47a1);
            border-radius: 12px;
            padding: 28px;
            color: white;
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 24px;
            box-shadow: 0 4px 15px rgba(26,115,232,0.3);
        }
        .profile-avatar {
            width: 70px;
            height: 70px;
            background: rgba(255,255,255,0.25);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: bold;
            flex-shrink: 0;
        }
        .profile-info h1 { font-size: 24px; margin-bottom: 6px; }
        .profile-info p { opacity: 0.85; font-size: 14px; margin-bottom: 3px; }

        /* إحصائيات سريعة */
        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            color: #1a73e8;
        }
        .stat-card .number.red { color: #c0392b; }
        .stat-card .number.green { color: #2e7d32; }
        .stat-card .number.orange { color: #e65100; }
        .stat-card .label {
            color: #888;
            font-size: 13px;
            margin-top: 4px;
        }

        /* تبويبات */
        .tabs {
            display: flex;
            gap: 4px;
            margin-bottom: 20px;
            background: white;
            padding: 6px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .tab {
            flex: 1;
            padding: 12px;
            text-align: center;
            font-size: 14px;
            cursor: pointer;
            color: #888;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .tab.active {
            background: #1a73e8;
            color: white;
            font-weight: bold;
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* كارد عام */
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
            font-size: 15px;
            margin-bottom: 12px;
            padding-bottom: 6px;
            border-bottom: 2px solid #e8f0fe;
        }

        /* الملاحظات */
        .obs-item {
            border-bottom: 1px solid #f0f0f0;
            padding: 12px 0;
        }
        .obs-item:last-child { border-bottom: none; }
        .obs-prof { font-size: 13px; color: #1a73e8; font-weight: bold; margin-bottom: 4px; }
        .obs-contenu { color: #444; font-size: 15px; line-height: 1.5; }
        .obs-date { color: #aaa; font-size: 12px; margin-top: 4px; }

        /* الأعداد */
        .matiere-section { margin-bottom: 24px; }
        .notes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 10px;
        }
        .note-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 14px;
            text-align: center;
        }
        .note-card .titre { font-size: 12px; color: #888; margin-bottom: 6px; }
        .note-card .val {
            font-size: 26px;
            font-weight: bold;
        }
        .note-card .max { font-size: 12px; color: #aaa; }
        .note-card .date { font-size: 11px; color: #aaa; margin-top: 4px; }
        .note-card .statut { margin-top: 6px; }
        .note-low { color: #c0392b; }
        .note-mid { color: #e65100; }
        .note-high { color: #2e7d32; }

        /* الغيابات */
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
        .badge-wait { background: #fff3e0; color: #e65100; }
        .badge-ok { background: #e6f4ea; color: #2e7d32; }
        .empty { text-align: center; color: #aaa; padding: 24px; }
        .back-btn {
            display: inline-block;
            margin-bottom: 20px;
            color: #1a73e8;
            text-decoration: none;
            font-size: 14px;
        }
        .back-btn:hover { text-decoration: underline; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">

    <a href="eleves.php" class="back-btn">← رجوع لقائمة الطلبة</a>

    <!-- بطاقة الطالب -->
    <div class="profile-header">
        <div class="profile-avatar">
            <?= mb_substr($eleve['nom'], 0, 1) ?>
        </div>
        <div class="profile-info">
            <h1><?= htmlspecialchars($eleve['nom']) ?></h1>
            <p>📚 <?= htmlspecialchars($eleve['classe_nom'] ?? '—') ?> — <?= htmlspecialchars($eleve['niveau'] ?? '') ?></p>
            <p>👨‍👦 ولي الأمر: <?= htmlspecialchars($eleves['parent_nom'] ?? '—') ?></p>
            <p>📧 <?= htmlspecialchars($eleves['parent_email'] ?? '—') ?></p>
        </div>
    </div>

    <!-- إحصائيات -->
    <div class="stats">
        <div class="stat-card">
            <div class="number"><?= count($observations) ?></div>
            <div class="label">📋 ملاحظة</div>
        </div>
        <div class="stat-card">
            <div class="number"><?= count($notes) ?></div>
            <div class="label">📊 عدد</div>
        </div>
        <div class="stat-card">
            <div class="number red"><?= $abs_non_just ?></div>
            <div class="label">❌ غياب غير مبرر</div>
        </div>
        <div class="stat-card">
            <div class="number orange"><?= $total_abs ?></div>
            <div class="label">📅 مجموع الغيابات</div>
        </div>
    </div>

    <!-- تبويبات -->
    <div class="tabs">
        <div class="tab active" onclick="switchTab(this, 'tab-obs')">
            📋 الملاحظات (<?= count($observations) ?>)
        </div>
        <div class="tab" onclick="switchTab(this, 'tab-notes')">
            📊 الأعداد (<?= count($notes) ?>)
        </div>
        <div class="tab" onclick="switchTab(this, 'tab-abs')">
            📅 الغيابات (<?= $total_abs ?>)
        </div>
    </div>

    <!-- الملاحظات -->
    <div id="tab-obs" class="tab-content active">
        <div class="card">
            <?php if (empty($observations)): ?>
                <p class="empty">لا توجد ملاحظات بعد</p>
            <?php else: ?>
                <?php foreach ($observations as $obs): ?>
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
    </div>

    <!-- الأعداد -->
    <div id="tab-notes" class="tab-content">
        <?php if (empty($notes)): ?>
            <div class="card"><p class="empty">لا توجد أعداد بعد</p></div>
        <?php else: ?>
            <?php foreach ($notes_par_matiere as $matiere => $liste): ?>
                <div class="card">
                    <h3>📚 <?= htmlspecialchars($matiere) ?></h3>
                    <div class="notes-grid">
                        <?php foreach ($liste as $n): ?>
                            <?php
                            $pct = $n['note'] !== null ? ($n['note'] / $n['note_max']) * 100 : 0;
                            $cls = $pct < 40 ? 'note-low' : ($pct < 60 ? 'note-mid' : 'note-high');
                            ?>
                            <div class="note-card">
                                <div class="titre"><?= htmlspecialchars($n['titre']) ?></div>
                                <?php if ($n['note'] !== null): ?>
                                    <div class="val <?= $cls ?>"><?= number_format($n['note'], 2) ?></div>
                                    <div class="max">من <?= $n['note_max'] ?></div>
                                <?php else: ?>
                                    <div class="val" style="color:#aaa">—</div>
                                <?php endif; ?>
                                <div class="date">📅 <?= $n['date_epreuve'] ?></div>
                                <div class="statut">
                                    <?php if ($n['statut'] === 'confirme'): ?>
                                        <span class="badge badge-ok">✅</span>
                                    <?php else: ?>
                                        <span class="badge badge-wait">⏳</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- الغيابات -->
    <div id="tab-abs" class="tab-content">
        <div class="card">
            <?php if (empty($absences)): ?>
                <p class="empty">لا توجد غيابات مسجلة 🎉</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>الحصة</th>
                            <th>المادة</th>
                            <th>الأستاذ</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($absences as $abs): ?>
                            <tr>
                                <td><?= $abs['date_absence'] ?></td>
                                <td><?= htmlspecialchars($abs['heure']) ?></td>
                                <td><?= htmlspecialchars($abs['matiere'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($abs['prof_nom']) ?></td>
                                <td>
                                    <?php if ($abs['justifie'] === 'oui'): ?>
                                        <span class="badge badge-just">مبرر ✅</span>
                                    <?php else: ?>
                                        <span class="badge badge-abs">غير مبرر ❌</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
function switchTab(el, targetId) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    document.getElementById(targetId).classList.add('active');
}
</script>

</body>
</html>