# NFSe Padrão Nacional — Quantum Tecnology

Pacote PHP para emissão, consulta e geração de DANFSe da **NFS-e Padrão Nacional** ([nfse.gov.br](https://www.nfse.gov.br/)), construído sobre os componentes [NFePHP](https://github.com/nfephp-org).

Este é um **fork mantido pela [Quantum Tecnology](https://github.com/Quantum-Tecnology)** do excelente trabalho da equipe **Hadder** (ver [Créditos](#créditos)). Partindo daquela base, evoluímos o pacote com foco em **robustez em produção, suporte a prefeituras com emissor próprio e geração de DANFSe local** — recursos detalhados abaixo.

> **Em uso em produção**, porém em evolução contínua. Contribuições são bem-vindas via PR.

## ✨ Melhorias desta versão (Quantum)

Além de tudo que já existia no pacote original, esta versão adiciona:

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

### 🧹 Limpeza e correções
- Remoção de código morto e de um `dump()` que vazava no output em produção.
- Correção de recursão sem `return` na geração de nomes de arquivos temporários de certificado.

## Instalação

Este pacote é distribuído via [Composer](https://getcomposer.org/):

```bash
composer require quantumtecnology/nfse-nacional
```

### Requisitos

- PHP 8.2+
- ext-dom, ext-curl, ext-zlib, ext-openssl, ext-mbstring

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
