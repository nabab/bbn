/**
 * Created by BBN on 28/03/2017.
 */
(function($, bbn){
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  Vue.component('bbn-initial', {
    mixins: [bbn.vue.basicComponent, bbn.vue.optionComponent],
    props: {
      userId: {},
      userName: {},
      email: {},
      width: {},
      height: {},
      charCount: {},
      textColor: {},
      color: {},
      fontSize: {},
      fontWeight: {},
      letters: {},
      radius: {},
      cfg: {
        type: Object,
        default(){
          return {
            width: 40,
            height: 40,
            charCount: 0,
            textColor: '#FFFFFF',
            letters: null,
            color: null,
            fontSize: null,
            fontWeight: {},
            radius: 0,
          };
        }
      }
    },
    data(){
      return $.extend({
        users: bbn.users ? bbn.users : []
      }, bbn.vue.treatData(this))
    },
    render(createElement){
      let opt = {
        'class': this.componentClass
      };
      if ( this.userName || this.name ){
        opt.attrs = {
          title: this.userName ? this.userName : this.name
        };
      }
      return createElement('img', opt);
    },
    methods: {
      getOptions(){
        let cfg = bbn.vue.getOptions(this);
        if ( cfg.letters ){
          cfg.charCount = cfg.letters.length;
        }
        else if ( bbn.users ){
          let name = cfg.userName ? cfg.userName : false;
          if ( !name && cfg.userId ){
            name = bbn.fn.get_field(bbn.users, "value", cfg.userId, "text");
          }
          if ( name ){
            let tmp = bbn.fn.removeEmpty(name.split(" ")),
                max = cfg.charCount || 3;
            if ( (tmp.length > max) && (tmp[0].length <= 3) ){
              tmp.shift();
            }
            cfg.letters = '';
            for ( let i = 0; i < tmp.length; i++ ){
              if ( !cfg.charCount || (cfg.letters.length <= cfg.charCount) ){
                cfg.letters += tmp[i].substr(0, 1);
              }
            }
          }
        }
        if ( !cfg.letters ){
          cfg.letters = '??';
        }
        if ( !cfg.charCount ){
          cfg.charCount = cfg.letters.length;
        }
        if ( !cfg.fontSize ){
          let baseSize = cfg.height / cfg.charCount;
          cfg.fontSize = Math.round(baseSize + bbn.fn.percent(15*cfg.charCount, baseSize));
        }
        if ( !cfg.name ){
          cfg.name = cfg.letters;
        }
        return cfg;
      }
    },
    mounted(){
      $(this.$el).initial(this.getOptions());
    },
  });

})(jQuery, bbn);
