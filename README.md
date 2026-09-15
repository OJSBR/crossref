# Crossref DOI plugin for OMP (Open Monograph Press)

[![OMP](https://img.shields.io/badge/OMP-3.5-brightgreen)](https://pkp.sfu.ca/omp/)
[![Version](https://img.shields.io/badge/version-1.0.0.6-blue)](version.xml)
[![License](https://img.shields.io/badge/license-GPL--3.0-lightgrey)](LICENSE)

**⬇️ Install package:** [OMP 3.5](https://github.com/OJSBR/crossref/releases/download/1.0.0.6/crossref-1.0.0.6.tar.gz) · [OMP 3.4](https://github.com/OJSBR/crossref/releases/download/1.0.0.4-omp3.4/crossref-1.0.0.4-omp3.4.tar.gz) — or browse all [Releases](../../releases).

Registers monograph and chapter DOIs with [Crossref](https://www.crossref.org/) and exports
the corresponding Crossref *book deposit* XML (schema 5.3.1).

## The problem

OMP 3.4/3.5 ships the core DOI framework but — unlike OJS — does **not** ship a Crossref
registration-agency plugin, so a press can assign DOIs and has no way to deposit them.

## What it does

This plugin fills that gap. It plugs into the native DOI
framework (`IDoiRegistrationAgency`), so DOIs are assigned and managed from
**Settings → Distribution → DOIs** and deposited from the **DOIs** management page, exactly
like in OJS.

> **Based on PKP's OJS Crossref plugin** by **Bozana Bokan, Juan Pablo Alperin and James
> MacGregor** (© Simon Fraser University / John Willinsky, MIT License), adapted to the OMP DOI
> framework and maintained by [OJSBR](https://ojsbr.com). See the
> [Credits & authorship](#credits--authorship) section below.

## Compatibility & branches

| OMP version | Branch | Plugin release |
|-------------|--------|----------------|
| OMP 3.5.x   | [`stable-3_5_0`](../../tree/stable-3_5_0) *(default)* | 1.0.0.6 |
| OMP 3.4.x   | [`stable-3_4_0`](../../tree/stable-3_4_0) | 1.0.0.4-omp3.4 |

Tested on OMP 3.5.0.5. The DOI registration-agency framework is shared by pkp-lib and has
the same contract in OMP 3.4 and 3.5, so both branches share the same implementation.

> **Upgrade from 1.0.0.5.** A contributor name made only of blanks in the deposit locale produced
> an empty `<surname>` and Crossref refused the whole deposit; long names could be cut in the
> middle of a character; every batch `timestamp` ended in `000`, so two deposits of the same DOI
> within a second carried the same record version; and five labels and messages borrowed from
> OJS showed as `##key##` in OMP (the password field, the missing-requirements notice and the
> deposit results). All fixed in 1.0.0.6.

## Requirements

- OMP **3.5.x**
- A Crossref member account (DOI prefix) and deposit credentials (username/password)

## What gets a DOI

- **Monograph** (the publication) → Crossref `<book book_type="monograph">`; for an
  **edited volume** the book is deposited as `<book book_type="edited_book">` and only the
  contributors flagged as *volume editor* are listed at book level, with
  `contributor_role="editor"` (chapter authors stay in their chapters)
- **Chapter** → Crossref `<content_item component_type="chapter">`

## Installation

1. Install via **Settings → Website → Plugins → Upload A New Plugin**, uploading the
   `crossref.tar.gz` package; or extract it into `plugins/generic/` so you get
   `plugins/generic/crossref/`.
2. Enable **Crossref Manager Plugin** under the *Generic* plugins list.

## Configuration

1. Go to **Settings → Distribution → DOIs**. Enable DOIs, set your **DOI prefix** and choose
   which content types get DOIs (Monographs and/or Chapters).
2. Select **Crossref** as the *Registration Agency* and fill in depositor name/email and
   your Crossref username/password. Keep **Test mode** on to validate against
   `test.crossref.org`; turn it off for production deposits to `doi.crossref.org`.
3. Make sure a **Publisher** is set under **Settings → Press** (Crossref requires it).

## Depositing

From the **DOIs** management page, assign DOIs to published monographs/chapters and use
**Deposit** (or **Export** to download the XML). Deposit status and the Crossref response
are shown per item.

## Notes

- Deposit resource URLs point at the OMP catalog: `.../catalog/book/{id}` for monographs and
  `.../catalog/book/{id}/chapter/{chapterId}` for chapters.
- ISBNs are taken from the monograph's publication formats (ONIX ISBN-13/ISBN-10); a
  `<noisbn reason="monograph"/>` is emitted when none are present.
- Metadata is exported in the publication's own locale.
- **ORCID iDs:** the Crossref schema only accepts `https://orcid.org/…`. An iD that does not
  match it is left out of the deposit instead of failing the whole export. ORCID **Sandbox**
  iDs (`https://sandbox.orcid.org/…`, stored when the ORCID integration runs against the
  sandbox API) are rewritten to the production host while the plugin is in **test mode**, so
  the Sandbox → OMP → Crossref workflow can be tested, and are dropped in production, so a
  sandbox iD is never deposited against a live DOI. The `authenticated` attribute follows
  whether the contributor's ORCID was actually verified.
- **Names:** blanks around a name are removed; a contributor with no name in the deposit locale
  takes the first locale that has one; a given name alone becomes the surname; names are cut to
  Crossref's 60 characters without breaking a character.

## Tests

- **PHP suite** (`tests/`, 20 tests): the plugin classes against the installed PKP, the
  contributors node (blank names, fallback locale, UTF-8 cut, ORCID in production and test
  mode), chapter pages, the batch timestamp and the 38 translations. Run either way from the OMP
  root:

  ```bash
  php plugins/generic/crossref/tests/run.php
  lib/pkp/lib/vendor/bin/phpunit --configuration lib/pkp/tests/phpunit.xml --no-coverage "$PWD/plugins/generic/crossref/tests"
  ```

- Verified on OMP 3.5.0.5: the XML of three published books with 4 and 5 chapters (DOIs set in
  memory, nothing deposited) validates against the Crossref schema, also as an edited volume
  (`edited_book` with the volume editor at book level) and with a surname made of a blank, which
  failed validation before 1.0.0.6. The settings form shows no untranslated label.

## Credits & authorship

- **Original work:** PKP's **OJS Crossref export/registration plugin** by Bozana Bokan, Juan Pablo
  Alperin and James MacGregor (© Simon Fraser University / John Willinsky,
  <https://github.com/pkp/ojs>), distributed under the MIT License, whose notice is kept in
  [`docs/LICENSE-PKP-MIT`](docs/LICENSE-PKP-MIT).
- **Adaptation to the OMP DOI framework:** maintained by [OJSBR](https://ojsbr.com) and
  distributed under the **GNU GPL v3**.

## Contributing

Issues and pull requests are welcome. Please target the branch matching the OMP version you
are working against. See [`CONTRIBUTING.md`](CONTRIBUTING.md).

## License

Distributed under the **GNU GPL v3**. See [`LICENSE`](LICENSE) and `docs/COPYING`; the MIT notice
of the original PKP plugin is in `docs/LICENSE-PKP-MIT`.

---

## 🇧🇷 Português

Plugin **Crossref** para o **Open Monograph Press (OMP)**: registra DOIs de livros
(monografias) e capítulos no [Crossref](https://www.crossref.org/) e exporta o XML de
depósito de livro (schema 5.3.1).

### O problema

O OMP 3.4/3.5 traz o framework de DOI, mas — diferente do OJS — **não** inclui um plugin de
agência Crossref: a editora atribui DOIs e não tem como depositá-los. Este plugin preenche essa
lacuna, integrando-se ao framework nativo (`IDoiRegistrationAgency`).

> **Baseado no plugin Crossref do OJS (PKP)** de **Bozana Bokan, Juan Pablo Alperin e James
> MacGregor** (© Simon Fraser University / John Willinsky, licença MIT), adaptado ao framework de
> DOI do OMP e mantido pela [OJSBR](https://ojsbr.com). Veja a seção
> [Créditos e autoria](#créditos-e-autoria) abaixo.

> **Atualização a partir da 1.0.0.5.** Nome de contribuidor feito só de espaços no idioma do
> depósito gerava `<surname>` vazio e o Crossref recusava o depósito inteiro; nomes longos podiam
> ser cortados no meio de um caractere; o `timestamp` do lote sempre terminava em `000`, e dois
> depósitos do mesmo DOI no mesmo segundo levavam a mesma versão; e cinco textos emprestados do
> OJS apareciam como `##chave##` no OMP (campo de senha, aviso de requisitos e resultados do
> depósito). Tudo corrigido na 1.0.0.6.

### Compatibilidade e branches

| Versão do OMP | Branch | Release do plugin |
|---------------|--------|-------------------|
| OMP 3.5.x     | [`stable-3_5_0`](../../tree/stable-3_5_0) *(padrão)* | 1.0.0.6 |
| OMP 3.4.x     | [`stable-3_4_0`](../../tree/stable-3_4_0) | 1.0.0.4-omp3.4 |

### O que recebe DOI

- **Monografia** (a publicação) → `<book book_type="monograph">`; em **obra organizada**
  (volume editado) o livro sai como `<book book_type="edited_book">` e só os contribuidores
  marcados como *editor de volume* entram no nível do livro, com `contributor_role="editor"`
  (os autores ficam nos seus capítulos)
- **Capítulo** → `<content_item component_type="chapter">`

### Instalação e configuração

1. Instale em **Configurações → Website → Plugins → Enviar um novo plugin** (pacote
   `crossref.tar.gz`) ou extraia em `plugins/generic/` (ficando `plugins/generic/crossref/`)
   e ative o **Crossref Manager Plugin**.
2. Em **Configurações → Distribuição → DOIs**, habilite DOIs, defina o **prefixo**, escolha
   os tipos de conteúdo, selecione **Crossref** como agência e preencha depositante e
   usuário/senha do Crossref. Mantenha o **modo de teste** para validar em
   `test.crossref.org`; desative para produção em `doi.crossref.org`.
3. Garanta um **Editor (Publisher)** em **Configurações → Editora**, exigido pelo Crossref.

### Notas

- **ORCID:** o schema do Crossref só aceita `https://orcid.org/…`. Um iD fora desse padrão é
  omitido do depósito em vez de derrubar o export inteiro. iDs do **Sandbox** do ORCID
  (`https://sandbox.orcid.org/…`, gravados quando a integração ORCID aponta para a API de
  sandbox) são reescritos para o host de produção enquanto o plugin está em **modo de teste**,
  para permitir testar o fluxo Sandbox → OMP → Crossref, e são omitidos em produção, para que
  um iD de sandbox nunca seja depositado contra um DOI real. O atributo `authenticated`
  acompanha se o ORCID do contribuidor foi de fato autenticado.
- **Nomes:** espaços em volta do nome são removidos; contribuidor sem nome no idioma do depósito
  usa o primeiro idioma que tiver; só prenome vira sobrenome; nomes são cortados nos 60 caracteres
  do Crossref sem quebrar caractere.

### Testes

Suíte PHP em `tests/` (20 testes, pelo `tests/run.php` ou pelo PHPUnit do PKP): classes do plugin
contra o PKP instalado, nó de contribuidores (nomes em branco, idioma de recuo, corte UTF-8, ORCID
em produção e em teste), páginas do capítulo, `timestamp` do lote e as 38 traduções. Verificado no
OMP 3.5.0.5: o XML de três livros publicados com 4 e 5 capítulos (DOIs só em memória, nada
depositado) valida contra o schema do Crossref, também como obra organizada e com sobrenome feito
de um espaço, que reprovava antes da 1.0.0.6. O formulário de configurações não mostra texto sem
tradução.

### Créditos e autoria

- **Trabalho original:** plugin **Crossref do OJS** (PKP) de Bozana Bokan, Juan Pablo Alperin e
  James MacGregor (© Simon Fraser University / John Willinsky, <https://github.com/pkp/ojs>),
  distribuído sob a licença MIT, cujo aviso está em [`docs/LICENSE-PKP-MIT`](docs/LICENSE-PKP-MIT).
- **Adaptação ao framework de DOI do OMP:** mantida pela [OJSBR](https://ojsbr.com) e distribuída
  sob a **GNU GPL v3**.

### Licença

Distribuído sob a **GNU GPL v3**. Veja [`LICENSE`](LICENSE) e `docs/COPYING`; o aviso MIT do
plugin original da PKP está em `docs/LICENSE-PKP-MIT`.
