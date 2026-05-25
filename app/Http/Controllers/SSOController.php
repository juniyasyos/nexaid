<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\Sso\SsoFlowService;

class SSOController extends Controller
{
    public function __construct(
        private readonly SsoFlowService $ssoFlowService
    ) {}

    /**
     * Step 1: Authorization endpoint
     * Aplikasi klien redirect user ke endpoint ini dengan app_key dan redirect_uri.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function authorize(Request $request)
    {
        return $this->ssoFlowService->authorize($request);
    }

    /**
     * Step 2: Token endpoint
     * Aplikasi klien menukar authorization code dengan access token.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function token(Request $request): JsonResponse
    {
        return $this->ssoFlowService->token($request);
    }

    /**
     * Revoke token endpoint.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function revoke(Request $request): JsonResponse
    {
        return $this->ssoFlowService->revoke($request);
    }

    /**
     * Introspect token endpoint - untuk validasi token dari aplikasi klien.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function introspect(Request $request): JsonResponse
    {
        return $this->ssoFlowService->introspect($request);
    }

    /**
     * User info endpoint - mendapatkan informasi user dari access token.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function userInfo(Request $request): JsonResponse
    {
        return $this->ssoFlowService->userInfo($request);
    }
}
