<?php
/**
 * Update all current cached RefShares
 *
 * @author owen@ostephens.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package filter/refshares
 */

// Force update of single cached RefShare RSS Feed
// Just a draft version
// Problem is that anyone can do this at the moment I think - no check on login etc...

// Copied some stuff from cron.php
if (!isset($_SERVER['REMOTE_ADDR']) && isset($_SERVER['argv'][0])) {
    chdir(dirname($_SERVER['argv'][0]));
}
require_once(dirname(__FILE__) . '/../../config.php');

// Should be all set now
global $CFG;
require_once($CFG->dirroot.'/filter/refshares/format_refshare.php');
// If move to post will need to handle URL decode I think
$refshare_url = $_GET['refshare_url'];
$style = $_GET['refshare_style'];
update_cached_refshare($refshare_url, $style);
?>