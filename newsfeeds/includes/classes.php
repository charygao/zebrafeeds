<?php


/*=======*/
/*   a subscription, a channel with metadata
*/
class Subscription {

	public $id;

	//feed title
	public $title;

	// source website address
	public $link;

	// source or user generated description
	public $description;

	//URL of the subscription file - feed
	public $xmlurl;

	// number of items to show for this subs
	public $shownItems = ZF_DEFAULT_NEWS_COUNT;

	public $position = -1;
	public $isActive = true;

	//array of strings, one entry per tag
	public $tags = array();

	public function __construct($address){
		$this->xmlurl = $address;
		$this->id = zf_makeId($this->xmlurl, '');
	}

	public function initFromXMLattributes(&$attributes) {
		$this->position = $attributes['POSITION'];

		if ($attributes['TITLE'] != '') {
			$this->title = html2specialchars($attributes['TITLE']);
		}

		if ($attributes['HTMLURL'] != '') {
			$this->link = html2specialchars($attributes['HTMLURL']);
		}

		if ($attributes['DESCRIPTION'] != '') {
			$this->description = html2specialchars($attributes['DESCRIPTION']);
		}

		if ( ($attributes['SHOWNITEMS'] != '') && (is_numeric($attributes['SHOWNITEMS'])) ) {
			$this->shownItems = $attributes['SHOWNITEMS'];
		}

		if ( ($attributes['TAGS'] != '') ) {
			$this->tags = array_unique(explode(',',html2specialchars($attributes['TAGS'])));
			//print_r($this->tags);
		}

		$this->isActive = ($attributes['ISSUBSCRIBED'] == 'yes');

	}

	public function getFeedParams(){
		$p = new FeedParams();
		$p->trimSize = $this->shownItems;
		$p->trimType = 'news';
		return $p;
	}

	public function __toString(){
		return $this->xmlurl;
	}

}

/*=======*/

class NewsItem {

	// unique id from title and desc
	public $id;

	// address and title of the news
	public $link;

	public $title;

	public $description;
	public $summary;
	public $isTruncated;
	public $isNew;
	//time stamp of the news publication if provided, or first seen if not
	public $date_timestamp;

	public $feed;

	//array of enclosure objects
	public $enclosures;

	public function __construct($feed, $link, $title, $date, $id='') {
		$this->enclosures = array();
		$this->isTruncated = false;
		$this->isNew = false;
		$this->date_timestamp = -1;
		$this->link = $link;
		$this->title = $title;
		$this->date_timestamp = $date;
		/* if GUID available, use it as basis for id */
		if (strlen($id) > 0 ) {
			$key = $id;
		} else {
			$key = $this->link.$this->title;
		}
		$this->id = zf_makeId($feed->subscriptionId, $key);
		$this->feed = $feed;
	}


	public function hasEnclosures(){
		return sizeof($this->enclosures)>0;
	}


	public function addEnclosure($enclosure) {
		array_push($this->enclosures, $enclosure);
	}

	/* returns a JSON-serializable header of this instance,
	without the publisher and enclosures */
	public function getSerializableHeader() {
		return new SerializableItemHeader($this);
	}

	/* returns a JSON-serializable header of this instance,
	including the publisher and without enclosures */
	public function getFullSerializableHeader($summary = false) {
		$header = new SerializableItemHeader($this);
		$header->subscriptionId = $this->feed->subscriptionId;
		if ($summary) $header->summary = $this->summary;
		return $header;
	}

	/* returns a JSON-serializable header of this instance,
	including the publisher and without enclosures */
	public function getSerializableItem() {
		return new SerializableItem($this);
	}
}

/*=======*/
class SerializableItemHeader {
	// unique id from title and desc
	public $id;
	// address and title of the news
	public $link;
	public $title;
	//time stamp of the news publication if provided, or first seen if not
	public $date_timestamp;
	public $isNew;

	public $summary; //might end up empty;

	// make this object out of a NewsItem object
	public function __construct($item) {
		$this->id = $item->id;
		$this->link = $item->link;
		$this->title = $item->title;
		$this->isNew = $item->isNew;
		$this->date_timestamp = $item->date_timestamp;

	}

}

/*=======*/
class SerializableItem extends SerializableItemHeader {
	public $description;
	public $enclosures;
	public function __construct($item) {

		parent::__construct($item);
		$this->description = $item->description;
		$this->enclosures = $item->enclosures;

	}

}
/*=======*/
class Enclosure {
	public $link;
	public $length;
	public $type;

	public function __construct() {
	}

	public function isImage() {
		return !(strpos($this->type, 'image') === false);
 	}

	public function isAudio() {
		return !(strpos($this->type, 'audio') === false);
	}

	public function isVideo() {
		return !(strpos($this->type, 'video') === false);
	}
}


/*=======*/
class FeedParams {
	// how to shorten the list of items
	public $trimType = 'none';
	// freshness number in days, hours or news
	public $trimSize = 0;
	public $onlyNew = false;
	public $sort = true;

	public function __construct($trimString = 'none') {
		$this->setTrimStr($trimString);
	}

	//allowed values: Xdays, Ynews,  Zhours, today,  yesterday, onlynew, auto
	public function setTrimStr($trimString) {
		switch ($trimString) {
			// auto: no change, use what was previously set
			case 'auto':
				break;
			case 'none':
				$this->trimSize = 0;
				$this->trimType = 'none';
			default:
				if (preg_match("/([0-9]+)(.*)/",$trimString, $matches)) {
		            $this->trimType = $matches[2];
		            $this->trimSize = $matches[1];
		        }
		        break;
	    }
	}

	public function getEarliestDate() {

		$earliest = 0;

		// get timestamp we don't want to go further
		if ($this->trimType == 'hours') {
			// earliest is the timestamp before which we should ignore news
			$earliest = time() - (3600 * $this->trimSize);
		}
		if ($this->trimType =='days') {
			// earliest is the timestamp before which we should ignore news

			// get timestamp of today at 0h00
			$todayts = strtotime(date("F j, Y"));

			// substract x-1 times 3600*24 seconds from that
			// x-1 because the current day counts, in the last x days
			$earliest = $todayts -  (3600*24*($this->trimSize-1));
		}

		return $earliest;
	}
}

