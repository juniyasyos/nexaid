<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function show(Request $request, SettingService $settingService): JsonResponse
    {
        $settings = $settingService->group('company');

        return response()->json([
            'name' => $settings['company.name'] ?? 'Perusahaan Anandan',
            'tagline' => $settings['company.tagline'] ?? 'Melayani dengan Hati dan Profesionalisme',
            'logo' => $settings['company.logo'] ?? '/images/company/logo.png',
            'address' => $settings['company.address'] ?? 'Jl. Raya Kesehatan No. 123, Kecamatan Sejahtera',
            'city' => $settings['company.city'] ?? 'Jember',
            'postal_code' => $settings['company.postal_code'] ?? '68121',
            'phone' => $settings['company.phone'] ?? '(0331) 123456',
            'email' => $settings['company.email'] ?? 'info@citrahusada.co.id',
            'website' => $settings['company.website'] ?? 'https://citrahusada.co.id',
            'director_name' => $settings['company.director_name'] ?? 'dr. Andi Pratama, M.Kes',
            'director_title' => $settings['company.director_title'] ?? 'Direktur Utama',
        ]);
    }
}
