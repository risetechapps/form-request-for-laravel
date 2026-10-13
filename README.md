# Laravel Form Request

## 📌 Sobre o Projeto
O **Laravel Form Request** é um package para Laravel que gerencia as regras de validação dos formulários de forma dinâmica, permitindo definir regras tanto via código quanto via banco de dados.

## ✨ Funcionalidades
- 📋 **Forms dinâmicos** - Regras de validação configuráveis em banco de dados
- 📁 **Forms via código** - Regras definidas em classes PHP
- 🔐 **Validadores customizados** - Documentos brasileiros, boletos, Pix, cartão e senha forte
- 🔗 **Contexto de validação** - Valores da rota disponíveis às regras, com a sintaxe nativa do Laravel
- 🏢 **Escopos de presença** - Condições extras nas regras `unique` e `exists` sem alterar a regra
- ⚡ **Cache** - Cache automático das regras para melhor performance
- 🔄 **Export/Import** - Migração de regras entre ambientes
- 📊 **Estatísticas** - Monitoramento e análise de uso

---

## 🚀 Instalação

### 1⃣ Requisitos
- PHP >= 8.3
- Laravel >= 12
- Composer instalado

### 2⃣ Instalação do Package
```bash
composer require risetechapps/form-request-for-laravel
```

### 3⃣ Publicar Configuração
```bash
php artisan vendor:publish --tag=config
```

### 4⃣ (Opcional) Publicar Traduções
```bash
php artisan vendor:publish --tag=lang
```

### 5⃣ Executar Migrations
```bash
php artisan form-request:migrate
```

### 6⃣ (Opcional) Popular regras padrão
```bash
php artisan form-request:seed
```

---

## 📋 Comandos Artisan

Os comandos enxergam os formulários do banco e os registrados em código numa lista só.
Quando o mesmo nome existe nos dois, vale o do banco (como na validação), e a origem
aparece como `database (sobrescreve config)`.

### Gerenciamento de Regras
| Comando | Descrição |
|---------|-----------|
| `php artisan form-request:list` | Lista formulários, campos e regras, com a origem de cada formulário |
| `php artisan form-request:list -m` | Mostra também a mensagem de cada regra (personalizadas em amarelo) |
| `php artisan form-request:list --missing` | Só as regras sem mensagem personalizada, com a mensagem padrão que vale hoje. Sai com código 1 se houver alguma, para usar na CI. Ignora o formulário interno `form_request`, a menos que seja pedido com `--form` |
| `php artisan form-request:list --database` | Só formulários cujas regras vêm do banco |
| `php artisan form-request:list --config` | Só formulários cujas regras vêm do código |
| `php artisan form-request:list --form=clients` | Filtra por formulário específico |
| `php artisan form-request:list --field=email` | Filtra por campo (nome exato, ou padrão como `itens.*`) |
| `php artisan form-request:list --json` | Mesma listagem em JSON, para scripts |

### Exportar/Importar
```bash
# Exportar para um arquivo (caminho relativo parte da raiz da aplicação)
php artisan form-request:export --file=regras.json

# Sem --file, o JSON sai no stdout
php artisan form-request:export --form=clients | jq '.clients.rules'

# Importar regras
php artisan form-request:import regras.json

# Ver o que seria criado/atualizado, sem gravar
php artisan form-request:import regras.json --dry-run

# Importar e sobrescrever existentes
php artisan form-request:import regras.json --force
```

O `import` faz as mesmas checagens da API (`POST /forms`): se algum formulário do arquivo
tiver regra desconhecida ou formato inválido, nada é gravado e o comando sai com código 1.
A gravação acontece numa transação. Arquivos gerados pelo `export` de versões anteriores
continuam aceitos.

### Cache
```bash
# Recarregar o cache de todos os formulários (substitui entradas antigas)
php artisan form-request:warm-cache

# Recarregar o cache de um formulário específico
php artisan form-request:warm-cache --form=clients

# Limpar cache de um formulário
php artisan form-request:clear-cache clients

# Limpar cache de todos os formulários
php artisan form-request:clear-cache --all
```

