<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Em vez de destruir a sessão inteira do PHP (o que afeta o ERP)
// vamos limpar apenas as variáveis que identificam o CLIENTE do cardápio
unset($_SESSION['cliente_id']);
unset($_SESSION['cliente_nome']);
unset($_SESSION['cliente_cpf']);
unset($_SESSION['cliente_logado']);

// Se você tiver uma chave específica para o painel do cliente, limpe-a aqui também.

// Redirecionamento explícito para a tela de login do CLIENTE no cardápio
header("Location: cliente_online.php");
exit;
?>
