<?php
require_once 'config/sessao.php'; 
require_once 'config/conexao.php';

if ($_SESSION['nivel'] !== 'admin') {
    die('Acesso negado');
}

$id_sangria = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id_sangria) {
    die('Sangria inválida.');
}

// Busca os detalhes exatos desta sangria usando a coluna correta (data_hora)
$sql = "
    SELECT s.*, u.nome as operador
    FROM sangrias s
    JOIN controle_caixas c ON s.caixa_id = c.id
    JOIN usuarios u ON c.usuario_id = u.id
    WHERE s.id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_sangria]);
$sangria = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sangria) {
    die('Comprovante não encontrado.');
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Comprovante de Sangria #<?= $sangria['id'] ?></title>
    <style>
        /* Estilização otimizada para cupom térmico e impressão limpa */
        body { font-family: 'Courier New', Courier, monospace; color: #000; width: 280px; margin: auto; padding: 10px; font-size: 12px; line-height: 1.4; }
        .text-center { text-align: center; }
        .bold { font-weight: bold; }
        .linha-divisoria { border-top: 1px dashed #000; margin: 10px 0; }
        .valor-grande { font-size: 16px; margin: 10px 0; }
        .assinatura { margin-top: 50px; text-align: center; border-top: 1px solid #000; padding-top: 5px; }
        
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print();">

    <div class="text-center bold">
        <span>GESTÃO BRENO</span><br>
        <span>COMPROVANTE DE SANGRIA DE CAIXA</span>
    </div>
    
    <div class="linha-divisoria"></div>
    
    <div>
        <span class="bold">Comprovante Nº:</span> #<?= $sangria['id'] ?><br>
        <span class="bold">Data/Hora:</span> <?= date('d/m/Y H:i:s', strtotime($sangria['data_hora'])) ?><br>
        <span class="bold">Caixa Ref:</span> Nº <?= $sangria['caixa_id'] ?><br>
        <span class="bold">Operador:</span> <?= htmlspecialchars($sangria['operador']) ?><br>
    </div>
    
    <div class="linha-divisoria"></div>
    
    <div class="text-center bold valor-grande">
        VALOR: R$ <?= number_format($sangria['valor'], 2, ',', '.') ?>
    </div>
    
    <div class="linha-divisoria"></div>
    
    <div>
        <span class="bold">Motivo:</span> <?= htmlspecialchars($sangria['motivo']) ?><br>
        <span class="bold">Obs:</span> <?= htmlspecialchars($sangria['observacao'] ?: '-') ?>
    </div>
    
    <div class="assinatura">
        Visto Responsável / Admin
    </div>
    
    <p class="text-center" style="font-size: 9px; margin-top: 30px;">Sistema Gestão Breno - Interno</p>

</body>
</html>