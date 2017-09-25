/**
 * Created by BBN on 07/01/2017.
 */
;(function($, bbn, kendo){
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  Vue.component('bbn-loading', {
    template: '#bbn-tpl-component-loading',
    props: {
      encoded: {
        type: Boolean,
        default: true
      },
      position: {
        type: Object,
        default: function(){
          return {
            position: {
              bottom: 5,
              right: 5
            }
          };
        }
      },
      history: {
        type: Number,
        default: 100
      },
      loadIcon: {
        type: String,
        default: '<svg xmlns:svg="http://www.w3.org/2000/svg" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.0" width="' + 16 + 'px" height="' + 16 + 'px" viewBox="0 0 128 128" xml:space="preserve"><g><path d="M59.6 0h8v40h-8V0z" fill="#000000" fill-opacity="1"/><path d="M59.6 0h8v40h-8V0z" fill="#cccccc" fill-opacity="0.2" transform="rotate(30 64 64)"/><path d="M59.6 0h8v40h-8V0z" fill="#cccccc" fill-opacity="0.2" transform="rotate(60 64 64)"/><path d="M59.6 0h8v40h-8V0z" fill="#cccccc" fill-opacity="0.2" transform="rotate(90 64 64)"/><path d="M59.6 0h8v40h-8V0z" fill="#cccccc" fill-opacity="0.2" transform="rotate(120 64 64)"/><path d="M59.6 0h8v40h-8V0z" fill="#b2b2b2" fill-opacity="0.3" transform="rotate(150 64 64)"/><path d="M59.6 0h8v40h-8V0z" fill="#999999" fill-opacity="0.4" transform="rotate(180 64 64)"/><path d="M59.6 0h8v40h-8V0z" fill="#7f7f7f" fill-opacity="0.5" transform="rotate(210 64 64)"/><path d="M59.6 0h8v40h-8V0z" fill="#666666" fill-opacity="0.6" transform="rotate(240 64 64)"/><path d="M59.6 0h8v40h-8V0z" fill="#4c4c4c" fill-opacity="0.7" transform="rotate(270 64 64)"/><path d="M59.6 0h8v40h-8V0z" fill="#333333" fill-opacity="0.8" transform="rotate(300 64 64)"/><path d="M59.6 0h8v40h-8V0z" fill="#191919" fill-opacity="0.9" transform="rotate(330 64 64)"/><animateTransform attributeName="transform" type="rotate" values="0 64 64;30 64 64;60 64 64;90 64 64;120 64 64;150 64 64;180 64 64;210 64 64;240 64 64;270 64 64;300 64 64;330 64 64" calcMode="discrete" dur="1080ms" repeatCount="indefinite"></animateTransform></g></svg>'
      }
    },
    data: function(){
      return {
        isLoading: false,
        isSuccess: false,
        isError: false,
        text: '',
        id: false,
        data: [],
        selected: 0,
        numLoaded: 0
      };
    },

    methods: {
      start: function(url, id){
        this.data.unshift({
          text: url,
          isLoading: true,
          isError: false,
          isSuccess: false,
          isPage: false,
          id: this.setId(id),
          time: (new Date()).getTime()
        });
        this.numLoaded++;
        if ( this.selected ){
          this.selected++;
        }
        if ( this.data.length >= this.history ){
          this.data.pop();
        }
      },

      end: function(url, id, data, res){
        let idx = bbn.fn.search(this.data, "id", id);
        if ( idx > -1 ){
          this.data.splice(idx, 1, $.extend(this.data[idx], {
            isLoading: false,
            isError: typeof(res) === 'string',
            isSuccess: typeof(res) !== 'string',
            isPage: (typeof(res) === 'object') && !!res.content && !!res.title,
            error: typeof(res) === 'string' ? res : false,
            length: (new Date()).getTime() - this.data[idx].time
          }));
        }
      },

      setId: function(id){
        if ( !id ){
          id = (new Date()).getTime();
        }
        return id;
      },

      update(selected){
        if ( selected === undefined ){
          selected = this.selected;
        }
        if ( this.data.length && this.data[selected] ){
          this.isLoading = this.data[selected].isLoading;
          this.isSuccess = this.data[selected].isSuccess;
          this.isError = this.data[selected].isError;
          this.isPage = this.data[selected].isPage;
          this.text = this.data[selected].text;
          this.id = this.data[selected].id;
          this.length = this.data[selected].length || false;
        }
        else{
          this.isLoading = false;
          this.isSuccess = false;
          this.isError = false;
          this.isPage = false;
          this.text = false;
          this.id = false;
          this.length = false;
        }
      }
    },

    mounted: function(){

    },

    watch: {
      data(){
        this.update();
      },
      selected(newVal, oldVal){
        if ( this.data[newVal] ){
          this.update();
        }
        else {
          this.update(0);
        }
      }
    }
  });

})(jQuery, bbn, kendo);
