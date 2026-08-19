<?php

namespace RiseTechApps\FormRequest\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RiseTechApps\FormRequest\FormRequest;
use RiseTechApps\FormRequest\Models\FormRequest as FormRequestModel;
use RiseTechApps\FormRequest\Tests\TestCase;
use RiseTechApps\FormRequest\ValidationRuleRepository;

class RuleCacheTest extends TestCase
{
    public function test_a_form_uses_a_single_cache_key_regardless_of_context(): void
    {
        FormRequestModel::create([
            'form' => 'cached',
            'rules' => ['ref' => 'required|in:{tenant},other'],
        ]);

        $repository = app(ValidationRuleRepository::class);

        $repository->getRules('cached');
        $repository->getRules('cached', ['tenant' => 1]);
        $repository->getRules('cached', ['tenant' => 2]);
        $repository->getRules('cached', ['id' => 9, 'tenant' => 3]);

        $this->assertTrue(Cache::has('form-request:cached'));

        // Regressão: contextos distintos geravam uma entrada md5 por combinação,
        // além de um registro auxiliar 'form-request:keys:' para rastreá-las.
        $this->assertFalse(Cache::has('form-request:keys:cached'));
    }

    public function test_the_result_is_actually_served_from_cache(): void
    {
        FormRequestModel::create(['form' => 'cached', 'rules' => ['ref' => 'required']]);

        $repository = app(ValidationRuleRepository::class);
        $repository->getRules('cached');

        // Alteração por fora do pacote: só some do resultado se houver cache.
        DB::table('form_requests')->where('form', 'cached')->delete();

        $this->assertSame('required', $repository->getRules('cached')['rules']['ref'] ?? null);
    }

    public function test_clear_cache_invalidates_entries_created_with_context(): void
    {
        FormRequestModel::create([
            'form' => 'cached',
            'rules' => ['ref' => 'required|in:{tenant},other'],
        ]);

        $repository = app(ValidationRuleRepository::class);
        $repository->getRules('cached', ['tenant' => 1]);

        $repository->clearCache('cached');

        $this->assertFalse(Cache::has('form-request:cached'));
    }

    public function test_updating_a_form_through_the_manager_invalidates_the_cache(): void
    {
        $form = FormRequestModel::create(['form' => 'cached', 'rules' => ['ref' => 'old']]);

        $repository = app(ValidationRuleRepository::class);
        $this->assertSame('old', $repository->getRules('cached', ['tenant' => 1])['rules']['ref']);

        app(\RiseTechApps\FormRequest\Services\FormManager::class)
            ->update($form, ['rules' => ['ref' => 'new']]);

        $this->assertSame('new', $repository->getRules('cached', ['tenant' => 1])['rules']['ref']);
    }

    public function test_forget_invalidates_the_cache_of_a_registered_form(): void
    {
        FormRequest::register('temp', ['name' => 'required']);
        $this->assertNotEmpty(FormRequest::resolve('temp', ['tenant' => 1])['rules']);

        FormRequest::forget('temp');

        $this->assertSame([], FormRequest::resolve('temp', ['tenant' => 1])['rules']);
    }
}
