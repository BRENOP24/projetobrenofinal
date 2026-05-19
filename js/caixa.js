// Variáveis que o PHP injeta no HTML (certifique-se que elas existam no PHP)
// const pedidoId = ...
// const caixaId = ...

function atualizarTotal() {
    // Busca o subtotal que o PHP deixou escondido ou em uma variável
    const subtotalTexto = document.getElementById('total_exibicao').innerText;
    // Limpa a string "R$ 10,00" para virar o número 10.00
    const subtotal = parseFloat(subtotalTexto.replace(/[^\d,]/g, '').replace(',', '.')) || 0;
    
    const desc = parseFloat(document.getElementById('desconto').value) || 0;
    const total = subtotal - desc;

    // Atualiza a exibição do total final
    const totalExibicao = document.getElementById('total_final') || document.getElementById('total_exibicao');
    totalExibicao.innerText = total.toLocaleString('pt-br', {style: 'currency', currency: 'BRL'});
}

function finalizarNoCaixa() {
    const pagId = document.getElementById('pagamento_id').value;
    const desc = document.getElementById('desconto').value;

    if(!confirm("Confirmar fechamento desta conta?")) return;

    // Criando os dados para o POST
    const dados = new URLSearchParams();
    dados.append('pedido_id', pedidoId);
    dados.append('caixa_id', caixaId);
    dados.append('pagamento_id', pagId);
    dados.append('desconto', desc);

    fetch('ajax_finalizar_caixa.php', {
        method: 'POST',
        body: dados
    })
    .then(r => r.json())
    .then(data => {
        if(data.sucesso) {
            if(confirm("Venda finalizada com sucesso! Deseja imprimir o cupom?")) {
                window.open(`imprimir_cupom.php?id=${data.pedido_id}`, '_blank');
            }
            window.location.href = 'fechamento_com_mesa.php';
        } else {
            alert("Erro ao finalizar: " + data.mensagem);
        }
    })
    .catch(err => {
        console.error("Erro na requisição:", err);
        alert("Erro de comunicação com o servidor.");
    });
}