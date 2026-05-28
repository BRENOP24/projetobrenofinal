// ===============================
// ESTADO GLOBAL DO CARRINHO
// ===============================
// Usamos window.carrinho para garantir que todas as funções enxerguem a mesma lista
window.carrinho = [];

document.addEventListener('DOMContentLoaded', () => {
    inicializarBotoesCompra();
});

// ===============================
// INICIALIZAÇÃO DOS BOTÕES
// ===============================
function inicializarBotoesCompra() {
    const botoes = document.querySelectorAll('.btn-adicionar-produto');
    botoes.forEach(botao => {
        botao.addEventListener('click', () => {
            const id = botao.getAttribute('data-id');
            const nome = botao.getAttribute('data-nome');
            const preco = parseFloat(botao.getAttribute('data-preco'));
            adicionarAoCarrinho(id, nome, preco);
        });
    });
}

// ===============================
// LÓGICA DO CARRINHO
// ===============================
function adicionarAoCarrinho(id, nome, preco) {
    const itemExistente = window.carrinho.find(item => item.id === id);

    if (itemExistente) {
        itemExistente.qtd += 1;
    } else {
        window.carrinho.push({
            id: id,
            nome: nome,
            preco: preco,
            qtd: 1
        });
    }

    atualizarBarraFlutuante();
}

function atualizarBarraFlutuante() {
    const barra = document.getElementById('barra-carrinho');
    const qtdTexto = document.getElementById('carrinho-qtd');
    const totalTexto = document.getElementById('carrinho-total');

    if (!barra) return;

    if (window.carrinho.length > 0) {
        barra.style.display = 'flex';
        const totalItens = window.carrinho.reduce((acc, item) => acc + item.qtd, 0);
        const valorTotal = window.carrinho.reduce((acc, item) => acc + (item.preco * item.qtd), 0);

        qtdTexto.innerText = `${totalItens} ${totalItens > 1 ? 'itens' : 'item'}`;
        totalTexto.innerText = valorTotal.toLocaleString('pt-br', { style: 'currency', currency: 'BRL' });
    } else {
        barra.style.display = 'none';
    }
}

function verificarTroco() {
    const pagamentoId = document.getElementById('cli-pagamento').value;
    const boxTroco = document.getElementById('box-troco');
    if (boxTroco) {
        boxTroco.style.display = (pagamentoId === "1") ? 'block' : 'none';
        if (pagamentoId !== "1") document.getElementById('cli-troco').value = '';
    }
}

// ===============================
// CÁLCULO DE TOTAIS
// ===============================
function atualizarTotalFinal() {
    const subtotal = window.carrinho.reduce((acc, item) => acc + (item.preco * item.qtd), 0);
    const tipoEntrega = document.getElementById('cli-entrega').value;
    let taxa = 0;

    if (tipoEntrega === 'entrega') {
        const selectBairro = document.getElementById('cli-bairro');
        const opcao = selectBairro.options[selectBairro.selectedIndex];
        taxa = (opcao && opcao.value !== "") ? parseFloat(opcao.getAttribute('data-taxa') || 0) : 0;
    }

    const totalGeral = subtotal + taxa;
    const btn = document.getElementById('btn-finalizar');
    if (btn) {
        btn.innerText = `Confirmar Pedido - Total: R$ ${totalGeral.toFixed(2).replace('.', ',')}`;
    }
    
    return { subtotal, taxa, totalGeral };
}

