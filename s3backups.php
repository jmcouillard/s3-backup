<?php



/******************************************************************************
 * Path settings
 ******************************************************************************/

define('DOMAINS_PATH', '/var/www/vhosts/');
define('BACKUP_PATH', "/root/data/backups/");
define('HTTP_FOLDER',  "/httpdocs");


/******************************************************************************
 * S3 settings
 ******************************************************************************/
define('PYTHONPATH', "/usr/bin/python". ":" . "/usr/lib/python2.6");
define('S3CFG_FILE_PATH', "/root/.s3cfg");
define('S3CMD_FILE', "/usr/bin/s3cmd");
define('S3_REMOTE_PATH', "jmcouillard/gsbackup/");


/******************************************************************************
 * DB settings
 ******************************************************************************/
define('DB_HOST', "localhost");
define('DB_USER', "admin");
// define('DB_PASS', 'password');
define('DB_PASS_SHADOW', '/etc/psa/.psa.shadow');



/******************************************************************************
 * EXECUTE
 ******************************************************************************/
date_default_timezone_set('UTC');
$datestamp = date("Y-m-d-H-i-s", time());
$domains = getDomains();
$mysqlpassword = defined('DB_PASS_SHADOW') ? trim(file_get_contents(DB_PASS_SHADOW)) : DB_PASS;
$link = mysql_connect(DB_HOST,DB_USER,$mysqlpassword);
$dbs = getDatabases($link);
$tasks = array();

createBaseTasks();
createDatabasesTask($dbs, $datestamp);
createDomainsTasks($domains, $datestamp);

saveDomainsList($domains);
echo outputShell();

exit;

//saveShell();
//execute();

function saveShell() {
    
    $fp = fopen(BACKUP_PATH . "script.sh", 'w');
    fwrite($fp, outputShell());
    fclose($fp);
}


function outputShell() {
    
    global $tasks;
    
    $output = "";
    foreach($tasks as $domain => $cmds) {
        $output .= 'echo "Processing ' . $domain . ' ..."' . "\n";
        $output .= implode("\n", $cmds);
        $output .= "\n";
    }    
    
    return $output;
}

function execute() {
	
	 $output = "";	
	 exec("sh " . BACKUP_PATH . "script.sh", $output,  $result);
}

function createBaseTasks() {
	
	global $tasks;	    
	  	    
	// Go to domains
	$cmds[] = "cd " . BACKUP_PATH;
	    
	// Clear backup dir
	$cmds[] = "rm *.sql";
	$cmds[] = "rm *.tar.gz";
		
    // Add task
    $tasks['maintenance tasks'] = $cmds;	
}

function createDomainsTasks($domains, $datestamp) {
	
	global $tasks;
	
	// Output command
	foreach($domains as $key => $domain) {
	    
	    $file = $domain["name"] . ".tar.gz";
	    $cmds = array();
	    
	    // Go to domains
		// $cmds[] = "cd " . DOMAINS_PATH . $domain;
	    
	    // Zip file
		$cmds[] = "tar -zcf " . BACKUP_PATH . $file . " " . $domain["path"];
		
		// Upload to s3
		$cmds[] = "export PYTHONPATH=" . PYTHONPATH . "; " . S3CMD_FILE . " -c " . S3CFG_FILE_PATH . " -H put ".BACKUP_PATH ."$file s3://".S3_REMOTE_PATH.$datestamp."/".$file;
        
        // Add task
        $tasks[$domain["name"]] = $cmds;
	}
}

function getDomains()
{
	global $argc;
	global $argv;

	if($argc > 1) {
		$webspaces = $argv;
		array_shift($webspaces);
	} else {
		$webspaces = array("");	
	}

	clearstatcache();
	
	foreach ($webspaces as $webspace) {

		// Open websace folder and list its files
		$webspaceHttpFolder = DOMAINS_PATH  . $webspace .  HTTP_FOLDER;
	    $dh = opendir($webspaceHttpFolder);

	    while (($filename = readdir($dh)) != false)
	    {
	    	// Define current site full path
	    	$sitepath = $webspaceHttpFolder . "/" . $filename;

	    	// Add to domains list if it is a valid folder
	        if (is_dir($sitepath) && !is_link($sitepath) && $filename != "." && $filename != ".." )
	        {
	        	$path = $sitepath;
				$domains[] = array("name"=>$filename, "webspace"=>$webspace, "path"=>$path);
	        }
	    }
	}

    // Sort by domain in alphabetical order (this groups subdomains)
    usort($domains, "compareDomains");

	return $domains;
}

function compareDomains($a, $b)
{
    $pattern = '/[^.]+\.[^\.]+$/';
    preg_match($pattern, $a["name"], $a_domain);
    preg_match($pattern, $b["name"], $b_domain);
    return strcmp($a_domain[0], $b_domain[0]);
}

function getDatabases($link) {

   $list = Array();
   $dbs = @mysql_list_dbs($link);

    while ($row = @mysql_fetch_object($dbs)) {
        if ($row->Database != 'information_schema') { $list[] = $row->Database; }
    }
    
   return $list;
}

function createDatabasesTask($dbs, $datestamp) {
	
	global $tasks;
	
	// Output command
	foreach($dbs as $key => $db) {
	    
	    $file = $db . "_sql.tar.gz";
	    $cmds = array();

	    // Defin which mysql password to use
	    $mysqlPassword = (defined('DB_PASS_SHADOW')) ? '`cat ' . DB_PASS_SHADOW . '`' : DB_PASS;

	    // Backup db
		$cmds[] = "mysqldump -h " . DB_HOST . " -u " . DB_USER . " -p" . $mysqlPassword . " " . $db. " > " . BACKUP_PATH . $db . ".sql";
		
	    // Go to backup
		$cmds[] = "cd " . BACKUP_PATH;

	    // Zip file
		$cmds[] = "tar -zcf " . $file . " " . $db . ".sql";
		
		// Upload to s3
		$cmds[] = "export PYTHONPATH=" . PYTHONPATH . "; " . S3CMD_FILE . " -c " . S3CFG_FILE_PATH . " -H put ".BACKUP_PATH ."$file s3://".S3_REMOTE_PATH.$datestamp."/".$file;
        
        // Add task
        $tasks[$db] = $cmds;
	}
}

function saveDomainsList($domains) {
    $fp = fopen(BACKUP_PATH . "domains.txt", 'w');
    foreach ($domains as $domain) {
    	fwrite($fp, $domain["path"] . "\n");
    }
    fclose($fp);
}

?>