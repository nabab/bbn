/**
 * Created by BBN Solutions.
 * User: Mirko Argentino
 * Date: 24/05/2017
 * Time: 15:45
 */

(($, bbn) => {
  "use strict";

  /**
   * Classic input with normalized appearance
   */
  Vue.component('bbn-chart', {
    mixins: [bbn.vue.optionComponent],
    template: "#bbn-tpl-component-chart",
    props: {
      source: {},
      type: {
        type: String,
        default: 'line'
      },
      /**
       * String => the same title to axixs X and Y.
       * Object => {x: 'titlex', y: 'titley'}
       */
			title: {
        type: String,
        default: ''
      },
      titleX: {
			  type: String,
        default: undefined
      },
      titleY: {
			  type: String,
        default: undefined
      },
      width: {
        type: String,
        default: '100%'
      },
      height: {
        type: String,
        default: '100%'
      },
      showPoint: {
        type: Boolean,
        default: true
      },
      showLine: {
        type: Boolean,
        default: true
      },
      lineSmooth: {
        type: Boolean,
        default: false
      },
      donut: {
        type: [Boolean, Number],
        default: false
      },
      fullWidth: {
        type: Boolean,
        default: true
      },
      showArea: {
        type: Boolean
      },
      showLabel: {
        type: Boolean,
        default: true
      },
      axisX: {
        type: Object,
        default(){
          return {};
        }
      },
      axisY: {
        type: Object,
        default(){
          return {};
        }
      },
      showLabelX: {
        type: [Boolean, Function],
        default: true
      },
      reverseLabelX: {
        type: Boolean,
        default: false
      },
			odd: {
        type: Boolean
      },
      even: {
        type: Boolean
      },
      showGridX: {
        type: Boolean,
        default: true
      },
      showLabelY: {
        type: [Boolean, Function],
        default: true
      },
      reverseLabelY: {
        type: Boolean,
        default: false
      },
      showGridY: {
        type: Boolean,
        default: true
      },
      animation: {
        type: [Boolean, Number],
        default: false
      },
      // set it to 0 (zero) for stacked bars
      barsDistance: {
        type: Number,
        default: undefined
      },
      horizontalBars: {
        type: Boolean
      },
      reverseData: {
        type: Boolean
      },
      color: {
        type: [String, Array]
      },
      labelColor: {
        type: String
      },
      labelColorX: {
        type: String
      },
      labelColorY: {
        type: String
      },
      backgroundColor: {
        type: String,
        default: 'inherit'
      },
      gridColor: {
        type: String
      },
      max: {
        type: Number,
        default: undefined
      },
      min: {
        type: Number,
        default: undefined
      },
      onlyInteger: {
        type: Boolean,
        default: false
      },
      tooltip: {
        type: [Boolean, Function],
        default: true
      },
      pointLabel: {
        type: Boolean,
        default: false
      },
      legend: {
        type: [Boolean, Array]
      },
      customLegend: {},

      /*threshold: {
        type: Number
      },*/
      step: {
        type: Boolean,
        default: false
      },
      dateFormat: {
        type: String
      },
      labelOffset: {
        type: Number,
        default: 0
      },
      labelExternal: {
        type: Boolean,
        default: false
      },
      labelWrap: {
        type: [Boolean, Number],
        default: false
      },
      padding: {
        type: Number,
        default: undefined
      },
      paddingTop: {
        type: Number,
        default: undefined
      },
      paddingRight: {
        type: Number,
        default: undefined
      },
      paddingBottom: {
        type: Number,
        default: undefined
      },
      paddingLeft: {
        type: Number,
        default: undefined
      },
      /** @todo add this to labels */
      currency: {
        type: String
      },
      /** @todo to fix problem with animation:true */
      distributeSeries: {
        type: Boolean
      },

      /*zoom: {
        type: Boolean
      },*/
      cfg: {
        type: Object,
        default(){
          return {};
        }
      }
    },
    computed: {
      data(){
        let data = this.source;
        if ( this.isLine || this.isBar ){
          if ( !Array.isArray(data.series[0]) && !this.distributeSeries ){
            data.series = [data.series];
          }
        }
        return data;
      },
      isLine(){
        return this.type === 'line';
      },
      isBar(){
        return this.type === 'bar';
      },
      isPie(){
        return this.type === 'pie';
      },
      plugins(){
        let plugins = [];
        // tooltip
        if ( this.tooltip ){
          plugins.push(Chartist.plugins.tooltip({
            currency: this.currency || false,
            transformTooltipTextFnc: $.isFunction(this.tooltip) ? this.tooltip : undefined
          }));
        }
        // axis X/Y title
        if ( !this.isPie && (this.titleX || this.titleY) ){
          plugins.push(Chartist.plugins.ctAxisTitle({
            axisX: {
              axisTitle: this.titleX || '',
              axisClass: 'ct-axis-title',
              offset: {
                x: 0,
                y: 30
              },
              textAnchor: 'middle'
            },
            axisY: {
              axisTitle: this.titleY || '',
              axisClass: 'ct-axis-title',
              offset: {
                x: 0,
                y: 0
              },
              textAnchor: 'middle',
              flipTitle: false
            }
          }));
        }
        // Point Label
        if ( this.pointLabel ){
          plugins.push(Chartist.plugins.ctPointLabels());
        }
        // Legend
        if ( this.legend ){
          plugins.push(Chartist.plugins.legend({
            onClick(a, b){
              const $rect = $("div.rect", b.target);
              if ( $rect.hasClass('inactive') ){
                $rect.removeClass('inactive');
              }
              else {
                $rect.addClass('inactive');
              }
            },
            removeAll: true,
            legendNames: Array.isArray(this.legendFixed) ? this.legendFixed : false
          }));
        }
        // Thresold
        /** @todo  it's not compatible with our colors system and legend */
        /*if ( (this.isLine || this.isBar) && (typeof this.threshold === 'number') ){
          plugins.push(Chartist.plugins.ctThreshold({
            threshold: this.threshold
          }));
        }*/
        // Zoom
        /** @todo problems with scale x axis */
        /*if ( this.zoom && this.isLine ){
          this.trasformData();
          this.axisX.type =  Chartist.AutoScaleAxis;
          this.axisX.divisor = this.getLabelsLength();
          this.axisY.type =  Chartist.AutoScaleAxis;
          plugins.push(Chartist.plugins.zoom({
            onZoom(chart, reset) {
              this.resetZoom = reset;
            }
          }));
        }*/
        return plugins;
      },
      legendFixed(){
        if ( Array.isArray(this.legend) && (typeof this.legend[0] === 'object') ){
          return $.map(this.legend, (l, i) => {
            return l.text || null;
          });
        }
        else {
          return this.legend;
        }
      },
      legendTitles(){
        if ( Array.isArray(this.legend) && (typeof this.legend[0] === 'object') ){
          return $.map(this.legend, (l, i) => {
            return l.title || (l.text || null) ;
          });
        }
        else {
          return this.legend;
        }
      },
      lineCfg(){
        let cfg = {
          lineSmooth: this.step && this.showLine ? Chartist.Interpolation.step() : this.lineSmooth,
          showPoint: this.showPoint,
          showLine: this.showLine,
          pointLabel: this.pointLabel
        };
        return this.isLine ? $.extend(true, cfg, this.lineBarCommon) : {};
      },
      barCfg(){
        let cfg = {
          seriesBarDistance: this.barsDistance
        };
        return this.isBar ? $.extend(true, cfg, this.lineBarCommon) : {};
      },
      lineBarCommon(){
        if ( this.isLine || this.isBar ){
          let cfg = {
            chartPadding: {
              top: this.paddingTop || this.padding,
              right: this.paddingRight || this.padding,
              bottom: this.paddingBottom || this.padding,
              left: this.paddingLeft || this.padding
            },
            axisX: $.extend(true, {
              showLabel: $.isFunction(this.showLabelX) ? true : this.showLabelX,
              showGrid: this.showGridX,
              position: this.reverseLabelX ? 'start' : 'end'
            }, this.axisX),
            axisY: $.extend(true, {
              showLabel: $.isFunction(this.showLabelY) ? true : this.showLabelY,
              showGrid: this.showGridY,
              position: this.reverseLabelY ? 'end' : 'start',
              onlyInteger: this.onlyInteger,
              high: this.max,
              low: this.min ? -this.min : undefined
            }, this.axisY)
          };
          // Axis X
          // Date format
          if ( this.dateFormat ){
            cfg.axisX.labelInterpolationFnc = (date, idx) => {
              if ( this.odd ){
                return idx % 2 > 0 ? moment(date).format(this.dateFormat) : null;
              }
              if ( this.even ){
                return idx % 2 === 0 ? moment(date).format(this.dateFormat) : null;
              }
              return moment(date).format(this.dateFormat);
            };
          }
          // Odd labels
          if ( this.odd && !this.even && !this.dateFormat ){
            cfg.axisX.labelInterpolationFnc = (val, idx) => {
              return idx % 2 > 0 ? val : null;
            };
          }
          // Even labels
          if ( this.even && !this.odd && !this.dateFormat ){
            cfg.axisX.labelInterpolationFnc = function(val, idx){
              return idx % 2 === 0 ? val : null;
            };
          }
          // Custom axisX label
          if ( $.isFunction(this.showLabelX) ){
            cfg.axisX.labelInterpolationFnc = this.showLabelX;
          }
          // Custom axisY label
          if ( $.isFunction(this.showLabelY) ){
            cfg.axisY.labelInterpolationFnc = this.showLabelY;
            cfg.axisY.offset = 100;
          }
          return cfg;
        }
        return {};
      },
      pieCfg(){
        let cfg = {
              donut: !!this.donut,
              chartPadding: this.padding,
              showLabel: this.showLabel,
              labelDirection: this.labelExternal ? 'explode' : 'neutral',
              labelOffset: this.labelOffset,
              labelInterpolationFnc: (value) => {
                if ( this.labelWrap ){
                  let ret = '',
                      labelWrap = typeof this.labelWrap === 'number' ? this.labelWrap : 25,
                      tmp,
                      cont = 0,
                      arr,
                      spl = (text) => {
                        let r = '',
                            idx = labelWrap;
                        if ( text.length <= labelWrap ){
                          return text;
                        }
                        for ( let i = labelWrap; i < text.length; i += labelWrap ){
                          if ( i === labelWrap ){
                            r += text.slice(0, i) + "\n"
                          }
                          r += text.slice(idx, i) + "\n";
                          idx = i;
                        }
                        return r + text.slice(idx);
                      };
                  if ( typeof value === 'number' ){
                    value = value.toString();
                  }
                  if ( value.length <= labelWrap ){
                    return value;
                  }
                  if ( value.indexOf('\n') !== -1 ){
                    arr = value.split('\n');
                    arr.forEach((a, i) => {
                      ret += spl(a) + (arr[i+1] ? '\n' : '');
                    });
                    return ret;
                  }
                  return spl(value);


                  /*if ( this.labelWrap ){
                    let ret = '',
                        labelWrap = typeof this.labelWrap === 'number' ? this.labelWrap : 25,
                        tmp,
                        cont = 0;
                    if ( typeof value === 'number' ){
                      value = value.toString();
                    }
                    if ( value.length <= labelWrap ){
                      return value;
                    }
                    tmp = value.split(" ");
                    tmp.forEach((t) => {
                      while ( t.length ){
                        if ( cont ){
                          let b = t.slice(0, cont);
                          t = t.slice(cont);
                          if ( b.length < cont ){
                            cont -= b.length;
                          }
                          else {
                            cont = 0;
                          }
                          ret +=  b + (cont ? ' ' : "\n");
                        }
                        if ( t.length ){
                          let a = t.slice(0, labelWrap-1);
                          if ( a.length < labelWrap ){
                            cont = labelWrap - a.length;
                          }
                          ret += a + (cont ? ' ' : '');
                          if ( cont ){
                            cont--;
                          }
                          t = t.slice(labelWrap-1);
                        }
                      }
                    });
                    return ret;
                  }
                  else {
                    return value;
                  }*/
                }
                else {
                  return value;
                }
              }
            };
        if ( typeof this.donut === 'number' ){
          cfg.donutWidth = this.donut;
        }
        else if ( this.donut ){
          cfg.donutWidth = '100%';
        }
        // Force donut if animation is active
        if ( this.animation ){
          cfg.donut = true;
          cfg.donutWidth = '100%';
        }
        return this.isPie ? cfg : {};
      },
      widgetCfg(){
        let cfg = $.extend(true, {
          type: this.type,
          fullWidth: this.fullWidth,
          width: this.width,
          height: this.height,
          tooltip: this.tooltip,
          plugins: this.plugins
        }, this.cfg);
        if ( this.isLine ){
          $.extend(true, cfg, this.lineCfg);
        }
        if ( this.isBar ){
          $.extend(true, cfg, this.barCfg);
        }
        if ( this.isPie ){
          $.extend(true, cfg, this.pieCfg);
        }
        return cfg;
      }
    },
    methods: {
      init(){
        if ( this.widget ){
          this.widget.detach();
          this.widget = false;
        }
        setTimeout(() => {
          // Widget configuration
          if ( this.isPie ){
            this.pieChart();
          }
          else if ( this.isBar ){
            this.barChart();
          }
          else {
            this.lineChart();
          }
          // Set items color
          if ( this.color ){
            this.setColor();
          }
          // Set labels color
          if ( this.labelColor || this.labelColorX || this.labelColorY ){
            this.setLabelColor();
          }
          // Set grid color
          if ( this.gridColor ){
            this.setGridColor();
          }
          // Operations to be performed during the widget draw
          this.widgetDraw();
          // Operations to be performed after widget creation
          this.widgetCreated();
        }, 100);
      },
      pieChart(){
        // Create widget
        this.widget = new Chartist.Pie(this.$refs.chart, this.data, this.widgetCfg);
        // Animations
        this.pieAnimation();
      },
      lineChart(){
        // Create widget
        this.widget = new Chartist.Line(this.$refs.chart, this.data, this.widgetCfg);
        // Animations
        this.lineAnimation();
      },
      barChart(){
        // Create widget
        this.widget = new Chartist.Bar(this.$refs.chart, this.data, this.widgetCfg);
        // Animations
        this.barAnimation();
      },
      getColorIdx(c){
        return c.element._node.parentElement.className.baseVal.replace('ct-series ', '').slice(-1).charCodeAt()-97;
      },
      lineAnimation(){
        if ( this.animation ){
          let seq = 0,
              delays = $.isNumeric(this.animation) ? this.animation : 20,
              durations = 500;
          // Once the chart is fully created we reset the sequence
          this.widget.on('created', () => {
            seq = 0;
          });
          // On each drawn element by Chartist we use the Chartist.Svg API to trigger SMIL animations
          this.widget.on('draw', (chartData) => {
            seq++;
            if ( (chartData.type === 'line') || (chartData.type === 'area') ){
              // If the drawn element is a line we do a simple opacity fade in. This could also be achieved using CSS3 animations.
              chartData.element.animate({
                opacity: {
                  // The delay when we like to start the animation
                  begin: seq * delays + 1000,
                  // Duration of the animation
                  dur: durations,
                  // The value where the animation should start
                  from: 0,
                  // The value where it should end
                  to: 1
                }
              });
            }
            else if ( (chartData.type === 'label') && (chartData.axis.units.pos === 'x') ){
              chartData.element.animate({
                y: {
                  begin: seq * delays,
                  dur: durations,
                  from: chartData.y + 100,
                  to: chartData.y,
                  // We can specify an easing function from Chartist.Svg.Easing
                  easing: 'easeOutQuart'
                }
              });
            }
            else if ( (chartData.type === 'label') && (chartData.axis.units.pos === 'y') ){
              chartData.element.animate({
                x: {
                  begin: seq * delays,
                  dur: durations,
                  from: chartData.x - 100,
                  to: chartData.x,
                  easing: 'easeOutQuart'
                }
              });
            }
            else if ( chartData.type === 'point' ){
              chartData.element.animate({
                x1: {
                  begin: seq * delays,
                  dur: durations,
                  from: chartData.x - 10,
                  to: chartData.x,
                  easing: 'easeOutQuart'
                },
                x2: {
                  begin: seq * delays,
                  dur: durations,
                  from: chartData.x - 10,
                  to: chartData.x,
                  easing: 'easeOutQuart'
                },
                opacity: {
                  begin: seq * delays,
                  dur: durations,
                  from: 0,
                  to: 1,
                  easing: 'easeOutQuart'
                }
              });
            }
            else if ( chartData.type === 'grid' ){
              // Using chartData.axis we get x or y which we can use to construct our animation definition objects
              let pos1Animation = {
                    begin: seq * delays,
                    dur: durations,
                    from: chartData[chartData.axis.units.pos + '1'] - 30,
                    to: chartData[chartData.axis.units.pos + '1'],
                    easing: 'easeOutQuart'
                  },
                  pos2Animation = {
                    begin: seq * delays,
                    dur: durations,
                    from: chartData[chartData.axis.units.pos + '2'] - 100,
                    to: chartData[chartData.axis.units.pos + '2'],
                    easing: 'easeOutQuart'
                  },
                  animations = {};
              animations[chartData.axis.units.pos + '1'] = pos1Animation;
              animations[chartData.axis.units.pos + '2'] = pos2Animation;
              animations['opacity'] = {
                begin: seq * delays,
                dur: durations,
                from: 0,
                to: 1,
                easing: 'easeOutQuart'
              };
              chartData.element.animate(animations);
            }
          });
        }
      },
      barAnimation(){
        if ( this.animation ){
          let delays = $.isNumeric(this.animation) ? this.animation : 500,
              durations = 500;
          this.widget.on('draw', (chartData) => {
            if ( chartData.type === 'bar' ){
              let color = this.color[this.legend ? this.getColorIdx(chartData) : chartData.seriesIndex],
                  style = chartData.element.attr('style');
              if ( color ){
                style = (style || '') + ' stroke: ' + color + ' !important;';
              }
              chartData.element.attr({
                style: style + ' stroke-width: 0px'
              });
              for ( let s = 0; s < chartData.series.length; ++s) {
                if ( chartData.seriesIndex === s ){
                  let ax = {
                    y2: {
                      begin:  s * delays,
                      dur:    durations,
                      from:   chartData.y1,
                      to:     chartData.y2,
                      easing: Chartist.Svg.Easing.easeOutSine
                    },
                    'stroke-width': {
                      begin: s * 500,
                      dur:   1,
                      from:  0,
                      to:    10,
                      fill:  'freeze'
                    }
                  };
                  if ( this.horizontalBars ){
                    ax.x2 = ax.y2;
                    ax.x2.from = chartData.x1;
                    ax.x2.to = chartData.x2;
                    delete ax.y2;
                  }
                  chartData.element.animate(ax, false);
                }
              }
            }
            else if ( (chartData.type === 'label') && (chartData.axis.units.pos === 'x') ){
              chartData.element.animate({
                y: {
                  begin: delays,
                  dur: durations,
                  from: chartData.y + 100,
                  to: chartData.y,
                  // We can specify an easing function from Chartist.Svg.Easing
                  easing: 'easeOutQuart'
                }
              });
            }
            else if ( (chartData.type === 'label') && (chartData.axis.units.pos === 'y') ){
              chartData.element.animate({
                x: {
                  begin: delays,
                  dur: durations,
                  from: chartData.x - 100,
                  to: chartData.x,
                  easing: 'easeOutQuart'
                }
              });
            }
            else if ( chartData.type === 'grid' ){
              // Using chartData.axis we get x or y which we can use to construct our animation definition objects
              let pos1Animation = {
                    begin: delays,
                    dur: durations,
                    from: chartData[chartData.axis.units.pos + '1'] - 30,
                    to: chartData[chartData.axis.units.pos + '1'],
                    easing: 'easeOutQuart'
                  },
                  pos2Animation = {
                    begin: delays,
                    dur: durations,
                    from: chartData[chartData.axis.units.pos + '2'] - 100,
                    to: chartData[chartData.axis.units.pos + '2'],
                    easing: 'easeOutQuart'
                  },
                  animations = {};
              animations[chartData.axis.units.pos + '1'] = pos1Animation;
              animations[chartData.axis.units.pos + '2'] = pos2Animation;
              animations['opacity'] = {
                begin: delays,
                dur: durations,
                from: 0,
                to: 1,
                easing: 'easeOutQuart'
              };
              chartData.element.animate(animations);
            }
          });
        }
      },
      pieAnimation(){
        if ( this.animation ){
          this.widget.on('draw', (chartData) => {
            if ( chartData.type === 'slice' ){
              let style = chartData.element.attr('style'),
                  color;
              if ( this.color && Array.isArray(this.color) ){
                color = this.color[this.legend ? this.getColorIdx(chartData) : chartData.index];
                if ( color ){
                  chartData.element.attr({
                    style: (style || '') + ' stroke: ' + color + ' !important;'
                  });
                }
              }
              // Get the total path length in order to use for dash array animation
              let pathLength = chartData.element._node.getTotalLength();
              // Set a dasharray that matches the path length as prerequisite to animate dashoffset
              chartData.element.attr({
                'stroke-dasharray': pathLength + 'px ' + pathLength + 'px'
              });
              // Create animation definition while also assigning an ID to the animation for later sync usage
              let animationDefinition = {
                'stroke-dashoffset': {
                  id: 'anim' + chartData.index,
                  dur: $.isNumeric(this.animation) ? this.animation : 500,
                  from: -pathLength + 'px',
                  to: '0px',
                  easing: Chartist.Svg.Easing.easeOutQuint,
                  // We need to use `fill: 'freeze'` otherwise our animation will fall back to initial (not visible)
                  fill: 'freeze'
                }
              };
              // If this was not the first slice, we need to time the animation so that it uses the end sync event of the previous animation
              if ( chartData.index !== 0 ){
                animationDefinition['stroke-dashoffset'].begin = 'anim' + (chartData.index - 1) + '.end';
              }
              // We need to set an initial value before the animation starts as we are not in guided mode which would do that for us
              chartData.element.attr({
                'stroke-dashoffset': -pathLength + 'px'
              });
              // We can't use guided mode as the animations need to rely on setting begin manually
              // See http://gionkunz.github.io/chartist-js/api-documentation.html#chartistsvg-function-animate
              chartData.element.animate(animationDefinition, false);
            }
          });
        }
      },
      setColor(){
        if ( typeof this.color === 'string' ){
          this.color = [this.color];
        }
        this.widget.on('draw', (chartData, b) => {
          let style = chartData.element.attr('style'),
              color;
          if ( (chartData.type === 'line') ||
            (chartData.type === 'point') ||
            ((chartData.type === 'bar') && !this.animation) ||
            ( chartData.type === 'area' )
          ){
            color = this.color[this.legend ? this.getColorIdx(chartData) : chartData.seriesIndex];
            if ( color ){
              chartData.element.attr({
                style: (style || '') + (chartData.type === 'area' ? ' fill: ' : ' stroke: ') + color + (chartData.type === 'area' ? '; fill-opacity: 0.1; stroke: none' : '')
              });
            }
          }
          if ( chartData.type === 'slice' ){
            color = this.color[this.legend ? this.getColorIdx(chartData) : chartData.index];
            if ( color && (this.isLine || this.isBar || (this.isPie && !this.animation)) ){
              chartData.element.attr({
                style: (style || '') + ' fill: ' + color
              });
            }
          }
        });
      },
      setLabelColor(){
        this.widget.on('draw', (chartData) => {
          let color = '';
          if ( (chartData.type === 'label') ){
            if ( this.labelColor ){
              color = this.labelColor;
            }
            if ( !this.isPie ){
              if ( this.labelColorX && (chartData.axis.units.pos === 'x') ){
                color = this.labelColorX;
              }
              else if ( this.labelColorY && (chartData.axis.units.pos === 'y') ){
                color = this.labelColorY;
              }
              $(chartData.element._node.children[0]).css('color', color);
            }
            else {
              chartData.element.attr({
                style: 'fill: ' + color
              });
            }
          }
        });
      },
      setGridColor(){
        this.widget.on('draw', (chartData) => {
          if ( chartData.type === 'grid' ){
            chartData.element.attr({
              style: 'stroke: ' + this.gridColor
            });
          }
        });
      },
      /*trasformData(){
        $.each(this.source.series, (i, v) => {
          this.source.series[i] = $.map(v, (el, idx) => {
            if ( (typeof el !== 'object') && this.source.labels[idx] ){
              return {
                x: this.source.labels[idx],
                y: el
              };
            }
            return el;
          })
        });
        this.source.labels = [];
      },
      getLabelsLength(){
        let length = 0;
        $.each(this.source.series, (i,v) => {
          length = v.length > length ? v.length : length;
        });
        return length;
      },*/
      widgetDraw(){
        let yOffset = this.labelExternal ? 15 : 7.5,
            p = 1,
            idDef = bbn.fn.randomString(),
            defs = false;
        this.widget.on('draw', (chartData) => {
          let tmp = 1;
          // Insert linebreak to labels
          if ( this.isPie ){
            if ( chartData.type === 'label' ){
              let lb = chartData.text.split("\n"),
                  text = '';
              if ( lb.length ){
                text = '<tspan>' + lb[0] + '</tspan>';
                $.each(lb, (i, v) => {
                  if ( i > 0 ){
                    text += '<tspan dy="' + yOffset + '" x="' + chartData.x + '">' + v + '</tspan>';
                    chartData.y -= yOffset;
                    chartData.element._node.attributes.dy.value -= (this.labelExternal ? yOffset-10 : yOffset);
                  }
                });
              }
              chartData.element._node.innerHTML = text;
              tmp = lb.length > p ? lb.length : tmp;
            }
            if ( this.labelExternal && ( tmp > p) ){
              p = tmp;
              //this.widget.update(this.widget.data, {chartPadding: (this.widget.options.chartPadding ? this.widget.options.chartPadding : 0) + (p*yOffset)}, true);
            }
            if ( chartData.type === 'slice' ){
              if ( !defs ){
                defs = {
                  x: chartData.center.x,
                  y: chartData.center.y
                };
                $(chartData.group._node.parentNode).prepend('<defs><radialGradient id="' + idDef + '" r="122.5" gradientUnits="userSpaceOnUse" cx="' + defs.x + '" cy="' + defs.y + '"><stop offset="0.05" style="stop-color:#fff;stop-opacity:0.65;"></stop><stop offset="0.55" style="stop-color:#fff;stop-opacity: 0;"></stop><stop offset="0.85" style="stop-color:#fff;stop-opacity: 0.25;"></stop></radialGradient></defs>');
              }
              chartData.element._node.outerHTML += '<path d="' + chartData.element._node.attributes.d.nodeValue + '" stroke="none" fill="url(#' + idDef + ')"></path>';
            }
          }
        });
      },
      widgetCreated(){
        this.widget.on('created', (chartData) => {
          // Set the right colors to legend
          if ( this.legend ){
            let colors = [];
            $("g.ct-series", this.widget.container).each((i,v) => {
              if ( this.isBar ){
                colors.push($("line.ct-bar", v).first().css('stroke'));
              }
              else {
                $("path", v).each((k, p) => {
                  if ( $(p).hasClass('ct-line') ||
                    $(p).hasClass('ct-slice-pie') ||
                    $(p).hasClass('ct-slice-donut')
                  ){
                    colors.push($(p).css($(p).hasClass('ct-slice-pie') ? 'fill' : 'stroke'));
                  }
                })
              }
            });
            setTimeout(() => {
              $("ul.ct-legend li", this.widget.container).each((i, v) => {
                if ( Array.isArray(this.legendTitles) ){
                  $(v).attr('title', this.legendTitles[i]);
                }
                if ( !$("div.rect", v).length ){
                  $(v).prepend('<div class="rect" style="background-color: ' + colors[i] +'; border-color: ' + colors[i] + '"></div>');
                }
              });
            }, 100);
          }
          // Set the right colors to point labels
          if ( !this.isPie && (this.labelColor || this.labelColorY) ){
            $("g.ct-series text.ct-label", this.widget.container).css('stroke', this.labelColorY || this.labelColor);
          }
          // Reset zoom
          /*if ( this.zoom && this.isLine ){
            $(this.widget.container).dblclick(() => {
              if ( this.resetZoom && $.isFunction(this.resetZoom) ){
                this.resetZoom();
              }
            });
          }*/
        });
      }
    },
    watch: {
      source(val){
        this.init();
      },
    },
    mounted(){
      this.$nextTick(() => {
        this.init();
      });
    }
  });
})(jQuery, bbn);