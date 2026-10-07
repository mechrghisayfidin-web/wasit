<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professeur') {
    header('Location: ../auth/login.php');
    exit();
}

// جلب الرسائل الموافق عليها فقط
$stmt = $pdo->prepare("
    SELECT m.*,
           u_exp.nom AS expediteur_nom
    FROM messages m
    JOIN utilisateurs u_exp ON m.expediteur_id = u_exp.id
    WHERE m.destinataire_id = ? AND m.statut = 'approuve'
    ORDER BY m.date_traitement DESC
");
$stmt->execute([$_SESSION['user_id']]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wassit — رسائلي</title>
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
            transition: box-shadow 0.2s;
        }
        .msg-item:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .msg-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .msg-exp {
            font-weight: bold;
            color: #1a73e8;
            font-size: 15px;
        }
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
        }
        .badge-ok {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            background: #e6f4ea;
            color: #2e7d32;
            margin-top: 10px;
        }
        .empty { text-align: center; color: #aaa; padding: 40px; }
        .empty-icon { font-size: 48px; margin-bottom: 12px; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">

    <div class="card">
        <h2>📬 رسائل الأولياء</h2>

        <?php if (empty($messages)): ?>
            <div class="empty">
                <div class="empty-icon">📭</div>
                <p>لا توجد رسائل بعد</p>
            </div>
        <?php else: ?>
            <?php foreach ($messages as $msg): ?>
                <div class="msg-item">
                    <div class="msg-header">
                        <div class="msg-exp">
                            👨‍👦 <?= htmlspecialchars($msg['expediteur_nom']) ?>
                        </div>
                        <div class="msg-date"><?= $msg['date_envoi'] ?></div>
                    </div>
                    <div class="msg-sujet">📌 <?= htmlspecialchars($msg['sujet']) ?></div>
                    <div class="msg-contenu"><?= htmlspecialchars($msg['contenu']) ?></div>
                    <span class="badge-ok">✅ موافق عليها من الإدارة</span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>
</body>
</html>