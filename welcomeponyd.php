#!/usr/bin/php
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

/****** ULTRA LAZY CODE GOOOOOOO ******/

/* Database structure:
 *
 * 'messages':
 * - 'enabled' - TINYINT(1)
 * - 'user' ---- VARCHAR(64)
 * - 'notice' -- INT(12)
 * - 'message' - LONGVARCHAR(1000)
 *
 * 'admins':
 * - 'id' ------ INT(6)
 */


// Allowed arguments & their defaults
$runmode = array(
    'no-daemon' => false,
    'help' => false,
    'write-initd' => false,
);
 
// Scan command line attributes for allowed arguments
foreach($argv as $k=>$arg) {
    if (substr($arg, 0, 2) == '--' && isset($runmode[substr($arg, 2)])) {
        $runmode[substr($arg, 2)] = true;
    }
}
 
// Help mode. Shows allowed argumentents and quit directly
if($runmode['help'] == true) {
    echo 'Usage: '.$argv[0].' [runmode]' . "\n";
    echo 'Available runmodes:' . "\n";
    foreach ($runmode as $runmod=>$val) {
        echo ' --'.$runmod . "\n";
    }
    die();
}
 
// Include Class
error_reporting(E_STRICT);
require_once 'System/Daemon.php';
 
// Setup
$options = array(
    'appName' => 'welcomeponyd',
    'appDir' => dirname(__FILE__),
    'appDescription' => 'Parses StatusNet public events and welcomes users with 1 notice.',
    'authorName' => 'Tylian',
    'authorEmail' => 'immatyger@gmail.com',
    'sysMaxExecutionTime' => '0',
    'sysMaxInputTime' => '0',
    'sysMemoryLimit' => '512M',
    'appRunAsGID' => 1000,
    'appRunAsUID' => 1000,
);
 
System_Daemon::setOptions($options);
 
// This program can also be run in the forground with runmode --no-daemon
if(!$runmode['no-daemon']) {
    System_Daemon::start();
} else {
	System_Daemon::info('running in no-daemon mode');
}
 
// With the runmode --write-initd, this program can automatically write a
// system startup file called: 'init.d'
// This will make sure your daemon will be started on reboot
if(!$runmode['write-initd']) {
    System_Daemon::info('not writing an init.d script this time');
} else {
    if(($initd_location = System_Daemon::writeAutoRun()) === false) {
        System_Daemon::notice('unable to write init.d script');
    } else {
        System_Daemon::info('sucessfully written startup script: %s',
			$initd_location);
    }
}

// ----------------------------------------------------------------------
// Bot config
require_once('welcomeponyconfig.php');

$admins = array();

initAdmins();
$admins[$adminUserId] = 'a';

// Time to wait between each check
$refreshDelay = 42;
$commandDelay = 180;

// Global variables
$lastNoticeId = 0;
$initialNoticeId = 0;
$lastCommandTime = 0;

// Random crap
$welcomeToggle = true;				// Toggle on/off for welcome me command
$uniqueIds = array();				// Array of unique users
$lastReset = "";					// Last day that the unique users thing was reset. It's text cause I can, and did.
$postingFrom = "the Death Egg";		// Source
$lastUpdated = "Jan 31, 2015, at 8:49 PM CST";	// The last time the script was updated

// -----------------------------------------------------------------------
// Bot entry point

date_default_timezone_set('UTC');

// Prepare posts to get the last notice ID.
$posts = getTimeline();
foreach($posts as $post) {
	if($post->id > $lastNoticeId) {
		$lastNoticeId = $post->id;
	}
}

$initialNoticeId = $lastNoticeId;

postNotice('I live! I LIVE! Mwahahahaha *cackles evilly*');