Com `rules.cache.enabled` desligado, `warm-cache` e `clear-cache` apenas avisam e saem com
código 0. `warm-cache` sai com código 1 se algum formulário falhar.

### Validação e Estatísticas
```bash
# Validar as regras do banco e do código (código de saída 1 se houver erro, útil na CI)
php artisan form-request:validate-rules

# Estatísticas: formulários por origem e quantos estão em cache
php artisan form-request:stats

# Detalhes por formulário e todos os validadores registrados
php artisan form-request:stats --detailed
```

---

## 📝 Uso

### Estendendo DynamicFormRequest

A forma recomendada. A classe declara apenas **qual** formulário resolver; o
pacote cuida de buscar as regras, traduzir as mensagens e alimentar o validador:

```php
use RiseTechApps\FormRequest\Http\Requests\DynamicFormRequest;

class StoreClientRequest extends DynamicFormRequest
{
    protected function formKey(): string
    {
        return 'clients';
    }

    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasPermission('clients.store');
    }
}
```

Para uma atualização, declare o contexto — os valores que as regras podem
referenciar:

```php
class UpdateClientRequest extends DynamicFormRequest
{
    protected function formKey(): string
    {
        return 'clients';
    }

    #[\Override]
    protected function validationContext(): array
    {
        return ['id' => $this->route('id')];
    }

    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasPermission('clients.update');
    }
}
```

As regras são resolvidas uma única vez por request e ficam em cache na instância.

### Usando apenas a Trait HasFormValidation

Se a classe já estende outro `FormRequest` e não pode trocar de base, o trait
entrega o tratamento de erros e o mesmo mecanismo de contexto. Nesse caso a
resolução das regras fica por sua conta:

```php
use Illuminate\Foundation\Http\FormRequest;
use RiseTechApps\FormRequest\Traits\HasFormValidation\HasFormValidation;
use RiseTechApps\FormRequest\ValidationRuleRepository;

class StoreClientRequest extends FormRequest
{
    use HasFormValidation;

    protected array $result = [];

    public function __construct(
        protected ValidationRuleRepository $repository,
        array $query = [],
        array $request = [],
        array $attributes = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        $content = null
    ) {
        parent::__construct($query, $request, $attributes, $cookies, $files, $server, $content);

        $this->result = $this->repository->getRules('clients', $this->validationContext());
    }

    protected function validationContext(): array
    {
        return ['id' => $this->route('id')];
    }

    public function rules(): array
    {
        return $this->result['rules'];
    }

    public function authorize(): bool
    {
        return auth()->check();
    }
}
```

> O construtor precisa repassar os sete parâmetros do `Request`. Omiti-los quebra
> `Request::create()` e `duplicate()`, que constroem a classe posicionalmente.

> Ao declarar `validationContext()` numa classe que o recebe do trait, **não** use
> `#[\Override]`: não há método de pai correspondente e o PHP emite um erro fatal.
> Em subclasses de `DynamicFormRequest` o atributo é válido.

### Registrando Regras via Código

#### Opção 1: Usando FormRequest::register()

No `AppServiceProvider` ou em um Service Provider:

```php
use RiseTechApps\FormRequest\FormRequest;

public function boot(): void
{
    FormRequest::register('clients', [
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:clients,email',
        'cpf' => 'required|cpf',
        'phone' => 'nullable|string',
    ], [
        'name.required' => 'O nome é obrigatório',
        'email.unique' => 'Este email já está cadastrado',
        'cpf.cpf' => 'CPF inválido',
    ], [
        'description' => 'Regras de validação para clientes',
    ]);
}
```

#### Opção 2: Usando RulesContract (Recomendado para projetos grandes)

Crie uma classe de regras:

