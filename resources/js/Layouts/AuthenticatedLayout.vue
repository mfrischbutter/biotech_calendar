<script setup lang="ts">
import { computed, ref, onUnmounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import {
    LayoutGrid,
    Building2,
    FileText,
    Calendar,
    Users,
    Settings as SettingsIcon,
    Menu,
    Search as SearchIcon,
} from 'lucide-vue-next';
import { useTrans } from '@/lib/use-trans';
import { initials } from '@/lib/utils';
import { Button } from '@/Components/ui/button';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import GlobalSearch from '@/Components/GlobalSearch.vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';

const page = usePage();
const { t } = useTrans();

const expanded = ref(localStorage.getItem('sidebar-expanded') !== 'false');
const mobileOpen = ref(false);
const searchRef = ref<InstanceType<typeof GlobalSearch> | null>(null);

const showLabels = computed(() => expanded.value || mobileOpen.value);

function toggleSidebar() {
    if (window.innerWidth < 768) {
        mobileOpen.value = !mobileOpen.value;
    } else {
        expanded.value = !expanded.value;
        localStorage.setItem('sidebar-expanded', String(expanded.value));
    }
}

const removeNavigateListener = router.on('navigate', () => {
    mobileOpen.value = false;
});
onUnmounted(() => removeNavigateListener());

const user = page.props.auth.user;
const company = page.props.companyBranding as { name: string; logo_url: string | null } | null;
const permissions = (user?.permissions ?? []) as string[];

function may(permission: string): boolean {
    return user?.role === 'owner' || permissions.includes(permission);
}

const navItems = computed(() => [
    { name: t('Dashboard'), href: 'dashboard', icon: LayoutGrid, show: true },
    { name: t('Clients'), href: 'clients.index', icon: Building2, show: may('clients.view') },
    { name: t('Contracts'), href: 'contracts.index', icon: FileText, show: may('contracts.view') },
    { name: t('Appointments'), href: 'calendar.index', icon: Calendar, show: may('appointments.view') },
    { name: t('Employees'), href: 'employees.index', icon: Users, show: user?.role === 'owner' },
    { name: t('Settings'), href: 'settings.index', icon: SettingsIcon, show: may('settings.view') },
].filter((item) => item.show));

function isActive(href: string): boolean {
    return route().current(href);
}
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-muted/40">
        <!-- Mobile backdrop -->
        <Transition
            enter-active-class="transition-opacity duration-200"
            leave-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-to-class="opacity-0"
        >
            <div
                v-if="mobileOpen"
                class="fixed inset-0 z-30 bg-black/50 md:hidden"
                @click="mobileOpen = false"
            />
        </Transition>

        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-[250px] -translate-x-full flex-col overflow-hidden bg-navy text-navy-foreground transition-all duration-200 md:relative md:z-auto md:translate-x-0"
            :class="[mobileOpen ? 'translate-x-0' : '', expanded ? 'md:w-[250px]' : 'md:w-16']"
        >
            <div class="flex h-14 shrink-0 items-center px-3">
                <button
                    class="grid h-9 w-9 shrink-0 place-content-center rounded-md text-navy-foreground/80 transition-colors hover:bg-white/10 hover:text-navy-foreground"
                    :aria-label="t('Toggle navigation')"
                    @click="toggleSidebar"
                >
                    <Menu class="h-5 w-5" aria-hidden="true" />
                </button>
                <template v-if="showLabels">
                    <img
                        v-if="company?.logo_url"
                        :src="company.logo_url"
                        :alt="company.name"
                        class="ml-2 h-7 rounded bg-white/95 object-contain px-1.5 py-0.5"
                    />
                    <span v-else class="ml-2 truncate text-sm font-semibold">
                        {{ company?.name ?? 'Wilhelmsen BioTec' }}
                    </span>
                </template>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-2 py-3">
                <Link
                    v-for="item in navItems"
                    :key="item.href"
                    :href="route(item.href)"
                    class="flex h-10 items-center rounded-md text-sm font-medium transition-colors"
                    :class="[
                        isActive(item.href)
                            ? 'bg-white/15 text-navy-foreground'
                            : 'text-navy-foreground/70 hover:bg-white/10 hover:text-navy-foreground',
                        showLabels ? 'gap-3 px-3' : 'w-10 justify-center',
                    ]"
                    :title="showLabels ? undefined : item.name"
                >
                    <component :is="item.icon" class="h-[18px] w-[18px] shrink-0" aria-hidden="true" />
                    <span v-if="showLabels" class="whitespace-nowrap">{{ item.name }}</span>
                </Link>
            </nav>

            <div class="border-t border-white/10 p-2">
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            class="flex h-10 w-full items-center rounded-md text-navy-foreground transition-colors hover:bg-white/10"
                            :class="showLabels ? 'gap-3 px-3' : 'justify-center'"
                        >
                            <!-- The bg/text overrides go on Avatar itself: its root
                                 carries `bg-secondary`, which the fallback cannot beat. -->
                            <Avatar class="h-7 w-7 shrink-0 bg-white/20 text-navy-foreground">
                                <AvatarFallback class="text-xs font-semibold text-navy-foreground">
                                    {{ initials(user.name) }}
                                </AvatarFallback>
                            </Avatar>
                            <span v-if="showLabels" class="truncate text-sm">{{ user.name }}</span>
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" class="w-48">
                        <DropdownMenuItem as-child>
                            <Link :href="route('profile.edit')" class="w-full cursor-pointer">
                                {{ t('Profile') }}
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem class="cursor-pointer" @click="router.post(route('logout'))">
                            {{ t('Log Out') }}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </aside>

        <!-- Main column -->
        <div class="flex flex-1 flex-col overflow-hidden">
            <!-- Top bar: search is always visible, so nobody needs a shortcut to find things -->
            <div class="flex h-14 shrink-0 items-center gap-2 border-b bg-background px-3 md:px-6">
                <button
                    class="grid h-9 w-9 shrink-0 place-content-center rounded-md text-foreground hover:bg-muted md:hidden"
                    :aria-label="t('Toggle navigation')"
                    @click="toggleSidebar"
                >
                    <Menu class="h-5 w-5" aria-hidden="true" />
                </button>

                <GlobalSearch ref="searchRef" class="hidden sm:block" />

                <button
                    class="grid h-9 w-9 shrink-0 place-content-center rounded-md text-foreground hover:bg-muted sm:hidden"
                    :aria-label="t('Search')"
                    @click="searchRef?.focus()"
                >
                    <SearchIcon class="h-5 w-5" aria-hidden="true" />
                </button>

                <div class="flex-1"></div>

                <NotificationBell />
            </div>

            <header v-if="$slots.header" class="border-b bg-background px-4 py-3 md:px-6 md:py-4">
                <slot name="header" />
            </header>

            <main class="flex-1 overflow-y-auto p-4 md:p-6">
                <slot />
            </main>
        </div>
    </div>
</template>
