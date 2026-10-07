<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professeur') {
    header('Location: ../auth/login.php');
    exit();
}

// جلب المواعيد المحددة فقط
$stmt = $pdo->prepare("
    SELECT r.*, u.nom AS parent_nom
    FROM rendez_vous r
    JOIN utilisateurs u ON r.parent_id = u.id
    WHERE r.professeur_id = ? AND r.statut = 'accepte'
    ORDER BY r.date_rdv ASC, r.heure_rdv ASC
");
$stmt->execute([$_SESSION['user_id']]);
$rdvs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// تصنيف: قادمة / ماضية
$aujourd_hui = date('Y-m-d');
$prochains = array_filter($rdvs, fn($r) => $r['date_rdv'] >= $aujourd_hui);
$passes = array_filter($rdvs, fn($r) => $r['date_rdv'] < $aujourd_hui);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wassit — مواعيدي</title>
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
        .rdv-item {
            display: flex;
            align-items: center;
            gap: 16px;
            border: 1px solid #f0f0f0;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 12px;
            background: #fafafa;
        }
        .rdv-item.today {
            border-color: #1a73e8;
            background: #e8f0fe;
        }
        .rdv-date-box {
            background: #1a73e8;
            color: white;
            border-radius: 10px;
            padding: 12px 16px;
            text-align: center;
            min-width: 70px;
        }
        .rdv-date-box .day { font-size: 24px; font-weight: bold; }
        .rdv-date-box .month { font-size: 11px; opacity: 0.85; }
        .rdv-info { flex: 1; }
        .rdv-parent {
            font-weight: bold;
            color: #333;
            font-size: 15px;
            margin-bottom: 4px;
        }
        .rdv-time {
            color: #1a73e8;
            font-size: 14px;
            font-weight: bold;
        }
        .rdv-motif {
            color: #666;
            font-size: 13px;
            margin-top: 6px;
            background: white;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #eee;
        }
        .badge-today {
            background: #1a73e8;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }
        .empty { text-align: center; color: #aaa; padding: 40px; }
        .empty-icon { font-size: 48px; margin-bottom: 12px; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">

    <!-- المواعيد القادمة -->
    <div class="card">
        <h2>📅 المواعيد القادمة</h2>
        <?php if (empty($prochains)): ?>
            <div class="empty">
                <div class="empty-icon">📭</div>
                <p>لا توجد مواعيد قادمة</p>
            </div>
        <?php else: ?>
            <?php foreach ($prochains as $rdv): ?>
                <?php
                $is_today = $rdv['date_rdv'] === $aujourd_hui;
                $date = new DateTime($rdv['date_rdv']);
                $mois_ar = [
                    1=>'جانفي',2=>'فيفري',3=>'مارس',4=>'أفريل',5=>'ماي',6=>'جوان',
                    7=>'جويلية',8=>'أوت',9=>'سبتمبر',10=>'أكتوبر',11=>'نوفمبر',12=>'ديسمبر'
                ];
                ?>
                <div class="rdv-item <?= $is_today ? 'today' : '' ?>">
                    <div class="rdv-date-box">
                        <div class="day"><?= $date->format('d') ?></div>
                        <div class="month"><?= $mois_ar[(int)$date->format('n')] ?></div>
                    </div>
                    <div class="rdv-info">
                        <div class="rdv-parent">
                            👨‍👦 <?= htmlspecialchars($rdv['parent_nom']) ?>
                            <?php if ($is_today): ?>
                                <span class="badge-today">اليوم 🔥</span>
                            <?php endif; ?>
                        </div>
                        <div class="rdv-time">🕐 <?= htmlspecialchars($rdv['heure_rdv']) ?></div>
                        <div class="rdv-motif">💬 <?= htmlspecialchars($rdv['motif']) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- المواعيد السابقة -->
    <?php if (!empty($passes)): ?>
    <div class="card">
        <h2>📜 المواعيد السابقة</h2>
        <?php foreach ($passes as $rdv): ?>
            <div class="rdv-item" style="opacity: 0.6;">
                <div class="rdv-date-box" style="background:#aaa;">
                    <div class="day"><?= date('d', strtotime($rdv['date_rdv'])) ?></div>
                    <div class="month"><?= date('m/Y', strtotime($rdv['date_rdv'])) ?></div>
                </div>
                <div class="rdv-info">
                    <div class="rdv-parent">👨‍👦 <?= htmlspecialchars($rdv['parent_nom']) ?></div>
                    <div class="rdv-time">🕐 <?= htmlspecialchars($rdv['heure_rdv']) ?></div>
                    <div class="rdv-motif">💬 <?= htmlspecialchars($rdv['motif']) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>
</body>
</html>