<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// INCLUDE ANY CONFIGS OR LIBRARIES
require_once ("config.php");

// DEFINE THE WEBHOOK ENGINE NAME
// (USED FOR LOCK FILE, WEBHOOK TABLE NAME AND LOGMYAPP TABLE NAME)
$webhook_engine	=	"inventory";

// LOCK FOR SINGLE INSTANCE
$fp = fopen('/tmp/php-commit-webhook-engine_'.$webhook_engine.'.lock', 'c');
if (!flock($fp, LOCK_EX | LOCK_NB)) {
	print "INSTANCE LOCKED\n";
	exit;
}

function RemoveSpoolItem($spool_id) {
	// DELETE WEBHOOK ENTRY
	global $webhook_engine;
	$db 	= new db('localhost', 'user', 'pass', 'webhooks');
	$db->query('DELETE FROM `'.$webhook_engine.'` WHERE id = "'.$spool_id.'"');
}

function NewSpoolItem($action, $metadata) {
	global $webhook_engine;
	$db 		= new db('localhost', 'user', 'pass', 'webhooks');
	$timestamp	= date('Y-m-d H:i:s');
	$metadata	= json_encode($metadata, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
	$SQL		=  "INSERT INTO `".$webhook_engine."` ";
	$SQL		.= "(timestamp, action, metadata) ";
	$SQL		.= "VALUES ('$timestamp', '$action', '$metadata')";
	if ($db->query($SQL)) {
		return true;
	} else {
		return false;
	}		
}

// DEFINE DATABASES
$log  	= new LogMyApp('localhost', 'user', 'pass', 'logmyapp', 'webhook-engine_'.$webhook_engine, $level="DEBUG");
$db 	= new db('localhost', 'user', 'pass', 'webhooks');
$db2	= new db('localhost', 'user', 'pass', 'sonar');

// SELECT THE WEBHOOK ENTRIES FROM THE DATABASE
$results= $db->query("SELECT * FROM `".$webhook_engine."` ORDER BY `timestamp` ASC")->fetchAll();

echo "<pre>";
// PROCESS EACH RESULT FROM THE SPOOL
foreach ($results as $r) {
  /* 
    THIS ENGINE WAS BUILT FOR SPECIFIC WEBHOOK DATA COMING FROM
    A AN APPLICATION. THE DATA STRUCTURE WAS AS FOLLOWS:
    {
      "event":"inventory.item.assigned",
      "object_id":"22401",
      "metadata":{
        "assigned_entity":"accounts",
        "assigned_id":"103613"
      },
      "entered_at":"2024-09-10 17:19:18"
    }

    YOUR MILAGE MAY VARY, BUT YOU CAN ALWAYS DEFINE VARIABLES
    THAT WILL BE MOST USED FROM YOUR WEBHOOK DATA THAT BEST FITS
    YOUR NEEDS.
  */

	$spool_id  = $r['id'];
	$entered_at= $r['timestamp'];
	$action	   = $r['action'];
	$metadata  = $r['metadata'];

	$cleanhtml = array(
		"&lt;br /&gt;","<p>", "</p>", 
		"&lt;p&gt;", "&lt;/p&gt;", 
		"&lt;b&gt;", "&lt;/b&gt;");
	$logheader  = "\n\n";
	$logheader = "Spool ID: $spool_id\n";
	$logheader .= "Entered At: $entered_at\n";
	$logheader .= "Current Timestamp: ".date('Y-m-d H:i:s')."\n";
	$logheader .= "Action: $action\n";
	$logheader .= "Metadata: $metadata\n\n";

	$meta = json_decode($metadata, true);


	// CLEAN UP OLD ENTRIES THAT FAILED TO FIRE
	/*$cutoff	=	date('Y-m-d H:i:s', strtotime('-36 hours'));
	if ($entered_at < $cutoff) {
		print $logheader;
		$logid = $log->write($action, "INFO", $logheader);
		print "Entry older than cut off time!\n";
		print "Dumping entry!\n\n";
		$log->changeStatus($logid, 3);
		$log->append($logid, "Entry older than cut off time!\nDumping entry!");
		RemoveSpoolItem($spool_id);
	}*/

	// inventory.item.assigned
	if ($action == "inventory.item.assigned") {
		print $logheader;
		$logid = $log->write($action, "INFO", $logheader);

		// PERFORM ACTIONS TO THIS WEBHOOK ITEM
    $log->append($logid, "Add useful log notes");


    // IF CONDITION NOT MET, YOU CAN ALWAYS SKIP
		if ($condition == null) {
				// SKIP THIS ITEM
        $log->changeStatus($logid, 3);
				$log->append($logid, "condition not met");
				RemoveSpoolItem($spool_id);
				continue;
			}

    // REMOVE THIS SPOOL ITEM WHEN ACTION IS FINISHED
    RemoveSpoolItem($spool_id);
	} // END ACTION

	
} // END FOREACH

// UNLOCK INSTANCE
fclose($fp);
?>
