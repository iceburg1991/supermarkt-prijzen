<?php

namespace App\Http\Controllers;

use App\Models\ScraperToken;
use App\Services\Scraper\TokenManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScraperSettingsController extends Controller
{
    public function __construct(
        private TokenManager $tokenManager
    ) {}

    /**
     * Show the scraper settings page.
     */
    public function index(): Response
    {
        $ahToken = ScraperToken::forSupermarket('ah');

        return Inertia::render('settings/Scrapers', [
            'scrapers' => [
                'ah' => [
                    'name' => 'Albert Heijn',
                    'requiresAuth' => true,
                    'hasToken' => $ahToken !== null,
                    'tokenObtainedAt' => $ahToken?->token_obtained_at?->toIso8601String(),
                ],
                'jumbo' => [
                    'name' => 'Jumbo',
                    'requiresAuth' => false,
                    'hasToken' => true, // Jumbo doesn't need auth
                    'tokenObtainedAt' => null,
                ],
            ],
        ]);
    }

    /**
     * Exchange auth code for refresh token and store it.
     */
    public function storeToken(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supermarket' => ['required', 'string', 'in:ah'],
            'auth_code' => ['required', 'string', 'min:10'],
        ]);

        try {
            // Exchange the auth code for tokens
            $tokenData = $this->tokenManager->exchangeCode($validated['auth_code']);

            if ($tokenData->refreshToken === null) {
                return back()->with('error', 'Geen refresh token ontvangen van Albert Heijn API.');
            }

            // Store the refresh token in database
            $this->tokenManager->storeRefreshToken(
                $validated['supermarket'],
                $tokenData->refreshToken
            );

            // Also cache the access token for immediate use
            $this->tokenManager->cacheAccessToken($validated['supermarket'], $tokenData);

            return back()->with('success', 'Token succesvol opgeslagen! Je kunt nu Albert Heijn producten scrapen.');
        } catch (\Exception $e) {
            return back()->with('error', 'Token exchange mislukt: '.$e->getMessage());
        }
    }

    /**
     * Delete stored token for a supermarket.
     */
    public function destroyToken(Request $request, string $supermarket): RedirectResponse
    {
        ScraperToken::where('supermarket', $supermarket)->delete();

        return back()->with('success', 'Token verwijderd.');
    }
}
