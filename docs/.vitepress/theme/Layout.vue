<script setup lang="ts">
import DefaultTheme from 'vitepress/theme'
import HeroFlow from './HeroFlow.vue'
import NotFound from './NotFound.vue'
</script>

<template>
  <DefaultTheme.Layout>
    <template #home-hero-image>
      <HeroFlow />
    </template>
    <template #not-found>
      <NotFound />
    </template>
  </DefaultTheme.Layout>
</template>
