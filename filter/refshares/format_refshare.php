<?php
/**
 * Retrieve a formatted set of references in a RefShare feed in specified style
 *
 * @author owen@ostephens.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package filter/refshares
 */

/*
 * TO DO
 * Caching
 *		Unless we start storing a cached version per course then including the course context in linking doesn't make sense....
 *		... also not sure it is currently working!
 * Need to refactor - e.g. see code duplication between cached_refshare and cache_refshare functions
 * 					Probably need to make more assumptions, or separate out functions?
 * Think about putting default reference style and sort order into options?
 * Maybe need to separate out check to see if style exists and set to default - so can be reused across functions
 * Add a 'last updated' note to formatted references
*/

function cached_refshare($refshare_url, $refshare_style) {
	global $CFG;
	// Using cache_filters table to store cached version of styled references
	$table = 'cache_filters';
	
	// Check style exists, and if not set to default
	$style = check_style($refshare_style);

	// Need entries to be unique for each RefShare feed/Style combo
	$md5key = md5($refshare_url.$style);
	
	// first check if refshare is cached in the table at all
	if ($exists = get_record($table,'filter','refshares','md5key',$md5key)) {
		// Record exists, need to check if cache is valid, otherwise refresh?
		if (time()-$exists->timemodified < $CFG->filter_refshares_cacheexpires) {
			// Cache not expired, so can return directly
			$formatted_refs = stripslashes(htmlspecialchars_decode($exists->rawtext));
		} else {
			// Need to update refshare
			$formatted_refs = update_cached_refshare($refshare_url, $style);
		}
		return $formatted_refs;
	} else {
		// Record does not exist, needs creating for first time - call 'cache_refshare' function
		// Will be slow....
		cache_refshare($refshare_url, $style);
		// Now check if exists, if not then return an error
		if ($exists = get_record($table,'filter','refshares','md5key',$md5key)) {
			$formatted_refs = stripslashes(htmlspecialchars_decode($exists->rawtext));
			return $formatted_refs;
		}
		else {
			error_log('Could not retrieve cached references for '.$refshare_url.'#'.$style.', even after trying to create');
			return false;
		}
	}
}

function cache_refshare($refshare_url, $refshare_style) {
	// Using cache_filters table to store cached version of styled references
	$table = 'cache_filters';
	
	// Check style exists, and if not set to default
	$style = check_style($refshare_style);

	// Need entries to be unique for each RefShare feed/Style combo
	$md5key = md5($refshare_url.$style);

	// Create cache of refshare rss feed in specified style for the first time
	if ($exists = get_record($table,'filter','refshares','md5key',$md5key)) {
		// Already a record
		$formatted_refs = stripslashes(htmlspecialchars_decode($exists->rawtext));
	} else {
		// Doesn't exist, going to create
		$newrec = new stdClass();
		$newrec->filter = 'refshares';	
		$newrec->md5key = $md5key;
		$newrec->version = 1;
		$formatted_refs = format_refshare($refshare_url, $style);
		$newrec->rawtext = addslashes(htmlspecialchars($formatted_refs));
        $newrec->timemodified = time();
        
		insert_record($table, $newrec);
	}
	return $formatted_refs;
}

function update_cached_refshare($refshare_url, $refshare_style) {
	$table = 'cache_filters';
	
	// Check style exists, and if not set to default
	$style = check_style($refshare_style);

	// Need entries to be unique for each RefShare feed/Style combo
	$md5key = md5($refshare_url.$style);
	
	// Check the record exists
	if ($exists = get_record($table,'filter','refshares','md5key',$md5key)) {
		// Only want to go ahead if we can get updated version
		// Otherwise write an error
		if($formatted_refs = format_refshare($refshare_url, $style)) {
			$updaterec = new stdClass();
			$updaterec->id = $exists->id;
			$updaterec->rawtext = addslashes(htmlspecialchars($formatted_refs));
	        $updaterec->timemodified = time();
			update_record($table, $updaterec);
		} else {
			$errormsg = 'Unable to update cache, using existing cache for '.$refshare_url.'#'.$style.'. See error_log for more details';
			error_log($errormsg);
			$formatted_refs = stripslashes(htmlspecialchars_decode($exists->rawtext));
		}
	} else {
		// Doesn't exist, check if we can create
		if($formatted_refs = format_refshare($refshare_url, $style)) {
			$newrec = new stdClass();
			$newrec->filter = 'refshares';	
			$newrec->md5key = $md5key;
			$newrec->version = 1;
			$formatted_refs = format_refshare($refshare_url, $style);
			$newrec->rawtext = addslashes(htmlspecialchars($formatted_refs));
	        $newrec->timemodified = time();

			insert_record($table, $newrec);
		} else {
			$errormsg = 'Unable to retrieve styled RefShare for '.$refshare_url.'#'.$style.', and there is no cached version. See error_log for more details';
			error_log($errormsg);
			// What should we return here?
			return $errormsg;
		}
	}
	return $formatted_refs;
}

