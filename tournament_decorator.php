<?php

include 'tournament.php';

class TournamentDecorator {
	private $resource;
	private $data;
	private $tournament;
	private $last_place;
	private $limit;
	private $marker;
	
	private $countries;
	
	
	public function __construct($type, $data, $input_encoding, $limit=0.75, $marker='*') {
//		$this->resource = $resource;
//		$this->ref = $ref;
		$this->limit = $limit;
		$this->marker = $marker;
		$this->countries = array();
		$parser = null;
		
		switch($type) {
		case "goblinxml":
			include 'goblin_parser.php';
			$parser = new GoblinParser(iconv($input_encoding, 'UTF-8', $data));
			$this->tournament = $parser->get_tournament();
			break;
		case "wallist":
			include 'wallist_parser.php';
			$parser = new WallistParser(iconv($input_encoding, 'UTF-8', $data));
			$this->tournament = $parser->get_tournament();
			break;
		default:
			break;
		}
		unset($parser);
		
		// If this is a query of some sort...
		//   Reply and exit(0).
		if(isset($_GET["opponents"])) {
			switch(strtoupper($_GET["type"])) {
				case "JSON":
					$this->json_get_opponents($_GET["opponents"], $_GET["callback"]);
					break;
				default:
			}
			exit(0);
		}
	}
	
	private function negate_result($res) {
		switch($res) {
		case "-": return "+";
		case "+": return "-";
		case "!+": return "!-";
		case "!-": return "!+";
		default:
			return $res;
		}
	}
	
	function json_get_opponents($id,$callback="") {
		date_default_timezone_set('UTC');
		$f = stat($this->file);
		$p = &$this->tournament->players;
		$ret = array();
		$items = array();
		$ret = array(
			"title"       => "Opponents in tournament",
			"description" => "",
			"modified"    => date( DATE_ATOM, $f["mtime"] ),
			"generator"   => "http://goforbundet.se",
			"sb_strings"  => array(
			"loser"    => "Förlorare",
			"winner"   => "Vinnare",
			"rank"     => "Rank",
			"komi"     => "Komi",
			"hc"       => "Handikapp")
			//"sgf"    => "sgf")
			);
		foreach($p[$id]->games as $g) {
			$win = $p[$g->winner];
			$lose = $p[$g->loser];
			$items[] = array(
				"round"    => $g->round,
				"rank"     => $op->strength,
				"winner"   => array( "name" => $win->name, "rank" => $win->strength, "stone" => ($g->white ? ($g->winner==$g->white?"white":"black") : "")),
				"loser"    => array( "name" => $lose->name, "rank" => $lose->strength, "stone" => ($g->white ? ($g->loser==$g->white?"white":"black") : "")),
				"white"    => $p[$g->white]->name,
				"black"    => $p[$g->black]->name,
				"handicap" => $g->handicap,
				"komi"     => $g->komi
				);
		}
		$ret["items"]=$items;
		
		if($callback)
			echo $callback."(";
		
		echo json_encode($ret);
		
		if($callback)
			echo ")";
	}
	
	function print_int($str,$type) {
    $str = trim($str);
    $dec = "";
    $int = $str;
    $tmp_s = strrev($str);
    if( substr($tmp_s,0,1) == "œ" ) {
      $int=substr($str,0,strlen($str)-1);
      $dec = "½";
    }
    if( substr($tmp_s,0,3) == "2/1" ) {
      $int=substr($str,0,strlen($str)-3);
      $dec = "½";
    }
    if( !strcmp(substr($tmp_s,0,2), "5.") ) {
      $int=substr($str,0,strlen($str)-2);
      $dec = "½";
    }
    echo "<td class='wall_".$type."'>".$int."</td>";
    if(substr($type,0,5)=="round") {
      echo "<td class='wall_round_dec'>".$dec."</td>";
    } else {
      echo "<td class='wall_".$type."_dec'>".$dec."</td>";
    }
  }

