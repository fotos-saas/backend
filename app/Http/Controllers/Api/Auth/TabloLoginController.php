<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Auth\LoginTabloCodeAction;
use App\Actions\Auth\LoginTabloPreviewAction;
use App\Actions\Auth\LoginTabloShareAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginTabloCodeRequest;
use Illuminate\Http\Request;

class TabloLoginController extends Controller
{
    /**
     * Login with 6-digit tablo/partner client access code
     */
    public function loginTabloCode(LoginTabloCodeRequest $request)
    {
        return app(LoginTabloCodeAction::class)->execute(
            $request->input('code'),
            $request
        );
    }

    /**
     * Login with share token (TabloProject based)
     */
    public function loginTabloShare(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string', 'size:64'],
            'restore' => ['nullable', 'string', 'size:64'],
        ]);

        return app(LoginTabloShareAction::class)->execute($request);
    }

    /**
     * Login with admin preview token (one-time use)
     */
    public function loginTabloPreview(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string', 'size:64'],
        ]);

        return app(LoginTabloPreviewAction::class)->execute($request);
    }
}
