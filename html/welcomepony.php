<?php
	/* Welcomebot
	 * Copyright (C) 2012-2015 Tylian, RedEnchilada <red@lyrawearspants.com>
	 * This program is free software; you can redistribute it and/or modify
	 * it under the terms of the GNU General Public License as published by
	 * the Free Software Foundation; either version 2 of the License, or
	 * (at your option) any later version.
	 *
	 * This program is distributed in the hope that it will be useful,
	 * but WITHOUT ANY WARRANTY; without even the implied warranty of
	 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	 * GNU General Public License for more details.
	 *
	 * You should have received a copy of the GNU General Public License along
	 * with this program; if not, write to the Free Software Foundation, Inc.,
	 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
	 */
	$list = isset($_GET['list']);

	require_once('../welcomeponyconfig.php');
	
	function renderWelcome() {
		global $mysqlHost, $mysqlUsername, $mysqlPassword, $mysqlDatabase;
		
		@mysql_connect($mysqlHost, $mysqlUsername, $mysqlPassword);
		@mysql_select_db($mysqlDatabase);
		
		$result = @mysql_query('SELECT message FROM messages WHERE enabled=1 ORDER BY RAND() LIMIT 1');
		$row = @mysql_fetch_object($result);
		
		@mysql_close();
		
		if($row)
			return sprintf(htmlspecialchars($row->message), '<em>newpony</em>');
		else
			return '<em>Database error.</em>';
	}
	
	function listWelcomes() {
		global $mysqlHost, $mysqlUsername, $mysqlPassword, $mysqlDatabase;
		$welcomesPerPage = 10;
		
		$page = intval($_GET['list']);
		if($page < 1) $page = 1;
		
		mysql_connect($mysqlHost, $mysqlUsername, $mysqlPassword);
		mysql_select_db($mysqlDatabase);
		
		echo '<h2>List of Welcomes, page ' . $page . '</h2> <ul>';
		
		$total = mysql_fetch_array(mysql_query('SELECT COUNT(*) FROM messages WHERE 1'));
		$total = $total[0];
		
		$result = mysql_query('SELECT * FROM messages LIMIT '.(($page-1)*$welcomesPerPage).',' . $welcomesPerPage);
		while($row = mysql_fetch_object($result)) {
			echo ' <li' . ($row->enabled == 1 ? '' : ' class="unapproved" title="Not approved"') . '> <p>'
				. sprintf(htmlspecialchars($row->message), '<em>newpony</em>')
				. '</p> <div>Suggested by <a href="http://rainbowdash.net/' . $row->user . '">'
				. $row->user . '</a></div> </li>';
		}
		
		echo ' </ul>';
		
		if($page > 1)
			echo ' <a href="welcomepony.php?list=' . ($page-1) . '" id="prevpage">Previous page</a>';
		if($page < $total/$welcomesPerPage)
			echo ' <a href="welcomepony.php?list=' . ($page+1) . '" id="nextpage">Next page</a>';
		echo ' <span id="pgcount">Page ' . $page . ' of ' . intval(($total+$welcomesPerPage-1)/$welcomesPerPage)
			. '</span>';
	}
?>
<!DOCTYPE html
PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
		<title><?php
			if ($list) { ?>List of Welcome Pony welcomes<?php } else { ?>Welcome to Welcome Pony!<?php }
		?></title>
		<meta http-equiv="Content-type" content="text/html;charset=UTF-8" />
		<link rel="shortcut icon" href="/welcomehorseico.png" />
		<link rel="stylesheet" type="text/css" href="/welcomehorse.css" />
	</head>
	<body>
		<div id="wrapper">
			<div id="header">
				<h1>Welcome Pony</h1>
				<div> <?php echo renderWelcome(); ?></div>
				<a href="welcomepony.php">About</a>
				<a href="welcomepony.php?list">Message List</a>
			</div>
			<div id="content">
