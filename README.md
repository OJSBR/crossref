# Crossref DOI plugin for OMP (OMP 3.4 branch)

[![OMP](https://img.shields.io/badge/OMP-3.4-brightgreen)](https://pkp.sfu.ca/omp/)
[![Version](https://img.shields.io/badge/version-1.0.0.2-blue)](version.xml)
[![License](https://img.shields.io/badge/license-GPL--3.0-lightgrey)](LICENSE)

**⬇️ Install package:** [OMP 3.5](https://github.com/OJSBR/crossref/releases/download/1.0.0.2-omp3.5/crossref-1.0.0.2-omp3.5.tar.gz) · [OMP 3.4](https://github.com/OJSBR/crossref/releases/download/1.0.0.2-omp3.4/crossref-1.0.0.2-omp3.4.tar.gz) — or browse all [Releases](../../releases).

> **This is the `stable-3_4_0` branch (OMP 3.4).** For OMP 3.5 use the
> [`stable-3_5_0`](../../tree/stable-3_5_0) branch.

Registers monograph and chapter DOIs with [Crossref](https://www.crossref.org/) and exports
the corresponding Crossref *book deposit* XML (schema 5.3.1).

OMP 3.4/3.5 ships the core DOI framework but — unlike OJS — does **not** ship a Crossref
registration-agency plugin. This plugin fills that gap. It plugs into the native DOI
framework (`IDoiRegistrationAgency`), so DOIs are assigned and managed from
**Settings → Distribution → DOIs** and deposited from the **DOIs** management page, exactly
like in OJS.

> **Developed and maintained by [OJSBR](https://ojsbr.com.br).** Based on the structure of
> PKP's OJS Crossref plugin, adapted to the OMP DOI framework. See the
> [Credits & authorship](#credits--authorship) section below.

## Compatibility & branches

| OMP version | Branch | Plugin release |
|-------------|--------|----------------|
| OMP 3.5.x   | [`stable-3_5_0`](../../tree/stable-3_5_0) *(default)* | 1.0.0.2 |
| OMP 3.4.x   | [`stable-3_4_0`](../../tree/stable-3_4_0) *(this branch)* | 1.0.0.2 |

The DOI registration-agency framework (`IDoiRegistrationAgency`, `Repo::doi()`) is shared by
pkp-lib and is present in OMP 3.4 and 3.5 with the same contract, so both branches share the
same implementation.

## Requirements

- OMP **3.4.x**
- A Crossref member account (DOI prefix) and deposit credentials (username/password)

## What gets a DOI

- **Monograph** (the publication) → Crossref `<book book_type="monograph">`
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

## Changelog

### 1.0.0.2 (2026-08-18) — OMP 3.4

- **Fix (fatal on export):** removed the `urlLocaleForPage: ''` named argument from the two
  `Dispatcher::url()` calls in `filter/MonographCrossrefXmlFilter.php` (monograph and chapter
  `<resource>` nodes). That parameter only exists in newer pkp-lib; on OMP 3.4 an unknown
  named argument is a fatal error, so **every** monograph/chapter DOI export threw
  `Unknown named parameter $urlLocaleForPage`. The generated `<resource>` URL is unchanged
  (`.../catalog/book/{id}`), because OMP does not add a locale segment to catalog paths.

## Credits & authorship

- **Developed and maintained by** [OJSBR](https://ojsbr.com.br) — original OMP plugin.
- **Based on:** the structure of PKP's **OJS Crossref export/registration plugin**
  (© Simon Fraser University / John Willinsky, <https://github.com/pkp/ojs>), adapted to the
  OMP DOI framework.
- Distributed under the **GNU GPL v3**, consistent with the PKP licensing.

## Contributing

Issues and pull requests are welcome. Please target the branch matching the OMP version you
are working against.

## License

Distributed under the **GNU GPL v3**. See [`LICENSE`](LICENSE) and `docs/COPYING`.

---

## 🇧🇷 Português

> **Esta é a branch `stable-3_4_0` (OMP 3.4).** Para OMP 3.5 use a branch
> [`stable-3_5_0`](../../tree/stable-3_5_0).

Plugin **Crossref** para o **Open Monograph Press (OMP)**: registra DOIs de livros
(monografias) e capítulos no [Crossref](https://www.crossref.org/) e exporta o XML de
depósito de livro (schema 5.3.1). O OMP 3.4/3.5 traz o framework de DOI, mas — diferente do
OJS — **não** inclui um plugin de agência Crossref; este plugin preenche essa lacuna,
integrando-se ao framework nativo (`IDoiRegistrationAgency`).

> **Desenvolvido e mantido pela [OJSBR](https://ojsbr.com.br).** Baseado na estrutura do
> plugin Crossref do OJS (PKP), adaptado ao framework de DOI do OMP. Veja a seção
> [Créditos e autoria](#créditos-e-autoria) abaixo.

### O que recebe DOI

- **Monografia** (a publicação) → `<book book_type="monograph">`
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

### Créditos e autoria

- **Desenvolvido e mantido pela** [OJSBR](https://ojsbr.com.br) — plugin autoral para OMP.
- **Baseado em:** a estrutura do plugin **Crossref do OJS** (© Simon Fraser University /
  John Willinsky, <https://github.com/pkp/ojs>), adaptada ao framework de DOI do OMP.
- Distribuído sob a **GNU GPL v3**, coerente com o licenciamento da PKP.

### Licença

Distribuído sob a **GNU GPL v3**. Veja [`LICENSE`](LICENSE) e `docs/COPYING`.
