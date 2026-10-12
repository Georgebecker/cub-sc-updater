/* Botao "Buscar agora" do painel CUB SC. Dados e textos chegam em window.cubScAdmin. */
(function () {
	'use strict';

	var config = window.cubScAdmin || {};
	var textos = config.i18n || {};
	var btn = document.getElementById('cub-sc-fetch');
	var status = document.getElementById('cub-sc-status');

	if (!btn || !status) {
		return;
	}

	btn.addEventListener('click', function () {
		status.textContent = textos.searching || 'Buscando...';

		var xhr = new XMLHttpRequest();
		xhr.open('POST', config.ajaxUrl);
		xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
		xhr.onload = function () {
			var resposta = {};
			try {
				resposta = JSON.parse(xhr.responseText);
			} catch (e) {
				resposta = {};
			}
			if (xhr.status === 200 && resposta && resposta.success) {
				status.textContent = textos.reloading || 'Pronto, recarregando...';
				location.reload();
				return;
			}
			var motivo = (resposta && resposta.data && resposta.data.message) ? resposta.data.message : ('HTTP ' + xhr.status);
			status.textContent = (textos.errorPrefix || 'Erro:') + ' ' + motivo;
		};
		xhr.onerror = function () {
			status.textContent = textos.networkError || 'Erro de rede.';
		};
		xhr.send('action=' + encodeURIComponent(config.action) + '&nonce=' + encodeURIComponent(config.nonce));
	});
})();
