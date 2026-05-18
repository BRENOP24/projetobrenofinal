<?php
require_once 'config/conexao.php';
require_once 'config/sessao.php';

$id_cliente = $_POST['id_cliente'];
$valor      = $_POST['valor_total'];
$vencimento = $_POST['data_vencimento'];
$descricao  = $_POST['descricao'] ?? '';

$stmt = $pdo->prepare("
    INSERT INTO contas_receber (
        id_cliente,
        valor_total,
        data_vencimento,
        descricao,
        status,
        data_criacao
    ) VALUES (?, ?, ?, ?, 'Pendente', NOW())
");

$stmt->execute([
    $id_cliente,
    $valor,
    $vencimento,
    $descricao
]);

header("Location: contas_receber.php");
exit;