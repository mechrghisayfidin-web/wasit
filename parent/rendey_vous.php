<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header('Location: ../auth/login.php');
    exit();
}

$success = '';
$error = '';

// طلب موعد
if (isset($_POST['action']) && $_POST['action'] === 'demander') {
    $destinataire = $_POST['destinataire'];
    $motif = trim($_POST['motif']);

    if (!empty($destinataire) && !empty($motif)) {
        $professeur_id = $destinataire === 'admin' ? null : $destinataire;

        $stmt = $pdo->prepare("
            INSERT INTO rendez_vous (parent_id, professeur_id, motif)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$_SESSION['user_id'], $professeur_id, $motif]);
        $success = "تم إرسال طلب الموعد ✅ — في انتظار رد الإدارة";
    } else {
        $error = "املأ كل الحقول!";
    }
}

// جلب أساتذة أبناء الولي
$stmt = $pdo->prepare("
    SELECT DISTINCT u.id, u.nom, u.matiere
    FROM utilisateurs u
    JOIN epreuves ep ON ep.professeur_id = u.id
    JOIN eleves el ON ep.classe_id = el.classe_id
    WHERE el.parent_id = ?
    UNION
    SELECT DISTINCT u.id, u.nom, u.matiere
    FROM utilisateurs u
    JOIN observations o ON o.professeur_id = u.id
    JOIN eleves el ON o.eleve_id = el.id
    WHERE el.parent_id = ?
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$professeurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب طلبات الولي
$stmt = $pdo->prepare("
    SELECT r.*, u.nom AS prof_nom, u.matiere
    FROM rendez_vous r
    LEFT JOIN utilisateurs u ON r.professeur_id = u.id
    WHERE r.parent_id = ?
    ORDER BY r.date_demande DESC
");
$stmt->execute([$_SESSION['user_id']]);
$rdvs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wassit — المواعيد</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f4f8; min-height: 100vh; }
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
        .container { max-width: 700px; margin: 30px auto; padding: 0 16px; }
        .card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 24px;
        }
        h2 { color: #333; margin-bottom: 20px; font-size: 18px; }
        label { display: block; font-size: 13px; color: #555; margin-bottom: 4px; font-weight: bold; }
        select, textarea {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
            font-family: inherit;
            outline: none;
            margin-bottom: 14px;
        }
        select:focus, textarea:focus { border-color: #1a73e8; }
        textarea { resize: vertical; min-height: 100px; }
        .btn-primary {
            width: 100%;
            padding: 12px;
            background: #1a73e8;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            cursor: pointer;
        }
        .btn-primary:hover { background: #1558b0; }
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
        .rdv-item {
            border-bottom: 1px solid #f0f0f0;
            padding: 16px 0;
        }
        .rdv-item:last-child { border-bottom: none; }
        .rdv-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .rdv-dest { font-weight: bold; color: #1a73e8; font-size: 15px; }
        .rdv-date { color: #aaa; font-size: 12px; }
        .rdv-motif { color: #555; font-size: 14px; margin-bottom: 8px; }
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
        .rdv-details {
            background: #e8f0fe;
            border-radius: 8px;
            padding: 12px;
            margin-top: 8px;
            font-size: 14px;
            color: #1a73e8;
        }
        .empty { text-align: center; color: #aaa; padding: 24px; }
    </style>
</head>
<body>

<header>
    <h1>📅 المواعيد</h1>
    <a href="dashboard.php">🏠 الرئيسية</a>
</header>

<div class="container">

    <!-- طلب موعد -->
    <div class="card">
        <h2>📨 طلب موعد جديد</h2>

        <?php if ($success): ?>
            <div class="success"><?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="demander">
            <label>مع من؟</label>
            <select name="destinataire" required>
                <option value="">— اختر —</option>
                <option value="admin">🏫 إدارة المعهد</option>
                <?php foreach ($professeurs as $prof): ?>
                    <option value="<?= $prof['id'] ?>">
                        👨‍🏫 <?= htmlspecialchars($prof['nom']) ?>
                        <?php if ($prof['matiere']): ?>
                            — <?= htmlspecialchars($prof['matiere']) ?>
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label>سبب الموعد</label>
            <textarea name="motif" placeholder="اشرح باختصار سبب طلب المقابلة..."></textarea>
            <button type="submit" class="btn-primary">إرسال الطلب 📨</button>
        </form>
    </div>

    <!-- طلباتي -->
    <div class="card">
        <h2>📋 طلباتي</h2>
        <?php if (empty($rdvs)): ?>
            <p class="empty">لا توجد طلبات بعد</p>
        <?php else: ?>
            <?php foreach ($rdvs as $rdv): ?>
                <div class="rdv-item">
                    <div class="rdv-header">
                        <div class="rdv-dest">
                            <?php if ($rdv['professeur_id']): ?>
                                👨‍🏫 <?= htmlspecialchars($rdv['prof_nom']) ?>
                                <?php if ($rdv['matiere']): ?>
                                    — <?= htmlspecialchars($rdv['matiere']) ?>
                                <?php endif; ?>
                            <?php else: ?>
                                🏫 إدارة المعهد
                            <?php endif; ?>
                        </div>
                        <div class="rdv-date"><?= $rdv['date_demande'] ?></div>
                    </div>
                    <div class="rdv-motif">💬 <?= htmlspecialchars($rdv['motif']) ?></div>

                    <?php if ($rdv['statut'] === 'en_attente'): ?>
                        <span class="badge badge-wait">⏳ في انتظار الرد</span>
                    <?php elseif ($rdv['statut'] === 'accepte'): ?>
                        <span class="badge badge-ok">✅ تم تحديد الموعد</span>
                        <div class="rdv-details">
                            📅 التاريخ: <?= $rdv['date_rdv'] ?> &nbsp;|&nbsp;
                            🕐 الوقت: <?= htmlspecialchars($rdv['heure_rdv']) ?>
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