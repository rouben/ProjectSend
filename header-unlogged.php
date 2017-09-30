<?php
/**
 * This file generates the header for pages shown to unlogged users and
 * clients (log in form and, if allowed, self registration form).
 *
 * @package ProjectSend
 */

/**
 * Check if the ProjectSend is installed. Done only on the log in form
 * page since all other are inaccessible if no valid session or cookie
 * is set.
 */
if (!is_projectsend_installed()) {
	header("Location:install/index.php");
	exit;
}

/**
 * This is defined on the public download page.
 * So even logged in users can access it.
 */
if (!isset($dont_redirect_if_logged)) {
	/** If logged as a system user, go directly to the back-end homepage */
	if (in_session_or_cookies($allowed_levels)) {
		header("Location:".BASE_URI."home.php");
	}

	/** If client is logged in, redirect to the files list. */
	check_for_client();
}
/**
 * Silent updates that are needed even if no user is logged in.
 */
require_once(ROOT_DIR.'/includes/core.update.silent.php');

?>
<!DOCTYPE html>
<html lang="<?php echo SITE_LANG; ?>">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<title><?php echo $page_title; ?> &raquo; <?php echo THIS_INSTALL_SET_TITLE; ?></title>
	<link rel="shortcut icon" href="<?php echo BASE_URI; ?>/favicon.ico" />
	<script type="text/javascript" src="<?php echo BASE_URI; ?>includes/js/jquery.1.12.4.min.js"></script>

	<link rel="stylesheet" media="all" type="text/css" href="<?php echo BASE_URI; ?>assets/bootstrap/css/bootstrap.min.css" />

	<!--[if lt IE 9]>
		<script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
		<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
	<![endif]-->

	<link rel="stylesheet" media="all" type="text/css" href="<?php echo BASE_URI; ?>css/base.css" />
	<link rel="stylesheet" media="all" type="text/css" href="<?php echo BASE_URI; ?>css/shared.css" />
	
	<link href='<?php echo PROTOCOL; ?>://fonts.googleapis.com/css?family=Open+Sans:400,700,300' rel='stylesheet' type='text/css'>
	<link href='<?php echo PROTOCOL; ?>://fonts.googleapis.com/css?family=Abel' rel='stylesheet' type='text/css'>

	<script type="text/javascript" src="<?php echo BASE_URI; ?>assets/bootstrap/js/bootstrap.min.js"></script>

	<script src="<?php echo BASE_URI; ?>includes/js/jquery.validations.js" type="text/javascript"></script>

	<script type="text/javascript">
		$(document).ready(function() {
			$('.button').click(function() {
				$(this).blur();
			});
		});
	</script>
</head>

<body>

	<header>
		<div id="header">
			<div id="lonely_logo">
				<h1><?php echo THIS_INSTALL_SET_TITLE; ?></h1>
			</div>
		</div>
		<div id="login_header_low">
		</div>
	</header>

	<div id="main">