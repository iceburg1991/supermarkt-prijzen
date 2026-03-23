<?php

declare(strict_types=1);

namespace App\Console\Commands\Scraper;

use App\Domain\Scraper\Services\TokenManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

#[Signature('scraper:auth:setup {supermarket : Supermarket identifier (e.g., ah)} {--code= : OAuth authorization code from browser}')]
#[Description('Setup OAuth authentication for a supermarket scraper')]
class AuthSetupCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(TokenManager $tokenManager): int
    {
        $supermarket = $this->argument('supermarket');
        $code = $this->option('code');

        if ($code === null) {
            $this->displayInstructions($supermarket);

            return Command::SUCCESS;
        }

        $this->info('Exchanging authorization code for tokens...');

        try {
            // Exchange code for tokens
            $tokenData = $tokenManager->exchangeCode($code);

            // Encrypt refresh token
            $encryptedToken = 'encrypted:'.Crypt::encryptString($tokenData->refreshToken);

            $this->newLine();
            $this->info('✓ Successfully obtained tokens!');
            $this->newLine();

            $this->info('Add this line to your .env file:');
            $this->line('SCRAPER_'.strtoupper($supermarket)."_REFRESH_TOKEN={$encryptedToken}");

            $this->newLine();
            $this->info('Token expires at: '.$tokenData->expiresAt->toDateTimeString());

            // Test the token
            $this->newLine();
            $this->info('Testing token...');

            $testResult = $this->testToken($tokenData->accessToken, $supermarket);

            if ($testResult) {
                $this->info('✓ Token is valid and working!');
            } else {
                $this->warn('⚠ Token test failed - please verify manually');
            }

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $this->error("✗ Failed to exchange code: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    /**
     * Display instructions for obtaining authorization code.
     */
    private function displayInstructions(string $supermarket): void
    {
        $this->info("OAuth Setup Instructions for {$supermarket}:");
        $this->newLine();

        if ($supermarket === 'ah') {
            $this->line('1. Open your browser and navigate to Albert Heijn website');
            $this->line('2. Open Developer Tools (F12)');
            $this->line('3. Go to Network tab');
            $this->line('4. Log in to your Albert Heijn account');
            $this->line('5. Look for a request to the OAuth token endpoint');
            $this->line('6. Copy the authorization code from the request');
            $this->line('7. Run this command again with --code parameter:');
            $this->newLine();
            $this->line('   php artisan scraper:auth:setup ah --code=YOUR_CODE_HERE');
        } else {
            $this->warn("No specific instructions available for {$supermarket}");
        }
    }

    /**
     * Test if the access token works.
     */
    private function testToken(string $accessToken, string $supermarket): bool
    {
        if ($supermarket !== 'ah') {
            return true; // Skip test for non-AH supermarkets
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$accessToken}",
                    'User-Agent' => 'Appie/8.0.0',
                    'x-client-name' => 'appie-ios',
                    'x-client-version' => '8.0.0',
                    'x-application' => 'AHWEBSHOP',
                ])
                ->get('https://api.ah.nl/mobile-services/product/search/v2', [
                    'query' => 'melk',
                    'page' => 0,
                    'size' => 1,
                ]);

            return $response->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
