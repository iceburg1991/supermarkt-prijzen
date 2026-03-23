<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PriceTrendChart from '@/components/PriceTrendChart.vue';
import ProductController from '@/actions/App/Http/Controllers/ProductController';
import type { BreadcrumbItem } from '@/types';

interface Price {
    price_cents: number;
    promo_price_cents: number;
    available: boolean;
    badge: string | null;
    unit_price: string | null;
    scraped_at: string;
}

interface Supermarket {
    identifier: string;
    name: string;
}

interface Category {
    id: number;
    name: string;
}

interface NormalizedCategory {
    id: number;
    name: string;
}

interface ProductCategory {
    id: number;
    name: string;
    normalized_categories: NormalizedCategory[];
}

interface Product {
    id: number;
    product_id: string;
    supermarket: string;
    name: string;
    quantity: string;
    image_url: string;
    product_url: string;
    latest_price: Price;
    supermarket_model: Supermarket;
    categories: ProductCategory[];
}

interface PriceHistoryPoint {
    scraped_at: string;
    price_cents: number;
    promo_price_cents: number;
}

interface SimilarProduct {
    id: number;
    product_id: string;
    supermarket: string;
    name: string;
    quantity: string;
    image_url: string;
    latest_price: Price;
    supermarket_model: Supermarket;
}

