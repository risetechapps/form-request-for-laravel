# Changelog

Todas as alterações notáveis neste projeto serão documentadas neste arquivo.
O formato é baseado em [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), e este projeto segue o [Versionamento Semântico](https://semver.org/lang/pt-BR/) (SemVer).

## [Unreleased]

### Adicionado
- `GET /forms` informa a paginação em `meta` (`current_page`, `last_page`, `per_page`, `total`, `from`, `to`), ao lado de `data`. Antes a resposta trazia só os itens da página.
- `GET /forms/{form}/schema`: descreve as regras de um formulário para validação no cliente, com nome, parâmetros e mensagem de cada regra. Regras que consultam o banco vêm como `server_only` e sem parâmetros.
- `POST` e `PUT /forms` recusam com 422 regras que o Validator não conhece ou `unique`/`exists` sem parâmetros, em vez de gravá-las. A verificação (`RuleInspector`) reconhece as regras nativas e todas as extensões registradas, inclusive as da aplicação.
- Chave de configuração `log_channel`, usada pelos validadores do pacote para registrar falhas internas.
- PHPStan (Larastan, nível 6) e Pint, com os scripts `composer analyse`, `composer format` e `composer format-check`.
- Testes da API e de todos os comandos Artisan.
- `form-request:list --json` e `--field` com padrão (`itens.*`).
- `form-request:list --missing`: lista só as regras sem mensagem personalizada (ignorando `bail`, `nullable` e outras que não geram mensagem) e sai com código 1 se houver alguma. O formulário interno `form_request` fica de fora, a menos que seja pedido com `--form=form_request`.
- `form-request:import --dry-run`: mostra o que seria criado, atualizado ou ignorado, sem gravar.
- `form-request:export` sem `--file` escreve o JSON no stdout.

### Alterado
- O pacote não usa mais o Loggly. Validadores e `FormController` registram falhas inesperadas pelo logger do Laravel, no canal `rules.log_channel`; quem acompanhava esses erros no Loggly deve apontar `log_channel` para o canal correspondente. Os logs de sucesso de cada operação da API e os de registro não encontrado (404) deixam de existir.
- `FormController` passa a estender `Illuminate\Routing\Controller` em vez do `App\Http\Controllers\Controller` da aplicação, que não precisa mais existir. Traits e métodos adicionados ao controller base do app deixam de valer para as rotas do pacote.
- `FormController` monta as respostas sem as macros `jsonSuccess`/`jsonGone`/`jsonNotFound` do `risetechapps/risetools`. O envelope `{success, code, message, data}` e os status (200, 404 e 410) continuam os mesmos.
- `form-request:validate-rules` usa a mesma verificação da API: regras desconhecidas passam a ser erro (código de saída 1). Deixa de avisar sobre `sometimes|required` e de confundir `required_if` e afins com `required`.
- A migration não depende mais de `tpetry/laravel-postgresql-enhanced`; no PostgreSQL a coluna `form` continua `citext`.
- `Store`/`UpdateFormRequest` negam a requisição (403) quando o model de usuário não tem `hasPermission()`, em vez de falhar com erro 500.
- O log de falha interna de um validador identifica a regra: a mensagem passa de `Error validating data rules` para `Validator {regra} failed` (ex.: `Validator uniqueJson failed`), e o contexto ganha a chave `rule`. O valor continua sendo negado.
- Os comandos Artisan usam uma lista única de formulários (`FormCatalog`), com a origem efetiva de cada um: um formulário no banco e no código aparece uma vez, como `database (sobrescreve config)`.
- **`form-request:export` sem `--file` não grava mais `form-requests-{data}.json`; o JSON sai no stdout.** Scripts que dependiam do arquivo gerado devem passar `--file`. Formulários de código passam a ser exportados com `description` e `data`, como os do banco, em vez de `metadata`; o `import` aceita os dois formatos.
- `form-request:import` valida o arquivo inteiro (mesmas checagens de `POST /forms`, incluindo regras desconhecidas) antes de gravar, e grava numa transação. Um formulário inválido recusa o arquivo todo, com código de saída 1. Uma lista JSON também é recusada.
- `form-request:list` troca a tabela por uma listagem no estilo do `route:list`: formulário com a origem colorida, um campo por linha e as regras ao lado. As mensagens aparecem com `-m`/`--messages`, já traduzidas e com os parâmetros preenchidos ("at least 3 characters"), em vez do marcador `campo.regra`; as personalizadas ficam em amarelo. No `--json`, cada regra informa `custom`.
- `form-request:validate-rules` indica a origem (`[database]` ou `[config]`) de cada erro e valida as duas definições quando o formulário existe nos dois lugares.
- `form-request:stats` lista todos os validadores registrados (pacote, `RulesContract` e `rules.validators`) e mostra quantos formulários estão em cache, em vez de uma contagem de chaves que quase sempre dava 0.
- `form-request:warm-cache` recarrega as entradas existentes e sai com código 1 se algum formulário falhar. Com o cache desligado, ele e o `clear-cache` avisam em vez de reportar sucesso.
- `DynamicFormRequest::translateMessages()` delega para a nova classe `MessageTranslator`.

### Corrigido
- **Segurança:** `index`, `show` e `destroy` não verificavam permissão: qualquer requisição que alcançasse a rota listava, lia ou apagava formulários. Agora, assim como `store`, `update` e o novo `schema`, exigem `hasPermission('FormController@{ação}')`. Aplicações que chamavam essas rotas sem essas permissões passam a receber 403.
- Mensagens de campos aninhados (`cliente.documento`, `itens.*.documento`) eram devolvidas como a chave crua. Mensagens de `validation.custom` no formato do Laravel (`'cliente.documento' => [...]`) também passam a ser encontradas.
- Mensagens personalizadas eram lidas como marcador "campo.regra" e podiam ser trocadas: `'Veja docs.url'` virava "The Veja docs field must be a valid URL.". Agora voltam exatamente como foram escritas. Uma mensagem que seja uma chave de tradução existente (ex.: `'validation.cpf'`) continua sendo traduzida, e passa a usar o nome do campo em vez de "Validation".
- Regras declaradas como array não recebiam o contexto: o id ficava fora do `unique` e os placeholders não eram resolvidos.
- `form-request:import` não limpava o cache ao criar um formulário, que continuava servindo as regras da configuração até o TTL expirar.
- `GET /forms?include_configured=1` descartava a chave `configured`.
- `form-request:migrate` descartava o código de saída do `migrate`.
- O README documentava `form-request:import --file=...`; o arquivo é argumento.
- `form-request:list` exibia formulários sobrescritos pelo banco na seção de código, com as regras do banco.
- `form-request:list --field` casava por trecho: `--field=a` selecionava quase todos os campos.
- `form-request:validate-rules --form=X` e `warm-cache --form=X` ignoravam formulários definidos só em código: o primeiro dizia que estava tudo válido, o segundo que o formulário não existia.
- `form-request:warm-cache` não substituía entradas já em cache e anunciava sucesso com o cache desligado.
- `form-request:export --database --config` não exportava nada.
- `form-request:seed` anunciava sucesso mesmo quando o `db:seed` falhava.

### Removido
- Dependências `risetechapps/monitoring-for-laravel` (e, com ela, `risetechapps/risetools` e `tpetry/laravel-postgresql-enhanced`) e `facade/ignition-contracts` (não usada).
- `.styleci.yml`, substituído pelo `pint.json`; `.idea/` e `.phpunit.result.cache` deixam de ser versionados.

## [2.5.1] - 2026-10-06
- Atualizad packages

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
