<script setup>
import {useGameStore} from "~/stores/game.js";

const { $api } = useNuxtApp()

const gameStore = useGameStore();

const traits = await $api('/trait-def');
console.log(traits);
debugger;

</script>

<template>
  <GamePlayerConfigurator v-if="traits.member" :traits="traits.member" />
</template>

<style scoped>

</style>