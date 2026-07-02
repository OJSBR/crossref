# Crossref DOI plugin for OMP (Open Monograph Press)

[![OMP](https://img.shields.io/badge/OMP-3.5-brightgreen)](https://pkp.sfu.ca/omp/)
[![Version](https://img.shields.io/badge/version-1.0.0.0-blue)](version.xml)
[![License](https://img.shields.io/badge/license-GPL--3.0-lightgrey)](LICENSE)

Registers monograph and chapter DOIs with [Crossref](https://www.crossref.org/) and
exports the corresponding Crossref *book deposit* XML (schema 5.3.1).

OMP 3.4/3.5 ships the core DOI framework but — unlike OJS — does **not** ship a
Crossref registration-agency plugin. This plugin fills that gap. It plugs into the
native DOI framework (`IDoiRegistrationAgency`), so DOIs are assigned and managed
from **Settings → Distribution → DOIs** and deposited from the **DOIs** management
page, exactly like in OJS.

> Developed by **[OJSBR](https://ojsbr.com.br)**, based on the OJS Crossref plugin
> structure and adapted to the OMP DOI framework.

## Compatibility / branches

| OMP version | Branch | Plugin release |
|-------------|--------|----------------|
| OMP 3.5.x   | [`stable-3_5_0`](../../tree/stable-3_5_0) *(default)* | 1.0.0.0 |

Tested on OMP 3.5.0-4.

## Requirements

- OMP **3.5.x**
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

1. Go to **Settings → Distribution → DOIs**.
2. Enable DOIs, set your **DOI prefix**, and choose which content types get DOIs
   (Monographs and/or Chapters).
3. Select **Crossref** as the *Registration Agency* and fill in:
   - Depositor name and email
   - Crossref username and password
   - *Test mode* — leave **on** while validating against `test.crossref.org`; turn
     **off** for production deposits to `doi.crossref.org`.
4. Make sure a **Publisher** is set under **Settings → Press**, as Crossref requires
   a publisher name in book deposits.

## Depositing

From the **DOIs** management page, assign DOIs to published monographs/chapters and
use **Deposit** (or **Export** to download the XML). Deposit status (Registered /
Error) and the Crossref response are shown per item.

## Notes

- The deposit resource URLs point at the OMP catalog: `.../catalog/book/{id}` for the
  monograph and `.../catalog/book/{id}/chapter/{chapterId}` for chapters.
- ISBNs are taken from the monograph's publication formats (ONIX identification codes
  ISBN-13/ISBN-10); when none are present a `<noisbn reason="monograph"/>` is emitted.
- Metadata is exported in the publication's own locale.

## Contributing

Issues and pull requests are welcome.

## License

Distributed under the **GNU GPL v3**. See [`LICENSE`](LICENSE) and `docs/COPYING`.

---

## 🇧🇷 Português

Plugin **Crossref** para o **Open Monograph Press (OMP)**: registra DOIs de livros
(monografias) e capítulos no [Crossref](https://www.crossref.org/) e exporta o XML de
depósito de livro (schema 5.3.1).

O OMP 3.4/3.5 traz o framework de DOI, mas — diferente do OJS — **não** inclui um
plugin de agência de registro Crossref. Este plugin preenche essa lacuna, integrando-se
ao framework nativo de DOI (`IDoiRegistrationAgency`): os DOIs são atribuídos e
gerenciados em **Configurações → Distribuição → DOIs** e depositados pela página de
gestão de **DOIs**, exatamente como no OJS.

> Desenvolvido pela **[OJSBR](https://ojsbr.com.br)**, com base na estrutura do plugin
> Crossref do OJS, adaptado ao framework de DOI do OMP.

### O que recebe DOI

- **Monografia** (a publicação) → `<book book_type="monograph">`
- **Capítulo** → `<content_item component_type="chapter">`

### Instalação e configuração

1. Instale em **Configurações → Website → Plugins → Enviar um novo plugin** (pacote
   `crossref.tar.gz`) ou extraia em `plugins/generic/` (ficando `plugins/generic/crossref/`).
2. Ative o **Crossref Manager Plugin** na lista de plugins *Genéricos*.
3. Em **Configurações → Distribuição → DOIs**, habilite DOIs, defina o **prefixo**,
   escolha os tipos de conteúdo (monografias e/ou capítulos), selecione **Crossref** como
   agência de registro e preencha nome/e-mail do depositante e usuário/senha do Crossref.
   Mantenha o **modo de teste** ativo para validar em `test.crossref.org`; desative para
   depósitos de produção em `doi.crossref.org`.
4. Garanta um **Editor (Publisher)** em **Configurações → Editora**, exigido pelo Crossref.

### Licença

Distribuído sob a **GNU GPL v3**. Veja [`LICENSE`](LICENSE) e `docs/COPYING`.
