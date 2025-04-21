<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { z } from 'zod';
import type { FormSubmitEvent } from '#ui/types';
import { apiUsersIdPatch, apiUsersPost } from '~/types/api'; // Import pro PATCH i POST
import type { UserJsonldReadable, UserWritable } from '@/types/api/types.gen';

// Props
const props = defineProps<{
  userToEdit?: UserJsonldReadable | null; // User pro editaci, null/undefined pro vytvoření
}>();

// Emits
const emit = defineEmits<{ (e: 'saved'): void }>();

// Model pro ovládání otevření/zavření z venku
const isOpen = defineModel<boolean>('open', { required: true });

// Notifikace
const toast = useToast();

// Určení módu (editace nebo vytváření)
const isEditMode = computed(() => !!props.userToEdit);

// ----- Schémata a Stav Formuláře -----

// Společné schéma pro email
const emailSchema = z.string().email('Neplatný formát emailu').min(1, 'Email je povinný');

// Schéma pro Vytvoření
const createUserSchema = z.object({
  email: emailSchema,
  password: z.string().min(6, 'Heslo musí mít alespoň 6 znaků'),
});

// Schéma pro Editaci
const editUserSchema = z.object({
  email: emailSchema,
});

// Dynamické schéma podle módu
const currentSchema = computed(() => isEditMode.value ? editUserSchema : createUserSchema);

// Typy pro formulářová data
type EditSchemaOutput = z.output<typeof editUserSchema>;
type CreateSchemaOutput = z.output<typeof createUserSchema>;
type FormSchemaOutput = EditSchemaOutput | CreateSchemaOutput;

// Reaktivní stav formuláře
const formState = ref<Partial<CreateSchemaOutput>>({ email: '', password: '' });

// ----- Logika -----

// Funkce pro reset a naplnění formuláře při změně props nebo otevření
function initializeForm() {
  if (isEditMode.value && props.userToEdit) {
    formState.value = { email: props.userToEdit.email || '' };
  } else {
    formState.value = { email: '', password: '' };
  }
}

// Sledování změn uživatele a otevření modálu pro inicializaci formu
watch(() => [props.userToEdit, isOpen.value], () => {
  if (isOpen.value) {
    initializeForm();
  }
}, { immediate: true });

// Handler pro odeslání formuláře
async function handleSubmit(event: FormSubmitEvent<FormSchemaOutput>) {
  try {
    let response;
    if (isEditMode.value && props.userToEdit?.id) {
      // --- EDITACE (PATCH) ---
      const userId = String(props.userToEdit.id);
      const requestBody: Partial<UserWritable> = {
        email: (event.data as EditSchemaOutput).email, // Bereme jen email
      };
      response = await apiUsersIdPatch({
        composable: "$fetch",
        path: { id: userId },
        body: requestBody,
        headers: {
          'Content-Type': 'application/merge-patch+json',
          'Accept': 'application/ld+json'
        }
      });
      toast.add({ title: 'Uživatel úspěšně upraven.', color: 'success' });
    } else {
      // --- VYTVOŘENÍ (POST) ---
      const requestBody: UserWritable = {
        email: (event.data as CreateSchemaOutput).email,
        password: (event.data as CreateSchemaOutput).password, // API Platform často očekává 'plainPassword'
        // Můžeš přidat defaultní role nebo jiná pole podle potřeby API
        roles: ['ROLE_USER']
      };
      response = await apiUsersPost({
        composable: "$fetch",
        body: requestBody,
         headers: {
           // POST obvykle očekává 'application/ld+json' nebo 'application/json'
           'Content-Type': 'application/ld+json', 
           'Accept': 'application/ld+json'
        }
      });
      toast.add({ title: 'Uživatel úspěšně vytvořen.', color: 'success' });
    }

    isOpen.value = false; // Zavři modál
    emit('saved');       // Emituj událost pro refresh tabulky

  } catch (error: any) {
    console.error('Chyba při ukládání uživatele:', error);
    // Pokus o získání detailnější chybové zprávy z API Platform (pokud existuje)
    const errorDetails = error.data?.detail || String(error);
    toast.add({ title: `Nepodařilo se ${isEditMode.value ? 'upravit' : 'vytvořit'} uživatele.`, description: errorDetails, color: 'error' });
  }
}
</script>

<template>
  <UModal v-model:open="isOpen">
    
      <template #header>
        <h3 class="text-lg font-semibold">
          {{ isEditMode ? `Upravit uživatele ${userToEdit?.email}` : 'Vytvořit nového uživatele' }}
        </h3>
      </template>

      <template #body>
        <UCard>
            <UForm :schema="currentSchema" :state="formState" @submit="handleSubmit" class="space-y-4">
                <UFormField label="Email" name="email" required>
                    <UInput v-model="formState.email" type="email" />
                </UFormField>

                <UFormField v-if="!isEditMode" label="Heslo" name="password" required>
                    <UInput v-model="formState.password" type="password" />
                </UFormField>


            </UForm>
        </UCard>
      </template>

      <template #footer>
            <UButton label="Zrušit" color="neutral" variant="ghost" @click="isOpen = false" />
            <UButton type="submit" :label="isEditMode ? 'Uložit změny' : 'Vytvořit uživatele'" color="primary" />
      </template>

  </UModal>
</template> 