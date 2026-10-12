# CUB SC Updater

Mantém o **CUB (Custo Unitário Básico) de Santa Catarina** atualizado no site: no **dia 5 de cada mês**
o plugin busca o valor no Sinduscon-Fpolis, guarda o histórico e publica em `/cub-sc/` pelo shortcode
`[cub_sc]`.

> **Idioma: PT-BR, por definição.** O CUB é um índice genuinamente brasileiro (ABNT NBR 12721) e este
> plugin cuida especificamente de Santa Catarina — não existe versão em outro idioma. A estrutura já
> está preparada (`Text Domain: cub-sc-updater`, igual ao slug do plugin) e a fonte de dados é trocável,
> caso o plugin venha a atender outros estados.

| | |
|---|---|
| **Versão** | 1.5 |
| **Compatibilidade** | WordPress 5.3+ · PHP 7.4+ (`Tested up to: 7.1`) |
| **Validado em produção** | <https://imoxs.com.br/cub-sc/> (PHP 8.1) |
| **Validado na máquina do autor** | PHP 8.4.26 — lint + 73 verificações automatizadas |
| **Dependências** | nenhuma (sem Composer, sem etapa de build) |
| **Licença** | MIT |

## Finalidade

Publicar o CUB-SC sem trabalho manual e sem risco de valor errado no ar: o plugin só publica o que
consegue interpretar e, quando a leitura falha, mostra o motivo no admin em vez de exibir um número
duvidoso.

## Campos

O que o plugin lê na página do Sinduscon:

| Campo | Rótulo procurado na página | Exemplo |
|---|---|---|
| Mês de referência | `Mês de Referência:` | `Setembro/2026` |
| Para ser usado em | `Para ser usado em:` | `Outubro/2026` |
| Residencial | `Residencial Médio` | `R$ 3.171,96` |
| Comercial | `Comercial Médio` | `R$ 3.374,19` |

O que ele guarda na option `cub_sc_updater_data` — histórico de até 36 registros, o mais novo no
índice `0`:

| Campo | Conteúdo |
|---|---|
| `time` | data e hora da leitura, no fuso do site |
| `reference`, `use_for` | mês de referência e "para ser usado em" |
| `residential`, `commercial` | valor como veio do site (`3.171,96`) |
| `residential_value`, `commercial_value` | o mesmo valor em número (`3171.96`), pronto para cálculo |

A mesma option guarda `last_attempt`, `last_checked`, `last_success` e `last_error`.

## Como funciona

1. **Buscar** — `wp_remote_get()` na página do CUB mensal do Sinduscon-Fpolis, no **dia 5 de cada mês**
   (quando o CUB do mês entra em vigor) ou pelo botão *Buscar agora* no admin. O evento do cron é
   diário, mas só age a partir do dia 5 e enquanto faltar o mês corrente — se o valor do mês já está
   guardado, o site do Sinduscon não é consultado.
2. **Interpretar** — `DOMDocument`/`DOMXPath` localiza o rótulo e lê o valor no bloco imediatamente
   seguinte; se o DOM não servir, a mesma lógica roda por expressões regulares sobre o texto.
3. **Guardar** — grava um registro novo apenas quando algo muda (comparação com a leitura mais
   recente); falhas viram `WP_Error`, com o motivo registrado em `last_error`.
4. **Exibir** — shortcode `[cub_sc]` e painel **CUB SC** no admin. O CSS só carrega em páginas que usam
   o shortcode; o JS do admin, só na própria tela do plugin.

A página `/cub-sc/` (contendo `[cub_sc]`) é criada na ativação. Ajustáveis por filtro: `cub_sc_source_url`
(fonte), `cub_sc_history_max` (tamanho do histórico), `cub_sc_fetch_day` (dia, padrão 5) e
`cub_sc_fetch_hour` (hora, padrão 9, no fuso do site).

Se a busca do dia 5 falhar (site fora do ar, por exemplo), ela é repetida nos dias seguintes: o valor
não fica um mês inteiro desatualizado.

## Instalação

1. Envie a pasta `cub-sc-updater/` (ou o `cub_sc_updater.zip`) para `wp-content/plugins/`.
2. Ative em *Plugins* — a página `/cub-sc/` é criada, a atualização mensal é agendada e a primeira busca
   roda poucos segundos depois.
3. Confira **CUB SC** no menu do admin; use *Buscar agora* sempre que quiser forçar uma leitura.

**Desativar** apenas para o cron e preserva tudo. **Excluir** faz limpeza total — histórico, página e
agendamentos — pelo `uninstall.php`, inclusive em multisite.

