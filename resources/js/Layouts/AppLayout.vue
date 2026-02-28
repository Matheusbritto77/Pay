<script setup>
import { ref, onMounted, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import Banner from '@/Components/Banner.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import UserProfileModal from '@/Components/UserProfileModal.vue';
import TeamsModal from '@/Components/TeamsModal.vue';

defineProps({
    title: String,
});

const showingNavigationDropdown = ref(false);
const showingUserMenuModal = ref(false);
const showingTeamsModal = ref(false);

const isDark = ref(localStorage.getItem('theme') === 'dark');

const toggleDarkMode = () => {
    isDark.value = !isDark.value;
    localStorage.setItem('theme', isDark.value ? 'dark' : 'light');
    updateTheme();
};

const updateTheme = () => {
    if (isDark.value) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
};

onMounted(() => {
    updateTheme();
});

const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <div class="min-h-screen bg-gray-50 dark:bg-slate-950 flex transition-colors duration-300">
        <Head :title="title" />

        <Banner />

        <!-- Sidebar overlay for mobile -->
        <div v-show="showingNavigationDropdown" class="fixed inset-0 z-40 lg:hidden" @click="showingNavigationDropdown = false">
            <div class="fixed inset-0 bg-gray-600 opacity-75 dark:bg-black/60" />
        </div>

        <!-- Sidebar -->
        <aside
            :class="showingNavigationDropdown ? 'translate-x-0' : '-translate-x-full'"
            class="fixed inset-y-0 left-0 z-50 w-64 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-0 lg:flex lg:flex-col shrink-0 border-r border-gray-200 dark:border-slate-800 shadow-sm"
        >
            <!-- Logo area -->
            <div class="flex items-center justify-between h-16 px-6 bg-gray-50 dark:bg-slate-950/50 border-b border-gray-200 dark:border-slate-800">
                <Link :href="route('dashboard')" class="flex items-center gap-2">
                    <ApplicationMark class="block h-8 w-auto text-indigo-600 dark:text-white" />
                    <span class="text-slate-900 dark:text-white font-bold text-lg tracking-tight">ERP Admin</span>
                </Link>
                <button class="lg:hidden p-1 rounded-md hover:bg-gray-100 dark:hover:bg-slate-800" @click="showingNavigationDropdown = false">
                    <svg class="size-6 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Navigation Links -->
            <nav class="flex-1 overflow-y-auto py-6 px-4 space-y-2">
                <div class="flex items-center justify-between px-2 mb-2">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Menu Principal</span>
                    <!-- Theme Toggle Button -->
                    <button 
                        @click="toggleDarkMode" 
                        class="p-1.5 rounded-lg bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700 text-slate-500 dark:text-slate-400 transition-colors"
                        :title="isDark ? 'Tema Claro' : 'Tema Escuro'"
                    >
                        <svg v-if="isDark" class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                        </svg>
                        <svg v-else class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                        </svg>
                    </button>
                </div>
                
                <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
                    <template #default="{ active }">
                        <svg :class="active ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 group-hover:text-slate-600 dark:group-hover:text-slate-200'" class="size-5 shrink-0 transition-colors" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                        </svg>
                        <span class="font-medium">Dashboard</span>
                    </template>
                </NavLink>

                <NavLink :href="route('connections')" :active="route().current('connections')">
                    <template #default="{ active }">
                        <svg :class="active ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 group-hover:text-slate-600 dark:group-hover:text-slate-200'" class="size-5 shrink-0 transition-colors" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m9.07-9.07l4.5-4.5a4.5 4.5 0 016.364 6.364l-1.757 1.757" />
                        </svg>
                        <span class="font-medium">Connections</span>
                    </template>
                </NavLink>

                <NavLink :href="route('crm')" :active="route().current('crm')">
                    <template #default="{ active }">
                        <svg :class="active ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 group-hover:text-slate-600 dark:group-hover:text-slate-200'" class="size-5 shrink-0 transition-colors" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6" />
                        </svg>
                        <span class="font-medium">CRM</span>
                    </template>
                </NavLink>

                <NavLink :href="route('crm.payments.index')" :active="route().current('crm.payments.index')">
                    <template #default="{ active }">
                        <svg :class="active ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 group-hover:text-slate-600 dark:group-hover:text-slate-200'" class="size-5 shrink-0 transition-colors" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="font-medium">Pagamentos</span>
                    </template>
                </NavLink>

                <!-- Teams Management Trigger -->
                <div v-if="$page.props.jetstream.hasTeamFeatures" class="pt-6">
                    <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider px-2 mb-2">Equipe</div>
                    <div class="px-2">
                        <button 
                            @click="showingTeamsModal = true"
                            class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 dark:bg-slate-800/50 hover:bg-gray-100 dark:hover:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700 transition group"
                        >
                            <span class="truncate text-sm font-medium text-slate-700 dark:text-slate-200 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">{{ $page.props.auth.user.current_team.name }}</span>
                            <svg class="size-4 text-slate-400 group-hover:text-indigo-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                            </svg>
                        </button>
                    </div>
                </div>
            </nav>

            <!-- User profile area -->
            <div class="p-4 bg-gray-50 dark:bg-slate-950/30 border-t border-gray-200 dark:border-slate-800">
                <button 
                    @click="showingUserMenuModal = true"
                    class="w-full flex items-center gap-3 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 transition group text-left"
                >
                    <img v-if="$page.props.jetstream.managesProfilePhotos" class="size-9 rounded-full object-cover border-2 border-transparent group-hover:border-indigo-500 dark:group-hover:border-indigo-400 transition" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name">
                    <div v-else class="size-9 rounded-full bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center font-bold text-indigo-700 dark:text-indigo-300 text-xs border-2 border-transparent group-hover:border-indigo-500 transition">
                        {{ $page.props.auth.user.name.charAt(0) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-slate-800 dark:text-white truncate transition-colors">{{ $page.props.auth.user.name }}</p>
                        <p class="text-xs text-slate-500 truncate">{{ $page.props.auth.user.email }}</p>
                    </div>
                </button>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
            <!-- Mobile header -->
            <header class="lg:hidden h-16 bg-white dark:bg-slate-900 border-b border-gray-200 dark:border-slate-800 flex items-center justify-between px-4 shrink-0 transition-colors duration-300">
                <button class="p-2 -ms-2 rounded-md hover:bg-gray-100 dark:hover:bg-slate-800" @click="showingNavigationDropdown = true">
                    <svg class="size-6 text-gray-500 dark:text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>
                <Link :href="route('dashboard')">
                    <ApplicationMark class="block h-8 w-auto text-indigo-600 dark:text-white" />
                </Link>
                <button @click="toggleDarkMode" class="p-2 rounded-md hover:bg-gray-100 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400">
                    <svg v-if="isDark" class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                    </svg>
                    <svg v-else class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                    </svg>
                </button>
            </header>

            <!-- Page Heading -->
            <header v-if="$slots.header" class="bg-white dark:bg-slate-900 border-b border-gray-100 dark:border-slate-800 transition-colors duration-300">
                <div class="max-w-screen-2xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto">
                <div class="max-w-screen-2xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
                    <slot />
                </div>
            </main>
        </div>

        <!-- User Profile Modal Component -->
        <UserProfileModal :show="showingUserMenuModal" @close="showingUserMenuModal = false" />

        <!-- Teams Modal Component -->
        <TeamsModal :show="showingTeamsModal" @close="showingTeamsModal = false" />
    </div>
</template>
