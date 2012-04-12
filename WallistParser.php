<?php
class WLParser {
  
  // Variables used for bookkeeping
  var $score_table;
  var $last_id;        // Basicly a row_id
  var $last_place;     // Used to remember the place if two players end on the same score
  var $header;         // Array with usefull column data
  
  var $rows;           // Array with data
  var $rounds;         // Total number of rounds
  var $has_country;
  var $has_sos;
  var $has_sosos;
  var $has_sodos;
  
  var $notes;
  
  function WLParser() {
    $this->score_table = array();
    $this->rows = array();
    $this->header = array();
    $this->notes = array();
    
    $this->last_id = 1;
    $this->last_place = 1;
    $this->rounds = 0;
    $this->has_country = false;
    $this->has_sos = false;
    $this->has_sodos = false;
    $this->has_sosos = false;
  }
  
  function parse() {
    foreach($this->rows as $row) {
      $this->parse_row($this->last_id-1);
      $this->last_place = $this->score_table[$this->last_id-1]->place;
      $this->last_id++;
    }
    unset($rows);
    unset($header);
  }
  
  function extract_from_text($file) {
    $contents = split("\n",file_get_contents($file));
    $this->parse_wallist($contents);
  }
  
  function parse_wallist(&$contents) {
    $passed_header = false;
    $passed_separator = false;
    $reading_results = false;
    $results_read = false;

    foreach($contents as $line) {
      $tmp = trim(strtolower($line));
      $tmp = preg_replace("/\s+/", " ", $tmp);
      $tmp2 = trim(substr($tmp,0,8));
      $tmp_arr = array_filter(explode(" ",$tmp),"is_empty");
      $tmp3 = array();
      foreach($tmp_arr as $item) {
        $tmp3[] = $item;
      }
      
      if(($tmp3[0] == "pl" or $tmp3[0] == "pl.") and $tmp3[1] == "name") {
        $this->header = array_reverse($tmp3);
        $passed_header = true;
      }
      
      if($tmp2 == "--------") {
        $passed_separator = true;
      }
      
      if( $tmp != "" and 
          $tmp2 != "resultat" and
          $tmp2 != "nordic c" and  // Uggly hack
          $tmp2 != "--------" and
          $tmp2 != "pl name" and
          $tmp2 != "pl. name" and
          $results_read == false ) {
        $this->rows[] = array_reverse(array_filter(preg_split("/\s+/",$line),"is_empty"));
        $reading_results = true;
      }
      
      if( $tmp == "" and $reading_results ) {
        $reading_results = false;
        $results_read = true;
      }
      
      // Assume anything after an empty row is a comment
      if( $tmp != "" and $results_read ) {
        $this->notes[] = $line;
      }
    }
  }
  
  function parse_row($row_id) {
    $do_parse = true;
    $i = 0;
    $p = new Player();
    
    while($do_parse) {
      
      switch($this->header[$i]) {
        case "pt":
          $p->set_pt($this->rows[$row_id][$i]);
          break;
        case "mm":
        case "mms": 
          $p->set_mms($this->rows[$row_id][$i]); 
          break;
        case "sos": 
          $p->set_sos($this->rows[$row_id][$i]); 
          $this->has_sos = true;
          break;
        case "sosos": 
          $p->set_sosos($this->rows[$row_id][$i]);
          $this->has_sosos = true;
          break;
        case "sodos": 
          $p->set_sodos($this->rows[$row_id][$i]);
          $this->has_sodos = true;
          break;
        case "cl" : 
        case "cl." :
        case "clu": 
          $p->set_club($this->rows[$row_id][$i]); 
          break;
        case "co.":
        case "co":
          $this->has_country = true;
          $p->set_country(trim($this->rows[$row_id][$i]));
          break;
        case "str" : 
          $p->set_rank($this->rows[$row_id][$i]); 
          break;
        default:
          if(is_numeric($this->header[$i])) {
            $this->rounds = max($this->rounds,(int)$this->header[$i]);
            $result = "";
            $oponent = -1;
            if($this->rows[$row_id][$i] == "free") {
              $p->add_result($this->header[$i], new Game($this->header[$i],-1));
            }
            if($this->rows[$row_id][$i] == "--") {
              $p->add_result($this->header[$i], null);
            } else {
              sscanf($this->rows[$row_id][$i],"%d%s",$oponent,$result);
              $p->add_result($this->header[$i], new Game($this->header[$i],$oponent, $result));
            }
          } else {
            $do_parse = false;
          }
      }
      $i++;
    }
  
    $tmp = array_reverse($this->rows[$row_id]);
    
    $i=1;
    if(is_numeric($tmp[0])) {
      $p->set_place($tmp[0]);
    }
    else { 
      $p->set_place($this->last_place);
      $i=0;
    }
    
    $p->set_id($this->last_id);
    
    $name = "";
    while($tmp[$i] != $p->rank) {
      $name .= " ".$tmp[$i];
      $i++;
    }
    
    $p->set_name(trim($name));
    
    $this->score_table[] = $p;
  }
  
}

function is_empty(&$val) {
  return trim($val) != "";
}
?>
