<template>
<div class="bbn-iblock bbn-radio">
  <div class="bbn-iblock"
       v-for="d in dataSource"
  >
    <input v-bind:value="d.value"
           ref="element"
           :name="name" ref="checkbox"
           class="k-radio"
           type="radio"
           :disabled="disabled ? true : false"
           :required="required ? true : false"
           :id="name + '_' + d.value"
    >
    <label class="k-radio-label"
           v-text="d.text"
           :for="name + \'_\' + d.value"
    ></label>
    <div class="bbn-iblock" v-html="separator"></div>
  </div>
</div>
</template>

<script>
  export default {
    name:'bbn-radio',
    mixins: [bbn.vue.vueComponent],
    props: {
      separator: {
        type: String,
        default: '<span style="margin-left: 2em">&nbsp;</span>'
      },
      cfg: {
        type: Object,
        default: function(){
          return {
            dataTextField: "text",
            dataValueField: "value"
          }
        }
      },
      source: {
        default: function(){
          return [{
            text: bbn._("Yes"),
            value: 1
          }, {
            text: bbn._("No"),
            value: 0
          }];
        }
      },
      label: {
        type: String,
      }
    },
    computed: {
      dataSource: function(){
        if ( this.source ){
          return bbn.vue.transformDataSource(this);
        }
        return [];
      }
    },
  }
</script>
<style></style>
