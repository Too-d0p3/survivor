import { useAuth } from '@/composables/useAuth'
import { client } from '~/types/api/client.gen'
import { useCookie } from '#app'

export default defineNuxtPlugin(async (nuxtApp) => {
  // Tento plugin běží pouze na straně klienta
  if (!process.client) {
    return;
  }

  const { isAuthenticated, user, fetchUser } = useAuth()
  const token = useCookie<string | null>('token')

  // Zkontrolujeme, zda existuje token v cookies
  if (isAuthenticated.value) {
    // Pokud ano, nastavíme konfiguraci pro heyapi klienta
    client.setConfig({
        headers: {
            'Authorization': `Bearer ${token.value}`
        }
    });

    // A pokud data uživatele ještě nejsou v globálním stavu (po F5)
    if (!user.value) {
      await fetchUser();
    }
  } else {
    user.value = null;
    client.setConfig({
        headers: {}
    });
  }
}); 