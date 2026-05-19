let carrinho = [];
let clienteObj = null;
let produtoObj = null;

// BUSCA CLIENTE
document.getElementById('input_cliente').addEventListener('input', function() {
    let q = this.value;
    let res = document.getElementById('lista_clientes');
    if (q.length < 2) { res.style.display = 'none'; return; }

    fetch(`buscar_dados.php?tipo=cliente&q=${q}`)
        .then(r => r.json())
        .then(dados => {
            res.innerHTML = '';
            res.style.display = 'block';
            dados.forEach(c => {
                let div = document.createElement('div');
                div.className = 'item-busca';
                div.innerHTML = `<strong>${c.nome}</strong> - ${c.bairro || 'S/B'}`;
                div.onclick = () => {
                    clienteObj = c;
                    document.getElementById('cliente_id').value = c.id;
                    document.getElementById('input_cliente').value = c.nome;
                    res.style.display = 'none';
                };
                res.appendChild(div);
            });
        });
});

// BUSCA PRODUTO
document.getElementById('input_produto').addEventListener('input', function() {
    let q = this.value;
    let res = document.getElementById('lista_produtos');
    if (q.length < 2) { res.style.display = 'none'; return; }

    fetch(`buscar_dados.php?tipo=produto&q=${q}`)
        .then(r => r.json())
        .then(dados => {
            res.innerHTML = '';
            res.style.display = 'block';
            dados.forEach(p => {
                let div = document.createElement('div');
                div.className = 'item-busca';
                div.innerHTML = `${p.nome} - <b>R$ ${p.preco_venda}</b>`;
                div.onclick = () => {
                    produtoObj = p;
                    document.getElementById('input_produto').value = p.nome;
                    res.style.display = 'none';
                };
                res.appendChild(div);
            });
        });
});

// FECHAR LISTAS AO CLICAR FORA
document.addEventListener('click', function(e) {
    if (!e.target.closest('.busca-container')) {
        const lc = document.getElementById('lista_clientes');
        const lp = document.getElementById('lista_produtos');
        if(lc) lc.style.display = 'none';
        if(lp) lp.style.display = 'none';
    }
});

function addItem() {
    if (!produtoObj) return alert("Selecione o produto na lista!");
    let qtdInput = document.getElementById('qtd_item');
    let qtd = parseFloat(qtdInput.value);
    if (isNaN(qtd) || qtd <= 0) return alert("Informe uma quantidade válida!");

    carrinho.push({
        id: produtoObj.id,
        nome: produtoObj.nome,
        preco: parseFloat(produtoObj.preco_venda),
        qtd: qtd,
        sub: qtd * parseFloat(produtoObj.preco_venda)
    });

    produtoObj = null;
    document.getElementById('input_produto').value = '';
    qtdInput.value = 1;
    renderCarrinho();
}

function renderCarrinho() {
    let html = '';
    carrinho.forEach((item, i) => {
        html += `<tr>
            <td>${item.nome}</td>
            <td>${item.qtd}</td>
            <td>R$ ${item.preco.toFixed(2)}</td>
            <td>R$ ${item.sub.toFixed(2)}</td>
            <td><button class="btn btn-sm btn-danger" onclick="removerItem(${i})"><i class="fas fa-times"></i></button></td>
        </tr>`;
    });
    document.querySelector('#tabela_itens tbody').innerHTML = html;
    calcularTotal();
}

function removerItem(i) {
    carrinho.splice(i, 1);
    renderCarrinho();
}

function calcularTotal() {
    let sub = carrinho.reduce((a, b) => a + b.sub, 0);
    let desc = parseFloat(document.getElementById('desc_venda').value) || 0;
    let taxa = parseFloat(document.getElementById('taxa_venda').value) || 0;
    let totalFinal = (sub - desc + taxa);
    document.getElementById('total_exibicao').innerText = `R$ ${totalFinal.toFixed(2)}`;
}

// ... (mantenha suas funções de busca e carrinho iguais, estão ótimas)

function gerenciarTipoVenda() {
    let tipo = document.getElementById('tipo_venda').value;
    let areaTaxa = document.getElementById('area_taxa');
    
    if (tipo === 'delivery') {
        if (!clienteObj) { 
            alert("Selecione o cliente primeiro!"); 
            document.getElementById('tipo_venda').value = 'balcao'; 
            return; 
        }
        
        areaTaxa.classList.remove('d-none');
        
        // Gerando os campos. 
        // IMPORTANTE: IDs 'end_entrega', 'bairro_entrega' e 'num_entrega' usados no finalizarVenda
        document.getElementById('modal_body_end').innerHTML = `
            <div class="row g-2">
                <div class="col-12">
                    <label class="small fw-bold">Endereço/Rua:</label>
                    <input type="text" id="end_entrega" class="form-control" value="${clienteObj.endereco || ''}">
                </div>
                <div class="col-7">
                    <label class="small fw-bold">Bairro:</label>
                    <input type="text" id="bairro_entrega" class="form-control" value="${clienteObj.bairro || ''}">
                </div>
                <div class="col-5">
                    <label class="small fw-bold">Número:</label>
                    <input type="text" id="num_entrega" class="form-control" value="${clienteObj.numero || ''}">
                </div>
            </div>`;
            
        new bootstrap.Modal(document.getElementById('modalEnd')).show();
    } else {
        areaTaxa.classList.add('d-none');
        document.getElementById('taxa_venda').value = '0.00';
        calcularTotal();
    }
}

async function finalizarVenda() {
    if (carrinho.length === 0) return alert("Adicione produtos!");
    const clienteId = document.getElementById('cliente_id').value;
    if (!clienteId) return alert("Selecione um cliente!");

    const tipoVenda = document.getElementById('tipo_venda').value;

    // Captura os dados do endereço se for delivery
    let enderecoFinal = null;
    if (tipoVenda === 'delivery') {
        // Proteção: verifica se os campos do modal existem antes de ler
        const campoEnd = document.getElementById('end_entrega');
        const campoBairro = document.getElementById('bairro_entrega');
        const campoNum = document.getElementById('num_entrega');

        if (campoEnd && campoBairro && campoNum) {
            enderecoFinal = {
                rua: campoEnd.value,
                bairro: campoBairro.value,
                numero: campoNum.value
            };
        }
    }

    const dados = {
        cliente_id: clienteId,
        tipo_venda: tipoVenda,
        itens: carrinho,
        desconto: document.getElementById('desc_venda').value || 0,
        taxa: document.getElementById('taxa_venda').value || 0,
        pagamento_id: document.getElementById('pagamento_id').value,
        endereco_confirmado: enderecoFinal 
    };

    try {
        let res = await fetch('salvar_pedido.php', { 
            method: 'POST', 
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(dados) 
        });
        
        let r = await res.json();

        if (r.status === 'sucesso') { 
            if (confirm("Venda realizada! Deseja imprimir o comprovante?")) {
                // Usando o ID retornado pelo seu PHP
                window.open(`imprimir_pedido.php?id=${r.id}`, '_blank');
            }
            location.reload(); 
        } else {
            alert("Erro: " + r.msg);
        }
    } catch (e) {
        console.error(e);
        alert("Erro na comunicação com o servidor.");
    }
}