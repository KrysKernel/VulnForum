<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /?page=pages/home');
    exit;
}

$me = current_user();
if (!$me) {
    header('Location: /?page=pages/login');
    exit;
}

$replyId = (int)($_POST['reply_id'] ?? 0);
$fallbackThreadId = (int)($_POST['thread_id'] ?? 0);
$redirectUrl = $fallbackThreadId > 0 ? '/?page=pages/thread&id=' . $fallbackThreadId : '/?page=pages/home';

if ($replyId <= 0) {
    $_SESSION['forum_flash'] = ['type' => 'error', 'text' => 'Invalid reply ID.'];
    header('Location: ' . $redirectUrl);
    exit;
}

$conn = db_conn();
try {
    $stmt = $conn->prepare('SELECT id, thread_id, user_id FROM replies WHERE id = ?');
    $stmt->bind_param('i', $replyId);
    $stmt->execute();
    $reply = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$reply) {
        $_SESSION['forum_flash'] = ['type' => 'error', 'text' => 'Reply not found.'];
    } else {
        $redirectUrl = '/?page=pages/thread&id=' . (int)$reply['thread_id'];
        if (($me['role'] ?? '') !== 'admin' && (int)$reply['user_id'] !== (int)$me['id']) {
            $_SESSION['forum_flash'] = ['type' => 'error', 'text' => 'You do not have permission to delete this reply.'];
        } else {
            $stmt = $conn->prepare('DELETE FROM replies WHERE id = ?');
            $stmt->bind_param('i', $replyId);
            $stmt->execute();
            $stmt->close();

            $_SESSION['forum_flash'] = ['type' => 'success', 'text' => 'Reply has been deleted.'];
        }
    }
} catch (Throwable $e) {
    $_SESSION['forum_flash'] = ['type' => 'error', 'text' => 'Failed to delete reply: ' . $e->getMessage()];
}
$conn->close();

header('Location: ' . $redirectUrl);
exit;
