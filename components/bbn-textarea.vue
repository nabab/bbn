<template>
<textarea class="k-textbox"
          v-bind:value="value"
          :name="name"
          ref="element"
          v-on:input="update($event.target.value)"
          v-on:click="click($event)"
          v-on:focus="focus($event)"
          v-on:blur="blur($event)"
          v-on:change="change($event)"
          v-on:keydown="keydown($event)"
          v-on:keyup="keyup($event)"
          :disabled="!!disabled"
          :required="!!required"
          :placeholder="placeholder"
          :maxlength="maxlength"
          :rows="rows"
          :cols="cols"
></textarea>
</template>

<script>
  export default {
    name:'bbn-textarea',
    mixins: [bbn.vue.vueComponent],
    props: {
			rows: {
				type: Number
			},
			cols: {
				type: Number
			},
			maxlength: {
				type: Number
			},
      cfg:{
				type: Object,
        default: function(){
          return {}
				}
			}
    },
    methods: {
      clear: function(){
        this.update('');
      }
    },
    data: function(){
      return $.extend({
        widgetName: "textarea",
      }, bbn.vue.treatData(this));
    },
    mounted: function(){
      var vm = this,
          $ele = $(vm.$el),
          cfg = vm.getOptions();

      if ( this.disabled ){
        $ele.addClass("k-state-disabled");
      }
    }
  }
</script>
<style></style>
