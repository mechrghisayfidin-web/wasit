<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// جلب الأقسام
$stmt = $pdo->prepare("SELECT * FROM classes WHERE ecole_id = ? ORDER BY nom");
$stmt->execute([$_SESSION['ecole_id']]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب الطلبة حسب القسم المختار
$eleves = [];
$classe_selectionnee = $_GET['classe_id'] ?? null;
$eleve_selectionne = $_GET['eleve_id'] ?? null;

if ($classe_selectionnee) {
    $stmt = $pdo->prepare("SELECT * FROM eleves WHERE classe_id = ? ORDER BY nom");
    $stmt->execute([$classe_selectionnee]);
    $eleves = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// جلب الأعداد
$notes = [];
if ($classe_selectionnee) {
    if ($eleve_selectionne) {
        // أعداد طالب محدد
        $stmt = $pdo->prepare("
            SELECT n.note, n.statut,
                   ep.titre, ep.matiere, ep.note_max, ep.date_epreuve, ep.trimestre,
                   u.nom AS prof_nom
            FROM notes n
            JOIN epreuves ep ON n.epreuve_id = ep.id
            JOIN utilisateurs u ON ep.professeur_id = u.id
            WHERE n.eleve_id = ?
            ORDER BY ep.matiere, ep.trimestre, ep.date_epreuve
        ");
        $stmt->execute([$eleve_selectionne]);
    } else {
        // كل أعداد القسم
        $stmt = $pdo->prepare("
            SELECT n.note, n.statut,
                   ep.titre, ep.matiere, ep.note_max, ep.date_epreuve, ep.trimestre,
                   u.nom AS prof_nom,
                   e.nom AS eleve_nom
            FROM notes n
            JOIN epreuves ep ON n.epreuve_id = ep.id
            JOIN utilisateurs u ON ep.professeur_id = u.id
            JOIN eleves e ON n.eleve_id = e.id
            WHERE e.classe_id = ?
            ORDER BY e.nom, ep.matiere, ep.date_epreuve
        ");
        $stmt->execute([$classe_selectionnee]);
    }
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// اسم الطالب المختار
$eleve_nom = '';
if ($eleve_selectionne) {
    foreach ($eleves as $e) {
        if ($e['id'] == $eleve_selectionne) {
            $eleve_nom = $e['nom'];
            break;
        }
    }
}

// تجميع حسب المادة
$notes_par_matiere = [];
foreach ($notes as $n) {
    $notes_par_matiere[$n['matiere']][] = $n;
}

$noms_trimestre = [1 => 'الفصل الأول', 2 => 'الفصل الثاني', 3 => 'الفصل الثالث'];
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
            background: white; border-radius: 12px; padding: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 24px;
        }
        h2 { color: #333; margin-bottom: 20px; font-size: 18px; }
        h3 { color: #1a73e8; font-size: 15px; margin: 16px 0 10px; padding-bottom: 6px; border-bottom: 2px solid #e8f0fe; }
        .filter-bar { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
        select {
            padding: 11px 14px; border: 1px solid #ddd; border-radius: 8px;
            font-size: 15px; font-family: inherit; outline: none; flex: 1;
        }
        select:focus { border-color: #1a73e8; }
        .btn {
            padding: 11px 24px; border: none; border-radius: 8px;
            font-size: 14px; cursor: pointer; white-space: nowrap;
        }
        .btn-primary { background: #1a73e8; color: white; }
        .btn-primary:hover { background: #1558b0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th {
            background: #f8f9fa; padding: 12px; text-align: right;
            font-size: 14px; color: #555; border-bottom: 2px solid #eee;
        }
        td { padding: 12px; border-bottom: 1px solid #f0f0f0; font-size: 14px; color: #444; }
        .note-val { font-size: 18px; font-weight: bold; }
        .note-low { color: #c0392b; }
        .note-mid { color: #e65100; }
        .note-high { color: #2e7d32; }
        .badge {
            display: inline-block; padding: 4px 10px; border-radius: 20px;
            font-size: 12px; font-weight: bold;
        }
        .badge-ok { background: #e6f4ea; color: #2e7d32; }
        .badge-wait { background: #fff3e0; color: #e65100; }
        .empty { text-align: center; color: #aaa; padding: 24px; }
        .info-bar {
            background: #e8f0fe; border-radius: 8px; padding: 12px 16px;
            margin-bottom: 20px; font-size: 14px; color: #1a73e8;
        }
        .readonly-notice {
            background: #fff3e0; color: #e65100; padding: 8px 14px;
            border-radius: 8px; font-size: 13px; margin-bottom: 16px;
        }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">

    <!-- فلتر -->
    <div class="card">
        <h2>📊 عرض الأعداد</h2>
        <div class="readonly-notice">
            👁️ وضع العرض فقط — التعديل من صلاحية الأستاذ
        </div>
        <form method="GET">
            <div class="filter-bar">
                <select name="classe_id" onchange="this.form.submit()">
                    <option value="">— اختر القسم —</option>
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?= $classe['id'] ?>"
                            <?= $classe_selectionnee == $classe['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($classe['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php if ($classe_selectionnee && !empty($eleves)): ?>
                <select name="eleve_id" onchange="this.form.submit()">
                    <option value="">— كل الطلبة —</option>
                    <?php foreach ($eleves as $e): ?>
                        <option value="<?= $e['id'] ?>"
                            <?= $eleve_selectionne == $e['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($e['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- النتائج -->
    <?php if ($classe_selectionnee): ?>
        <?php if (empty($notes)): ?>
            <div class="card">
                <p class="empty">لا توجد أعداد بعد لهذا القسم</p>
            </div>
        <?php else: ?>

            <?php if ($eleve_selectionne): ?>
                <!-- أعداد طالب محدد -->
                <div class="card">
                    <div class="info-bar">
                        🎓 <?= htmlspecialchars($eleve_nom) ?>
                    </div>
                    <?php foreach ($notes_par_matiere as $matiere => $liste): ?>
                        <h3>📚 <?= htmlspecialchars($matiere) ?></h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>الفرض</th>
                                    <th>الفصل</th>
                                    <th>التاريخ</th>
                                    <th>الأستاذ</th>
                                    <th>العدد</th>
                                    <th>الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($liste as $n): ?>
                                    <?php
                                    $pct = $n['note'] !== null ? ($n['note'] / $n['note_max']) * 100 : 0;
                                    $cls = $pct < 40 ? 'note-low' : ($pct < 60 ? 'note-mid' : 'note-high');
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($n['titre']) ?></td>
                                        <td><?= $noms_trimestre[$n['trimestre']] ?? '—' ?></td>
                                        <td><?= $n['date_epreuve'] ?></td>
                                        <td><?= htmlspecialchars($n['prof_nom']) ?></td>
                                        <td>
                                            <?php if ($n['note'] !== null): ?>
                                                <span class="note-val <?= $cls ?>">
                                                    <?= number_format($n['note'], 2) ?>/<?= $n['note_max'] ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color:#aaa">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($n['statut'] === 'confirme'): ?>
                                                <span class="badge badge-ok">✅ مؤكد</span>
                                            <?php else: ?>
                                                <span class="badge badge-wait">⏳ انتظار</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>
                <!-- كل أعداد القسم -->
                <div class="card">
                    <?php
                    $notes_par_eleve = [];
                    foreach ($notes as $n) {
                        $notes_par_eleve[$n['eleve_nom']][$n['matiere']][] = $n;
                    }
                    ?>
                    <?php foreach ($notes_par_eleve as $eleve_n => $matieres): ?>
                        <h3>🎓 <?= htmlspecialchars($eleve_n) ?></h3>
                        <?php foreach ($matieres as $matiere => $liste): ?>
                            <p style="font-size:13px; color:#1a73e8; margin-bottom:6px;">📚 <?= htmlspecialchars($matiere) ?></p>
                            <table>
                                <thead>
                                    <tr>
                                        <th>الفرض</th>
                                        <th>الفصل</th>
                                        <th>العدد</th>
                                        <th>الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($liste as $n): ?>
                                        <?php
                                        $pct = $n['note'] !== null ? ($n['note'] / $n['note_max']) * 100 : 0;
                                        $cls = $pct < 40 ? 'note-low' : ($pct < 60 ? 'note-mid' : 'note-high');
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($n['titre']) ?></td>
                                            <td><?= $noms_trimestre[$n['trimestre']] ?? '—' ?></td>
                                            <td>
                                                <?php if ($n['note'] !== null): ?>
                                                    <span class="note-val <?= $cls ?>">
                                                        <?= number_format($n['note'], 2) ?>/<?= $n['note_max'] ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color:#aaa">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($n['statut'] === 'confirme'): ?>
                                                    <span class="badge badge-ok">✅</span>
                                                <?php else: ?>
                                                    <span class="badge badge-wait">⏳</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>

</div>
</body>
</html>