// ===============================
// ENVIO DO PEDIDO PARA O PHP (BLINDADO CONTRA DUPLICIDADE)
// ===============================
async function enviarPedido(event) {
    // 1. Previne comportamentos inesperados do botão
    if (event && event.preventDefault) {
        event.preventDefault();
    }

    const btnFinalizar = document.getElementById('btn-finalizar');

    // 2. TRAVA DE SEGURANÇA ABSOLUTA: Se o botão já foi clicado, mata a execução imediatamente
    if (btnFinalizar && (btnFinalizar.disabled || btnFinalizar.innerText === "Processando...")) {
        return;
    }

    if (!window.carrinho || window.carrinho.length === 0) {
        alert("Seu carrinho está vazio.");
        return;
    }

    // Captura dos campos
    const inputCpf = document.getElementById('cli-cpf');
    const inputNome = document.getElementById('cli-nome');
    const inputTelefone = document.getElementById('cli-telefone');
    const inputPagamento = document.getElementById('cli-pagamento');

    if (!inputCpf.value || !inputNome.value || !inputTelefone.value || !inputPagamento.value) {
        alert("Preencha CPF, Nome, Telefone e Forma de Pagamento.");
        return;
    }

    // Travas de validação básicas do Front-end
    const nomeTratado = inputNome.value.trim();
    const cpfLimpo = inputCpf.value.replace(/\D/g, '');
    const telefoneLimpo = inputTelefone.value.replace(/\D/g, '');

    const regexNome = /^[A-Za-záàâãéèêíïóôõöúçñÁÀÂÃÉÈÍÏÓÔÕÖÚÇÑ ]+$/;
    if (!regexNome.test(nomeTratado) || nomeTratado.length < 3) {
        alert("Por favor, insira um nome válido (apenas letras, mínimo de 3 caracteres).");
        inputNome.focus();
        return;
    }

    if (telefoneLimpo.length < 10 || telefoneLimpo.length > 11) {
        alert("Por favor, insira um número de telefone válido com DDD (10 ou 11 dígitos).");
        inputTelefone.focus();
        return;
    }

    if (cpfLimpo.length !== 11) {
        alert("O CPF deve conter exatamente 11 números.");
        inputCpf.focus();
        return;
    }

    const tipoEntrega = document.getElementById('cli-entrega').value;
    let endereco = "";
    let bairro = "";

    if (tipoEntrega === "entrega") {
        endereco = document.getElementById('cli-endereco').value.trim();
        bairro = document.getElementById('cli-bairro').value;
        if (!endereco || !bairro) {
            alert("Preencha o endereço completo e selecione o bairro.");
            return;
        }
    }

    // Chamada correta da função de valores
    const valores = atualizarTotalFinal();

    // 3. DESABILITA O BOTÃO IMEDIATAMENTE (Síncrono) antes de chamar o Servidor
    if (btnFinalizar) {
        btnFinalizar.innerText = "Processando...";
        btnFinalizar.disabled = true;
    }

    const dadosPedido = {
        cliente_cpf: cpfLimpo,
        cliente_nome: nomeTratado,
        cliente_telefone: telefoneLimpo,
        tipo_entrega: tipoEntrega,
        endereco_completo: endereco,
        bairro_entrega: bairro,
        forma_pagamento: parseInt(inputPagamento.value),
        precisa_troco: parseFloat(document.getElementById('cli-troco').value) || 0,
        taxa_entrega: valores.taxa,
        total_geral: valores.totalGeral,
        itens: window.carrinho.map(item => ({
            id: parseInt(item.id),
            qtd: parseInt(item.qtd),
            preco: parseFloat(item.preco)
        }))
    };

    try {
        const response = await fetch('processar_pedido_online.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dadosPedido)
        });

        const result = await response.json();

        if (result.sucesso) {
            alert(`Pedido #${result.pedido_id} realizado com sucesso!`);
            window.location.reload();
        } else {
            alert("Erro: " + result.erro);
            // Libera o botão novamente se houver falha tratada pelo back-end
            if (btnFinalizar) {
                btnFinalizar.innerText = "Confirmar Pedido";
                btnFinalizar.disabled = false;
            }
        }
    } catch (error) {
        alert("Erro de comunicação com o servidor.");
        if (btnFinalizar) {
            btnFinalizar.innerText = "Confirmar Pedido";
            btnFinalizar.disabled = false;
        }
    }
}

// ===============================
// MODAL E RENDERIZAÇÃO
// ===============================
document.getElementById('btn-abrir-modal-carrinho')?.addEventListener('click', () => {
    const modal = document.getElementById('modal-checkout'); 
    if (modal) {
        modal.style.display = 'block';
        renderizarItensCarrinho();
        atualizarTotalFinal();
    }
});

function renderizarItensCarrinho() {
    const listaItens = document.getElementById('lista-itens-carrinho');
    if (!listaItens) return;

    listaItens.innerHTML = '';
    window.carrinho.forEach((item, index) => {
        listaItens.innerHTML += `
            <div style="display:flex; justify-content:space-between; margin-bottom:10px; border-bottom:1px solid #eee; padding-bottom:5px;">
                <span>${item.qtd}x ${item.nome}</span>
                <span>R$ ${(item.preco * item.qtd).toFixed(2).replace('.', ',')}</span>
                <button onclick="removerItem(${index})" style="color:red; border:none; background:none; cursor:pointer;">[x]</button>
            </div>
        `;
    });
}

function removerItem(index) {
    window.carrinho.splice(index, 1);
    renderizarItensCarrinho();
    atualizarBarraFlutuante();
    atualizarTotalFinal();
}
