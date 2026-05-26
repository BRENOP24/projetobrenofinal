<?php 
// --- FORÇAR EXIBIÇÃO DE ERROS NO RENDER PARA DEBUG ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/sessao.php'; 
require_once 'config/conexao.php';
require_once 'config/funcoes.php';

// Credenciais configuradas
define('CLOUDINARY_CLOUD_NAME', 'dvlh11o6w');
define('CLOUDINARY_API_KEY', '591916441776592');
define('CLOUDINARY_API_SECRET', 'SyY1qSVlTc9C1egsVUlfMACCU_g');

$mensagem = "";

// --- FUNÇÃO DE UPLOAD ASSINADO PARA O CLOUDINARY ---
function uploadParaCloudinary($arquivoTmp) {
    $timestamp = time();
    $folder = 'produtos';

    // 1. Parâmetros em estrita ordem alfabética para a assinatura
    $params = [
        'folder' => $folder,
        'timestamp' => $timestamp
    ];
    ksort($params);

    // 2. Monta a string query e gera a assinatura SHA-1
    $paramsToSign = http_build_query($params);
    $signature = sha1($paramsToSign . CLOUDINARY_API_SECRET);

    $url = "https://api.cloudinary.com/v1_1/" . CLOUDINARY_CLOUD_NAME . "/image/upload";

    // 3. Payload do POST
    $data = [
        'file'      => new CURLFile($arquivoTmp), // Corrigido para a variável correta da função
        'api_key'   => CLOUDINARY_API_KEY,
        'timestamp' => $timestamp,
        'signature' => $signature,
        'folder'    => $folder
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); 

    $resposta = curl_exec($ch);
    $erro = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($erro) {
        return ["sucesso" => false, "erro" => "Erro de conexão (cURL): " . $erro];
    }

    $json = json_decode($resposta, true);
    
    if ($http_code !== 200) {
        $msg_erro = $json['error']['message'] ?? "Erro desconhecido no Cloudinary";
        return ["sucesso" => false, "erro" => "Cloudinary API [HTTP $http_code]: " . $msg_erro];
    }

    return ["sucesso" => true, "url" => $json['secure_url']];
}

