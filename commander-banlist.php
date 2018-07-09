<?php

// Banlists
$banlists = array(
	'Multiplayer' => array(
		'cache' => 'cache/banned/mtgcommander.html',
		'title' => 'MTG Commander',
		'url'   => 'http://mtgcommander.net/rules.php',
		'match' => function($html) {
			preg_match(
				'/\<font size\=\+1\>Banlist\<\/font\>.+?\<table width\=100\%\>.+?\<\/table\>/',
				$html, $table
			);
			preg_match_all(
				'/\<li\><a .*?href=\"http\:\/\/deckbox.org\/mtg\/.+?\"\>(.+?)\<\/a\>/',
				$table[0], $cards
			);
			return $cards[1];
		}
	),
	'MTGO 1v1' => array(
		'cache' => 'cache/banned/mtgo.html',
		'title' => 'Magic the Gathering Online, 30 Life',
		'url'   => 'https://magic.wizards.com/en/game-info/gameplay/rules-and-formats/banned-restricted/magic-online-commander',
		'match' => function($html) {
			$html = substr($html, 0, strpos($html, '<h1>Multiplayer-Pointed Bans</h1>'));
			preg_match_all(
				'/\<a href=\"http\:\/\/gatherer.wizards.com\/Pages\/Card\/Details\.aspx\?name\=.+?\".*?\>(.+?)\</',
				$html, $cards
			);

			// Add cards not in MTGO.
			$cards[1] = array_merge($cards[1], array('Chaos Orb', 'Falling Star', 'Shahrazad'));
			return $cards[1];
		}
	),
	'Duel 1v1' => array(
		'cache' => 'cache/banned/duelcommander.html',
		'title' => 'Duel Commander, 20 Life',
		'url'   => 'http://www.duelcommander.com/banlist/',
		'match' => function($html) {
			preg_match_all(
				'/\<a href=\"http\:\/\/gatherer.wizards.com\/Pages\/Card\/Details\.aspx\?multiverseid\=\d+\".*?\>(.+?)\</',
				$html, $cards
			);
			return array_map(
				function($value) {
					return str_replace(array('&#8217;', 'Divning'), array('\'', 'Divining'), $value);
				},
				$cards[1]
			);
		}
	),
	'WotC'   => array(
		'cache' => 'cache/banned/commander.html',
		'title' => 'Wizards of the Coast',
		'url'   => 'http://magic.wizards.com/en/gameinfo/gameplay/formats/commander',
		'match' => function($html) {
			preg_match(
				'/\<p\>[^\:]*banned[^\:]*\:\<\/p\><ul(?: class=\"list\-links\")?\>(.+?)\<\/ul\>/',
				$html,
				$list
			);
			preg_match_all(
				'/\<li\>\<a href=\"[^\"]+?\" class=\"autocard-link\" data-image-url=\"(?:[^\"]+)\">([^\<]+)\<\/a\>[^\<]*\<\/li\>/',
				$list[1],
				$cards
			);
			return $cards[1];
		}
	)
);
$count_banlists = count($banlists);



// Compile local banlist.
$banlist      = array();
$cards        = array();
$expiration   = time() - 86400; // 1 day
$table_header = '';
$x            = 0;
foreach ($banlists as $name => $meta) {

	// If the cache file doesn't exist or has expired,
	if (
		!file_exists($meta['cache']) ||
		filemtime($meta['cache']) < $expiration
	) {
		try {
			$html = file_get_contents($meta['url']);
		}
		catch (Error $e) {
			$html = '';
		}
		if (
			$html ||
			!file_exists($meta['cache'])
		)
			file_put_contents(
				$meta['cache'],
				str_replace(
					array("\n", "\r", "\t"), '',
					str_replace('’', '\'', $html)
				)
			);
	}
	foreach (call_user_func($meta['match'], file_get_contents($meta['cache'])) as $card) {
		if (!array_key_exists($card, $banlist))
			$banlist[$card] = array(0, 0, 0);

		// Merge Multiplayer and WotC
		$banlist[$card][$x == 3 ? 0 : $x] = 1;
	}

	// Multiplayer/1v1 <th>
	if ($x < 3)
		$table_header .= '<th><abbr title="' . $meta['title'] . '">' . $name . '</abbr></th>';
	$x++;
}
unset($banlists['WotC']);
$count_banlists--;



// Mox artifacts on MTGCommander
unset($banlist['Mox Sapphire, Ruby, Pearl, Emerald and Jet']);
$banlist['Mox Emerald'][0]  = 1;
$banlist['Mox Jet'][0]      = 1;
$banlist['Mox Pearl'][0]    = 1;
$banlist['Mox Ruby'][0]     = 1;
$banlist['Mox Sapphire'][0] = 1;



