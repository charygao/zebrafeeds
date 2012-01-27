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
// ZebraFeeds RSS fetch layer, SimplePie version


if (!defined('ZF_VER')) exit;

require_once($zf_path . 'includes/simplepie.inc');
require_once($zf_path . 'includes/feed.php');



/*
module interface entry point
returns a feed object
 */
function &zf_xpie_fetch_feed(&$channeldata, &$resultString) {
    
	$myfeed = null;

 
    $SP_feed = new SimplePie();

    $SP_feed->set_feed_url($channeldata['xmlurl']);
    // check here according to refresh time
    $SP_feed->enable_cache(false);
    $SP_feed->enable_order_by_date(false);
    $SP_feed->set_timeout(20);
    $SP_feed->set_useragent(ZF_USERAGENT);
    $SP_feed->set_stupidly_fast(true);
    $SP_feed->force_feed(true);
    //$SP_feed->force_fsockopen(true);
    //set cache duration, set cache location
    $SP_feed->init();
    $SP_feed->handle_content_type();

    if ($SP_feed->data) {
    
        $myfeed = new feed();
        
        $myfeed->channel['title'] = $SP_feed->get_title();
        $myfeed->channel['xmlurl'] = $channeldata['xmlurl'];
        $myfeed->channel['link'] = $SP_feed->get_link();
        $myfeed->channel['description'] = $SP_feed->get_description();
        $myfeed->channel['favicon'] = $SP_feed->get_favicon();
        $myfeed->channel['logo'] = $SP_feed->get_image_url();
        $index=0;

        foreach($SP_feed->get_items() as $item) {
            $myfeed->items[$index]['link'] = $item->get_permalink();
            $myfeed->items[$index]['title'] = $item->get_title(); 
            $myfeed->items[$index]['date_timestamp']  = $item->get_date('U');
		    $myfeed->items[$index]['description'] = $item->get_content();		    
		    //$myfeed->items[$index]['summary'] = $item->get_description(); 
            $encidx = 0;
            $enc = $item->get_enclosures();
            if (is_array($enc)) {
				foreach ($enc as $enclosure) {
					$myfeed->items[$index]['enclosures'][$encidx]['link'] = $enclosure->get_link();
					$myfeed->items[$index]['enclosures'][$encidx]['length'] = $enclosure->get_length();
					$myfeed->items[$index]['enclosures'][$encidx]['type'] = $enclosure->get_type();
					$encidx++;
            	} 
            }
            $index++;
        }

        /* metadata */
        $myfeed->last_fetched = time();
    } else {
		if ($SP_feed->error()) {
			$resultString = $SP_feed->error() . " on ".$channeldata['xmlurl'];
		} else $resultString = 'Error fetching or parsing '.$channeldata['xmlurl'];

    }


    return $myfeed;


}

?>
