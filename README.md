# CUB SC Updater

Plugin WordPress de arquivo único que busca o valor do **CUB (Custo Unitário Básico) de Santa Catarina**
no site do Sinduscon-Fpolis, guarda histórico e publica o valor em `/cub-sc/` pelo shortcode `[cub_sc]`.

**Autor:** George Herman Becker
**Gestão em Tecnologia da Informação**

**Apoie o autor:**
- **PIX:** `a8b68e14-edfe-4450-88f2-c2af4aca2a6c`
- **Buy Me a Coffee:** <https://buymeacoffee.com/georgehbecker>
- **LinkedIn:** <https://www.linkedin.com/in/georgehbecker/>

---

## O que tem aqui

- `cub-sc-updater.php` — o plugin (arquivo único, 276 linhas).
- `docs/REGRAS_DE_OURO.md` — as regras de ouro do projeto (contrato de trabalho).
- `.githooks/` — ganchos que bloqueiam o `.env` no commit **e** no push.
- `LICENSE` — MIT.

## Como o plugin funciona

1. **Buscar** — `wp_remote_get()` na página do Sinduscon-Fpolis, disparado pelo cron diário `cub_sc_updater_daily_event`.
2. **Interpretar** — `DOMDocument`/`DOMXPath` procura "Residencial", "Comercial" e "Mês de Referência";
   se o DOM falhar, cai para expressões regulares sobre o HTML.
3. **Guardar** — option `cub_sc_updater_data` (histórico de até 36 registros + data da última verificação).
4. **Exibir** — shortcode `[cub_sc]` na página `/cub-sc/` (criada automaticamente na ativação)
   e painel **CUB SC** no admin, com o botão *Buscar agora*.

## Instalação

1. Copie a pasta para `wp-content/plugins/cub-sc/`.
2. Ative em *Plugins* — a página `/cub-sc/` é criada e o cron agendado.
3. Acompanhe em **CUB SC** (admin); use *Buscar agora* para forçar uma atualização.

## Requisitos

- WordPress 5.x+ e PHP 7.4+ (`DOMDocument`, `libxml`, `wp_remote_get`).
- Sem dependências externas, sem Composer, sem etapa de build.

## Desenvolvimento

- **Ative os ganchos de proteção** em um clone novo: `.githooks\instalar.ps1` (ou `git config core.hooksPath .githooks`).
- Codificação **UTF-8 sem BOM** em todos os arquivos.
- `cub-sc-updater.php` usa quebras **LF** — mantenha assim para não poluir o histórico com diffs
  de fim de linha (a documentação e os ganchos vieram do `projeto_becker` em CRLF).
- **PHP local já instalado**: `C:\Users\user\tools\php\php.exe` (PHP 8.4.26 NTS x64, com `php.ini` próprio,
  `mbstring` habilitado e `date.timezone = America/Sao_Paulo`). Validação de sintaxe:
  ```powershell
  & 'C:\Users\user\tools\php\php.exe' -l cub-sc-updater.php
  ```
- **Ainda não há WordPress local** (nem MySQL/Apache), então o que depende do core do WP precisa de
  stubs ou de uma instalação local — a checagem de sintaxe, porém, já roda aqui.

## Melhorias mapeadas (próxima rodada)

Diagnóstico já feito, aguardando execução:

- [ ] **Histórico** — o dedupe compara com o registro mais **antigo** (`end()` após `array_unshift`), não com o anterior.
      Comprovado com PHP: enquanto a referência não muda, funciona; **da primeira mudança em diante grava 1 duplicata por dia**
      (`A → B, B, B, B` vira `B | B | B | B | A`), estourando o cap de 36 em cerca de um mês
- [ ] **Datas** — usa `date()` (fuso do servidor/UTC); trocar por `wp_date()`/`current_time()`
- [ ] **Ativação** — dispara requisição HTTP síncrona (trava a tela até 20 s); agendar com `wp_schedule_single_event()`
- [ ] **Desativação** — remove só 1 evento de cron; usar `wp_clear_scheduled_hook()`
- [ ] **Página `/cub-sc/`** — `maybe_create_page()` consulta o banco em **toda** requisição; criar só na ativação
- [ ] **Robustez** — acessos ao histórico sem `isset` (warnings no PHP 8)
- [ ] **Segurança** — AJAX sem nonce (CSRF); usar `check_ajax_referer()` + nonce no JS
- [ ] **Segurança** — `wp_die()` deveria responder 403, não 200
- [ ] **Scraper** — hoje raspa a home do site, não a página do CUB; URL deveria ser específica e filtrável
- [ ] **Scraper** — XPath pega o primeiro nó com o texto (pode ser menu/breadcrumb); restringir à linha com o valor
- [ ] **Scraper** — fallback por regex procura no HTML inteiro; limitar a janela de busca
- [ ] **Scraper** — detectar o charset da página em vez do truque `<?xml encoding="utf-8" ?>`
- [ ] **Scraper** — `libxml_use_internal_errors()` não é restaurado
- [ ] **Diagnóstico** — `fetch_and_store()` devolve só `false`; guardar `last_error` e mostrar o motivo no admin
- [ ] **Concorrência** — lock para cron + botão manual não rodarem juntos
- [ ] **Valores** — `sanitize_price()` funde números: `'2.500,00 a 2.600,00'` → `'2.500,002.600,00'` e
      `'R$ 2.500,00 - R$ 2.600,00'` → `'2.500,00-2.600,00'` (comprovado). Extrair só o primeiro preço e guardar valor numérico
- [ ] **WordPress** — i18n (`Text Domain`) e cabeçalho com `Requires at least`/`Requires PHP`/`License`
- [ ] **WordPress** — `wp_enqueue_script()`/`wp_add_inline_script()` no lugar do JS inline; AJAX devolvendo dados em vez de `location.reload()`
- [ ] **Empacotamento** — `uninstall.php`, `readme.txt` e `.gitattributes` (export-ignore)
