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
  Vue.component('bbn-chart2', {
    mixins: [bbn.vue.basicComponent, bbn.vue.optionComponent],
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
        type: Number
      },
      height: {
        type: Number
      },
      point: {
        type: [Boolean, Number],
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
      label: {
        type: [Boolean, Function],
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
        type: [Boolean, String],
        default: true
      },
      customLegend: {

      },
      insetLegend: {
			  type: Object
      },

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
      isDonut(){
        return (this.type === 'pie') && this.donut;
      },
      legendFixed(){
        if ( Array.isArray(this.legend) && (typeof this.legend[0] === 'object') ){
          return $.map(this.legend, (l) => {
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
          point: {
            show: $.isNumeric(this.point) ? true : this.point,
            r: $.isNumeric(this.point) ? this.point : undefined
          },
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
        return this.isPie ? {
        [this.isDonut ? 'donut' : 'pie']: {
          label: {
            show: $.isFunction(this.label) ? true : this.label,
            format: $.isFunction(this.label) ? this.label : (val) => { return val; }
          },
          width: $.isNumeric(this.donut) ? this.donut : undefined
          }
        } : {};
      },
      widgetCfg(){
        const vm = this;
        let cfg = $.extend(true, {
          bindto: this.$refs.chart,
          data: {
            columns: [],
            type: this.isDonut ? 'donut' : this.type
          },
          size: {
            width: this.width || undefined,
            height: this.height || undefined
          },
          padding: {
            top: this.paddingTop || this.padding,
            right: this.paddingRight || this.padding,
            left: this.paddingLeft || this.padding,
            bottom: this.paddingBottom || this.padding
          },
          legend: {
            show: typeof this.legend === 'string' ? true : this.legend,
            position: typeof this.legend === 'string' ? this.legend : undefined,
            inset: ((this.legend === 'inset') && this.insetLegend) ? this.insetLegend : undefined
          },
          tooltip: {
            show: $.isFunction(this.tooltip) ? true : this.tooltip,
            format: {
              value: $.isFunction(this.tooltip) ? this.tooltip : undefined
            }
          }
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
        // if ( this.widget ){
        //   this.widget.detach();
        //   this.widget = false;
        // }
        // Widget initialization
        this.widget = new c3.generate(this.widgetCfg);
        this.setColumns();
        // Set items color
        if ( this.color ){
          //this.setColor();
        }
        // Set labels color
        if ( this.labelColor || this.labelColorX || this.labelColorY ){
          //this.setLabelColor();
        }
        // Set grid color
        if ( this.gridColor ){
          //this.setGridColor();
        }
        // Operations to be performed during the widget draw
        //this.widgetDraw();
        // Operations to be performed after widget creation
        //this.widgetCreated();

      },
      setColumns(){
        if ( this.source.labels && this.source.labels.length && this.source.series ){
          let d = [];
          $.each(this.source.series, (i, v) => {
            d.push([this.source.labels[i], v]);
          });
          this.widget.load({columns: d});

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
          if ( this.isLine ){
            
          }
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
              let legendHeight = $('ul.ct-legend:not(.ct-legend-inside)', this.widget.container).height(),
                  svgHeight = $('svg', this.widget.container).height(),
                  contHeight = $(this.widget.container).height();
              if ( (legendHeight + svgHeight) > contHeight ){
                this.widget.update(false, {height: contHeight - legendHeight}, true);
                return;
              }
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
        //this.init();
      },
    },
    mounted(){
      this.$nextTick(() => {
        this.init();
      });
    }
  });
})(jQuery, bbn);