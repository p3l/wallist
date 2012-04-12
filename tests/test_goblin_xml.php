<?php

include '../tournament_decorator.php';

header("Content-type: text/html; charset=ISO-8859-1");

$a = new TournamentDecorator("goblinxml", file_get_contents("SM2008.xml"), "UTF-8", 0.75, "<img src='../star.gif' alt='*'/>");

echo "
  <script type='text/javascript' src='../jquery.js'></script>
  <script type='text/javascript' src='../scoreboard.js'></script>
	<link rel='stylesheet' type='text/css' href='../scoreboard.css' />
";

echo $a->display('iso-8859-1');
?>
