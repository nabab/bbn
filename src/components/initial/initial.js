/**
 * Created by BBN on 28/03/2017.
 */
(function($, bbn){
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  Vue.component('bbn-initial', {
    mixins: [bbn.vue.optionComponent],
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
        default: function(){
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
    data: function(){
      var vm = this;
      return $.extend({
        users: bbn.users ? bbn.users : []
      }, bbn.vue.treatData(vm))
    },
    render: function(createElement){
      var vm = this,
          opt = {};
      if ( vm.userName || vm.name ){
        opt.attrs = {
          title: vm.userName ? vm.userName : vm.name
        };
      }
      return createElement('img', opt);
    },
    methods: {
      getOptions: function(){
        var vm = this,
            cfg = bbn.vue.getOptions(vm);
        if ( cfg.letters ){
          cfg.charCount = cfg.letters.length;
        }
        else if ( bbn.users ){
          var name = cfg.userName ? cfg.userName : false;
          if ( !name && cfg.userId ){
            name = bbn.fn.get_field(bbn.users, "value", cfg.userId, "text");
          }
          if ( name ){
            var tmp = bbn.fn.removeEmpty(name.split(" ")),
                max = cfg.charCount || 3;
            if ( (tmp.length > max) && (tmp[0].length <= 3) ){
              tmp.shift();
            }
            cfg.letters = '';
            for ( var i = 0; i < tmp.length; i++ ){
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
          var baseSize = cfg.height / cfg.charCount;
          cfg.fontSize = Math.round(baseSize + bbn.fn.percent(15*cfg.charCount, baseSize));
        }
        if ( !cfg.name ){
          cfg.name = cfg.letters;
        }
        return cfg;
      }
    },
    mounted: function(){
      var vm = this,
          cfg = vm.getOptions();
      $(vm.$el).initial(cfg);
    },
  });

})(jQuery, bbn);
