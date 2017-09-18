/**
 * Created by BBN on 13/02/2017.
 */
(function($, bbn){
  "use strict";

  const VALUES = [{
    name: 'year',
    timeout: 3600000
  }, {
    name: 'month',
    timeout: 3600000
  }, {
    name: 'day',
    timeout: 3600000
  }, {
    name: 'hour',
    timeout: 3600000
  }, {
    name: 'minute',
    timeout: 60000
  }, {
    name: 'second',
    timeout: 1000
  }, {
    name: 'millisecond',
    timeout: 50
  }];

  Vue.component('bbn-countdown', {
    template: '#bbn-tpl-component-countdown',
    props: {
      precision: {
        type: String,
        default: 'second'
      },
      scale: {
        type: String,
        default: 'year'
      },
      target: {
        type: [Date, String, Function],
        required: true
      }
    },
    data(){
      return {
        precisionIdx: bbn.fn.search(VALUES, "name", this.precision),
        scaleIdx: bbn.fn.search(VALUES, "name", this.scale),
        realTarget: bbn.fn.date($.isFunction(this.target) ? this.target() : this.target),
        targetYear: false,
        targetMonth: false,
        targetDay: false,
        targetHour: false,
        targetMinute: false,
        targetSecond: false,
        targetMillisecond: false,
        year: false,
        month: false,
        day: false,
        hour: false,
        minute: false,
        second: false,
        millisecond: false,
        interval: 0,
        time: false
      };
    },
    methods: {
      check(){
        return this.realTarget &&
          (this.precisionIdx > -1) &&
          (this.scaleIdx > -1) &&
          (this.precisionIdx >= this.scaleIdx);
      },
      init(){
        clearInterval(this.interval);
        if ( !this.check() ){
          bbn.fn.error(bbn._("Error in the countdown component, the precision can't be lower than the scale"));
        }
        else{
          this.time = this.realTarget.getTime();
          this.targetYear = this.realTarget.getFullYear();
          this.targetMonth = this.realTarget.getMonth();
          this.targetDay = this.realTarget.getDate();
          this.targetHour = this.realTarget.getHours();
          this.targetMinute = this.realTarget.getMinutes();
          this.targetSecond = this.realTarget.getSeconds();
          this.targetMillisecond = this.realTarget.getMilliseconds();
          let next,
              d = new Date();
          if ( this.precisionIdx <= 3 ){
            next = new Date(d.getFullYear(), d.getMonth(), d.getDate(), d.getHours() +1, 0, 0);
          }
          else if ( this.precisionIdx === 4 ){
            next = new Date(d.getFullYear(), d.getMonth(), d.getDate(), d.getMinutes() +1, 0, 0);
          }
          else {
            next = new Date(d.getFullYear(), d.getMonth(), d.getDate(), d.getMinutes(), d.getSeconds() + 1, 0);
          }
          let timeout = next.getTime() - d.getTime();
          if ( timeout < 0 ){
            timeout = 0;
          }
          setTimeout(() => {
            this.update();
            this.interval = setInterval(this.update, VALUES[this.precision]);
          }, timeout);
          this.update();
        }
      },
      update(){
        let d = new Date(),
            tNow = d.getTime();
        if ( tNow > this.time ){
          $.each(VALUES, (i, a) => {
            this.$set(this, a.name, 0);
          });
        }
        else{
          //let diff = tNow - this.time;

          let diff = [
            this.targetYear - d.getFullYear(),
            this.targetMonth - d.getMonth(),
            this.targetDay - d.getDate(),
            this.targetHour - d.getHours(),
            this.targetMinute - d.getMinutes(),
            this.targetSecond - d.getSeconds(),
            this.targetMillisecond - d.getMilliseconds()
          ];
          for ( let i = 0; i < VALUES.length; i++ ){
            if ( this.precisionIdx <= i ){
              if ( diff[i] < 0 ){
                diff[i-1]--;
                switch ( i ){
                  case 1:
                    diff[1] = 11 - diff[1];
                    break;
                  case 2:
                    diff[2] = bbn.fn.daysInMonth(d) - diff[2];
                    break;
                  case 3:
                    diff[3] = 24 - diff[3];
                    break;
                  case 4:
                    diff[4] = 60 - diff[4];
                    break;
                  case 5:
                    diff[5] = 60 - diff[5];
                    break;
                  case 6:
                    diff[6] = 1000 - diff[6];
                    break;
                }
              }
              if ( this.scaleIdx > i ){
                switch ( i ){
                  case 0:
                    diff[1] += 365 * diff[i];
                    break;
                  case 1:
                    diff[2] += 12 * diff[i];
                    break;
                  case 2:
                    diff[3] += 30 * diff[i];
                    break;
                  case 3:
                    diff[4] += 24 * diff[i];
                    break;
                  case 4:
                    diff[5] += 60 * diff[i];
                    break;
                  case 5:
                    diff[6] += 60 * diff[i];
                    break;
                }
                diff[i] = 0;
              }
            }
            else{
              this.$set(this, VALUES[i].name, diff[i]);
            }
          }
          this.$forceUpdate();
        }
      }
    },
    created(){
      this.init();
    },
  });
})(jQuery, bbn);
