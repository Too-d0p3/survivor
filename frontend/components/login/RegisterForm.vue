<script setup>
import { ref } from 'vue'

const email = ref('')
const password = ref('')

async function handleRegister() {
  try {
    await $fetch('/api/register', {
      method: 'POST',
      body: { email: email.value, password: password.value },
    })
    alert('Registrace úspěšná. Nyní se můžete přihlásit.')
    navigateTo('/login')
  } catch (error) {
    console.error('Registrace selhala:', error)
    alert('Registrace selhala.')
  }
}
</script>

<template>
  <form @submit.prevent="handleRegister">
    <UInput v-model="email" placeholder="Email" />
    <UInput v-model="password" type="password" placeholder="Password" />
    <UButton type="submit">Registrovat se</UButton>
  </form>
</template>
