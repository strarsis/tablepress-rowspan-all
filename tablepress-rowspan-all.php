<?php
/*
Plugin Name: TablePress Extension: Rowspans everywhere
Description: Allows using rowspans in thead.
Version: 1.0
Author: strarsis <strarsis@gmail.com>
*/

require_once 'dom_helper.php';

const TABLEPRESS_SPAN_OUTSIDE = '#outside_rowspan#';

add_filter('tablepress_datatables_parameters', function($parameters, $table_id, $html_id, $js_options ) {
  //$parameters['orderCellsTop'] = true;
  return $parameters;
}, 10, 4);

// change trigger words in table header to preserve them
add_filter('tablepress_table_raw_render_data', function($table, $render_options) {

  // rows
  $rows = $table['data'];

  // more than two row for thead + potential rowspan making sense at all
  if(count($rows) <= 1) {
    return $table; // skip
  }

  // with a tfoot, there must be at least three rows (header, middle, footer)
  if(  $render_options['table_head'] && 
     (!$render_options['table_foot'] || $render_options['table_foot'] && count($rows) <= 2)) {

    $last_row_no = 0;
    for($row_no  = 1; $row_no < count($rows); $row_no++) {
      $row = $rows[$row_no];

      for($col_no = 0; $col_no < count($row); $col_no++) {
        $col = $row[$col_no];

        // only continous rows after head
        if($col == '#rowspan#') {
          if($row_no > ($last_row_no+1)) {
            return $table; // skip table
          }
          $last_row_no = $row_no;

          $table['data'][$row_no][$col_no] = TABLEPRESS_SPAN_OUTSIDE;
        }

      }

    }

  }

  return $table;
}, 10, 2);

// adjust tablepress markup by using the previously prepared trigger words
add_filter('tablepress_table_output', function($output, $table, $render_options) {

  // table
  $table  = fragment_to_dom($output);
  $dom    = $table->ownerDocument;
  $xpath  = new DOMXpath($dom);

  // thead
  $theads = $xpath->query("//thead");
  if($theads->length == 0) {
    return $output; // skip
  }
  // thead rows + cols
  $thead = $theads->item(0);
  $thead_row   = $dom->getElementsByTagName('thead')->item(0);
  $thead_cells = $dom->getElementsByTagName('th');

  // all #head_rowspan# cells
  $trigger_cells = $xpath->query(".//td[not(ancestor::thead) and not(ancestor::tfoot) and text() = '" . TABLEPRESS_SPAN_OUTSIDE . "']");
  $last_row_no   = 0;
  foreach($trigger_cells as $cell) {
    // row of cell
    $row    = $xpath->query("parent::tr", $cell)->item(0);

    // only continous rows after head (2nd test)
    $row_no = dom_parent_position($row);
    if($row_no > ($last_row_no+1)) {
      continue;
    }
    $last_row_no = $row_no;

    // find thead cell above this cell
    $cell_no    = dom_parent_position($cell);
    $thead_cell = $thead_cells->item($cell_no);

    // update rowspan of cell
    $cur_rowspan = $thead_cell->getAttribute("rowspan");
    if(!$cur_rowspan) {
      $cur_rowspan = 1;
    }
    $thead_cell->setAttribute("rowspan", $cur_rowspan + 1);

    // move row to thead
    $thead->appendChild($row);
  }

  // remove the placeholder trigger word cells
  // in second step because removal during iteration causes issues
  foreach( $trigger_cells as $cell ) {
    $cell->parentNode->removeChild($cell);
  }

  // change td to th for the new row
  $thead_td_cells = $xpath->query('.//td', $thead);
  foreach($thead_td_cells as $td_cell) {
    $changed_th_cell = dom_change_tagname($td_cell, "th");
  }


  $newHtml = dom_to_fragment($table);
  return $newHtml;

}, 10, 3);
