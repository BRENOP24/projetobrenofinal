<?php 
require_once 'config/sessao.php';
require_once 'config/conexao.php';

if (!in_array($_SESSION['nivel'], ['admin','garcom'])) {
    header("Location: dashboard.php");
    exit;
}

try {
    $stmt = $pdo->query("
        SELECT 
            m.id, 
            m.numero,
            EXISTS (
                SELECT 1 FROM pedidos p 
                WHERE p.origem_tipo = 'mesa'
                AND p.origem_id = m.id 
                AND p.situacao = 'aberto'
            ) AS ocupada
        FROM mesas m
        ORDER BY m.numero ASC
    ");

    $mesas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao carregar mesas: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Essencial para o celular -->
    <title>Gestão Breno - Mesas</title>
    <style>
        :root {
            --bg-principal: #f8fafc;
            --card-bg: #ffffff;
            --texto-principal: #1e293b;
            --texto-secundario: #64748b;
            --cor-livre: #10b981; /* Verde esmeralda moderno */
            --cor-ocupada: #ef4444; /* Vermelho vibrante */
            --cor-botao: #3b82f6; /* Azul moderno */
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
            --shadow-lg: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }

        body { 
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; 
            background: var(--bg-principal); 
            color: var(--texto-principal);
            margin: 0;
            padding: 16px; 
            box-sizing: border-box;
            display: flex;
            justify-content: center;
        }

        .container {
            width: 100%;
            max-width: 800px;
        }

        /* Topo / Cabeçalho */
        .topo { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 24px; 
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .topo h2 {
            font-size: 1.5rem;
            margin: 0;
            font-weight: 700;
        }

        .topo-acoes {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .btn-voltar {
            text-decoration: none; 
            color: var(--texto-secundario); 
            font-weight: 600;
            font-size: 0.95rem;
            transition: color 0.2s;
        }
        .btn-voltar:hover {
            color: var(--texto-principal);
        }

        /* Grid de Mesas */
        .grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); 
            gap: 16px; 
        }

        /* Cards de Mesa */
        .card { 
            padding: 24px 12px; 
            text-align: center; 
            border-radius: 16px; 
            color: #fff; 
            font-weight: 600; 
            font-size: 1.1rem;
            cursor: pointer; 
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); 
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            gap: 4px;
            -webkit-tap-highlight-color: transparent;
        }
        
        .card small {
            font-size: 0.8rem;
            opacity: 0.85;
            font-weight: 400;
        }

        .card:hover { 
            transform: translateY(-4px); 
            box-shadow: var(--shadow-md); 
        }

        .card:active {
            transform: translateY(1px);
        }

        .livre { 
            background: linear-gradient(135deg, #10b981, #059669); 
        }
        
        .ocupada { 
            background: linear-gradient(135deg, #ef4444, #dc2626); 
        }

        /* Botões Gerais */
        .btn { 
            padding: 10px 16px; 
            border: none; 
            border-radius: 10px; 
            cursor: pointer; 
            font-weight: 600; 
            font-size: 0.95rem;
            transition: all 0.2s;
        }
        
        .btn-nova { 
            background: var(--cor-botao); 
            color: #fff; 
            box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2);
        }
        .btn-nova:hover {
            background: #2563eb;
            box-shadow: 0 4px 6px rgba(59, 130, 246, 0.3);
        }
        
        /* Modal Totalmente Repaginado */
        .modal { 
            display: none; 
            position: fixed; 
            top: 0; left: 0; 
            width: 100%; height: 100%; 
            background: rgba(15, 23, 42, 0.4); /* Fundo escuro fosco moderno */
            backdrop-filter: blur(4px); /* Efeito de vidro borrado ao fundo */
            justify-content: center; 
            align-items: center; 
            z-index: 1000;
            padding: 16px;
            box-sizing: border-box;
        }
        
        .modal-content { 
            background: #fff; 
            padding: 24px; 
            border-radius: 20px; 
            width: 100%;
            max-width: 340px; 
            box-shadow: var(--shadow-lg);
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        .modal-content h3 {
            margin: 0 0 16px 0;
            font-size: 1.3rem;
            color: var(--texto-principal);
        }

        .modal-content input { 
            width: 100%; 
            padding: 12px; 
            margin-bottom: 20px; 
            border: 2px solid #e2e8f0; 
            border-radius: 10px; 
            box-sizing: border-box; 
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        
        .modal-content input:focus {
            outline: none;
            border-color: var(--cor-botao);
        }

        .modal-botoes {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        /* Ajuste fino para celulares pequenos */
        @media (max-width: 480px) {
            .topo h2 { font-size: 1.3rem; }
            .grid { grid-template-columns: repeat(2, 1fr); gap: 12px; } /* Garante 2 colunas organizadas no celular */
            .card { padding: 20px 10px; }
        }
    </style>
</head>
<body>

<div class="container">

    <div class="topo">
        <h2>🍽️ Controle Mesas</h2>
        <div class="topo-acoes">
            <a href="garcom.php" class="btn-voltar">← Voltar</a>
            <button class="btn btn-nova" onclick="abrirModal()">+ Nova Mesa</button>
        </div>
    </div>

    <div class="grid">
        <?php if (empty($mesas)): ?>
            <p style="grid-column: 1/-1; color: var(--texto-secundario); text-align: center;">Nenhuma mesa cadastrada.</p>
        <?php else: ?>
            <?php foreach($mesas as $m): ?>
                <div 
                    class="card <?= $m['ocupada'] ? 'ocupada' : 'livre' ?>"
                    onclick="irParaMesa(<?= $m['id'] ?>)"
                >
                    Mesa <?= htmlspecialchars($m['numero']) ?>
                    <small><?= $m['ocupada'] ? 'Ocupada' : 'Livre' ?></small>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<!-- Modal -->
<div id="modalMesa" class="modal">
    <div class="modal-content">
        <h3>Cadastrar Mesa</h3>
        <input type="number" id="nova_mesa_numero" placeholder="Número da mesa (Ex: 3)" inputmode="numeric">

        <div class="modal-botoes">
            <button class="btn" style="background:#f1f5f9; color: var(--texto-secundario);" onclick="fecharModal()">Cancelar</button>
            <button class="btn btn-nova" onclick="window.criarMesa()">Salvar</button>
        </div>
    </div>
</div>

<script>
function irParaMesa(id) {
    window.location.href = "abrir_pedido.php?tipo=mesa&id=" + id;
}

function abrirModal() {
    document.getElementById('modalMesa').style.display = 'flex';
    document.getElementById('nova_mesa_numero').focus();
}

function fecharModal() {
    document.getElementById('modalMesa').style.display = 'none';
    document.getElementById('nova_mesa_numero').value = '';
}
</script>

<script src="js/garcom.js"></script>

</body>
</html>
