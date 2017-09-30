<?php
$startScriptTime=microtime(TRUE);
/**
 * Contains the form and the processes used to install ProjectSend.
 *
 * @package		ProjectSend
 * @subpackage	Install (config check)
 */
error_reporting(E_ALL);

define( 'IS_INSTALL', true );
define( 'IS_MAKE_CONFIG', true );

define( 'ABS_PARENT', dirname( dirname(__FILE__) ) );
require_once( ABS_PARENT . '/sys.includes.php' );

$page_title_install		= __('Install','cftp_admin');

// array of POST variables to check, with associated default value
$post_vars = array(
	'dbdriver'		=> 'mysql',
	'dbname'		=> 'projectsend',
	'dbuser'		=> 'root',
	'dbpassword'	=> 'root',
	'dbhost'		=> 'localhost',
	'dbprefix'		=> 'tbl_',
	'dbreuse'		=> 'no',
	'lang'			=> 'en'
);

// parse all variables in the above array and fill in whatever is sent via POST
foreach ($post_vars as $var=>$value) {
	if (isset($_POST[$var])) {
		$post_vars[$var] = trim(stripslashes((string) $_POST[$var]));
	}
}

//check PDO status
$pdo_available_drivers = PDO::getAvailableDrivers();
$pdo_mysql_available = in_array('mysql', $pdo_available_drivers);
$pdo_mssql_available = in_array('dblib', $pdo_available_drivers);
$pdo_driver_available = $pdo_mysql_available || $pdo_mssql_available;

// only mysql driver is available, let's force it
if ($pdo_mysql_available && !$pdo_mssql_available) {
	$post_vars['dbdriver'] = 'mysql';
}

// only mysql driver is available, let's force it
if (!$pdo_mysql_available && $pdo_mssql_available) {
	$post_vars['dbdriver'] = 'mssql';
}