Se o layout do Sinduscon mudar, a busca falha e o motivo aparece no painel: o plugin nunca publica um
valor que não conseguiu ler.

## Perguntas frequentes

**Quando o valor é atualizado?**
No dia 5 de cada mês. O evento do cron roda todos os dias, mas só faz a busca a partir do dia 5 e
enquanto o CUB do mês corrente ainda não estiver guardado — na prática, uma consulta por mês ao site do
Sinduscon. Se a busca do dia 5 falhar, é tentada de novo no dia seguinte. O botão *Buscar agora* ignora
essa regra e busca na hora.

**O plugin extrai o CUB de vários meses?**
Não. Ele lê o valor vigente (o bloco do mês corrente, com rótulo `Residencial Médio` / `Comercial Médio`)
e guarda uma linha de histórico apenas quando algum número muda — a página mostra o CUB atual e essa
lista de mudanças.

**Dá para mudar o dia ou o horário da busca automática?**
Sim: `cub_sc_fetch_day` (padrão 5) e `cub_sc_fetch_hour` (padrão 9, no fuso do site).

**E o histórico, tem limite?**
36 registros por padrão, ajustável pelo filtro `cub_sc_history_max`.

**Ao excluir o plugin, o que acontece?**
Limpeza total: histórico, página `/cub-sc/` e agendamentos. Desativar apenas para o cron e preserva tudo.

## Versões

### 1.5
- **Preparação para o repositório oficial** — o `Text Domain` passou a ser `cub-sc-updater`, igual ao
  slug do plugin (o WordPress casa o domínio de tradução com a pasta do plugin).
- **`readme.txt`** — descrição curta reduzida de 157 para 139 caracteres (o wp.org limita a 150),
  `Donate link` e a seção **External services**, obrigatória porque o plugin consulta um site de terceiros.
- **Domínio de tradução literal** — as 36 chamadas de tradução passaram a usar `'cub-sc-updater'` como
  texto literal, como o padrão de código do WordPress exige.
- **Testes de coerência** — 7 verificações novas travam os erros que o revisor do wp.org recusa:
  `Text Domain` diferente do slug, versão diferente do `Stable tag`, domínio não-literal, readme sem as
  seções obrigatórias e descrição curta fora do limite.

### 1.4
- **Atualização mensal** — a busca automática passou a acontecer no **dia 5**, quando o CUB do mês entra
  em vigor. O evento do cron continua diário, mas `fetch_mensal()` só age a partir do dia 5 e enquanto
  faltar o mês corrente: o site do Sinduscon deixa de ser consultado todos os dias.
- **Resiliente** — falha no dia 5 é repetida nos dias seguintes, em vez de deixar o valor um mês
  desatualizado; registro antigo sem "para ser usado em" conta como pendente e força nova leitura.
- **Filtros novos** — `cub_sc_fetch_day` (padrão 5) e `cub_sc_fetch_hour` (padrão 9, no fuso do site).

### 1.3
- **Corrigido: o WordPress recusava o pacote no envio.** O zip saía do `Compress-Archive` do Windows com
  barra invertida nos nomes das entradas (`cub-sc\assets\cub-sc.css`); a especificação ZIP exige barra
  normal e o PclZip do WordPress lia tudo como um único arquivo. O empacotamento agora escreve as entradas
  com `/` e **valida** o resultado (o build falha se voltar a acontecer).
- **Distribuição** — pacote `cub_sc_updater.zip`, com a pasta do plugin na raiz: instala pelo painel ou
  sobrescreve uma instalação existente ao ser extraído em `wp-content/plugins/`.
- **Versão visível** — marcada no cabeçalho e no `readme.txt`, para conferir na tela de Plugins se o
  arquivo novo realmente subiu.

### 1.2
- **i18n** — `Text Domain: cub-sc`, `Domain Path: /languages` e todas as strings de tela passando por
  `__()`/`esc_html__()` (antes eram literais fixos).
- **Assets** — o script do admin saiu do HTML e virou `assets/cub-sc-admin.js`, enfileirado com
  `wp_enqueue_script()` + `wp_add_inline_script()` (nonce, URL do AJAX e textos em `window.cubScAdmin`);
  o estilo do shortcode virou `assets/cub-sc.css`, carregado só em páginas com `[cub_sc]`.
- **Distribuição** — `uninstall.php` (limpeza total, inclusive multisite), `readme.txt` para o wp.org,
  `.gitattributes` com `export-ignore` e `build.ps1` para gerar o `cub_sc_updater.zip`.
