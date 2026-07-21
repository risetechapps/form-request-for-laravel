# Changelog

Todas as alterações notáveis neste projeto serão documentadas neste arquivo.
O formato é baseado em [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), e este projeto segue o [Versionamento Semântico](https://semver.org/lang/pt-BR/) (SemVer).

## [2.3.0] - 2026-07-20

### Adicionado
- Escopos de presença: condições extras aplicadas às regras `unique` e `exists` de todas as tabelas, sem alterar a string da regra. Registro via `FormRequest::presenceScope()`, `FormRequest::presenceScopeAll()` ou pela chave `presence_scopes` da configuração, com `FormRequest::withoutPresenceScopes()` para ignorá-los pontualmente.
- Novos validadores de documentos: `cnae` e `ncm`.
- Novos validadores financeiros: `credit_card` (algoritmo de Luhn, com restrição opcional de bandeira), `pix_key`, `bank_barcode`, `digitable_line` e o alias `bank_slip`.
- Novos validadores auxiliares: `strong_password` (comprimento e requisitos parametrizáveis) e `existsJson`.
- Mensagens padrão em inglês para todos os validadores do package, publicáveis com `vendor:publish --tag=lang`.
- Suporte à chave `validators` da configuração, permitindo registrar validadores próprios e sobrescrever os nativos.
- Suíte de testes automatizados do package.

### Alterado
- O validador `cnpj` passa a aceitar CNPJ alfanumérico, conforme a Nota Técnica COTEC nº 49/2024. CNPJs numéricos seguem válidos.
- As traduções do package passam a usar o namespace `form-request`, evitando que sejam mescladas nos arquivos de tradução da aplicação.

### Corrigido
- A chave `validators` da configuração não era lida, impedindo o registro de validadores customizados.
- A facade `FormRequestFacade` não resolvia por falta do binding `form-request` no container.

## [2.2.1] - 2026-04-28
- Atualizado packages.

## [2.2.0] - 2026-03-13
- Atualizado packages.

## [2.1.0] - 2026-02-25
- Corrigido validação de regra 'exists'.

## [2.0.0] - 2026-02-05
- Refatorado Service Provider e registro de regras e validadores.

## [1.2.0] - 2026-01-24
- Corrigido o momento em que registra as regras de validação, o mesmo foi colcado em booted

## [1.1.0] - 2026-01-09
- Removido validação obsoleta e corrigido aplicação de parametros.


## [1.0.0] - 2025-11-27
- Lançamento inicial (Primeira versão estável).
