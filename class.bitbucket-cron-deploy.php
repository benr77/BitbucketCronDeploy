<?php

/** 
 * Git Deployment Script for BitBucket
 *
 * All the existing PHP Git deploy scripts seem to rely on the repository copy on the web server, 
 * and the web site files themselves, all being writable by the web server user (e.g. apache or www-data). 
 * From a security point of view this is far from ideal.
 * 
 * This script does pretty much the same as the others, except it can also be called via cron. 
 * Symlink it in to web space and create your URL for a BitBucket Hook POST Request. Hits to this URL from 
 * BitBucket will cause an empty file to be written.
 *
 * Then, cron the script to run every minute or whatever. When run from cron, it looks for the above file. 
 * If it finds it, it does the Git checkout under the permissions of the system user account and NOT the 
 * web server user. Once this is done it deletes the data file.
 *
 * @Author: Ben Roberts ben@headsnet.com
 *
 */


class cronDeploy 
{
	/** 
	 * The location of the repo on the web server. This is a DETACHED HEAD
	 */
	private $repo_dir;
	
	/** 
	 * The location to deploy the files to.
	 */
	private $root_dir;
	
	/** 
	 * What Git repository branch do we want to deploy
	 */
	private $git_branch;
	
	/** 
	 * Full path to git binary is required if git is not in your PHP user's path. Otherwise just use 'git'.
	 */
	private $git_path = 'git';
	
	/** 
	 * The name of the flag file. This is a subdirectory of this script's location
	 */
	private $dataFile = '/data/request.txt';
	
	/** 
	 * The name of the log file. This is a subdirectory of this script's location
	 */
	private $logFile = '/data/request.log';
	
	/** 
	 * Flag to indicate an update is available
	 */
	private $update = false;
	
	
	/** 
	 * Constructor
	 */
	public function __construct ()
	{
		
	}
	
	/** 
	 * Set repo_dir
	 */
	public function setRepoPath ($p)
	{
		$this->repo_dir = $p;
	}
	
	/** 
	 * Set root_dir
	 */
	public function setRootPath ($p)
	{
		$this->root_dir = $p;
	}
	
	/** 
	 * Set git_path
	 */
	public function setGitPath ($p)
	{
		$this->git_path = $p;
	}
	
	/** 
	 * Set git_path
	 */
	public function setGitBranch ($p)
	{
		$this->git_branch = $p;
	}
	
	
	/** 
	 * Determines methods to call etc
	 */
	public function deploy ()
	{
		// If we are called by the web server, just check for an update and write a flag file
		if (!isset($_SERVER['argv']))
		{
			// Get and decode the JSON request data
			$this->getRequestData();
			
			// Process the data and see if we need to run an update
			$this->checkUpdate();
			
			// If we need to update, write a data file as a flag
			$this->writeDataFile();
		}
		// Otherwise, we are called by cron using the user account permissions. If the flag file
		// is present, run the git checkout
		else
		{
			$this->doGitCheckout();
		}
	}
	
	
	/** 
	 * Parse data from Bitbucket hook payload
	 */
	private function getRequestData ()
	{
		// Do nothing if we don't have suitable params
		if (empty($_POST) || empty($_POST['payload']) || strlen($_POST['payload']) == 0) 
		{
			exit;
		}
		
		$this->payload = json_decode($_POST['payload']);
		
		// Check parsing has been successful
		if ($this->payload === null)
		{
			$this->writeLog('Failed to parse JSON');
			exit;
		}
	}
	
	/** 
	 * Look at JSON data and see if we need to update our branch
	 */
	private function checkUpdate ()
	{
		// When merging and pushing to bitbucket, the commits array will be empty.
		// In this case there is no way to know what branch was pushed to, so we will do an update.
		if (empty($payload->commits))
		{
			$this->update = true;
		} 
		else 
		{
			foreach ($payload->commits as $commit) 
			{
				if ($commit->branch === $this->git_branch || isset($commit->branches) && in_array($this->git_branch, $commit->branches)) 
				{
					$this->update = true;
					break;
				}
			}
		}
	}
	
	/** 
	 * Write the data file. This is used as a flag to tell the cron 
	 * running this script to do the actual export
	 */
	private function writeDataFile ()
	{
		if ($this->update)
		{
			if (!file_put_contents(__DIR__.$this->dataFile, date('m/d/Y h:i:sa')."\n"))
			{
				$this->writeLog('Error writing request data file');
			}
			else
			{
				@chmod(__DIR__.$this->dataFile, 0660);
				$this->writeLog('Writing request data file');	
			}
		}
	}
	
	/** 
	 * Remove the data file
	 */
	private function removeDataFile ()
	{
		if (!unlink(__DIR__.$this->dataFile))
		{
			$this->writeLog('Error deleting request data file');
		}
		else
		{
			$this->writeLog('Deleting request data file OK');	
		}
	}
	
	
	/** 
	 * Convenience method to log activity to a file
	 */
	private function writeLog ($msg)
	{
		file_put_contents(__DIR__.$this->logFile, date('d/m/Y h:i:sa').' - '.$msg."\n", FILE_APPEND);
		@chmod(__DIR__.$this->logFile, 0660);
	}
	
	
	/** 
	 * Perform the Git checkout. This method is only run via a cron job so that 
	 * the checkout can be done by the system user account rather than the web server user.
	 */
	private function doGitCheckout ()
	{
		// Look for the request file. If none is present exit immediately
		if (!file_exists(__DIR__.$this->dataFile)) exit;
		
		// Now assuming we have found the file, perform the Git checkout
		exec("cd {$this->repo_dir} && {$this->git_path} fetch 2>&1", $op1);
		exec("cd {$this->repo_dir} && GIT_WORK_TREE={$this->root_dir} {$this->git_path} checkout -f 2>&1", $op2);
		
		echo "\nDeployment report\n\n";
		
		print_r($op1);
		echo "\n\n";
		print_r($op2);
		
		$this->removeDataFile();
	}

}

