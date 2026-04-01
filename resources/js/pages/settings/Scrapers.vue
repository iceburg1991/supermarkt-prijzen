<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { CheckCircle, XCircle, Key, Trash2, ExternalLink } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { useDateTime } from '@/composables/useDateTime';
import type { BreadcrumbItem } from '@/types';

type Scraper = {
    name: string;
    requiresAuth: boolean;
    hasToken: boolean;
    tokenObtainedAt: string | null;
};

type Props = {
    scrapers: Record<string, Scraper>;
};

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Scraper settings',
        href: '/settings/scrapers',
    },
];

const { formatDateTime } = useDateTime();

const form = useForm({
    supermarket: 'ah',
    auth_code: '',
});

function submitToken() {
    form.post('/settings/scrapers/token', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('auth_code');
        },
    });
}

function deleteToken(supermarket: string) {
    if (confirm('Weet je zeker dat je deze token wilt verwijderen?')) {
        form.delete(`/settings/scrapers/token/${supermarket}`, {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Scraper settings" />

        <h1 class="sr-only">Scraper settings</h1>

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title="Scraper configuratie"
                    description="Beheer de authenticatie tokens voor de supermarkt scrapers"
                />

                <!-- Scraper Status Cards -->
                <div class="space-y-4">
                    <div
                        v-for="(scraper, key) in scrapers"
                        :key="key"
                        class="rounded-lg border p-4"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <component
                                    :is="scraper.hasToken ? CheckCircle : XCircle"
                                    :class="[
                                        'h-5 w-5',
                                        scraper.hasToken ? 'text-green-500' : 'text-red-500'
                                    ]"
                                />
                                <div>
                                    <h3 class="font-medium">{{ scraper.name }}</h3>
                                    <p class="text-sm text-muted-foreground">
                                        <template v-if="!scraper.requiresAuth">
                                            Geen authenticatie nodig
                                        </template>
                                        <template v-else-if="scraper.hasToken">
                                            Token verkregen: {{ formatDateTime(scraper.tokenObtainedAt) }}
                                        </template>
                                        <template v-else>
                                            Geen token geconfigureerd
                                        </template>
                                    </p>
                                </div>
                            </div>

                            <Button
                                v-if="scraper.requiresAuth && scraper.hasToken"
                                variant="ghost"
                                size="sm"
                                @click="deleteToken(key as string)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- AH Token Setup -->
            <div class="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title="Albert Heijn authenticatie"
                    description="Voer een auth code in om een nieuwe token te verkrijgen"
                />

                <!-- Instructions -->
                <div class="rounded-lg bg-muted p-4 text-sm space-y-3">
                    <p class="font-medium">Hoe krijg je een auth code (via browser):</p>
                    <ol class="list-decimal list-inside space-y-2 text-muted-foreground">
                        <li>Open Developer Tools in je browser (<kbd class="bg-background px-1.5 py-0.5 rounded text-xs">F12</kbd>)</li>
                        <li>Ga naar de <strong>Network</strong> tab en vink <strong>Preserve log</strong> aan</li>
                        <li>
                            <a
                                href="https://login.ah.nl/secure/oauth/authorize?client_id=appie-ios&redirect_uri=appie://login-exit&response_type=code"
                                target="_blank"
                                class="inline-flex items-center gap-1 text-primary hover:underline"
                            >
                                Open de AH login pagina
                                <ExternalLink class="h-3 w-3" />
                            </a>
                            en log in
                        </li>
                        <li>Na het inloggen faalt de redirect (dat is normaal)</li>
                        <li>Zoek in de Network tab naar <code class="bg-background px-1 rounded">ingelogd.json</code></li>
                        <li>Klik erop → bekijk de <strong>Response</strong> → kopieer de code na <code class="bg-background px-1 rounded">code=</code></li>
                    </ol>
                    <p class="text-xs text-muted-foreground/70 mt-2">
                        De code ziet eruit als: <code class="bg-background px-1 rounded">xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx</code>
                    </p>
                </div>

                <form @submit.prevent="submitToken" class="space-y-4">
                    <div class="grid gap-2">
                        <Label for="auth_code">Auth code</Label>
                        <Input
                            id="auth_code"
                            v-model="form.auth_code"
                            type="text"
                            placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                            class="font-mono"
                        />
                        <InputError :message="form.errors.auth_code" />
                    </div>

                    <Button
                        type="submit"
                        :disabled="form.processing || !form.auth_code"
                    >
                        <Key class="h-4 w-4 mr-2" />
                        {{ form.processing ? 'Bezig...' : 'Token opslaan' }}
                    </Button>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
