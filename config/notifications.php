<?php

function createNotification(
    mysqli $conn,
    int $user_id,
    string $title,
    string $message,
    string $type = "system"
) {
    $stmt = $conn->prepare("
        INSERT INTO notifications
            (user_id, title, message, type)
        VALUES
            (?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "isss",
        $user_id,
        $title,
        $message,
        $type
    );

    return $stmt->execute();
}
?>