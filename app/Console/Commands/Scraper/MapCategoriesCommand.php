<?php

declare(strict_types=1);

namespace App\Console\Commands\Scraper;

use App\Domain\Scraper\Services\CategoryNormalizer;
use App\Models\Category;
use App\Models\Supermarket;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('categories:map {--supermarket= : Specific supermarket to map categories for} {--auto : Automatically create mappings without manual review}')]
#[Description('Map supermarket categories to normalized categories')]
class MapCategoriesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CategoryNormalizer $categoryNormalizer): int
    {
        $supermarket = $this->option('supermarket');
        $autoMode = $this->option('auto');

        $this->info('Category Mapping Tool');
        $this->newLine();

        // Get categories to map
        $query = Category::query()
            ->with(['normalizedCategories', 'supermarketModel'])
            ->whereDoesntHave('normalizedCategories'); // Only unmapped categories

        if ($supermarket !== null) {
            // Validate supermarket exists
            if (! Supermarket::where('identifier', $supermarket)->exists()) {
                $this->error("✗ Supermarket '{$supermarket}' not found");

                return Command::FAILURE;
            }

            $query->where('supermarket', $supermarket);
            $this->info("Mapping categories for: {$supermarket}");
        } else {
            $this->info('Mapping categories for all supermarkets');
        }

        $categories = $query->get();

        if ($categories->isEmpty()) {
            $this->info('✓ No unmapped categories found');

            return Command::SUCCESS;
        }

        $this->info("Found {$categories->count()} unmapped categories");
        $this->newLine();

        $mapped = 0;
        $skipped = 0;

        foreach ($categories as $category) {
            // Suggest normalized category
            $suggestion = $categoryNormalizer->suggestMapping($category);

            if ($suggestion === null) {
                $this->warn("⚠ No suggestion for: {$category->name} ({$category->supermarket})");
                $skipped++;

                continue;
            }

            // Display suggestion
            $this->line("Category: <fg=cyan>{$category->name}</> (<fg=yellow>{$category->supermarket}</>)");
            $this->line("Suggested: <fg=green>{$suggestion->name}</>");

            if ($autoMode) {
                // Auto mode: create mapping without asking
                $categoryNormalizer->createMapping($category, $suggestion, 'auto');
                $this->info('✓ Mapped automatically');
                $mapped++;
            } else {
                // Manual mode: ask for confirmation
                $choice = $this->choice(
                    'Action',
                    ['approve' => 'Approve', 'skip' => 'Skip', 'quit' => 'Quit'],
                    'approve'
                );

                if ($choice === 'quit') {
                    $this->newLine();
                    $this->info("Stopped. Mapped {$mapped} categories.");

                    return Command::SUCCESS;
                }

                if ($choice === 'approve') {
                    $categoryNormalizer->createMapping($category, $suggestion, 'manual');
                    $this->info('✓ Mapping approved');
                    $mapped++;
                } else {
                    $this->info('⊘ Skipped');
                    $skipped++;
                }
            }

            $this->newLine();
        }

        // Summary
        $this->info('=== Summary ===');
        $this->info("✓ Mapped: {$mapped}");
        $this->info("⊘ Skipped: {$skipped}");

        return Command::SUCCESS;
    }
}
