<script setup lang="ts">
const { logout, user } = useAuth()

onMounted(async () => {
  console.log('user',user.value);
})

</script>

<template>
  <div>
    {{user?.email}}
    <h1>Vítejte na hlavní stránce!</h1>
    <NuxtLink to="/game/new-game" ><UButton>Vytvořit hru</UButton></NuxtLink>

    <UButton @click="logout">Odhlásit se</UButton>
  </div>
</template>

