/**
 * Created by BBN on 13/06/2017.
 */
(($, bbn) => {
  "use strict";

  Vue.component('bbn-upload', {
    //mixins: [bbn.vue.fullComponent],
    mixins: [bbn.vue.inputComponent],
    template: '#bbn-tpl-component-upload',
    props: {
      source: {
        type: Array,
        default(){
         return [];
        }
      },
      saveUrl: {
        type: String
      },
      removeUrl: {
        type: String
      },
      autoUpload : {
        type: Boolean,
        default: true
      },
      multiple: {
        type: Boolean,
        default: true
      },
      enabled: {
        type: Boolean,
        default: true
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
      text: {
        type: Object,
        default(){
          return {
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
        }
      }
    },
    data(){
      return {
        isMounted: false
      };
    },
    computed: {
      dropHereText(){
        return this.enabled ? this.text.dropHere : '';
      },
      getCfg(){
        let cfg = {
          request: {
            endpoint: this.saveUrl
          },
          deleteFile: {
            endpoint: this.removeUrl || null,
            enabled: !!this.removeUrl,
            forceConfirm: true,
            method: 'POST',
            confirmMessage: bbn._('Are you sure you want to delete') + " {filename}?"
          },
          callbacks: {
            onValidate(d){
              const files = this.getUploads({
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
            },
            onSubmitDelete(id){
              this.setDeleteFileParams({filename: this.getName(id)}, id);
            }
          },
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

        if ( this.thumbNot && this.thumbNot.length ){
          cfg.thumbnails.placeholders.notAvailablePath = this.thumbNot;
        }
        if ( this.thumbWaiting && this.thumbWaiting.length ){
          cfg.thumbnails.placeholders.waitingPath = this.thumbWaiting;
        }
        if ( $.isFunction(this.success) ){
          cfg.callbacks.onComplete = (id, name, errorReason, xhr) => {
            bbn.fn.log('onComplete', id, name, errorReason, xhr);
            this.success();
          };
        }
        if ( $.isFunction(this.error) ){
          cfg.callbacks.onError = (id, name, errorReason, xhr) => {
            bbn.fn.log('onError', id, name, errorReason, xhr);
            this.error();
          };
        }
        if ( $.isFunction(this.delete) ){
          cfg.callbacks.onDelete = (id, name, errorReason, xhr) => {
            bbn.fn.log('onDelete', id, name, errorReason, xhr);
            this.delete();
          };
        }

        return cfg;
      }
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
    mounted(){
      this.$nextTick(() => {
        this.widget = new qq.FineUploader($.extend({
          element: this.$refs.upload,
          template: this.$refs.ui_template,
        }, this.getCfg));
        this.isMounted = true;
        //this.enable(this.enabled);
        //this.$emit("ready", this.value);
      });
    },
    watch: {
      enabled(val){
        this.enable(val);
      }
    }
  });

})(jQuery, bbn);
