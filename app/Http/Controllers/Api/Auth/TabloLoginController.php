<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Auth\LoginTabloCodeAction;
use App\Actions\Auth\LoginTabloPreviewAction;
use App\Actions\Auth\LoginTabloShareAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginTabloPreviewRequest;
use App\Http\Requests\Api\Auth\LoginTabloShareRequest;
use App\Http\Requests\Api\LoginTabloCodeRequest;

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
    public function loginTabloShare(LoginTabloShareRequest $request)
    {
        return app(LoginTabloShareAction::class)->execute($request);
    }

    /**
     * Login with admin preview token (one-time use)
     */
    public function loginTabloPreview(LoginTabloPreviewRequest $request)
    {
        return app(LoginTabloPreviewAction::class)->execute($request);
    }
}