const props = defineProps<{
    product: Product;
    priceHistory: PriceHistoryPoint[];
    averagePrice: number;
    priceChange: number;
    similarProducts: SimilarProduct[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Products', href: ProductController.index().url },
    { title: props.product.name, href: '#' },
];

// Format price
const formatPrice = (cents: number): string => {
    return `€${(cents / 100).toFixed(2)}`;
};

// Get effective price
const getEffectivePrice = (price: Price): number => {
    return price.promo_price_cents > 0 ? price.promo_price_cents : price.price_cents;
};

// Calculate savings
const calculateSavings = (price: Price): number => {
    if (price.promo_price_cents > 0) {
        return price.price_cents - price.promo_price_cents;
    }
    return 0;
};

// Calculate savings percentage
const calculateSavingsPercentage = (price: Price): number => {
    const savings = calculateSavings(price);
    if (savings > 0) {
        return Math.round((savings / price.price_cents) * 100);
    }
    return 0;
};
</script>

<template>
    <Head :title="product.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6 !bg-gray-50 dark:bg-gray-900">
            <!-- Product header -->
            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Image -->
                <div class="rounded-lg border bg-white p-6 shadow-sm">
                    <div class="aspect-square overflow-hidden rounded-lg bg-muted">
                        <img
                            v-if="product.image_url"
                            :src="product.image_url"
                            :alt="product.name"
                            class="h-full w-full object-contain"
                        />
                        <div
                            v-else
                            class="flex h-full items-center justify-center text-muted-foreground"
                        >
                            No image available
                        </div>
                    </div>
                </div>

                <!-- Product info -->
                <div class="flex flex-col gap-4">
                    <!-- Supermarket badge -->
                    <div>
                        <span
                            class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium"
                            :class="{
                                'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200':
                                    product.supermarket === 'ah',
                                'bg-yellow-400 text-gray-900':
                                    product.supermarket === 'jumbo',
                            }"
                        >
                            {{ product.supermarket_model.name }}
                        </span>
                    </div>

                    <!-- Product name -->
                    <h1 class="text-3xl font-bold tracking-tight">
                        {{ product.name }}
                    </h1>

                    <!-- Quantity -->
                    <p v-if="product.quantity" class="text-lg text-muted-foreground">
                        {{ product.quantity }}
                    </p>

                    <!-- Price card -->
                    <div class="rounded-lg border bg-white p-6 shadow-sm">
                        <!-- Current price -->
                        <div class="mb-4">
                            <div class="text-sm text-muted-foreground mb-1">
                                Current Price
                            </div>
                            <div
                                v-if="product.latest_price.promo_price_cents > 0"
                                class="flex items-baseline gap-3"
                            >
                                <span class="text-4xl font-bold text-red-600">
                                    {{ formatPrice(product.latest_price.promo_price_cents) }}
                                </span>
                                <span class="text-xl text-muted-foreground line-through">
                                    {{ formatPrice(product.latest_price.price_cents) }}
                                </span>
                            </div>
                            <div v-else>
                                <span class="text-4xl font-bold text-gray-900">
                                    {{ formatPrice(product.latest_price.price_cents) }}
                                </span>
                            </div>
                        </div>

                        <!-- Savings -->
                        <div
                            v-if="calculateSavings(product.latest_price) > 0"
                            class="mb-4 rounded-md bg-red-50 p-3 dark:bg-red-950"
                        >
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-red-800 dark:text-red-200">
                                    Save {{ formatPrice(calculateSavings(product.latest_price)) }}
                                    ({{ calculateSavingsPercentage(product.latest_price) }}%)
                                </span>
                            </div>
                        </div>

                        <!-- Badge -->
                        <div v-if="product.latest_price.badge" class="mb-4">
                            <span
                                class="inline-flex items-center rounded-full bg-red-100 px-3 py-1 text-sm font-medium text-red-800 dark:bg-red-900 dark:text-red-200"
                            >
                                {{ product.latest_price.badge }}
                            </span>
                        </div>

                        <!-- Unit price -->
                        <div
                            v-if="product.latest_price.unit_price"
                            class="text-sm text-muted-foreground"
                        >
                            {{ product.latest_price.unit_price }}
                        </div>

                        <!-- Availability -->
                        <div class="mt-4 flex items-center gap-2">
                            <div
                                class="h-2 w-2 rounded-full"
                                :class="{
                                    'bg-green-500': product.latest_price.available,
                                    'bg-red-500': !product.latest_price.available,
                                }"
                            ></div>
                            <span class="text-sm">
                                {{
                                    product.latest_price.available
                                        ? 'In stock'
                                        : 'Out of stock'
                                }}
                            </span>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="rounded-lg border bg-white p-4 shadow-sm">
                            <div class="text-sm text-muted-foreground mb-1">
                                30-day Average
                            </div>
                            <div class="text-2xl font-bold text-gray-900">
                                {{ formatPrice(averagePrice) }}
                            </div>
                        </div>
                        <div class="rounded-lg border bg-white p-4 shadow-sm">
                            <div class="text-sm text-muted-foreground mb-1">
                                7-day Change
                            </div>
                            <div
                                class="text-2xl font-bold"
                                :class="{
                                    'text-red-600': priceChange > 0,
                                    'text-green-600': priceChange < 0,
                                }"
                            >
                                {{ priceChange > 0 ? '+' : '' }}{{ priceChange.toFixed(1) }}%
                            </div>
                        </div>
                    </div>

                    <!-- External link -->
                    <a
                        v-if="product.product_url"
                        :href="product.product_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                    >
                        View on {{ product.supermarket_model.name }}
                        <svg
                            class="ml-2 h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                            />
                        </svg>
                    </a>
                </div>
            </div>

            <!-- Price trend chart -->
            <div class="rounded-lg border bg-white p-6 shadow-sm">
                <h2 class="text-xl font-bold mb-4">Price History (90 days)</h2>
                <PriceTrendChart
                    v-if="priceHistory.length > 0"
                    :data="priceHistory"
                    :product-name="product.name"
                />
                <div
                    v-else
                    class="flex h-[400px] items-center justify-center text-muted-foreground"
                >
                    No price history available
                </div>
            </div>

            <!-- Categories -->
            <div
                v-if="product.categories.length > 0"
                class="rounded-lg border bg-white p-6 shadow-sm"
            >
                <h2 class="text-xl font-bold mb-4">Categories</h2>
                <div class="flex flex-wrap gap-2">
                    <span
                        v-for="category in product.categories"
                        :key="category.id"
                        class="inline-flex items-center rounded-full border px-3 py-1 text-sm"
                    >
                        {{ category.name }}
                    </span>
                </div>
            </div>

            <!-- Similar products -->
            <div v-if="similarProducts.length > 0" class="rounded-lg border bg-white p-6 shadow-sm">
                <h2 class="text-xl font-bold mb-4">Compare with other supermarkets</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <a
                        v-for="similar in similarProducts"
                        :key="`${similar.product_id}-${similar.supermarket}`"
                        :href="ProductController.show({ productId: similar.product_id, supermarket: similar.supermarket }).url"
                        class="group flex gap-4 rounded-lg border p-4 transition-all hover:shadow-md"
                    >
                        <!-- Image -->
                        <div class="h-20 w-20 flex-shrink-0 overflow-hidden rounded-md bg-muted">
                            <img
                                v-if="similar.image_url"
                                :src="similar.image_url"
                                :alt="similar.name"
                                class="h-full w-full object-cover"
                            />
                        </div>

                        <!-- Info -->
                        <div class="flex flex-1 flex-col">
                            <span
                                class="mb-1 inline-flex w-fit items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="{
                                    'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200':
                                        similar.supermarket === 'ah',
                                    'bg-yellow-400 text-gray-900':
                                        similar.supermarket === 'jumbo',
                                }"
                            >
                                {{ similar.supermarket_model.name }}
                            </span>
                            <h3 class="font-semibold line-clamp-2 text-sm mb-1">
                                {{ similar.name }}
                            </h3>
                            <div class="mt-auto">
                                <span
                                    class="font-bold"
                                    :class="{
                                        'text-red-600':
                                            similar.latest_price.promo_price_cents > 0,
                                    }"
                                >
                                    {{ formatPrice(getEffectivePrice(similar.latest_price)) }}
                                </span>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
