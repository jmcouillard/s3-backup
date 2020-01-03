<?php



/******************************************************************************
 * Path settings
 ******************************************************************************/

define('DOMAINS_PATH', '/var/www/vhosts/');
define('BACKUP_PATH', "/home/backup/backups/");
define('HTTP_FOLDER',  "/httpdocs");


/******************************************************************************
 * S3 settings
 ******************************************************************************/
define('PYTHONPATH', "/usr/bin/python". ":" . "/usr/lib/python2.6");
define('S3CFG_FILE_PATH', "/root/.s3cfg");
define('S3CMD_FILE', "/usr/bin/s3cmd");
define('S3_REMOTE_PATH', "jmcouillard-mtl/gtbackup/");


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
$webspaces = getWebspaces();
$domains = getDomains($webspaces);
$mysqlpassword = defined('DB_PASS_SHADOW') ? trim(file_get_contents(DB_PASS_SHADOW)) : DB_PASS;
$link = mysql_connect(DB_HOST,DB_USER,$mysqlpassword);
$dbs = getDatabases($link);
$tasks = array();

createBaseTasks();
createDatabasesTask($dbs, $datestamp);
createDomainsTasks($domains, $datestamp);
createFilesTasks($domains, $datestamp);
createMailTasks($webspaces, $datestamp);

ksort($tasks);

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
    foreach($tasks as $task => $cmds) {
        $output .= 'echo "Processing ' . $task . ' ..."' . "\n";
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
	    
	    $file = $domain["name"] . ".tar";
	    $cmds = array();
	    
	    // Go to domains
		// $cmds[] = "cd " . DOMAINS_PATH . $domain;
	    
	    // Create archive file (no compression)
		$cmds[] = "tar -cPf " . BACKUP_PATH . $file . " " . $domain["path"];

	    // Zip file
		// $cmds[] = "tar -zcPf " . BACKUP_PATH . $file . " " . $domain["path"];
		
		// Upload to s3
		$cmds[] = "export PYTHONPATH=" . PYTHONPATH . "; " . S3CMD_FILE . " -c " . S3CFG_FILE_PATH . " -H put ".BACKUP_PATH ."$file s3://".S3_REMOTE_PATH.$datestamp."/".$file." --storage-class=STANDARD_IA --no-check-md5";
       
       	// Remove file
		$cmds[] = "rm " . BACKUP_PATH . $file;

        // Add task
        $tasks[$domain["name"]." DOMAIN"] = $cmds;
	}
}

function createFilesTasks($domains, $datestamp) {
	
	global $tasks;
	
	// Output command
	foreach($domains as $key => $domain) {
	    
	    $file = $domain["name"] . ".files.tar";
	    $storage = "/home/storage" . str_replace("httpdocs/", "", $domain["path"]);
	    $cmds = array();
	    
	    // Go to domains
		// $cmds[] = "cd " . DOMAINS_PATH . $domain;

	    // Conditional
		$cmds[] = "if [ -d \"{$storage}\" ]; then";
	    
	    // Create archive file (no compression)
		$cmds[] = "tar -cPf " . BACKUP_PATH . $file . " " . $storage;

	    // Zip file (with compression level at 1 using --fast)
		// $cmds[] = "gzip -v --fast " . BACKUP_PATH . $file;
		// $file = $file . ".gz";

		// Upload to s3
		$cmds[] = "export PYTHONPATH=" . PYTHONPATH . "; " . S3CMD_FILE . " -c " . S3CFG_FILE_PATH . " -H put ".BACKUP_PATH ."$file s3://".S3_REMOTE_PATH.$datestamp."/".$file." --storage-class=STANDARD_IA --no-check-md5";
       
       	// Remove file
		$cmds[] = "rm " . BACKUP_PATH . $file;
		
	    // Conditional
		$cmds[] = "fi";

        // Add task
        $tasks[$domain["name"]." FILES"] = $cmds;
	}
}

