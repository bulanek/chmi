/**
 * @file
 * Plugin jQuery Tablesorter.
 */

(function ($) {
  Drupal.behaviors.tablesorter = {
    attach: function (context, settings) {
      $('.tablesorter').each(function (idx, table) {
    	    // extend the default setting to always include the zebra widget. 
    	    $.tablesorter.defaults.widgets = ['zebra']; 
    	    // call the tablesorter plugin 
    	    $(table).tablesorter();
      });
    }
  };
})(jQuery);
