var bstone = "stone-small-black.png";
var wstone = "stone-small-white.png"
var th_opponent = "Mot";
var th_rank = "Rank";
var th_result = "Resultat";
var th_komi = "Komi";
var th_hc = "Handikapp";

$.fn.wait = function(time, type) {
	time = time || 600;
	type = type || "fx";
	return this.queue(type, function() {
			var self = this;
			setTimeout(function() {
					$(self).dequeue();
			}, time);
	});
};

function stone_url(stone) {
	return "<img src='stone-small-"+stone+".png' alt='"+stone+"' />";
}

$(document).ready(function() {
		$('table tbody tr').mouseover(function() {
			$(this).addClass('selectedRow');
		}).mouseout(function() {
			$(this).removeClass('selectedRow');
		}).click(function(ev) {
			if(ev.detail !=1) return false;
			if($(this).hasClass('sb_nextinfo')) {
				$(this).removeClass('sb_nextinfo');
				$(this).next().remove();
			} else {
				$(this).addClass('sb_nextinfo');
				var row = $(this).attr("id").split("_")[1];
				var tds = $(this).children().length;
				var ins = this;
				var hc = false;
				var ex = "<tr class='sb_opponents'><td colspan='2'></td><td colspan='"+(tds-2)+"'>";
				
				$.getJSON('test.php?callback=?', {opponents:row , type:'json'}, function(data) {
						
					ex = ex + "<table class='sb_games' cellspacing='0'><thead><tr><th>#</th><th class='sb_won' colspan='3'>"+data.sb_strings.winner+
					     "</th><th class='sb_lost' colspan='3'>"+data.sb_strings.loser+"</th><th>"+data.sb_strings.komi+
							 "</th>";
					
					$.each(data.items, function(i, item) {
						if(item.handicap.length > 0) {
							hc = true;
						}
					})
					
					if(hc) {
						ex = ex + "<th>" + data.sb_strings.hc + "</th>";
					}
					
					ex = ex + "</tr></thead><tbody>";
					
					$.each(data.items, function(i, item) {

						if(item.opponent == item.black) { stone="<img src='" + bstone + "' alt='(b)' />"; }
						if(item.opponent == item.white) { stone="<img src='" + wstone + "' alt='(w)' />"; }

						ex = ex +
						     '<tr class="sb_'+ ((item.round % 2) ? "odd":"even")+'"><td>'  + item.round +
								 '</td><td>' + stone_url(item.winner.stone) +
						     '</td><td>' + item.winner.name +
								 '</td><td class="sb_rank">' + item.winner.rank +
								 '</td><td>' + stone_url(item.loser.stone) +
						     '</td><td>' + item.loser.name +
								 '</td><td class="sb_rank">' + item.loser.rank +
								 '</td><td class="sb_komi">' + item.komi +
								 (hc ? '</td><td>' + item.handicap : '') +
								 '</td></tr>';
					})
				
					ex = ex + "</tbody></table></td>";
					if($(ins).hasClass('sb_nextinfo')) {
							$(ins).after(ex);
					}
				});
		}
	}).wait();
})