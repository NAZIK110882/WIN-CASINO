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

// ЗМІНЕНО: Штучно зменшуємо шанс на льодяник. 
// Тепер у пулі багато дешевих символів і лише один льодяник.
$symbols = [
    '🍎', '🍎', '🍎', '🍎', 
    '🍇', '🍇', '🍇', '🍇', 
    '🍉', '🍉', '🍉', 
    '🍌', '🍌', '🍌', 
    '🫐', '🫐', 
    '🍬', '🍬', 
    '🍭' // Льодяник тепер випадає набагато рідше
];

$grid = [];
$counts = [];

for ($i = 0; $i < 25; $i++) {
    $sym = $symbols[array_rand($symbols)];
    $grid[] = $sym;
    $counts[$sym] = ($counts[$sym] ?? 0) + 1;
}

$winAmount = 0;
$winningSymbols = [];
$bonusGame = false;

// Звичайні виграші (від 8 штук), крім льодяника
foreach ($counts as $sym => $count) {
    if ($sym !== '🍭' && $count >= 8) {
        $multiplier = ($count - 7) * 0.5; 
        if ($sym === '🍬') $multiplier *= 3;
        $winAmount += $betAmount * $multiplier;
        $winningSymbols[] = $sym;
    }
}

// БОНУС: Якщо випало 3 або більше льодяників
if (isset($counts['🍭']) && $counts['🍭'] >= 3) {
    $bonusGame = true;
    $winAmount += $betAmount * 50; // Бонусний множник х50
    $winningSymbols[] = '🍭';
}

$newBalance = $userRow['balance'] - $betAmount + $winAmount;

try {
    $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?")->execute([$newBalance, $userId]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Помилка збереження']);
    exit;
}

echo json_encode([
    'success' => true,
    'grid' => $grid,
    'win' => $winAmount,
    'new_balance' => $newBalance,
    'winning_symbols' => $winningSymbols,
    'bonus_game' => $bonusGame
]);
?>