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

$threadId = (int)($_POST['thread_id'] ?? 0);
if ($threadId <= 0) {
    $_SESSION['forum_flash'] = ['type' => 'error', 'text' => 'Invalid thread ID.'];
    header('Location: /?page=pages/home');
    exit;
}

$conn = db_conn();
$inTransaction = false;
try {
    $conn->begin_transaction();
    $inTransaction = true;

    $stmt = $conn->prepare('SELECT id, user_id, title FROM threads WHERE id = ?');
    $stmt->bind_param('i', $threadId);
    $stmt->execute();
    $thread = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$thread) {
        $conn->rollback();
        $inTransaction = false;
        $_SESSION['forum_flash'] = ['type' => 'error', 'text' => 'Thread not found.'];
    } elseif (($me['role'] ?? '') !== 'admin' && (int)$thread['user_id'] !== (int)$me['id']) {
        $conn->rollback();
        $inTransaction = false;
        $_SESSION['forum_flash'] = ['type' => 'error', 'text' => 'You do not have permission to delete this thread.'];
    } else {
        $stmt = $conn->prepare('DELETE FROM replies WHERE thread_id = ?');
        $stmt->bind_param('i', $threadId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare('DELETE FROM threads WHERE id = ?');
        $stmt->bind_param('i', $threadId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $inTransaction = false;
        $_SESSION['forum_flash'] = [
            'type' => 'success',
            'text' => 'Thread "' . $thread['title'] . '" has been deleted.'
        ];
    }
} catch (Throwable $e) {
    if ($inTransaction) {
        $conn->rollback();
    }
    $_SESSION['forum_flash'] = ['type' => 'error', 'text' => 'Failed to delete thread: ' . $e->getMessage()];
}
$conn->close();

header('Location: /?page=pages/home');
exit;