// --- 1. LÓGICA PARA SALVAR ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['btn_salvar'])) {
        $codigo_barras = trim($_POST['codigo_barras'] ?? "");
        $nome           = trim($_POST['nome'] ?? "");
        $preco          = str_replace(',', '.', $_POST['preco'] ?? "0"); 
        $estoque        = $_POST['estoque'] ?? 0;
        $categoria      = $_POST['categoria_id'] ?? "";
        $online         = $_POST['aparecer_online'] ?? "N";
        $descricao      = trim($_POST['descricao'] ?? "");
        $unidade        = $_POST['unidade_medida'] ?? "UN";
        $imagem_nome    = null;

        // Processa o upload se houver um arquivo enviado
        if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === 0) {
            $resultado = uploadParaCloudinary($_FILES['imagem']['tmp_name']);
            
            // --- TRAVA DE DEBUG COMENTADA PARA PERMITIR GRAVAÇÃO ---
            // echo "<h1>Debug de Envio do Cloudinary</h1>";
            // echo "<pre>"; print_r($resultado); echo "</pre>"; 
            // exit;
            // ---------------------------------------------------------

            if ($resultado['sucesso']) {
                $imagem_nome = $resultado['url']; // Guarda a URL segura (https) gerada
            } else {
                $mensagem = "<div class='alert error'>❌ " . htmlspecialchars($resultado['erro']) . "</div>";
            }
        } else {
            // Se o usuário não enviou imagem no cadastro, define uma padrão ou deixa null
            $imagem_nome = "https://res.cloudinary.com/dvlh11o6w/image/upload/v1/samples/ecommerce/shoes.jpg";
        }

        // Só executa o INSERT se o upload passou sem erros ou se usou a imagem padrão
        if (empty($mensagem)) {
            try {
                $sql = "INSERT INTO produtos (codigo_barras, nome, preco_venda, estoque, categoria_id, aparecer_online, descricao, unidade_medida, imagem, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Ativo')";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$codigo_barras, $nome, $preco, $estoque, $categoria, $online, $descricao, $unidade, $imagem_nome])) {
                    
                    if (function_exists('registrarLog')) {
                        $detalhes = "Cadastrou o produto: {$nome} | Cód: {$codigo_barras} | Estoque Inicial: {$estoque} {$unidade} | Preço: R$ {$preco}";
                        registrarLog($pdo, 'INSERCAO', 'produtos', $detalhes);
                    }

                    $mensagem = "<div class='alert success'>✅ Produto cadastrado com sucesso!</div>";
                }
            } catch (PDOException $e) {
                $mensagem = "<div class='alert error'>❌ Erro ao salvar no banco: " . $e->getMessage() . "</div>";
            }
        }
    }
  
    if (isset($_POST['btn_inativar'])) {
        $id_inativar = $_POST['id_produto'];
        
        try {
            $stmt_busca = $pdo->prepare("SELECT nome, codigo_barras FROM produtos WHERE id = ?");
            $stmt_busca->execute([$id_inativar]);
            $prod_info = $stmt_busca->fetch(PDO::FETCH_ASSOC);
            $nome_produto = $prod_info['nome'] ?? "Desconhecido";
            $cod_produto  = $prod_info['codigo_barras'] ?? "Sem Código";

            if ($pdo->prepare("UPDATE produtos SET status = 'Inativo' WHERE id = ?")->execute([$id_inativar])) {
                
                if (function_exists('registrarLog')) {
                    $detalhes = "Inativou o produto: {$nome_produto} | Cód: {$cod_produto} (ID do Banco: {$id_inativar})";
                    registrarLog($pdo, 'INATIVACAO', 'produtos', $detalhes);
                }

                $mensagem = "<div class='alert warning'>⚠️ Produto movido para inativos!</div>";
            }
        } catch (PDOException $e) {
            $mensagem = "<div class='alert error'>❌ Erro ao inativar: " . $e->getMessage() . "</div>";
        }
    }
}

// --- 2. LÓGICA DE FILTRO ---
$busca        = $_GET['busca'] ?? "";
$filtro_cat   = $_GET['filtro_categoria'] ?? "";
$ver_inativos = (isset($_GET['status']) && $_GET['status'] == 'Inativo') ? 'Inativo' : 'Ativo';

$sql_lista = "SELECT p.*, c.nome as nome_categoria FROM produtos p LEFT JOIN categorias c ON p.categoria_id = c.id WHERE p.status = ?";
$params = [$ver_inativos];

if (!empty($busca)) {
    $sql_lista .= " AND (p.nome ILIKE ? OR p.codigo_barras = ?)";
    $params[] = "%$busca%";
    $params[] = $busca;
}

if (!empty($filtro_cat)) {
    $sql_lista .= " AND p.categoria_id = ?";
    $params[] = (int)$filtro_cat;
}

