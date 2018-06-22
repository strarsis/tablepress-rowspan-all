<?php
/*
Plugin Name: TablePress Extension: Rowspans everywhere
Description: Allows using rowspans in thead.
Version: 1.1.0
Author: strarsis <strarsis@gmail.com>
GitHub Plugin URI: strarsis/tablepress-rowspan-all
*/

require_once 'dom_helper.php';

const TABLEPRESS_ROWSPAN         = '#rowspan#';
const TABLEPRESS_ROWSPAN_OUTSIDE = '#outside_rowspan#';


// change trigger words in table header to preserve them
// (TablePress already uses #rowspan# but only between body cells, not table head + body cells)
add_filter('tablepress_table_raw_render_data', function($table, $render_options)
{
    
    // rows
    $rows = $table['data'];
    
    // more than two row for thead + potential rowspan making sense at all
    if (count($rows) <= 1)
        return $table; // skip
    
    // with a tfoot, there must be at least three rows (header, middle, footer)
    if ($render_options['table_head'] && (!$render_options['table_foot'] || $render_options['table_foot'] && count($rows) <= 2)) {
        
        $last_row_no = 0;
        for ($row_no = 1; $row_no < count($rows); $row_no++) {
            $row = $rows[$row_no];
            
            for ($col_no = 0; $col_no < count($row); $col_no++) {
                $col = $row[$col_no];
                
                // only continous rows after head
                if ($col === TABLEPRESS_ROWSPAN) {
                    if ($row_no > ($last_row_no + 1))
                        return $table; // skip table
                    
                    $last_row_no = $row_no;
                    
                    $table['data'][$row_no][$col_no] = TABLEPRESS_ROWSPAN_OUTSIDE;
                }
                
            }
            
        }
        
    }
    
    return $table;
}, 10, 2);

add_filter('tablepress_table_output', function($output, $table, $render_options)
{
    // table
    $table = fragment_to_dom($output);
    $dom   = $table->ownerDocument;
    $xpath = new DOMXpath($dom);
    
    // tablepress-specific assumptions:
    // - there is only one row (<tr></tr>) in table header (<thead></thead>)
    
    // thead
    $theads = $xpath->query('//thead');
    if ($theads->length === 0)
        return $output; // skip
    $thead = $theads->item(0);
    
    $thead_row   = $thead->getElementsByTagName('tr')->item(0);
    $thead_cells = $thead_row->getElementsByTagName('th');
    
    // thead cells can also have colspan
    // physical occupation map
    $thead_cells_phys = array();
    foreach ($thead_cells as $thead_cell_index => $thead_cell) {
        $thead_cells_phys[] = $thead_cell_index; // add at least once
        
        $thead_cell_colspan = $thead_cell->getAttribute('colspan');
        if (empty($thead_cell_colspan))
            continue; // skip cell
        for ($cs = 0; $cs < ($thead_cell_colspan - 1); $cs++)
            $thead_cells_phys[] = $thead_cell_index;
    }
    
    // all rowspan marked cells
    $trigger_cells = $xpath->query(".//td[not(ancestor::thead) and " . "not(ancestor::tfoot) and " . "text() = '" . TABLEPRESS_ROWSPAN_OUTSIDE . "']");
    
    $last_row_no = 0;
    foreach ($trigger_cells as $cell) {
        // row of cell
        $row = $xpath->query('parent::tr', $cell)->item(0);
        
        // only continous rows after head (2nd test)
        $row_no = dom_parent_position($row);
        if ($row_no > ($last_row_no + 1))
            continue;
        $last_row_no = $row_no;
        
        // find thead cell above this cell
        $cell_no            = dom_parent_position($cell);
        $thead_cell_phys_no = $thead_cells_phys[$cell_no];
        $thead_cell         = $thead_cells->item($thead_cell_phys_no);
        
        // update rowspan of cell
        $cur_rowspan = $thead_cell->getAttribute('rowspan');
        if (!$cur_rowspan)
            $cur_rowspan = 1;
        
        $thead_cell->setAttribute('rowspan', $cur_rowspan + 1);
        
        // move row to thead
        $thead->appendChild($row);
    }
    
    // remove the placeholder trigger word cells
    // in second step because removal during iteration causes issues
    foreach ($trigger_cells as $cell)
        $cell->parentNode->removeChild($cell);
    
    // change td to th for the new row
    $thead_td_cells = $xpath->query('.//td', $thead);
    foreach ($thead_td_cells as $td_cell)
        $changed_th_cell = dom_change_tagname($td_cell, 'th');
    
    
    $newHtml = dom_to_fragment($table);
    return $newHtml;
}, 3, 10);
