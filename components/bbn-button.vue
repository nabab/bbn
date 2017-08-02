<template>
<button class="k-button" ref="button" v-on:click="click($event)" :type="type" :disabled="disabled ? true : false"><i v-if="icon" :class="icon"> </i><slot></slot></button>
    </template>
<script>
 export default {
  name:'bbn-button',
  mixins:[bbn.vue.vueComponent],
  props: {
      icon: {
        type: String,
      },
      type: {
        type: String,
      },
      disabled: {}
    },
  }
</script>
<style>
</style>
