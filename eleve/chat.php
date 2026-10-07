<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'eleves') {
    header('Location: ../auth/login.php');
    exit();
}

$eleve_id = $_SESSION['user_id'];

// إرسال رسالة
if (isset($_POST['action']) && $_POST['action'] === 'send') {
    $message = trim($_POST['message']);
    if (!empty($message)) {
        $stmt = $pdo->prepare("
            INSERT INTO chat_messages (classe_id, eleve_id, message)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$_SESSION['classe_id'], $eleve_id, $message]);
    }
    header('Location: chat.php');
    exit();
}

// جلب آخر 50 رسالة
$stmt = $pdo->prepare("
    SELECT c.*, e.nom AS eleve_nom
    FROM chat_messages c
    JOIN eleves e ON c.eleve_id = e.id
    WHERE c.classe_id = ?
    ORDER BY c.date_envoi DESC
    LIMIT 50
");
$stmt->execute([$_SESSION['classe_id']]);
$messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

// اسم القسم
$stmt = $pdo->prepare("SELECT nom FROM classes WHERE id = ?");
$stmt->execute([$_SESSION['classe_id']]);
$classe_nom = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wassit — دردشة القسم</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f4f8; }
        .chat-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            display: flex;
            flex-direction: column;
            height: calc(100vh - 60px);
            overflow: hidden;
        }
        .chat-header {
            background: #1a73e8;
            color: white;
            padding: 16px 24px;
            font-size: 16px;
            font-weight: bold;
        }
        .chat-header span {
            display: block;
            font-size: 12px;
            opacity: 0.8;
            font-weight: normal;
            margin-top: 2px;
        }
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .msg-bubble {
            max-width: 75%;
            padding: 10px 14px;
            border-radius: 14px;
            font-size: 14px;
            line-height: 1.5;
            position: relative;
        }
        .msg-bubble.mine {
            background: #1a73e8;
            color: white;
            align-self: flex-start;
            border-bottom-left-radius: 4px;
        }
        .msg-bubble.other {
            background: #f0f0f0;
            color: #333;
            align-self: flex-end;
            border-bottom-right-radius: 4px;
        }
        .msg-author {
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 4px;
            opacity: 0.8;
        }
        .msg-time {
            font-size: 10px;
            opacity: 0.6;
            margin-top: 4px;
            text-align: left;
        }
        .chat-input {
            display: flex;
            gap: 10px;
            padding: 16px;
            border-top: 1px solid #f0f0f0;
        }
        .chat-input input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 24px;
            font-size: 14px;
            font-family: inherit;
            outline: none;
        }
        .chat-input input:focus { border-color: #1a73e8; }
        .chat-input button {
            background: #1a73e8;
            color: white;
            border: none;
            border-radius: 50%;
            width: 44px;
            height: 44px;
            font-size: 18px;
            cursor: pointer;
            flex-shrink: 0;
        }
        .chat-input button:hover { background: #1558b0; }
        .empty { text-align: center; color: #aaa; padding: 40px; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content" style="padding: 16px;">
    <div class="chat-card">
        <div class="chat-header">
            💬 دردشة القسم
            <span>📚 <?= htmlspecialchars($classe_nom) ?></span>
        </div>

        <div class="chat-messages" id="chatBox">
            <?php if (empty($messages)): ?>
                <p class="empty">لا توجد رسائل بعد — كن أول من يكتب! 👋</p>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                    <?php $is_mine = $msg['eleve_id'] == $eleve_id; ?>
                    <div class="msg-bubble <?= $is_mine ? 'mine' : 'other' ?>">
                        <?php if (!$is_mine): ?>
                            <div class="msg-author"><?= htmlspecialchars($msg['eleve_nom']) ?></div>
                        <?php endif; ?>
                        <?= htmlspecialchars($msg['message']) ?>
                        <div class="msg-time"><?= date('H:i', strtotime($msg['date_envoi'])) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <form method="POST" class="chat-input">
            <input type="hidden" name="action" value="send">
            <input type="text" name="message" placeholder="اكتب رسالتك..." required autofocus>
            <button type="submit">➤</button>
        </form>
    </div>
</div>

<script>
// نزول تلقائي لآخر رسالة
const chatBox = document.getElementById('chatBox');
chatBox.scrollTop = chatBox.scrollHeight;
</script>

</body>
</html>