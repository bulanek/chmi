/**
 * @file
 * Plugin jQuery Tablesorter.
 */

(function ($) {
  Drupal.behaviors.tablesorter = {
    attach: function once(context, settings) {
      $('.tablesorter').each(function (idx, table) {
    	    // extend the default setting to always include the zebra widget.
    	    // call the tablesorter plugin
    	  	$(table).tablesorter({
    	  		  theme: 'blue',
				  widthFixed: true,
				  sortLocaleCompare: true, // needed for accented characters in the data
				  sortList: [ [0,1] ],
				  widgets: ['zebra', 'filter'],	
    	  	}
    	  	)
    	  	.tablesorterPager({
    	  	  container: $(".pager"),

    	  	  // output string - default is '{page}/{totalPages}';
              // possible variables: {size}, {page}, {totalPages}, {filteredPages}, {startRow}, {endRow}, {filteredRows} and {totalRows}
              // also {page:input} & {startRow:input} will add a modifiable input in place of the value
              output: '{startRow} to {endRow} ({totalRows})',
              // apply disabled classname (cssDisabled option) to the pager arrows when the rows
              // are at either extreme is visible; default is true
              updateArrows: true,
              // starting page of the pager (zero based index)
              page: 0,
              // Number of visible rows - default is 10
              size: 25,
              // Saves the current pager page size and number (requires storage widget)
              savePages: true,
              // Saves tablesorter paging to custom key if defined.
              // Key parameter name used by the $.tablesorter.storage function.
              // Useful if you have multiple tables defined
              storageKey: 'tablesorter-pager',
              // Reset pager to this page after filtering; set to desired page number (zero-based index),
              // or false to not change page at filter start
              pageReset: 0,
              // if true, the table will remain the same height no matter how many records are displayed.
              // The space is made up by an empty table row set to a height to compensate; default is false
              fixedHeight: false,
              // remove rows from the table to speed up the sort of large tables.
              // setting this to false, only hides the non-visible rows; needed if you plan to
              // add/remove rows with the pager enabled.
              removeRows: false,
              // If true, child rows will be counted towards the pager set size
              countChildRows: false,
              // css class names of pager arrows
              cssNext        : '.next',  // next page arrow
              cssPrev        : '.prev',  // previous page arrow
              cssFirst       : '.first', // go to first page arrow
              cssLast        : '.last',  // go to last page arrow
              cssGoto        : '.gotoPage', // page select dropdown - select dropdown that set the "page" option
              cssPageDisplay : '.pagedisplay', // location of where the "output" is displayed
              cssPageSize    : '.pagesize', // page size selector - select dropdown that sets the "size" option

              // class added to arrows when at the extremes; see the "updateArrows" option
              // (i.e. prev/first arrows are "disabled" when on the first page)
              cssDisabled    : 'disabled', // Note there is no period "." in front of this class name
              cssErrorRow    : 'tablesorter-errorRow' // error information row
    	  		});
    	  	 $(table).find("thead").prepend(
    	  	        `<tr class=\"tablesorter-ignoreRow\">
                         <td class=\"pager\" colspan=\"5\">
    	  	                 <img src="../addons/pager/icons/first.png" class="first"/>
    	  	                 <img src="../addons/pager/icons/prev.png" class="prev"/>
                             <span class=\"pagedisplay\"></span> 
                             <img src="../addons/pager/icons/next.png" class="next"/>
    	  	                 <img src="../addons/pager/icons/last.png" class="last"/>
                             <select class=\"pagesize\">
                                 <option value=\"25\">25</option>
                             </select>
                         </td>
    	  	         </tr>`
    	  	        );
      });
    }
  };
})(jQuery);
