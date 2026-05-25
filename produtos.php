<?php
// ==========================================
// CONFIGURAÇÕES DO CLOUDINARY
// ==========================================
define('CLOUDINARY_CLOUD_NAME', 'Raiz');
define('CLOUDINARY_API_KEY', '591916441776592');
define('CLOUDINARY_API_SECRET', 'SyY1qSVlTc9C1egsVUlfMACCU_g');

$mensagem_alerta = "";

// Lógica de salvamento do produto
if (isset($_POST['btn_salvar'])) {
    $url_imagem_final = null;

    // Verificar se uma foto foi enviada
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['imagem']['tmp_name'];
        $timestamp = time();
        
        // Gerar assinatura de segurança para a API do Cloudinary
        $sign_string = "timestamp=$timestamp" . CLOUDINARY_API_SECRET;
        $signature = sha1($sign_string);

        // Envio dos dados via cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.cloudinary.com/v1_1/" . CLOUDINARY_CLOUD_NAME . "/image/upload");
        curl_setopt($ch, CURLOPT_POST, true);
        
        $post_fields = [
            'file' => new CURLFile($file_tmp),
            'api_key' => CLOUDINARY_API_KEY,
            'timestamp' => $timestamp,
            'signature' => $signature
        ];
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            $mensagem_alerta = "<div class='alert error'>Erro na comunicação com o Cloudinary: " . $err . "</div>";
        } else {
            $response_data = json_decode($response, true);
            if (isset($response_data['secure_url'])) {
                // Sucesso: temos a URL da internet!
                $url_imagem_final = $response_data['secure_url'];
            } else {
                $msg_erro = $response_data['error']['message'] ?? 'Erro desconhecido';
                $mensagem_alerta = "<div class='alert error'>Erro ao subir imagem: " . $msg_erro . "</div>";
            }
        }
    }

    // Coleta dos demais campos do formulário
    $codigo_barras   = $_POST['codigo_barras'] ?? '';
    $nome            = $_POST['nome'] ?? '';
    $preco           = $_POST['preco'] ?? '';
    $unidade_medida  = $_POST['unidade_medida'] ?? 'UN';
    $estoque         = $_POST['estoque'] ?? 0;
    $categoria_id    = $_POST['categoria_id'] ?? '';
    $aparecer_online = $_POST['aparecer_online'] ?? 'N';

    // -----------------------------------------------------------------
    // SUA LOGICA DE INSERT NO BANCO DE DADOS ENTRA AQUI
    // -----------------------------------------------------------------
    // Na sua query, a coluna 'imagem' deve receber a variável $url_imagem_final
    // Exemplo:
    // $stmt = $pdo->prepare("INSERT INTO produtos (...) VALUES (..., :imagem)");
    // $stmt->bindValue(':imagem', $url_imagem_final);
    // $stmt->execute();
    // -----------------------------------------------------------------
    
    if (empty($mensagem_alerta)) {
        $mensagem_alerta = "<div class='alert success'>📦 Produto gravado com sucesso no sistema!</div>";
    }
}