function createDatabasesTask($dbs, $datestamp) {
	
	global $tasks;
	
	// Output command
	foreach($dbs as $key => $db) {
	    
	    $file = $db . ".sql.tar";
	    $cmds = array();

	    // Defin which mysql password to use
	    $mysqlPassword = (defined('DB_PASS_SHADOW')) ? '`cat ' . DB_PASS_SHADOW . '`' : DB_PASS;

	    // Backup db
		// $cmds[] = "mysqldump -h " . DB_HOST . " -u " . DB_USER . " -p" . $mysqlPassword . " " . $db. " > " . BACKUP_PATH . $db . ".sql";
		$cmds[] = "mysqldump -h " . DB_HOST . " -u " . DB_USER . " -p" . $mysqlPassword . " " . $db . " --default-character-set=utf8 --result-file=" . BACKUP_PATH . $db . ".sql";

	    // Go to backup
		$cmds[] = "cd " . BACKUP_PATH;

	    // Create archive file (no compression)
		$cmds[] = "tar -cPf " . $file . " " . $db . ".sql";

	    // Zip file
		// $cmds[] = "tar -zcPf " . $file . " " . $db . ".sql";
		
		// Upload to s3
		$cmds[] = "export PYTHONPATH=" . PYTHONPATH . "; " . S3CMD_FILE . " -c " . S3CFG_FILE_PATH . " -H put ".BACKUP_PATH ."$file s3://".S3_REMOTE_PATH.$datestamp."/".$file." --storage-class=STANDARD_IA --no-check-md5";
        
       	// Remove file
		$cmds[] = "rm " . BACKUP_PATH . $file;
		$cmds[] = "rm " . BACKUP_PATH . $db . ".sql";

        // Add task
        $tasks[$db." DB"] = $cmds;
	}
}

function createMailTasks($webspaces, $datestamp)
{
	global $tasks;
	
	// Output command
	foreach($webspaces as $key => $webspace) {
	    
	    $file = $webspace.".mail.tar.gz";
	    $cmds = array();
	    
	    // Conditional
		$cmds[] = "if [ -f /usr/local/psa/bin/pleskbackup ]; then";
	    
	    // Zip file
		$cmds[] = "/usr/local/psa/bin/pleskbackup domains-name ".$webspace." --only-mail --output-file=".BACKUP_PATH.$file; 
		// $cmds[] = "/usr/local/psa/bin/pleskbackup domains-name ".$webspace." -v --only-mail --output-file=".BACKUP_PATH.$file; 
		
		// Upload to s3
		$cmds[] = "export PYTHONPATH=" . PYTHONPATH . "; " . S3CMD_FILE . " -c " . S3CFG_FILE_PATH . " -H put ".BACKUP_PATH ."$file s3://".S3_REMOTE_PATH.$datestamp."/".$file." --storage-class=STANDARD_IA --no-check-md5";
	    
       	// Remove file
		$cmds[] = "rm " . BACKUP_PATH . $file;

	    // Conditional
		$cmds[] = "fi";
        
        // Add task
        $tasks[$webspace . " MAIL"] = $cmds;
	}
}

function getWebspaces()
{
	global $argc;
	global $argv;

	if($argc > 1) {
		$webspaces = $argv;
		array_shift($webspaces);
	} else {
		$webspaces = array("");	
	}

	return $webspaces;
}

function getDomains($webspaces)
{

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
    return strcmp(reset($a_domain), reset($b_domain));
}

function getDatabases($link) {

   $list = Array();
   $dbs = @mysql_list_dbs($link);

    while ($row = @mysql_fetch_object($dbs)) {
        if ($row->Database != 'information_schema') { $list[] = $row->Database; }
    }
    
   return $list;
}


function saveDomainsList($domains) {
    $fp = fopen(BACKUP_PATH . "domains.txt", 'w');
    foreach ($domains as $domain) {
    	fwrite($fp, $domain["path"] . "\n");
    }
    fclose($fp);
}

?>