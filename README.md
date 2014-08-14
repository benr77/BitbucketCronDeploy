# BitBucket Git Deploy Script For Simple Websites #

All the existing PHP Git deploy scripts seem to rely on the repository copy on the web server, and the web site files themselves, all being writable by the web server user (e.g. apache or www-data). From a security point of view this is far from ideal.

This script does pretty much the same as the others, except it can also be called via cron as well as the web server via an HTTP POST request. The HTTP request just sets up a flag in the form of an empty file. The script is also cron'd regularly and runs the git checkout if it finds the file set by the HTTP request. Simples.

This is an alternative deployment script to that provided by Jonathan Nicol. You will need to follow his procedures as described [here](http://jonathannicol.com/blog/2013/11/19/automated-git-deployments-from-bitbucket/). Credit to him for his clear and detailed instructions.

## Installation ##

### Files & Permissions ###

Put the script plus its data directory somewhere on your web server, inside the user account you want to own the project.

Set permissions on the data directory
```
chmod 775 data
chmod g+s data
chown apache data
```

### Edit the paths ###

At the top of the script, there are the root_path and repo_path parameters. Set these to whatever locations you used when setting things up with Jonathan's instructions.

### Symlink to web space ###

Symlink it in to web space and create your URL for a BitBucket Hook POST Request. Hits to this URL from BitBucket will cause an empty file to be written. As Jonathan suggests choose a cryptic name for the symlink to help via security through obscurity etc. You could also restrict access to this script via an .htaccess or whatever.

### Set up the cron job ###

Then, cron the script to run every minute or so. When run from cron, it looks for the empty file created by the HTTP request. If it finds it, it does the Git checkout under the permissions of the system user account and NOT the web server user. Once this is done it deletes the data file.

## Notes ##

Bitbucket now offers Deployment Keys - you can store your SSH public key as a Deployment Key per repository rather than against your entire Bitbucket account. Much more secure...

The script can easily be adapted for GitHub hooks - just need to change the test for the POST "payload" parameter I think.

This functionality could equally have been achieved by using a separate script and a restricted "sudo" configuration to allow the webserver user to execute the script with the permissions of the system user account. However, more developers will have access to cron on their server than to the sudo configuration so the cron approach should serve a wider audience.

Modifications and suggestions welcome.

