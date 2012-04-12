<?php

/*
    * TC[ ] : Tournament unique identifier: the format is:
      <type><date in the format yymmdd><unique suffix, "A", "B", "C", etc...>
      type can be:
          o W : World Championship
          o E : European Championship
          o G : Pandanet Go European Cup
          o T : Any other tournaments
    * CL[ ] : Tournament class
    * EV[ ] : the title of the tournament
    * PC[ ] : the place of the tournament, in the format <country>,<city>
    * DT[ ] : the dates of the event, from the first to the last day (separated by comma), in the ISO format: yyyy-mm-dd
    * HA[ ] : the standard handicap reduction adopted, prefixed by "h"; so "h2" means "handicap dropped by 2 stones", "h0" means full handicap, "h9" always means "no handicap".
      N.B.:when the specification of handicap is at level of game (see below), it overrides the standard specification
    * KM[ ] : the komi
    * TM[ ] : the adjusted time
    * CM[ ] : comments

Legenda:

    * Tournament class:
      EGF recognizes three tournament categories:
          o class A - well organised tournament recognised by EGF member
            time limit requirements: adjusted time minimum 75 minutes, basic time minimum 60 minutes
            weight for inclusion to EGF ratings: 1.00
            In addition tournaments with handicaps in the top bar are not included in class A.
          o class B - well organized tournament recognized by EGF member
            time limit requirements: adjusted time minimum 50 minutes, basic time minimum 40 minutes
            weight for inclusion to EGF ratings: 0.75
          o class C - casual or club tournament recognized by EGF member
            time limit requirements: adjusted time minimum 30 minutes, basic time minimum 25 minutes
            weight for inclusion to EGF ratings: 0.50
    * Adjusted time:
      is calculated as:
          o Standard byoyomi - basic time + time equivalent to 45 moves.
            e.g.: basic time: 60 minutes, byoyomi: 30 seconds per move:
            60 + (45 x 0.5) = 82.5 minutes
          o Canadian byoyomi - basic time + time equivalent to 60 moves.
            e.g.: basic time: 75 minutes, byoyomi: 12 moves in 5 minutes:
            75 + (60 x (5 / 12)) = 100 minutes
      Sudden death - implying adjusted time = basic time - is acceptable, provided all other criteria are met.

If your wallist hasn't these tags, don't worry: you will be asked to give these pieces of information during the uploading process.

The other lines contain data about each player's performance. Each line must contain the following tokens, separated by blank space or tabs:

    * placement : a sequence of integer numbers starting from 1, with no repetitions (i.e.: no ex-aequo) nor sequence jumps
    * last name
    * name
    * rank declared at the beginning of the tournament
    * country (2-chars ISO code)
    * club (4 chars)
    * a variable number of columns (depending on tournament's criteria and also on the program you used), typically 3 or 4, containing the values on which placements have been calculated (MMS, SOS and so on). EGD ignores them completely
    * as many "tokens" as rounds in the tournaments with each game's result, in the format:
      <opponent><result>[/<colour>[<handicap>]]
      where:
          o opponent: is referred by his placement in the wallist
          o result: + (a win), - (a loss) or = (jigo)
          o colour: 'b/w' = black/white
          o handicap: specifies the number of handicaps (given or taken). If omitted, zero is assumed, so for instance: "2+/w" is the same as "2+/w0"
    * optionally other columns may be present with information about placement. The system ignores them.
    * if this wallist has already been processed by EGD Admin or by the previous not-so-automatic system, there is a last column with the player's PIN in this format:
      |<PIN>  (the first character is a pipe)


*/


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
