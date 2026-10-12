=== CUB SC Updater ===
Contributors: georgehbecker
Donate link: https://buymeacoffee.com/georgehbecker
Tags: cub, sinduscon, santa catarina, construcao civil, custo unitario basico
Requires at least: 5.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.5
License: MIT
License URI: https://opensource.org/licenses/MIT

Mantém o CUB (Custo Unitário Básico) de Santa Catarina atualizado: busca no Sinduscon-Fpolis e publica em /cub-sc/ pelo shortcode [cub_sc].

== Description ==

Este plugin mantém o valor do **CUB de Santa Catarina** sempre atualizado no site, sem trabalho manual.

No **dia 5 de cada mês** o WP-Cron visita a página do CUB mensal do Sinduscon-Fpolis e lê o mês de
referência, o "para ser usado em" e os valores **Residencial** e **Comercial**. É quando o CUB do mês
costuma entrar em vigor — o plugin não fica consultando o site todos os dias à toa: se o mês corrente
já está publicado, ele não faz requisição nenhuma.

Recursos:

* Shortcode `[cub_sc]` com o valor vigente, o mês de referência e o histórico.
* Página `/cub-sc/` criada automaticamente na ativação.
* Painel **CUB SC** no admin, com o botão *Buscar agora*.
* Atualização automática no dia 5; se aquele dia falhar, tenta de novo nos dias seguintes.
* Cria uma linha nova no histórico apenas quando algum valor realmente muda.
* Sem dependências externas, sem Composer e sem etapa de build.

Aviso: os dados são lidos do site do Sinduscon-Fpolis. Se aquele layout mudar, a busca falha com um
motivo visível no painel (o plugin nunca publica um valor que não conseguiu ler).

== Installation ==

1. Envie a pasta `cub-sc-updater` para `wp-content/plugins/` (ou instale o zip pelo painel).
2. Ative em *Plugins*. A página `/cub-sc/` é criada, a atualização mensal é agendada e a primeira
   busca roda poucos segundos depois.
3. Confira em **CUB SC** no menu do admin; use *Buscar agora* sempre que quiser forçar uma leitura.

== Frequently Asked Questions ==

= Quando o valor é atualizado? =

No dia 5 de cada mês, automaticamente. O evento do cron roda todos os dias, mas só faz a busca a
partir do dia 5 e enquanto o CUB do mês corrente ainda não estiver guardado — ou seja, na prática uma
consulta por mês ao site do Sinduscon. Se a busca do dia 5 falhar (site fora do ar, por exemplo), ela é
tentada de novo no dia seguinte. O botão *Buscar agora* ignora essa regra e busca na hora.

= De onde vem o valor? =

Da página pública do CUB mensal do Sinduscon-Fpolis. O endereço pode ser trocado pelo filtro
`cub_sc_source_url`.

= Posso mudar o dia ou o horário da busca automática? =

Sim: `cub_sc_fetch_day` (padrão 5) e `cub_sc_fetch_hour` (padrão 9, no fuso do site).

= Posso mudar o tamanho do histórico? =

Sim, pelo filtro `cub_sc_history_max` (padrão: 36 registros).

= Ao excluir o plugin, o que acontece com os dados? =

A exclusão faz limpeza total: o histórico, a página `/cub-sc/` e os agendamentos são removidos.
Desativar o plugin, por outro lado, apenas para o cron e preserva tudo.

== External services ==

= CUB mensal do Sinduscon-Fpolis =

Este plugin consulta um serviço externo para obter o valor do CUB de Santa Catarina.

* **Serviço:** página pública do CUB mensal do Sinduscon-Fpolis.
* **Endereço:** https://sinduscon-fpolis.org.br/servico/cub-mensal/ (trocável pelo filtro `cub_sc_source_url`).
* **O que é enviado:** uma requisição HTTP `GET` comum, com os cabeçalhos `Accept: text/html`,
  `Accept-Language: pt-BR` e um user-agent próprio do plugin. Nenhum dado do site, do administrador ou
  do visitante é enviado: não há cookies, autenticação nem envio de formulários.
* **Quando:** no dia 5 de cada mês (o evento do WP-Cron só age a partir do dia 5 e enquanto faltar o mês
  corrente) e quando o administrador usa o botão *Buscar agora*.
* **Por que:** é a fonte pública do valor do CUB de Santa Catarina que o plugin exibe.
* **Termos de uso e privacidade do serviço:** https://sinduscon-fpolis.org.br/politica-de-privacidade/

O plugin não envia dados a nenhum outro serviço, não coleta estatísticas e não faz telemetria.

== Changelog ==

= 1.5 =
* Preparação para o repositório oficial: `Text Domain` passou a ser `cub-sc-updater`, igual ao slug
  do plugin (o WordPress casa o domínio de tradução com a pasta do plugin).
* readme.txt: descrição curta dentro do limite de 150 caracteres, campo `Donate link` e seção
  "External services" descrevendo a consulta ao site do Sinduscon-Fpolis.
* Código: domínio de tradução sempre literal nas chamadas de tradução (padrão exigido pelo WordPress).

= 1.4 =
* Atualização automática passou a ser mensal: roda no dia 5, quando o CUB do mês entra em vigor.
* O evento diário do cron agora só busca a partir do dia 5 e enquanto faltar o mês corrente — o site
  do Sinduscon deixa de ser consultado todos os dias.
* Falha no dia 5 é repetida nos dias seguintes, em vez de deixar o valor um mês desatualizado.
* Filtros `cub_sc_fetch_day` e `cub_sc_fetch_hour` para ajustar dia e horário.

= 1.3 =
* Empacotamento: as entradas do zip passaram a usar barra normal (`/`). O zip gerado no Windows vinha
  com barra invertida (`\`) e o WordPress recusava o pacote no envio.
* Distribuição: pacote `cub_sc_updater.zip`, com a pasta do plugin na raiz, para instalar pelo painel
  ou extrair sobre a instalação existente.

= 1.2 =
* i18n: Text Domain `cub-sc`, strings passando por `__()`/`esc_html__()` e `languages/` preparado.
* Admin: script movido para `assets/cub-sc-admin.js` com `wp_enqueue_script()` + `wp_add_inline_script()`.
* Front: estilo do shortcode movido para `assets/cub-sc.css`, carregado só em páginas com `[cub_sc]`.
* `uninstall.php` com limpeza total (option, página, cron e trava), inclusive em multisite.
* `readme.txt` do repositório oficial e `.gitattributes` com `export-ignore`.
* Corrigido: mensagens de erro do admin passaram a ter acentuação correta.

= 1.1 =
* Extração por blocos (rótulo + valor seguinte), que impediu o valor residencial de vazar para comercial.
* Datas no fuso do site (`wp_date()`), dedupe contra o registro mais recente e erros visíveis no admin.

= 1.0 =
* Primeira versão.

== Upgrade Notice ==

= 1.5 =
Ajuste interno do domínio de tradução, para casar com o slug do plugin no repositório oficial.

= 1.4 =
A busca automática agora acontece no dia 5 de cada mês, em vez de todos os dias.

= 1.3 =
Corrige o pacote que o WordPress recusava no envio e adiciona o pacote de atualização dos arquivos.

= 1.2 =
Correções de leitura do site, i18n, assets próprios e limpeza total ao excluir.
