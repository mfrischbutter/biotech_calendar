<script setup lang="ts">
import { ref } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { useTrans } from '@/lib/use-trans';
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
const expanded = ref(true);

const { t } = useTrans();
const user = page.props.auth.user;

const navItems = [
    { name: t('Dashboard'), href: 'dashboard', icon: 'grid' },
    { name: t('Clients'), href: 'clients.index', icon: 'users' },
    { name: t('Appointments'), href: 'calendar.index', icon: 'calendar' },
    ...(user?.role === 'owner'
        ? [{ name: t('Employees'), href: 'employees.index', icon: 'shield' }]
        : []),
];

function isActive(href: string | null): boolean {
    if (!href) return false;
    return route().current(href);
}

function initials(name: string): string {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
}
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-muted/40">
        <!-- Sidebar -->
        <aside
            class="flex flex-col border-r bg-background transition-all duration-200"
            :class="expanded ? 'w-[250px]' : 'w-16'"
        >
            <!-- Logo / toggle -->
            <div class="flex h-14 items-center px-4">
                <button
                    @click="expanded = !expanded"
                    class="flex items-center gap-2 text-foreground"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 12h18M3 6h18M3 18h18" />
                    </svg>
                    <span v-if="expanded" class="text-sm font-semibold whitespace-nowrap">
                        Biotech
                    </span>
                </button>
            </div>

            <Separator />

            <!-- Nav links -->
            <nav class="flex-1 space-y-1 px-2 py-3">
                <template v-for="item in navItems" :key="item.name">
                    <Link
                        v-if="item.href"
                        :href="route(item.href)"
                        class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                        :class="
                            isActive(item.href)
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                        "
                    >
                        <!-- Dashboard icon -->
                        <svg v-if="item.icon === 'grid'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7" /><rect x="14" y="3" width="7" height="7" /><rect x="3" y="14" width="7" height="7" /><rect x="14" y="14" width="7" height="7" />
                        </svg>
                        <!-- Users icon -->
                        <svg v-if="item.icon === 'users'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" />
                        </svg>
                        <!-- Calendar icon -->
                        <svg v-if="item.icon === 'calendar'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2" /><line x1="16" y1="2" x2="16" y2="6" /><line x1="8" y1="2" x2="8" y2="6" /><line x1="3" y1="10" x2="21" y2="10" />
                        </svg>
                        <!-- Shield icon -->
                        <svg v-if="item.icon === 'shield'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
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
                        <Button variant="ghost" class="w-full justify-start gap-3 px-3">
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
