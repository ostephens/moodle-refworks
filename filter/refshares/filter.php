<?php
/**
 * Filter to display a formatted list of references given a RefShare RSS Feed address
 *
 * @author owen@ostephens.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package filter/refshares
 */

function refshares_filter ($courseid, $text) {
/*
* To do:
* Consider having a filter option as a refshare base url so only code is needed?
* Is it possible to add tutor only setting to refresh the feeds (e.g. a button to click to trigger refresh)
*
* Filter for pattern of [refshare url]#[style]
* e.g. http://www.refworks.com/refshare/?site=015791142406000000/RWWS3A1351696/Telstar&amp;rss#harvard
*/

    global $CFG, $COURSE;

    // Do some initial checks to make sure we aren't doing unnecessary work... 
    if(empty($text)){
        return $text;
    }

	if (!is_string($text)) {
        // non string data can not be filtered anyway
        return $text;
    }

	// Checking for existence of RefShare URL
	// Form is http://www.refworks.com/refshare/?site=015791142406000000/RWWS3A1351696/Telstar&amp;rss
    if(strpos($text,'refshare')===false){
        //no refshare tag detected so return
        return $text;
    }
	
	// RefShare is in the text, so we are going ahead with the filter
	//Only require libs when we are sure we need them.
    include_once(dirname(__FILE__).'/format_refshare.php');
	
	// Make a copy so we have the original should we need it
	$newtext = $text;

	// Copying form of mediaplugin filter
	// Define search string - assumes code followed by whitespace or a html entity starting <
    $search = '/(http:\/\/www\.refworks\.com\/refshare\/[^\s<]*)/is';
    $newtext = preg_replace_callback($search, 'refshares_plugin_callback', $newtext);
	
	return $newtext;
}

function refshares_plugin_callback($refshares_url_style) {
	// $refshares_url_style is an array of matches from the text string
	// $refshares_url_style[0] and $refshares_url_style[1] are equivalent in this case
	$refshare_param = explode('#',$refshares_url_style[1]);
	//Might want to be a bit cleverer here - all special characters will have been encoded so unencode?
	// Do we want to be kind and check if has '&rss' on the end, and if not, add it?
	$refshare_rss = preg_replace('/&amp;/','&',$refshare_param[0]);
	$refshare_style = $refshare_param[1];
	// Currently format_refshare can throw exceptions - probably need to handle this differently
	// Especially if move to caching
	// $refshare_formatted = format_refshare($refshare_rss, $refshare_style);	
	$refshare_formatted = cached_refshare($refshare_rss, $refshare_style);
	if (!$refshare_formatted){
		$refshare_url = preg_replace('/&rss/','',$refshare_rss);
		return 	get_string('usererrormsg','filter_refshares').'<a href="'.$refshare_url.'">'.
		$refshare_url.'</a>.';
		
	}
	return $refshare_formatted;
}

?>