/**
 * Created by BBN on 13/06/2017.
 */
(function($, bbn, kendo){
  "use strict";

  var ui = kendo.ui,
			dropDownTreeView = ui.Widget.extend({
				_uid: null,
				_selId: null,
				_treeview: null,
				_dropdown: null,

				init: function(element, options){
					var that = this,
							isInput = bbn.fn.tagName(element) === "input";

					ui.Widget.fn.init.call(that, element, options);

					that._uid = new Date().getTime();

					var classes = $(element).attr("class"),
							of = $(element).css("overflow"),
							mh = $(element).css("max-height"),
							of = $(element).css("overflow"),
							w = $(element).width() - 24,
							additionalStyle = "",
							container = $(element);

					if ( of && mh ){
						additionalStyle = kendo.format("max-height:{0};overflow:{1};", mh, of);
					}
					if ( w ){
						additionalStyle += kendo.format("width:{0};", w);
					}
					if ( isInput ){
						container = $(kendo.format('<div class="{0}" style="{1}"/>', classes, additionalStyle));
						$(element).hide().after(container);
					}
					var treeID = 'extTreeView' + that._uid;
					container.append(kendo.format("<input id='extDropDown{0}' class='k-ext-dropdown {1}'/>", that._uid, classes));
					container.append(kendo.format("<div id='{0}' class='k-ext-treeview' style='z-index:1;{1}'/>", treeID, additionalStyle));

					var $treeviewRootElem,
							$dropdownRootElem,
							ds = [];
					if ( inputVal ){
						ds.push({
							text: inputVal,
							value: inputVal
						});
					}

					var ddCfg = {
						dataSource: [],
						dataTextField: "text",
						dataValueField: "value",
						open: function(e){
							//to prevent the dropdown from opening or closing. A bug was found when clicking on the dropdown to
							//"close" it. The default dropdown was visible after the treeview had closed.
							e.preventDefault();
							// If the treeview is not visible, then make it visible.
							if ( !$treeviewRootElem.hasClass("k-custom-visible") ){
								// Position the treeview so that it is below the dropdown.
								$treeviewRootElem.css({
									"top": $dropdownRootElem.position().top + $dropdownRootElem.height(),
									"left": $dropdownRootElem.position().left
								});
								// Display the treeview.
								$treeviewRootElem.slideToggle("fast", function(){
									that._dropdown.close();
									$treeviewRootElem.addClass("k-custom-visible");
								});
							}
							if ( that._selId ){
								that._treeview.expandTo(that._selId);
								var ddVal = $dropdownRootElem.find("span.k-input").text();
								var selectedNode = that._treeview.findByText(ddVal);
								that._treeview.select(selectedNode);
							}
							var list = $("#" + treeID);
							var width = list.width();
							list.width("auto");
							var width2 = list.width();
							var width3 = $dropdownRootElem.width() + 22;
							if ( width3 > width2 ){
								list.width(width3);
							}
							else if ( width2 > width ){
								list.css({width: width2});
							}
							else{
								list.width(width);
							}
						}
					};
					ddCfg.enable = options.enable !== false;
					if ( options.optionLabel ){
						ddCfg.optionLabel = options.optionLabel;
					}
					if ( options.change ){
						ddCfg.change = options.change;
					}
					if ( options.select ){
						ddCfg.select = options.select;
					}

					// Create the dropdown.
					that._dropdown = $(kendo.format("#extDropDown{0}", that._uid)).kendoDropDownList(ddCfg).data("kendoDropDownList");

					if ( options.dropDownWidth ){
						that._dropdown._inputWrapper.width(options.dropDownWidth);
					}
					else if ( w ){
						that._dropdown._inputWrapper.css({width: w}).parent().css({width: w});
					}

					$dropdownRootElem = $(that._dropdown.element).closest("span.k-dropdown"); // Create the treeview.
					that._treeview = $(kendo.format("#extTreeView{0}", that._uid)).kendoTreeView(options.treeview).data("kendoTreeView");
					that._treeview.bind("select", function(e){
						//bbn.fn.log("SELECT", e);
						// When a node is selected, display the text for the node in the dropdown and hide the treeview.
						$dropdownRootElem.find("span.k-input").text($(e.node).children("div").text());
						$treeviewRootElem.slideToggle("fast", function(){
							that._selId = $("#extTreeView" + that._uid).data("kendoTreeView").dataItem(e.node).id;
							$treeviewRootElem.removeClass("k-custom-visible");
							that.trigger("select", e);
						});
					});

					$treeviewRootElem = $(that._treeview.element).closest("div.k-treeview"); // Hide the treeview.
					$treeviewRootElem
						.width($dropdownRootElem.width() - 2)
						.css({
							"border": "1px solid #ccc",
							"display": "none",
							"position": "absolute",
							"background-color": that._dropdown.list.css("background-color")
						});
					var inputVal = that.element.val();
					if ( inputVal ){
						that.value(inputVal);
					}
					$(document).click(function(e){
						// Ignore clicks on the treetriew.
						if ( $(e.target).closest("div.k-treeview").length === 0 ){
							// If visible, then close the treeview.
							if ( $treeviewRootElem.hasClass("k-custom-visible") ){
								$treeviewRootElem.slideToggle("fast", function(){
									$treeviewRootElem.removeClass("k-custom-visible");
								});
							}
						}
					});
				},

				value: function (value ){
					if ( value !== undefined ){
						var that = this,
								dataItem = that._treeview.dataSource.get(value),
								item = that._treeview.findByUid(dataItem.uid),
								$dropdownRootElem = $(that._dropdown.element).closest("span.k-dropdown");
						that._dropdown.value(value);
						$dropdownRootElem.find("span.k-input").text($(item).children("div").text());
						that._selId = value;
						return this.element.val(value);
					}
					else{
						return this.element.val();
					}
				},

				dropDownList: function(){
					return this._dropdown;
				},

				treeview: function(){
					return this._treeview;
				},

				options: {
					name: "DropDownTreeView"
				}
			});
  ui.plugin(dropDownTreeView);

  Vue.component('bbn-dropdowntreeview', {
    mixins: [bbn.vue.vueComponent],
    template: '#bbn-tpl-component-dropdowntreeview',
    props: {
      source: {
        type: Array
      },
			dataTextField: {
				type: String
			},
			dataValueField: {
				type: String
			},
			placeholder: {
				type: String
			},
			select: {},
      cfg: {
        type: Object,
        default: function(){
          return {
        		treeview: {
							dataTextField: "text",
							dataValueField: "value",
							dataSource: {}
						}
          };
        }
      }
    },
    methods: {
      getOptions: function(){
        var vm = this,
            cfg = bbn.vue.getOptions(vm),
						opt = {};
				if ( cfg.disabled ){
					opt.enable = false;
				}
				if ( cfg.placeholder ){
					opt.optionLabel = cfg.placeholder;
				}
        opt.change = function(){
					if ( $.isFunction(cfg.change) ){
						return cfg.change();
					}
        };
				if ( cfg.dataTextField ){
					cfg.treeview.dataTextField = cfg.dataTextField;
				}
				if ( cfg.dataValueField ){
					cfg.treeview.dataValueField = cfg.dataValueField;
				}
				opt.treeview = $.extend(cfg.treeview, {
					dataSource: new kendo.data.HierarchicalDataSource({
						data: vm.source,
						schema: {
							model: {
								id: vm.widgetCfg.dataValueField || 'value',
								hasChildren: 'is_parent',
								children: 'items',
								fields: {
									text: {type: 'string'},
									is_parent: {type: 'bool'}
								}
							}
						}
					}),
					select: function(e){
						var dt = e.sender.dataItem(e.node);
						if ( dt ){
							vm.widget.value(dt[vm.widgetCfg.dataValueField || 'value']);
							vm.$emit("input", dt[vm.widgetCfg.dataValueField || 'value']);
						}
					}
				});
        return opt;
      }
    },
    data: function(){
      return $.extend({
        widgetName: "kendoDropDownTreeView"
      }, bbn.vue.treatData(this));
    },
    mounted: function(){
      var vm = this,
					cfg = vm.getOptions();

      vm.widget = $(vm.$el).kendoDropDownTreeView(cfg).data("kendoDropDownTreeView");
      
			/*if ( !cfg.optionLabel && vm.widget.treeview().dataSource.data().length && !vm.value ){
        vm.widget.select(0);
        vm.widget.trigger("change");
      }*/
    },
    watch:{
      source: function(newSource){
        this.widget.treeview().dataSource.data(newSource);
      }
    }
  });

})(jQuery, bbn, kendo);