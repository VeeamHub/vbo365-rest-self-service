<?php
session_start();

require_once('config.php');
require_once('veeam.class.php');
set_time_limit(0);

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
	if (isset($_POST['action'])) { $action = $_POST['action']; }
	if (isset($_POST['json'])) { $json = $_POST['json']; }
	if (isset($_POST['limit'])) { $limit = $_POST['limit']; }
	if (isset($_POST['offset'])) { $offset = $_POST['offset']; }
	if (isset($_POST['type'])) { $type = $_POST['type']; }

	if (isset($_POST['id'])) { $id = $_POST['id']; }
	if (isset($_POST['folderid'])) { $folderid = $_POST['folderid']; }
	if (isset($_POST['itemid'])) { $itemid = $_POST['itemid']; }
	if (isset($_POST['mailboxid'])) { $mailboxid = $_POST['mailboxid']; }
	if (isset($_POST['rid'])) { $rid = $_POST['rid']; }
	if (isset($_POST['siteid'])) { $siteid = $_POST['siteid']; }
	if (isset($_POST['userid'])) { $userid = $_POST['userid']; }

	$veeam = new VBO($host, $port, $version);
	
	if (isset($_SESSION['token'])) {
		$veeam->setToken($_SESSION['token']);
	}
	
	/* Jobs Calls */
	if ($action == 'changejobstate') {
		$veeam->changeJobState($id, $json);
	}
	if ($action == 'getjobs') {
		$jobs = $veeam->getJobs($id);
		echo json_encode($jobs);
	}
	if ($action == 'getjobsession') {
		$getjobsession = $veeam->getJobSession($id);
		echo json_encode($getjobsession);
	}
	if ($action == 'startjob') {
		$job = $veeam->startJob($id);
		echo json_encode($job);
	}

	/* Organizations Calls */
	if ($action == 'getorganizations') {
		$org = $veeam->getOrganizations();
		echo json_encode($org);
	}

	/* Repositories Calls */
	if ($action == 'getrepo') {
		$repo = $veeam->getBackupRepository($id);
		echo json_encode($repo);
	}

	/* Sessions Calls */
	if ($action == 'getsessionlog') {
		$log = $veeam->getSessionLog($id);
		echo json_encode($log);
	}
	if ($action == 'getsessions') {
		$log = $veeam->getSessions($offset);
		echo json_encode($log);
	}
	if ($action == 'getbackupsessionlog') {
		$log = $veeam->getBackupSessionLog($id);
		echo json_encode($log);
	}
	if ($action == 'getbackupsessions') {
		$log = $veeam->getBackupSessions();
		echo json_encode($log);
	}
	if ($action == 'getrestoresessionevents') {
		$log = $veeam->getRestoreSessionEvents($id);
		echo json_encode($log);
	}
	if ($action == 'getrestoresessions') {
		$log = $veeam->getRestoreSessions();
		echo json_encode($log);
	}

	/* Restore Session Calls */
	if ($action == 'startrestore') {
		if (isset($id) && ($id != 'tenant')) {
			$session = $veeam->startRestoreSession($json, $id);
		} else {
			$session = $veeam->startRestoreSession($json);
		}
		
		$_SESSION['rid'] = $session['id'];
		$_SESSION['rtype'] = strtolower($session['type']);
		
		echo $session['id'];
	}
	if ($action == 'stoprestore') {
		$session = $veeam->stopRestoreSession($rid);
		
		if (isset($session['error'])) {
			echo json_encode($session['error']);
			
			unset($_SESSION['rid']);
			unset($_SESSION['rtype']);
		} else {		
			echo 'success';
			
			unset($_SESSION['rid']);
			unset($_SESSION['rtype']);
		}
	}
	if ($action == 'getrestoredevicecode') {
		$code = $veeam->getRestoreDeviceCode($rid, $json);
		echo json_encode($code);
	}

	/* Exchange Calls */
	if ($action == 'getmailboxes') {
		$mailboxes = $veeam->getMailboxes($rid, $offset);
		echo json_encode($mailboxes);
	}
	if ($action == 'getmailitems') {
		if (isset($offset)) {
			$items = $veeam->getMailboxItems($mailboxid, $rid, $folderid, $offset);
		} else {
			$items = $veeam->getMailboxItems($mailboxid, $rid, $folderid);
		}
		
		echo json_encode($items);
	}
	if ($action == 'getmailboxfolders') {
		if (isset($folderid)) {
			$folders = $veeam->getMailboxFolders($mailboxid, $rid, $offset, $folderid, $limit);
		} else {
			$folders = $veeam->getMailboxFolders($mailboxid, $rid, $offset);
		}
		
		echo json_encode($folders);
	}

	/* Exchange Restore Calls */
	if ($action == 'exportmailbox') {
		$veeam->exportMailbox($mailboxid, $rid, $json);
	}
	if ($action == 'exportmailitem') {
		$veeam->exportMailItem($itemid, $mailboxid, $rid, $json);
	}
	if ($action == 'exportmultiplemailitems') {
		$veeam->exportMultipleMailItems($itemid, $mailboxid, $rid, $json);
	}
	if ($action == 'restoremailbox') {
		$restore = $veeam->restoreMailbox($mailboxid, $rid, $json);
		echo json_encode($restore);
	}
	if ($action == 'restoremailitem') {
		$restore = $veeam->restoreMailItem($itemid, $mailboxid, $rid, $json);
		echo json_encode($restore);
	}
	if ($action == 'restoremultiplemailitems') {
		$restore = $veeam->restoreMultipleMailItems($mailboxid, $rid, $json);
		echo json_encode($restore);
	}

	/* OneDrive Calls */
	if ($action == 'getonedriveaccounts') {
		$accounts = $veeam->getOneDrives($rid, $offset);
		echo json_encode($accounts);
	}
	if ($action == 'getonedrivefolders') {
		$folders = $veeam->getOneDriveFolders($rid, $userid, $folderid, $offset);
		echo json_encode($folders);
	}
	if ($action == 'getonedriveitems') {
		if (isset($offset)) {
			$items = $veeam->getOneDriveTree($rid, $userid, $type, $folderid, $offset);
		} else {
			$items = $veeam->getOneDriveTree($rid, $userid, $type, $folderid);
		}
		
		echo json_encode($items);
	}
	if ($action == 'getonedriveitemsbyfolder') {
		$items = $veeam->getOneDriveTree($rid, $userid, $type, $folderid);
		
		echo json_encode($items);
	}

	/* OneDrive Restore Calls */
	if ($action == 'exportonedrive') {
		$veeam->exportOneDrive($userid, $rid, $json);
	}
	if ($action == 'exportonedriveitem') {
		$veeam->exportOneDriveItem($itemid, $userid, $rid, $json, $type);
	}
	if ($action == 'exportmultipleonedriveitems') {
		$veeam->exportMultipleOneDriveItems($itemid, $userid, $rid, $json, $type);
	}
	if ($action == 'restoreonedrive') {
		$restore = $veeam->restoreOneDrive($userid, $rid, $json);
		echo json_encode($restore);
	}
	if ($action == 'restoreonedriveitem') {
		$restore = $veeam->restoreOneDriveItem($itemid, $userid, $rid, $json, $type);
		echo json_encode($restore);
	}
	if ($action == 'restoremultipleonedriveitems') {
		$restore = $veeam->restoreMultipleOneDriveItems($userid, $rid, $json);
		echo json_encode($restore);
	}

	/* SharePoint Calls */
	if ($action == 'getsharepointitems') {
		if (isset($offset)) {
			$items = $veeam->getSharePointItems($rid, $siteid, $folderid, $type, $offset);
		} else {
			$items = $veeam->getSharePointItems($rid, $siteid, $folderid, $type);
		}

		echo json_encode($items);
	}
	if ($action == 'getsharepointfolders') {
		$folders = $veeam->getSharePointFolders($rid, $siteid, $folderid, $offset);
		echo json_encode($folders);
	}
	if ($action == 'getsharepointcontent') {
		$content = $veeam->getSharePointContent($rid, $siteid, $type, $offset);
		echo json_encode($content);
	}
	if ($action == 'getsharepointsites') {
		$sites = $veeam->getSharePointSites($rid, $offset);
		echo json_encode($sites);
	}

	/* SharePoint Restore Calls */
	if ($action == 'exportsharepoint') {
		$veeam->exportSharePoint($siteid, $rid, $json);
	}
	if ($action == 'exportsharepointitem') {
		$veeam->exportSharePointItem($itemid, $siteid, $rid, $json, $type);
	}
	if ($action == 'exportmultiplesharepointitems') {
		$veeam->exportMultipleSharePointItems($itemid, $siteid, $rid, $json, $type);
	}
	if ($action == 'restoresharepoint') {
		$restore = $veeam->restoreSharePoint($siteid, $rid, $json);
		echo json_encode($restore);
	}
	if ($action == 'restoresharepointitem') {
		$restore = $veeam->restoreSharePointItem($itemid, $siteid, $rid, $json, $type);
		echo json_encode($restore);
	}
	if ($action == 'restoremultiplesharepointitems') {
		$restore = $veeam->restoreMultipleSharePointItems($siteid, $rid, $json);
		echo json_encode($restore);
	}
} else {
	$veeam = new VBO($host, $port, $version);
	$veeam->logout();
}
?>