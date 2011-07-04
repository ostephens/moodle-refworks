This file is the README.txt for /filter/refshares

Dependencies
------------

This filter relies on the TELSTAR code, originally developed by the Open University, being installed in the Moodle installation.
More details at http://code.google.com/p/telstar

Function
--------

The filter checks for the existence of URLs for RefShare RSS feeds in the page, and replaces them with a 'styled' reference list
The styled list includes links to online versions of the references or OpenURLs as appropriate.

The style of the displayed reference list can be set by adding '#' followed by the (short) name of the RefWorks style
The names that can be used are the same as those set in the $referencestyles array in /local/references/apibib_lib.php
The 'short name' is keyed as 'string' in this array.

For example:
http://www.refworks.com/refshare/?site=039761155528000000/RWWEB106495192/Learning%20and%20Memory&rss#APA-SSU
http://www.refworks.com/refshare/?site=039761155528000000/RWWEB106495192/Learning%20and%20Memory&rss#Harvard-BS-SSU

If the list cannot be displayed for any reason, an error message is displayed (this can be modified in the accompanying lang file)
Below the error message a link to the RefShare URL will be displayed for the user to click if necessary.

Caching
-------

To improve performance, formatted versions of the refshare references are stored in the cache_filters table as follows:

id = incremental id
filter = refshares
version = 1
md5key = md5 hash of the RefShare RSS URL concatenated with the Style to be applied (using string from $referencestyles as above)
rawtext = html snippet of formatted references
timemodified = UNIX Epoch timestamp of when the cache was last updated

The Filter also has an option that can be set (via Manage Filters in Moodle admin) for how often the cache should be refreshed. Options are:
1 min
15 minutes
1 hour
1 day
1 week

If this amount of time has passed since the cached version was last updated, when the cache is requested it will attempt to update it.
If the cache cannot be updated at that time (e.g. the RefWorks API does not respond, or the RSS feed cannot be retrieved) the existing 
version will be served instead and an error logged.

N.B. SEE NOTE BELOW ON INTERACTION BETWEEN CACHE EXPIRES INTERVAL AND CRON FREQUENCY
ALSO NOTE THAT THERE IS AN OPTION TO SET A MOODLE-WIDE 'TEXT CACHE LIFETIME' FOR FILTERS.
THIS OPTION TAKE PRECEDENCE OVER THE FILTER SPECIFIC CACHE SETTING, AS THE FILTER CODE IS NOT CALLED UNLESS THE TEXT CACHE HAS EXPIRED


Cron
----

A cron job can be run regularly to update the cached versions. 
As (in Moodle 1.9.x) Filters do not have their own cron, this has to be called from /local/cron.php
To do this add the following lines to /local/cron.php (create this file if it doesn't already exist):
#########
require_once($CFG->dirroot.'/filter/refshares/cron.php');
refshares_cron();
########

The refshares_cron function does the following:
1) retrieves all cached filters
2) extracts the RefShare RSS URL and the style from the rawtext string (this is stored in a span id - see "Styling" below)
3) creates a refreshed cached version

N.B. THE CRON JOB IGNORES ANY EXPIRY TIME ON THE CACHE AND REFRESHES ALL EXISTING CACHES

Styling
-------

To enable easy styling, each set of references is wrapped in a <span> tag with a class of 'refshare'.
This <span> has a title of the RefShare RSS URL concatenated with '#' and the style string

Within a set of references, each reference is wrapped in a <span> tag with a class of 'reference'
Any notes with a reference are wrapped in a separate (i.e. not nested withing <span class="reference") <span> with class of 'reference_note'

Link Behaviour
--------------

It is possible to set (via Manage Filters in Moodle admin) whether links from references open in the current or a new window/tab.
N.B. CHANGES TO THIS OPTION WILL ONLY APPLY AS CACHED VERSIONS OF REFERENCES ARE REFRESHED

Contextual Linking
------------------

In many cases OpenURL links are created with the reference. As the filters are not specific to a course/module context in Moodle 1.9 these OpenURLs do not include the course/module ID
