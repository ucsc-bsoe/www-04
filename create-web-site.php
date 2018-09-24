#!/usr/local/bin/php
<?php

################################################################################
# make sure we're running as root                                              #
################################################################################

if (posix_getuid() != 0) {
  echo "ERROR: This script must be run as root.\n";

  exit(1);
}

################################################################################
# build and array of valid site types                                          #
################################################################################

$site_types = array();

$conf_templates = glob("/usr/local/etc/apache24/templates/*-conf");

foreach($conf_templates as $conf_template) {
  preg_match(
    "|/([^/]*)-conf|",
    $conf_template,
    $matches
  );

  array_push(
    $site_types,
    $matches[1]
  );
}

################################################################################
# check to make sure we have the proper number of parameters                   #
################################################################################

if (count($argv) < 5) {
  echo
    "USAGE: " .
    $argv[0] .
    " <host name> <owner ucsc.edu e-mail> <username> <" .
    join("|", $site_types) .
    "> <site-type-specific options>\n";

  exit(1);
}

################################################################################
# store all our parameters into local variables for easy reference             #
################################################################################

$host_name = trim(strtolower($argv[1]));
$owner_e_mail = trim(strtolower($argv[2]));
$owner_username = trim(strtolower($argv[3]));
$site_type = trim(strtolower($argv[4]));

$site_folder_name = "/www/" . $host_name . "/";

list($owner_login, $owner_domain) = preg_split(
  "/@/",
  $owner_e_mail,
  2
);

$variables = array(
  "SERVER_NAME" => $host_name,
  "SERVER_ADMIN" => $owner_e_mail,
  "OWNER_USERNAME" => $owner_username,
);

################################################################################
# check to make sure the host name is sensible                                 #
################################################################################

if (gethostbyname($host_name . ".") == $host_name . ".") {
  echo
    "ERROR: The host name " .
    $host_name .
    " does not resolve.\n";

  exit(1);
} else if (gethostbyname($host_name) != gethostbyname(php_uname("n"))) {
  echo
    "WARNING: The host name " .
    $host_name .
    " does not resolve to the correct IP.  Press CTRL-C to stop, or enter to continue.\n";

  readline();
}

################################################################################
# check to make sure that the owner e-mail address appears to be valid         #
################################################################################

list($owner_mailbox, $owner_domain) = preg_split(
  "/@/",
  $owner_e_mail,
  2
);

if (($owner_mailbox == "") or ($owner_domain != "ucsc.edu")) {
  echo
    "ERROR: The e-mail address you specified does not appear to be a valid @ucsc.edu address.\n";

  exit(1);
}

################################################################################
# check to make sure that the site_type is valid                               #
################################################################################

$template_file_name =
  "/usr/local/etc/apache24/templates/" .
  $site_type .
  "-conf";

if (!file_exists($template_file_name)) {
  echo
    "ERROR: Could not find template file " .
    $template_file_name .
    "\n";

  exit(1);
}

################################################################################
# check to see if user already exists                                          #
################################################################################

  # passthru(
  #  "id" .  $owner_username,
  #  $exit_code
  #);
  echo "Checking if username " . $owner_username . " exists.\n";
  passthru( "id " . $owner_username,
    $exit_code
  );

  if ($exit_code != 1) {
    echo
      "ERROR: username " .
      $owner_username .
      " is already being used, please choose a unique username.\n";

    exit(1);
  } else { 
    echo
     "That username isn't being used, continuing to create site.\n";
  }


################################################################################
# check to see if this site has already been configured                        #
################################################################################

if (file_exists($site_folder_name)) {
  echo
    "Site folder " .
    $site_folder_name .
    " already exists.\n";

  exit(1);
}

$site_config_file_name =
  "/usr/local/etc/apache24/sites/" . $host_name . ".conf";

if (file_exists($site_config_file_name)) {
  echo
    "ERROR: Site configuration file " .
    $site_config_file_name .
    " already exists.\n";
}

