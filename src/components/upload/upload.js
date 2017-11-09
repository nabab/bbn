/**
 * Created by BBN on 13/06/2017.
 */
(($, bbn) => {
  "use strict";

  var vc;
  Vue.component('bbn-upload', {
    //mixins: [bbn.vue.fullComponent],
    mixins: [bbn.vue.inputComponent],
    template: '#bbn-tpl-component-upload',
    props: {
      value: {
        type: [Array, String],
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
      json: {
        type: Boolean,
        default: false
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
        isMounted: false,
        widgetValue: []
      };
    },
    computed: {
      dropHereText(){
        return this.enabled ? this.text.dropHere : '';
      },
      getSource(){
        let res;
        if ( (typeof this.value === 'string') && this.json ){
          res = JSON.parse(this.value);
        }
        else if ( Array.isArray(this.value) ){
          res = this.value;
        }
        return Array.isArray(res) ? res : false;
      },
      getValue(){
        let files = $.map(this.widgetValue, (e) => {
          return {
            name: e.name,
            size: e.size,
            extension: e.name.slice(e.name.lastIndexOf('.'))
          }
        });
        return this.json ? JSON.stringify(files) : files;
      },
      getCfg(){
        let cfg = {
          request: {
            inputName: 'file',
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
            onComplete(){
              if ( $.isFunction(vc.success) ){
                vc.success();
              }
            },
            onError(){
              if ( $.isFunction(vc.error) ){
                vc.error();
              }
            },
            onSubmitDelete(id){
              this.setDeleteFileParams({file: this.getName(id)}, id);
            },
            onDeleteComplete(id, xhr, err, e){
              if ( $.isFunction(vc.delete) ){
                vc.delete();
              }
            },
            onStatusChange(){
              vc.widgetValue = vc.widget.getUploads({status: [qq.status.UPLOAD_SUCCESSFUL]}) || [];
            }
          },
          validation: {
            stopOnFirstInvalidFile: true
          },
          thumbnails: {
            placeholders: {
              notAvailablePath: 'data:image/png;base64,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',
              waitingPath: 'data:image/png;base64,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'
            }
          }
        };

        if ( this.thumbNot && this.thumbNot.length ){
          cfg.thumbnails.placeholders.notAvailablePath = this.thumbNot;
        }
        if ( this.thumbWaiting && this.thumbWaiting.length ){
          cfg.thumbnails.placeholders.waitingPath = this.thumbWaiting;
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
    created(){
      vc = this;
    },
    mounted(){
      this.$nextTick(() => {
        this.widget = new qq.FineUploader($.extend({
          element: this.$refs.upload,
          template: this.$refs.ui_template,
        }, this.getCfg));
        if ( this.value && this.getSource ){
          this.widget.addInitialFiles(this.getSource);
        }
        this.isMounted = true;
        //this.enable(this.enabled);
        //this.$emit("ready", this.value);
      });
    },
    watch: {
      enabled(val){
        this.enable(val);
      },
      widgetValue(val){
        vc.$emit('input', vc.getValue);
        vc.$emit('change', vc.getValue);
      }
    }
  });

})(jQuery, bbn);
