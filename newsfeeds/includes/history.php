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
// ZebraFeeds history  class

/* what does this class do?
it stores the time every item was seen for the first time

*/



if (!defined('ZF_VER')) exit;

/* class for object holding the history of news items seen from a feed */

class history {


    /* array of timestamps, one per news */
    private $_timestamps;

    /* name of history file */
    private $_fileName;
    // just for logging
    private $_address;

    public function __construct($address) {

        // array index: item id
        // data, array of "timestamp of first seen", "just seen" flag
        $this->_timestamps = array();
        $this->_fileName = ZF_HISTORYDIR.'/'.md5($address).'.hst';
        $this->_address = $address;

        $this->load();

    }


    public function save(){
        $fp = @fopen( $this->_fileName, 'w' );

        if ( ! $fp ) {
            zf_debug( "History unable to open file for writing: $this->_fileName, $this->_address", DBG_SESSION);
            return 0;
        }


        $data = serialize( $this->_timestamps );
        fwrite( $fp, $data );
        fclose( $fp );
    }


    public function load(){

        if ( ! file_exists( $this->_fileName ) ) {
            zf_debug( "History file not found $this->_fileName, $this->_address", DBG_SESSION);
            return 0;
        }

        $fp = @fopen($this->_fileName, 'r');
        if ( ! $fp ) {
            zf_debug( "Failed to open history file for reading: $this->_fileName", DBG_SESSION);
            return 0;
        }

        if ($filesize = filesize($this->_fileName) ) {
        	$data = fread( $fp, filesize($this->_fileName) );
        	$this->_timestamps = unserialize( $data );
        	return 1;
    	}
            zf_debug( "Failed to open history file: $this->_fileName", DBG_SESSION);

    	return 0;

    }

    public function delete(){
        $res = unlink( $this->_fileName );

        if ( ! $res ) {
            zf_debug( "Unable to delete history file: $this->_fileName, $this->_address", DBG_SESSION);
            return false;
        }
        return true;


    }



    /* returns the timestamp of the first time the item was seen in the DB
     * called during caching of RSS object
     */
    public function getDateFirstSeen($id) {
    	zf_debug($this->_address .': getting time first seen for item- '. $id, DBG_SESSION);

        if (!isset($this->_timestamps[$id])) {

            zf_debug('New item '.$id.' (getting date of first time seen)', DBG_SESSION);

            $this->_timestamps[$id]['ts'] = time();
        }
        return $this->_timestamps[$id]['ts'];

    }

    /* mark new items and register still existing items as current
    * $items: array from the Feed object
    * $since: time stamp to tell when to go back in time from
    * $now: compare at timestamp X
    */
    public function markNewFeedItems($items, $since, $now) {

		zf_debug($this->_address .': marking items newer than: '.date('dS F Y h:i:s A', $since), DBG_SESSION);
		zf_debug('now it\'s '.date('dS F Y h:i:s A', $now), DBG_SESSION);
        // for each item
        // if item in timestamps table
        //   check 'timestamp' against 'now'
        //   if item is new
        //      calculate id
        //      mark id as seen
        //   otherwise, mark it as new
        foreach($items as $item) {

            //$id = zf_makeId('',$items[$i]['link']);
            $id = $item->id;

			zf_debug('checking item '.$id.": ".$item->title, DBG_SESSION);

			// did we have this item in our DB?
            if (isset($this->_timestamps[$id])) {
                // found in our history DB
                // it's new if it appeared after our since time stamp reference

                if (ZF_NEWITEMS != 'no') {
                    zf_debug('item last seen on '.date('dS F Y h:i:s A', $this->_timestamps[$id]['ts']), DBG_SESSION);

                    if ($this->_timestamps[$id]['ts'] - $since > 0 ) {
                        zf_debug('=> new unseen item', DBG_SESSION);
                        $item->isNew = true;
                    } else {
                    	zf_debug('=> This is old news', DBG_SESSION);
                        $item->isNew = false;
                    }
                }
                // whatever case, mark this news as current, we saw it in the feed

            } else {
                /* should happen only if items have date */
                zf_debug('Dated item marked as new: '.$item->title, DBG_SESSION);
                $this->_timestamps[$id]['ts'] = $now;
                $item->isNew= true;

            }
            $this->_timestamps[$id]['current'] = true;
        }
    }

    // takes the list of items, and delete those
    // not seen during the last marking round
    public function purge() {
        // for each ids from our table
        // if id has not been seen, delete it
        // if id has been seen, mark it as unseen for next time
        // save

        $cnt = count($this->_timestamps);
        $tmpts = $this->_timestamps;

        //echo "<code>"; print_r($tmpts); echo "</code>";
        zf_debug($cnt. " news to check for purge on " . $this->_address, DBG_SESSION);

        foreach ($tmpts as $id => $entry) {

            if (!$entry['current']) {
                //zf_debug('news is not current. deleting from history');
                unset ($this->_timestamps[$id]);
            } else {
                //zf_debug('news is current, keeping it in history');
                /* reset the 'current' flag before save, so that it comes reset at next load */
                $this->_timestamps[$id]['current'] = false;
            }

        }
        zf_debug(count($this->_timestamps). " news left after purge on " .$this->_address, DBG_SESSION);
        $this->save();

    }

}


?>
