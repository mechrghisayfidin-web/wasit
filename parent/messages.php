<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header('Location: ../auth/login.php');
    exit();
}

$success = '';
$error = '';

// إرسال رسالة
if (isset($_POST['action']) && $_POST['action'] === 'send') {
    $destinataire_id = $_POST['destinataire_id'];
    $sujet = trim($_POST['sujet']);
    $contenu = trim($_POST['contenu']);

    if (!empty($destinataire_id) && !empty($sujet) && !empty($contenu)) {
        $stmt = $pdo->prepare("
            INSERT INTO messages (expediteur_id, destinataire_id, sujet, contenu)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$_SESSION['user_id'], $destinataire_id, $sujet, $contenu]);
        $success = "تم إرسال رسالتك للمدير ✅ — في انتظار الموافقة";
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

// جلب رسائل الولي
$stmt = $pdo->prepare("
    SELECT m.*, 
           u_dest.nom AS destinataire_nom,
           u_dest.matiere AS destinataire_matiere
    FROM messages m
    JOIN utilisateurs u_dest ON m.destinataire_id = u_dest.id
    WHERE m.expediteur_id = ?
    ORDER BY m.date_envoi DESC
");
$stmt->execute([$_SESSION['user_id']]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wassit — الرسائل</title>
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
        .container {
            max-width: 800px;
            margin: 30px auto;
            padding: 0 16px;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 24px;
        }
        h2 { color: #333; margin-bottom: 20px; font-size: 18px; }
        label {
            display: block;
            font-size: 13px;
            color: #555;
            margin-bottom: 4px;
            font-weight: bold;
        }
        select, input, textarea {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
            font-family: inherit;
            outline: none;
            margin-bottom: 14px;
        }
        select:focus, input:focus, textarea:focus { border-color: #1a73e8; }
        textarea { resize: vertical; min-height: 120px; }
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
        .msg-item {
            border-bottom: 1px solid #f0f0f0;
            padding: 16px 0;
        }
        .msg-item:last-child { border-bottom: none; }
        .msg-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .msg-dest {
            font-weight: bold;
            color: #1a73e8;
            font-size: 15px;
        }
        .msg-date { color: #aaa; font-size: 12px; }
        .msg-sujet {
            color: #333;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 6px;
        }
        .msg-contenu {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-top: 8px;
        }
        .badge-wait { background: #fff3e0; color: #e65100; }
        .badge-ok { background: #e6f4ea; color: #2e7d32; }
        .badge-refused { background: #fdecea; color: #c0392b; }
        .empty { text-align: center; color: #aaa; padding: 24px; }
        .no-prof {
            background: #fff3e0;
            color: #e65100;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            text-align: center;
        }
    </style>
</head>
<body>

<header>
    <h1>✉️ الرسائل</h1>
    <a href="dashboard.php">🏠 الرئيسية</a>
</header>

<div class="container">

    <!-- إرسال رسالة -->
    <div class="card">
        <h2>📨 إرسال رسالة لأستاذ</h2>

        <?php if ($success): ?>
            <div class="success"><?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>

        <?php if (empty($professeurs)): ?>
            <p class="no-prof">⚠️ لا يوجد أساتذة مرتبطون بأبنائك بعد</p>
        <?php else: ?>
        <form method="POST">
            <input type="hidden" name="action" value="send">
            <label>الأستاذ</label>
            <select name="destinataire_id" required>
                <option value="">— اختر الأستاذ —</option>
                <?php foreach ($professeurs as $prof): ?>
                    <option value="<?= $prof['id'] ?>">
                        <?= htmlspecialchars($prof['nom']) ?>
                        <?php if ($prof['matiere']): ?>
                            — <?= htmlspecialchars($prof['matiere']) ?>
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label>الموضوع</label>
            <input type="text" name="sujet" placeholder="موضوع الرسالة" required>
            <label>الرسالة</label>
            <textarea name="contenu" placeholder="اكتب رسالتك هنا..."></textarea>
            <button type="submit" class="btn-primary">إرسال للمدير للموافقة 📨</button>
        </form>
        <?php endif; ?>
    </div>

    <!-- رسائلي -->
    <div class="card">
        <h2>📬 رسائلي</h2>
        <?php if (empty($messages)): ?>
            <p class="empty">لا توجد رسائل بعد</p>
        <?php else: ?>
            <?php foreach ($messages as $msg): ?>
                <div class="msg-item">
                    <div class="msg-header">
                        <div class="msg-dest">
                            👨‍🏫 <?= htmlspecialchars($msg['destinataire_nom']) ?>
                            <?php if ($msg['destinataire_matiere']): ?>
                                — <?= htmlspecialchars($msg['destinataire_matiere']) ?>
                            <?php endif; ?>
                        </div>
                        <div class="msg-date"><?= $msg['date_envoi'] ?></div>
                    </div>
                    <div class="msg-sujet">📌 <?= htmlspecialchars($msg['sujet']) ?></div>
                    <div class="msg-contenu"><?= htmlspecialchars($msg['contenu']) ?></div>
                    <div>
                        <?php if ($msg['statut'] === 'en_attente'): ?>
                            <span class="badge badge-wait">⏳ في انتظار موافقة المدير</span>
                        <?php elseif ($msg['statut'] === 'approuve'): ?>
                            <span class="badge badge-ok">✅ تمت الموافقة وأُرسلت</span>
                        <?php else: ?>
                            <span class="badge badge-refused">❌ رُفضت من المدير</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>
</body>
</html>