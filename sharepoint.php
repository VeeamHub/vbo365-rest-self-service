<?php
error_reporting(E_ALL || E_STRICT);
set_time_limit(0);
session_start();

require_once('config.php');
require_once('veeam.class.php');

if (empty($host) || empty($port) || empty($version)) {
    exit('Please modify the configuration file first and configure the Veeam Backup for Microsoft Office 365 host, port and RESTful API version settings.');
}

if (!preg_match('/v[3-4]/', $version)) {
	exit('Invalid API version found. Please modify the configuration file and configure the Veeam Backup for Microsoft Office 365 RESTful API version setting. Only version 3 and 4 are supported.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo $title; ?></title>
    <base href="/" />
    <link rel="shortcut icon" href="images/favicon.ico" />
    <link rel="stylesheet" type="text/css" href="css/bootstrap.min.css" />
    <link rel="stylesheet" type="text/css" href="css/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="css/fontawesome.min.css" />
    <link rel="stylesheet" type="text/css" href="css/style.css" />
	<link rel="stylesheet" type="text/css" href="css/sweetalert2.min.css" />
	<link rel="stylesheet" type="text/css" href="css/jstree.min.css" />
    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/fontawesome.min.js"></script>
    <script src="js/filesize.min.js"></script>
	<script src="js/flatpickr.js"></script>
	<script src="js/jquery.redirect.js"></script>
    <script src="js/moment.min.js"></script>
	<script src="js/sweetalert2.all.min.js"></script>
	<script src="js/jstree.min.js"></script>
</head>
<body>
<?php
if (isset($_SESSION['token'])) {
	$veeam = new VBO($host, $port, $version);
    $veeam->setToken($_SESSION['token']);
	
    $user = $_SESSION['user'];
?>
<nav class="navbar navbar-inverse navbar-static-top">
	<ul class="nav navbar-header">
	  <li><a class="navbar-brand navbar-logo" href="/"><img src="images/logo.svg" alt="Veeam Backup for Microsoft Office 365" class="logo" /></a></li>
	</ul>
	<ul class="nav navbar-nav" id="nav">
	  <li><a href="exchange">Exchange</a></li>
	  <li><a href="onedrive">OneDrive</a></li>
	  <li class="active"><a href="sharepoint">SharePoint</a></li>
	</ul>
	<ul class="nav navbar-nav navbar-right">
	  <li><a href="#"><span class="fa fa-user"></span> Welcome <i><?php echo $user; ?></i> !</a></li>
	  <li id="logout"><a href="#"><span class="fa fa-sign-out-alt"></span> Logout</a></li>
	</ul>
</nav>
<div class="container-fluid">
    <link rel="stylesheet" href="css/sharepoint.css" />
    <aside id="sidebar">
        <div class="logo-container"><i class="logo fa fa-share-alt"></i></div>
        <div class="separator"></div>
        <menu class="menu-segment" id="menu">
		<?php
		if (!isset($_SESSION['rid'])) { /* No restore session is running */
			$check = filter_var($user, FILTER_VALIDATE_EMAIL);

			echo '<ul id="ul-sharepoint-users">';
			
			if ($check === false && strtolower($administrator) == 'yes') {
				$oid = $_GET['oid'];
				$org = $veeam->getOrganizations();
				$menu = false;
				
				for ($i = 0; $i < count($org); $i++) {
					if (isset($oid) && !empty($oid) && $oid == $org[$i]['id']) {
						echo '<li class="active"><a href="sharepoint/' . $org[$i]['id'] . '">' . $org[$i]['name'] . '</a></li>';
					} else {
						echo '<li><a href="sharepoint/' . $org[$i]['id'] . '">' . $org[$i]['name'] . '</a></li>';
					}
				}
			} else {
				$org = $veeam->getOrganization();
				$oid = $org['id'];
				$menu = true;
				
				echo '<li class="active"><a href="sharepoint">' . $org['name'] . '</a></li>';
			}
			
			echo '</ul>';
		} else { /* Restore session is running */
			$rid = $_SESSION['rid'];

			if (strcmp($_SESSION['rtype'], 'vesp') === 0) {
				$content = array();
				$sid = $_GET['sid'];
				$org = $veeam->getOrganizationID($rid);

				echo '<button class="btn btn-default btn-danger btn-stop-restore" title="Stop Restore">Stop Restore</button>';
				echo '<div class="separator"></div>';

				if (isset($sid) && !empty($sid)) {
					$cid = $_GET['cid'];
					$type = $_GET['type'];
					$libraries = $veeam->getSharePointContent($rid, $sid, 'libraries');
					$lists = $veeam->getSharePointContent($rid, $sid, 'lists');
					
					echo '<a href="sharepoint/' . $org['id'] . '"><i class="fa fa-home"></i></a>';
					echo '<ul id="ul-sharepoint-sites">';
					echo '<div class="separator"></div>';

					for ($i = 0; $i < count($libraries['results']); $i++) {
						array_push($content, array('name' => $libraries['results'][$i]['name'], 'id' => $libraries['results'][$i]['id'], 'type' => 'library'));
					}

					for ($i = 0; $i < count($lists['results']); $i++) {
						array_push($content, array('name' => $lists['results'][$i]['name'], 'id' => $lists['results'][$i]['id'], 'type' => 'list'));
					}

					uasort($content, function($a, $b) {
						return strcasecmp($a['name'], $b['name']);
					});

					foreach ($content as $key => $value) {
						if (isset($cid) && !empty($cid) && ($cid == $value['id'])) {
							echo '<li class="active"><a data-type="' . $value['type'] . '" href="sharepoint/' . $org['id'] . '/' . $sid . '/' . $value['id'] . '/' . $value['type'] . '">' . $value['name'] . '</a></li>';
						} else {
							echo '<li><a data-type="' . $value['type'] . '" href="sharepoint/' . $org['id'] . '/' . $sid . '/' . $value['id'] . '/' . $value['type'] . '">' . $value['name'] . '</a></li>';
						}
					}

					echo '</ul>';
				} else {
					$sites = $veeam->getSharePointSites($rid);
					
					if ($sites == '500') { /* Restore session has expired or was killed */
						unset($_SESSION['rid']);
						?>
						<script>
						Swal.fire({
							type: 'info',
							title: 'Restore session expired',
							text: 'Your restore session has expired.'
						}).then(function(e) {
							window.location.href = '/sharepoint';
						});
						</script>
						<?php
					} else {
						for ($i = 0; $i < count($sites['results']); $i++) {
							array_push($content, array('name'=> $sites['results'][$i]['name'], 'id' => $sites['results'][$i]['id']));
						}

						uasort($content, function($a, $b) {
							return strcasecmp($a['name'], $b['name']);
						});

						foreach ($content as $key => $value) {
							echo '<li><a href="sharepoint/' . $org['id'] . '/' . $value['id'] . '">' . $value['name'] . '</a></li>';
						}
					}
				}
			} else {
			   ?>
				<script>
				Swal.fire({
					type: 'info',
					showConfirmButton: false,
					title: 'Restore session running',
					text: 'Found another restore session running, please stop the session first if you want to restore SharePoint items.',
					<?php
					if (strcmp($_SESSION['rtype'], 'vex') === 0) {
						echo "footer: '<a href=\"/exchange\">Go to restore session</a>'";
					} else {
						echo "footer: '<a href=\"/onedrive\">Go to restore session</a>'";
					}
					?>
				})
				</script>
				<?php
				exit;
			}
		}
		?>
        </menu>
        <div class="separator"></div>
        <div class="bottom-padding"></div>
    </aside>
    <main id="main">
		<h1>SharePoint</h1>
        <div class="sharepoint-container">
			<?php
			if (isset($oid) || $menu) {
				if ($check === false && strtolower($administrator) == 'yes') {
					$org = $veeam->getOrganizationByID($oid);
				}
			?>
			<div class="row marginexplore">
				<div class="col-sm-2">
					<div class="input-group flatpickr" data-wrap="true" data-clickOpens="false">
						<input type="text" class="form-control" id="pit-date" placeholder="Select a date..." data-input>
						<span class="input-group-addon" data-open><i class="fa fa-calendar"></i></span>
						<script>
						$('#pit-date').removeClass('errorClass');

						$('.flatpickr').flatpickr({
							dateFormat: "Y.m.d H:i",
							enableTime: true,
							minDate: "<?php echo date('Y.m.d', strtotime($org['firstBackuptime'])); ?>",
							maxDate: "<?php echo date('Y.m.d', strtotime($org['lastBackuptime'])); ?>",
							time_24hr: true
						});
						</script>
					</div>
				</div>
				<div class="col-sm-10">
					<button class="btn btn-default btn-secondary btn-start-restore" title="Start Restore" <?php if (isset($_GET['oid'])) { echo 'data-oid="' . $_GET['oid'] . '"'; } ?> data-latest="false">Start Restore</button>
				
					<button class="btn btn-default btn-secondary btn-start-restore" title="Explore last backup (<?php echo date('d/m/Y H:i T', strtotime($org['lastBackuptime'])); ?>)" <?php if (isset($_GET['oid'])) { echo 'data-oid="' . $_GET['oid'] . '"'; } ?> data-pit="<?php echo date('Y.m.d H:i', strtotime($org['lastBackuptime'])); ?>" data-latest="true">Explore last backup</button>
				</div>
			</div>
			<?php
			}
			
			if (!isset($_SESSION['rid'])) { /* No restore session is running */
				if (isset($oid) && !empty($oid)) {
					$org = $veeam->getOrganizationByID($oid);
					$repo = $veeam->getOrganizationRepository($oid);
					$repoid = end(explode('/', $repo[0]['_links']['backupRepository']['href']));
					$orgdata = $veeam->getOrganizationData($repoid);
					$sites = $veeam->getSiteData($repoid);
					$sitesarray = array();
					
					for ($i = 0; $i < count($orgdata['results']); $i++) {
						if (strcasecmp($org['name'], $orgdata['results'][$i]['displayName']) === 0) {
							$orgid = $orgdata['results'][$i]['organizationId'];
						}
					}
					
					if (count($sites['results']) != '0') {
						for ($i = 0; $i < count($sites['results']); $i++) {
							if (strcmp($sites['results'][$i]['organizationId'], $orgid) === 0) {
								if (!empty($sites['results'][$i]['backedUpTime'])) {
									array_push($sitesarray, array(
										'id' => $sites['results'][$i]['id'],
										'backedUpTime' => $sites['results'][$i]['backedUpTime'],
										'title' => $sites['results'][$i]['title'],
										'url' => $sites['results'][$i]['url']
									));
								}
							}
						}
						
						usort($sitesarray, function($a, $b) {
							return strcmp($a['title'], $b['title']);
						});
					}
						
					if (isset($orgid) && count($sitesarray) != 0) {
					?>
					<div class="alert alert-info">The following is an overview with all the backed up (personal) SharePoint sites within the organization.</div>					
					<table class="table table-bordered table-padding table-striped">
						<thead>
							<tr>
								<th>Sites</th>
								<th>Last backup</th>
								<th>Objects in backup</th>
							</tr>
						</thead>
						<tbody>
						<?php
							for ($i = 0; $i < count($sitesarray); $i++) {							
								echo '<tr>';
								echo '<td>' . $sitesarray[$i]['title'] . '</td>';
								echo '<td>' . date('d/m/Y H:i T', strtotime($sitesarray[$i]['backedUpTime'])) . '</td>';
								echo '<td>';
								
								if (preg_match('/personal/i', $sitesarray[$i]['url'])) {
									echo '<i class="fa fa-user-alt fa-2x" style="color:green" title="Personal SharePoint site"></i>';
								} else {
									echo '<i class="fa fa-share-alt fa-2x" style="color:green" title="SharePoint site"></i>';
								}
								
								echo '</td>';
								echo '</tr>';
							}
						?>
						</tbody>
					</table>
					<?php
					} else {
						if ($check === false && strtolower($administrator) == 'yes') {
							echo '<p>No SharePoint sites found for this organization.</p>';
						} else {
							echo '<p>Select a point in time and start the restore.</p>';
						}
					}
				} else {
					if ($check === false && strtolower($administrator) == 'yes') {
						echo '<p>Select an organization to start a restore session.</p>';
					} else {
						echo '<p>Select a point in time and start the restore.</p>';
					}
				}
			} else { /* Restore session is running */
				if (isset($sid) && !empty($sid)) {
					$name = $veeam->getSharePointSiteName($rid, $sid);

					if (isset($cid) && !empty($cid)) {
						$folders = $veeam->getSharePointTree($rid, $sid, $cid);

						if (strcmp($type, 'list') === 0) { /* Lists have folders and items */
							$items = $veeam->getSharePointTree($rid, $sid, $cid, 'Items');
							$list = $veeam->getSharePointListName($rid, $sid, $cid, 'Lists');
						} else { /* Libraries have folders and documents */
							$documents = $veeam->getSharePointTree($rid, $sid, $cid, 'Documents');
							$list = $veeam->getSharePointListName($rid, $sid, $cid, 'Libraries');
						}
						?>
						<ul class="breadcrumb">
							<li><a href="sharepoint/<?php echo $org['id']; ?>"><i class="fa fa-home"></i></a></li>
							<?php
							if (isset($list) && !empty($list)) {
								echo '<li><a href="sharepoint/' . $org['id'] . '/' . $sid . '">' . $name["name"] . '</a></li>';
								echo '<li class="active">' . $list["name"]. '</li>'; 
							} else {
								echo '<li class="active">' . $name["name"] . '</li>';
							}
							?>
						</ul>
						<?php
						
						if (count($folders['results']) === 0 && count($documents['results']) === 0) {
							echo '<p>No items available.</p>';
						} else {
						?>
						<div class="row">
							<div class="col-sm-2 text-center">
								<div class="btn-group dropdown">
									<button class="btn btn-default dropdown-toggle form-control" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true">Restore selected <span class="caret"></span></button>
									<ul class="dropdown-menu dropdown-menu-right">
									  <li class="dropdown-header">Download as</li>									  
									  <li><a class="dropdown-link" href="javascript:void(0);" onclick="downloadZIP('documents', 'multipleexport', '<?php echo $owner['name']; ?>', '<?php echo $sid; ?>', 'multiple')"><i class="fa fa-download"></i> ZIP file</a></li>
									  <li class="divider"></li>
									  <li class="dropdown-header">Restore to</li>
									  <li><a class="dropdown-link" href="javascript:void(0);" onclick="restoreToOriginal('documents', 'multiplerestore', '<?php echo $sid; ?>', 'multiple')"><i class="fa fa-upload"></i> Original location</a></li>
									</ul>
								</div>
							</div>
							<div class="col-sm-10">
								<input class="form-control search" id="search-sharepoint" placeholder="Filter by item..." />
							</div>
						</div>
						<div class="row">
							<div class="col-sm-2 zeroPadding">
								<table class="table table-bordered table-padding table-striped" id="table-sharepoint-folders">
									<thead>
										<tr>
											<th class="text-center"><strong>Folder Browser</strong></th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td>
												<input type="text" class="form-control search" id="jstree_q" placeholder="Hilight a folder...">
												<div id="jstree">
													<ul>
														<li data-folderid="<?php echo $cid; ?>" data-jstree='{ "opened" : true, "selected": true }'>
															<?php echo $list["name"]; ?>
															<ul>
															<?php
															for ($i = 0; $i < count($folders['results']); $i++) {
																echo '<li data-folderid="'.$folders['results'][$i]['id'].'" data-jstree=\'{ "opened" : true }\'>'.$folders['results'][$i]['name'].'</li>';
															}
															?>
															</ul>
														</li>
													</ul>
												</div>
												<script>
												$(function () {
													$('#jstree').jstree({ 
														core: {
														  check_callback: true
														},
														'plugins': [ 'search', 'sort' ]
													});
													
													var to = false;
												    
													$('#jstree_q').keyup(function (e) {
														if (to) { 
															clearTimeout(to); 
														}
														
														to = setTimeout(function (e) {
															var v = $('#jstree_q').val();
															
															$('#jstree').jstree(true).search(v);
														}, 250);
													});
													
													$('#jstree').on('activate_node.jstree', function (e, data) {
														if (data == undefined || data.node == undefined || data.node.id == undefined || data.node.data.folderid == undefined)
															return;

														var folderid = data.node.data.folderid;
														var parent = data.node.id;
														
														loadFolderItems(folderid, parent);
													});
												});
												</script>
											</td>
										</tr>
									</tbody>
								</table>
							</div>
							<div class="col-sm-10 zeroPadding">
								<div class="wrapper"><div class="loader hide" id="loader"></div></div>
								<table class="table table-bordered table-padding table-striped" id="table-sharepoint-items">
									<thead>
										<tr>
											<th class="text-center"><input type="checkbox" id="chk-all" title="Select all"></th>
											<?php
											if (strcmp($type, 'list') === 0) {
												echo '<th><strong>Title</strong></th>';
											} else {
												echo '<th><strong>Name</strong></th>';
											}
											?>
											<th><strong>Size</strong></th>
											<th><strong>Modification date</strong></th>
											<th><strong>Version</strong></th>
											<th class="text-center"><strong>Options</strong></th>
										</tr>
									</thead>
									<tbody>
										<?php
										for ($i = 0; $i < count($folders['results']); $i++) {
										?>
										<tr>
											<td></td>
											<td><i class="far fa-folder"></i> <a href="javascript:void(0);" onclick="loadFolderItems('<?php echo $folders['results'][$i]['id']; ?>');"><?php echo $folders['results'][$i]['name']; ?></a></td>
											<td>-</td>
											<td><?php echo date('d/m/Y H:i', strtotime($folders['results'][$i]['modificationTime'])) . ' (by ' . $folders['results'][$i]['modifiedBy'] . ')'; ?></td>
											<td>-</td>
											<td class="text-center">
												<div class="btn-group dropdown">
													<button class="btn btn-default dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true">Options <span class="caret"></span></button>
													<ul class="dropdown-menu dropdown-menu-right">
													  <li class="dropdown-header">Download as</li>													  
													  <li><a class="dropdown-link" href="javascript:void(0);" onclick="downloadZIP('folders', '<?php echo $folders['results'][$i]['id']; ?>', '<?php echo $folders['results'][$i]['name']; ?>', '<?php echo $sid; ?>', 'single')"><i class="fa fa-download"></i> ZIP file</a></li>
													  <li class="divider"></li>
													  <li class="dropdown-header">Restore to</li>
													  <li><a class="dropdown-link" href="javascript:void(0);" onclick="restoreToOriginal('folders', '<?php echo $folders['results'][$i]['id']; ?>', '<?php echo $sid; ?>', 'single')"><i class="fa fa-upload"></i> Original location</a></li>
													</ul>
												</div>
											</td>
										</tr>
										<?php
										}
										if (strcmp($type, 'list') === 0) {
											for ($i = 0; $i < count($items['results']); $i++) {
										?>
										<tr>
											<td></td>
											<td><i class="far fa-file"></i> <?php echo $items['results'][$i]['title']; ?></td>
											<td>-</td>
											<td><?php echo date('d/m/Y H:i', strtotime($items['results'][$i]['modificationTime'])) . ' by ' . $items['results'][$i]['modifiedBy'] . ''; ?></em></td>
											<td><?php echo $items['results'][$i]['version']; ?></td>
											<td class="text-center">
												<div class="btn-group dropdown">
													<button class="btn btn-default dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true">Options <span class="caret"></span></button>
													<ul class="dropdown-menu dropdown-menu-right">
													  <li><a class="dropdown-link" href="javascript:void(0);" onclick="restoreToOriginal('items', '<?php echo $folders['results'][$i]['id']; ?>', '<?php echo $sid; ?>', 'single')"><i class="fa fa-upload"></i> Restore item</a></li>
													</ul>
												</div>
											</td>
										</tr>
										<?php
											}
										} else {
											for ($i = 0; $i < count($documents['results']); $i++) {
										?>
										<tr>
											<td class="text-center"><input type="checkbox" name="checkbox-sharepoint" value="<?php echo $documents['results'][$i]['id']; ?>"></td>
											<td><i class="far fa-file"></i> <?php echo $documents['results'][$i]['name']; ?></td>
											<td><script>document.write(filesize(<?php echo $documents['results'][$i]['sizeBytes']; ?>, {round: 2}));</script></td>
											<td><?php echo date('d/m/Y H:i', strtotime($documents['results'][$i]['modificationTime'])) . ' by ' . $documents['results'][$i]['modifiedBy'] . ''; ?></em></td>
											<td><?php echo $documents['results'][$i]['version']; ?></td>
											<td class="text-center">
												<div class="btn-group dropdown">
													<button class="btn btn-default dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true">Options <span class="caret"></span></button>
													<ul class="dropdown-menu dropdown-menu-right">
													  <li class="dropdown-header">Download as</li>												  
													  <li><a class="dropdown-link" href="javascript:void(0);" onclick="downloadFile('documents', '<?php echo $documents['results'][$i]['id']; ?>', '<?php echo $documents['results'][$i]['name']; ?>', '<?php echo $sid; ?>')"><i class="fa fa-download"></i> Plain file</a></li>
													  <li><a class="dropdown-link" href="javascript:void(0);" onclick="downloadZIP('documents', '<?php echo $documents['results'][$i]['id']; ?>', '<?php echo $documents['results'][$i]['name']; ?>', '<?php echo $sid; ?>', 'single')"><i class="fa fa-download"></i> ZIP file</a></li>
													  <li class="divider"></li>
													  <li class="dropdown-header">Restore to</li>
													  <li><a class="dropdown-link" href="javascript:void(0);" onclick="restoreToOriginal('documents', '<?php echo $documents['results'][$i]['id']; ?>', '<?php echo $sid; ?>', 'single')"><i class="fa fa-upload"></i> Original location</a></li>
													</ul>
												</div>
											</td>
										</tr>
										<?php
										}
									}
									?>
									</tbody>
								</table>
								<div class="text-center">
									<?php
									if (count($documents['results']) == '30') {
									?>
										<a class="btn btn-default load-more-link" data-folderid="null" data-siteid="<?php echo $sid; ?>" data-offset="<?php echo count($documents['results'])+1; ?>" href="<?php echo $_SERVER['REQUEST_URI']; ?>#">Load more items</a>
									<?php
									} else {
									?>
										<a class="btn btn-default hide load-more-link" data-folderid="null" data-siteid="<?php echo $sid; ?>" data-offset="<?php echo count($documents['results'])+1; ?>" href="<?php echo $_SERVER['REQUEST_URI']; ?>#">Load more items</a>
									<?php
									}
									?>
								</div>
							</div>
						</div>
						<?php
						}
					} else { /* Select a library or list */
						?>
						<ul class="breadcrumb">
							<li><a href="sharepoint/<?php echo $org['id']; ?>"><i class="fa fa-home"></i></a></li>
							<li class="active"><?php echo $name['name']; ?></li>
						</ul>
						<p>Select a library or list to view the specific content.</p>
						<?php
					}
				} else { /* List all sites */
				?>
				<table class="table table-bordered table-padding table-striped">
					<thead>
						<tr>
							<th>Site</th>
							<th class="text-center">Options</th>
						</tr>
					</thead>
					<tbody>
						<?php
						$siteslist = array();

						for ($i = 0; $i < count($sites['results']); $i++) {
							array_push($siteslist, array('name'=> $sites['results'][$i]['name'], 'id' => $sites['results'][$i]['id']));
						}

						uasort($siteslist, function($a, $b) {
							return strcasecmp($a['name'], $b['name']);
						});

						foreach ($siteslist as $key => $value) {
						?>
						<tr>
							<td><a href="sharepoint/<?php echo $org['id']; ?>/<?php echo $value['id']; ?>"><?php echo $value['name']; ?></a></td>
							<td class="text-center">
								<div class="btn-group dropdown">
									<button class="btn btn-default dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true">Options <span class="caret"></span></button>
									<ul class="dropdown-menu dropdown-menu-right">
									  <li class="dropdown-header">Restore to</li>
									  <li><a class="dropdown-link" href="javascript:void(0);" onclick="restoreToOriginal('documents', '<?php echo $value['name']; ?>', '<?php echo $value['id']; ?>', 'full')"><i class="fa fa-upload"></i> Original location</a></li>
									</ul>
								</div>
							</td>
						</tr>
					<?php
					}
					?>
					</tbody>
				</table>
				<?php
				}
			}
			?>
        </div>
    </main>
</div>

<script>
$('#logout').click(function(e) {
	e.preventDefault();
	
	const swalWithBootstrapButtons = Swal.mixin({
	  confirmButtonClass: 'btn btn-success btn-margin',
	  cancelButtonClass: 'btn btn-danger',
	  buttonsStyling: false,
	})
	
	swalWithBootstrapButtons.fire({
		type: 'question',
		title: 'Logout',
		text: 'You are about to logout. Are you sure you want to continue?',
		showCancelButton: true,
		confirmButtonText: 'Yes',
		cancelButtonText: 'No',
	}).then((result) => {
		if (result.value) {
			$.post('index.php', {'logout' : true}, function(data) {
				window.location.replace('index.php');
			});
		  } else {
			return;
		}
	})
});

/* SharePoint Restore Buttons */
$('.btn-start-restore').click(function(e) {
    if (typeof $(this).data('jid') !== 'undefined') {
        var jid = $(this).data('jid');
    }

    if (typeof $(this).data('oid') !== 'undefined') {
        var oid = $(this).data('oid');
    } else {
        var oid = 'tenant';
	}
	
	if ($(this).data('latest')) {
		var pit = $(this).data('pit');
	} else {
		if (!document.getElementById('pit-date').value) {
			$('#pit-date').addClass('errorClass');
			
			Swal.fire({
				type: 'info',
				title: 'No date selected',
				text: 'Please select a date first before starting the restore or use the \"explore last backup\" button.'
			})
			
			return;
		} else {
			var pit = $('#pit-date').val();
			
			$('#pit-date').removeClass('errorClass');
		}
	}

    var json = '{ "explore": { "datetime": "' + pit + '", "type": "vesp", "ShowAllVersions": "true", "ShowDeleted": "true" } }';

    $(':button').prop('disabled', true);

    $.post('veeam.php', {'action' : 'startrestore', 'json' : json, 'id' : oid}).done(function(data) {
        if (data.match(/([a-zA-Z0-9]{8})-([a-zA-Z0-9]{4})-([a-zA-Z0-9]{4})-([a-zA-Z0-9]{4})-([a-zA-Z0-9]{12})/g)) {
			Swal.fire({
				type: 'success',
				title: 'Session started',
				text: 'Restore session has been started and you can now perform restores.'
			}).then(function(e) {
				window.location.href = 'sharepoint';
			});
        } else {
            Swal.fire({
				type: 'error',
				title: 'Error starting restore session',
				text: '' + data
			})
			
            $(':button').prop('disabled', false);
        }
    });
});
$('.btn-stop-restore').click(function(e) {
    var rid = '<?php echo $rid; ?>';

    const swalWithBootstrapButtons = Swal.mixin({
	  confirmButtonClass: 'btn btn-success btn-margin',
	  cancelButtonClass: 'btn btn-danger',
	  buttonsStyling: false,
	})
	
	swalWithBootstrapButtons.fire({
		type: 'question',
		title: 'Stop the restore session?',
		text: 'This will terminate any restore options for the specific point in time.',
		showCancelButton: true,
		confirmButtonText: 'Stop',
		cancelButtonText: 'Cancel',
	}).then((result) => {
		if (result.value) {
			$.post('veeam.php', {'action' : 'stoprestore', 'id' : rid}).done(function(data) {
				swalWithBootstrapButtons.fire({
					type: 'success', 
					title: 'Restore session was stopped',
					text: 'The restore session was stopped successfully.',
				}).then(function(e) {
					window.location.href = 'sharepoint';
				});
			});
		  } else {
			return;
		}
	})
});

<?php
if (isset($rid)) {
?>
/* Dropdown settings */
$('hide.bs.dropdown').dropdown(function(e) {
    $(e.target).find('>.dropdown-menu:first').slideUp();
});
$('show.bs.dropdown').dropdown(function(e) {
    $(e.target).find('>.dropdown-menu:first').slideDown();
});

/* Select all checkbox */
$('#chk-all').click(function(e) {
    var table = $(e.target).closest('table');
    $('tr:visible :checkbox', table).prop('checked', this.checked);
});

/* Item search */
$('#search-sharepoint').keyup(function(e) {
    var searchText = $(this).val().toLowerCase();
    
    $.each($('#table-sharepoint-items tbody tr'), function(e) {
        if ($(this).text().toLowerCase().indexOf(searchText) === -1) {
           $(this).hide();
        } else {
           $(this).show();
        }
    });
});

$('ul#ul-sharepoint-sites li').click(function(e) {
    $(this).parent().find('li.active').removeClass('active');
    $(this).addClass('active');
});

/* Load more link */
$('.load-more-link').click(function(e) {
    var folderid = $(this).data('folderid');
    var siteid = $(this).data('siteid');
    var offset = $(this).data('offset');
    var rid = '<?php echo $rid; ?>';

    loadItems(folderid, siteid, rid, offset);
});

/* Export to file */
function downloadFile(filetype, itemid, itemname, siteid) {
    var rid = '<?php echo $rid; ?>';
	var json = '{ "save": { "asZip": "false" } }';
	
	Swal.fire({
		type: 'info',
		title: 'Download is starting',
		text: 'Export in progress and your download will start soon.'
	})

	$.post('veeam.php', {'action' : 'exportsharepointitem', 'itemid' : itemid, 'siteid' : siteid, 'rid'	: rid, 'json' : json, 'type' : filetype}).done(function(data) {
		if (data) {
			$.redirect('download.php', {ext : 'plain', file : data, name : itemname}, 'POST');
			
			Swal.close();
		} else {
			Swal.fire({
				type: 'error',
				title: 'Export failed',
				text: 'Export failed.'
			})
			return;
		}
	});
}

/* Export to ZIP file */
function downloadZIP(filetype, itemid, itemname, siteid, type) {
    var rid = '<?php echo $rid; ?>';

	Swal.fire({
		type: 'info',
		title: 'Download is starting',
		text: 'Export in progress and your download will start soon.'
	})
	
	if (type == 'multiple') { /* Multiple items export */
		var act = 'exportmultiplesharepointitems';
		var filetype = 'documents';
		var ids = '';
		var filename = 'exported-sharepointitems';
		
		if ($("input[name='checkbox-sharepoint']:checked").length === 0) { /* Error handling for multiple export button */
			Swal.close();
			
			Swal.fire({
				type: 'error',
				title: 'Restore failed',
				text: 'No items have been selected.'
			})
			
			return;
		}

		$("input[name='checkbox-sharepoint']:checked").each(function(e) {
			ids = ids + '{ "Id": "' + this.value + '" }, ';
		});
		
		var json = '{ \
			"save": { \
				"asZip" : "true", \
				"Documents": [ \
				  ' + ids + ' \
				] \
			} \
		}';
	} else {
		if (type == 'single') {	/* Single item export */
			var act = 'exportsharepointitem';
			var filename = itemname;
		} else { /* Full SharePoint export */
			var act = 'exportsharepoint';
			var filename = 'sharepoint-' + itemname; /* sharepoint-username */
		}
		
		var json = '{ "save": { "asZip": "true" } }';
	}

	$.post('veeam.php', {'action' : act, 'itemid' : itemid, 'siteid' : siteid, 'rid' : rid, 'json' : json, 'type' : filetype}).done(function(data) {
		if (data && data != '500') {
			$.redirect('download.php', {ext : 'zip', file : data, name : filename}, 'POST');
			
			Swal.close();
		} else {
			Swal.fire({
				type: 'error',
				title: 'Export failed',
				text: '' + data
			})
			
			return;
		}
	});
}

/* Restore to original location */
function restoreToOriginal(filetype, itemid, siteid, type) {
    var rid = '<?php echo $rid; ?>';
	
	if (type == 'multiple' && $("input[name='checkbox-sharepoint']:checked").length == 0) { /* Error handling for multiple restore button */
		Swal.fire({
			type: 'error',
			title: 'Restore failed',
			text: 'No items have been selected.'
		})
		return;
	}
	
	const swalWithBootstrapButtons = Swal.mixin({
	  confirmButtonClass: 'btn btn-success',
	  cancelButtonClass: 'btn btn-danger btn-margin',
	  buttonsStyling: false,
	  input: 'text'
	})
	
	swalWithBootstrapButtons.fire({
		title: 'Restore to the original location',
		html: 
			'<form method="POST">' +
			'<div class="form-group row">' +
			'<div class="alert alert-warning" role="alert">Warning: this will restore the last version of the item.</div>' +
			'<label for="restore-original-user" class="col-sm-4 col-form-label text-right">Username:</label>' +
			'<div class="col-sm-8"><input type="text" class="form-control restoredata" id="restore-original-user" placeholder="user@example.onmicrosoft.com"></input></div>' +
			'</div>' +
			'<div class="form-group row">' +
			'<label for="restore-original-pass" class="col-sm-4 col-form-label text-right">Password:</label>' +
			'<div class="col-sm-8"><input type="password" class="form-control restoredata" id="restore-original-pass" placeholder="password"></input></div>' +
			'</div>' +
			'<div class="form-group row">' +
			'<label for="restore-original-action" class="col-sm-4 col-form-label text-right">If the file exists:</label>' +
			'<div class="col-sm-8"><select class="form-control restoredata" id="restore-original-action">' +
			'<option value="merge">Merge file</option>' +
			'<option value="overwrite">Overwrite file</option>' +
			'</select></div>' +
			'</div>' +
			'<div class="form-group row">' +
			'<label for="restore-original-permissions" class="col-sm-4 col-form-label text-right">Restore permissions:</label>' +
			'<div class="col-sm-8"><select class="form-control restoredata" id="restore-original-permissions">' +
			'<option value="true">Yes</option>' +
			'<option value="false">No</option>' +
			'</select></div>' +
			'<input type="hidden" id="restore-original-listname" value="<?php echo $list['name']; ?>"></input>' +
			'</div>' +
			'</form>',			
		focusConfirm: false,
		showCancelButton: true,
		confirmButtonText: 'Restore',
		cancelButtonText: 'Cancel',
		reverseButtons: true,
		inputValidator: () => {
			var elem = document.getElementById('swal2-validation-message');
			elem.style.setProperty('margin', '10px 0px', '');
			
			var restoredata = Object.values(document.getElementsByClassName('restoredata'));
			var errors = [ 'No username defined.', 'No password defined.' ];
			
			for (var i = 0; i < restoredata.length; i++) {
				if (!restoredata[i].value)
					return errors[i];
			}
		},
		onBeforeOpen: function (dom) {
			swal.getInput().style.display = 'none';
		},
		preConfirm: function() {
		   return new Promise(function(resolve) {
				resolve([
					$('#restore-original-user').val(),
					$('#restore-original-pass').val(),
					$('#restore-original-action').val(),
					$('#restore-original-listname').val(),
					$('#restore-original-permissions').val(),
				 ]);
			});
		},
	}).then(function(result) {
		if (result.value) {
			var user = $('#restore-original-user').val();
			var pass = $('#restore-original-pass').val();
			var listname = $('#restore-original-listname').val();
        	var restoreaction = $('#restore-original-action').val();
		    var restorepermissions = $('#restore-original-permissions').val();
		
			Swal.fire({
				type: 'info',
				title: 'Item restore in progress',
				text: 'Restore in progress...'
			})
			
			if (type == 'multiple') { /* Multiple items restore */
				var act = 'restoremultiplesharepointitems';
				filetype = 'documents';
				var ids = '';
				
				$("input[name='checkbox-sharepoint']:checked").each(function(e) {
					ids = ids + '{ "Id": "' + this.value + '" }, ';
				});
				
				var json = '{ "restoreTo": \
					{ "userName": "' + user + '", \
					  "userPassword": "' + pass + '", \
					  "list" : "' + listname + '", \
					  "restorePermissions" : "' + restorepermissions + '", \
					  "sendSharedLinksNotification": "true", \
					  "documentVersion" : "last", \
					  "documentLastVersionAction" : "' + restoreaction + '", \
					  "Documents": [ \
						' + ids + ' \
					  ] \
					} \
				}';
			} else {
				if (type == 'single') { /* Single item restore */
					var act = 'restoresharepointitem';
					
					if ((filetype == 'libraries') || (filetype == 'lists')) {
						var json = '{ "restoreTo": \
							{ "userName": "' + user + '", \
							  "userPassword": "' + pass + '", \
							  "list" : "' + listname + '", \
							  "restorePermissions" : "' + restorepermissions + '", \
							  "sendSharedLinksNotification": "true", \
							  "documentVersion" : "last", \
							  "documentLastVersionAction" : "' + restoreaction + '", \
							  "RestoreListViews" : "true", \
							  "changedItems" : "true", \
							  "DeletedItems" : "true" \
							} \
						}';
					} else {
						var json = '{ "restoreTo": \
							{ "userName": "' + user + '", \
							  "userPassword": "' + pass + '", \
							  "list" : "' + listname + '", \
							  "restorePermissions" : "' + restorepermissions + '", \
							  "sendSharedLinksNotification": "true", \
							  "documentVersion" : "last", \
							  "documentLastVersionAction" : "' + restoreaction + '", \
							} \
						}';
					}
				} else if (type == 'full') { /* Full SharePoint restore */
					var act = 'restoresharepoint';
					
					var json = '{ "restoreTo": \
						{ "userName": "' + user + '", \
						  "userPassword": "' + pass + '", \
						  "list" : "' + listname + '", \
						  "restorePermissions" : "' + restorepermissions + '", \
						  "sendSharedLinksNotification": "true", \
						  "documentVersion" : "last", \
						  "documentLastVersionAction" : "' + restoreaction + '", \
						  "RestoreListViews" : "true", \
						  "changedItems" : "true", \
						  "DeletedItems" : "true", \
						  "RestoreSubsites" : "true", \
						  "RestoreMasterPages" : "true" \
						} \
					}';
				}
			}

			$.post('veeam.php', {'action' : act, 'itemid' : itemid, 'siteid' : siteid, 'rid' : rid, 'json' : json, 'type' : filetype}).done(function(data) {
				Swal.fire({
					type: 'info',
					title: 'Item restore',
					text: '' + data
				})
			});
		} else {
			return;
		}
	});
}

function disableTree() {
  $('#jstree li.jstree-node').each(function(e) {
    $('#jstree').jstree('disable_node', this.id)
  })
  
  $('#jstree i.jstree-ocl').off('click.block').on('click.block', function(e) {
    return false;
  });
}  
function enableTree() {
  $('#jstree li.jstree-node').each(function(e) {
    $('#jstree').jstree('enable_node', this.id)
  });
  
  $('#jstree i.jstree-ocl').off('click.block');
}

/* SharePoint functions */
function fillTableDocuments(response, siteid, type) {
    if (response.results.length !== 0) {
        for (var i = 0; i < response.results.length; i++) {
            if (type == 'documents') {
                var size = filesize(response.results[i].sizeBytes, {round: 2});
				
				$('#table-sharepoint-items tbody').append('<tr> \
				<td class="text-center"><input type="checkbox" name="checkbox-sharepoint" value="' + response.results[i].id + '"></td> \
                <td><i class="far fa-file"></i> ' + response.results[i].name + '</td> \
                <td>' + size + '</td> \
				<td>' + moment(response.results[i].modificationTime).format('DD/MM/YYYY HH:mm') + ' (by ' + response.results[i].modifiedBy + ')</td> \
                <td>' + response.results[i].version + '</td> \
                <td class="text-center"> \
                <div class="dropdown"> \
                <button class="btn btn-default dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true">Options <span class="caret"></span></button> \
                <ul class="dropdown-menu dropdown-menu-right"> \
                <li class="dropdown-header">Download as</li> \
                <li><a class="dropdown-link" href="javascript:void(0);" onclick="downloadFile(\'documents\', \'' + response.results[i].id + '\', \'' + response.results[i].name + '\', \'' + siteid + '\')"><i class="fa fa-download"></i> Plain file</a></li> \
				<li><a class="dropdown-link" href="javascript:void(0);" onclick="downloadZIP(\'documents\', \'' + response.results[i].id + '\', \'' + response.results[i].name + '\', \'' + siteid + '\', \'single\')"><i class="fa fa-download"></i> ZIP file</a></li> \
                <li class="divider"></li> \
                <li class="dropdown-header">Restore to</li> \
                <li><a class="dropdown-link" href="javascript:void(0);" onclick="restoreToOriginal(\'documents\', \'' + response.results[i].id + '\', \'' + siteid + '\', \'single\')"><i class="fa fa-upload"></i> Original location</a></li> \
                </ul> \
                </div> \
                </td> \
                </tr>');
            } else {			
				$('#table-sharepoint-items tbody').append('<tr> \
				<td class="text-center"><input type="checkbox" name="checkbox-sharepoint" value="' + response.results[i].id + '"></td> \
                <td><i class="far fa-file"></i> ' + response.results[i].name + '</td> \
                <td>-</td> \
				<td>' + moment(response.results[i].modificationTime).format('DD/MM/YYYY HH:mm') + ' (by ' + response.results[i].modifiedBy + ')</td> \
                <td>' + response.results[i].version + '</td> \
                <td class="text-center"> \
                <div class="dropdown"> \
                <button class="btn btn-default dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true">Options <span class="caret"></span></button> \
                <ul class="dropdown-menu dropdown-menu-right"> \
				<li><a class="dropdown-link" href="javascript:void(0);" onclick="restoreToOriginal(\'items\', \'' + response.results[i].id + '\', \'' + siteid + '\', \'single\')"><i class="fa fa-upload"></i> Restore item</a></li> \
                </ul> \
                </div> \
                </td> \
                </tr>');
            }            
        }
    }
}

function fillTableFolders(response, folderid, siteid) {
    if (response.results.length !== 0) {
        for (var i = 0; i < response.results.length; i++) {
            $('#table-sharepoint-items tbody').append('<tr> \
				<td></td> \
                <td><i class="far fa-folder"></i> <a href="javascript:void(0);" onclick="loadFolderItems(\'' + response.results[i].id + '\');">' + response.results[i].name + '</a></td> \
                <td>-</td> \
				<td>' + moment(response.results[i].modificationTime).format('DD/MM/YYYY HH:mm') + ' (by ' + response.results[i].modifiedBy + ')</td> \
                <td>-</td> \
                <td class="text-center"> \
                <div class="dropdown"> \
                <button class="btn btn-default dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true">Options <span class="caret"></span></button> \
                <ul class="dropdown-menu dropdown-menu-right"> \
				<li class="dropdown-header">Download as</li> \
				<li><a class="dropdown-link" href="javascript:void(0);" onclick="downloadZIP(\'folders\', \'' + response.results[i].id + '\', \'' + response.results[i].name + '\', \'' + siteid + '\', \'single\')"><i class="fa fa-download"></i> ZIP file</a></li> \
                <li class="dropdown-header">Restore to</li> \
				<li><a class="dropdown-link" href="javascript:void(0);" onclick="restoreToOriginal(\'folders\', \'' + response.results[i].id + '\', \'' + siteid + '\', \'single\')"><i class="fa fa-upload"></i> Original location</a></li> \
                </ul> \
                </div> \
                </td> \
                </tr>');
        }
    }
}

function loadFolderItems(folderid, parent) {
	if (arguments.length == 1) {
		parent = null;
	}
	
    var responsedocuments, responsefolders;
	var rid = '<?php echo $rid; ?>';
	var siteid = '<?php echo $sid; ?>';
	
	disableTree();
	
	$('#table-sharepoint-items tbody').empty();
	$('#loader').removeClass('hide');
	$('a.load-more-link').addClass('hide');
	
    $.post('veeam.php', {'action' : 'getsharepointitems', 'rid' : rid, 'siteid' : siteid, 'type' : 'Folders', 'folderid' : folderid}).done(function(data) {
        responsefolders = JSON.parse(data);

		if (parent !== null) {
			if (responsefolders.results.length !== 0) {
				var responsefolderid, responsefoldername;
				var node = $('#jstree').jstree('get_selected');
				var children = $('#jstree').jstree('get_children_dom', node);

				if (children.length === 0) {
					for (var i = 0; i < responsefolders.results.length; i++) {
						responsefolderid = responsefolders.results[i].id;
						responsefoldername = responsefolders.results[i].name;
						
						$('#jstree').jstree('create_node', parent, {data: {"folderid" : responsefolderid}, text: responsefoldername});
						
						$('#jstree').on('create_node.jstree', function (e, data) {
							$('#jstree').jstree('open_node', data.parent);
						});
					}
				} else {
					var childrenFolderidArray = [];
					var selectedNode, existingid;
					
					for (var j = 0; j < children.length; j++) {
						selectedNode = $('#jstree').jstree(true).get_node(children[j], true);
						existingid = selectedNode[0].dataset.folderid;
						
						childrenFolderidArray.push(existingid);
					}
					
					for (var i = 0; i < responsefolders.results.length; i++) {
						responsefolderid = responsefolders.results[i].id;
						responsefoldername = responsefolders.results[i].name;
						
						if (!childrenFolderidArray.push(responsefolderid)) {
							$('#jstree').jstree('create_node', parent, {data: {"folderid" : responsefolderid}, text: responsefoldername});
							
							$('#jstree').on('create_node.jstree', function (e, data) {
								$('#jstree').jstree('open_node', data.parent);
							});
						}
					}
				}
			}
		} else {
			var childrenFolderidArray = [];
			var children, treeid, treefolderid;
			var treedata = $('#jstree').jstree(true).get_json('#', {'flat': true});
			
			for (var i = 0; i < treedata.length; i++) {
				treeid = treedata[i]['id'];
				treefolderid = treedata[i]['data']['folderid'];
				
				if (treefolderid === folderid) {
					$('#jstree').jstree('deselect_all');
					$('#jstree').jstree('select_node', treeid);
					
					children = $('#jstree').jstree('get_children_dom', treeid);
					
					if (children.length === 0) {
						for (var x = 0; x < responsefolders.results.length; x++) {
							responsefolderid = responsefolders.results[x].id;
							responsefoldername = responsefolders.results[x].name;
							$('#jstree').jstree('create_node', treeid, {data: {"folderid" : responsefolderid}, text: responsefoldername});

							$('#jstree').on('create_node.jstree', function (e, data) {
								$('#jstree').jstree('open_node', data.parent);
							});
						}
					} else {
						for (var j = 0; j < children.length; j++) {
							selectedNode = $('#jstree').jstree(true).get_node(treeid, true);
							existingid = selectedNode[0].dataset.folderid;
							
							childrenFolderidArray.push(existingid);
						}
						
						for (var x = 0; x < responsefolders.results.length; x++) {
							responsefolderid = responsefolders.results[x].id;
							responsefoldername = responsefolders.results[x].name;
							
							if (!childrenFolderidArray.push(responsefolderid)) {
								$('#jstree').jstree('create_node', treeid, {data: {"folderid" : responsefolderid}, text: responsefoldername});
								console.log('createB');
								$('#jstree').on('create_node.jstree', function (e, data) {
									$('#jstree').jstree('open_node', data.parent);
								});
							}
						}
					}
				}
			}
		}
    });

	setTimeout(function(e) {
		<?php
		if (strcmp($type, 'list') === 0) {
		?>
		$.post('veeam.php', {'action' : 'getsharepointitems', 'rid' : rid, 'siteid' : siteid, 'type' : 'Items', 'folderid' : folderid}).done(function(data) {
			responsedocuments = JSON.parse(data);
		});
		<?php
		} else {
		?>
		$.post('veeam.php', {'action' : 'getsharepointitems', 'rid' : rid, 'siteid' : siteid, 'type' : 'Documents', 'folderid' : folderid}).done(function(data) {
			responsedocuments = JSON.parse(data);
		});
		<?php
		}
		?>
	}, 2000);

    setTimeout(function(e) {
		if ((typeof responsefolders !== 'undefined' && responsefolders.results.length === 0) && (typeof responsedocuments !== 'undefined' && responsedocuments.results.length === 0)) {
			$('#table-sharepoint-items tbody').append('<tr><td class="text-center" colspan="6">No items available in this folder.</td></tr>');
		}
		
        if (typeof responsefolders !== 'undefined' && responsefolders.results.length !== 0) {
            fillTableFolders(responsefolders, folderid, siteid);
        }

        if (typeof responsedocuments !== 'undefined' && responsedocuments.results.length !== 0) {
            <?php
            if (strcmp($type, 'list') === 0) {
            ?>
            fillTableDocuments(responsedocuments, siteid, 'items');
            <?php
            } else {
            ?>
            fillTableDocuments(responsedocuments, siteid, 'documents');
            <?php
            }
            ?>
        }

        if ((typeof responsefolders !== 'undefined' && responsefolders.results.length == '30') || (typeof responsedocuments !== 'undefined' && responsedocuments.results.length == '30')) {
            $('a.load-more-link').removeClass('hide');
            $('a.load-more-link').data('offset', 30);
            $('a.load-more-link').data('folderid', folderid);
        } else {
            $('a.load-more-link').addClass('hide');
        }
		
		$('#loader').addClass('hide');
		enableTree();
    }, 3000);
}

function loadItems(folderid, siteid, offset) {
	var rid = '<?php echo $rid; ?>';
    var responsedocuments, responsefolders;

    $.post('veeam.php', {'action' : 'getsharepointitems', 'folderid' : folderid, 'rid' : rid, 'siteid' : siteid, 'offset' : offset, 'type' : 'folders'}).done(function(data) {
        responsefolders = JSON.parse(data);
    });

    $.post('veeam.php', {'action' : 'getsharepointitems', 'folderid' : folderid, 'rid' : rid, 'siteid' : siteid, 'offset' : offset, 'type' : 'documents'}).done(function(data) {
        responsedocuments = JSON.parse(data);
    });

    setTimeout(function(e) {
        if (typeof responsefolders !== 'undefined') {
            fillTableFolders(responsefolders, folderid, siteid);
        }

        if (typeof responsedocuments !== 'undefined') {
            fillTableDocuments(responsedocuments, siteid);
        }

        if ((typeof responsefolders !== 'undefined' && responsefolders.results.length == '30') || (typeof responsedocuments !== 'undefined' && responsedocuments.results.length == '30')) {
            $('a.load-more-link').removeClass('hide');
            $('a.load-more-link').data('offset', offset + 30);
            $('a.load-more-link').data('folderid', folderid);
        } else {
            $('a.load-more-link').addClass('hide');
        }
    }, 2000);
}
<?php
}
?>
</script>
<?php
} else {
	if (isset($_SESSION['refreshtoken'])) {
		$veeam->refreshToken($_SESSION['refreshtoken']);
		
		$_SESSION['refreshtoken'] = $veeam->getRefreshToken();
        $_SESSION['token'] = $veeam->getToken();
	} else {
		unset($_SESSION);
		session_destroy();
		?>
		<script>
		Swal.fire({
			type: 'info',
			title: 'Session expired',
			text: 'Your session has expired and requires you to log in again.'
		}).then(function(e) {
			window.location.href = '/index.php';
		});
		</script>
		<?php
	}
}
?>
</body>
</html>