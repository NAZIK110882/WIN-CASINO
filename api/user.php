<?php
header('Content-Type: application/json');
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=casino_db;charset=utf8mb4", 'root', '');
    $stmt = $pdo->prepare("SELECT username, balance FROM users WHERE id = 1");
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'username' => $user['username'], 'balance' => $user['balance']]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Помилка БД']);
}
?>