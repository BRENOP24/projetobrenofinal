<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';

$usuario_id = $_SESSION['usuario_id'];
$usuario_nome = $_SESSION['usuario_nome'];

// 1. Verifica se existe um caixa aberto
$stmt = $pdo->prepare("SELECT * FROM controle_caixas WHERE usuario_id = ? AND status = 'aberto' LIMIT 1");
$stmt->execute([$usuario_id]);
$caixa_atual = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ABERTURA DE CAIXA
    if (isset($_POST['btn_abrir'])) {
        // Limpa o valor para o formato do banco (ex: 10,50 -> 10.50)
        $valor_inicial = str_replace(',', '.', $_POST['valor_inicial']);
        
        try {
            // No Postgres, podemos omitir data_abertura se o DEFAULT for current_timestamp
            $sql = "INSERT INTO controle_caixas (usuario_id, valor_inicial, status, data_abertura) 
                    VALUES (?, ?, 'aberto', CURRENT_TIMESTAMP)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$usuario_id, $valor_inicial]);
            
            header("Location: caixas.php");
            exit;
        } catch (PDOException $e) {
            die("Erro ao abrir caixa: " . $e->getMessage());
        }
    }

    // FECHAMENTO DE CAIXA
    if (isset($_POST['btn_fechar']) && $caixa_atual) {
        $contado = $_POST['contado']; // Espera um array vindo do form
        
        // Garante que os valores do array sejam numéricos para o sum
        $valor_total_informado = array_sum(array_map(function($v) { 
            return (float)str_replace(',', '.', $v); 
        }, $contado));
        
        $detalhes_json = json_encode($contado);

        try {
            $stmt = $pdo->prepare("UPDATE controle_caixas SET 
                valor_final_informado = ?, 
                observacao_adm = ?, 
                data_fechamento = CURRENT_TIMESTAMP, 
                status = 'fechado' 
                WHERE id = ? AND status = 'aberto'");
            
            $stmt->execute([$valor_total_informado, $detalhes_json, $caixa_atual['id']]);
            
            header("Location: caixas.php?sucesso=fechado");
            exit;
        } catch (PDOException $e) {
            die("Erro ao fechar caixa: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Movimentação de Caixa - Gestão Breno</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f6f9; padding: 20px; }
        .container-fluid { max-width: 900px; margin: auto; background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        
        /* Cabeçalho Padronizado */
        .header-section { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #eee; margin-bottom: 25px; padding-bottom: 15px; }
        .btn-voltar { background: #6c757d; color: #fff; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-size: 0.9rem; font-weight: bold; transition: 0.3s; }
        .btn-voltar:hover { background: #495057; }

        /* Cards e Layout */
        .caixa-status { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; text-align: center; }
        .status-aberto { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        
        .grid-valores { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px; }
        .card-pagamento { background: #f8f9fa; padding: 15px; border-radius: 8px; border-top: 4px solid #007bff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .card-pagamento label { display: block; font-size: 0.85rem; font-weight: bold; color: #555; margin-bottom: 8px; }
        .input-valor { width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 5px; font-size: 1.1rem; font-weight: bold; box-sizing: border-box; }
        
        .btn-acao { width: 100%; padding: 15px; border: none; border-radius: 8px; font-size: 1.1rem; font-weight: bold; cursor: pointer; color: white; margin-top: 25px; transition: 0.3s; }
        .btn-abrir { background: #28a745; }
        .btn-abrir:hover { background: #218838; }
        .btn-fechar { background: #dc3545; }
        .btn-fechar:hover { background: #c82333; }
        
        .info-user { font-size: 0.9rem; color: #666; margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="header-section">
        <h2 style="margin:0;">🏧 Movimentação de Caixa</h2>
        <a href="dashboard.php" class="btn-voltar">⬅ Voltar ao Painel</a>
    </div>

    <div class="info-user">
        Operador: <strong><?= $usuario_nome ?></strong>
    </div>

    <?php if (!$caixa_atual): ?>
        <div style="text-align: center; padding: 20px;">
            <h3 style="color: #28a745; margin-top: 0;">Abrir Novo Caixa</h3>
            <p>Informe o valor disponível em dinheiro para troco:</p>
            
            <form method="POST" style="max-width: 400px; margin: auto;">
                <label style="display:block; margin-bottom: 10px; font-weight:bold;">Valor Inicial (Fundo de Reserva):</label>
                <input type="text" name="valor_inicial" class="input-valor" style="text-align: center;" placeholder="0,00" required>
                <button type="submit" name="btn_abrir" class="btn-acao btn-abrir">Iniciar Expediente</button>
            </form>
        </div>

    <?php else: ?>
        <div class="caixa-status status-aberto">
            CAIXA ABERTO DESDE: <?= date('d/m/Y H:i', strtotime($caixa_atual['data_abertura'])) ?>
        </div>

        <form method="POST">
            <h3 style="margin-bottom: 5px;">Finalizar Turno</h3>
            <p style="color: #666; font-size: 0.9rem;">Conte os valores físicos na gaveta e informe abaixo:</p>

            <div class="grid-valores">
                <div class="card-pagamento">
                    <label>💵 Dinheiro (Espécie)</label>
                    <input type="number" step="0.01" name="contado[dinheiro]" class="input-valor" placeholder="0.00" required>
                </div>

                <div class="card-pagamento" style="border-top-color: #32bcad;">
                    <label>📱 PIX</label>
                    <input type="number" step="0.01" name="contado[pix]" class="input-valor" placeholder="0.00" required>
                </div>

                <div class="card-pagamento" style="border-top-color: #ffc107;">
                    <label>💳 Cartão de Crédito</label>
                    <input type="number" step="0.01" name="contado[credito]" class="input-valor" placeholder="0.00" required>
                </div>

                <div class="card-pagamento" style="border-top-color: #17a2b8;">
                    <label>💳 Cartão de Débito</label>
                    <input type="number" step="0.01" name="contado[debito]" class="input-valor" placeholder="0.00" required>
                </div>
            </div>

            <button type="submit" name="btn_fechar" class="btn-acao btn-fechar" onclick="return confirm('Deseja realmente fechar o caixa e enviar para conferência?')">
                Encerrar e Enviar para Conferência
            </button>
        </form>
    <?php endif; ?>
</div>

</body>
</html>