	public function display($encoding="") {
		ob_start();
		echo "<table cellspacing='0' class='wall_table'>\n".
		     "<thead>\n".
		     "  <tr class='wall_row_header'>\n".
		     "    <th>&nbsp;</th> <th>#</th> <th class='wall_header_name'>Namn</th>\n";
		if($this->tournament->has_country) {
			echo "    <th class='wall_header_country'>Land</th>\n";
		}
		echo "    <th class='wall_header_club'>Klubb</th>\n".
		     "    <th class='wall_header_rank'>Rank</th>\n";
				 
		switch(strtolower($this->tournament->score[1])) {
		case "mms":
			echo "    <th class='wall_header_mms' colspan='2'><acronym title='McMahon Score'>MMS</acronym></th>\n";
			break;
		default:
			echo "    <th class='wall_header_score' colspan='2'>".$this->tournament->score[1]."</th>\n";
		}
		
	  for($i=1; $i<=$this->tournament->rounds; $i++) {
			echo "    <th class='wall_header_round' colspan='2'>$i</th>\n";
		}
		echo "    <th class='wall_header_pt'><acronym title='Poäng'>Pt.</acronym></th>\n";
		
		if(count($this->tournament->score)>1) {
			for($i=2; $i<=count($this->tournament->score); $i++) {
				$css  = "";
				$desc = "";
				switch(strtolower($this->tournament->score[$i])) {
				case 'sos':
					$css="sos";
					$desc="Sum of Opponents Score";
					break;
				case 'sosos':
					$css="sosos";
					$desc="Sum of Opponents SOS";
					break;
				default:
					echo $this->tournament->score[$i];
				}
				if($css) {
					echo "    <th class='wall_header_$css' colspan='2'><acronym title='$desc'>".$this->tournament->score[$i]."</acronym</th>\n";
				}
			}
		}
		
		echo "  </tr>\n";

		$limit = $this->tournament->round*$this->limit;
		echo "</thead>\n";
		echo "<tbody>\n";
		
		foreach($this->tournament->players as $p) {
			echo "  <tr id='sb_".($p->order)."' class='wall_row_".(($p->order%2)?"odd":"even")."'>\n";
			echo "    <td>";
			if((float)$p->victories > $limit) {
				echo $this->marker;
			}
			echo "</td>\n";
			echo "    <td class='wall_place'>";
			if($this->last_place != $p->order) {
				echo $p->order;
				$this->last_place = $p->order;
			} 
			echo "</td>\n";
			echo "    <td class='wall_name'>".$p->name."</td>\n";
			if($this->tournament->has_country) {
				echo "    <td class='wall_country'>";
				if(isset($this->countries[strtolower($p->country)]))
					 echo "<acronym title='".$this->countries[strtolower($p->country)]."'>".$p->country."</acronym>";
				 else
				   echo $p->country;
				echo "</td>\n";
			}
			echo "    <td class='wall_club'>";
      if(isset($this->clubs[$p->club]))
        echo "<acronym title='".$this->clubs[$p->club]."'>".$p->club."</acronym>";
			else
			  echo $p->club;
      echo "</td>\n";
      echo "    <td class='wall_rank'>".$p->strength."</td>";
			
			if($this->tournament->mode == 'MACMAHON') {
				$this->print_int($p->score[1], 'mms');
			}

			foreach($p->games as $g) {
				echo "    <td class='wall_round_result'><acronym class='wall_round_acronym' title='";
        // If the player did not play this round
        if($g == null) {
          // The last table cell is for exclamation marks - pure eyecandy
          echo "Stod över ronden'>--&nbsp;</acronym></td><td></td>\n";
        } 
        // If the player had a automatic win (odd number of players)
        if($g !=null and $g->opponent == -1) { 
          echo "Automatisk vinst'>free</acronym></td><td></td>";
        } elseif($g !=null) {
          switch($g->result) {
            case "+": echo "Vinst mot"; break;
            case "-": echo "Förlust mot"; break;
            case "=": echo "Jigo mot"; break;
            case "+!": echo "Tilldömd vinst mot"; break;
            case "-!": echo "Tilldömd förlust mot"; break;
            case "=!": echo "Tilldömd jigo"; break;
			case "--": echo "Stod över ronden"; break;
			case "free": echo "Frirond"; break;	//FIXME: Needs adressing in the output below.. substr(,0,1)
            default:
            echo "Okänt resultat (".$g->result.") mot";
          }
          echo " ".$this->tournament->players[$g->opponent]->name." [".
               $this->tournament->players[$g->opponent]->strength."]".
               "'>".$g->opponent.substr($g->result,0,1)."</acronym></td>";
          if(strlen($g->result)>1)
            echo "<td class='wall_round_result_judged'>!</td>";
          else
            echo "<td></td>\n";
        }
			}
			
			echo "    <td class='wall_pt'>".$p->points."</td>";
			if(count($p->score)>1) {
				for($i=2; $i<=count($p->score); $i++) {
					echo "    ";
					$this->print_int($p->score[$i], "round_".strtolower($this->tournament->score[$i]));
					echo "\n";
				}
			}
			echo "  </tr>\n";
		}
	  echo "</tbody>\n";
		echo "</table>\n";
		echo "<p><a href='".$this->file."'>Källdata</a></p>\n";
		if($encoding) { 
		  return iconv("UTF-8", $encoding, ob_get_clean());
		}	else {
		  return ob_get_clean();
		}
	}
}

?>
