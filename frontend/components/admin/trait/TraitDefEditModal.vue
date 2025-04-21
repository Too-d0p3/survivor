<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { z } from 'zod';
import type { FormSubmitEvent } from '#ui/types';
import { apiTraitDefsIdPatch, apiTraitDefsPost } from '@/types/api/index'; // Import pro TraitDef
import type { TraitDefJsonldReadable, TraitDefWritable,  TraitDefJsonldWritable } from '@/types/api/types.gen'; // Typy pro TraitDef

// Props
const props = defineProps<{
  traitDefToEdit?: TraitDefJsonldReadable | null; // TraitDef pro editaci, null/undefined pro vytvoření
}>();

// Emits
const emit = defineEmits<{ (e: 'saved'): void }>();

// Model pro ovládání otevření/zavření z venku
const isOpen = defineModel<boolean>('open', { required: true });

// Notifikace
const toast = useToast();

// Určení módu (editace nebo vytváření)
const isEditMode = computed(() => !!props.traitDefToEdit);

// ----- Schéma a Stav Formuláře -----

// Možné typy traitů (získané ideálně z API nebo konstanty)
const traitTypes = [
  { value: 'social', label: 'Social' },
  { value: 'strategic', label: 'Strategic' },
  { value: 'emotional', label: 'Emotional' },
  { value: 'physical', label: 'Physical' },
];

// Schéma pro formulář (společné pro create i edit, ale PATCH pošle jen změněné)
const traitDefSchema = z.object({
  key: z.string().min(1, 'Klíč je povinný').regex(/^[a-z0-9_]+$/, 'Klíč může obsahovat pouze malá písmena, čísla a podtržítka'),
  label: z.string().min(1, 'Název je povinný'),
  description: z.string().optional(),
  type: z.enum(['social', 'strategic', 'emotional', 'physical'], { errorMap: () => ({ message: 'Vyberte platný typ' })}),
});

type FormSchemaOutput = z.output<typeof traitDefSchema>;

// Reaktivní stav formuláře
const formState = ref<Partial<FormSchemaOutput>>({});

// ----- Logika -----

// Funkce pro reset a naplnění formuláře
function initializeForm() {
  if (isEditMode.value && props.traitDefToEdit) {
    formState.value = {
      key: props.traitDefToEdit.key,
      label: props.traitDefToEdit.label,
      description: props.traitDefToEdit.description ?? undefined,
      type: props.traitDefToEdit.type,
    };
  } else {
    formState.value = { key: '', label: '', description: '', type: undefined }; // Reset
  }
}

// Sledování změn a otevření modálu pro inicializaci formu
watch(() => [props.traitDefToEdit, isOpen.value], () => {
  if (isOpen.value) {
    initializeForm();
  }
}, { immediate: true });

// Handler pro odeslání formuláře
async function handleSubmit(event: FormSubmitEvent<FormSchemaOutput>) {
  try {
    if (isEditMode.value && props.traitDefToEdit?.id) {
      // --- EDITACE (PATCH) ---
      const traitDefId = String(props.traitDefToEdit.id);
      // Připravíme tělo s explicitně upraveným typem description
      const requestBody: Ref<TraitDefWritable> = toRef({
         key: event.data.key,
         label: event.data.label,
         description: (event.data.description ?? undefined) as string,
         type: event.data.type,
      });

      await apiTraitDefsIdPatch({
        composable: "$fetch",
        path: { id: traitDefId },
        // Vrátíme explicitní přetypování, ale na objektu s upraveným description
        body: requestBody,
        headers: {
          'Content-Type': 'application/merge-patch+json',
          'Accept': 'application/ld+json'
        }
      });
      toast.add({ title: 'Vlastnost úspěšně upravena.', color: 'success' });
    } else {
      // --- VYTVOŘENÍ (POST) ---
      // Explicitně definujeme objekt s typem description jako string | undefined
      const postBody: Ref<TraitDefJsonldWritable> = toRef({
        key: event.data.key!,
        label: event.data.label!,
        description: event.data.description ?? undefined,
        type: event.data.type!,
      });

      await apiTraitDefsPost({
        composable: "$fetch",
        body: postBody,
        headers: {
           'Content-Type': 'application/ld+json',
           'Accept': 'application/ld+json'
        }
      });
      toast.add({ title: 'Vlastnost úspěšně vytvořena.', color: 'success' });
    }

    isOpen.value = false; // Zavři modál
    emit('saved');       // Emituj událost pro refresh tabulky

  } catch (error: any) {
    console.error('Chyba při ukládání vlastnosti:', error);
    const errorDetails = error.data?.detail || String(error);
    toast.add({ title: `Nepodařilo se ${isEditMode.value ? 'upravit' : 'vytvořit'} vlastnost.`, description: errorDetails, color: 'error' });
  }
}
</script>

<template>
  <UModal v-model:open="isOpen">
    <!-- Použití slotů modálu -->
    <template #header>
      <h3 class="text-lg font-semibold">
        {{ isEditMode ? `Upravit vlastnost ${traitDefToEdit?.label}` : 'Vytvořit novou vlastnost' }}
      </h3>
    </template>

    <template #body>
      <!-- UCard pro obsah formuláře uvnitř body slotu -->
      <UCard>
        <UForm :schema="traitDefSchema" :state="formState" @submit="handleSubmit" class="space-y-4">
          <UFormField label="Klíč (unikátní)" name="key" required>
            <UInput v-model="formState.key" :disabled="isEditMode" />
          </UFormField>
          <UFormField label="Název (Label)" name="label" required>
            <UInput v-model="formState.label" />
          </UFormField>
          <UFormField label="Popis" name="description">
            <UTextarea v-model="formState.description" />
          </UFormField>
          <UFormField label="Typ" name="type" required>
            <USelectMenu
              v-model="formState.type"
              :items="traitTypes"
              value-key="value"
              placeholder="Vyberte typ..."
            />
          </UFormField>

          <div class="flex justify-end space-x-2 pt-4">
            <UButton label="Zrušit" color="neutral" variant="ghost" @click="isOpen = false" />
            <UButton type="submit" :label="isEditMode ? 'Uložit změny' : 'Vytvořit vlastnost'" color="primary" />
          </div>
        </UForm>
      </UCard>
    </template>
    
    <!-- Můžeš přidat i #footer slot, pokud bys chtěl tlačítka oddělit -->
    <!-- 
    <template #footer>
       <div class="flex justify-end space-x-2">
         <UButton label="Zrušit" color="neutral" variant="ghost" @click="isOpen = false" />
         <UButton type="submit" form="trait-form-id" :label="isEditMode ? 'Uložit změny' : 'Vytvořit vlastnost'" color="primary" />
       </div>
    </template>
    -->
  </UModal>
</template> 