while(!System_Daemon::isDying()) {
    $posts = getTimeline();
	$lastCachedId = $lastNoticeId;
	$currentReadIndex = array();
	foreach($posts as $post) {
		// Store the ID so we don't reparse notices we've already seen
		if($post->id > $lastNoticeId)
			$lastNoticeId = $post->id;
			
		// Juuuust in case...
		if($post->id <= $lastCachedId) {
			//System_Daemon::info('Skipping over notice %s...', $post->id);
			continue;
		}
	
		// Check if we need to empty the unique users array
		if(date('j') != $lastReset) {
			$lastReset = date('j');
			System_Daemon::info('%s users posted today.', count($uniqueIds));
			$uniqueIds = array();
		}
		
		$newUser = false;
	
		// Check if the user is unique, if so add them to the unique user array
		if(!in_array($post->user->id, $uniqueIds)) {	
			$uniqueIds[] = $post->user->id;
			$newUser = true;
		}
		
		// Count how many notices from this user we've seen this loop
		if(!isset($currentReadIndex[$post->user->id]))
			$currentReadIndex[$post->user->id] = 0;
		$currentReadIndex[$post->user->id]++;
		
		// Send a welcome if they have only one notice
		if($post->user->statuses_count == $currentReadIndex[$post->user->id] && $newUser) {
			System_Daemon::info('new user found: %s', $post->user->screen_name);
			postNotice(getMessage($post->user->screen_name), $post->id);
			continue;
		}
		
		$postText = strtolower($post->text);
		
		// This message doesn't reference us, ignore it!
		if(strpos($postText, strtolower("@$username")) === false) {
			if(time() - $lastCommandTime < $commandDelay && !$isAdmin || ($commandDelay % 2) == 1)
				continue;
			if(strpos($postText, 'borderlands') !== false ||
				strpos($postText, 'borchlight 2') !== false) {
				System_Daemon::info('%s mentioned Borderlands or Torchlight', $post->user->screen_name);
				$message = sprintf('@%s #getyiffed', $post->user->screen_name);
				postNotice($message, $post->id);
				
				$lastCommandTime = time();
				continue;
			}
			if(strpos($postText, '#bonemom') !== false) {
				System_Daemon::info('%s is a bonemom', $post->user->screen_name);
				$message = sprintf('@%s hail satan', $post->user->screen_name);
				postNotice($message, $post->id);
				
				$lastCommandTime = time();
				continue;
			}
			
			continue;
		}
		
		// Store if they're an admin
		$isAdmin = isset($admins[$post->user->id]);
		
		// SUPER HAPPY CRAZY LET EVERYONE DO EVERYTHING MODE
		//$isAdmin = true;
		
		// Add message to Queue if they request it
		if(substr($postText, 0, strlen($username) + 6) == "@$username add ") {
			System_Daemon::info('adding message from %s to database', $post->user->screen_name);
			
			$message = substr($post->text, strlen($username) + 6);
			$message = str_replace(array('%', '{newpony}', '@newpony'), array('%%', '%1$s', '@%1$s'), $message, $count);
			if($count == 0) $message = '@%1$s '.$message;
			$reply = sprintf('@%s Thank you for the suggestion! The message has been sent to a queue to be approved, and once it is you\'ll see it being sent to newponies!', $post->user->screen_name);
			
			$response = postNotice($reply, $post->id);
			addMessage($message, $post->user->screen_name, $response->id);
			continue;
		}
		
		// Commands that have a limit
		if((time() - $lastCommandTime >= $commandDelay) || ($isAdmin && substr($postText, 0, strlen($username) + 9) != "@$username source ")) {
			if(strpos($postText, 'ping') !== false) {
				System_Daemon::info('responding to ping from %s', $post->user->screen_name);
				$message = sprintf('@%s pong!', $post->user->screen_name);
				postNotice($message, $post->id);
				
				$lastCommandTime = time();
				continue;
			}
			if(strpos($postText, 'tell me a story') !== false) {
				System_Daemon::info('telling %s a story', $post->user->screen_name);
				$stories = array(
					'Once upon a time there was a pony who felt very sad because she didn\'t know what to do with her life. Then she found out that her special talent was welcoming people! So she began welcoming all the newponies who showed up around town. The end!',
					'Pinkie heard the ding of the oven go off. She hoped her stomach was OK; she hadn\'t spoken in a long while. Maybe her stomach was starved! Wait, if her stomach was starved, why wasn\'t she starving? She was moderately hungry, but not in any mortal danger... unless her stomach talking was a new Pinkie Sense she hadn\'t realized before.',
					'RExABP, A FANFICTION. "Hello @[i][/i]abigpony, I am home now, *smoulder!* AND I am looking so handsome and also my shirt opened? *ripple!*" "Oooh Mr @[i][/i]redenchilada , oooh! *swoon* Let\'s do it!" "Yes. And I will leave my #digimonworldsucks on." "*gaze*" MEANWHILE ON RDN: SHIRTS RIPPING. PONIES TURNING GAY. IT WAS AMAZING. The end.',
					'A haiku: Welcome to our site! \\ I love to meet new ponies! \\ I hope you have fun!',
					'Once upon a time, there was a cliche princess in a cliche castle. One day, said cliche princess found a glass slipper. She put it on and vanquished the evil witch with its... glassy...ness...',
					'No.'
				);
				$message = '@' . $post->user->screen_name . ' ' . $stories[array_rand($stories)];
				postNotice($message, $post->id);
				
				$lastCommandTime = time();
				continue;
			}
			
			if(strpos($postText, 'welcome me') !== false) {
				// Deny the welcome if a mod has turned welcomes off
				if(!$welcomeToggle) continue;
				
				System_Daemon::info('sending a forced welcome to %s', $post->user->screen_name);
				postNotice(getMessage($post->user->screen_name), $post->id);
				
				$lastCommandTime = time();
				continue;
			}
			
			if(strpos($postText, 'users') !== false) {
				System_Daemon::info('sending unique user stats to %s', $post->user->screen_name);
				$message = sprintf('@%s Wowie, %d unique users have posted today!!', $post->user->screen_name, count($uniqueIds));
				postNotice($message, $post->id);
				
				$lastCommandTime = time();
				continue;
			}
			
			if(strpos($postText, 'you\'re ugly') !== false ||
				strpos($postText, 'your ugly') !== false) {
				System_Daemon::info('getting mad at %s', $post->user->screen_name);
				$message = sprintf('@%s #banned', $post->user->screen_name);
				postNotice($message, $post->id);
				
				$lastCommandTime = time();
				continue;
			}
			
			if(strpos($postText, 'love') !== false) {
				System_Daemon::info('%s loves me! *swoon*', $post->user->screen_name);
				$message = sprintf('@%s I love you too! *swoon~*', $post->user->screen_name);
				postNotice($message, $post->id);
				
				$lastCommandTime = time();
				continue;
			}
			
			if(strpos($postText, 'kiss me') !== false) {
				System_Daemon::info('making out with %s', $post->user->screen_name);
				if($post->user->id == 798)
					$message = sprintf('*sweeps @%s off their feet in a dramatic show of passion while locking lips*', $post->user->screen_name);
				else
					$message = sprintf('*pecks @%s on the cheek a little*', $post->user->screen_name);
				postNotice($message, $post->id);
				
				$lastCommandTime = time();
				continue;
			}
			
			if(substr($postText, 0, strlen($username) + 8) == "@$username kiss @") {
				System_Daemon::info('kissing for %s', $post->user->screen_name);
				
				$message = substr($post->text, strlen($username) + 8);
				$message = sprintf('*pecks @%s on the cheek a little*', $message);
				
				$response = postNotice($message, $post->id);
				continue;
			}
			
			if(substr($postText, 0, strlen($username) + 7) == "@$username hug @") {
				System_Daemon::info('giving %s a hug', $post->user->screen_name);
				
				$message = substr($post->text, strlen($username) + 7);
				$message = sprintf('@%s #hugs', $message);
				
				$response = postNotice($message, $post->id);
				continue;
			}
		}
		
		// The following commands are admin only.
		if(!$isAdmin) continue;

		if(strpos($postText, 'approve welcome') !== false) {
			System_Daemon::info('%s approved welcome notice %s', $post->user->screen_name, $post->in_reply_to_status_id);
			$message = sprintf('@%s Okey dokey lokey! I\'ll start sending this message to newponies!', $post->user->screen_name);
			postNotice($message, $post->id);
			
			approveMessage($post->in_reply_to_status_id);
			continue;
		}

		if(strpos($postText, 'welcome off') !== false) {
			System_Daemon::info('%s disabled welcome messages', $post->user->screen_name);
			$message = sprintf('@%s Okey dokey lokey! I won\'t welcome ponies who want me to!', $post->user->screen_name);
			postNotice($message, $post->id);
			
			$welcomeToggle = false;
			continue;
		}
			
		if(strpos($postText, 'welcome on') !== false) {
			System_Daemon::info('%s enabled welcome messages', $post->user->screen_name);
			$message = sprintf('@%s Okey dokey lokey! I\'ll welcome ponies who want me to!', $post->user->screen_name);
			postNotice($message, $post->id);
			
			$welcomeToggle = true;
			continue;
		}
		
		if(strpos($postText, 'unique reset') !== false) {
			System_Daemon::info('%s resetted unique user list manually.', $post->user->screen_name);
			$message = sprintf('@%s Okey dokey lokey! I\'ll reset my list of unique ponies!', $post->user->screen_name);
			postNotice($message, $post->id);
			
			$uniqueIds = array();
			continue;
		}
		
		if(strpos($postText, 'shut down') !== false) {
			System_Daemon::info('stopping due to command from '. $post->user->screen_name);
			$message = sprintf('@%s Okay.. If you insist.. Goodbye cruel Equestria!!', $post->user->screen_name);
			postNotice($message, $post->id);
			
			System_Daemon::stop();
			exit();
		}
		
		if(substr($postText, 0, strlen($username) + 10) == "@$username promote ") {
		
			$promoting = findUser(substr($post->text, strlen($username) + 10));
			
			System_Daemon::info('%s is promoting %s to bot admin', $post->user->screen_name, $promoting->screen_name);
			
			$message = "";
			if(addAdmin($promoting->id))
				$message = '@%s Hooray! @%s is now a bot admin!';
			else
				$message = '@%s %s could not be promoted.';
				
			$reply = sprintf($message, $post->user->screen_name, $promoting->screen_name);
			
			postNotice($reply, $post->id);
			continue;
		}
		
		if(substr($postText, 0, strlen($username) + 9) == "@$username demote ") {
		
			$demoting = findUser(substr($post->text, strlen($username) + 9));
			
			System_Daemon::info('%s is demoting %s from bot admin', $post->user->screen_name, $demoting->screen_name);
			
			$message = "";
			if(removeAdmin($demoting->id))
				$message = '@%s Aww, okey. @%s is no longer a bot admin.';
			else
				$message = '@%s %s could not be demoted.';
				
			$reply = sprintf($message, $post->user->screen_name, $demoting->screen_name);
			
			postNotice($reply, $post->id);
			continue;
		}
		
		if(strpos($postText, 'debug') !== false) {
			System_Daemon::info('Giving %s debug info', $post->user->screen_name);
			
			$message = sprintf('@%s I check the timeline every %s seconds. My current command timeout is %s seconds.'
				. ' Manual welcomes are %s. I\'ve read %s notices since my last reboot. My daemon was last tinkered'
				. ' with on %s. I am not Ross\'s waifu.', 
				$post->user->screen_name,
				$refreshDelay,
				$commandDelay,
				($welcomeToggle ? 'on' : 'off'),
				$lastNoticeId-$initialNoticeId,
				$lastUpdated);
			postNotice($message, $post->id);
			
			
			continue;
		}
		
		if(substr($postText, 0, strlen($username) + 9) == "@$username source ") {
		
			$postingFrom = substr($post->text, strlen($username) + 9);
			
			System_Daemon::info('%s changed source to %s', $post->user->screen_name, $postingFrom);
				
			$reply = sprintf('@%s Okey, I\'ll start talking from "%s"!', $post->user->screen_name, $postingFrom);
			
			postNotice($reply, $post->id);
			continue;
		}
		if(substr($postText, 0, strlen($username) + 10) == "@$username refresh ") {
		
			$tempVar = intval(substr($post->text, strlen($username) + 10));
			
			System_Daemon::info('%s changed refresh timeout to %s', $post->user->screen_name, $postingFrom);
			
			$reply = '';
			if($tempVar > 9 && $tempVar < 181) {
				$reply = sprintf('@%s Okey, I\'ll start checking the timeline every %s seconds!', $post->user->screen_name, $tempVar);
				$refreshDelay = $tempVar;
			} else {
				$reply = sprintf('@%s That\'s not a valid number! The range is 10 to 180 seconds.', $post->user->screen_name);
			}
			
			postNotice($reply, $post->id);
			continue;
		}
		if(substr($postText, 0, strlen($username) + 10) == "@$username command ") {
		
			$tempVar = intval(substr($post->text, strlen($username) + 10));
			
			System_Daemon::info('%s changed command timeout to %s', $post->user->screen_name, $postingFrom);
			
			$reply = '';
			if($tempVar > 9 && $tempVar < 901) {
				$reply = sprintf('@%s Okey, I\'ll start taking commands every %s seconds!', $post->user->screen_name, $tempVar);
				$commandDelay = $tempVar;
			} else {
				$reply = sprintf('@%s That\'s not a valid number! The range is 10 to 900 seconds.', $post->user->screen_name);
			}
			
			postNotice($reply, $post->id);
			continue;
		}
	}
 
    System_Daemon::iterate($refreshDelay);
}

