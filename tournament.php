<?php

class TGame {
	public $round;		// Integer: Round in tournament
	public $black;		// Integer pointing to Tournament->players
	public $white;		// Integer pointing to Tournament->players
	public $komi;		// Float?
	public $handicap;	// Integer?
	public $result;		// (free|0=)|(0[+-]|0=0)|--|[0-9]+[+-](\/[w|b][2-9]?)?
						//FIXME: Add mapping to categories instead - ex. free, did not play, result
						//		 Also, how is jigo described?
	public $opponent; 	// Integer pointing to Tournament->players
	public $winner;		// Integer pointing to Tournament->players
	public $loser;		// Integer pointing to Tournament->players
	
	public function __construct() {
		//FIXME: Set default values (esp index ptr =-1)
	}
}

class TPlayer {
	public $name;
	public $country;
	public $club;
	public $strength;	// String ([0-9]{1,2}[kdp])
	public $order;		// Int (order in tournament list)
	public $rank;		// Int (place in tournament)
	public $points;		// Int
	public $victories;	// Int
	public $score;		// Array [int->float|int] with score (MMS/Score) and tiebreakers (SOS, SOSOS, SODOS)
	
	public $games;		// Array [int->TGame] with game information
	
	public function __construct() {
		$this->score = array();
		$this->games = array();
		$this->country = "";
		$this->club = "";
	}
	
	public function add_game(&$game) { $this->games[$game->round] = &$game; }
}

class Tournament {
	protected $name;
	protected $location;
	public $mode;
	protected $start_date;
	protected $stop_date;
	public $rounds;
	public $round;
	public $komi;
	public $score;
	public $notes;			// Used when parsing all Wallists
							// Sometimes notes on the tournament are provided at the end of the file
	
	public $players;		// Array of TPlayer Objects
	public $has_country;
	protected $games;		// Array of (rounds) Array of TGame Objects
	
	private $cur_pos;
	private $cur_round;
	
	public function __construct() {
		$this->has_country = false;
		$this->notes = array();
		$this->score = array();
	}
	
	public function add_player(&$player) {
		$this->players[$player->order] = &$player;
	}
	
	public function add_game(&$game) {
		$this->games[$this->cur_round][$this->cur_pos]=&$game;
	}
	
	public function set_name($name) {
		$this->name=$name;
	}
	
	public function set_location($location) {
		$this->location=$location;
	}
	public function set_mode($mode) { $this->mode = $mode; }
	public function set_rounds($nr) { $this->rounds = $nr; }
	public function set_round($nr)  { $this->round = $nr; }
	public function set_start_date($date) { $this->start_date = $date; }
	public function set_stop_date($date) { $this->stop_date = $date; }
	public function set_komi($komi) { $this->komi = $komi; }
}

?>
