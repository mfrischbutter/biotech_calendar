<script setup lang="ts">
import { ref, watch } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { useTrans } from '@/lib/use-trans';
import { initials } from '@/lib/utils';
import { Button } from '@/Components/ui/button';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Separator } from '@/Components/ui/separator';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';

const page = usePage();
const expanded = ref(localStorage.getItem('sidebar-expanded') !== 'false');

watch(expanded, (val) => {
    localStorage.setItem('sidebar-expanded', String(val));
});

const { t } = useTrans();
const user = page.props.auth.user;

const permissions = (user?.permissions ?? []) as string[];

const navItems = [
    { name: t('Dashboard'), href: 'dashboard', icon: 'grid' },
    { name: t('Clients'), href: 'clients.index', icon: 'users' },
    { name: t('Appointments'), href: 'calendar.index', icon: 'calendar' },
    ...(user?.role === 'owner'
        ? [{ name: t('Employees'), href: 'employees.index', icon: 'user-check' }]
        : []),
    ...(user?.role === 'owner' || permissions.includes('settings.view')
        ? [{ name: t('Settings'), href: 'settings.index', icon: 'settings' }]
        : []),
];

function isActive(href: string | null): boolean {
    if (!href) return false;
    return route().current(href);
}
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-muted/40">
        <!-- Sidebar -->
        <aside
            class="flex flex-col border-r bg-background overflow-hidden transition-[width] duration-200"
            :class="expanded ? 'w-[250px]' : 'w-14'"
        >
            <!-- Logo / toggle -->
            <div class="flex h-14 items-center px-2">
                <button
                    @click="expanded = !expanded"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md text-foreground hover:bg-muted"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" class="shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 12h18M3 6h18M3 18h18" />
                    </svg>
                </button>
                <span v-if="expanded" class="ml-2 text-sm font-semibold whitespace-nowrap">
                    Biotech
                </span>
            </div>

            <Separator />

            <!-- Nav links -->
            <nav class="flex-1 space-y-1 px-2 py-3">
                <template v-for="item in navItems" :key="item.name">
                    <Link
                        v-if="item.href"
                        :href="route(item.href)"
                        class="flex h-10 items-center rounded-md text-sm font-medium transition-colors"
                        :class="[
                            isActive(item.href)
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                            expanded ? 'px-3 gap-3' : 'w-10 justify-center',
                        ]"
                    >
                        <!-- Dashboard icon -->
                        <svg v-if="item.icon === 'grid'" xmlns="http://www.w3.org/2000/svg" width="18" height="18" class="shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7" /><rect x="14" y="3" width="7" height="7" /><rect x="3" y="14" width="7" height="7" /><rect x="14" y="14" width="7" height="7" />
                        </svg>
                        <!-- Users icon -->
                        <svg v-if="item.icon === 'users'" xmlns="http://www.w3.org/2000/svg" width="18" height="18" class="shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" />
                        </svg>
                        <!-- Calendar icon -->
                        <svg v-if="item.icon === 'calendar'" xmlns="http://www.w3.org/2000/svg" width="18" height="18" class="shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2" /><line x1="16" y1="2" x2="16" y2="6" /><line x1="8" y1="2" x2="8" y2="6" /><line x1="3" y1="10" x2="21" y2="10" />
                        </svg>
                        <!-- User-check icon (employees) -->
                        <svg v-if="item.icon === 'user-check'" xmlns="http://www.w3.org/2000/svg" width="18" height="18" class="shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="8.5" cy="7" r="4" /><polyline points="17 11 19 13 23 9" />
                        </svg>
                        <!-- Settings icon -->
                        <svg v-if="item.icon === 'settings'" xmlns="http://www.w3.org/2000/svg" width="18" height="18" class="shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
                        </svg>
                        <span v-if="expanded" class="whitespace-nowrap">{{ item.name }}</span>
                    </Link>
                </template>
            </nav>

            <Separator />

            <!-- User section at bottom -->
            <div class="p-2">
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            class="w-full h-10"
                            :class="expanded ? 'justify-start gap-3 px-3' : 'justify-center px-0'"
                        >
                            <Avatar class="h-7 w-7 shrink-0">
                                <AvatarFallback class="text-xs">
                                    {{ initials(page.props.auth.user.name) }}
                                </AvatarFallback>
                            </Avatar>
                            <span v-if="expanded" class="truncate text-sm">
                                {{ page.props.auth.user.name }}
                            </span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" class="w-48">
                        <DropdownMenuItem as-child>
                            <Link :href="route('profile.edit')" class="w-full cursor-pointer">
                                {{ t('Profile') }}
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            class="cursor-pointer"
                            @click="router.post(route('logout'))"
                        >
                            {{ t('Log Out') }}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </aside>

        <!-- Main content -->
        <div class="flex flex-1 flex-col overflow-hidden">
            <!-- Page header -->
            <header v-if="$slots.header" class="border-b bg-background px-6 py-4">
                <slot name="header" />
            </header>

            <!-- Page content -->
            <main
                v-motion
                :initial="{ opacity: 0, y: 8 }"
                :enter="{ opacity: 1, y: 0, transition: { duration: 250 } }"
                class="flex-1 overflow-y-auto p-6"
            >
                <slot />
            </main>
        </div>
    </div>
</template>
