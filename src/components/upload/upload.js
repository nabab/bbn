/**
 * Created by BBN on 13/06/2017.
 */
(($, bbn) => {
  "use strict";

  Vue.component('bbn-upload', {
    mixins: [bbn.vue.vueComponent],
    template: '<div class="bbn-upload" ref="upload"></div>',
    props: {
      source: {
        type: Array
      },
      saveUrl: {
        type: String
      },
      removeUrl: {
        type: String
      },
      autoUpload : {
        type: Boolean
      },
      multiple: {
        type: Boolean
      },
      enabled: {
        type: Boolean
      },
      success: {},
      error: {},
      delete: {},
      thumbNot : {
        type: String
      },
      thumbWaiting: {
        type: String
      },
      cfg: {
        type: Object,
        default(){
          return {
            source: [],
            enabled: true,
            autoUpload: true,
            multiple: true,
            request: {
              endpoint: ''
            },
            deleteFile: {
              enabled: false,
              endpoint: '',
              forceConfirm: true,
              method: 'POST',
              confirmMessage: bbn._('Are you sure you want to delete') + " {filename}?"
            },
            callbacks: {},
            validation: {
              stopOnFirstInvalidFile: true
            },
            thumbnails: {
              placeholders: {
                notAvailablePath: null,
                waitingPath: null
              }
            }
          };
        }
      }
    },
    methods: {
      getOptions(){
        const vm = this,
              cfg = bbn.vue.getOptions(vm);

        if ( cfg.saveUrl && cfg.saveUrl.length ){
          cfg.request.endpoint = cfg.saveUrl;
        }
        if ( cfg.removeUrl && cfg.removeUrl.length ){
          cfg.deleteFile.endpoint = cfg.removeUrl;
          cfg.deleteFile.enabled = true;
        }
        if ( cfg.thumbNot && cfg.thumbNot.length ){
          cfg.thumbnails.placeholders.notAvailablePath = cfg.thumbNot;
        }
        if ( cfg.thumbWaiting && cfg.thumbWaiting.length ){
          cfg.thumbnails.placeholders.waitingPath = cfg.thumbWaiting;
        }
        if ( $.isFunction(cfg.success) ){
          cfg.callbacks.onComplete = (id, name, errorReason, xhr) => {
            bbn.fn.log('onComplete', id, name, errorReason, xhr);
            cfg.success();
          };
        }
        if ( $.isFunction(cfg.error) ){
          cfg.callbacks.onError = (id, name, errorReason, xhr) => {
            bbn.fn.log('onError', id, name, errorReason, xhr);
            cfg.error();
          };
        }
        if ( $.isFunction(cfg.delete) ){
          cfg.callbacks.onDelete = (id, name, errorReason, xhr) => {
            bbn.fn.log('onDelete', id, name, errorReason, xhr);
            cfg.delete();
          };
        }

        cfg.callbacks.onValidate = (d) => {
          const files = vm.widget.getUploads({
            status: [
              qq.status.SUBMITTED,
              qq.status.QUEUED,
              qq.status.UPLOADING,
              qq.status.UPLOAD_RETYRING,
              qq.status.UPLOAD_FAILED,
              qq.status.UPLOAD_SUCCESSFUL,
              qq.status.PAUSED
            ]
          });
          if ( bbn.fn.search(files, 'name', d.name) > -1 ){
            bbn.fn.alert('The file ' + d.name + ' already exists!');
            return false;
          }
        };

        cfg.callbacks.onSubmitDelete = (id) => {
          vm.widget.setDeleteFileParams({filename: vm.widget.getName(id)}, id);
        };

        return cfg;
      },
    },
    data(){
      return bbn.vue.treatData(this);
    },
    mounted(){
      const vm = this,
            opt = vm.getOptions();

      $.extend(opt, {
        text: {
          uploadButton: bbn._('Upload a file'),
          dropHere: bbn._('Drop files here'),
          processingDropped: bbn._('Processing dropped files...'),
          retry: bbn._('Retry'),
          editFilename: bbn._('Edit filename'),
          delete: bbn._('Delete'),
          pause: bbn._('Pause'),
          continue: bbn._('Continue'),
          close: bbn._('Close'),
          no: bbn._('No'),
          yes: bbn._('Yes'),
          cancel: bbn._('Cancel'),
          ok: bbn._('OK')
        }
      });


      vm.templateVue = new Vue({
        el: $($("#bbn-tpl-component-upload").get(0).innerHTML).get(0),
        data(){
          return opt;
        },
        methods: {
          enable(val){
            const $inp = $("input[name=qqfile]", this.$el);
            if ( val ){
              $inp.removeAttr('disabled');
            }
            else {
              $inp.attr('disabled', 'disabled');
            }
          }
        },
        computed: {
          dropHereText(){
            return this.enabled ? this.text.dropHere : '';
          }
        },
        whatch: {
          enabled(val){
            this.enable(val);
          }
        },
        mounted(){
          const vm2 = this;
          vm.widget = new qq.FineUploader($.extend(opt, {
            element: vm.$el,
            template: vm2.$el,
          }));
          vm2.enable(vm2.enabled);
        }
      });
    },
    watch: {
      enabled(val){
        this.templateVue.enabled = val;
      }
    }
  });

})(jQuery, bbn);