// Card info
$banlist2 = array();
$cards    = $page->query('
	SELECT
		`card`.`mana`,
		`card`.`name`,
		`card`.`type`,
		`set_card`.`multiverseid`
	FROM `set_card`
	LEFT JOIN `card`
	ON `set_card`.`card` = `card`.`rowid`
	LEFT JOIN `set`
	ON `set_card`.`set` = `set`.`rowid`
	WHERE
		`card`.`rules` LIKE "%for ante.%" OR
		`card`.`type` = "Conspiracy" OR
		`card`.`name` IN (' .
		implode(
			', ',
			array_map(
				function($value) {
					global $page;
					return $page->db->quote($value);
				},
				array_keys($banlist)
			)
		) .
	')
	GROUP BY `card`.`name`
	ORDER BY
		`card`.`name`   ASC,
		`set`.`release` DESC;
')->fetchAll(PDO::FETCH_ASSOC);
foreach ($cards as $card) {
	if (array_key_exists($card['name'], $banlist)) {
		$banlist[$card['name']]['mana']         = $card['mana'];
		$banlist[$card['name']]['multiverseid'] = $card['multiverseid'];
		$banlist[$card['name']]['type']         = $card['type'];
	}
	else
		array_push($banlist2, $card);
}



// Tables
include 'resources/name2url.php';
ksort($banlist);
$columns        = 4;
$count_banlist  = count($banlist);
$count_banlist2 = count($banlist2);
$per            = ceil($count_banlist / $columns);
$per2           = ceil($count_banlist2 / $columns);
$tables         = array();
$tables2        = array();
for ($x = 0; $x < $columns; $x++) {
	$y = 0;

	// Banlist
	$table = array();
	foreach ($banlist as $name => $meta) {
		$row = array();
		if (array_key_exists('mana', $meta))
			$row['mana'] = $meta['mana'];
		if (array_key_exists('multiverseid', $meta))
			$row['multiverseid'] = $meta['multiverseid'];
		if (array_key_exists('type', $meta))
			$row['type'] = $meta['type'];
		$row['name'] = $name;
		$row['url']  = name2url($name);
		$row['cells'] = array();
		$banned = 0;
		for ($z = 0; $z < $count_banlists; $z++) {
			$banned += $meta[$z];
			array_push($row['cells'], $meta[$z]);
		}
		if ($banned == $count_banlists)
			$row['banned'] = true;
		array_push($table, $row);
		array_shift($banlist);
		$y++;
		if ($y == $per)
			break;
	}
	array_push($tables, $table);

	// Global Banlist
	$table = array();
	for ($y = $x * $per2; $y < min($count_banlist2, ($x + 1) * $per2); $y++) {
		$banlist2[$y]['url'] = name2url($banlist2[$y]['name']);
		array_push($table, $banlist2[$y]);
	}
	array_push($tables2, $table);
}



// Page contents.
$page
	->set('table_header', $table_header)
	->set('tables',       $tables)
	->set('tables2',      $tables2);



?>
<title>Commander (EDH) Banlists - Magic: The Gathering</title>
<meta name="description" content="An accumulation of banlists for the Commander format of Magic: The Gathering." />
<meta name="keywords" content="commander banlist, duel banlist, duel commander, duel commander banlist, edh banlist, mtg commander, mtg commander banlist, wizards commander banlist" />

<div id="legend">
	<span><span class="y">&#x2713;</span> allowed</span>
	<span><span class="x">&#x2718;</span> banned</span>
</div>

<div class="tables">
	<foreach tables="table">
		<table class="banlist">
			<thead>
				<tr>
					<th>Card Name</th>
					{{table_header}}
				</tr>
			</thead>
			<tbody>
				<foreach table="row">
					<tr if-row-banned-class="x">
						<th>
							<if row-multiverseid>
								<a href="/{{row-url}}" if-row-multiverseid-data-multiverseid="{{row-multiverseid}}" target="_blank" title="{{row-name}} &ndash; MTGeni.us">{{row-name}}</a>
							</if>
							<else>
								{{row-name}}
							</else>
						</th>
						<foreach row-cells="cell">
							<if cell-value>
								<td class="x">
									&#x2718;<if cell-key="1" row-type="Legendary Creature"><abbr title="Banned only as the General">&dagger;</abbr></if>
								</td>
							</if>
							<else>
								<td class="y">&#x2713;</td>
							</else>
						</foreach>
					</tr>
				</foreach>
			</tbody>
		</table>
	</foreach>
</div>



<!-- MTGeni.us Commander Banlists -->
<ins class="adsbygoogle" data-ad-client="ca-pub-3978223571927395" data-ad-slot="2687084242" data-ad-format="auto"></ins>



<!-- Ante and Conspiracy cards -->
<h2>Ante and Conspiracy Cards <span class="x">&#x2718;</span></h2>
<div class="tables">
	<foreach tables2="table">
		<table class="banlist">
			<thead>
				<tr>
					<th>Card Name</th>
				</tr>
			</thead>
			<tbody>
				<foreach table="row">
					<tr>
						<th>
							<if row-multiverseid>
								<a href="/{{row-url}}" if-row-multiverseid-data-multiverseid="{{row-multiverseid}}" target="_blank" title="{{row-name}} &ndash; MTGeni.us">{{row-name}}</a>
							</if>
							<else>
								{{row-name}}
							</else>
						</th>
					</tr>
				</foreach>
			</tbody>
		</table>
	</foreach>
</div>