<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

// Отримуємо дані з POST-запиту від фронтенду
$data = json_decode(file_get_contents('php://input'), true);

$bet = floatval($data['bet'] ?? 0);
$multiplier = floatval($data['multiplier'] ?? 1.00);

if ($bet <= 0 || $multiplier < 1.00) {
    echo json_encode(['success' => false, 'error' => 'Некоректні дані ставки або коефіцієнта']);
    exit;
}

// Точний розрахунок виграшу
$winAmount = $bet * $multiplier;

// Підключення до бази даних
$host = '127.0.0.1';
$db   = 'casino_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Для тесту поки що беремо користувача з id = 1
    $userId = 1; 

    // Отримуємо поточний баланс
    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$userRow) {
        echo json_encode(['success' => false, 'error' => 'Користувача не знайдено']);
        exit;
    }

    $currentBalance = floatval($userRow['balance']);
    $newBalance = $currentBalance + $winAmount;

    // Оновлюємо баланс у БД
    $updateStmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
    $updateStmt->execute([$newBalance, $userId]);

    // Повертаємо результат на фронтенд
    echo json_encode([
        'success' => true,
        'win' => $winAmount,
        'new_balance' => $newBalance
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Помилка бази даних']);
}
?>