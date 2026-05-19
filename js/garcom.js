// js/garcom.js

window.produtoSelecionado = null;

// 1. Busca de Produtos (Autocompletar)
window.configurarBusca = function() {
    const inputProd = document.getElementById('input_produto');
    const listaProd = document.getElementById('lista_produtos');

    if (!inputProd) return;

    inputProd.addEventListener('input', function() {
        let termo = this.value;
        if (termo.length > 2) {
            fetch(`buscar_produtos_estoque.php?query=${termo}`)
                .then(res => res.json())
                .then(data => {
                    listaProd.innerHTML = "";
                    listaProd.style.display = "block";
                    data.forEach(prod => {
                        let div = document.createElement('div');
                        div.className = "item-busca";
                        div.innerText = `${prod.nome} - R$ ${parseFloat(prod.preco_venda).toFixed(2)}`;
                        div.onclick = function() {
                            window.produtoSelecionado = { 
                                id: prod.id, 
                                nome: prod.nome, 
                                valor: prod.preco_venda 
                            };
                            inputProd.value = prod.nome;
                            listaProd.style.display = "none";
                        };
                        listaProd.appendChild(div);
                    });
                })
                .catch(err => console.error("Erro na busca:", err));
        } else {
            listaProd.style.display = "none";
        }
    });
};

// 2. Listar Itens da Mesa
window.carregarItens = function() {
    if (!window.pedido_id) return;

    fetch(`buscar_itens_pedido.php?pedido_id=${window.pedido_id}`)
        .then(res => {
            if (!res.ok) return res.text().then(text => { throw new Error(text) });
            return res.json();
        })
        .then(data => {
            const tbody = document.querySelector("#tabela_itens tbody");
            const totalExibicao = document.getElementById("total_exibicao");
            
            if (!tbody) return;
            tbody.innerHTML = "";
            let totalGeral = 0;

            if (Array.isArray(data)) {
                data.forEach(item => {
                    let totalItem = parseFloat(item.total) || 0;
                    tbody.innerHTML += `
                        <tr>
                            <td>${item.produto}</td>
                            <td class="text-center">${item.quantidade}</td>
                            <td class="text-end">R$ ${parseFloat(item.preco_unitario).toFixed(2)}</td>
                            <td class="text-end">R$ ${totalItem.toFixed(2)}</td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-danger" onclick="removerItem(${item.id})">
                                    Excluir
                                </button>
                            </td>
                        </tr>`;
                    totalGeral += totalItem;
                });
            }
            if (totalExibicao) totalExibicao.innerText = "R$ " + totalGeral.toFixed(2);
        })
        .catch(err => console.error("Erro ao carregar tabela:", err.message));
};

// 3. Adicionar Item
window.addItemGarcom = function() {
    if (!window.produtoSelecionado) {
        alert("Selecione um produto clicando na lista!");
        return;
    }

    const qtdInput = document.getElementById('qtd_item');
    const qtd = qtdInput ? qtdInput.value : 1;

    const dados = {
        pedido_id: window.pedido_id,
        produto_id: window.produtoSelecionado.id,
        quantidade: qtd,
        valor: window.produtoSelecionado.valor
    };

    fetch('salvar_item.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(dados)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('input_produto').value = "";
            if (qtdInput) qtdInput.value = 1;
            window.produtoSelecionado = null;
            window.carregarItens();
        } else {
            alert("Erro: " + data.erro);
        }
    })
    .catch(err => console.error("Erro ao salvar:", err));
};

// 4. Remover Item
window.removerItem = function(id) {
    const motivo = prompt("Informe o motivo do cancelamento deste item:");
    if (motivo === null) return; 

    if (motivo.trim() === "") {
        alert("O motivo é obrigatório para realizar a exclusão.");
        return;
    }

    fetch('remover_item.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            id: id, 
            pedido_id: window.pedido_id,
            motivo: motivo 
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.carregarItens();
        } else {
            alert("Erro ao remover: " + data.erro);
        }
    })
    .catch(err => console.error("Erro na remoção:", err));
};

// 5. Criar Nova Mesa/Comanda (CORRIGIDO PARA JSON)
window.criarMesa = function() {
    const inputMesa = document.getElementById('nova_mesa_numero');
    const numeroMesa = inputMesa ? inputMesa.value : '';

    if (numeroMesa.trim() === "") {
        alert("Digite o número da mesa");
        return;
    }

    fetch('/projeto_breno/criar_mesa.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ numero: numeroMesa })
    })
    
    .then(res => res.text())
    .then(text => {
        try {
            const data = JSON.parse(text);

            if (data.success) {
                location.reload();
            } else {
                alert(data.erro);
            }

        } catch (e) {
            console.error("Resposta inválida:", text);
            alert("Erro no servidor (resposta inválida)");
        }
    })
    .catch(err => {
        console.error(err);
        alert("Erro de conexão com o servidor");
    });
};

// Inicialização
document.addEventListener('DOMContentLoaded', () => {
    window.configurarBusca();
    window.carregarItens();
});