<script setup lang="ts">
import { computed } from 'vue';
import { Line } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    Filler,
    type ChartOptions,
} from 'chart.js';
import { useDateTime } from '@/composables/useDateTime';

// Register Chart.js components
ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    Filler
);

interface PricePoint {
    scraped_at: string;
    price_cents: number;
    promo_price_cents: number;
}

const props = defineProps<{
    data: PricePoint[];
    productName: string;
}>();

// Use DateTime composable for automatic UTC to local timezone conversion
const { formatDate } = useDateTime();

// Prepare chart data
const chartData = computed(() => {
    const labels = props.data.map((point) => {
        // Format date with short month and day
        return formatDate(point.scraped_at, {
            day: 'numeric',
            month: 'short',
        });
    });

    const regularPrices = props.data.map((point) => point.price_cents / 100);
    const promoPrices = props.data.map((point) =>
        point.promo_price_cents > 0 ? point.promo_price_cents / 100 : null
    );

    return {
        labels,
        datasets: [
            {
                label: 'Regular Price',
                data: regularPrices,
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.4,
                fill: true,
                pointRadius: 3,
                pointHoverRadius: 5,
            },
            {
                label: 'Promo Price',
                data: promoPrices,
                borderColor: 'rgb(239, 68, 68)',
                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                tension: 0.4,
                fill: true,
                pointRadius: 3,
                pointHoverRadius: 5,
                spanGaps: true,
            },
        ],
    };
});

// Chart options
const chartOptions: ChartOptions<'line'> = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: true,
            position: 'top',
        },
        tooltip: {
            mode: 'index',
            intersect: false,
            callbacks: {
                label: (context) => {
                    const label = context.dataset.label || '';
                    const value = context.parsed.y;
                    return value !== null ? `${label}: €${value.toFixed(2)}` : '';
                },
            },
        },
    },
    scales: {
        y: {
            beginAtZero: false,
            ticks: {
                callback: (value) => `€${value}`,
            },
        },
        x: {
            grid: {
                display: false,
            },
        },
    },
    interaction: {
        mode: 'nearest',
        axis: 'x',
        intersect: false,
    },
};
</script>

<template>
    <div class="h-[400px] w-full">
        <Line :data="chartData" :options="chartOptions" />
    </div>
</template>
