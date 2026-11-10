// Aba CTE: preencher o formulário de frete e filtrar a tabela de fretes (JavaScript puro).
(function () {
    'use strict';

    const form = document.getElementById('formFrete');

    // Botões "sem frete" e "editar" preenchem o formulário de frete.
    document.querySelectorAll('[data-frete-cidades]').forEach((botao) => {
        botao.addEventListener('click', () => {
            form.elements.cidades.value = botao.dataset.freteCidades;
            form.elements.valor.value = botao.dataset.freteValor || '';
            form.elements.codigo.value = botao.dataset.freteCodigo || '';
            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
            form.elements.valor.focus();
        });
    });

    // Filtro da tabela de fretes pelas cidades.
    const filtro = document.querySelector('[data-filtro-fretes]');
    if (filtro) {
        filtro.addEventListener('input', () => {
            // Sem acento, como as cidades da tabela.
            const termo = filtro.value.trim().toUpperCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            document.querySelectorAll('tr[data-frete]').forEach((linha) => {
                linha.classList.toggle('d-none', termo !== '' && !linha.dataset.frete.includes(termo));
            });
        });
    }
})();