```php
<?php

namespace App\Rules;

use RiseTechApps\FormRequest\Contracts\RulesContract;

class ClientsRule implements RulesContract
{
    public static function Rules(): array
    {
        return [
            'store_client' => [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:clients,email',
                'cpf' => 'required|cpf',
            ],
            'update_client' => [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:clients,email',
            ],
        ];
    }

    public static function Messages(): array
    {
        return [
            'store_client' => [
                'name.required' => 'O nome do cliente é obrigatório',
                'email.required' => 'O email é obrigatório',
                'email.email' => 'Informe um email válido',
                'email.unique' => 'Este email já está cadastrado',
                'cpf.required' => 'O CPF é obrigatório',
                'cpf.cpf' => 'CPF inválido',
            ],
            'update_client' => [
                'name.required' => 'O nome do cliente é obrigatório',
                'email.unique' => 'Este email já está em uso por outro cliente',
            ],
        ];
    }

    public static function Validator(): array
    {
        return [
            // Validadores customizados específicos deste módulo
        ];
    }
}
```

Registre no `AppServiceProvider`:

```php
use RiseTechApps\FormRequest\RulesRegistry;
use App\Rules\ClientsRule;

public function boot(RulesRegistry $rulesRegistry): void
{
    $rulesRegistry->register(ClientsRule::class);
}
```

**Vantagens desta abordagem:**
- Separação de responsabilidades
- Facilidade de manutenção
- Suporte a múltiplos formulários em uma única classe
- Organização por módulo

#### Opção 3: Usando a facade

O alias `FormRequest` é registrado automaticamente pelo package discovery e expõe os
mesmos métodos:

```php
use FormRequest;

FormRequest::register('clients', ['name' => 'required|string|max:255']);
```

> Atenção: o alias global tem o mesmo nome curto de `Illuminate\Foundation\Http\FormRequest`.
> Em arquivos que estendem o FormRequest do Laravel, mantenha o `use` explícito.

### API RESTful

O pacote expõe endpoints para gerenciar formulários via API:

```php
// Em routes/api.php
use RiseTechApps\FormRequest\FormRequest;

FormRequest::routes([
    'middleware' => ['auth:sanctum'],
    'prefix' => 'admin'
]);
```

Endpoints disponíveis:
- `GET /api/admin/forms` - Listar formulários
- `POST /api/admin/forms` - Criar formulário
- `GET /api/admin/forms/{id}` - Ver formulário
- `PUT /api/admin/forms/{id}` - Atualizar formulário
- `DELETE /api/admin/forms/{id}` - Remover formulário
- `GET /api/admin/forms/{form}/schema` - Regras de um formulário para validação no cliente

#### Permissões

Todas as rotas exigem um usuário autenticado cujo model tenha o método
`hasPermission(string $module): bool`. O módulo verificado é a ação da rota, no
formato `Controller@método`:

| Rota | Permissão |
|------|-----------|
| `GET /forms` | `RiseTechApps\FormRequest\Http\Controllers\FormController@index` |
| `POST /forms` | `...\FormController@store` |
| `GET /forms/{id}` | `...\FormController@show` |
| `PUT /forms/{id}` | `...\FormController@update` |
| `DELETE /forms/{id}` | `...\FormController@destroy` |
| `GET /forms/{form}/schema` | `...\FormController@schema` |

Sem usuário, sem a permissão ou sem `hasPermission()` no model, a resposta é 403
`{"success": false, "message": "Permission denied."}`. Como o schema costuma ser lido
pelo front-end de quem preenche o formulário, a permissão `@schema` normalmente vai para
esses usuários também, e não só para quem administra as regras.

As respostas seguem o envelope `{success, code, message, data}`: 200 em caso de
sucesso, 404 para formulário inexistente e 410 para falha inesperada, que é registrada
no canal `rules.log_channel`.

`GET /api/forms` pagina com `per_page` (padrão 15) e `page`, e informa a paginação em
`meta`, ao lado de `data`. Com `per_page=0` a lista vem inteira, sem `meta`:

```json
{
    "success": true,
    "code": 200,
    "message": "Operation completed successfully.",
    "data": [...],
    "meta": {"current_page": 2, "last_page": 4, "per_page": 5, "total": 20, "from": 6, "to": 10}
}
```

`GET /api/forms?include_configured=1` acrescenta, ao lado de `data`, a chave
`configured` com os formulários registrados em código.

