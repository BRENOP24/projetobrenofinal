<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';

// 🔒 valida entrada
$tipo = $_GET['tipo'] ?? null;
$id   = $_GET['id'] ?? null;

if (!$tipo || !$id || !in_array($tipo, ['mesa','comanda'])) {
    die("Dados inválidos");
}

// 🔹 verifica pedido aberto
$stmt = $pdo->prepare("
    SELECT id FROM pedidos 
    WHERE origem_tipo = :tipo 
    AND origem_id = :id 
    AND situacao = 'aberto'
    LIMIT 1
");

$stmt->execute([
    ':tipo' => $tipo,
    ':id' => $id
]);

$pedido = $stmt->fetch();

if ($pedido) {
    $pedido_id = $pedido['id'];

} else {

    $stmt = $pdo->prepare("
        INSERT INTO pedidos (
            usuario_id,
            tipo_venda,
            origem_tipo,
            origem_id,
            situacao,
            valor_total,
            criado_em
        ) VALUES (
            :usuario,
            :tipo_venda,
            :origem_tipo,
            :origem_id,
            'aberto',
            0,
            NOW()
        )
        RETURNING id
    ");

    $stmt->execute([
        ':usuario' => $_SESSION['usuario_id'],
        ':tipo_venda' => 'local',
        ':origem_tipo' => $tipo,
        ':origem_id' => $id
    ]);

    $pedido_id = $stmt->fetchColumn();
}

// 🔥 REDIRECIONAMENTO
if ($tipo === 'mesa') {
    header("Location: mesa_pedido.php?pedido_id=" . $pedido_id);
} else {
    header("Location: comanda_pedido.php?pedido_id=" . $pedido_id);
}

exit;