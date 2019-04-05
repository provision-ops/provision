<Directory <?php print $this->publish_path; ?>>
    Order allow,deny
    Allow from all
    Satisfy All
    Require all granted

<?php print $extra_config; ?>


<?php
  if (is_readable("{$this->publish_path}/.htaccess")) {
    print "\n# Include the platform's htaccess file\n";
    print "Include {$this->publish_path}/.htaccess\n";
  }
?>

  # Do not read any .htaccess in the platform
  AllowOverride none

</Directory>

