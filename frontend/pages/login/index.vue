<script setup>
</script>

<template>
  <div class="flex flex-col items-center justify-center min-h-screen">
    <h1 class="text-2xl font-bold mb-4">Přihlášení</h1>
    <LoginForm />
    <p class="mt-4">
      Nemáte účet?
      <NuxtLink to="/login/register" class="text-blue-500 hover:underline">Registrujte se</NuxtLink>
    </p>
  </div>
</template>