function format_refshare($refshare_url, $refshare_style) {
	// Ideally we'd check for a cached (where? db table?) copy here now - in appropriate style
	// The rest of the script below would kick in only if ...
	// cronjob
	// a forced update (how?) [why not do it from the 'preview' function in MyReferences? Sounds good]
	// Other?
	// and results written to cache (then called for display?)
	//
	// But could leave in hook to allow use of filter without db table - check for existence of table first
	// if refworks_collab_refshares table doesn't exist, then just do filter on fly - but note performance will not be good...
	global $CFG;
	require_once($CFG->dirroot.'/local/references/convert/refxml.php');
	require_once($CFG->dirroot.'/local/references/apibib/apibib_lib.php');
	require_once($CFG->dirroot.'/local/references/linking.php');
	// Retrieve feed using curl
	$c = curl_init($refshare_url);
    curl_setopt($c, CURLOPT_HTTPGET, true);

    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($c, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
    curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 15);

    //manual header
    $header[] = "Content-type: text/xml";
    curl_setopt($c, CURLOPT_HTTPHEADER, $header);

    //set proxy details, use either values sent to method, or moodle values
    if(isset($CFG->proxyhost)){
        //setup  class with proxy that moodle is using
        if($CFG->proxyhost!=''){
            curl_setopt($c, CURLOPT_PROXY, $CFG->proxyhost);
            if($CFG->proxyport!=''){
                curl_setopt($c, CURLOPT_PROXYPORT, $CFG->proxyport);
            }
        }
    }
    curl_setopt($c, CURLOPT_HTTPPROXYTUNNEL, false);
    $returnedarray = array(); // for results
    $page = curl_exec($c);

    if ($page == false) {
		$errormsg = 'Cannot parse RefWorks RSS feed (cannot load into DOM). Results from fetching feed were: ';
		$errormsg .= curl_getinfo($c,CURLINFO_HTTP_CODE);
		error_log($errormsg);
		return false;
    }else{
		if(strpos($page,'<?xml')===false){
			$errormsg = 'Cannot parse RefWorks RSS feed (does not seem to be xml)';
			error_log($errormsg);
			return false;
        }
    }

    curl_close ($c);

	//instance of ref xml management class
	$refman=new refxml();

	//load feed into dom + check
	$dom = new DOMDocument();
	$dom->preserveWhiteSpace = false;
	$dom->formatOutput = false;

	if(!$dom->loadXML($page)){
		$errormsg = 'Cannot parse RefWorks RSS feed (cannot load into DOM). Results from fetching feed were';
		error_log($errormsg);
		return false;
	}

	if($refman->test_data_type($dom,'RefWorksRSS')===false){
		$errormsg = 'Cannot parse RSS feed (Does not seem to be a RefWorks RSS)';
		error_log($errormsg);
		return false;
	}

	//get title (of RSS feed) + items
	$title = $dom->getElementsByTagName('title')->item(0)->nodeValue;
	
	//Step 1 - 
	//Convert rss feed into a reference data xml (RefWorks XML)
	//First check if we can convert the data from rss xml to a standard format
	$refxml=false;
	//when converting rss, sort by author
	//perhaps this should go into a filter option?
	$refxml=$refman->return_transform_in($dom,'RefWorksRSS','creator');
	//clear initial data vars, as no longer needed.
	unset($dom);
	unset($xpath);
	if($refxml===false) {
		$errormsg = 'Error processing RefWorks RSS feed';
		error_log($errormsg);
		return false;
	}
	
	// Step 2 - 
	// Make sure style exists, and if not use default
	$style = check_style($refshare_style);

	$refstring=$refman->return_references($refxml,true);
	$titles=apibib::getbib($refstring, $style,'RefWorks XML');
	if(!is_array($titles)){
		$titles=array();
	}

	//Step 3 - get data for each item
	$alldata=$refman->return_reference($refxml,array(),true,true);
	if($alldata===false){
		$alldata=array();
	}



	//Step 4 - 
	//Work out weblink for each item
	//work out if we need to send the course name to the link as this is used to rack where links come from when using OpenURL
	$coursename = '';
	/*
	########
	######## Not currently including course context as filter is cached and could be re-used in any course context
	######## Therefore including course context doesn't make sense
	######## Also Cron does not have a course context for a filter
	########
	if(isset($COURSE->id) && $COURSE->id == SITEID) {
		//in cron job - don't know course id, so get from db using $modid
		if($modid>0){
			if($courseid=get_field('resourcepage','course','id',$modid)){
				$coursename = get_field('course','shortname','id',$courseid);
			}
		}
	}
	*/

	//Step 5 - 
	//Create html containing styled and linked references
	$formatted_refs = '<span class="refshare" title="'.$refshare_url.'#'.$refshare_style.'">';
	$count = count($alldata);
	for ($i=0;$i<$count;$i++){
		$data = $alldata[$i];
		$document=DOMDocument::loadXML($data); //prepare for creation of weblink
		$linking = linking::create_link($document, $coursename); //create weblink
		$title = $titles[$i];
		$desc = '';
		$notes = $document->getElementsByTagName('no');
		if($notes->length >0){
			$noteval = $notes->item(0)->nodeValue;
			$desc = clean_text($noteval, FORMAT_HTML).'<br>';
		}
		
		$weblink=$linking[0];
		
		$formatted_refs .= '<span class="reference"><a href="'.$weblink.'"';
							'" target="_blank">'.$title.'</a></span><br><span class="reference_note">'.$desc.
							'</span><br>';
		if ($CFG->filter_refshares_linkbehaviour === 'newwin') {
			$formatted_refs .= ' target="_blank"';
		}
		$formatted_refs .= '>'.$title.'</a></span><br><span class="reference_note">'.$desc.'</span><br>';
	}
	$formatted_refs .= '</span>';

	// Going to return the html we are looking for....
	return $formatted_refs;
	
}

function check_style ($refshare_style) {
	// Check given style exists in $referencestyles array specified in local/references/apibib_lib.php
	// Convert to lower case before checking to be forgiving
	// Otherwise use the first one...
	global $CFG;
	require_once($CFG->dirroot.'/local/references/apibib/apibib_lib.php');
	
	$style = '';
	foreach(apibib::$referencestyles as $style) {
		if (strtolower($refshare_style) === strtolower($style['string'])) {
			$style = $style['string'];
			break;
		}
	}
	
	if(!$refshare_style=$style) {
		$style = apibib::$referencestyles[0]['string'];
	}
	
	return $style;
	
}

?>