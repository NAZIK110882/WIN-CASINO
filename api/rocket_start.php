<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

$data = json_decode(file_get_contents('php://input'), true);
$bet = floatval($data['bet'] ?? 0);

if ($bet <= 0) {
    echo json_encode(['error' => 'Некоректна ставка']);
    exit;
}

$host = '127.0.0.1';
$db   = 'casino_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $userId = 1;
    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$userRow || $userRow['balance'] < $bet) {
        echo json_encode(['error' => 'Недостатньо коштів на балансі']);
        exit;
    }

    $newBalance = floatval($userRow['balance']) - $bet;

    $updateStmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
    $updateStmt->execute([$newBalance, $userId]);

    // Рандомний вибух від 1.30x до 3.50x
    $crashPoint = round(1.30 + (lcg_value() * 2.20), 2);

    echo json_encode([
        'success' => true,
        'new_balance' => $newBalance,
        'crash_point' => $crashPoint
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => 'Помилка бази даних']);
}
?>