if ($pdo_driver_available) {
	// check host connection
	$dsn = $post_vars['dbdriver'] . ':host=' . $post_vars['dbhost'] . ';dbname=' . $post_vars['dbname'];
	try{
		$db = new PDO($dsn, $post_vars['dbuser'], $post_vars['dbpassword'], array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
		$pdo_connected = true;
	}
	catch(PDOException $ex){
    	$pdo_connected = false;
	}
}

// names of reserved tables
$table_names = array(
	'actions_log',
	'downloads',
	'files',
	'files_relations',
	'folders',
	'groups',
	'members',
	'notifications',
	'options',
	'password_reset',
	'users',
);

// check if tables exists
$table_exists = false;
if ($pdo_connected) {
	foreach ($table_names as $name) {
		$table_exists = $table_exists || table_exists($db, $post_vars['dbprefix'].$name);
	}
}
$reuse_tables =  $post_vars['dbreuse'] == 'reuse';

// scan for language files
$po_files = scandir(ROOT_DIR.'/lang/');
$langs = array();
foreach ($po_files as $file) {
  if (preg_match("/\.po$/", $file)) {
	  $langs[] = substr($file,0,-3); // removes last 3 characters
  }
}
sort($langs, SORT_STRING);

// ok if selected language has a .po file associated
$lang_ok = in_array($post_vars['lang'], $langs);

// check file & folders are writable
$config_file = ROOT_DIR.'/includes/sys.config.php';
$config_file_writable = is_writable($config_file) || is_writable(dirname($config_file));
$upload_dir = ROOT_DIR.'/upload';
$upload_files_dir = ROOT_DIR.'/upload/files';
$upload_files_dir_writable = is_writable($upload_files_dir) || is_writable($upload_dir);
$upload_temp_dir = ROOT_DIR.'/upload/temp';
$upload_temp_dir_writable = is_writable($upload_temp_dir) || is_writable($upload_dir);

// retrieve user data for web server
if (function_exists('posix_getpwuid')) {
	$server_user_data = posix_getpwuid(posix_getuid());
	$server_user = $server_user_data['name'] . ' (uid=' . $server_user_data['uid'] . ' gid=' . $server_user_data['gid'] . ')';
} else {
	$server_user = getenv('USERNAME');
}

// if everything is ok, we can proceed
$ready_to_go = $pdo_connected && (!$table_exists || $reuse_tables) && $lang_ok && $config_file_writable && $upload_files_dir_writable && $upload_temp_dir_writable;

// if the user requested to write the config file AND we can proceed, we try to write the new configuration
if (isset($_POST['submit-start']) && $ready_to_go) {
	$out = '<?php
define(\'DB_DRIVER\', \''.$post_vars['dbdriver'].'\');
define(\'DB_NAME\', \''.$post_vars['dbname'].'\');
define(\'DB_HOST\', \''.$post_vars['dbhost'].'\');
define(\'DB_USER\', \''.$post_vars['dbuser'].'\');
define(\'DB_PASSWORD\', \''.$post_vars['dbpassword'].'\');
define(\'TABLES_PREFIX\', \''.$post_vars['dbprefix'].'\');
define(\'SITE_LANG\',\''.$post_vars['lang'].'\');
define(\'MAX_FILESIZE\',2048);
define(\'EMAIL_ENCODING\', \'utf-8\');
';
	$result = file_put_contents($config_file, $out, LOCK_EX);
	if ($result > 0) {
		// config file written successfully
		error_reporting(0);
		return;
	}
}


/**
 * Check if a table exists in the current database.
 * (taken from stackoverflow)
 *
 * @param PDO $pdo PDO instance connected to a database.
 * @param string $table Table to search for.
 * @return bool TRUE if table exists, FALSE if no table found.
 */
function table_exists($pdo, $table) {

    // Try a select statement against the table
    // Run it in try/catch in case PDO is in ERRMODE_EXCEPTION.
    try {
        $result = $pdo->query("SELECT 1 FROM $table LIMIT 1");
    } catch (Exception $e) {
        // We got an exception == table not found
        return FALSE;
    }

    // Result is either boolean FALSE (no table found) or PDOStatement Object (table found)
    return $result !== FALSE;
}

function pdo_status_label() {
	global $pdo_connected;
	if ($pdo_connected) {
?>
		<span class="label label-success">OK</span>
<?php
	} else {
?>
		<span class="label label-danger">!</span>
<?php
	}
}

include_once( ABS_PARENT . '/header-unlogged.php' );
?>

	<!-- #main opened on header.php -->
		<div class="container">
			<div class="row">
				<div class="col-xs-12 col-xs-offset-0 col-sm-8 col-sm-offset-2 col-md-6 col-md-offset-3 white-box">
					<div class="white-box-interior">
	
						<script type="text/javascript">
							$(document).ready(function() {
							/*	$("form").submit(function() {
									clean_form(this);
			
									is_complete(this.this_install_title,'<?php echo $install_no_sitename; ?>');
									is_complete(this.base_uri,'<?php echo $install_no_baseuri; ?>');
									is_complete(this.install_user_fullname,'<?php echo $validation_no_name; ?>');
									is_complete(this.install_user_mail,'<?php echo $validation_no_email; ?>');
									// username
									is_complete(this.install_user_username,'<?php echo $validation_no_user; ?>');
									is_length(this.install_user_username,<?php echo MIN_USER_CHARS; ?>,<?php echo MAX_USER_CHARS; ?>,'<?php echo $validation_length_user; ?>');
									is_alpha_or_dot(this.install_user_username,'<?php echo $validation_alpha_user; ?>');
									// password fields
									is_complete(this.install_user_pass,'<?php echo $validation_no_pass; ?>');
									//is_complete(this.install_user_repeat,'<?php echo $validation_no_pass2; ?>');
									is_email(this.install_user_mail,'<?php echo $validation_invalid_mail; ?>');
									is_length(this.install_user_pass,<?php echo MIN_USER_CHARS; ?>,<?php echo MAX_USER_CHARS; ?>,'<?php echo $validation_length_pass; ?>');
									is_password(this.install_user_pass,'<?php $chars = addslashes($validation_valid_chars); echo $validation_valid_pass." ".$chars; ?>');
									//is_match(this.install_user_pass,this.install_user_repeat,'<?php echo $validation_match_pass; ?>');
			
									// show the errors or continue if everything is ok
									if (show_form_errors() == false) { return false; }
								}); */
							});
						</script>
			
						<form action="make-config.php" name="installform" method="post" class="form-horizontal">
			
							<h3><?php _e('Database configuration','cftp_admin'); ?></h3>
							<p><?php _e('You need to provide this data for a correct database communication.','cftp_admin'); ?></p>

							<div class="form-group">
								<label for="dbdriver" class="col-sm-4 control-label"><?php _e('Select driver','cftp_admin'); ?></label>
								<div class="col-sm-6">
									<div class="radio <?php if ( !$pdo_mysql_available ) { echo 'disabled'; } ?>">
										<label for="dbdriver_mysql">
											<input type="radio" id="dbdriver_mysql" name="dbdriver" value="mysql" <?php echo !$pdo_mysql_available ? 'disabled' : ''; ?> <?php echo $post_vars['dbdriver'] == 'mysql' ? 'checked' : ''; ?> />
											MySQL <?php if ( !$pdo_mysql_available ) { _e('(not supported)','cftp_admin'); } ?>
										</label>
									</div>
									<div class="radio <?php if ( !$pdo_mssql_available ) { echo 'disabled'; } ?>">
										<label for="dbdriver_mssql">
											<input type="radio" id="dbdriver_mssql" name="dbdriver" value="mssql" <?php echo !$pdo_mssql_available ? 'disabled' : ''; ?> <?php echo $post_vars['dbdriver'] == 'mssql' ? 'checked' : ''; ?> />
											MS SQL Server <?php if ( !$pdo_mssql_available ) { _e('(not supported)','cftp_admin'); } ?>
										</label>
									</div>
								</div>
								<div class="col-sm-2">
									<?php if ($pdo_driver_available) : ?>
										<span class="label label-success">OK</span>
									<?php else : ?>
										<span class="label label-danger">!</span>
									<?php endif; ?>
								</div>
							</div>

							<div class="form-group">
								<label for="dbhost" class="col-sm-4 control-label"><?php _e('Host','cftp_admin'); ?></label>
								<div class="col-sm-6">
									<input type="text" name="dbhost" id="dbhost" <?php echo !$pdo_driver_available ? 'disabled' : ''; ?> class="required form-control" value="<?php echo $post_vars['dbhost']; ?>" />
								</div>
								<div class="col-sm-2">
									<?php pdo_status_label(); ?>
								</div>
							</div>

							<div class="form-group">
								<label for="dbname" class="col-sm-4 control-label"><?php _e('Database name','cftp_admin'); ?></label>
								<div class="col-sm-6">
									<input type="text" name="dbname" id="dbname" <?php echo !$pdo_driver_available ? 'disabled' : ''; ?> class="required form-control" value="<?php echo $post_vars['dbname']; ?>" />
								</div>
								<div class="col-sm-2">
									<?php pdo_status_label(); ?>
								</div>
							</div>

							<div class="form-group">
								<label for="dbuser" class="col-sm-4 control-label"><?php _e('Username','cftp_admin'); ?></label>
								<div class="col-sm-6">
									<input type="text" name="dbuser" id="dbuser" <?php echo !$pdo_driver_available ? 'disabled' : ''; ?> class="required form-control" value="<?php echo $post_vars['dbuser']; ?>" />
								</div>
								<div class="col-sm-2">
									<?php pdo_status_label(); ?>
								</div>
							</div>

							<div class="form-group">
								<label for="dbpassword" class="col-sm-4 control-label"><?php _e('Password','cftp_admin'); ?></label>
								<div class="col-sm-6">
									<input type="text" name="dbpassword" id="dbpassword" <?php echo !$pdo_driver_available ? 'disabled' : ''; ?> class="required form-control" value="<?php echo $post_vars['dbpassword']; ?>" />
								</div>
								<div class="col-sm-2">
									<?php pdo_status_label(); ?>
								</div>
							</div>

							<div class="form-group">
								<label for="dbprefix" class="col-sm-4 control-label"><?php _e('Table prefix','cftp_admin'); ?></label>
								<div class="col-sm-6">
									<input type="text" name="dbprefix" id="dbprefix" <?php echo !$pdo_driver_available ? 'disabled' : ''; ?> class="required form-control" value="<?php echo $post_vars['dbprefix']; ?>" />
								</div>
								<div class="col-sm-2">
									<?php if ($pdo_connected) : ?>
										<?php if (!$table_exists || $reuse_tables) : ?>
											<span class="label label-success">OK</span>
										<?php else : ?>
											<span class="label label-danger">!</span>
										<?php endif; ?>
										<?php if ($table_exists) : ?>
											<p class="label label-danger"><?php _e('The database is already populated','cftp_admin'); ?></p>
										<?php endif; ?>
									<?php endif; ?>
								</div>
							</div>
			
							<?php if ($pdo_connected) : ?>
								<?php if ($table_exists) : ?>
									<div class="form-group">
										<div class="col-sm-offset-4 col-sm-6">
											<div class="checkbox">
												<label for="dbreuse">
													<input type="checkbox" name="dbreuse" id="dbreuse" value="reuse" <?php echo $post_vars['dbreuse'] == 'reuse' ? 'checked' : ''; ?> /> <?php _e('Reuse existing tables','cftp_admin'); ?>
												</label>
											</div>
										</div>
										<div class="col-sm-2">
											<?php if ($reuse_tables) : ?>
												<span class="label label-success">OK</span>
											<?php endif; ?>
										</div>
									</div>
								<?php endif; ?>
							<?php endif; ?>

							<div class="options_divide"></div>
			
							<h3><?php _e('Language selection','cftp_admin'); ?></h3>

							<div class="form-group">
								<label for="lang" class="col-sm-4 control-label"><?php _e('Language','cftp_admin'); ?></label>
								<div class="col-sm-6">
									<select name="lang" class="form-control">
										<?php foreach ($langs as $l) : ?>
											<option value="<?php echo $l;?>" <?php echo $post_vars['lang']==$l ? 'selected' : ''; ?>><?php echo $l;?></option>
										<?php endforeach?>
									</select>
								</div>
							</div>
			
							<div class="options_divide"></div>
			
							<h3><?php _e('Folders','cftp_admin'); ?></h3>

							<?php
								$write_checks = array(
														'config'		=> array(
																				'label'		=> 'Config file',
																				'file'		=> $config_file,
																				'status'	=> $config_file_writable,
																			),
														'upload'		=> array(
																				'label'		=> 'Upload directory',
																				'file'		=> $upload_files_dir,
																				'status'	=> $upload_files_dir_writable,
																			),
														'temp'			=> array(
																				'label'		=> 'Temp directory',
																				'file'		=> $upload_temp_dir,
																				'status'	=> $upload_temp_dir_writable,
																			),
													);
								foreach ( $write_checks as $check => $values ) {
							?>
									<div class="form-group">
										<label class="col-sm-4 control-label"><?php _e($values['label'], 'cftp_admin'); ?></label>
										<div class="col-sm-6">
											<?php echo $values['file']; ?>
										</div>
										<div class="col-sm-2">
											<?php if ( $values['status'] ) : ?>
												<span class="label label-success"><?php _e('Writable','cftp_admin'); ?></span>
											<?php else : ?>
												<span class="label label-important"><?php _e('Not writable','cftp_admin'); ?></span>
											<?php endif; ?>
										</div>
									</div>
							<?php
								}
							?>

							<div class="options_divide"></div>
			
							<h3><?php _e('System information','cftp_admin'); ?></h3>

							<?php
								$system_info = array(
													'os'			=> array(
																			'label'		=> 'Server OS',
																			'name'		=> 'server_os',
																			'value'		=> php_uname(),
																		),
													'user'			=> array(
																			'label'		=> 'Server user',
																			'name'		=> 'server_user',
																			'value'		=> $server_user,
																		),
													'version'		=> array(
																			'label'		=> 'PHP version',
																			'name'		=> 'phpversion',
																			'value'		=> phpversion(),
																		),
													'sapi'			=> array(
																			'label'		=> 'PHP SAPI',
																			'name'		=> 'sapi_name',
																			'value'		=> php_sapi_name(),
																		),
													'memory'		=> array(
																			'label'		=> 'Memory limit',
																			'name'		=> 'memory_limit',
																			'value'		=> ini_get('memory_limit'),
																		),
													'post'			=> array(
																			'label'		=> 'POST max size',
																			'name'		=> 'post_max_size',
																			'value'		=> ini_get('post_max_size'),
																		),
													'upload'		=> array(
																			'label'		=> 'Upload max filesize',
																			'name'		=> 'upload_max_filesize',
																			'value'		=> ini_get('upload_max_filesize'),
																		),
												);
								foreach ( $system_info as $data => $values ) {
							?>
									<div class="form-group">
										<label for="<?php echo $values['name']; ?>" class="col-sm-4 control-label"><?php _e($values['label'],'cftp_admin'); ?></label>
										<div class="col-sm-6">
											<input type="text" name="<?php echo $values['name']; ?>" id="<?php echo $values['name']; ?>" class="form-control" disabled value="<?php echo $values['value']; ?>" />
										</div>
									</div>
							<?php
								}
							?>
								
							<div class="inside_form_buttons">
								<?php if ($ready_to_go) : ?>
									<button type="submit" name="submit" class="btn btn-wide btn-secondary"><?php _e('Check again','cftp_admin'); ?></button>
									<button type="submit" name="submit-start" class="btn btn-wide btn-primary"><?php _e('Write config file','cftp_admin'); ?></button>
								<?php else : ?>
									<button type="submit" name="submit" class="btn btn-wide btn-primary"><?php _e('Check','cftp_admin'); ?></button>
								<?php endif; ?>
							</div>
			
						</form>
	
					</div>
				</div>
			</div>
		</div>

	</div> <!--main-->

	<?php default_footer_info(); ?>

</body>
</html>
<?php $endScriptTime=microtime(TRUE);
$totalScriptTime=$endScriptTime-$startScriptTime;
echo "\n\r".'<!-- Load time: '.number_format($totalScriptTime, 4).' seconds -->';exit; ?>