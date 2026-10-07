<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$success = '';
$error = '';

// تحديد موعد
if (isset($_POST['action']) && $_POST['action'] === 'accepter') {
    $rdv_id = $_POST['rdv_id'];
    $date_rdv = $_POST['date_rdv'];
    $heure_rdv = trim($_POST['heure_rdv']);

    if (!empty($date_rdv) && !empty($heure_rdv)) {
        $stmt = $pdo->prepare("
            UPDATE rendez_vous 
            SET statut = 'accepte', date_rdv = ?, heure_rdv = ?
            WHERE id = ?
        ");
        $stmt->execute([$date_rdv, $heure_rdv, $rdv_id]);
        $success = "تم تحديد الموعد ✅ — تم إشعار الولي والأستاذ";
    } else {
        $error = "حدد التاريخ والوقت!";
    }
}

// رفض موعد
if (isset($_POST['action']) && $_POST['action'] === 'refuser') {
    $rdv_id = $_POST['rdv_id'];
    $stmt = $pdo->prepare("UPDATE rendez_vous SET statut = 'refuse' WHERE id = ?");
    $stmt->execute([$rdv_id]);
    $success = "تم رفض الطلب ❌";
}

// جلب الطلبات
$stmt = $pdo->prepare("
    SELECT r.*, 
           u_parent.nom AS parent_nom,
           u_prof.nom AS prof_nom, u_prof.matiere
    FROM rendez_vous r
    JOIN utilisateurs u_parent ON r.parent_id = u_parent.id
    LEFT JOIN utilisateurs u_prof ON r.professeur_id = u_prof.id
    WHERE u_parent.ecole_id = ?
    ORDER BY 
        CASE r.statut WHEN 'en_attente' THEN 0 ELSE 1 END,
        r.date_demande DESC
");
$stmt->execute([$_SESSION['ecole_id']]);
$rdvs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$en_attente = array_filter($rdvs, fn($r) => $r['statut'] === 'en_attente');
$traites = array_filter($rdvs, fn($r) => $r['statut'] !== 'en_attente');

// اسم المعهد
$stmt = $pdo->prepare("SELECT nom FROM ecoles WHERE id = ?");
$stmt->execute([$_SESSION['ecole_id']]);
$nom_ecole = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wassit — المواعيد</title>
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
            border: 1px solid #f0f0f0;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 12px;
            background: #fafafa;
        }
        .rdv-item.en_attente {
            border-color: #ffe0b2;
            background: #fffbf5;
        }
        .rdv-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .rdv-parties { font-size: 14px; }
        .rdv-parent { font-weight: bold; color: #333; }
        .rdv-arrow { color: #aaa; margin: 0 6px; }
        .rdv-dest { color: #1a73e8; font-weight: bold; }
        .rdv-date { color: #aaa; font-size: 12px; }
        .rdv-motif {
            color: #555;
            font-size: 14px;
            background: white;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #eee;
            margin-bottom: 12px;
        }
        .rdv-form {
            display: flex;
            gap: 10px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .rdv-form > div { flex: 1; min-width: 130px; }
        label { display: block; font-size: 12px; color: #888; margin-bottom: 4px; }
        input, select {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            outline: none;
        }
        input:focus, select:focus { border-color: #1a73e8; }
        .btn {
            padding: 9px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            font-family: inherit;
            white-space: nowrap;
        }
        .btn-approve { background: #1a73e8; color: white; }
        .btn-approve:hover { background: #1558b0; }
        .btn-refuse {
            background: #fdecea;
            color: #c0392b;
            margin-top: 12px;
        }
        .btn-refuse:hover { background: #f5c6cb; }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-wait { background: #fff3e0; color: #e65100; }
        .badge-ok { background: #e6f4ea; color: #2e7d32; }
        .badge-refused { background: #fdecea; color: #c0392b; }
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
        .empty { text-align: center; color: #aaa; padding: 24px; }
        .count-badge {
            background: #e65100;
            color: white;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            margin-right: 6px;
        }
        .rdv-details {
            background: #e8f0fe;
            border-radius: 8px;
            padding: 10px;
            font-size: 13px;
            color: #1a73e8;
            margin-top: 8px;
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

    <!-- في الانتظار -->
    <div class="card">
        <h2>
            ⏳ طلبات في الانتظار
            <?php if (count($en_attente) > 0): ?>
                <span class="count-badge"><?= count($en_attente) ?></span>
            <?php endif; ?>
        </h2>

        <?php if (empty($en_attente)): ?>
            <p class="empty">لا توجد طلبات في الانتظار 🎉</p>
        <?php else: ?>
            <?php foreach ($en_attente as $rdv): ?>
                <div class="rdv-item en_attente">
                    <div class="rdv-header">
                        <div class="rdv-parties">
                            <span class="rdv-parent">👨‍👦 <?= htmlspecialchars($rdv['parent_nom']) ?></span>
                            <span class="rdv-arrow">←</span>
                            <span class="rdv-dest">
                                <?php if ($rdv['professeur_id']): ?>
                                    👨‍🏫 <?= htmlspecialchars($rdv['prof_nom']) ?>
                                    <?php if ($rdv['matiere']): ?>
                                        — <?= htmlspecialchars($rdv['matiere']) ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    🏫 الإدارة
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="rdv-date"><?= $rdv['date_demande'] ?></div>
                    </div>
                    <div class="rdv-motif">💬 <?= htmlspecialchars($rdv['motif']) ?></div>

                    <form method="POST">
                        <input type="hidden" name="rdv_id" value="<?= $rdv['id'] ?>">
                        <input type="hidden" name="action" value="accepter">
                        <div class="rdv-form">
                            <div>
                                <label>التاريخ</label>
                                <input type="date" name="date_rdv" min="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div>
                                <label>الوقت</label>
                                <input type="time" name="heure_rdv" required>
                            </div>
                            <div style="flex: 0;">
                                <button type="submit" class="btn btn-approve">✅ تحديد الموعد</button>
                            </div>
                        </div>
                    </form>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="rdv_id" value="<?= $rdv['id'] ?>">
                        <input type="hidden" name="action" value="refuser">
                        <button type="submit" class="btn btn-refuse"
                            onclick="return confirm('رفض هذا الطلب؟')">
                            ❌ رفض الطلب
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- المعالجة -->
    <div class="card">
        <h2>📋 الطلبات المعالجة</h2>
        <?php if (empty($traites)): ?>
            <p class="empty">لا توجد طلبات معالجة بعد</p>
        <?php else: ?>
            <?php foreach ($traites as $rdv): ?>
                <div class="rdv-item">
                    <div class="rdv-header">
                        <div class="rdv-parties">
                            <span class="rdv-parent">👨‍👦 <?= htmlspecialchars($rdv['parent_nom']) ?></span>
                            <span class="rdv-arrow">←</span>
                            <span class="rdv-dest">
                                <?php if ($rdv['professeur_id']): ?>
                                    👨‍🏫 <?= htmlspecialchars($rdv['prof_nom']) ?>
                                <?php else: ?>
                                    🏫 الإدارة
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="rdv-date"><?= $rdv['date_demande'] ?></div>
                    </div>
                    <div class="rdv-motif">💬 <?= htmlspecialchars($rdv['motif']) ?></div>
                    <?php if ($rdv['statut'] === 'accepte'): ?>
                        <span class="badge badge-ok">✅ تم التحديد</span>
                        <div class="rdv-details">
                            📅 <?= $rdv['date_rdv'] ?> &nbsp;|&nbsp; 🕐 <?= $rdv['heure_rdv'] ?>
                        </div>
                    <?php else: ?>
                        <span class="badge badge-refused">❌ مرفوض</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>
</body>
</html>