<?php if($list) { listWelcomes(); } else { ?>
				<h2 id="about">About</h2>
				<p>Hi, I'm Welcome Pony! I'm a bot designed to help make new users on 
					<a href="http://rainbowdash.net">Rainbow Dash Network</a> feel more welcome 
					by sending them a welcome message! I also keep watch of how many ponies post 
					to the site!</p>
				<p>The welcomepony daemon is currently maintained and run by 
					@<a href="http://rainbowdash.net/user/798">redenchilada</a>. Ask him if you 
					have any questions about me!</p>
					
				<h2 id="commands">Commands</h2>
				<p>All commands have a three-minute timeout between commands, except for bot admins.</p>
				<dl>
					<dt>@welcomepony add <em>@newpony {message}</em></dt>
					<dd>Suggests a welcome message to add to Welcome Pony's message list. Use 
						@<em>newpony</em> or <em>{newpony}</em> to refer to the username of the new member. Messages must be approved 
						by a bot moderator before they will start appearing.</dd>
					
					<dt>@welcomepony welcome me</dt>
					<dd>Forces Welcome Pony to send you a welcome message from her list.</dd>
					
					<dt>@welcomepony users</dt>
					<dd>Posts a count of unique users that have posted in the current calendar 
						day (UTC).</dd>
					
					<dt>@welcomepony ping</dt>
					<dd>Tests Welcome Pony's response. "Pong!"</dd>
					
					<dt>@welcomepony tell me a story</dt>
					<dd>Posts a random story from a preset list. There is currently no option to 
						suggest stories.</dd>
					
					<dt>@welcomepony kiss <em>{me|@user}</em></dt>
					<dt>@welcomepony hug @<em>user</em></dt>
					<dt>@welcomepony I love you</dt>
					<dd>Various affectionate commands for the heck of it.</dd>
				</dl>
				<h3 id="admincommands">Bot admin commands</h3>
				<dl>
					<dt>@welcomepony approve welcome</dt>
					<dd>Approves the welcome message responded to so that new users will see it. 
						(Respond to the message Welcome Pony posts as a response to the <em>add</em> 
						command to use this command.)</dd>
					
					<dt>@welcomepony welcome <em>{on|off}</em></dt>
					<dd>Enables/disables the <em>welcome me</em> command for forced welcomes. 
						(Welcome Pony will still welcome new users regardless of the status of 
						this setting.)</dd>
					
					<dt>@welcomepony promote <em>user</em></dt>
					<dt>@welcomepony demote <em>user</em></dt>
					<dd>Promotes or demotes a user to/from bot admin. <em>User</em> can be a 
						username or a user ID.</dd>
					
					<dt>@welcomepony source <em>source</em></dt>
					<dd>Changes the "from" field that Welcome Pony sets when it posts notices.</dd>
					
					<dt>@welcomepony refresh <em>seconds</em></dt>
					<dt>@welcomepony command <em>seconds</em></dt>
					<dd>Changes the refresh interval and command timeouts. Valid numbers are 10-180 seconds 
						for <em>refresh</em>, and 10-900 seconds for <em>command</em>.</dd>
					
					<dt>@welcomepony unique reset</dt>
					<dd>Forces a reset of the unique user tally.</dd>
					
					<dt>@welcomepony debug</dt>
					<dd>Announces the refresh and command delays, as well as whether forced welcomes are enabled.</dd>
					
					<dt>@welcomepony shut down</dt>
					<dd>Halts the Welcome Pony daemon. This should not be needed except when the 
						bot is updated.</dd>
				</dl>
				<p>There may be other hidden commands available too!</p><?php } ?>

			</div>
		</div>
		<div id="src"><a href="http://derpiboo.ru/261391">Banner source</a> | 
			<a href="http://moabite.deviantart.com/art/Smile-Pinkie-Pie-303594206">Footer source</a></div>
	</body>
</html>