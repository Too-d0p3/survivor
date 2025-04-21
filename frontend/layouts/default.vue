<template>
  <div class="fixed inset-0 overflow-hidden">
    <NuxtLink to="/" class="text-blue-500 hover:underline">Homepage</NuxtLink>
    <NuxtLink to="/admin" class="text-blue-500 hover:underline">Administrace</NuxtLink>
    <slot />
  </div>
</template>

<script setup>
// Zde může být logika specifická pro layout
</script> 