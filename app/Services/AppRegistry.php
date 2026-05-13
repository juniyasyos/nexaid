<?php

namespace App\Services;

use  App\Domain\Iam\Models\Application;;
use App\Services\Contracts\AppRegistryContract;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AppRegistry implements AppRegistryContract
{
    public function create(array $payload): Application
    {
        $data = $this->validate($payload);

        return Application::create($data);
    }

    public function update(Application $application, array $payload): Application
    {
        $data = $this->validate($payload, $application->getKey());

        $application->fill($data);
        $application->save();

        return $application->refresh();
    }

    public function delete(Application $application): void
    {
        $application->delete();
    }

    public function getByKeyOrFail(string $appKey): Application
    {
        return Application::findByKey($this->normalizeKey($appKey));
    }

    public function enabledList(): Collection
    {
        // OPTIMIZATION: Cache enabled applications for 5 minutes to avoid repeated queries
        return Cache::remember('iam.enabled_apps', 300, function () {
            return Application::enabled()->get();
        });
    }

    /**
     * @throws ValidationException
     */
    protected function validate(array $payload, ?int $ignoreId = null): array
    {
        $normalized = $this->prepare($payload);

        $rules = [
            'app_key' => [
                'required',
                'string',
                'max:191',
                'regex:/^[a-z0-9\-_.]+$/',
                Rule::unique('applications', 'app_key')->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'enabled' => ['boolean'],
            'redirect_uris' => ['nullable', 'array'],
            'redirect_uris.*' => ['string'],
            'logo_url' => ['nullable', 'string', 'max:191'],
            'created_by' => ['nullable', 'exists:users,id'],
        ];

        $validator = Validator::make($normalized, $rules);
        $validator->validate();

        return $normalized;
    }

    protected function prepare(array $payload): array
    {
        $normalized = $payload;

        if (array_key_exists('app_key', $normalized)) {
            $normalized['app_key'] = $this->normalizeKey($normalized['app_key']);
        }

        if (! array_key_exists('enabled', $normalized)) {
            $normalized['enabled'] = true;
        }

        if (array_key_exists('redirect_uris', $normalized)) {
            $redirects = Arr::wrap($normalized['redirect_uris']);
            $normalized['redirect_uris'] = array_values(array_filter($redirects));
        }

        return $normalized;
    }

    protected function normalizeKey(?string $key): string
    {
        return Str::slug((string) $key, separator: '.');
    }
}
