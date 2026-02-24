<?php

namespace NinjaPortal\Api\Support;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class PortalApiContext
{
    /**
     * @return class-string<Model>
     */
    public function consumerModelClass(): string
    {
        return $this->resolveModelClass((string) config('portal-api.auth.consumer_model'));
    }

    /**
     * @return class-string<Model>
     */
    public function adminModelClass(): string
    {
        return $this->resolveModelClass((string) config('portal-api.auth.admin_model'));
    }

    /**
     * @return class-string<Model>
     */
    public function modelClassForContext(string $context): string
    {
        $context = strtolower(trim($context));

        return $context === 'admin'
            ? $this->adminModelClass()
            : $this->consumerModelClass();
    }

    public function guardForContext(string $context): string
    {
        $context = strtolower(trim($context));

        return $context === 'admin'
            ? (string) config('portal-api.auth.guards.admin', 'portal_api_admin')
            : (string) config('portal-api.auth.guards.consumer', 'api');
    }

    public function consumerTable(): string
    {
        return $this->newModel($this->consumerModelClass())->getTable();
    }

    public function adminTable(): string
    {
        return $this->newModel($this->adminModelClass())->getTable();
    }

    public function findConsumerByRouteKeyOrFail(mixed $value): Model
    {
        $modelClass = $this->consumerModelClass();
        $model = $this->newModel($modelClass);

        $resolved = $model->resolveRouteBinding($value);
        if ($resolved instanceof Model) {
            return $resolved;
        }

        return $modelClass::query()->findOrFail($value);
    }

    /**
     * @param  class-string<Model>  $class
     * @return class-string<Model>
     */
    protected function resolveModelClass(string $class): string
    {
        if ($class === '' || ! class_exists($class)) {
            throw new RuntimeException('Portal API auth model is not configured or does not exist.');
        }

        if (! is_subclass_of($class, Model::class)) {
            throw new RuntimeException(sprintf('Configured model [%s] must extend %s.', $class, Model::class));
        }

        return $class;
    }

    /**
     * @param  class-string<Model>  $class
     */
    protected function newModel(string $class): Model
    {
        /** @var Model $model */
        $model = new $class;

        return $model;
    }
}
