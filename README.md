# Laravel Form Request

## 📌 Sobre o Projeto
O **Laravel Form Request** é um package para Laravel que gerencia as regras de validação dos formulários de forma dinâmica, permitindo definir regras tanto via código quanto via banco de dados.

## ✨ Funcionalidades
- 📋 **Forms dinâmicos** - Regras de validação configuráveis em banco de dados
- 📁 **Forms via código** - Regras definidas em classes PHP
- 🔐 **Validadores customizados** - Documentos brasileiros, boletos, Pix, cartão e senha forte
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

### 4⃣ Executar Migrations
```bash
php artisan form-request:migrate
```

### 5⃣ (Opcional) Popular regras padrão
```bash
php artisan form-request:seed
```

---

## 📋 Comandos Artisan

### Gerenciamento de Regras
| Comando | Descrição |
|---------|-----------|
| `php artisan form-request:list` | Lista todas as regras em formato de tabela |
| `php artisan form-request:list --database` | Lista apenas regras do banco |
| `php artisan form-request:list --config` | Lista apenas regras em código |
| `php artisan form-request:list --form=clients` | Filtra por formulário específico |
| `php artisan form-request:list --field=email` | Filtra por campo específico |

### Exportar/Importar
```bash
# Exportar todas as regras
php artisan form-request:export --file=regras.json

# Exportar apenas um formulário
php artisan form-request:export --file=clients.json --form=clients

# Importar regras
php artisan form-request:import --file=regras.json

# Importar e sobrescrever existentes
php artisan form-request:import --file=regras.json --force
```

### Cache
```bash
# Pré-carregar cache de todos os formulários
php artisan form-request:warm-cache

# Pré-carregar cache de um formulário específico
php artisan form-request:warm-cache --form=clients

# Limpar cache de um formulário
php artisan form-request:clear-cache clients

# Limpar cache de todos os formulários
php artisan form-request:clear-cache --all
```

### Validação e Estatísticas
```bash
# Validar sintaxe das regras
php artisan form-request:validate-rules

# Estatísticas básicas
php artisan form-request:stats

# Estatísticas detalhadas
php artisan form-request:stats --detailed
```

---

## 📝 Uso

### Usando a Trait HasFormValidation

Crie um FormRequest que utiliza as regras dinâmicas:

```php
use RiseTechApps\FormRequest\Traits\HasFormValidation\HasFormValidation;
use RiseTechApps\FormRequest\ValidationRuleRepository;

class StoreClientRequest extends FormRequest
{
    use HasFormValidation;

    protected ValidationRuleRepository $ruleRepository;
    protected array $result = [];

    public function __construct(ValidationRuleRepository $validatorRuleRepository)
    {
        parent::__construct();

        $this->ruleRepository = $validatorRuleRepository;
        $this->result = $this->ruleRepository->getRules('clients');
    }

    public function rules(): array
    {
        return $this->result['rules'];
    }

    public function messages(): array
    {
        return $this->result['messages'];
    }

    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasPermission('clients.store');
    }
}
```

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

    // Regras definidas em código
    'forms' => [
        'user_registration' => [
            'rules' => [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
            ],
            'messages' => [
                'email.unique' => 'validation.email_unique',
            ],
            'metadata' => [
                'description' => 'Default rules for user registration forms.',
            ],
        ],
    ],

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

1. **Cache** - Verifica se existe no cache
2. **Banco de Dados** - Busca regras persistidas
3. **Configuração** - Busca regras definidas em código
4. **Mensagens** - Gera mensagens padrão se necessário

### Validação

Os validadores customizados são registrados no boot via `Validator::extend`, a partir do
`RulesRegistry` — que reúne os validadores nativos, os das classes `RulesContract` e os
declarados em `config('rules.validators')`.

Para as regras `unique` e `exists`, o package substitui o `validation.presence` do Laravel
por um `ScopedPresenceVerifier`, que aplica os escopos registrados. Um presence verifier
totalmente customizado da aplicação tem precedência e é preservado.

---

## 🧪 Testes

```bash
composer install
composer test
```

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
