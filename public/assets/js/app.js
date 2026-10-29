// JavaScript puro do Roteirizador - sem dependência de frameworks de front-end.
document.addEventListener('DOMContentLoaded', () => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // Tooltips do Bootstrap (indicadores da Crítica, travas).
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));

    // Filtros de data/revenda/Mesa/setor enviam o formulário ao mudar.
    // data-limpa="campo": zera o filtro dependente (ex.: trocar a revenda volta a Mesa
    // para "Todas"; trocar a Mesa volta o setor para "Todos").
    document.querySelectorAll('[data-autosubmit]').forEach((campo) => {
        campo.addEventListener('change', () => {
            const dependente = campo.dataset.limpa && campo.form.elements[campo.dataset.limpa];
            if (dependente) {
                dependente.value = '';
            }
            campo.form.submit();
        });
    });

    // Confirmação antes de ações destrutivas.
    document.querySelectorAll('form[data-confirmar]').forEach((form) => {
        form.addEventListener('submit', (evento) => {
            if (!window.confirm(form.dataset.confirmar)) {
                evento.preventDefault();
            }
        });
    });

    // Importações: campo de data só para pedidos do Cora; indicador de carregamento.
    const tipoImportacao = document.querySelector('[data-toggle-cora]');
    if (tipoImportacao) {
        const campoData = document.getElementById('campoDataCora');
        const alternar = () => {
            const ehCora = tipoImportacao.value === 'cora';
            campoData.classList.toggle('d-none', !ehCora);
            campoData.querySelector('input').required = ehCora;
        };
        tipoImportacao.addEventListener('change', alternar);
        alternar();
    }

    // Usuários: no cadastro, habilita só as abas que o perfil escolhido permite.
    document.querySelectorAll('form[data-abas-por-perfil]').forEach((form) => {
        const perfil = form.querySelector('[data-perfil]');
        const atualizarAbas = () => {
            form.querySelectorAll('input[name="abas[]"]').forEach((aba) => {
                const permitida = aba.dataset.perfis.split(' ').includes(perfil.value);
                aba.disabled = !permitida;
                aba.checked = permitida;
            });
            // Salas só se aplicam ao Comercial.
            form.querySelectorAll('input[name="salas[]"]').forEach((sala) => {
                sala.disabled = perfil.value !== 'comercial';
                if (sala.disabled) {
                    sala.checked = false;
                }
            });
        };
        perfil.addEventListener('change', atualizarAbas);
        atualizarAbas();
    });

    document.querySelectorAll('form[data-loading]').forEach((form) => {
        form.addEventListener('submit', () => {
            const botao = form.querySelector('button[type="submit"]');
            botao.disabled = true;
            botao.querySelector('.spinner-border')?.classList.remove('d-none');
        });
    });

    // Crítica: salva a decisão de cada linha sem recarregar a página.
    document.querySelectorAll('form[data-ajax]').forEach((form) => {
        form.addEventListener('submit', async (evento) => {
            evento.preventDefault();
            const botao = form.querySelector('button[type="submit"]');
            const linha = form.closest('tr');
            botao.disabled = true;

            try {
                const resposta = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { 'X-Requested-With': 'fetch', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                });

                if (resposta.status === 419) {
                    throw new Error('Sessão expirada. Atualize a página e faça login novamente.');
                }

                const retorno = await resposta.json();

                if (!retorno.ok) {
                    throw new Error(retorno.mensagem);
                }

                if (linha) {
                    const pendente = form.elements.decisao.value === '';
                    linha.dataset.pendente = pendente ? '1' : '0';
                    linha.dataset.decisao = pendente ? 'pendente' : form.elements.decisao.value;
                    linha.classList.toggle('linha-decidida', !pendente);
                    linha.classList.add('linha-salva');
                    setTimeout(() => linha.classList.remove('linha-salva'), 1200);
                }

                atualizarContadores();
            } catch (erro) {
                window.alert(erro.message || 'Não foi possível salvar. Verifique a conexão e tente novamente.');
            } finally {
                botao.disabled = false;
            }
        });
    });

    // Crítica: filtros locais da tabela (texto, setor, decisão, indicadores e faixas de valor/HL).
    const tabela = document.querySelector('[data-filtro-tabela]');
    const painelFiltros = document.getElementById('filtrosCritica');
    const campo = (id) => document.getElementById(id);
    const numero = (id) => {
        const valor = campo(id)?.value ?? '';
        return valor === '' ? null : parseFloat(valor);
    };
    const dentroDaFaixa = (valor, minimo, maximo) => (minimo === null || valor >= minimo) && (maximo === null || valor <= maximo);

    function aplicarFiltros() {
        if (!tabela) {
            return;
        }

        const texto = (campo('filtroTexto')?.value || '').trim().toLowerCase();
        const setor = campo('filtroSetor')?.value || '';
        const decisao = campo('filtroDecisao')?.value || '';
        const valorMin = numero('filtroValorMin');
        const valorMax = numero('filtroValorMax');
        const hlMin = numero('filtroHlMin');
        const hlMax = numero('filtroHlMax');
        const comAlerta = campo('filtroAlertas')?.checked;
        const semAlerta = campo('filtroSemIndicador')?.checked;
        const exigeTodos = document.querySelector('input[name="modoIndicador"]:checked')?.value === 'todos';
        const marcados = [...document.querySelectorAll('.filtro-indicador:checked')].map((c) => c.value);

        tabela.querySelectorAll('tbody tr').forEach((linha) => {
            const indicadores = linha.dataset.indicadores ? linha.dataset.indicadores.split(' ') : [];
            const atendeIndicadores = marcados.length === 0
                || (exigeTodos ? marcados.every((i) => indicadores.includes(i)) : marcados.some((i) => indicadores.includes(i)));

            const visivel = (!texto || linha.dataset.busca.includes(texto))
                && (!setor || linha.dataset.setor === setor)
                && (!decisao || linha.dataset.decisao === decisao)
                && dentroDaFaixa(parseFloat(linha.dataset.valor), valorMin, valorMax)
                && dentroDaFaixa(parseFloat(linha.dataset.hl), hlMin, hlMax)
                && (!comAlerta || linha.dataset.alerta === '1')
                && (!semAlerta || linha.dataset.alerta === '0')
                && atendeIndicadores;

            linha.classList.toggle('d-none', !visivel);
        });

        atualizarContadores();
    }

    if (painelFiltros) {
        painelFiltros.addEventListener('input', aplicarFiltros);
        painelFiltros.addEventListener('change', aplicarFiltros);

        campo('limparFiltros')?.addEventListener('click', () => {
            painelFiltros.querySelectorAll('input[type="search"], input[type="number"]').forEach((c) => { c.value = ''; });
            painelFiltros.querySelectorAll('select').forEach((c) => { c.value = ''; });
            painelFiltros.querySelectorAll('input[type="checkbox"]').forEach((c) => { c.checked = false; });
            campo('modoQualquer').checked = true;
            aplicarFiltros();
        });
    }

    // Troca > Notas em rota: imagem (PNG) das notas do filtro para enviar pelo WhatsApp.
    // Desenhada em <canvas> com os dados de #dadosFotoNotas, sem biblioteca externa.
    const modalFoto = document.getElementById('modalFotoNotas');
    if (modalFoto) {
        const dados = JSON.parse(document.getElementById('dadosFotoNotas').textContent);
        const previa = document.getElementById('previaFotoNotas');
        const aviso = document.getElementById('avisoFotoNotas');
        let imagem = null;

        modalFoto.addEventListener('show.bs.modal', async () => {
            if (!imagem) {
                imagem = await desenharFotoNotas(dados);
                previa.src = URL.createObjectURL(imagem);
            }
        });

        const baixar = () => {
            const link = document.createElement('a');
            link.href = URL.createObjectURL(imagem);
            link.download = dados.arquivo;
            link.click();
        };

        document.getElementById('baixarFotoNotas').addEventListener('click', () => imagem && baixar());

        document.getElementById('compartilharFotoNotas').addEventListener('click', async () => {
            if (!imagem) {
                return;
            }
            const arquivo = new File([imagem], dados.arquivo, { type: 'image/png' });
            if (navigator.canShare && navigator.canShare({ files: [arquivo] })) {
                try {
                    await navigator.share({ files: [arquivo], title: dados.titulo });
                } catch (erro) {
                    // Compartilhamento cancelado pelo usuário: nada a fazer.
                }
                return;
            }
            baixar();
            aviso.textContent = 'Este navegador não compartilha imagens diretamente: a imagem foi baixada. Anexe-a na conversa do WhatsApp.';
        });
    }

    async function desenharFotoNotas(dados) {
        const largura = 1080;
        const margem = 48;
        const fonte = '"Segoe UI", Roboto, Arial, sans-serif';
        const cores = { azul: '#0A4FA0', azulClaro: '#E8F0FA', amarelo: '#F2B820', texto: '#1f2937', cinza: '#6b7280' };
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');

        const logo = await new Promise((resolver) => {
            const origem = document.querySelector('.logo-navbar');
            if (!origem) {
                resolver(null);
                return;
            }
            const img = new Image();
            img.onload = () => resolver(img);
            img.onerror = () => resolver(null);
            img.src = origem.src;
        });

        // Retângulo preenchido com cantos arredondados (retângulo simples em navegadores antigos).
        const retangulo = (x, y, w, h, raio) => {
            ctx.beginPath();
            if (ctx.roundRect) {
                ctx.roundRect(x, y, w, h, raio);
            } else {
                ctx.rect(x, y, w, h);
            }
            ctx.fill();
        };

        // Quebra o texto em linhas que caibam na largura disponível.
        const quebrar = (texto, larguraMax, estilo) => {
            ctx.font = estilo;
            const linhas = [];
            let atual = '';
            String(texto).split(' ').forEach((palavra) => {
                const teste = atual ? atual + ' ' + palavra : palavra;
                if (ctx.measureText(teste).width > larguraMax && atual) {
                    linhas.push(atual);
                    atual = palavra;
                } else {
                    atual = teste;
                }
            });
            linhas.push(atual);
            return linhas;
        };

        // Duas passagens: a primeira só mede a altura, a segunda desenha.
        const desenhar = (pintar) => {
            let y = 0;
            const alturaCabecalho = 190;

            if (pintar) {
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.fillStyle = cores.azul;
                ctx.fillRect(0, 0, largura, alturaCabecalho);
                ctx.fillStyle = cores.amarelo;
                ctx.fillRect(0, alturaCabecalho, largura, 8);

                let xTexto = margem;
                if (logo) {
                    const alturaLogo = 110;
                    const larguraLogo = logo.width * (alturaLogo / logo.height);
                    ctx.fillStyle = '#ffffff';
                    retangulo(margem - 12, 40 - 12, larguraLogo + 24, alturaLogo + 24, 14);
                    ctx.drawImage(logo, margem, 40, larguraLogo, alturaLogo);
                    xTexto = margem + larguraLogo + 40;
                }
                ctx.fillStyle = '#ffffff';
                ctx.font = `bold 44px ${fonte}`;
                ctx.fillText(dados.titulo, xTexto, 82);
                ctx.font = `28px ${fonte}`;
                ctx.fillText(dados.filtros, xTexto, 124);
                ctx.fillStyle = cores.amarelo;
                ctx.font = `bold 26px ${fonte}`;
                ctx.fillText(dados.emissao, xTexto, 162);
            }
            y = alturaCabecalho + 8 + 32;

            dados.notas.forEach((nota) => {
                const larguraTexto = largura - margem * 2 - 48;
                const linhasCliente = quebrar(nota.cliente, larguraTexto, `28px ${fonte}`);
                const linhasItens = nota.itens.flatMap((item) => quebrar('• ' + item, larguraTexto, `26px ${fonte}`));
                const altura = 16 + 36 + linhasCliente.length * 34 + linhasItens.length * 32 + 14;

                if (pintar) {
                    ctx.fillStyle = cores.azulClaro;
                    retangulo(margem, y, largura - margem * 2, altura, 14);
                    ctx.fillStyle = cores.amarelo;
                    ctx.fillRect(margem, y, 10, altura);

                    let yTexto = y + 16 + 28;
                    const x = margem + 30;
                    const cabecalhoNota = `Nota ${nota.nota}  ·  Setor ${nota.setor}`;
                    ctx.fillStyle = cores.azul;
                    ctx.font = `bold 30px ${fonte}`;
                    ctx.fillText(cabecalhoNota, x, yTexto);
                    if (nota.mapa) {
                        const xMapa = x + ctx.measureText(cabecalhoNota).width + 24;
                        ctx.fillStyle = cores.cinza;
                        ctx.font = `24px ${fonte}`;
                        ctx.fillText(`Mapa ${nota.mapa}`, xMapa, yTexto);
                    }
                    yTexto += 38;
                    ctx.fillStyle = cores.texto;
                    ctx.font = `28px ${fonte}`;
                    linhasCliente.forEach((linha) => { ctx.fillText(linha, x, yTexto); yTexto += 34; });
                    ctx.font = `26px ${fonte}`;
                    linhasItens.forEach((linha) => { ctx.fillText(linha, x, yTexto); yTexto += 32; });
                }
                y += altura + 14;
            });

            if (pintar) {
                ctx.fillStyle = cores.cinza;
                ctx.font = `24px ${fonte}`;
                ctx.fillText(`${dados.notas.length} nota(s) · ${dados.gerado}`, margem, y + 30);
            }

            return y + 70;
        };

        canvas.width = largura;
        canvas.height = 10;
        canvas.height = desenhar(false);
        desenhar(true);

        return new Promise((resolver) => canvas.toBlob(resolver, 'image/png'));
    }

    function atualizarContadores() {
        if (!tabela) {
            return;
        }

        const linhas = [...tabela.querySelectorAll('tbody tr')];
        const visiveis = document.getElementById('contadorVisiveis');
        const decididos = document.getElementById('contadorDecididos');

        if (visiveis) {
            visiveis.textContent = linhas.filter((l) => !l.classList.contains('d-none')).length;
        }
        if (decididos) {
            decididos.textContent = linhas.filter((l) => l.dataset.pendente === '0').length;
        }
    }

});
