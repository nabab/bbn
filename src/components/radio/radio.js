/**
 * Created by BBN on 13/02/2017.
 */
(function($, bbn, kendo){
  "use strict";
  Vue.component('bbn-radio', {
    mixins: [bbn.vue.inputComponent, bbn.vue.dataSourceComponent],
    template: '#bbn-tpl-component-radio',
    props: {
      separator: {
        type: String
      },
			vertical: {
				type: Boolean,
				default: false
			},
      source: {
        default(){
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
    data(){
      return {
        checked: this.value,
      }
    },
    model: {
      prop: 'checked',
      event: 'change'
    },
    computed: {
      dataSource(){
        if ( this.source ){
          return bbn.vue.toKendoDataSource(this);
        }
        return [];
      },
			getSeparator(){
				if ( !this.vertical && !this.separator ){
					return '<span style="margin-left: 2em">&nbsp;</span>';
				}
				return this.separator;
			}
    },
		methods: {
			changed(e){

				//this.$emit('check', e.target.value);
			}
		},
    mounted(){
      //this.$emit("ready", this.checked);
    }
  });

})(jQuery, bbn, kendo);
