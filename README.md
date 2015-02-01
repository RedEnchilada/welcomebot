# Welcomebot
A bot to welcome new users to RDN or any other StatusNet/GNUSocial instance. 
Original base code by [Tylian](https://github.com/Tylian), current code 
maintained and hosted by [RedEnchilada](https://github.com/RedEnchilada) and 
made available under the GNU GPL v2.

## Use
The bot has the following dependencies:
* PHP (tested in 5.3.0 and 5.5.9; similar versions should work)
* MySQL
* cURL
* PEAR's SystemDaemon library

Install these on your server, then upload the files somewhere. The daemon 
script and configuration file should be outside any web-accessible folder; the 
web-facing message browser, if used, should be located in a web-accessible 
folder, and may need tweaked to include the config file properly if moved from 
the relative structure present in the repo.

Set up a new MySQL user and database and run `wp-dbsetup.sql` on it. Rename 
welcomeponyconfig.sample.php to welcomeponyconfig.php and fill in the proper 
values for use. Boot the daemon through shell with `php 
/path/to/welcomeponyd.php`. A command listing for the bot can be found in 
`html/welcomepony.php`.

None of the files in the `html/` directory are needed to run the daemon, and 
the directory may be omitted if you do not wish to have a landing page for the 
bot or wish to write your own.

If the SystemDaemon library is unavailable for whatever reason, the daemon can 
be executed as a foreground process (ideally through screen or etc) with the 
`--no-daemon` parameter.

(These instructions were whipped up quickly from memory; if you've followed 
them and encounter an error, contact me and I'll append them accordingly.)
