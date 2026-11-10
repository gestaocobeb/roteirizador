/**
 * Farol: gera imagens (PNG) para envio no WhatsApp.
 * - Botão #baixarImagemFarol: painel do dia (cards das revendas e resumo).
 * - Botões [data-imagem-buffer="PM"]: tabela do buffer reprogramado da revenda.
 *   A mesma tabela serve a outras telas (ex.: Tirinha, notas no cartão), que
 *   informam "colunas" e "arquivo" próprios.
 * Desenho em <canvas> com JavaScript puro; os dados vêm formatados do PHP
 * (script#dadosImagemFarol). No celular com HTTPS abre o compartilhamento
 * direto; nos demais casos baixa o arquivo.
 */
(function () {
    'use strict';

    const fonte = document.getElementById('dadosImagemFarol');
    if (!fonte) {
        return;
    }

    const dados = JSON.parse(fonte.textContent);

    const COR = {
        fundo: '#f4f6f9',
        faixa: '#0b3d91',
        textoFaixa: '#ffffff',
        card: '#ffffff',
        cabecalhoCard: '#e8eef9',
        titulo: '#0b3d91',
        texto: '#212529',
        rotulo: '#495057',
        obs: '#6c757d',
        linha: '#dee2e6',
        zebra: '#f8f9fa',
        destaque: '#198754',
    };

    const LARGURA = 1080;
    const MARGEM = 32;
    const ESPACO = 20;
    const ALTURA_FAIXA = 120;
    const ALTURA_LINHA = 46;
    const FONTE = 'Segoe UI, Roboto, Arial, sans-serif';
    // Escala 2x para a imagem ficar nítida no celular.
    const ESCALA = 2;

    function carregarImagem(src) {
        return new Promise((resolve) => {
            const img = new Image();
            img.onload = () => resolve(img);
            img.onerror = () => resolve(null);
            img.src = src;
        });
    }

    function textoAjustado(ctx, texto, larguraMax) {
        if (ctx.measureText(texto).width <= larguraMax) {
            return texto;
        }
        let cortado = texto;
        while (cortado.length > 1 && ctx.measureText(cortado + '…').width > larguraMax) {
            cortado = cortado.slice(0, -1);
        }
        return cortado + '…';
    }

    function retangulo(ctx, x, y, w, h, r, cor) {
        ctx.fillStyle = cor;
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
        ctx.fill();
    }

    /**
     * Cria o canvas com fundo, faixa azul (logo, título, subtítulo) e rodapé.
     */
    async function novaImagem(altura, titulo, subtitulo) {
        const canvas = document.createElement('canvas');
        canvas.width = LARGURA * ESCALA;
        canvas.height = altura * ESCALA;
        const ctx = canvas.getContext('2d');
        ctx.scale(ESCALA, ESCALA);
        ctx.textBaseline = 'middle';

        ctx.fillStyle = COR.fundo;
        ctx.fillRect(0, 0, LARGURA, altura);
        ctx.fillStyle = COR.faixa;
        ctx.fillRect(0, 0, LARGURA, ALTURA_FAIXA);

        const logo = await carregarImagem(dados.logo);
        let xTitulo = MARGEM;
        if (logo) {
            const alturaLogo = 72;
            const larguraLogo = logo.width * (alturaLogo / logo.height);
            retangulo(ctx, MARGEM, 24, larguraLogo + 16, alturaLogo, 10, '#ffffff');
            ctx.drawImage(logo, MARGEM + 8, 24, larguraLogo, alturaLogo);
            xTitulo = MARGEM + larguraLogo + 40;
        }

        ctx.textAlign = 'left';
        ctx.fillStyle = COR.textoFaixa;
        ctx.font = `bold 36px ${FONTE}`;
        ctx.fillText(textoAjustado(ctx, titulo, LARGURA - xTitulo - MARGEM), xTitulo, 50);
        ctx.font = `26px ${FONTE}`;
        ctx.fillText(subtitulo, xTitulo, 90);

        ctx.textAlign = 'right';
        ctx.fillStyle = COR.obs;
        ctx.font = `16px ${FONTE}`;
        ctx.fillText(dados.gerado + ' - Roteirizador COBEB', LARGURA - MARGEM, altura - 28);

        return { canvas, ctx };
    }

    async function desenharFarol() {
        const revendas = dados.revendas;
        const colunas = Math.max(revendas.length, 1);
        const larguraCard = (LARGURA - MARGEM * 2 - ESPACO * (colunas - 1)) / colunas;
        const linhasPorCard = Math.max(...revendas.map((r) => r.linhas.length), 0);
        const alturaCard = 64 + linhasPorCard * ALTURA_LINHA + 12;
        const alturaResumo = dados.resumo.length ? 150 : 0;
        const altura = ALTURA_FAIXA + MARGEM + alturaCard + (alturaResumo ? ESPACO + alturaResumo : 0) + 60;

        const { canvas, ctx } = await novaImagem(altura, dados.titulo, dados.data);

        // Cards por revenda.
        const yCards = ALTURA_FAIXA + MARGEM;
        revendas.forEach((revenda, i) => {
            const x = MARGEM + i * (larguraCard + ESPACO);
            retangulo(ctx, x, yCards, larguraCard, alturaCard, 14, COR.card);
            retangulo(ctx, x, yCards, larguraCard, 56, 14, COR.cabecalhoCard);
            ctx.fillStyle = COR.cabecalhoCard;
            ctx.fillRect(x, yCards + 40, larguraCard, 16);

            ctx.fillStyle = COR.titulo;
            ctx.font = `bold 24px ${FONTE}`;
            ctx.textAlign = 'center';
            ctx.fillText(textoAjustado(ctx, revenda.titulo, larguraCard - 24), x + larguraCard / 2, yCards + 29);

            revenda.linhas.forEach((linha, j) => {
                const [rotulo, valor, obs] = linha;
                const y = yCards + 64 + j * ALTURA_LINHA;

                if (j > 0) {
                    ctx.strokeStyle = COR.linha;
                    ctx.lineWidth = 1;
                    ctx.beginPath();
                    ctx.moveTo(x + 14, y);
                    ctx.lineTo(x + larguraCard - 14, y);
                    ctx.stroke();
                }

                const meio = obs ? y + ALTURA_LINHA / 2 - 7 : y + ALTURA_LINHA / 2;

                // Valor primeiro: o rótulo usa só o espaço que sobra (com folga de 14px).
                ctx.textAlign = 'right';
                ctx.fillStyle = COR.texto;
                ctx.font = `bold 21px ${FONTE}`;
                ctx.fillText(valor, x + larguraCard - 16, meio);
                const larguraValor = ctx.measureText(valor).width;

                ctx.textAlign = 'left';
                ctx.fillStyle = COR.rotulo;
                ctx.font = `18px ${FONTE}`;
                ctx.fillText(textoAjustado(ctx, rotulo, larguraCard - 32 - larguraValor - 14), x + 16, meio);

                if (obs) {
                    ctx.textAlign = 'right';
                    ctx.fillStyle = COR.obs;
                    ctx.font = `italic 15px ${FONTE}`;
                    ctx.fillText(textoAjustado(ctx, obs, larguraCard - 32), x + larguraCard - 16, meio + 19);
                }
            });
        });

        // Resumo do dia.
        if (alturaResumo) {
            const y = yCards + alturaCard + ESPACO;
            const largura = LARGURA - MARGEM * 2;
            retangulo(ctx, MARGEM, y, largura, alturaResumo, 14, COR.card);

            ctx.textAlign = 'left';
            ctx.fillStyle = COR.titulo;
            ctx.font = `bold 24px ${FONTE}`;
            ctx.fillText('Resumo do dia', MARGEM + 16, y + 30);

            const larguraItem = largura / dados.resumo.length;
            dados.resumo.forEach(([rotulo, valor], i) => {
                const cx = MARGEM + larguraItem * i + larguraItem / 2;
                ctx.textAlign = 'center';
                ctx.fillStyle = COR.rotulo;
                ctx.font = `17px ${FONTE}`;
                ctx.fillText(textoAjustado(ctx, rotulo, larguraItem - 12), cx, y + 78);
                ctx.fillStyle = COR.destaque;
                ctx.font = `bold 28px ${FONTE}`;
                ctx.fillText(valor, cx, y + 114);
            });
        }

        return canvas;
    }

    /**
     * Tabela do buffer reprogramado de uma revenda.
     */
    async function desenharBuffer(revenda) {
        const buffer = dados.buffers[revenda];
        const pedidos = buffer.pedidos;
        const alturaLinha = 42;
        const alturaTabela = 56 + pedidos.length * alturaLinha + 56;
        const altura = ALTURA_FAIXA + MARGEM + alturaTabela + 60;
        const largura = LARGURA - MARGEM * 2;

        const { canvas, ctx } = await novaImagem(altura, buffer.titulo, dados.data);

        // Colunas: [título, largura relativa, alinhamento]. Outras telas (Tirinha) mandam as próprias.
        const colunas = buffer.colunas || [
            ['Cliente', 0.10, 'left'],
            ['Nome do cliente', 0.36, 'left'],
            ['Setor', 0.28, 'left'],
            ['Pedidos', 0.10, 'right'],
            ['Volume total (HL)', 0.16, 'right'],
        ];
        const xTabela = MARGEM;
        const yTabela = ALTURA_FAIXA + MARGEM;
        const larguraUtil = largura - 32;

        function linha(valores, y, fonteTexto, cor) {
            let x = xTabela + 16;
            ctx.font = fonteTexto;
            ctx.fillStyle = cor;
            colunas.forEach(([, proporcao, alinhamento], i) => {
                const w = larguraUtil * proporcao;
                const texto = textoAjustado(ctx, valores[i] ?? '', w - 12);
                ctx.textAlign = alinhamento;
                ctx.fillText(texto, alinhamento === 'right' ? x + w : x, y);
                x += w;
            });
        }

        retangulo(ctx, xTabela, yTabela, largura, alturaTabela, 14, COR.card);
        retangulo(ctx, xTabela, yTabela, largura, 56, 14, COR.cabecalhoCard);
        ctx.fillStyle = COR.cabecalhoCard;
        ctx.fillRect(xTabela, yTabela + 40, largura, 16);
        linha(colunas.map((c) => c[0]), yTabela + 28, `bold 20px ${FONTE}`, COR.titulo);

        pedidos.forEach((pedido, i) => {
            const y = yTabela + 56 + i * alturaLinha;
            if (i % 2 === 1) {
                ctx.fillStyle = COR.zebra;
                ctx.fillRect(xTabela, y, largura, alturaLinha);
            }
            linha(pedido, y + alturaLinha / 2, `19px ${FONTE}`, COR.texto);
        });

        const yTotal = yTabela + 56 + pedidos.length * alturaLinha;
        ctx.strokeStyle = COR.linha;
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(xTabela + 14, yTotal);
        ctx.lineTo(xTabela + largura - 14, yTotal);
        ctx.stroke();

        ctx.textAlign = 'right';
        ctx.fillStyle = COR.destaque;
        ctx.font = `bold 22px ${FONTE}`;
        ctx.fillText('Total: ' + buffer.total, xTabela + largura - 16, yTotal + 28);

        return canvas;
    }

    /**
     * Compartilha (celular com HTTPS) ou baixa o PNG.
     */
    async function entregar(canvas, nome, titulo) {
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
        const arquivo = new File([blob], nome, { type: 'image/png' });

        if (navigator.canShare && navigator.canShare({ files: [arquivo] })) {
            try {
                await navigator.share({ files: [arquivo], title: titulo });
                return;
            } catch (erro) {
                if (erro.name === 'AbortError') {
                    return;
                }
            }
        }

        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = nome;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(link.href), 1000);
    }

    function ligar(botao, gerar) {
        botao.addEventListener('click', async () => {
            botao.disabled = true;
            try {
                await gerar();
            } finally {
                botao.disabled = false;
            }
        });
    }

    const sufixoData = dados.data.replace(/\D/g, '') || 'dia';

    const botaoFarol = document.getElementById('baixarImagemFarol');
    if (botaoFarol) {
        ligar(botaoFarol, async () => entregar(await desenharFarol(), `farol-${sufixoData}.png`, dados.titulo));
    }

    document.querySelectorAll('[data-imagem-buffer]').forEach((botao) => {
        const revenda = botao.dataset.imagemBuffer;
        if (!dados.buffers[revenda]) {
            return;
        }
        ligar(botao, async () => entregar(
            await desenharBuffer(revenda),
            dados.buffers[revenda].arquivo || `buffer-${revenda.toLowerCase()}-${sufixoData}.png`,
            dados.buffers[revenda].titulo
        ));
    });
})();