$sql_lista .= " ORDER BY p.codigo_barras ASC LIMIT 100";
$stmt = $pdo->prepare($sql_lista);
$stmt->execute($params);
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categorias = $pdo->query("SELECT * FROM categorias ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
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

        .header { 
            display: flex; justify-content: space-between; align-items: center; 
            margin-bottom: 30px; background: white; padding: 20px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .header h2 { margin: 0; font-weight: 700; color: var(--gray-700); }
        .btn-voltar { background: var(--gray-100); color: var(--gray-700); padding: 8px 16px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 500; transition: 0.3s; }
        .btn-voltar:hover { background: var(--gray-200); }

        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; font-size: 14px; }
        .success { background: #dcfce7; color: #166534; }
        .error { background: #fee2e2; color: #991b1b; }
        .warning { background: #fef3c7; color: #92400e; }

        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .card-title { font-size: 16px; font-weight: 600; margin-bottom: 20px; color: var(--gray-700); display: block; }

        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; }
        .form-grid > * { min-width: 0; } /* Previne quebras latentes */
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

        .filter-bar { background: white; padding: 15px; border-radius: 12px; margin-bottom: 20px; display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
        .btn-filter { background: var(--gray-700); color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; height: 41px; }

        .table-container { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; }
        thead { background: #f9fafb; border-bottom: 1px solid var(--gray-200); }
        th { padding: 15px; text-align: left; font-size: 12px; text-transform: uppercase; color: #6b7280; letter-spacing: 0.05em; }
        td { padding: 15px; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
        tr:hover { background-color: #fbfbfb; }

        .img-prod { width: 45px; height: 45px; object-fit: cover; border-radius: 8px; background: #eee; }
        
        .badge-estoque {
            padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 600;
        }
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

        <?= $mensagem ?>

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
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nome']) ?></option>
                            <?php endforeach; ?>
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
                <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>" placeholder="Nome ou código...">
            </div>
            <div class="form-group" style="flex: 1; min-width: 150px;">
                <label>Categoria</label>
                <select name="filtro_categoria">
                    <option value="">Todas</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($filtro_cat == $cat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="flex: 1; min-width: 130px;">
                <label>Situação</label>
                <select name="status">
                    <option value="Ativo" <?= $ver_inativos == 'Ativo' ? 'selected' : '' ?>>Ativos</option>
                    <option value="Inativo" <?= $ver_inativos == 'Inativo' ? 'selected' : '' ?>>Inativos</option>
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
                    <?php if (count($produtos) > 0): ?>
                        <?php foreach ($produtos as $p): ?>
                        <tr class="<?= $ver_inativos == 'Inativo' ? 'status-inativo-linha' : '' ?>">
                            <td>
                                <?php if($p['imagem']): ?>
                                    <?php 
                                        // Garante compatibilidade: links novos do Cloudinary (http) abrem direto, uploads antigos buscam localmente
                                        $src_imagem = (strpos($p['imagem'], 'http') === 0) ? $p['imagem'] : 'uploads/produtos/' . $p['imagem'];
                                    ?>
                                    <img src="<?= $src_imagem ?>" class="img-prod">
                                <?php else: ?>
                                    <div class="img-prod" style="display: flex; align-items: center; justify-content: center; font-size: 9px; color: #ccc;">SEM FOTO</div>
                                <?php endif; ?>
                            </td>
                            <td style="font-family: monospace; font-weight: 600; color: #666;"><?= $p['codigo_barras'] ?></td>
                            <td>
                                <div style="font-weight: 600; color: var(--gray-700);"><?= htmlspecialchars($p['nome']) ?></div>
                                <div style="font-size: 12px; color: #9ca3af;"><?= htmlspecialchars($p['nome_categoria'] ?? 'Geral') ?></div>
                            </td>
                            <td style="font-weight: 700;">R$ <?= number_format($p['preco_venda'], 2, ',', '.') ?></td>
                            <td>
                                <span class="badge-estoque <?= $p['estoque'] <= 0 ? 'low-stock' : 'ok-stock' ?>">
                                    <?= $p['estoque'] ?> <?= $p['unidade_medida'] ?>
                                </span>
                            </td>
                            <td>
                                <div class="actions" style="justify-content: center;">
                                    <a href="editar_produto.php?id=<?= $p['id'] ?>" class="btn-action" title="Editar">✏️</a>
                                    
                                    <?php if ($ver_inativos == 'Ativo'): ?>
                                        <form method="POST" onsubmit="return confirm('Inativar este produto?')" style="margin: 0;">
                                            <input type="hidden" name="id_produto" value="<?= $p['id'] ?>">
                                            <button type="submit" name="btn_inativar" class="btn-action" style="background:none; border:none; cursor:pointer;" title="Inativar">🚫</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="padding: 40px; text-align: center; color: #9ca3af;">Nenhum produto encontrado na base de dados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
