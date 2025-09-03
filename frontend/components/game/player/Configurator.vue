<script setup>
import {useGameStore} from "~/stores/game.js";

const { $api } = useNuxtApp();

const props = defineProps({
  traits: Array,
})

const gameStore = useGameStore();
const toast = useToast();

const state = reactive({
  playerTraits: {
    loading: false,
    data: {},
    summaryDescription: null,
    dataChange: false,
    summaryDescriptionLoading: false,
    lock: false,
  },

})

const formState = ref({
  name: '',
  characterDescription: '',
})

const handleSubmit = async (event) => {
  state.playerTraits.loading = true;
  const res = $api({
    method: "post",
    url: '/api/game/player/traits/generate',
    body: {
      description: formState.value.characterDescription,
    }
  }).then((res) => {
    state.playerTraits.data = res.traits;
    state.playerTraits.dataChange = false;
    state.playerTraits.summaryDescription = res.summary;
    console.log(res);
    toast.add({title: 'Generování vlastností', description: 'OK', color: 'success'});
  })
      .catch((err) => {
        toast.add({title: 'Generování vlastností', description: err.message, color: 'error'});
      }).finally(() => {
        state.playerTraits.loading = false;
      })
}

const generatePlayerSummaryDescription = async () => {
  if (!state.playerTraits.dataChange) return;

  state.playerTraits.summaryDescriptionLoading = true;
  state.playerTraits.lock = true;
  const res = client.post({
    composable: "$fetch",
    url: '/api/game/player/traits/generate-summary-description',
    body: {
      ...state.playerTraits.data,
    }
  }).then((res) => {
    state.playerTraits.summaryDescription = res.summary;
    state.playerTraits.dataChange = false;
    console.log(res);
    toast.add({title: 'Generování popisu', description: 'OK', color: 'success'});
  }).catch((err) => {
    toast.add({title: 'Generování popisu', description: err.message, color: 'error'});
  }).finally(() => {
    state.playerTraits.summaryDescriptionLoading = false;
    state.playerTraits.lock = false;
  })
}

const textareaRef = ref(null)

const resizeTextArea = () => {
  const el = textareaRef.value
  if (!el) return

  el.style.height = 'auto' // reset výšky
  const scrollHeight = el.scrollHeight

  const maxHeight = 500 // nastav si libovolnou maximální výšku v px
  if (scrollHeight > maxHeight) {
    el.style.height = maxHeight + 'px'
  } else {
    el.style.height = scrollHeight + 'px'
  }
}

onMounted(() => {
  resizeTextArea()
})
</script>

<template>
  <div class="flex gap-12 justify-center items-center h-full">

    <div class="w-[40%]">
      <h1 class="mb-4 text-xl font-bold">Vytvoř si svého hráče</h1>
      <UForm :state="formState" @submit="handleSubmit" class="flex flex-col gap-4">
        <UFormField label="Jméno" name="name" required>
          <UInput v-model="formState.name"/>
        </UFormField>

<!--        <UFormField label="Popis hráče" name="characterDescription" required>-->
<!--          <UTextarea v-model="formState.characterDescription" class="w-full" rows="10"/>-->
<!--        </UFormField>-->

        <div>
          <label for="characterDescription" class="block font-medium text-(--ui-text) after:content-['*'] after:ms-0.5 after:text-(--ui-error) text-sm mb-2">Popis hráče</label>
          <div
              class="flex flex-col items-stretch gap-2 px-2.5 py-2 w-full rounded-[calc(var(--ui-radius)*2)] backdrop-blur bg-(--ui-bg-elevated)/50 ring ring-(--ui-border) sticky bottom-0 [view-transition-name:chat-prompt] z-10">
            <!---->
            <div class="relative inline-flex items-start">
              <textarea
                  ref="textareaRef"
                  @input="resizeTextArea"
                  id="characterDescription"
                  rows="5"
                  placeholder="Zde zadejte slovní popis hráče..."
                  class="w-full rounded-[calc(var(--ui-radius)*1.5)] border-0 placeholder:text-(--ui-text-dimmed) focus:outline-none disabled:cursor-not-allowed disabled:opacity-75 transition-colors px-2.5 py-1.5 gap-1.5 text-(--ui-text-highlighted) bg-transparent resize-none text-base/5"
                  v-model="formState.characterDescription"
              />
            </div>
          </div>
        </div>

        <UButton type="submit" label="Generovat vlastnosti" class="w-46" color="primary" variant="ghost" icon="lucide:wand-sparkles"
                 :disabled="state.playerTraits.loading || state.playerTraits.summaryDescriptionLoading"/>
      </UForm>
    </div>


    <div class="w-[400px]">
      <UCard>
        <template #header>
          <div class="flex justify-between">
            <div>Krátký popis hráče</div>
            <UButton v-if="state.playerTraits.dataChange" icon="i-lucide-refresh-ccw" size="xs"
                     variant="ghost"
                     @click="generatePlayerSummaryDescription"
                     :disabled="state.playerTraits.loading || state.playerTraits.summaryDescriptionLoading"></UButton>
          </div>

        </template>

        <div class="grid gap-2" v-if="state.playerTraits.loading || state.playerTraits.summaryDescriptionLoading">
          <USkeleton class="w-full h-4"/>
          <USkeleton class="w-full h-4"/>
          <USkeleton class="w-full h-4"/>
        </div>
        <div class="text-sm text-center" v-else-if="state.playerTraits.summaryDescription">
          {{ state.playerTraits.summaryDescription }}
        </div>
        <div class="text-sm text-center text-gray-500" v-else>
          Generuj vlastnosti, nebo je nastav ručně a vygeneruj popis hráče
        </div>
      </UCard>

      <GamePlayerTraitsList :lock="state.playerTraits.lock" :traits="props.traits" :strengths="state.playerTraits.data"
                            @update:strength="({ traitKey, value }) => {state.playerTraits.data[traitKey] = value; state.playerTraits.dataChange = true;}"
                            :loading="state.playerTraits.loading"/>
    </div>

  </div>
</template>

<style scoped>

</style>