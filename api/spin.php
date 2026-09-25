<?php
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$betAmount = isset($input['bet']) ? (float)$input['bet'] : 0;

if ($betAmount <= 0) {
    echo json_encode(['error' => 'Ставка має бути більше нуля']);
    exit;
}

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=casino_db;charset=utf8mb4", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Помилка БД']);
    exit;
}

$userId = 1; 

$stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userRow = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userRow || $userRow['balance'] < $betAmount) {
    echo json_encode(['error' => 'Недостатньо коштів']);
    exit;
}

$symbols = ['🍒', '🍋', '🔔', '💎', '7️⃣', '🍉', '🍇', '🍀'];
$slot1 = $symbols[array_rand($symbols)];
$slot2 = $symbols[array_rand($symbols)];
$slot3 = $symbols[array_rand($symbols)];

$winAmount = 0;
if ($slot1 === $slot2 && $slot2 === $slot3) {
    if ($slot1 === '7️⃣') $winAmount = $betAmount * 50; 
    elseif ($slot1 === '💎') $winAmount = $betAmount * 25;
    else $winAmount = $betAmount * 10;
} elseif ($slot1 === $slot2 || $slot2 === $slot3 || $slot1 === $slot3) {
    $winAmount = $betAmount * 2; 
}

$newBalance = $userRow['balance'] - $betAmount + $winAmount;

try {
    $pdo->beginTransaction();
    $updateStmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
    $updateStmt->execute([$newBalance, $userId]);
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['error' => 'Помилка транзакції']);
    exit;
}

echo json_encode([
    'success' => true,
    'result' => [$slot1, $slot2, $slot3],
    'bet' => $betAmount,
    'win' => $winAmount,
    'new_balance' => $newBalance
]);
?>