<?php
/*
Plugin Name: TablePress Extension: Rowspans everywhere
Description: Allows using rowspans in thead.
Version: 1.2.4
Author: strarsis <strarsis@gmail.com>
GitHub Plugin URI: strarsis/tablepress-rowspan-all
*/


/*
 * TablePress plugin caches the table markup (as transients).
 * If the table markup doesn't change after installing/updating this plugin,
 * the TablePress cache may has to be invalidated.
 * Either delete the TablePress-related transients,
 * all transients (as transients should only be used for temporary data),
 * or update the cache by re-saving the tables that should use 
 * this plugin by using the `outside_rowspan` trigger word.
 * 
 */


require_once 'dom_helper.php';

const TABLEPRESS_ROWSPAN         = '#rowspan#';
const TABLEPRESS_ROWSPAN_OUTSIDE = '#outside_rowspan#';


// Replace #rowspan# trigger word in table header (thead) cells with something else to preserve them 
// as they would be, although ignored by TablePress, they are still cleaned up by it
// @see https://github.com/TobiasBg/TablePress/blob/master/classes/class-render.php#L53
// (TablePress already uses #rowspan# but only between table body cells, it ignores it between table header and body cells)
function tablepress_ext_rowspan_all_trigger_words($table, $render_options) {
    // $table is a nested array

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
}
add_filter('tablepress_table_raw_render_data', 'tablepress_ext_rowspan_all_trigger_words', 10, 2);


// Add rowspan-attributes to table header cells in rendered HTML output
// TablePress-specific assumptions:
// - There is only one row (<tr></tr>) in table header (<thead></thead>).
function tablepress_ext_rowspan_all_adjust_html($output, $table, $render_options) {
    // table
    $table = fragment_to_dom($output);
    $dom   = $table->ownerDocument;
    $xpath = new DOMXpath($dom);

    // thead
    $theads = $xpath->query('//thead');
    if ($theads->length === 0)
        return $output; // skip
    $thead = $theads->item(0);

    $thead_row   = $thead->getElementsByTagName('tr')->item(0);
    $thead_cells = $thead_row->getElementsByTagName('th');


    // thead cells can also have colspans
    $thead_cells_phys = array();
    foreach ($thead_cells as $thead_cell_index => $thead_cell) {
        $colspan_val = $thead_cell->getAttribute('colspan');
        $colspan     = !empty($colspan_val) ? $colspan_val : 1; // default colspan = 1

        for($ins_col = 0; $ins_col < $colspan; $ins_col++)
            $thead_cells_phys[] = $thead_cell_index - 1; // 0-based index!
    }


    // all cells in table body that contain the outside-rowspan trigger word text,
    // sorted by their order in DOM, row order is preserved
    $trigger_cells = $xpath->query(".//td[not(ancestor::thead) and not(ancestor::tfoot) and text() = '" . TABLEPRESS_ROWSPAN_OUTSIDE . "']");

    $last_row_no = 0;
    foreach ($trigger_cells as $cell) {
        // row of cell
        $row = $xpath->query('parent::tr', $cell)->item(0);

        // only continous rows after head (2nd test)
        $row_no = dom_parent_position($row);
        if ($row_no > ($last_row_no + 1)) // when more than one row is skipped
            continue;
        $last_row_no = $row_no;

        // find thead cell above this cell (in same physical column)
        $cell_no            = dom_parent_position($cell); // cell no = column no
        $thead_cell_phys_no = $thead_cells_phys[$cell_no];
        $thead_cell         = $thead_cells->item($thead_cell_phys_no);

        // update rowspan of thead cell
        $cur_rowspan_val = $thead_cell->getAttribute('rowspan');
        $cur_rowspan     = !empty($cur_rowspan_val) ? $cur_rowspan_val : 1; // default rowspan = 1

        $thead_cell->setAttribute('rowspan', $cur_rowspan + 1);
    }


    // The following manipulations change the DOM tree,
    // this would intefere in the loop above, 
    // hence this is done in a separate step afterwards:

    foreach ($trigger_cells as $cell) {
        // move affected rows to thead
        $row = $xpath->query('parent::tr', $cell)->item(0);
        $thead->appendChild($row); // (idempotent)

        // remove the placeholder trigger word cells
        $cell->parentNode->removeChild($cell);
    }

    // change td to th for the new row
    $thead_td_cells = $xpath->query('.//td', $thead);
    foreach ($thead_td_cells as $td_cell)
        $changed_th_cell = dom_change_tagname($td_cell, 'th');


    $newHtml = dom_to_fragment($table);
    return $newHtml;
}
add_filter('tablepress_table_output', 'tablepress_ext_rowspan_all_adjust_html', 3, 10);
