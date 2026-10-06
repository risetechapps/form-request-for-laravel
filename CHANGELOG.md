# Changelog

Todas as alterações notáveis neste projeto serão documentadas neste arquivo.
O formato é baseado em [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), e este projeto segue o [Versionamento Semântico](https://semver.org/lang/pt-BR/) (SemVer).


## [2.5.0] - 2026-10-06
- Atualizad packages

## [2.4.0] - 2026-08-19

### Adicionado
- O contexto de validação passa a alimentar também os dados do validador. `HasFormValidation` mescla `validationContext()` em `validationData()`, o que habilita a interpolação nativa do Laravel nas regras — `unique:tabela,coluna,[id]` — e permite que uma regra customizada declarada como `minhaRegra:id` resolva o valor com `data_get($validator->getData(), 'id')`, do mesmo modo que `same:password` e `gt:idade` referenciam outros campos.
- `validationContext()` disponível em `HasFormValidation`, e não apenas em `DynamicFormRequest`: form requests que estendem `Illuminate\Foundation\Http\FormRequest` e usam só o trait têm agora o mesmo comportamento.

### Alterado
- `setIdUpdate` deixa de sobrescrever um `except` informado explicitamente. Ele só preenche a posição quando ela foi omitida, preservando `unique:tabela,coluna,99`, `unique:tabela,coluna,NULL,...` e a interpolação nativa `unique:tabela,coluna,[id]`.
- A resolução de placeholders acontece apenas na porção de parâmetros de cada segmento da regra, nunca no nome. Um parâmetro que apenas se chama como a chave do contexto é preservado: `exists:tabela,id` mantém a coluna `id`, e `minhaRegra:id` entrega `id` ao validador.
- O guard sobre regras contendo `exists:` foi removido: ele existia para contornar a ambiguidade acima, que deixou de ser possível, e desligava a substituição na regra inteira.
- Cada formulário passa a usar uma única chave de cache. O contexto não altera mais o valor resolvido, então deixou de compor a chave; o registro auxiliar de chaves derivadas foi removido.
- `DynamicFormRequest` não recebe mais `ValidationRuleRepository` pelo construtor, resolvendo-o sob demanda.

### Corrigido
- `FormRequest::register()` chamado no `boot()` de um provider da aplicação era descartado. `FormRegistry` não tinha binding em `register()`, então o container devolvia uma instância nova a cada resolução, e o callback `booted()` ainda substituía o binding. Corresponde à "Opção 1" documentada no README, que nunca funcionou.
- O contexto de validação era mesclado no `WHERE` da consulta a `form_requests`. Chaves que não correspondem a colunas quebravam a resolução — erro no PostgreSQL e, no SQLite, zero linhas em silêncio, com queda para as regras da configuração.
- Placeholders no formato `:chave` consumiam o `:` separador do Laravel e fundiam nome e valor da regra, resultando em `Method Illuminate\Validation\Validator::validate<Regra><valor> does not exist`.
- `unique:tabela` sem coluna explícita recebia o id na posição da coluna. Passa a preencher `NULL`, que faz o Laravel usar o nome do atributo.
- As mensagens padrão do pacote nunca eram resolvidas em `DynamicFormRequest`: o namespace usado era `formrequest::` onde o provider registra `form-request::`, e a chave era montada como `campo.regra` onde o arquivo do pacote é indexado por nome de regra. A resposta trazia a chave crua, como `documento.cpf`.
- Os endpoints de criação e atualização persistiam `validationData()`, que devolve todo o input, em vez de `validated()`.
- O construtor de `DynamicFormRequest` declarava uma dependência na posição que a fábrica estática do Symfony ocupa, quebrando `Request::create()` e `duplicate()`.

### Removido
- Chave de configuração `forms`. Estava documentada no README, mas nenhum código do pacote a lia.

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
