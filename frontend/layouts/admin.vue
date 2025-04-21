<template>
  <div class="flex h-screen bg-gray-900">
    <!-- Levý Sidebar -->
    <aside class="w-64 bg-gray-800 text-white p-4 flex flex-col">
      <h1 class="text-2xl font-semibold mb-6">Administrace</h1>
      <nav class="flex-grow">
        <ul>
          <li class="mb-2">
            <NuxtLink to="/admin" class="block hover:bg-gray-700 p-2 rounded" active-class="bg-gray-700">Dashboard</NuxtLink>
          </li>
          <li class="mb-2">
            <!-- Přidej další odkazy podle potřeby -->
            <NuxtLink to="/admin/users" class="block hover:bg-gray-700 p-2 rounded" active-class="bg-gray-700">Uživatelé</NuxtLink>
          </li>
          <li class="mb-2">
            <!-- Přidej další odkazy podle potřeby -->
            <NuxtLink to="/admin/traits" class="block hover:bg-gray-700 p-2 rounded" active-class="bg-gray-700">Traits</NuxtLink>
          </li>
          <li class="mb-2">
            <NuxtLink to="/admin/settings" class="block hover:bg-gray-700 p-2 rounded" active-class="bg-gray-700">Nastavení</NuxtLink>
          </li>
        </ul>
      </nav>
      <!-- Můžeš přidat odkaz na profil nebo odhlášení -->
      <div>
        <NuxtLink to="/" class="block hover:bg-gray-700 p-2 rounded" active-class="bg-gray-700">Homepage</NuxtLink>
      </div>
      <div>
        <UButton label="Odhlásit se" color="gray" variant="ghost" class="w-full" @click="handleLogout" />
      </div>
    </aside>

    <!-- Hlavní Obsah -->
    <main class="flex-1 p-6 overflow-y-auto text-white">
      <slot />
    </main>
  </div>
</template>

<script setup>
import { useAuth } from '@/composables/useAuth'

const { logout } = useAuth()

// Přidání metody pro odhlášení přímo z layoutu
async function handleLogout() {
  await logout()
  // Logout funkce v useAuth by už měla přesměrovat, ale pro jistotu:
  // await navigateTo('/login')
}

// Zde může být další logika specifická pro admin layout
</script> 