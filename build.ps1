# build.ps1 - monta o cub_sc_updater.zip, pronto para instalar/atualizar no WordPress
# Uso: powershell -ExecutionPolicy Bypass -File build.ps1
#
# O zip traz a pasta do plugin na raiz: instala pelo painel (Plugins > Adicionar novo > Enviar plugin)
# quando a pasta ainda nao existe, ou sobrescreve uma instalacao existente ao ser extraido em
# wp-content/plugins/ (o painel recusa sobrescrever pasta que ja existe).
#
# ATENCAO: os nomes das entradas usam barra normal (/). O Compress-Archive do Windows grava barra
# invertida (\), o que faz o WordPress recusar o pacote - por isso o zip e escrito aqui byte a byte,
# com os nomes sob controle, e conferido no final.

$ErrorActionPreference = "Stop"

$root = $PSScriptRoot
# pasta do plugin dentro do zip: acompanha o nome usado na instalacao
$pasta = 'cub-sc-updater'
$saida = Join-Path $root 'cub_sc_updater.zip'

# caminho dentro do zip (sempre com barra normal) - o arquivo de origem tem o mesmo caminho
$arquivos = @(
    'cub-sc-updater.php',
    'uninstall.php',
    'readme.txt',
    'LICENSE',
    'assets/cub-sc-admin.js',
    'assets/cub-sc.css',
    'languages/README.md'
)

# PHP local (opcional): confere a sintaxe e roda os testes antes de empacotar
$php = 'C:\Users\user\tools\php\php.exe'
if (-not (Test-Path $php)) {
    $php = (Get-Command php -ErrorAction SilentlyContinue | Select-Object -First 1).Source
}

if ($php) {
    foreach ($arquivo in 'cub-sc-updater.php', 'uninstall.php') {
        & $php -l (Join-Path $root $arquivo) | Out-Null
        if ($LASTEXITCODE -ne 0) { throw "Erro de sintaxe em $arquivo" }
    }
    & $php (Join-Path $root 'tests\test-cub-sc.php') | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "Os testes falharam - corrija antes de empacotar." }
} else {
    Write-Warning "PHP nao encontrado: pacote gerado sem checar sintaxe nem rodar os testes."
}

function New-PluginZip {
    param(
        [string]$Destino,
        [string]$Prefixo,
        [string[]]$Arquivos,
        [string]$Raiz
    )

    Add-Type -AssemblyName System.IO.Compression

    if (Test-Path $Destino) { Remove-Item $Destino -Force }

    $stream = [System.IO.File]::Open($Destino, [System.IO.FileMode]::CreateNew)
    try {
        $zip = New-Object System.IO.Compression.ZipArchive($stream, [System.IO.Compression.ZipArchiveMode]::Create)
        try {
            foreach ($relativo in $Arquivos) {
                $origem = Join-Path $Raiz ($relativo -replace '/', '\')
                if (-not (Test-Path $origem)) { throw "Arquivo obrigatorio ausente: $relativo" }

                $bytes = [System.IO.File]::ReadAllBytes($origem)
                # BOM no inicio de um PHP quebra o site (manda bytes antes dos cabecalhos HTTP)
                if ($bytes.Length -ge 3 -and $bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF) {
                    throw "BOM encontrado em $relativo - grave o arquivo em UTF-8 sem BOM."
                }

                $entrada = $zip.CreateEntry($Prefixo + $relativo, [System.IO.Compression.CompressionLevel]::Optimal)
                $saida = $entrada.Open()
                try {
                    $saida.Write($bytes, 0, $bytes.Length)
                } finally {
                    $saida.Dispose()
                }
            }
        } finally {
            $zip.Dispose()
        }
    } finally {
        $stream.Dispose()
    }
}

function Test-PluginZip {
    param(
        [string]$Caminho,
        [string]$Prefixo,
        [string[]]$Arquivos
    )

    Add-Type -AssemblyName System.IO.Compression.FileSystem

    $zip = [System.IO.Compression.ZipFile]::OpenRead($Caminho)
    try {
        $nomes = @($zip.Entries | ForEach-Object { $_.FullName })
    } finally {
        $zip.Dispose()
    }

    foreach ($nome in $nomes) {
        # barra invertida faz o WordPress ler tudo como um unico arquivo e falhar
        if ($nome -like '*\*') { throw "Entrada com barra invertida (o WordPress recusa): $nome" }
        if ($nome.StartsWith('/')) { throw "Entrada com barra inicial: $nome" }
    }

    $esperados = @($Arquivos | ForEach-Object { $Prefixo + $_ })
    if ($nomes.Count -ne $esperados.Count) {
        throw "$(Split-Path -Leaf $Caminho) tem $($nomes.Count) entradas; esperado $($esperados.Count)."
    }
    foreach ($esperado in $esperados) {
        if ($nomes -notcontains $esperado) { throw "Entrada ausente em $(Split-Path -Leaf $Caminho): $esperado" }
    }

    Write-Host ("{0} ({1:N0} bytes)" -f (Split-Path -Leaf $Caminho), (Get-Item $Caminho).Length)
    foreach ($nome in $nomes) { Write-Host ("  " + $nome) }
}

Write-Host ""
New-PluginZip -Destino $saida -Prefixo "$pasta/" -Arquivos $arquivos -Raiz $root
Test-PluginZip -Caminho $saida -Prefixo "$pasta/" -Arquivos $arquivos
