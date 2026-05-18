
<?php
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config/conexao.php';

$apiKey = "beffac46bc3c4c4287ff2f38b1a486c4";

// 🔹 Buscar pedidos válidos (COM COORDENADAS)
$pedidosDB = $pdo->query("
    SELECT id, latitude, longitude 
    FROM pedidos 
    WHERE tipo_venda = 'delivery' 
    AND motoboy_id IS NULL
    AND latitude IS NOT NULL
    AND longitude IS NOT NULL
")->fetchAll(PDO::FETCH_ASSOC);

// 🔹 Buscar motoboys ativos (COM COORDENADAS)
$motoboysDB = $pdo->query("
    SELECT id, latitude, longitude 
    FROM motoboys 
    WHERE latitude IS NOT NULL
    AND longitude IS NOT NULL
")->fetchAll(PDO::FETCH_ASSOC);

// 🔹 Validação básica
if (count($pedidosDB) == 0) {
    echo json_encode(["status" => "erro", "msg" => "Sem pedidos válidos"]);
    exit;
}

if (count($motoboysDB) == 0) {
    echo json_encode(["status" => "erro", "msg" => "Sem motoboys ativos"]);
    exit;
}

// 🔥 Fallback: 1 pedido
if (count($pedidosDB) == 1) {
    $pedido = $pedidosDB[0];
    $motoboy = $motoboysDB[0];

    $stmt = $pdo->prepare("
        UPDATE pedidos 
        SET motoboy_id = :motoboy 
        WHERE id = :pedido
    ");

    $stmt->execute([
        ':motoboy' => $motoboy['id'],
        ':pedido' => $pedido['id']
    ]);

    echo json_encode(["status" => "sucesso", "msg" => "Pedido atribuído automaticamente"]);
    exit;
}

// 🔹 Calcular limite
$limite = ceil(count($pedidosDB) / count($motoboysDB));

// 🔹 Montar jobs
$jobs = [];
foreach ($pedidosDB as $p) {
    $jobs[] = [
        "id" => (string)$p['id'],
        "location" => [(float)$p['longitude'], (float)$p['latitude']],
        "size" => [1]
    ];
}

// 🔹 Montar agents
$agents = [];
foreach ($motoboysDB as $m) {
    $agents[] = [
        "id" => (string)$m['id'],
        "start_location" => [(float)$m['longitude'], (float)$m['latitude']],
        "capacity" => [$limite]
    ];
}

// 🔹 Chamada API
$data = [
    "mode" => "drive",
    "agents" => $agents,
    "jobs" => $jobs
];

$ch = curl_init("https://api.geoapify.com/v1/routeplanner?apiKey=$apiKey");
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo json_encode(["status" => "erro", "msg" => curl_error($ch)]);
    exit;
}

curl_close($ch);

$resultado = json_decode($response, true);

// 🔥 Validação resposta API
if (!isset($resultado['features'])) {
    echo json_encode([
        "status" => "erro",
        "msg" => "Erro na resposta da API",
        "debug" => $resultado
    ]);
    exit;
}

// 🔹 Processar resposta
foreach ($resultado['features'] as $rota) {
    $motoboy_id = $rota['properties']['agent_id'];

    foreach ($rota['properties']['actions'] as $acao) {
        if ($acao['type'] === 'job') {
            $pedido_id = $acao['job_id'];

            $stmt = $pdo->prepare("
                UPDATE pedidos 
                SET motoboy_id = :motoboy 
                WHERE id = :pedido
            ");

            $stmt->execute([
                ':motoboy' => $motoboy_id,
                ':pedido' => $pedido_id
            ]);
        }
    }
}

echo json_encode(["status" => "sucesso"]);