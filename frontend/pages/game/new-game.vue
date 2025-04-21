<script setup>
import {useGameStore} from "~/stores/game.js";
import {apiTraitDefsGetCollection} from '~/types/api';

const gameStore = useGameStore();

const {status, data: traits, refresh} = await apiTraitDefsGetCollection({
  composable: "useFetch"
});

</script>

<template>
  <GamePlayerConfigurator v-if="traits.member" :traits="traits.member" />
</template>

<style scoped>

</style>