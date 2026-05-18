<?php
header('Content-Type: application/json');
require_once 'config/conexao.php';

$json = file_get_contents("php://input");

if (!$json) {
    echo json_encode(['success' => false, 'erro' => 'Nenhum dado recebido']);
    exit;
}

$data = json_decode($json, true);

if (!is_array($data)) {
    echo json_encode(['success' => false, 'erro' => 'JSON inválido']);
    exit;
}

$numero = $data['numero'] ?? null;

if (!$numero) {
    echo json_encode(['success' => false, 'erro' => 'Número da mesa não recebido']);
    exit;
}

try {
    // Evita duplicidade
    $stmtCheck = $pdo->prepare("SELECT id FROM mesas WHERE numero = ?");
    $stmtCheck->execute([$numero]);

    if ($stmtCheck->fetch()) {
        echo json_encode(['success' => false, 'erro' => 'Mesa já existe']);
        exit;
    }

    // Inserção
    $stmt = $pdo->prepare("INSERT INTO mesas (numero) VALUES (?)");
    $stmt->execute([$numero]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'erro' => 'Erro SQL: ' . $e->getMessage()
    ]);
}