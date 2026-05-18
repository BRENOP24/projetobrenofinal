<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';

$numero = $_POST['numero'] ?? '';

if (empty($numero)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'O identificador não pode estar vazio!']);
    exit;
}

try {
    // Agora aceita texto (Varchar), então enviamos a string direta
    $stmt = $pdo->prepare("INSERT INTO comandas (numero, status, criado_em) VALUES (?, 'aberto', NOW())");
    $stmt->execute([$numero]);

    echo json_encode(['sucesso' => true]);
} catch (PDOException $e) {
    echo json_encode(['sucesso' => false, 'mensagem' => "Erro no banco: " . $e->getMessage()]);
}