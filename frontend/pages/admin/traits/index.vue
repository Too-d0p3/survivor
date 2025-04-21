<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import { apiTraitDefsGetCollection } from '@/types/api/index';
import type { TraitDefJsonldReadable } from '@/types/api/types.gen';
import { ref } from 'vue';

const { status, data, refresh } = await apiTraitDefsGetCollection({
  composable: "useFetch"
});

// Stavy pro ovládání modálu
const isModalOpen = ref(false);
const editingTraitDef = ref<TraitDefJsonldReadable | null>(null); // null pro vytvoření

// Funkce pro otevření modálu v režimu editace
function openEditModal(traitDef: TraitDefJsonldReadable) {
  editingTraitDef.value = traitDef; // Nastavíme trait pro editaci
  isModalOpen.value = true;
}

// Funkce pro otevření modálu v režimu vytváření
function openCreateModal() {
  editingTraitDef.value = null; // Žádný trait = vytváření
  isModalOpen.value = true;
}

// Funkce volaná po úspěšném uložení v modálu
function onModalSaved() {
  refresh(); // Obnovíme tabulku
}

// Definice sloupců (opraveno podle vzoru z users/index.vue)
const columns: TableColumn<TraitDefJsonldReadable>[] = [
  { accessorKey: 'id', header: 'ID' },
  { accessorKey: 'key', header: 'Klíč' },
  { accessorKey: 'label', header: 'Název' },
  { accessorKey: 'type', header: 'Typ' },
  { accessorKey: 'description', header: 'Popis' },
  { id: 'actions', header: 'Akce' }
];

</script>

<template>
  <div class="flex flex-col flex-1">
    <div class="flex justify-end mb-4">
      <UButton label="Vytvořit vlastnost" icon="i-lucide-plus" @click="openCreateModal" />
    </div>

    <UTable
      :data="data?.member" 
      :columns="columns"
      :loading="status === 'pending'"
      class="flex-1"
      :sort="{ column: 'id', direction: 'asc' }"
    >
      <template #type-cell="{ row }">
        <UBadge :label="row.original.type" variant="subtle" />
      </template>
      
      <template #actions-cell="{ row }">
        <UButton
          icon="i-lucide-edit"
          variant="ghost"
          color="neutral"
          class="mr-2"
          :disabled="!row.original.id"
          @click="row.original.id ? openEditModal(row.original) : null"
        />
      </template>
    </UTable>

    <!-- Použití nové komponenty modálu -->
    <AdminTraitDefEditModal 
      v-model:open="isModalOpen" 
      :trait-def-to-edit="editingTraitDef" 
      @saved="onModalSaved"
    />

  </div>
</template> 