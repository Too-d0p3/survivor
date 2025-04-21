<script setup>

</script>

<template>
  <div class="flex flex-col items-center justify-center min-h-screen">
    <h1 class="text-2xl font-bold mb-4">Registrace</h1>
    <LoginRegisterForm />
    <p class="mt-4">
      Máte již účet?
      <NuxtLink to="/login" class="text-blue-500 hover:underline">Přihlaste se</NuxtLink>
    </p>
  </div>
</template>

