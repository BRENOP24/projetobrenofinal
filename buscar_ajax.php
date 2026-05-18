<?php
require_once 'config/conexao.php';

$tipo = $_GET['tipo'] ?? '';
$q = $_GET['q'] ?? '';

if ($tipo == 'cliente') {
    $stmt = $pdo->prepare("SELECT id, nome, cpf, endereco FROM clientes WHERE nome LIKE ? OR cpf LIKE ? LIMIT 10");
    $stmt->execute(["%$q%", "%$q%"]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

if ($tipo == 'produto') {
    $stmt = $pdo->prepare("SELECT id, nome, preco_venda FROM produtos WHERE nome LIKE ? OR codigo_barras LIKE ? LIMIT 10");
    $stmt->execute(["%$q%", "%$q%"]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}