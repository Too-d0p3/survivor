<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import { apiUsersGetCollection } from '@/types/api/index';
import type { UserJsonldReadable } from '@/types/api/types.gen';
import { ref } from 'vue';

const {status, data, refresh} = await apiUsersGetCollection({
  composable: "useFetch"
});

const isModalOpen = ref(false);
const editingUser = ref<UserJsonldReadable | null>(null);

function openEditModal(userId: string | number) {
  const userToEdit = data.value?.member?.find(u => String(u.id) === String(userId));
  if (!userToEdit) {
    console.error('User not found for editing:', userId);
    return;
  }
  editingUser.value = userToEdit;
  isModalOpen.value = true;
}

function openCreateModal() {
  editingUser.value = null;
  isModalOpen.value = true;
}

const columns: TableColumn<UserJsonldReadable>[] = [
  {
    accessorKey: 'id',
    header: 'ID',
  },
  {
    accessorKey: 'email',
    header: 'Email',
  },
  {
    id: 'actions',
    header: 'Akce'
  }
];

function onModalSaved() {
  refresh();
}
</script>

<template>
  <div class="flex flex-col flex-1">
    <div class="flex justify-end mb-4">
      <UButton label="Vytvořit uživatele" icon="i-lucide-plus" @click="openCreateModal" />
    </div>

    <UTable
      :data="data?.member" 
      :columns="columns"
      :loading="status === 'pending'"
      class="flex-1"
    >
      <template #actions-cell="{ row }">
        <UButton
          icon="i-lucide-edit"
          variant="ghost"
          color="neutral"
          class="mr-2"
          :disabled="!row.original.id"
          @click="row.original.id ? openEditModal(row.original.id) : null"
        />
        <UButton
          icon="i-lucide-trash-2"
          variant="ghost"
          color="error"
        />
      </template>
    </UTable>

    <AdminUserEditModal 
      v-model:open="isModalOpen" 
      :user-to-edit="editingUser" 
      @saved="onModalSaved"
    />
  </div>
</template>