System_Daemon::info('stopping');
System_Daemon::stop();

// ----------------------------------------------------------------------
// Bot helper functions below this line
 
// Gets the public timeline as an object
function getTimeline() {
	global $lastNoticeId, $apiBase, $username, $password;

	System_Daemon::info('getting timeline from after notice %s', $lastNoticeId);
	
	$curl = curl_init();
	
	curl_setopt($curl, CURLOPT_URL, "$apiBase/statuses/public_timeline.json?since_id=$lastNoticeId");
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
	curl_setopt($curl, CURLOPT_USERPWD, "$username:$password");
	
	$data = curl_exec($curl);
	curl_close($curl);
	
	$result = json_decode($data);

	System_Daemon::info('%s notices grabbed', count($result));
	
	return $result;
}

// Gets a random welcome message from the database.
function getMessage($name) {
	global $mysqlHost, $mysqlUsername, $mysqlPassword, $mysqlDatabase;
	
	if(!mysql_connect($mysqlHost, $mysqlUsername, $mysqlPassword)) handleDBError();
	mysql_select_db($mysqlDatabase);
	
	$result = mysql_query('SELECT * FROM messages WHERE enabled=1 ORDER BY RAND() LIMIT 1');
    $row = mysql_fetch_object($result);
	
	mysql_close();
	
	return sprintf($row->message, $name);
}

