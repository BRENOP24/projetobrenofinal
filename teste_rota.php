<?php

$apiKey = "beffac46bc3c4c4287ff2f38b1a486c4";

// 🔹 3 pedidos (exemplo em Cascavel)
$pedidos = [
    [
        "id" => "pedido_1",
        "location" => [-53.4552, -24.9555]
    ],
    [
        "id" => "pedido_2",
        "location" => [-53.4560, -24.9600]
    ],
    [
        "id" => "pedido_3",
        "location" => [-53.4600, -24.9700]
    ]
];

// 🔹 2 motoboys
$motoboys = [
    [
        "id" => "motoboy_1",
        "start_location" => [-53.4550, -24.9500]
    ],
    [
        "id" => "motoboy_2",
        "start_location" => [-53.4600, -24.9500]
    ]
];

// 🔹 Monta payload
$data = [
    "mode" => "drive",
    "agents" => $motoboys,
    "jobs" => $pedidos
];

// 🔹 Chamada API
$url = "https://api.geoapify.com/v1/routeplanner?apiKey=$apiKey";

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo "Erro CURL: " . curl_error($ch);
    exit;
}

curl_close($ch);

// 🔹 Resultado
$resultado = json_decode($response, true);

echo "<pre>";
print_r($resultado);
echo "</pre>";