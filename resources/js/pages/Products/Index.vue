<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import ProductController from '@/actions/App/Http/Controllers/ProductController';
import { useDateTime } from '@/composables/useDateTime';
import type { BreadcrumbItem } from '@/types';

interface Price {
    price_cents: number;
    promo_price_cents: number;
    available: boolean;
    badge: string | null;
    scraped_at?: string;
}

interface Supermarket {
    identifier: string;
    name: string;
}

interface Product {
    id: number;
    product_id: string;
    supermarket: string;
    name: string;
    quantity: string;
    image_url: string;
    latest_price: Price;
    supermarket_model: Supermarket;
}

interface PaginatedProducts {
    data: Product[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Filters {
    search: string | null;
    supermarket: string | null;
    category: number | null;
    promotions: boolean;
}

interface ScrapeRun {
    supermarket: string;
    last_scraped_at: string;
}

const props = defineProps<{
    products: PaginatedProducts;
    filters: Filters;
    supermarkets: Supermarket[];
    categories: Array<{ id: number; name: string }>;
    lastScrapeRuns: Record<string, ScrapeRun>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Products', href: ProductController.index().url },
];

// Use DateTime composable for automatic UTC to local timezone conversion
const { formatDate, formatRelative } = useDateTime();

// Local filter state
const search = ref(props.filters.search || '');
const selectedSupermarket = ref(props.filters.supermarket || '');
const selectedCategory = ref(props.filters.category?.toString() || '');
const showPromotionsOnly = ref(props.filters.promotions);

// Apply filters
const applyFilters = () => {
    router.get(
        ProductController.index().url,
        {
            search: search.value || undefined,
            supermarket: selectedSupermarket.value || undefined,
            category: selectedCategory.value || undefined,
            promotions: showPromotionsOnly.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        }
    );
};

// Clear filters
const clearFilters = () => {
    search.value = '';
    selectedSupermarket.value = '';
    selectedCategory.value = '';
    showPromotionsOnly.value = false;
    router.get(ProductController.index().url);
};

// Format price
const formatPrice = (cents: number): string => {
    return `€${(cents / 100).toFixed(2)}`;
};

// Get effective price (promo or regular)
const getEffectivePrice = (price: Price): number => {
    return price.promo_price_cents > 0 ? price.promo_price_cents : price.price_cents;
};

// Check if any filters are active
const hasActiveFilters = computed(() => {
    return !!(
        props.filters.search ||
        props.filters.supermarket ||
        props.filters.category ||
        props.filters.promotions
    );
});
</script>

<template>
    <Head title="Products" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full min-h-screen flex-1 flex-col gap-6 p-6 !bg-gray-50">
            <!-- Header with light background -->
            <div class="rounded-lg bg-gradient-to-r from-gray-50 to-gray-100 p-6 shadow-lg border">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight text-gray-900">Products</h1>
                        <p class="mt-1 text-gray-600">
                            Browse and compare supermarket prices
                        </p>
                    </div>
                    <div class="flex gap-3">
                        <div
                            v-if="lastScrapeRuns.ah"
                            class="flex flex-col items-end"
                        >
                            <span class="text-xs font-medium text-gray-500">Albert Heijn</span>
                            <span class="text-sm text-gray-700">
                                {{ formatRelative(lastScrapeRuns.ah.last_scraped_at) }}
                            </span>
                        </div>
                        <div
                            v-if="lastScrapeRuns.jumbo"
                            class="flex flex-col items-end"
                        >
                            <span class="text-xs font-medium text-gray-500">Jumbo</span>
                            <span class="text-sm text-gray-700">
                                {{ formatRelative(lastScrapeRuns.jumbo.last_scraped_at) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters with white background -->
            <div class="rounded-lg border bg-white p-4 shadow-sm">
                <div class="grid gap-4 md:grid-cols-4">
                    <!-- Search -->
                    <div>
                        <label class="text-sm font-medium mb-2 block">
                            Search
                        </label>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search products..."
                            class="w-full rounded-md border px-3 py-2 text-sm"
                            @keyup.enter="applyFilters"
                        />
                    </div>

                    <!-- Supermarket -->
                    <div>
                        <label class="text-sm font-medium mb-2 block">
                            Supermarket
                        </label>
                        <select
                            v-model="selectedSupermarket"
                            class="w-full rounded-md border px-3 py-2 text-sm"
                            @change="applyFilters"
                        >
                            <option value="">All supermarkets</option>
                            <option
                                v-for="supermarket in supermarkets"
                                :key="supermarket.identifier"
                                :value="supermarket.identifier"
                            >
                                {{ supermarket.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Category -->
                    <div>
                        <label class="text-sm font-medium mb-2 block">
                            Category
                        </label>
                        <select
                            v-model="selectedCategory"
                            class="w-full rounded-md border px-3 py-2 text-sm"
                            @change="applyFilters"
                        >
                            <option value="">All categories</option>
                            <option
                                v-for="category in categories"
                                :key="category.id"
                                :value="category.id"
                            >
                                {{ category.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Promotions -->
                    <div class="flex items-end">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                v-model="showPromotionsOnly"
                                type="checkbox"
                                class="rounded"
                                @change="applyFilters"
                            />
                            <span class="text-sm font-medium">
                                Promotions only
                            </span>
                        </label>
                    </div>
                </div>

                <!-- Filter actions -->
                <div v-if="hasActiveFilters" class="mt-4 flex gap-2">
                    <button
                        @click="clearFilters"
                        class="text-sm text-muted-foreground hover:text-foreground"
                    >
                        Clear filters
                    </button>
                </div>
            </div>

            <!-- Results count -->
            <div class="text-sm text-muted-foreground">
                Showing {{ products.data.length }} of {{ products.total }} products
            </div>

            <!-- Product table -->
            <div
                v-if="products.data.length > 0"
                class="rounded-lg border bg-white shadow-sm overflow-hidden"
            >
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="border-b bg-gray-100">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">
                                    Supermarkt
                                </th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">
                                    Product
                                </th>
                                <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700">
                                    Prijs
                                </th>
                                <th class="px-4 py-3 text-center text-sm font-semibold text-gray-700">
                                    Status
                                </th>
                                <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700">
                                    Laatste Update
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="product in products.data"
                                :key="`${product.product_id}-${product.supermarket}`"
                                class="hover:bg-gray-50 transition-colors cursor-pointer dark:hover:bg-gray-700"
                                @click="router.visit(ProductController.show({ productId: product.product_id, supermarket: product.supermarket }).url)"
                            >
                                <!-- Supermarket badge -->
                                <td class="px-4 py-4 w-32">
                                    <span
                                        class="inline-flex items-center justify-center rounded-md px-3 py-1.5 text-xs font-bold uppercase tracking-wide"
                                        :class="{
                                            'bg-blue-500 text-white':
                                                product.supermarket === 'ah',
                                            'bg-yellow-400 text-gray-900':
                                                product.supermarket === 'jumbo',
                                        }"
                                    >
                                        {{ product.supermarket_model.name }}
                                    </span>
                                </td>

                                <!-- Product info -->
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-3">
                                        <!-- Image -->
                                        <div class="h-12 w-12 flex-shrink-0 overflow-hidden rounded-md bg-muted">
                                            <img
                                                v-if="product.image_url"
                                                :src="product.image_url"
                                                :alt="product.name"
                                                class="h-full w-full object-cover"
                                            />
                                        </div>
                                        <!-- Name and quantity -->
                                        <div class="min-w-0 flex-1">
                                            <div class="font-medium text-sm line-clamp-1">
                                                {{ product.name }}
                                            </div>
                                            <div
                                                v-if="product.quantity"
                                                class="text-xs text-muted-foreground mt-0.5"
                                            >
                                                {{ product.quantity }}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Price -->
                                <td class="px-4 py-4 text-right">
                                    <div
                                        v-if="product.latest_price?.promo_price_cents > 0"
                                        class="space-y-1"
                                    >
                                        <div class="text-lg font-bold text-red-600">
                                            {{ formatPrice(product.latest_price.promo_price_cents) }}
                                        </div>
                                        <div class="text-xs text-muted-foreground line-through">
                                            {{ formatPrice(product.latest_price.price_cents) }}
                                        </div>
                                    </div>
                                    <div v-else class="text-lg font-bold">
                                        {{ formatPrice(product.latest_price?.price_cents || 0) }}
                                    </div>
                                </td>

                                <!-- Status -->
                                <td class="px-4 py-4 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <!-- Badge -->
                                        <span
                                            v-if="product.latest_price?.badge"
                                            class="inline-flex items-center rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-800 dark:bg-red-900 dark:text-red-200"
                                        >
                                            {{ product.latest_price.badge }}
                                        </span>
                                        <!-- Availability -->
                                        <div
                                            v-if="!product.latest_price?.available"
                                            class="inline-flex items-center rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-800"
                                        >
                                            Niet beschikbaar
                                        </div>
                                    </div>
                                </td>

                                <!-- Last update -->
                                <td class="px-4 py-4 text-right text-sm text-muted-foreground">
                                    <div v-if="product.latest_price?.scraped_at">
                                        {{ formatDate(product.latest_price.scraped_at) }}
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Empty state -->
            <div
                v-else
                class="flex flex-col items-center justify-center rounded-lg border border-dashed p-12 text-center"
            >
                <p class="text-lg font-semibold mb-2">No products found</p>
                <p class="text-sm text-muted-foreground mb-4">
                    Try adjusting your filters or search term
                </p>
                <button
                    v-if="hasActiveFilters"
                    @click="clearFilters"
                    class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                >
                    Clear filters
                </button>
            </div>

            <!-- Pagination -->
            <div
                v-if="products.last_page > 1"
                class="flex items-center justify-between border-t pt-4"
            >
                <div class="text-sm text-muted-foreground">
                    Page {{ products.current_page }} of {{ products.last_page }}
                </div>
                <div class="flex gap-2">
                    <a
                        v-if="products.current_page > 1"
                        :href="`${ProductController.index().url}?page=${products.current_page - 1}`"
                        class="rounded-md border px-3 py-2 text-sm hover:bg-muted"
                    >
                        Previous
                    </a>
                    <a
                        v-if="products.current_page < products.last_page"
                        :href="`${ProductController.index().url}?page=${products.current_page + 1}`"
                        class="rounded-md border px-3 py-2 text-sm hover:bg-muted"
                    >
                        Next
                    </a>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
