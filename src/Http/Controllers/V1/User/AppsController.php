<?php

namespace NinjaPortal\Api\Http\Controllers\V1\User;

use Illuminate\Http\Request;
use NinjaPortal\Api\Http\Controllers\Controller;
use NinjaPortal\Portal\Contracts\Services\UserAppCredentialServiceInterface;
use NinjaPortal\Portal\Contracts\Services\UserAppServiceInterface;
use NinjaPortal\Portal\Contracts\Services\SettingServiceInterface;

/**
 * @group Apps (Consumer)
 */
class AppsController extends Controller
{
    public function __construct(
        protected UserAppServiceInterface $apps,
        protected UserAppCredentialServiceInterface $credentials,
        protected SettingServiceInterface $settings
    ) {}

    /**
     * List my apps
     *
     * @authenticated
     */
    public function index()
    {
        $email = (string) auth()->user()->email;

        return response()->success($this->apps->all($email));
    }

    /**
     * Create an app
     *
     * @authenticated
     *
     * @bodyParam name string required Example: My App
     * @bodyParam apiProducts array Optional initial API products. Example: ["product-1"]
     */
    public function store(Request $request)
    {
        if (! $this->canCreateApps(auth()->user())) {
            return response()->forbidden('Account pending approval.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'apiProducts' => ['sometimes', 'array'],
            'apiProducts.*' => ['string'],
            'callbackUrl' => ['sometimes', 'string'],
        ]);

        $email = (string) auth()->user()->email;
        $app = $this->apps->create($email, $data);

        return response()->created('App created.', $app);
    }

    /**
     * Get an app
     *
     * @authenticated
     */
    public function show(string $appName)
    {
        $email = (string) auth()->user()->email;

        return response()->success($this->apps->find($email, $appName));
    }

    /**
     * Update an app
     *
     * @authenticated
     */
    public function update(Request $request, string $appName)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'callbackUrl' => ['sometimes', 'string'],
        ]);

        $email = (string) auth()->user()->email;
        $app = $this->apps->update($email, $appName, $data);

        return response()->success('App updated.', $app);
    }

    /**
     * Delete an app
     *
     * @authenticated
     */
    public function destroy(string $appName)
    {
        $email = (string) auth()->user()->email;
        $this->apps->delete($email, $appName);

        return response()->success('App deleted.');
    }

    /**
     * Approve an app
     *
     * @authenticated
     */
    public function approve(string $appName)
    {
        $email = (string) auth()->user()->email;

        return response()->success('App approved.', $this->apps->approve($email, $appName));
    }

    /**
     * Revoke an app
     *
     * @authenticated
     */
    public function revoke(string $appName)
    {
        $email = (string) auth()->user()->email;

        return response()->success('App revoked.', $this->apps->revoke($email, $appName));
    }

    /**
     * Create app credential
     *
     * @authenticated
     *
     * @bodyParam apiProducts array required Example: ["product-1"]
     * @bodyParam expiresIn integer Optional expiry in milliseconds or -1 for never. Example: -1
     */
    public function createCredential(Request $request, string $appName)
    {
        if (! $this->canCreateApps(auth()->user())) {
            return response()->forbidden('Account pending approval.');
        }

        $data = $request->validate([
            'apiProducts' => ['required', 'array', 'min:1'],
            'apiProducts.*' => ['string'],
            'expiresIn' => ['sometimes', 'integer'],
        ]);

        $email = (string) auth()->user()->email;
        $this->credentials->create($email, $appName, $data['apiProducts'], $data['expiresIn'] ?? null);

        return response()->created('Credential created.');
    }

    /**
     * Approve app credential
     *
     * @authenticated
     */
    public function approveCredential(string $appName, string $key)
    {
        $email = (string) auth()->user()->email;
        $this->credentials->approve($email, $appName, $key);

        return response()->success('Credential approved.');
    }

    /**
     * Revoke app credential
     *
     * @authenticated
     */
    public function revokeCredential(string $appName, string $key)
    {
        $email = (string) auth()->user()->email;
        $this->credentials->revoke($email, $appName, $key);

        return response()->success('Credential revoked.');
    }

    /**
     * Delete app credential
     *
     * @authenticated
     */
    public function deleteCredential(string $appName, string $key)
    {
        $email = (string) auth()->user()->email;
        $this->credentials->delete($email, $appName, $key);

        return response()->success('Credential deleted.');
    }

    /**
     * Add products to credential
     *
     * @authenticated
     */
    public function addCredentialProducts(Request $request, string $appName, string $key)
    {
        $data = $request->validate([
            'apiProducts' => ['required', 'array', 'min:1'],
            'apiProducts.*' => ['string'],
        ]);

        $email = (string) auth()->user()->email;
        $this->credentials->addProducts($email, $appName, $key, $data['apiProducts']);

        return response()->success('Products added.');
    }

    /**
     * Remove product from credential
     *
     * @authenticated
     */
    public function removeCredentialProduct(string $appName, string $key, string $product)
    {
        $email = (string) auth()->user()->email;
        $this->credentials->removeProducts($email, $appName, $key, $product);

        return response()->success('Product removed.');
    }

    protected function canCreateApps(mixed $user): bool
    {
        if (! is_object($user)) {
            return false;
        }

        $allowUnapproved = (bool) $this->settings->get('features.allow_unapproved_app_creation');

        if ($allowUnapproved) {
            return true;
        }

        $activeStatus = 'active';
        $class = $user::class;

        if (defined($class.'::ACTIVE_STATUS')) {
            $activeStatus = (string) constant($class.'::ACTIVE_STATUS');
        } elseif (property_exists($class, 'ACTIVE_STATUS')) {
            /** @phpstan-ignore-next-line */
            $activeStatus = (string) $user::$ACTIVE_STATUS;
        }

        return (string) ($user->status ?? '') === $activeStatus;
    }

    /**
     * Approve product on credential
     *
     * @authenticated
     */
    public function approveCredentialProduct(string $appName, string $key, string $product)
    {
        $email = (string) auth()->user()->email;
        $this->credentials->approveApiProduct($email, $appName, $key, $product);

        return response()->success('Product approved.');
    }

    /**
     * Revoke product on credential
     *
     * @authenticated
     */
    public function revokeCredentialProduct(string $appName, string $key, string $product)
    {
        $email = (string) auth()->user()->email;
        $this->credentials->revokeApiProduct($email, $appName, $key, $product);

        return response()->success('Product revoked.');
    }
}
