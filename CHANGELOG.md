# Changelog

Todas as mudanças relevantes desta biblioteca são documentadas aqui.

O formato segue [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e o versionamento segue [SemVer](https://semver.org/lang/pt-BR/).

**Antes de atualizar em produção, leia a seção da versão de destino.** Mudanças que exigem ação manual aparecem sob **⚠️ Requer atenção**.

> **Nota sobre o histórico.** Este arquivo começa na `3.3.0`. As versões anteriores (24 tags, de `1.0.0` a `3.2.4`) não tinham changelog escrito e não serão reconstruídas a partir do log do Git.

## [3.3.1] — 2026-09-01

### Alterado

- **O rodapé do DANFSe passa a creditar a Quantum.** Antes assinava apenas `Powered by NFePHP`, que é o pacote de onde vem o motor de PDF (`sped-da`) — não quem gera o documento. Agora consta `Powered by Quantum Tecnologia - NFS-e Nacional (sobre NFePHP)`. A atribuição ao NFePHP **permanece de propósito**: o FPDF usado vem mesmo do `sped-da` (LGPL/MIT) e retirar o crédito seria incorreto, além de contrariar a licença.

## [3.3.0] — 2026-09-01

DANFSe reescrito para bater com o documento oficial e passar a exibir o quadro **IBS/CBS** da Reforma Tributária.

### Corrigido

Esta seção não trata de estética: o DANFSe **imprimia informação fiscal errada**. Todos os casos abaixo foram conferidos contra o XML de notas reais autorizadas, versionadas em `tests/Fixtures/`.

- **ISSQN e alíquota saíam ZERADOS.** O parser lia os valores de `DPS/infDPS/valores` — o documento que o contribuinte **envia**, onde `tribMun` apenas declara a intenção (`tribISSQN`, `tpRetISSQN`) e não existe `vBC`, `pAliq` nem `vISSQN`. Quem **calcula** base, alíquota e imposto é o Fisco, em `infNFSe/valores`. Numa nota de Uberlândia o documento mostrava `BC R$ 441,60 | 0,00 % | R$ 0,00` onde o correto era `BC R$ 441,60 | 3,00 % | R$ 13,25`. O DPS continua sendo usado como *fallback* quando o XML não traz o cálculo.

- **Tributação federal também zerada, por nomes de chave inexistentes.** O código buscava `vPIS`, `vCOFINS`, `vINSS`, `vIR` e `vCSLL`. No schema v1.01 os nomes são `piscofins/vPis`, `piscofins/vCofins`, `vRetCP`, `vRetIRRF` e `vRetCSLL` — nenhum dos antigos existe em lugar nenhum do XML, então o bloco inteiro imprimia `R$ 0,00`.

- **Regime do Simples Nacional classificado errado.** `opSimpNac` era traduzido pela tabela de `regEspTrib`, que tem domínio completamente diferente. Uma ME/EPP (`opSimpNac=3`) aparecia como *"Sociedade de Profissionais"*. Agora são três de-para distintos — `opSimpNac`, `regApTribSN` e `regEspTrib` — e o documento mostra os **dois** campos do Simples, como o oficial.

- **A retenção nunca aparecia.** O ternário era `$x == 1 ? 'Nao Retido' : 'Nao Retido'` — ramos idênticos, tanto no ISSQN quanto no PIS/COFINS. Uma nota com ISS retido saía como não retido. Passa a ler `tpRetISSQN` / `tpRetPisCofins` (1 = Retido).

- **Município em branco.** O parser lia `xMun`, que **não existe** em `enderNac`/`endNac` — só `cMun`, o código IBGE. Ver *Adicionado* sobre a tabela de municípios.

- **`*** DOCUMENTO PROVISORIO ***` carimbado em vermelho de forma incondicional**, inclusive em nota autorizada (`cStat 100`). A linha existia apenas em `DanfseSimples`; em `Danfse` estava comentada — sintoma direto de as duas classes serem cópias que divergiram em silêncio. Em compensação, `DanfseSimples` **não** desenhava as tarjas reais de homologação/cancelamento, que só existiam na outra.

- **Códigos de tributação sem descrição.** `xTribNac` e `xTribMun` vêm no XML autorizado e eram ignorados: o PDF imprimia só `080201` e deixava o municipal vazio. Agora sai `038 - 8599603 - Treinamento em informática`, como no oficial.

- **Campos fixos no lugar de dados reais.** *País Resultado* era sempre `Brasil` e *Regime Especial* sempre `Nenhum`, apesar de `cPaisResult` e `regEspTrib` já serem parseados.

- **`Str::lower($x) ?? ''`** tinha o `??` fora do parêntese e emitia notice quando o e-mail faltava.

### Adicionado

- **Quadro IBS/CBS** (Reforma Tributária, EC 132/2023) com CST, classificação tributária, município de incidência, base, as três alíquotas e os valores apurados. O bloco é **sempre exibido** — zerado quando a nota não tem o grupo, porque a ausência do tributo é informação fiscal tanto quanto a presença. Obrigatório a partir de **01/08/2026** (`Dps::IBSCBS_OBRIGATORIO_EM`) e **01/01/2027** para o Simples Nacional.

- **Acentuação correta nos rótulos** (`TRIBUTAÇÃO MUNICIPAL`, `Município`, `Código de Tributação`). As fontes core do FPDF são ISO-8859-1 e não cobrem acentuação — por isso os rótulos tinham sido escritos sem acento. O pacote passa a distribuir **DejaVu Sans Condensed** (licença livre, em `storage/fonts/`), na variante Condensed por ser a mais próxima da usada no documento oficial.

- **Tabela IBGE com 5.571 municípios** (`storage/municipios.php`, da API oficial do IBGE, carregada sob demanda). Para o emitente o XML traz `xLocEmi`; para o **tomador não existe nome nenhum** no documento, apenas o código — e o oficial imprime `Campinas - SP`.

- **Seções que faltavam**: intermediário do serviço (com a faixa *"NÃO IDENTIFICADO NA NFS-e"* quando ausente, como no oficial) e totais aproximados dos tributos (Lei 12.741/2012).

- **`setOrgaoEmissor()`** para o bloco de Prefeitura/Secretaria/telefone/e-mail no cabeçalho. Esses dados **não existem no XML** — o portal os obtém de cadastro próprio por município. Sem eles o espaço fica livre, em vez de o documento exibir dado inventado.

- **Primeira cobertura de testes do DANFSe**: 73 testes, 233 asserções. Antes eram **zero** para ~2.600 linhas.

### Alterado

- **`Danfse` e `DanfseSimples` agora compartilham `Danfse\AbstractDanfse`.** Eram cópias de ~1.300 linhas que divergiram em silêncio (daí a tarja de provisório numa e não na outra); qualquer correção precisava ser feita duas vezes — e nem sempre era. As subclasses ficaram com o que de fato as diferencia: a marca do cabeçalho e o QR Code. **1.044 linhas a menos.**

- **`Danfse` funciona fora do Laravel.** Antes usava `Illuminate\Support\Str`, `public_path()` e a facade `LaravelQRCode` — **nenhum dos três declarado no `composer.json`** — e gravava um PNG por nota em `public/images/`, sem criar o diretório nem limpar depois. O QR passa a ser gerado em memória.

- Título `DANFSe V1.0` → `DANFSe v1.0`, e campo sem valor passa a exibir `-`, como no oficial.

### ⚠️ Requer atenção

- **Nova dependência: `chillerlan/php-qrcode ^6.0`.** Substitui a facade `LaravelQRCode`, que era usada sem estar declarada.

- **Quem ESTENDE as classes deve conferir.** A API pública não muda — `new Danfse($xml)` e `render($logo)` seguem iguais —, mas `Danfse` e `DanfseSimples` passaram a estender `Danfse\AbstractDanfse` em vez de `DaCommon` diretamente. As propriedades e métodos protegidos de uso comum (`$infNfse`, `$prestador`, `$tomador`, `$servico`, `statusNFSe()`, `adjustImage()`, `$logomarca`) foram preservados.

- **Contornos que viraram redundantes.** Quem mantinha um placeholder de logo para evitar o `$nImgW`/`$nImgH` indefinidos do `renderCabecalho()` pode removê-lo: a ausência de logo passou a ser tratada e o QR tem posição própria.

- **O peso do pacote subiu ~880 KB** (fontes + tabela IBGE). É o custo de acentuação correta e de resolver o município do tomador, que o XML não informa.

- **`.gitattributes` marca `*.z` como binário.** Sem isso a conversão de fim de linha corromperia as fontes no checkout — o PDF sairia sem texto, e o erro só apareceria para quem clonasse o repositório.

### Não implementado

- Leiaute **RTC IBS/CBS v1.04** (NT SE/CGNFS-e nº 009): `indZFMALC`, `indDoacao`/`gEstornoCred`, `gIBSCBSAjuste`, `gPagAntecipado`, `gPgtoVinc`, `bensMoveis`.
- **Imposto Seletivo (IS)** — não existe em nenhum ponto do pacote.
- Grupo `interm` na **geração** do DPS (segue como TODO em `Dps.php`); o DANFSe já sabe **ler** o grupo quando o XML o traz.
