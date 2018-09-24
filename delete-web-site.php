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
# check to make sure we have the proper number of parameters                   #
################################################################################

if (count($argv) != 3) {
  echo
    "USAGE: " .
    $argv[0] .
    " <host name> <owner username>\n";

  exit(1);
}

$host_name = trim(strtolower($argv[1]));
$owner_name = trim(strtolower($argv[2]));

################################################################################
# make sure that the web site host name provided exists                        #
################################################################################

$folder_name =
  "/www/" .
  $host_name;

if (!file_exists($folder_name)) {
  echo "ERROR: The web site you specified does not appear to exist.\n";

  exit(1);
}

################################################################################
# delete the owner of this site                                                #
################################################################################

exec('pw deluser ' .  $owner_name. " ",$user_exists);

################################################################################
# remove the web site configuration file                                       #
################################################################################

$config_file =
  "/usr/local/etc/apache24/sites/" .
  $host_name .
  ".conf";

if (file_exists($config_file)) {
  unlink($config_file);
}

################################################################################
# re-start Apache                                                              #
################################################################################

system(
  "/usr/local/etc/rc.d/apache24 graceful",
  $exit_code
);

if ($exit_code != 0) {
  echo "ERROR: Could not re-start apache.\n";

  exit(1);
}

################################################################################
# remove web site home directory                                               #
################################################################################

system(
  "/bin/rm -Rfv " . escapeshellarg("/www/" . $host_name),
  $exit_code
);

if ($exit_code != 0) {
  echo "ERROR: Could not remove web site home directory.\n";

  exit(1);
}

?>
