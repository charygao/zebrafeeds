<?php
/*
 * Feed cache class for ZebraFeeds.


 Borrowed from MagpieRSS:

 * Author:		Kellan Elliott-McCrea <kellan@protest.net>
 * Version:		0.51
 * License:		GPL
 *
 */

if (!defined('ZF_VER')) exit;

require_once($zf_path . 'includes/common.php');
require_once($zf_path . 'includes/simplepie_fetch.php');


class FeedCache {
	private $BASE_CACHE = './cache';	// where the cache files are stored
	public $ERROR	   = "";		   // accumulate error messages
	private $MAX_AGE;

	static private $instance = NULL;

	private function __construct($base='') {
		if ( $base ) {
			$this->BASE_CACHE = $base;
		}

		$this->MAX_AGE = ZF_DEFAULT_REFRESH_TIME * 60;

		// attempt to make the cache directory
		if ( ! file_exists( $this->BASE_CACHE ) ) {
			$status = @mkdir( $this->BASE_CACHE, 0755 );

			// if make failed
			if ( ! $status ) {
				zf_error("Cache couldn't make dir '" . $this->BASE_CACHE . "'.");
			}
		}

	}

	static public function getInstance() {
		if (self::$instance == NULL){
            self::$instance = new FeedCache(ZF_CACHEDIR);
        }
		return self::$instance;
	}

	private function key($url) {
		return $url . ZF_ENCODING;
	}
/*=======================================================================*\
	Function:	set
	Purpose:	add an item to the cache, keyed on url
	Input:		url from wich the rss file was fetched
	Output:		true on sucess
\*=======================================================================*/
	public function set ($url, $rss) {
		$cache_file = $this->file_name( $this->key($url) );
		$fp = @fopen( $cache_file, 'w' );

		if ( ! $fp ) {
			zf_error(
				"Cache unable to open file for writing: $cache_file"
			);
			return 0;
		}


		$data = $this->serialize( $rss );
		fwrite( $fp, $data );
		fclose( $fp );

		return $cache_file;
	}

/*=======================================================================*\
	Function:	get
	Purpose:	fetch an item from the cache
	Input:		url from wich the rss file was fetched
	Output:		cached object on HIT, false on MISS
\*=======================================================================*/
	public function get ($url) {
		$cache_file = $this->file_name( $this->key($url) );

		if ( ! file_exists( $cache_file ) ) {
			zf_debug("Cache doesn't contain: $url (cache file: $cache_file)", DBG_FEED);
			return 0;
		}

		$fp = @fopen($cache_file, 'r');
		if ( ! $fp ) {
			zf_error(
				"Failed to open cache file for reading: $cache_file"
			);
			return 0;
		}

		if ($filesize = filesize($cache_file) ) {
			$data = fread( $fp, filesize($cache_file) );
			$rss = $this->unserialize( $data );

			return $rss;
		}

		return 0;
	}

/*=======================================================================*\
	Function:	check_cache
	Purpose:	check a url for membership in the cache
				and whether the object is older then MAX_AGE (ie. STALE)
	Input:		url from wich the rss file was fetched
	            MAX_AGE to check against
	Output:		HIT, STALE or MISS
\*=======================================================================*/
	public function check_cache ( $url ) {

		$age = $this->cache_age($url);

		if ( $age > -1 ) {
			if ( $this->MAX_AGE > $age ) {
				// object exists and is current
				return 'HIT';
			}
			else {
				// object exists but is old
				return 'STALE';
			}
		}
		else {
			// object does not exist
			return 'MISS';
		}
	}

	public function cache_age( $url ) {
		$filename = $this->file_name( $this->key($url) );
		if ( file_exists( $filename ) ) {
			$mtime = filemtime( $filename );
			$age = time() - $mtime;
			return $age;
		}
		else {
			return -1;
		}
	}

/*=======================================================================*\
	Function:	serialize
\*=======================================================================*/
	private function serialize ( $feed ) {
		return serialize( $feed );
	}

/*=======================================================================*\
	Function:	unserialize
\*=======================================================================*/
	private function unserialize ( $data ) {
		return unserialize( $data );
	}

/*=======================================================================*\
	Function:	file_name
	Purpose:	map url to location in cache
	Input:		url from wich the rss file was fetched
	Output:		a file name
\*=======================================================================*/
	private function file_name ($url) {
		$filename = md5( $url );
		return join( DIRECTORY_SEPARATOR, array( $this->BASE_CACHE, $filename ) );
	}



	/* update the cache for the array of subscriptions provided */
	public function update($subscriptions, $forceUpdate = false) {
		// TODO: use parallel fetch
		foreach ($subscriptions as $sub) {

			zf_debug("Checking cache for $sub->title", DBG_FEED);
			$status = $this->check_cache($sub->xmlurl);
			zf_debug("status: $status", DBG_FEED);
			$needsRefresh = ($status == 'STALE') || ($status == 'MISS');

			if ($forceUpdate || $needsRefresh ) {
				zf_debug('fetching remote file '.$sub->title, DBG_FEED);
				$feed = zf_xpie_fetch_feed($sub->id, $sub->xmlurl, $resultString);
				if ( $feed ) {
					zf_debug("Fetch successful", DBG_FEED);
					/* one shot: add our extra data and do our post processing
					  (we will here fix missing dates)
					BEFORE storing to cache */
					$feed->normalize($feedHistory);

					// add object to cache
					$this->set( $sub->xmlurl, $feed );
				} else {
					zf_debug('failed fetching remote file '.$sub->xmlurl, DBG_FEED);
					return NULL;
				}
			}

		}


	}

}

