<script setup>
const props = defineProps({
  traits: Array,
  strengths: {
    type: Object,
    default: () => {},
  },
  loading: Boolean,
  lock: Boolean,
});

const emit = defineEmits(['update:strength']);

</script>

<template>
  <div>
    <GamePlayerTraitsCard v-for="trait in props.traits" :trait="trait" :strength="props.strengths[trait.key]" @update:strength="val => emit('update:strength', { traitKey: trait.key, value: val })" :loading="loading" :lock="lock" />
  </div>
</template>

<style scoped>

</style>