# Laravel Form Request

## 📌 Sobre o Projeto
O **Laravel Form Request** é um package para Laravel que gerencia as regras de validação dos formulários de forma dinâmica, permitindo definir regras tanto via código quanto via banco de dados.

## ✨ Funcionalidades
- 📋 **Forms dinâmicos** - Regras de validação configuráveis em banco de dados
- 📁 **Forms via código** - Regras definidas em classes PHP
- 🔐 **Validadores customizados** - CPF, CNPJ, uniqueJson, required_if_any
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

| Regra | Descrição | Exemplo |
|-------|-----------|---------|
| `cpf` | Valida CPF brasileiro | `'cpf' => 'required\|cpf'` |
| `cnpj` | Valida CNPJ brasileiro | `'cnpj' => 'required\|cnpj'` |
| `uniqueJson` | Valida unicidade em coluna JSON | `'email' => 'uniqueJson:users,preferences.email'` |
| `required_if_any` | Requerido se qualquer campo for preenchido | `'field' => 'required_if_any:field_a,field_b'` |

---

## ⚛️ Configuração

Arquivo `config/rules.php`:

```php
return [
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
