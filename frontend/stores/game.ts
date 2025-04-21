import { defineStore } from 'pinia'

export const useGameStore = defineStore('game', () => {
    const traitDefs = ref(null);

    const setTraitDefs = (newTraitDefs: any) => {
        traitDefs.value = newTraitDefs;
    }

    return {
        traitDefs,
        setTraitDefs,
    }
});