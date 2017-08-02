<template>
<div>
  <div class="qq-uploader-selector qq-uploader qq-gallery bbn-vmiddle"
       v-bind="{'qq-drop-area-text': dropHereText}"
  >
    <div class="qq-total-progress-bar-container-selector qq-total-progress-bar-container">
      <div role="progressbar"
           aria-valuenow="0"
           aria-valuemin="0"
           aria-valuemax="100"
           class="qq-total-progress-bar-selector qq-progress-bar qq-total-progress-bar"
      ></div>
    </div>
    <div v-if="enabled"
         class="qq-upload-drop-area-selector qq-upload-drop-area"
         style="background-color: lightgreen"
         qq-hide-dropzone
    >
      <span class="qq-upload-drop-area-text-selector"></span>
    </div>
    <a class="qq-upload-button-selector k-button"
       :class="{'k-state-disabled': !enabled}"
    >{{text.uploadButton}}</a>
    <span class="qq-drop-processing-selector qq-drop-processing">
        <span>{{text.processingDropped}}</span>
        <span class="qq-drop-processing-spinner-selector qq-drop-processing-spinner"></span>
    </span>
    <ul class="qq-upload-list-selector qq-upload-list"
        role="region"
        aria-live="polite"
        aria-relevant="additions removals"
        v-show="enabled"
    >
      <li>
        <span role="status" class="qq-upload-status-text-selector qq-upload-status-text"></span>
        <div class="qq-progress-bar-container-selector qq-progress-bar-container">
          <div role="progressbar"
               aria-valuenow="0"
               aria-valuemin="0"
               aria-valuemax="100"
               class="qq-progress-bar-selector qq-progress-bar"
          ></div>
        </div>
        <span class="qq-upload-spinner-selector qq-upload-spinner"></span>
        <div class="qq-thumbnail-wrapper">
          <img class="qq-thumbnail-selector" qq-max-size="120" qq-server-scale>
        </div>
        <button type="button" class="qq-upload-cancel-selector qq-upload-cancel">X</button>
        <button type="button" class="qq-upload-retry-selector qq-upload-retry">
          <span class="qq-btn qq-retry-icon" v-bind="{'aria-label': text.retry}"></span>
          {{text.retry}}
        </button>

        <div class="qq-file-info">
          <div class="qq-file-name">
            <span class="qq-upload-file-selector qq-upload-file"></span>
            <span class="qq-edit-filename-icon-selector qq-btn qq-edit-filename-icon"
                  v-bind="{'aria-label': text.editFilename}"
            ></span>
          </div>
          <input class="qq-edit-filename-selector qq-edit-filename" tabindex="0" type="text">
          <span class="qq-upload-size-selector qq-upload-size"></span>
          <button type="button" class="qq-btn qq-upload-delete-selector qq-upload-delete">
            <span class="qq-btn qq-delete-icon" v-bind="{'aria-label': text.delete}"></span>
          </button>
          <button type="button" class="qq-btn qq-upload-pause-selector qq-upload-pause">
            <span class="qq-btn qq-pause-icon" v-bind="{'aria-label': text.pause}"></span>
          </button>
          <button type="button" class="qq-btn qq-upload-continue-selector qq-upload-continue">
            <span class="qq-btn qq-continue-icon" v-bind="{'aria-label': text.continue}"></span>
          </button>
        </div>
      </li>
    </ul>

    <dialog class="qq-alert-dialog-selector">
      <div class="qq-dialog-message-selector"></div>
      <div class="qq-dialog-buttons">
        <button type="button" class="qq-cancel-button-selector">{{text.close}}</button>
      </div>
    </dialog>

    <dialog class="qq-confirm-dialog-selector">
      <div class="qq-dialog-message-selector"></div>
      <div class="qq-dialog-buttons">
        <button type="button" class="qq-cancel-button-selector">{{text.no}}</button>
        <button type="button" class="qq-ok-button-selector">{{text.yes}}</button>
      </div>
    </dialog>

    <dialog class="qq-prompt-dialog-selector">
      <div class="qq-dialog-message-selector"></div>
      <input type="text">
      <div class="qq-dialog-buttons">
        <button type="button" class="qq-cancel-button-selector">{{text.cancel}}</button>
        <button type="button" class="qq-ok-button-selector">{{text.ok}}</button>
      </div>
    </dialog>
  </div>
</div>
</template>
<script>
  var fine-uploader =  require('fine-uploader');


  export default {
    name:'bbn-upload',
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
  }
</script>
<style>
.bbn-upload {
  .qq-gallery.qq-uploader {
    min-height: unset;
    &:before {
      top: unset;
    }
    &.qq-uploader-selector.bbn-vmiddle {
      flex-direction: column;
      align-items: inherit;
    }
    .qq-upload-button-selector.k-button {
      display: inline;
      width: 105px;
      float: left;
      &.qq-upload-button-hover {
        background: unset;
      }
    }
    ul.qq-upload-list-selector.qq-upload-list {
      padding: 0;
      li {
        margin-top: 25px;
        margin-bottom: 1px;
        span.qq-upload-file {
          padding: 1px;
          color: initial;
        }
      }
    }
  }
}
</style>