#### Regras validadas antes de salvar

`POST` e `PUT` recusam com 422 regras que o Validator não conhece, em vez de
gravá-las e falhar só quando alguém enviar o formulário. São reconhecidas as regras
nativas do Laravel, os validadores do pacote e os registrados pela aplicação
(`config('rules.validators')`, `RulesContract` ou `Validator::extend`). Os erros
saem por campo:

```json
{
    "success": false,
    "errors": {
        "rules.name": ["The rule \"requird\" is not registered."],
        "rules.email": ["The rule \"unique\" requires parameters."]
    }
}
```

O comando `form-request:validate-rules` usa a mesma verificação.

#### Schema para o front-end

`GET /forms/{form}/schema` descreve o formulário, do banco ou do código, num formato
pronto para validação no cliente:

```json
{
    "form": "clients",
    "fields": {
        "email": {
            "required": true,
            "rules": [
                {"rule": "required", "parameters": [], "server_only": false, "message": "The Email field is required."},
                {"rule": "max", "parameters": ["255"], "server_only": false, "message": {"string": "...", "numeric": "..."}},
                {"rule": "unique", "parameters": [], "server_only": true, "message": "The Email has already been taken."}
            ]
        }
    }
}
```

- Regras que consultam o banco (`unique`, `exists`, `uniqueJson`, `existsJson`) vêm com
  `server_only: true` e sem parâmetros, que revelariam tabelas e colunas.
- Objetos de regra sem forma textual (closures, `ValidationRule`) aparecem como
  `{"rule": "custom", "server_only": true}`.
- As mensagens são as mesmas do `DynamicFormRequest`. Placeholders como `:min` e, em
  campos aninhados, `:attribute` ficam para o cliente preencher; regras de tamanho
  trazem uma mensagem por tipo.
- O schema é resolvido sem contexto: placeholders como `{tenant}` aparecem crus.

---

## 🔗 Contexto de validação

O contexto são valores que as regras podem referenciar — tipicamente o id do
registro em edição. Ele é declarado uma vez, em `validationContext()`, e o pacote
o usa em dois lugares:

1. **Ao resolver as regras** — interpola placeholders na string e completa o
   `except` de regras `unique`.
2. **Nos dados do validador** — o contexto é mesclado em `validationData()`, então
   as regras o enxergam em tempo de execução.

O contexto tem precedência sobre o corpo da requisição: ele vem da rota ou da
sessão autenticada, e o payload não deve poder sobrescrevê-lo. Uma chave sem regra
correspondente não aparece em `validated()` e portanto não é persistida.

### Ignorar o próprio registro em `unique`

Três formas, da mais idiomática para a mais explícita:

```php
// 1. Interpolação nativa do Laravel: lê o valor dos dados validados.
'email' => 'required|email|unique:clients,email,[id]',

// 2. Sem informar o except: o pacote completa com o id do contexto.
'email' => 'required|email|unique:clients,email',

// 3. Placeholder do pacote, para posições que o Laravel não interpola.
'email' => 'required|email|unique:clients,email,{id}',
```

Um `except` informado explicitamente é sempre preservado — o preenchimento
automático só ocorre quando a posição foi omitida.

### Regras customizadas

Um parâmetro que nomeia um campo é idioma nativo do Laravel (`same:password`,
`gt:idade`). O pacote preserva a string, e quem resolve o valor é o validador:

```php
'cpf' => 'required|cpf|meuValidador:id',
```

```php
public static function validate($attribute, $value, $parameters, $validator): bool
{
    $id = data_get($validator->getData(), $parameters[0] ?? 'id');
    // ...
}
```

### Placeholders do pacote

Para posições que a sintaxe do Laravel não interpola, o contexto pode ser
injetado na string da regra com `{chave}` ou `:chave`:

```php
'ref' => 'required|in:{tipo},outro',       // in:cliente,outro
'ref' => 'required|in::tipo,outro',        // idem
```

A substituição ocorre apenas na porção de parâmetros da regra, nunca no nome.
Um parâmetro que apenas se chama como a chave do contexto é preservado:
`exists:clients,id` mantém a coluna `id`.

