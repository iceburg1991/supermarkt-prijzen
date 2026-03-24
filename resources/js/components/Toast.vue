<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { CheckCircle, XCircle, AlertTriangle, Info, X } from 'lucide-vue-next';

interface FlashMessages {
    success?: string | null;
    error?: string | null;
    warning?: string | null;
    info?: string | null;
}

const page = usePage<{ flash: FlashMessages }>();
const show = ref(false);
const message = ref('');
const type = ref<'success' | 'error' | 'warning' | 'info'>('success');

// Watch for flash messages
watch(
    () => page.props.flash,
    (flash) => {
        if (flash.success) {
            showToast(flash.success, 'success');
        } else if (flash.error) {
            showToast(flash.error, 'error');
        } else if (flash.warning) {
            showToast(flash.warning, 'warning');
        } else if (flash.info) {
            showToast(flash.info, 'info');
        }
    },
    { deep: true, immediate: true }
);

function showToast(msg: string, toastType: typeof type.value) {
    message.value = msg;
    type.value = toastType;
    show.value = true;

    // Auto-hide after 5 seconds (longer for warnings/errors)
    const duration = toastType === 'error' || toastType === 'warning' ? 8000 : 5000;
    setTimeout(() => {
        show.value = false;
    }, duration);
}

function close() {
    show.value = false;
}

const icon = computed(() => {
    switch (type.value) {
        case 'success':
            return CheckCircle;
        case 'error':
            return XCircle;
        case 'warning':
            return AlertTriangle;
        case 'info':
            return Info;
    }
});

const bgColor = computed(() => {
    switch (type.value) {
        case 'success':
            return 'bg-green-50 border-green-200';
        case 'error':
            return 'bg-red-50 border-red-200';
        case 'warning':
            return 'bg-yellow-50 border-yellow-200';
        case 'info':
            return 'bg-blue-50 border-blue-200';
    }
});

const iconColor = computed(() => {
    switch (type.value) {
        case 'success':
            return 'text-green-600';
        case 'error':
            return 'text-red-600';
        case 'warning':
            return 'text-yellow-600';
        case 'info':
            return 'text-blue-600';
    }
});

const textColor = computed(() => {
    switch (type.value) {
        case 'success':
            return 'text-green-900';
        case 'error':
            return 'text-red-900';
        case 'warning':
            return 'text-yellow-900';
        case 'info':
            return 'text-blue-900';
    }
});
</script>

<template>
    <Transition
        enter-active-class="transition ease-out duration-300"
        enter-from-class="translate-y-2 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition ease-in duration-200"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-2 opacity-0"
    >
        <div
            v-if="show"
            class="fixed top-4 right-4 z-50 max-w-md w-full"
        >
            <div
                :class="[
                    'rounded-lg border-2 p-4 shadow-lg',
                    bgColor,
                ]"
            >
                <div class="flex items-start gap-3">
                    <component
                        :is="icon"
                        :class="['h-5 w-5 flex-shrink-0 mt-0.5', iconColor]"
                    />
                    <div class="flex-1 min-w-0">
                        <p :class="['text-sm font-medium', textColor]">
                            {{ message }}
                        </p>
                    </div>
                    <button
                        @click="close"
                        :class="['flex-shrink-0 hover:opacity-70 transition-opacity', iconColor]"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>
