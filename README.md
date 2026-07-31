# NFSe Padrão Nacional — Quantum Tecnology

Pacote PHP para emissão, consulta e geração de DANFSe da **NFS-e Padrão Nacional** ([nfse.gov.br](https://www.nfse.gov.br/)), construído sobre os componentes [NFePHP](https://github.com/nfephp-org).

Este é um **fork mantido pela [Quantum Tecnology](https://github.com/Quantum-Tecnology)** do excelente trabalho da equipe **Hadder** (ver [Créditos](#créditos)). Partindo daquela base, evoluímos o pacote com foco em **robustez em produção, suporte a prefeituras com emissor próprio e geração de DANFSe local** — recursos detalhados abaixo.

> **Em uso em produção**, porém em evolução contínua. Contribuições são bem-vindas via PR.

## ✨ Melhorias desta versão (Quantum)

Além de tudo que já existia no pacote original, esta versão adiciona:

### 🏛️ Destaque de IBS/CBS (Reforma Tributária — EC 132/2023)
O grupo `<IBSCBS>` do DPS é gerado a partir de `$std->infDPS->IBSCBS`. É **opcional**: sem ele no payload, o XML sai exatamente como antes.

No DPS você **apenas declara a situação tributária** — `CST` e `cClassTrib`. As alíquotas e os valores de IBS/CBS são calculados pelo Ambiente de Dados Nacional e voltam no `<infNFSe>` da NFS-e autorizada; **não envie valores**.

```php
$std->infDPS->IBSCBS = (object) [
    'finNFSe'  => '0',        // hoje o XSD só aceita 0
    'indFinal' => '0',        // 0 = não é consumidor final
    'cIndOp'   => '030101',   // 6 dígitos (tabela oficial)
    'indDest'  => '0',        // 0 = tomador é o destinatário
    'valores'  => (object) [
        'trib' => (object) [
            'gIBSCBS' => (object) [
                'CST'        => '000',     // 3 dígitos
                'cClassTrib' => '000001',  // 6 dígitos
            ],
        ],
    ],
];
```

Grupos opcionais suportados: `tpOper`, `gRefNFSe` (até 99 chaves), `tpEnteGov`, `dest` (com endereço nacional ou exterior), `imovel` (`cCIB` ou endereço), `gReeRepRes` (até 1000 documentos), `cCredPres`, `gTribRegular` e `gDif`.

> ⚠️ **Os códigos são strings, nunca inteiros.** `CST`, `cClassTrib`, `cIndOp` e `cCredPres` têm zero à esquerda significativo — um cast para `int` transforma `'000001'` em `1` e a nota é rejeitada.

### 🧭 Roteamento inteligente por município (emissão × cancelamento × consulta)
Municípios com **emissor próprio** (ex.: **Americana-SP**) aceitam o leiaute nacional, mas num endpoint específico da prefeitura — enquanto as **consultas** continuam no Ambiente Nacional (ADN/SEFIN).

O pacote separa essas responsabilidades automaticamente:
- **Emissão / cancelamento** → usam a URL configurada em `storage/prefeituras.json` (override da prefeitura).
- **Consultas por chave** → usam sempre o Ambiente Nacional central.

Isso elimina os erros `404` que ocorriam quando a consulta era enviada, por engano, ao endpoint de emissão da prefeitura.

> ⚠️ **Emissão e cancelamento costumam ter endpoints DIFERENTES.** Em Americana-SP, por exemplo, a emissão vai para `.../api/adn/dps/recepcao` e o cancelamento (evento) para `.../api/adn/dps/evento`. Por isso o `storage/prefeituras.json` separa `urls` (base) de `operations` (caminho por operação): a URL final é `base + "/" + operação`. Se `cancelar_nfse` ficar vazio, o evento de cancelamento é postado na base de emissão e a prefeitura rejeita com **"DPS inválido ou não informado"**. Veja [Configuração da prefeitura](#configuração-da-prefeitura).

### 🧾 DANFSe local (sem depender do ADN)
Geração do PDF da DANFSe **diretamente a partir do XML**, sem precisar baixar o PDF oficial do Ambiente Nacional:
- **`Danfse`** — DANFSe completa a partir do XML autorizado.
- **`DanfseSimples`** — renderização tolerante (lê só a estrutura, não exige assinatura), ideal para **rascunhos, prévias e notas recebidas via DFe**.

Útil quando o ADN está indisponível, para pré-visualização antes da transmissão, ou para notas importadas que não têm PDF oficial salvo.

### 🛡️ Validação que falha cedo e com mensagem clara
- **`cTribNac` obrigatório (6 dígitos)** é validado na montagem do DPS. Antes, um valor ausente gerava uma tag `<cTribNac/>` vazia e a SEFAZ rejeitava com **`L2103`** (XML fora do schema) — erro difícil de diagnosticar. Agora você recebe uma `InvalidArgumentException` clara, **antes** de assinar e transmitir.
- **`getOperation()`** e a resolução de URL lançam exceção em chaves/origens desconhecidas, em vez de falhar silenciosamente.

### 🏷️ Namespace próprio
O namespace passou a ser **`QuantumTecnology\NfseNacional`**, alinhado aos demais pacotes da Quantum.

> ⚠️ **Breaking change** ao migrar de versões anteriores: troque os `use Hadder\NfseNacional\...` por `use QuantumTecnology\NfseNacional\...`.

### 🔁 Eventos: cancelamento e cancelamento por substituição
O `renderEvento()` **nunca gerou XML válido** em versões anteriores: faltava o elemento obrigatório `<nPedRegEvento>` e o atributo `Id` tinha 59 caracteres onde o schema exige 62. Na prática, **nenhum cancelamento era aceito** pelo Ambiente Nacional. Corrigido.

Além do `e101101` (cancelamento), agora também é gerado o corpo do **`e105102`** (cancelamento por substituição), que antes tinha o código mapeado mas produzia um `infPedReg` sem o grupo do evento.

```php
$std->infPedReg->e101101 = (object) [
    'cMotivo' => '1',                              // TSCodJustCanc: 1, 2 ou 9
    'xMotivo' => 'Erro na emissao da nota fiscal',
];

// Substituição usa outra enumeração — com zero à esquerda:
$std->infPedReg->e105102 = (object) [
    'cMotivo'      => '01',                        // TSCodJustSubst: 01..05, 99
    'chSubstituta' => '...',                       // chave da NFS-e substituta
];
```

O `xDesc` **não precisa ser informado** — é enumeração de valor fixo no schema e o pacote o deriva do próprio evento (a grafia do 105102, por exemplo, não leva cedilha). O `nPedRegEvento` assume `1` por padrão; informe-o em `$std->infPedReg->nPedRegEvento` nos eventos que podem se repetir.

### 🛡️ Compatibilidade de versão do PHP
`generateId()` e o cliente HTTP usavam `mb_str_pad()` e `mb_trim()`, **funções exclusivas do PHP 8.4**, enquanto o pacote declara `^8.1`. Em PHP 8.1–8.3 a instalação passava sem aviso e o pacote quebrava na primeira emissão. Trocadas por `str_pad()`/`trim()`.

> A regra `mb_str_functions` do `.php-cs-fixer.php` foi **desligada de propósito**: ela reconvertia essas chamadas automaticamente, reintroduzindo o bug a cada formatação.

### 🧹 Limpeza e correções
- **`cNBS` é obrigatório** no schema e era emitido sob `isset()`, como se fosse opcional — a nota saía sem ele e a SEFAZ rejeitava com `L2103`.
- Remoção de código morto (incluindo um elemento `<DPS>` órfão criado dentro do gerador de eventos) e de um `dump()` que vazava no output em produção.
- Correção de recursão sem `return` na geração de nomes de arquivos temporários de certificado.

## Instalação

Este pacote é distribuído via [Composer](https://getcomposer.org/):

```bash
composer require quantumtecnology/nfse-nacional
```

### Requisitos

- PHP 8.1+
- ext-dom, ext-curl, ext-zlib, ext-openssl, ext-mbstring

### Testes

```bash
composer install
composer test          # ou: vendor/bin/phpunit
```

A suíte valida o XML gerado contra os **XSDs oficiais v1.01** (`storage/schemes/`) e inclui um teste de paridade com uma NFS-e real autorizada pelo Ambiente Nacional (fixture anonimizada em `tests/Fixtures/`).

## Serviços implementados

| Método | Descrição |
|---|---|
| `enviaDps` | Emite a NFS-e (envia o DPS) |
| `cancelaNfse` | Cancela uma NFS-e autorizada |
| `consultarNfseChave` | Consulta a NFS-e pela chave (XML) |
| `consultarDpsChave` | Consulta o DPS pela chave |
| `consultarNfseEventos` | Consulta eventos de uma NFS-e |
| `consultarDanfse` | Baixa a DANFSe (PDF oficial) do ADN |
| `Danfse` / `DanfseSimples` | Gera a DANFSe **localmente** a partir do XML |

Exemplos de uso estão na pasta [`exemples/`](exemples/).

## ⚠️ Avisos importantes

### Configuração da prefeitura

A variável `prefeitura` aceita atualmente dois formatos:

- Um identificador textual, por exemplo: `americana-sp`
- O **código IBGE** do município (ex.: `3501608`)

⚠️ Ambos são aceitos por compatibilidade, mas **o padrão futuro será exclusivamente o código IBGE**. Recomenda-se já adotá-lo. As URLs e operações por município ficam em [`storage/prefeituras.json`](storage/prefeituras.json).

#### Estrutura do `prefeituras.json`

Cada município tem `urls` (a **base** por ambiente) e `operations` (o **caminho** de cada operação). A URL final é montada como `base + "/" + operação` (a operação vazia `""` usa a base direta):

```jsonc
{
    "3501608": {                         // código IBGE (chave recomendada)
        "urls": {
            "sefin_producao":    "https://nfse.americana.sp.gov.br/api/adn/dps",
            "sefin_homologacao": "https://americanahomologacao.nfe.com.br/api/adn/dps"
        },
        "operations": {
            "emitir_nfse":   "recepcao",  // => .../api/adn/dps/recepcao
            "cancelar_nfse": "evento"     // => .../api/adn/dps/evento
        }
    }
}
```

> ⚠️ **Não deixe `cancelar_nfse` vazio quando o cancelamento usa um caminho diferente da emissão.** Se a base apontar para `.../recepcao` e `cancelar_nfse` for `""`, o evento de cancelamento será postado em `.../recepcao` e a prefeitura responderá **"DPS inválido ou não informado"**. Prefira manter a base no nível comum (`.../api/adn/dps`) e especificar cada operação. Consulte o manual da prefeitura para os endpoints corretos (emissão, evento/cancelamento, consulta por chave, download de XML/DANFSe).

### `consultarNfseChave()` e encoding

O XML, após o `gzdecode`, vem em **ISO-8859-1**. Por padrão o método mantém ISO via `mb_convert_encoding`. Caso tenha problemas, passe `false` no segundo parâmetro para receber o XML cru:

```php
// Retorna ISO-8859-1 (padrão)
$tools->consultarNfseChave('CHAVE_NFSE');

// Retorna o XML cru, sem mb_convert_encoding
$tools->consultarNfseChave('CHAVE_NFSE', false);
```

## FAQ — E999 (erro não catalogado)

Esse erro se refere a uma falha **não catalogada pela própria Receita**, incluindo erros de servidor (500) e problemas aleatórios. No ambiente de **homologação** costuma aparecer sem motivo aparente, enquanto em **produção** a nota normalmente é emitida sem problemas.

Causa mais comum relatada:

- CPF/CNPJ do **prestador** não existente / não cadastrado / não habilitado na NFS-e Nacional ou na prefeitura.

## Créditos

Este fork é mantido pela **[Quantum Tecnology](https://github.com/Quantum-Tecnology)**, mas **não nasceu do zero**. Ele se apoia inteiramente no trabalho de quem veio antes.

Nosso agradecimento à **equipe Hadder** e em especial ao **Fernando Friedrich** ([Rainzart/nfse-nacional](https://github.com/Rainzart/nfse-nacional)) por criar, manter e disponibilizar este pacote como Open Source. As melhorias desta versão só foram possíveis porque havia uma base sólida e bem estruturada para construir em cima.

A seguir, o agradecimento original do autor — que mantemos na íntegra:

> ### (por Fernando Friedrich)
>
> Este pacote **não caiu do céu**, **não apareceu por geração espontânea** e muito menos foi escrito do zero em um surto de genialidade de minha parte.
>
> Ele foi **copiado, clonado, analisado, desmontado, reaproveitado, adaptado e por fim ajustado por mim**, tendo como base pacotes de emissão de **NFSe** que eram disponibilizados como **Open Source** pelo Sr. **[Roberto L. Machado](https://github.com/robmachado)** e que, atualmente, não se encontram mais disponíveis publicamente.
>
> Sim, **variáveis, métodos, classes, estruturas e ideias de arquitetura** foram utilizadas como referência (copiadas) — algumas foram alteradas, outras melhoradas, outras apenas sobreviveram ao tempo — sempre tendo como principal base o projeto **[NFePHP](https://github.com/robmachado/sped-nfse)**.
>
> Na época da criação deste repositório, o cenário era simples: eu precisava **emitir notas fiscais para meus clientes**. Não existia nenhuma alternativa Open Source ativa e funcional em PHP, e depender de **APIs pagas** definitivamente não era uma opção para mim.
>
> Diante disso, fica aqui meu agradecimento **mais do que merecido** ao **Roberto**, por criar, manter e disponibilizar gratuitamente projetos como o **NFePHP**.
>
> Sem esse trabalho prévio, este repositório **muito provavelmente não existiria**.

E, por fim, obrigado a **todas as pessoas que contribuem** com este projeto — enviando PRs, sugerindo melhorias, corrigindo bugs ou apontando problemas. A lista de contribuidores do projeto original pode ser vista em: https://github.com/Rainzart/nfse-nacional/graphs/contributors

## Licença

MIT.