- **Corrigido** — as mensagens de erro do admin passaram a ter acentuação correta.

### 1.1
- **Corrigido: o valor comercial vinha do residencial.** O XPath pegava o primeiro nó que citasse a
  palavra (no site real, um parágrafo de 600 caracteres) e as regex de rótulo não casavam com
  "M**é**dio". A leitura agora é por bloco: rótulo curto + primeiro valor da janela seguinte.
- **Corrigido: histórico com uma duplicata por dia.** O dedupe comparava com o registro mais *antigo*;
  agora compara com a leitura mais recente e só cria linha quando algo muda de verdade.
- **Corrigido: valores colados em faixas.** `sanitize_price()` transformava `2.500,00 a 2.600,00` em
  `2.500,002.600,00`; agora extrai só o primeiro preço e exibe formatado (`R$ 3.171,96`).
- **Corrigido: datas no fuso do servidor** — `date()` trocado por `wp_date()` (fuso do site).
- **Corrigido: ativação travando até 20 s** por requisição HTTP síncrona — a primeira busca passou a
  ser agendada com `wp_schedule_single_event()`.
- **Corrigido: desativação incompleta** removia um único evento do cron — agora limpa o agendamento todo.
- **Corrigido: consulta ao banco em todo pageview** para achar `/cub-sc/` — a página é criada na
  ativação e restaurada da lixeira em vez de duplicada.
- **Adicionado** — campo "Para ser usado em", erro visível no admin (`WP_Error` + `last_error`),
  nonce e `403` no AJAX, trava por *transient* contra buscas simultâneas, detecção de charset e testes
  automatizados que rodam sem WordPress.

### 1.0
- Primeira versão: busca diária no Sinduscon-Fpolis, histórico e shortcode `[cub_sc]`.

## Desenvolvimento

- **Testes** — não precisam de WordPress (stubs + HTML real de `tests/fixtures/`):
  ```powershell
  php tests/test-cub-sc.php
  ```
  São 73 verificações: parser contra o HTML real do site, histórico, falhas, travas, AJAX, agenda do
  dia 5, assets, i18n e as regras do repositório oficial (Text Domain = slug, versão = `Stable tag`,
  domínio de tradução literal, descrição curta ≤ 150 caracteres, `License`/`License URI` iguais no PHP
  e no readme, seção de serviços externos presente). Os fixtures são capturas da página do Sinduscon —
  trocando um arquivo e rodando os testes, dá para ver exatamente o que parou de ser lido se o site mudar.
- **Lint** — `php -l cub-sc-updater.php` (PHP local: `C:\Users\user\tools\php\php.exe`, 8.4.26).
- **Pacote** — `.\build.ps1` gera o `cub_sc_updater.zip` (pasta `cub-sc-updater/` na raiz, nomes de
  entrada com barra normal) e recusa empacotar com erro de sintaxe, teste quebrado, BOM ou entrada com
  barra invertida. Instalação limpa: *Plugins → Adicionar novo → Enviar plugin*. Atualização: extraia o
  zip em `wp-content/plugins/` para sobrescrever a pasta existente (o painel recusa pasta repetida).
- **Ganchos de proteção** — `.githooks\instalar.ps1` bloqueia `.env` no commit **e** no push.
- **Padrões** — UTF-8 sem BOM; PHP com quebras LF; todo o PHP do plugin em um arquivo único.
- **Regras do projeto** — `docs/REGRAS_DE_OURO.md`.

## Pendências

- [x] ~~**Teste de ponta a ponta em WordPress real**~~ — validado em produção
      (<https://imoxs.com.br/cub-sc/>, PHP 8.1): a ativação criou a página, a busca gravou o registro e o
      shortcode publicou os valores. O painel do admin segue sem teste local (não há WP nesta máquina).
- [ ] **Adaptação para outros estados** — o CUB é divulgado por vários Sinduscons; o endereço já é
      trocável pelo filtro `cub_sc_source_url`, mas cada site tem um layout próprio e a leitura
      precisaria de ajuste.

## Autor

**George Herman Becker** — Gestão em Tecnologia da Informação

- **PIX:** `a8b68e14-edfe-4450-88f2-c2af4aca2a6c`
- **Buy Me a Coffee:** <https://buymeacoffee.com/georgehbecker>
- **LinkedIn:** <https://www.linkedin.com/in/georgehbecker/>

## Licença

MIT — veja [LICENSE](./LICENSE).
