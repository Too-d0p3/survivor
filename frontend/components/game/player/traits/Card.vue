<script setup>
const props = defineProps({
  trait: Object,
  strength: Number|null,
  loading: Boolean,
  lock: Boolean,
})

const emit = defineEmits(['update:strength']);
</script>

<template>
  <UCard class="p2">
<!--    <div class="flex justify-between">-->
<!--      {{ trait.label }}-->

<!--      <USkeleton v-if="loading" class="w-6 h-6" />-->
<!--      <UBadge v-else>-->
<!--          {{ props.strength !== undefined && props.strength !== null ? Math.round(props.strength * 100) : '?' }}-->
<!--      </UBadge>-->
<!--    </div>-->
      <div class="flex gap-5 items-center">
        <div class="w-32">
          <span class="text-nowrap text-sm">{{ trait.label }}</span>
        </div>

        <div class="grow">
          <USkeleton v-if="loading" class="w-full h-2" />
          <USlider v-else :min="0" :max="100" :model-value="(strength ?? 0) * 100" @update:model-value="val => emit('update:strength', val / 100)" :disabled="props.lock" size="xs"/>
        </div>

        <div class="w-9 text-right">
          <USkeleton v-if="loading" class="w-full h-6" />
          <UBadge variant="subtle" class="w-full justify-end" v-else>
            {{ props.strength !== undefined && props.strength !== null ? Math.round(props.strength * 100) : '?' }}
          </UBadge>
        </div>
      </div>

  </UCard>
</template>

<style scoped>

</style>