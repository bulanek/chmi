/**
 * @file
 */
(function ($, Drupal ) {
	    Drupal.behaviors.chmi_behaviour = {
		attach : function once(context, settings) {
			var station = settings.graph_chmi.station;
			var observable = settings.graph_chmi.observable;
			var time = settings.graph_chmi.time.map(function(x) {
				return parseInt(x);
			});
			var values = settings.graph_chmi.values.map(function(x) {
				return parseFloat(x);
			});
			var data = [];
			for (i = 0; i < values.length; i++) {
				data.push([ time[i] * 1000, values[i] ]);
			}

			// The following plot uses a number of options to set the title,
			// add axis labels, and shows how to use the canvasAxisLabelRenderer
			// plugin to provide rotated axis labels.
			var plotChmi = $.jqplot('graph_chmi', [ data ], {
				// Give the plot a title.
				title : 'Station: ' + station,
				// You can specify options for all axes on the plot at once with
				// the axesDefaults object. Here, we're using a canvas renderer
				// to draw the axis label which allows rotated text.
				axesDefaults : {
					labelRenderer : $.jqplot.CanvasAxisLabelRenderer
				},
				series: [{ 
		            renderer: $.jqplot.OHLCRenderer,
		            rendererOptions: {
		                candleStick: true
		            } 
		        }], 
				// An axes object holds options for all axes.
				// Allowable axes are xaxis, x2axis, yaxis, y2axis, y3axis, ...
				// Up to 9 y axes are supported.
				axes : {
					// options for each axis are specified in seperate option
					// objects.
					xaxis : {
						label : "Date",
						renderer : $.jqplot.DateAxisRenderer,
						rendererOptions : {
							tickRenderer : $.jqplot.CanvasAxisTickRenderer,
						},
						tickOptions : {
							formatString : '%#d.%#m. %#H:%M',
							angle : -40,
						},
					// Turn off "padding". This will allow data point to lie on the
					// edges of the grid. Default padding is 1.2 and will keep all
					// points inside the bounds of the grid.
					},
					yaxis : {
						label : observable
					}
				},
				cursor:{
					show:	true,
					zoom:	true,
				}
			});
			plotChmi.replot();
			$(window).resize(function() {
				plotChmi.replot( { resetAxes: true } );
			});
		}
	};
})(jQuery, Drupal);