################################################################################
# create each one of the folders that the site needs to operate                #
################################################################################

$folders = array(
  "",
  "htdocs",
  "tmp",
  "sessions",
  "private",
  "cgi-bin",
);

foreach($folders as $folder) {
  $this_folder_name =
    $site_folder_name .
    $folder;
   
  if (!mkdir($this_folder_name,0700)) {
    echo
      "ERROR: Could not create folder " .
      $site_folder_name .
      $folder .
      "\n";

    exit(1);
  }
  
}

################################################################################
# copy user files                                                              #
################################################################################

$user_files = array(
  ".login",
  ".cshrc",
  ".mail_aliases",
  ".mailrc",
  ".profile",
  ".shrc",
);

foreach($user_files as $user_file) {
  passthru(
    "/bin/cp /usr/local/etc/apache24/templates/user/" . $user_file . " " . escapeshellarg($site_folder_name . "/" . $user_file),
    $exit_code
  );

  if ($exit_code != 0) {
    echo
      "ERROR: Got exit code " .
      $exit_code .
      " while copying: " .
      $user_file .
      "\n";

    exit(1);
  }
}

################################################################################
# create the site configuration file, and update its values                    #
################################################################################

$configuration = file_get_contents($template_file_name);

foreach($variables as $key => $value) {
  $configuration = preg_replace(
    "/" . $key . "/",
    $value,
    $configuration
  );
}

file_put_contents(
  $site_config_file_name,
  $configuration
);

################################################################################
# Add owner and group, change ownership on the whole site                      #
# folder to owner_username                                                     #
################################################################################

# Create the group first
passthru(
  "pw groupadd " . $owner_username,
  $exit_code
);

if ($exit_code != 0) {
  echo
    "ERROR: There was an error while creating the group.\n";

  exit(1);
}

# Create the user (we have already checked to make sure this user doesn't exist).
exec(
"pw useradd -n " . $owner_username . " -g " . $owner_username . " -d " .$site_folder_name .  " -w random",
  $output_user
);

# Echo the password
echo "Password for " . $owner_username . ": " . $output_user[0] . "\n";

# Give permission to this user to all files.
passthru(
  "/usr/sbin/chown -R " . $owner_username . ":" . $owner_username . " "  . escapeshellarg($site_folder_name),
  $exit_code
);

if ($exit_code != 0) {
  echo
    "ERROR: There was an error while changing the ownership of the site folder.\n";

  exit(1);
}

################################################################################
# restart apache so that it picks up the new configuration file                #
################################################################################

passthru(
  "/usr/local/sbin/apachectl graceful",
  $exit_code
);

if ($exit_code != 0) {
  echo
    "ERROR: Got exit code " .
    $exit_code .
    " while restarting the Apache server.\n";

  exit(1);
}

################################################################################
# if this site type has a script file, run it now                              #
################################################################################

$script_file_name =
  "/usr/local/etc/apache24/templates/" .
  $site_type .
  ".php";

if (file_exists($script_file_name)) {
  require_once($script_file_name);
}

################################################################################
# if this web site type has an e-mail template, send e-mail to the site owner  #
################################################################################

$e_mail_template =
  "/usr/local/etc/apache24/templates/" .
  $site_type .
  "-mail";

$variables['ADMIN_PASSWORD'] = $output_user[0];

if (file_exists($e_mail_template)) {
  $message = file_get_contents($e_mail_template);

  foreach($variables as $key => $value) {
    $message = preg_replace(
      "/" . $key . "/",
      $value,
      $message
    );
  }

  mail(
    $owner_e_mail,
    "Your New Web Site: " . $host_name,
    $message,
    "From: webmaster@soe.ucsc.edu\nBCC: web-notifications@soe.ucsc.edu\n",
    "-fwebmaster@soe.ucsc.edu"
  );
}

?>
