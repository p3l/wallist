<?php
/*
include '../tournament.php';
include '../wallist_parser.php';

$a = new WallistParser(file_get_contents("jusandan.txt"));
*/
include '../tournament_decorator.php';

header("Content-type: text/html; charset=UTF-8");

$a = new TournamentDecorator("wallist", file_get_contents("jusandan.txt"), "iso-8859-1", 0.75, "<img src='../star.gif' alt='*'/>");

echo "
  <script type='text/javascript' src='../jquery.js'></script>
  <script type='text/javascript' src='../scoreboard.js'></script>
	<link rel='stylesheet' type='text/css' href='../scoreboard.css' />
";

echo $a->display('utf-8');

?>
