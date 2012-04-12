<?php

class WallistParser {
	public $tournament;
	private $rows;
	private $last_id;
	private $last_rank;
	private $header;
	private $rounds;
	
	public function __construct(&$data) {
		$this->rows = array();
		$this->last_id = 1;
		$this->last_rank = 1;
		$this->rounds = -1;
		$this->tournament = new Tournament();
		$this->tournament->has_country = false;
		$this->tournament->mode = 'SWIZZ';		// Default mode, though MACMAHON is more common
		
		$this->filter_data($data);
		
		$i=0;
		foreach($this->rows as $row) {
			$this->parse_row($i++);
		}
	}
	
	public function get_tournament() {
		return $this->tournament;
	}
	
	private function filter_data(&$data) {
		$passed_header = false;
		$passed_separator = false;
		$reading_results = false;
		$results_read = false;
		
		// Split rows and replace whitespace other than space with space
		//foreach(split("\n", preg_replace("/[\s^\n]+/", " ",$data)) as $line) {
		foreach(split("\n", $data) as $line) {
			$tmp = trim(preg_replace("/\s+/", " ", strtolower($line)));
			
			if(!$tmp)
				break;
				
			// $rprefix is used to understand where the results section start
			$rprefix = trim(substr($tmp, 0, 8));
			// $hprefix is used to understand when the header is reached
			$hprefix = array_filter(split(" ", $tmp), "is_empty");

			if(($hprefix[0] = "pl" or $hprefix[0] == "pl.") and $hprefix[1]  == "name") {
				$this->header = array_reverse($hprefix);
				
				// Find the number of rounds in the tournament and the tournament settings
				foreach($hprefix as $col) {
					if(is_numeric($col) and ((int)$col > $this->tournament->rounds))
						$this->tournament->rounds = (int)$col;
					if($col == 'mms' or $col == 'mm')
						$this->tournament->score[1]='MMS';
						$this->tournament->mode = 'MACMAHON';
					if($col == 'sos')
						$this->tournament->score[2]='SOS';
					if($col == 'sosos')
						$this->tournament->score[3]='SOSOS';
					if($col == 'sodos')
						$this->tournament->score[3]='SODOS';
				}
				$passed_header = true;
			}
			
			// Sometimes there is a separator in the wallist files
			if($rprefix == "--------") {
				$passed_separator = true;
			}

			if(	$rprefix != "resultat" and
				$rprefix != "nordic c" and // Uggly hack (for what data?)
				$rprefix != "--------" and
				$rprefix != "pl name"  and
				$rprefix != "pl. name" and
				$results_read == false ) {
				
				$this->rows[] = array_reverse(array_filter(preg_split("/\s+/", $line), "is_empty"));
				$reading_results = true;
			}
			
			// When we reach the end of the results table
			// make sure to not try to add more
			if( $tmp == "" and $reading_results = true ) {
				$reading_results = false;
				$results_read = true;
			}
			
			// Assume that anything following the results table are comments
			if( $tmp != "" and $results_read ) {
				$this->tournament->notes[] = $line;
			}
		}
		
	}
	
	private function parse_row($row_id) {
		$do_parse =  true;
		$i = 0;
		$player = new TPlayer();
		$player->order = $row_id + 1;
		
		while($do_parse) {
			switch($this->header[$i]) {
			case "pt":
				// Nr of wins
				$player->victories = $this->rows[$row_id][$i];
				$player->points = $this->rows[$row_id][$i];
				break;
			case "mm":
			case "mms":
				// $player->score is an array 1.. with score and tiebreakers (SOS, SOSOS, SODOS)
				$player->score[1] = $this->rows[$row_id][$i];
				break;
			case "sos":
				$player->score[2] = $this->rows[$row_id][$i];
				break;
			case "sosos":
				$player->score[3] = $this->rows[$row_id][$i];
				break;
			case "sodos":
				$player->score[3] = $this->rows[$row_id][$i];
				break; 
			case "cl":
			case "cl.":
			case "clu":
				$player->club = $this->rows[$row_id][$i];
				break; 
			case "co.":
			case "co":
				$player->country = $this->rows[$row_id][$i];
				break;
			case "str":
				$player->strength = $this->rows[$row_id][$i];
				break;
			default:
				if(is_numeric($this->header[$i])) {
					// A numeric header indicates a game result
					$game = new TGame();
					$game->round = (int)$this->header[$i];
					$game->opponent = -1;
					$game->black = -1;
					$game->white = -1;
					$game->loser = -1;
					
					if($this->rows[$row_id][$i] == "free") {
						$game->result = "free";
						$game->winner = $player->order;
					} elseif($this->rows[$row_id][$i] == "--") {
						$game->result = "--";
						$game->winner = $player->order;
					} else {
						sscanf($this->rows[$row_id][$i],"%d%s",$game->opponent,$game->result);
						if($game->result == "+" or $game->result == "+!") {
							$game->winner = $player->order;
							$game->loser = $game->opponent;
						} elseif($game->result == "-" or $game->result == "-!") {
							$game->winner = $game->opponent;
							$game->loser = $player->order;
						} elseif($game->result == "=" or $game->result == "=!") {
							// Jigo
							$game->winner = $game->opponent;
							$game->loser = $game->opponent;
						}
					}
					
					$player->add_game($game);
					unset($game);
				} else {
					// Now we should have reached the name part of the wallist row
					// Time to break out of this loop and start reading from the other end of the row
					$do_parse = false;
				}
			}
			$i++;
		}
		
		$this->rows[$row_id] =  array_reverse($this->rows[$row_id]);
		$i=1;
		
		if(is_numeric($this->rows[$row_id][0])) {
			$player->rank = (int)$this->rows[$row_id][0];
			$this->last_rank = $player->rank;
		} else {
			// Player won't have a rank digit if it shares the same position as the previous player
			$player->rank = $this->last_rank;
			$i=0;
		}
		
		$name = "";
		$del = "";
		while($this->rows[$row_id][$i] != $player->strength) {
			$name .= $del.$this->rows[$row_id][$i++];
			$del = " ";
		}
		
		$player->name = $name;
		ksort($player->games);
		$this->tournament->add_player($player);
		unset($player);
	}
}

function is_empty(&$val) {
  return trim($val) != "";
}

?>
