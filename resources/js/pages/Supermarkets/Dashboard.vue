<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { RefreshCw, ShoppingCart, Tag, Clock, TrendingUp } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';

interface Supermarket {
    identifier: string;
    name: string;
    enabled: boolean;
}

interface LastScrapeRun {
    completed_at: string;
    products_scraped: number;
    duration_seconds: number;
}

interface SupermarketStatistics {
    supermarket: Supermarket;
    product_count: number;
    promotion_count: number;
    total_scrape_runs: number;
    last_scrape_run: LastScrapeRun | null;
}

const props = defineProps<{
    statistics: SupermarketStatistics[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Supermarkets', href: '#' },
];

// Format date
const formatDate = (dateString: string): string => {
    const date = new Date(dateString);
    return date.toLocaleString('nl-NL', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

// Format relative time
const formatRelativeTime = (dateString: string): string => {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 60) {
        return `${diffMins} min geleden`;
    } else if (diffHours < 24) {
        return `${diffHours} uur geleden`;
    } else {
        return `${diffDays} dag${diffDays > 1 ? 'en' : ''} geleden`;
    }
};

// Format duration
const formatDuration = (seconds: number): string => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}m ${secs}s`;
};

// Sync supermarket
const syncSupermarket = (identifier: string) => {
    router.post(`/supermarkets/${identifier}/sync`);
};

// Get supermarket badge color
const getSupermarketBadgeClass = (identifier: string): string => {
    return identifier === 'ah'
        ? 'bg-blue-100 text-blue-800'
        : 'bg-yellow-400 text-gray-900';
};
</script>

<template>
    <Head title="Supermarkets" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full min-h-screen flex-1 flex-col gap-6 p-6 !bg-gray-50">
            <!-- Header -->
            <div class="rounded-lg bg-gradient-to-r from-gray-50 to-gray-100 p-6 shadow-lg border">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight text-gray-900">Supermarkets</h1>
                        <p class="mt-1 text-gray-600">
                            Beheer en synchroniseer supermarkt prijzen
                        </p>
                    </div>
                </div>
            </div>

            <!-- Statistics Grid -->
            <div class="grid gap-6 md:grid-cols-2">
                <div
                    v-for="stat in statistics"
                    :key="stat.supermarket.identifier"
                    class="rounded-lg border bg-white p-6 shadow-sm"
                >
                    <!-- Supermarket Header -->
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-3">
                            <span
                                class="inline-flex items-center rounded-full px-3 py-1 text-sm font-bold"
                                :class="getSupermarketBadgeClass(stat.supermarket.identifier)"
                            >
                                {{ stat.supermarket.name }}
                            </span>
                        </div>
                        <Button
                            @click="syncSupermarket(stat.supermarket.identifier)"
                            size="sm"
                            variant="outline"
                            class="gap-2"
                        >
                            <RefreshCw class="h-4 w-4" />
                            Sync Nu
                        </Button>
                    </div>

                    <!-- Stats Grid -->
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div class="flex items-start gap-3">
                            <div class="rounded-lg bg-blue-50 p-2">
                                <ShoppingCart class="h-5 w-5 text-blue-600" />
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Producten</p>
                                <p class="text-2xl font-bold text-gray-900">
                                    {{ stat.product_count?.toLocaleString() || '0' }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <div class="rounded-lg bg-red-50 p-2">
                                <Tag class="h-5 w-5 text-red-600" />
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Aanbiedingen</p>
                                <p class="text-2xl font-bold text-gray-900">
                                    {{ stat.promotion_count?.toLocaleString() || '0' }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <div class="rounded-lg bg-green-50 p-2">
                                <TrendingUp class="h-5 w-5 text-green-600" />
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Totaal Syncs</p>
                                <p class="text-2xl font-bold text-gray-900">
                                    {{ stat.total_scrape_runs?.toLocaleString() || '0' }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <div class="rounded-lg bg-purple-50 p-2">
                                <Clock class="h-5 w-5 text-purple-600" />
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Laatste Sync</p>
                                <p
                                    v-if="stat.last_scrape_run"
                                    class="text-sm font-semibold text-gray-900"
                                >
                                    {{ formatRelativeTime(stat.last_scrape_run.completed_at) }}
                                </p>
                                <p v-else class="text-sm text-gray-400">Nog niet gesynchroniseerd</p>
                            </div>
                        </div>
                    </div>

                    <!-- Last Scrape Details -->
                    <div
                        v-if="stat.last_scrape_run"
                        class="rounded-lg bg-gray-50 p-4 border border-gray-200"
                    >
                        <h3 class="text-sm font-semibold text-gray-700 mb-2">
                            Laatste Synchronisatie Details
                        </h3>
                        <div class="space-y-1 text-sm text-gray-600">
                            <div class="flex justify-between">
                                <span>Datum:</span>
                                <span class="font-medium text-gray-900">
                                    {{ formatDate(stat.last_scrape_run.completed_at) }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span>Producten:</span>
                                <span class="font-medium text-gray-900">
                                    {{ stat.last_scrape_run.products_scraped?.toLocaleString() || '0' }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span>Duur:</span>
                                <span class="font-medium text-gray-900">
                                    {{ formatDuration(stat.last_scrape_run.duration_seconds) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
