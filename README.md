# Laravel Form Request

## 📌 Sobre o Projeto
O **Laravel Form Request** é um package para Laravel que gerencia as regras de validação dos formulários da requisição.

## ✨ Funcionalidades
- 🏷 **Forms** regra de validação dos formulários.
- 🏷 **Forms** feature para classe da validação.

---

## 🚀 Instalação

### 1️⃣ Requisitos
Antes de instalar, certifique-se de que seu projeto atenda aos seguintes requisitos:
- PHP >= 8.0
- Laravel >= 10
- Composer instalado

### 2️⃣ Instalação do Package
Execute o seguinte comando no terminal:
```bash
  composer require risetechapps/form-request-for-laravel
```
---

### 3️⃣ Implemente Form Request
Execute o seguinte comando no terminal:
```php
  
  use RiseTechApps\FormRequest\Traits\hasFormValidation\hasFormValidation;
  use RiseTechApps\FormRequest\ValidationRuleRepository;
  
  class StoreClientRequest extends FormRequest
  {
    use hasFormValidation;

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

        if(auth()->check() && auth()->user()->hasPermission(Permissions::$DASHBOARD_CLIENT_STORE)) {
            return true;
        }

        return false;
    }
 }
```
---

## 🛠 Contribuição
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

