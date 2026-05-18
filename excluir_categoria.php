<?php
require_once 'config/sessao.php';
require_once 'config/conexao.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    try {
        $stmt = $pdo->prepare("DELETE FROM categorias WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: categorias.php?mensagem=excluido");
    } catch (PDOException $e) {
        die("Erro ao excluir: " . $e->getMessage());
    }
} else {
    header("Location: categorias.php");
}
exit;