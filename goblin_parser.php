<?php

//TODO: Fix primary score () according to tournament mode. I.e. MMS/Score
//		Maybe that should be taken care of in the decorator?

class GoblinParser {
	private $tournament;
	
	private $parser;
	private $sub_root;
	private $player;
	private $game;
	
	public function __construct($data) {
		$this->tournament=new Tournament();
		$this->tournament->has_country =  true;  // hard coded for now - might add logic to handle only one country later
		
		$this->parser = xml_parser_create("");
		xml_parser_set_option($this->parser, XML_OPTION_TARGET_ENCODING, "UTF-8");
		xml_set_object($this->parser, &$this);
		xml_set_element_handler($this->parser, "tag_open", "tag_close");
		xml_set_character_data_handler($this->parser, "tag_data");
		
		xml_parse($this->parser, $data);
	}
	
	public function get_tournament() {
		ksort($this->tournament->players);
		return $this->tournament;
	}
	
	private function tag_open($parser, $name, $attrs) {
		/* - TOURNAMENTINFO
		   - RESULTS
			     PLAYER
					   ROUND
		*/
		switch($name) {
		case 'TOURNAMENTINFO':
		case 'RESULTS':
			$this->sub_root=$name;
			break;
		case 'PLAYER':
			$this->player = new TPlayer();
			$this->sub_root=$name;
			break;
		case 'SCORE':
			// ORDER 1 = MMS/Score (i.e. primary score)
			// ORDER 2 = SOS (i.e. tiebreakers)
			$this->player->score[(int)$attrs["ORDER"]] = $attrs["VALUE"];
			break;
		case 'ROUND':
			$this->game = new TGame();
			$this->game->round = (int)trim($attrs["DISPLAYVALUE"]);
			$this->sub_root=$name;
			break;
		case 'SETTINGS':
			$this->game->handicap = trim($attrs["HANDICAP"]);
			$this->game->komi = trim($attrs["KOMI"]);
			break;
		case 'RESULT':
			$opponent = -1; $result = ""; $winner = "";
			if(trim($attrs["EGF"])!= "--") {
				sscanf(trim($attrs["EGF"]), "%d%[^/]%s", $opponent, $result, $winner);
			
				$this->game->result = $result;
				$this->game->opponent = $opponent;
				$this->game->game = trim($attrs["GAME"]);
				$this->game->judged = (strchr($result, "!")?true:false);
				
				if($result == "+" or $result == "+!") {
					$this->game->winner = $this->player->order;
					$this->game->loser = $opponent;
					//$this->game->result = "+";
				} elseif($result == "-" or $result == "-!") {
					$this->game->winner = $opponent;
					$this->game->loser = $this->player->order;
					//$this->game->result = "-";
				}
				
				if($winner == "w") {
					$this->game->white = $this->player->order;
					$this->game->black = $opponent;
				} else {
					$this->game->white = $opponent;
					$this->game->black = $this->player->order;
				}
			} else {
				$this->game->result = "--";
				$this->game->game = "--";
				$this->game->winner = -1;
				$this->game->loser = -1;
				$this->game->opponent = null;
				$this->game->white = -1;
				$this->game->black = -1;
			}
			
			break;
		case 'PROPERTY':
			switch($this->sub_root) {
			case 'TOURNAMENTINFO':
				if($attrs["NAME"] == "name")      $this->tournament->set_name($attrs["VALUE"]);
				if($attrs["NAME"] == "location")  $this->tournament->set_location($attrs["VALUE"]);
				if($attrs["NAME"] == "mode")      $this->tournament->mode = strtoupper(trim($attrs["VALUE"]));
				if($attrs["NAME"] == "rounds")    $this->tournament->set_rounds($attrs["VALUE"]);
				if($attrs["NAME"] == "round")     $this->tournament->set_round($attrs["VALUE"]);
				if($attrs["NAME"] == "dateStart") $this->tournament->set_start_date($attrs["VALUE"]);
				if($attrs["NAME"] == "dateEnd")   $this->tournament->set_stop_date($attrs["VALUE"]);
				if($attrs["NAME"] == "komi")      $this->tournament->set_komi($attrs["VALUE"]);
				if($attrs["NAME"] == "scores") {
					if($this->tournament->mode == "MACMAHON" and (int)$attrs["VALUE"] == 1)
						$this->tournament->score[1] = "MMS";
					else {
						$tmp = explode(".",$attrs["DISPLAYVALUE"]);
					  $this->tournament->score[(int)$attrs["VALUE"]] = $tmp[1];
					}
				}
				break;
			case 'PLAYER':
				if($attrs["NAME"] == "name") $this->player->name = trim($attrs["VALUE"]);
				if($attrs["NAME"] == "country") $this->player->country = trim($attrs["VALUE"]);
				if($attrs["NAME"] == "club") $this->player->club = trim($attrs["VALUE"]);
				if($attrs["NAME"] == "strength") $this->player->strength = trim($attrs["VALUE"]);
				if($attrs["NAME"] == "order") $this->player->order = (int)trim($attrs["DISPLAYVALUE"]);
				if($attrs["NAME"] == "rank") $this->player->rank = (int)trim($attrs["VALUE"]);
				if($attrs["NAME"] == "points") $this->player->points = (int)trim($attrs["VALUE"]);
				if($attrs["NAME"] == "victories") $this->player->victories = (int)trim($attrs["VALUE"]);
				break;
			default:
			}
		default:
		}
	}
	
	private function tag_close($parser, $name) {
		switch($name) {
		case 'PLAYER':
			ksort($this->player->games);
			$this->tournament->add_player($this->player);
			unset($this->player);
			break;
		case 'ROUND':
			$this->player->add_game($this->game);
			unset($this->game);
			break;
		default:
		}
	}
	
	private function tag_data($parser, $data) {
	}
}

?>