// Lógica simulada para os produtos da tabela (substitua pela sua consulta do banco)
// Note que adicionei URLs reais/exemplo do Cloudinary ou caminhos antigos para demonstração
$produtos = [
    [
        'id' => 1,
        'imagem' => 'https://res.cloudinary.com/' . CLOUDINARY_CLOUD_NAME . '/image/upload/v123456/exemplo_coca.jpg', // Exemplo Cloudinary
        'codigo_barras' => '1213',
        'nome' => 'Coca Cola Lata 350ml',
        'categoria' => 'Bebidas',
        'preco' => '8,00',
        'estoque' => '8 UN'
    ],
    [
        'id' => 4,
        'imagem' => 'uploads/produtos/13154142f8f43f67b1c3742297e14777.jpg', // Exemplo antigo local
        'codigo_barras' => '2563',
        'nome' => 'Cafe Expresso',
        'categoria' => 'Bebidas',
        'preco' => '6,50',
        'estoque' => '11 UN'
    ],
    // Seus outros produtos viriam aqui do banco...
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Produtos - Breno</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-700: #374151;
            --text-main: #1f2937;
        }
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #f8fafc; 
            color: var(--text-main);
            margin: 0; padding: 20px;
        }
        .container { max-width: 1200px; margin: auto; }
        /* Cabeçalho */
        .header { 
            display: flex; justify-content: space-between; align-items: center; 
            margin-bottom: 30px; background: white; padding: 20px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .header h2 { margin: 0; font-weight: 700; color: var(--gray-700); }
        .btn-voltar { background: var(--gray-100); color: var(--gray-700); padding: 8px 16px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 500; transition: 0.3s; }
        .btn-voltar:hover { background: var(--gray-200); }

        /* Alertas */
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; font-size: 14px; }
        .success { background: #dcfce7; color: #166534; }
        .error { background: #fee2e2; color: #991b1b; }
        .warning { background: #fef3c7; color: #92400e; }

        /* Card de Cadastro */
        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .card-title { font-size: 16px; font-weight: 600; margin-bottom: 20px; color: var(--gray-700); display: block; }

        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; }
        .form-group { display: flex; flex-direction: column; gap: 5px; }
        .form-group label { font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; }
        
        input, select {
            padding: 10px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; outline: none; transition: 0.2s;
        }
        input:focus, select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }

        .btn-save { 
            grid-column: 1 / -1; background: var(--primary); color: white; border: none; padding: 12px; 
            border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 15px; transition: 0.3s; margin-top: 10px;
        }
        .btn-save:hover { background: var(--primary-hover); transform: translateY(-1px); }

        /* Filtros */
        .filter-bar { background: white; padding: 15px; border-radius: 12px; margin-bottom: 20px; display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
        .btn-filter { background: var(--gray-700); color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; height: 41px; }

        /* Tabela */
        .table-container { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; }
        thead { background: #f9fafb; border-bottom: 1px solid var(--gray-200); }
        th { padding: 15px; text-align: left; font-size: 12px; text-transform: uppercase; color: #6b7280; letter-spacing: 0.05em; }
        td { padding: 15px; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
        tr:hover { background-color: #fbfbfb; }

        .img-prod { width: 45px; height: 45px; object-fit: cover; border-radius: 8px; background: #eee; }
        .badge-estoque { padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 600; }
        .low-stock { background: #fee2e2; color: #dc2626; }
        .ok-stock { background: #dcfce7; color: #059669; }

        .actions { display: flex; gap: 10px; }
        .btn-action { text-decoration: none; font-size: 18px; transition: 0.2s; }
        .btn-action:hover { transform: scale(1.2); }
        .status-inativo-linha { color: #9ca3af; }
    </style>
</head>
<body>

    <div class="container">
        
        <div class="header">
            <h2>📦 Gestão de Produtos</h2>
            <a href="dashboard.php" class="btn-voltar">⬅ Voltar ao Painel</a>
        </div>

        <?php echo $mensagem_alerta; ?>
        
        <div class="card">
            <span class="card-title">Novo Produto</span>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group" style="grid-column: span 1;">
                        <label>Cód. Barras</label>
                        <input type="text" name="codigo_barras" placeholder="Digite o código manual" required>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Nome do Produto</label>
                        <input type="text" name="nome" required placeholder="Ex: Coca Cola 2L">
                    </div>
                    <div class="form-group">
                        <label>Preço (R$)</label>
                        <input type="text" name="preco" required placeholder="0,00">
                    </div>
                    <div class="form-group">
                        <label>Unidade</label>
                        <select name="unidade_medida">
                            <option value="UN">UN</option>
                            <option value="KG">KG</option>
                            <option value="LT">LT</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Estoque Inicial</label>
                        <input type="number" name="estoque" value="0">
                    </div>
                    <div class="form-group">
                        <label>Categoria</label>
                        <select name="categoria_id" required>
                            <option value="">Selecione...</option>
                            <option value="1">Bebidas</option>
                            <option value="7">Diversos</option>
                            <option value="2">Doces</option>
                            <option value="10">Farmácia</option>
                            <option value="5">Lanches</option>
                            <option value="3">Limpeza</option>
                            <option value="8">Mercado</option>
                            <option value="4">Padaria</option>
                            <option value="6">Pizzas</option>
                            <option value="9">Uso Consumo</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Cardápio Online?</label>
                        <select name="aparecer_online">
                            <option value="N">Não</option>
                            <option value="S">Sim</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column: span 1;">
                        <label>Foto</label>
                        <input type="file" name="imagem" accept="image/*">
                    </div>
                </div>
                <button type="submit" name="btn_salvar" class="btn-save">Gravar Produto no Sistema</button>
            </form>
        </div>

        <form method="GET" class="filter-bar">
            <div class="form-group" style="flex: 2; min-width: 200px;">
                <label>Pesquisar</label>
                <input type="text" name="busca" value="" placeholder="Nome ou código...">
            </div>
            <div class="form-group" style="flex: 1; min-width: 150px;">
                <label>Categoria</label>
                <select name="filtro_categoria">
                    <option value="">Todas</option>
                    <option value="1">Bebidas</option>
                    <option value="7">Diversos</option>
                    <option value="2">Doces</option>
                    <option value="10">Farmácia</option>
                    <option value="5">Lanches</option>
                    <option value="3">Limpeza</option>
                    <option value="8">Mercado</option>
                    <option value="4">Padaria</option>
                    <option value="6">Pizzas</option>
                    <option value="9">Uso Consumo</option>
                </select>
            </div>
            <div class="form-group" style="flex: 1; min-width: 130px;">
                <label>Situação</label>
                <select name="status">
                    <option value="Ativo" selected>Ativos</option>
                    <option value="Inativo">Inativos</option>
                </select>
            </div>
            <button type="submit" class="btn-filter">Filtrar</button>
            <a href="produtos.php" class="btn-voltar" style="padding: 10px; height: 41px; display: flex; align-items: center;">Limpar</a>
        </form>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Foto</th>
                        <th>Cód. Barras</th>
                        <th>Produto / Categoria</th>
                        <th>Preço</th>
                        <th>Estoque</th>
                        <th style="text-align: center;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produtos as $prod): ?>
                        <tr>
                            <td>
                                <?php 
                                // Tratamento inteligente para a tag img:
                                // Se a imagem guardada for um link completo (Cloudinary), usa direto.
                                // Se for apenas o nome do arquivo antigo, concatena com a pasta local.
                                $caminho_imagem = (strpos($prod['imagem'], 'http') === 0) ? $prod['imagem'] : 'uploads/produtos/' . basename($prod['imagem']);
                                ?>
                                <img src="<?php echo !empty($prod['imagem']) ? $caminho_imagem : 'uploads/produtos/sem-foto.png'; ?>" class="img-prod">
                            </td>
                            <td style="font-family: monospace; font-weight: 600; color: #666;"><?php echo $prod['codigo_barras']; ?></td>
                            <td>
                                <div style="font-weight: 600; color: var(--gray-700);"><?php echo $prod['nome']; ?></div>
                                <div style="font-size: 12px; color: #9ca3af;"><?php echo $prod['categoria']; ?></div>
                            </td>
                            <td style="font-weight: 700;">R$ <?php echo $prod['preco']; ?></td>
                            <td>
                                <span class="badge-estoque ok-stock">
                                    <?php echo $prod['estoque']; ?>
                                </span>
                            </td>
                            <td>
                                <div class="actions" style="justify-content: center;">
                                    <a href="editar_produto.php?id=<?php echo $prod['id']; ?>" class="btn-action" title="Editar">✏️</a>
                                    <form method="POST" onsubmit="return confirm('Inativar este produto?')" style="margin: 0;">
                                        <input type="hidden" name="id_produto" value="<?php echo $prod['id']; ?>">
                                        <button type="submit" name="btn_inativar" class="btn-action" style="background:none; border:none; cursor:pointer;" title="Inativar">🚫</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