// Adds a welcome message to the database
function addMessage($message, $user, $id) {
	global $mysqlHost, $mysqlUsername, $mysqlPassword, $mysqlDatabase;
	
	if(!mysql_connect($mysqlHost, $mysqlUsername, $mysqlPassword)) handleDBError();
	mysql_select_db($mysqlDatabase);
	
	$query = sprintf("INSERT INTO `%s`.`messages` (`enabled`, `user`, `notice`, `message`)
		VALUES (0, '%s', '%s', '%s')",
		$mysqlDatabase,
		mysql_real_escape_string($user),
		mysql_real_escape_string($id),
		mysql_real_escape_string($message));
		
	$result = mysql_query($query);
	
	mysql_close();
	return true;
}

function approveMessage($id) {
	global $mysqlHost, $mysqlUsername, $mysqlPassword, $mysqlDatabase;

	if(!mysql_connect($mysqlHost, $mysqlUsername, $mysqlPassword)) handleDBError();
	mysql_select_db($mysqlDatabase);
	
	$query = sprintf("UPDATE `%s`.`messages` SET `enabled`=1 WHERE notice=%s",
		$mysqlDatabase,
		mysql_real_escape_string($id));
		
	$result = mysql_query($query);
	
	mysql_close();
	return true;
}

// Posts a notice to the service using the auth provided on config
function postNotice($notice, $replyId=0) {
	global $apiBase, $username, $password, $postingFrom;
	$curl = curl_init();

	System_Daemon::info('sending notice: %s', $notice);
	
	$data = array('status' => $notice, 'source' => $postingFrom, 'in_reply_to_status_id' => $replyId);
	
	curl_setopt($curl, CURLOPT_URL, "$apiBase/statuses/update.json");
	curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl, CURLOPT_POST, 1);
	curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data, '&'));
	curl_setopt($curl, CURLOPT_USERPWD, "$username:$password");

	$result = curl_exec($curl);
	curl_close($curl);
	
	return json_decode($result);
}