> Para condições dinâmicas em `unique` e `exists` — tenant, soft delete — prefira
> os [escopos de presença](#-escopos-de-unique-e-exists): eles se aplicam a todas
> as regras sem alterar nenhuma string.

---

## 🔐 Validadores Customizados

O pacote inclui validadores extras:

### Documentos

| Regra | Descrição | Exemplo |
|-------|-----------|---------|
| `cpf` | Valida CPF brasileiro | `'cpf' => 'required\|cpf'` |
| `cnpj` | Valida CNPJ, numérico ou alfanumérico | `'cnpj' => 'required\|cnpj'` |
| `cnae` | Estrutura do código CNAE 2.x (7 dígitos) | `'cnae' => 'required\|cnae'` |
| `ncm` | Estrutura do código NCM/SH (8 dígitos) | `'ncm' => 'required\|ncm'` |

> O `cnpj` segue a Nota Técnica COTEC nº 49/2024: as 12 primeiras posições aceitam letras
> e números, e os 2 dígitos verificadores usam o valor ASCII do caractere menos 48.
> CNPJs numéricos antigos continuam válidos sem alteração.

> `cnae` e `ncm` **não possuem dígito verificador**. A validação é estrutural
> (tamanho, divisão/capítulo válidos) e não garante que o código exista nas tabelas oficiais.

### Financeiro

| Regra | Descrição | Exemplo |
|-------|-----------|---------|
| `credit_card` | Número de cartão pelo algoritmo de Luhn | `'card' => 'required\|credit_card'` |
| `credit_card:bandeiras` | Restringe as bandeiras aceitas | `'card' => 'required\|credit_card:visa,mastercard'` |
| `pix_key` | Chave Pix em qualquer formato do Banco Central | `'key' => 'required\|pix_key'` |
| `pix_key:tipos` | Restringe os tipos aceitos | `'key' => 'required\|pix_key:email,phone'` |
| `bank_barcode` | Código de barras bancário de 44 posições | `'code' => 'required\|bank_barcode'` |
| `digitable_line` | Linha digitável de 47 ou 48 posições | `'line' => 'required\|digitable_line'` |
| `bank_slip` | Alias de `digitable_line` | `'boleto' => 'required\|bank_slip'` |

Bandeiras aceitas em `credit_card`: `visa`, `mastercard`, `amex`, `elo`, `hipercard`,
`diners`, `discover`, `jcb`.

Tipos aceitos em `pix_key`: `cpf`, `cnpj`, `email`, `phone`, `random`.
Chaves de CPF/CNPJ trafegam apenas com dígitos e telefone segue E.164 (`+5511999998888`),
como definido pela DICT.

`bank_barcode` e `digitable_line` cobrem tanto títulos bancários quanto contas de
arrecadação (iniciadas em 8). Na linha digitável, além do DV de cada campo, o código de
barras é remontado e revalidado.

### Outros

| Regra | Descrição | Exemplo |
|-------|-----------|---------|
| `strong_password` | Força da senha, parametrizável | `'password' => 'required\|strong_password'` |
| `uniqueJson` | Valida unicidade em coluna JSON | `'email' => 'uniqueJson:users,preferences.email'` |
| `existsJson` | Exige que o valor exista em coluna JSON | `'email' => 'existsJson:users,preferences.email'` |
| `required_if_any` | Requerido se qualquer campo for preenchido | `'field' => 'required_if_any:field_a,field_b'` |

O `strong_password` exige, por padrão, 8 caracteres com maiúscula, minúscula, número e
símbolo. Os parâmetros ajustam esse conjunto — um número define o comprimento mínimo e os
demais valores (`upper`, `lower`, `number`, `symbol`) substituem a lista de exigências:

```php
'password' => 'required|strong_password',              // padrão
'password' => 'required|strong_password:12',           // apenas aumenta o mínimo
'password' => 'required|strong_password:10,upper,number',
```

Letras acentuadas contam como letra, e não como símbolo.

### Mensagens de erro

Todas as regras acima têm mensagem padrão **em inglês**, registrada como fallback. Elas
só aparecem quando a aplicação não define a sua própria — a tradução para outros idiomas
fica a cargo de quem usa o package.

A ordem de precedência é a do próprio Laravel:

1. Mensagem inline do FormRequest (`messages()` ou o 3º argumento de `Validator::make`)
2. `validation.custom.{campo}.{regra}` nos arquivos de tradução da aplicação
3. `validation.{regra}` nos arquivos de tradução da aplicação
4. Mensagem padrão do package (inglês)

Para traduzir, basta declarar as chaves no `lang` da aplicação:

```php
// lang/pt_BR/validation.php
return [
    'cpf' => 'O campo :attribute deve conter um CPF válido.',
    'strong_password' => 'A senha informada é muito fraca.',
    'pix_key' => 'O campo :attribute deve conter uma chave Pix válida.',
];
```

> A chave é o nome da regra em **snake_case**, que é como o Laravel a procura.
> Atenção nas regras em camelCase: `uniqueJson` vira `validation.unique_json` e
> `existsJson` vira `validation.exists_json`.

Se preferir partir das mensagens do package, publique-as e edite:

```bash
php artisan vendor:publish --tag=lang
```

Os arquivos vão para `lang/vendor/form-request/`. Esse caminho altera apenas o **fallback**;
para aplicações multi-idioma prefira declarar `validation.{regra}` no `lang` da aplicação,
que é resolvido a cada validação e respeita o locale do request.

### Registrando validadores próprios

Em `config/rules.php`, no formato `'regra' => Classe::class`. A classe precisa implementar
`RiseTechApps\FormRequest\Contracts\ValidatorContract`. Chaves declaradas aqui
sobrescrevem os validadores nativos:

```php
'validators' => [
    'my_document' => \App\Validators\MyDocument::class,
],
```

---

## 🏢 Escopos de `unique` e `exists`

Regras como `unique:authentications,email` geram sempre a mesma consulta:

```sql
select count(*) as aggregate from "authentications" where "email" = ?
```

Em cenários multi-tenant é preciso restringir essa consulta ao tenant atual, o que
normalmente exigiria criar uma regra personalizada para cada tabela. Os escopos de
presença injetam condições extras em **todas** as regras `unique` e `exists`, sem
alterar nenhuma string de regra:

```php
use RiseTechApps\FormRequest\FormRequest;

// Em AppServiceProvider::boot()
FormRequest::presenceScope('authentications', fn($query) => $query->where('tenant_id', tenant()->id));

// Aplicado a todas as tabelas
FormRequest::presenceScopeAll(fn($query) => $query->whereNull('deleted_at'));
```

A regra continua `unique:authentications,email`, mas a consulta passa a ser:

```sql
select count(*) as aggregate from "authentications"
where "tenant_id" = ? and "deleted_at" is null and "email" = ?
```

As closures são avaliadas **no momento da consulta**, então leem sempre o tenant
resolvido naquele request ou job.

### Escopos nomeados

Informar um nome permite substituir ou remover o escopo depois — útil quando o registro
acontece em um middleware por request:

```php
use RiseTechApps\FormRequest\Validation\PresenceScopeRegistry;

FormRequest::presenceScope('authentications', $scope, 'tenant');

app(PresenceScopeRegistry::class)->forget('authentications', 'tenant');
```

### Ignorando os escopos

```php
FormRequest::withoutPresenceScopes(fn() => $validator->validate());
```

### Via configuração

Cada entrada é uma classe invocável resolvida pelo container, que recebe o query builder
e o nome da tabela. Use `'*'` para alcançar todas as tabelas:

```php
'presence_scopes' => [
    '*' => [\App\Validation\TenantScope::class],
    'authentications' => [\App\Validation\NotDeletedScope::class],
],
```

```php
namespace App\Validation;

use Illuminate\Database\Query\Builder;

class TenantScope
{
    public function __invoke(Builder $query, string $table): void
    {
        $query->where('tenant_id', tenant()->id);
    }
}
```

> Os escopos valem para `unique` **e** `exists`. O ponto de extensão é o
> `PresenceVerifier` do Laravel, que não informa qual das duas regras originou a consulta.

---

## ⚛️ Configuração

Arquivo `config/rules.php`:

```php
return [
    // Validadores próprios: 'regra' => Classe::class
    'validators' => [
        // 'my_document' => \App\Validators\MyDocument::class,
    ],

    // Condições extras aplicadas às regras unique e exists
    'presence_scopes' => [
        // '*' => [\App\Validation\TenantScope::class],
        // 'authentications' => [\App\Validation\NotDeletedScope::class],
    ],

    // Canal de log das falhas internas dos validadores (null = canal padrão)
    // Uma falha interna (tabela inexistente, banco fora do ar) nega o valor
    // e grava "Validator {regra} failed", com a regra também em context.rule.
    'log_channel' => null,

    // Configuração de cache
    'cache' => [
        'enabled' => true,
        'ttl' => 300,  // segundos
        'store' => null, // null = cache padrão
    ],
];
```

---

## 🏛️ Arquitetura

### Estrutura de Banco

Tabela `form_requests`:
- `id` - UUID
- `form` - Nome/chave do formulário (unique)
- `rules` - JSON com as regras
- `messages` - JSON com mensagens personalizadas
- `data` - Metadados adicionais
- `description` - Descrição
- `timestamps` - created_at, updated_at

### Fluxo de Resolução

1. **Cache** - Verifica se existe no cache. Cada formulário usa uma única chave,
   `form-request:{nome}`, invalidada a cada escrita.
2. **Banco de Dados** - Busca regras persistidas, apenas pelo nome do formulário.
3. **Configuração** - Se o banco não tiver o formulário, usa as regras em código.
4. **Mensagens** - Gera mensagens padrão se o formulário não trouxer as suas.
5. **Contexto** - Sobre o resultado cacheado, completa o `except` das regras
   `unique` e resolve os placeholders. Por acontecer depois do cache, o contexto
   não multiplica as entradas.

### Validação

Os validadores customizados são registrados no boot via `Validator::extend`, a partir do
`RulesRegistry` — que reúne os validadores nativos, os das classes `RulesContract` e os
declarados em `config('rules.validators')`.

Para as regras `unique` e `exists`, o package substitui o `validation.presence` do Laravel
por um `ScopedPresenceVerifier`, que aplica os escopos registrados. Um presence verifier
totalmente customizado da aplicação tem precedência e é preservado.

---

## 🧪 Testes e qualidade

```bash
composer install
composer test          # PHPUnit
composer analyse       # PHPStan (Larastan), nível 6
composer format        # Pint
composer format-check  # Pint sem alterar arquivos, como no CI
```

O CI roda os testes em PHP 8.3 e 8.4, com as versões mínimas e as mais recentes das
dependências, além do PHPStan e do Pint.

### Rodar os comandos sem uma aplicação

O `testbench.yaml` sobe uma aplicação Laravel mínima com o pacote e um banco SQLite,
para experimentar os comandos Artisan direto do repositório:

```bash
vendor/bin/testbench workbench:create-sqlite-db   # uma vez
vendor/bin/testbench form-request:migrate
vendor/bin/testbench form-request:import regras.json --dry-run
vendor/bin/testbench form-request:list
```

O banco fica em `vendor/orchestra/testbench-core/laravel/database/database.sqlite` e é
recriado com `workbench:drop-sqlite-db` seguido de `workbench:create-sqlite-db`.

---

## 🤝 Contribuição

Sinta-se à vontade para contribuir! Basta seguir estes passos:
1. Faça um fork do repositório
2. Crie uma branch (`feature/nova-funcionalidade`)
3. Faça um commit das suas alterações
4. Envie um Pull Request

---

## 📜 Licença

Este projeto é distribuído sob a licença MIT. Veja o arquivo [LICENSE](LICENSE) para mais detalhes.

---

💡 **Desenvolvido por [Rise Tech](https://risetech.com.br)**
