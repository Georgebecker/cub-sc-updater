# languages

Pasta das traduções do plugin (`Text Domain: cub-sc`).

As strings-fonte do código já são **PT-BR**, então o plugin funciona sem nenhum arquivo aqui —
esta pasta existe para quando (e se) aparecer tradução.

Para traduzir, crie `cub-sc-<locale>.po` (e o `.mo` correspondente), por exemplo
`cub-sc-en_US.po`. O `load_plugin_textdomain()` do plugin carrega esta pasta automaticamente.

Arquivos `.po` podem ser editados com Poedit, WP-CLI (`wp i18n make-pot`) ou direto no editor.