// Given a username or ID, returns a JSON object of the user
function findUser($id) {
	global $apiBase;
	
	$curl = curl_init();
	
	if(substr($id, 0, 1) == '@') $id = substr($id, 1);
	
	curl_setopt($curl, CURLOPT_URL, "$apiBase/users/show.json?id=$id");
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
	
	$data = curl_exec($curl);
	curl_close($curl);
	
	return json_decode($data);
}

function initAdmins() {
	global $mysqlHost, $mysqlUsername, $mysqlPassword, $mysqlDatabase, $admins;
	
	if(!mysql_connect($mysqlHost, $mysqlUsername, $mysqlPassword)) exit;
	mysql_select_db($mysqlDatabase);
	
	$result = mysql_query('SELECT * FROM admins');
	while($row = mysql_fetch_array($result))
		$admins[intval($row['id'])] = 'm';
	
	mysql_close();
}

function addAdmin($id) {
	global $mysqlHost, $mysqlUsername, $mysqlPassword, $mysqlDatabase, $admins;
	
	if(isset($admins[$id]))
		return false;
	
	if(!mysql_connect($mysqlHost, $mysqlUsername, $mysqlPassword)) handleDBError();
	mysql_select_db($mysqlDatabase);
	
	$query = sprintf("INSERT INTO `%s`.`admins` (`id`)
		VALUES ('%s')",
		$mysqlDatabase,
		mysql_real_escape_string($id));
		
	$result = mysql_query($query);
	
	$admins[$id] = 'm';
	
	mysql_close();
	return true;
}

function removeAdmin($id) {
	global $mysqlHost, $mysqlUsername, $mysqlPassword, $mysqlDatabase, $admins;
	
	if(!isset($admins[$id]) || $admins[$id] == 'a')
		return false;

	if(!mysql_connect($mysqlHost, $mysqlUsername, $mysqlPassword)) handleDBError();
	mysql_select_db($mysqlDatabase);
	
	$query = sprintf("DELETE FROM `%s`.`admins` WHERE `id`='%s'",
		$mysqlDatabase,
		mysql_real_escape_string($id));
		
	$result = mysql_query($query);
	
	unset($admins[$id]);
	
	mysql_close();
	return true;
}

function handleDBError() {
	postNotice('Database error. Attempting to reboot... @redenchilada', null);
	shell_exec('sudo reboot');
	exit;
}
 
?>