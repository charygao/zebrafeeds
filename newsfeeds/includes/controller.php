<?php
// ZebraFeeds - copyright (c) 2006 Laurent Cazalet
// http://www.cazalet.org/zebrafeeds
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
//
//
// ZebraFeeds user functions.
// For the case where ZF_USEOPML is set to no.
// allow to manually configure the output

if (!defined('ZF_VER')) exit;


/* manual adding feeds to avoid using opml */
function zf_addFeed($feedUrl, $showedItems, $refreshTime) {
//TODO: adapt to new classes
    global $zf_aggregator;
    $i = count($zf_aggregator->channels);
    $zf_aggregator->channels[$i]['xmlurl'] = $feedUrl;
    $zf_aggregator->channels[$i]['showeditems'] = $showedItems;
    $zf_aggregator->channels[$i]['refreshtime'] = $refreshTime;
    // make sure it is taken when looping to find out which feeds to display
    $zf_aggregator->channels[$i]['issubscribed'] = "yes";
    $zf_aggregator->channels[$i]['position'] = $i;

}

/* init=restart page */
function zf_init() {
    zf_reset();
}

/* restart page */
function zf_reset() {
    global $zf_aggregator;
    unset($zf_aggregator);
    $zf_aggregator = new aggregator();
}

/* pick our template */
function zf_useTemplate($templateName) {
    global $zf_aggregator;
    $zf_aggregator->useTemplate($templateName);

}

function zf_groupByDate() {
    global $zf_aggregator;
    $zf_aggregator->setViewMode('date');
}

function zf_trim($size=12, $mode='hours') {
    global $zf_aggregator;
    //automatic now $zf_aggregator->setViewMode('trim');
    $zf_aggregator->setTrimOptions($mode, $size);
}


function zf_useList($listName) {
    global $zf_aggregator;
	$zf_aggregator->useList($listName);
}

/* filter output according to a keyword */
function zf_match($expression) {
    global $zf_aggregator;
    $zf_aggregator->matchNews($expression);

}

/* Main function: display the page
called in either case: automatic or not
at this point, all parameters are supposed to be present in
the zf_aggregator object. otherwise, take configured defaults
*/
function zf_renderView() {
    global $zf_aggregator;

    // if we still don't have a template, get one.
    //TODO: add check function in aggregator
    if ($zf_aggregator->_template == null) {
        $zf_aggregator->useTemplate(zf_getDisplayTemplateName());
    }


	//TODO: move this to aggregator "handleOnlineParameters"
    /* for viewmode, trim, match, HTTP parameters take precedence over what
    is configured, either in admin page or by script*/

    // if HTTP parameter requires a special 'group by' mode
    if (!empty($_GET['zfviewmode'])) {
        $zf_aggregator->setViewMode($_GET['zfviewmode']);
    }

    // what do we match?
    if (isset($_GET['zfmatch']) && (strlen($_GET['zfmatch']) > 0)) {
        $zf_aggregator->matchNews($_GET['zfmatch']);
    }
    // what do we trim to?
    if (!empty($_GET['zftrim'])) {
		$zf_aggregator->setTrimString($_GET['zftrim']);
    }


    $zf_aggregator->printMainView();


}


?>
