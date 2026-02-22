<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateApiKeysRequest;
use App\Services\ApiKeyVault;
use Inertia\Inertia;
use Inertia\Response;

class AdminSettingsController extends Controller
{
    public function __construct(private ApiKeyVault $vault) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Settings', [
            'apiKeys' => $this->vault->getStatus(auth()->id()),
        ]);
    }

    public function update(UpdateApiKeysRequest $request)
    {
        $userId = $request->user()->id;

        foreach (['anthropic', 'nano_banana'] as $provider) {
            $key = $request->input($provider);
            if ($key) {
                $this->vault->set($provider, $key, $userId);
            }
        }

        // Kling uses two separate fields combined as access_key:secret_key
        $accessKey = $request->input('kling_access_key');
        $secretKey = $request->input('kling_secret_key');
        if ($accessKey && $secretKey) {
            $this->vault->set('kling', "{$accessKey}:{$secretKey}", $userId);
        }

        return redirect()->route('admin.settings')
            ->with('success', 'API keys updated successfully.');
    }
}
