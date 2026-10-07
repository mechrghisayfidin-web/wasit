<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// إحصائيات
$stmt = $pdo->prepare("SELECT COUNT(*) FROM eleves WHERE ecole_id = ?");
$stmt->execute([$_SESSION['ecole_id']]);
$total_eleves = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM utilisateurs WHERE ecole_id = ? AND role = 'professeur'");
$stmt->execute([$_SESSION['ecole_id']]);
$total_profs = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM observations o JOIN utilisateurs u ON o.professeur_id = u.id WHERE u.ecole_id = ?");
$stmt->execute([$_SESSION['ecole_id']]);
$total_obs = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE ecole_id = ?");
$stmt->execute([$_SESSION['ecole_id']]);
$total_classes = $stmt->fetchColumn();

// آخر الملاحظات
$stmt = $pdo->prepare("
    SELECT o.contenu, o.date_obs,
           e.nom AS eleve_nom, e.classe,
           u.nom AS prof_nom
    FROM observations o
    JOIN eleves e ON o.eleve_id = e.id
    JOIN utilisateurs u ON o.professeur_id = u.id
    WHERE u.ecole_id = ?
    ORDER BY o.date_obs DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['ecole_id']]);
$observations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wassit — لوحة الإدارة</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f0f4f8;
        }
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
            font-size: 36px;
            font-weight: bold;
            color: #1a73e8;
        }
        .stat-card .label {
            color: #888;
            font-size: 14px;
            margin-top: 4px;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 24px;
        }
        h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 18px;
        }
        .obs-item {
            border-bottom: 1px solid #f0f0f0;
            padding: 12px 0;
        }
        .obs-item:last-child { border-bottom: none; }
        .obs-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
        }
        .obs-eleves { font-weight: bold; color: #1a73e8; font-size: 14px; }
        .obs-prof { color: #888; font-size: 13px; }
        .obs-contenu { color: #444; font-size: 15px; margin: 4px 0; }
        .obs-date { color: #aaa; font-size: 12px; }
        .empty { text-align: center; color: #aaa; padding: 20px; }
        .welcome {
            background: linear-gradient(135deg, #1a73e8, #0d47a1);
            color: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }
        .welcome h1 { font-size: 22px; margin-bottom: 4px; }
        .welcome p { opacity: 0.8; font-size: 14px; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>
<div class="main-content">

    <!-- ترحيب -->
    <div class="welcome">
        <h1>مرحباً، <?= htmlspecialchars($_SESSION['nom']) ?> 👋</h1>
        <p>لوحة تحكم Wassit — إدارة كاملة للمعهد</p>
    </div>

    <!-- إحصائيات -->
    <div class="stats">
        <div class="stat-card">
            <div class="number"><?= $total_eleves ?></div>
            <div class="label">🎓 طالب</div>
        </div>
        <div class="stat-card">
            <div class="number"><?= $total_profs ?></div>
            <div class="label">👨‍🏫 أستاذ</div>
        </div>
        <div class="stat-card">
            <div class="number"><?= $total_classes ?></div>
            <div class="label">🏫 قسم</div>
        </div>
        <div class="stat-card">
            <div class="number"><?= $total_obs ?></div>
            <div class="label">📋 ملاحظة</div>
        </div>
    </div>

    <!-- آخر الملاحظات -->
    <div class="card">
        <h2>📋 آخر الملاحظات</h2>
        <?php if (empty($observations)): ?>
            <p class="empty">لا توجد ملاحظات بعد</p>
        <?php else: ?>
            <?php foreach ($observations as $obs): ?>
                <div class="obs-item">
                    <div class="obs-header">
                        <div>
                            <span class="obs-eleves"><?= htmlspecialchars($obs['eleve_nom']) ?></span>
                            <span class="obs-prof"> — <?= htmlspecialchars($obs['classe']) ?></span>
                        </div>
                        <div class="obs-date"><?= $obs['date_obs'] ?></div>
                    </div>
                    <div class="obs-contenu"><?= htmlspecialchars($obs['contenu']) ?></div>
                    <div class="obs-prof">✍️ <?= htmlspecialchars($obs['prof_nom']) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>
</body>
</html>