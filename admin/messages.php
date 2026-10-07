<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$success = '';
$error = '';

// موافقة أو رفض
if (isset($_POST['action'])) {
    $msg_id = $_POST['msg_id'];

    if ($_POST['action'] === 'approuver') {
        $stmt = $pdo->prepare("
            UPDATE messages 
            SET statut = 'approuve', date_traitement = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$msg_id]);
        $success = "تمت الموافقة وأُرسلت الرسالة للأستاذ ✅";
    } elseif ($_POST['action'] === 'refuser') {
        $stmt = $pdo->prepare("
            UPDATE messages 
            SET statut = 'refuse', date_traitement = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$msg_id]);
        $success = "تم رفض الرسالة ❌";
    }
}

// جلب الرسائل
$stmt = $pdo->prepare("
    SELECT m.*,
           u_exp.nom AS expediteur_nom,
           u_dest.nom AS destinataire_nom,
           u_dest.matiere AS destinataire_matiere
    FROM messages m
    JOIN utilisateurs u_exp ON m.expediteur_id = u_exp.id
    JOIN utilisateurs u_dest ON m.destinataire_id = u_dest.id
    WHERE u_exp.ecole_id = ?
    ORDER BY 
        CASE m.statut WHEN 'en_attente' THEN 0 ELSE 1 END,
        m.date_envoi DESC
");
$stmt->execute([$_SESSION['ecole_id']]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$en_attente = array_filter($messages, fn($m) => $m['statut'] === 'en_attente');
$traites = array_filter($messages, fn($m) => $m['statut'] !== 'en_attente');
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wassit — الرسائل</title>
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
        .msg-item {
            border: 1px solid #f0f0f0;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 12px;
            background: #fafafa;
        }
        .msg-item.en_attente {
            border-color: #ffe0b2;
            background: #fffbf5;
        }
        .msg-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .msg-parties { font-size: 14px; }
        .msg-exp {
            font-weight: bold;
            color: #333;
        }
        .msg-arrow { color: #aaa; margin: 0 6px; }
        .msg-dest { color: #1a73e8; font-weight: bold; }
        .msg-date { color: #aaa; font-size: 12px; }
        .msg-sujet {
            font-weight: bold;
            color: #333;
            font-size: 15px;
            margin-bottom: 8px;
        }
        .msg-contenu {
            color: #555;
            font-size: 14px;
            line-height: 1.6;
            background: white;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #eee;
            margin-bottom: 12px;
        }
        .msg-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .btn {
            padding: 8px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            font-family: inherit;
        }
        .btn-approve {
            background: #1a73e8;
            color: white;
        }
        .btn-approve:hover { background: #1558b0; }
        .btn-refuse {
            background: #fdecea;
            color: #c0392b;
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
        .traite-date {
            font-size: 12px;
            color: #aaa;
            margin-top: 4px;
        }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">

    <?php if ($success): ?>
        <div class="success"><?= $success ?></div>
    <?php endif; ?>

    <!-- في الانتظار -->
    <div class="card">
        <h2>
            ⏳ في انتظار الموافقة
            <?php if (count($en_attente) > 0): ?>
                <span class="count-badge"><?= count($en_attente) ?></span>
            <?php endif; ?>
        </h2>

        <?php if (empty($en_attente)): ?>
            <p class="empty">لا توجد رسائل في الانتظار 🎉</p>
        <?php else: ?>
            <?php foreach ($en_attente as $msg): ?>
                <div class="msg-item en_attente">
                    <div class="msg-header">
                        <div class="msg-parties">
                            <span class="msg-exp">👨‍👦 <?= htmlspecialchars($msg['expediteur_nom']) ?></span>
                            <span class="msg-arrow">←</span>
                            <span class="msg-dest">
                                👨‍🏫 <?= htmlspecialchars($msg['destinataire_nom']) ?>
                                <?php if ($msg['destinataire_matiere']): ?>
                                    — <?= htmlspecialchars($msg['destinataire_matiere']) ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="msg-date"><?= $msg['date_envoi'] ?></div>
                    </div>
                    <div class="msg-sujet">📌 <?= htmlspecialchars($msg['sujet']) ?></div>
                    <div class="msg-contenu"><?= htmlspecialchars($msg['contenu']) ?></div>
                    <div class="msg-actions">
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="msg_id" value="<?= $msg['id'] ?>">
                            <input type="hidden" name="action" value="approuver">
                            <button type="submit" class="btn btn-approve"
                                onclick="return confirm('موافقة وإرسال للأستاذ؟')">
                                ✅ موافقة وإرسال
                            </button>
                        </form>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="msg_id" value="<?= $msg['id'] ?>">
                            <input type="hidden" name="action" value="refuser">
                            <button type="submit" class="btn btn-refuse"
                                onclick="return confirm('رفض هذه الرسالة؟')">
                                ❌ رفض
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- المعالجة -->
    <div class="card">
        <h2>📬 الرسائل المعالجة</h2>
        <?php if (empty($traites)): ?>
            <p class="empty">لا توجد رسائل معالجة بعد</p>
        <?php else: ?>
            <?php foreach ($traites as $msg): ?>
                <div class="msg-item">
                    <div class="msg-header">
                        <div class="msg-parties">
                            <span class="msg-exp">👨‍👦 <?= htmlspecialchars($msg['expediteur_nom']) ?></span>
                            <span class="msg-arrow">←</span>
                            <span class="msg-dest">
                                👨‍🏫 <?= htmlspecialchars($msg['destinataire_nom']) ?>
                            </span>
                        </div>
                        <div class="msg-date"><?= $msg['date_envoi'] ?></div>
                    </div>
                    <div class="msg-sujet">📌 <?= htmlspecialchars($msg['sujet']) ?></div>
                    <div class="msg-contenu"><?= htmlspecialchars($msg['contenu']) ?></div>
                    <div>
                        <?php if ($msg['statut'] === 'approuve'): ?>
                            <span class="badge badge-ok">✅ مُوافق عليها</span>
                        <?php else: ?>
                            <span class="badge badge-refused">❌ مرفوضة</span>
                        <?php endif; ?>
                        <div class="traite-date">
                            🕐 عولجت في: <?= $msg['date_traitement'] ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>
</body>
</html>