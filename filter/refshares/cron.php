<?php
/**
 * Update all current cached RefShares
 *
 * @author owen@ostephens.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package filter/refshares
 */

// Retrieve all records from cache_filters where filter='refshares'
// Refresh all of them
function refshares_cron() {
	global $CFG;
	$table = 'cache_filters';
	$refshare_records = get_records($table, 'filter', 'refshares', '', '*') ;
    $refshares_updated = 0;
	foreach ($refshare_records as $rec) {
		// Need to check this search...
		$search = '/title="(http:\/\/www\.refworks\.com\/refshare\/.*?)#(.*?)"/is';
		$formatted_refs = stripslashes(htmlspecialchars_decode($rec->rawtext));
	    if (preg_match($search, $formatted_refs, $matches) == 1) {
			// grabbing refshareurl + style so that we can recache
			$refshare_url = $matches[1];
			$style = $matches[2];
			require_once($CFG->dirroot.'/filter/refshares/format_refshare.php');
			update_cached_refshare($refshare_url, $style);
		} else {
			error_log('RefShare details not found in record. ID in cache_filters table is '.$rec->id.' Text is '.$formatted_refs);
		}
		$refshares_updated += 1;
	}

	mtrace("Cron job found ".$refshares_updated." RefShare caches to update, any failures will have been written to error_log");
}

?>