<script setup>
import { ref } from 'vue'
import { useAuth } from '@/composables/useAuth'

const email = ref('')
const password = ref('')
const { login } = useAuth()

async function handleLogin() {
  const success = await login(email.value, password.value)
  if (success) {
    navigateTo('/')
  } else {
    alert('Přihlášení selhalo.')
  }
}
</script>

<template>
  <form @submit.prevent="handleLogin">
    <UInput v-model="email" placeholder="Email" />
    <UInput v-model="password" type="password" placeholder="Password" />
    <UButton type="submit">Přihlásit se</UButton>
  </form>
</template>
