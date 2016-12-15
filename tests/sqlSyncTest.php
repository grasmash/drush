<?php

/**
* @file
*  For now we only test sql-sync in simulated mode.
*
*  Future: Using two copies of Drupal, we could test
*  overwriting one site with another.
*/

namespace Unish;

/**
 *  @group slow
 *  @group commands
 *  @group sql
 */
class sqlSyncTest extends CommandUnishTestCase {

  /**
   * Covers the following responsibilities.
   *   - A user created on the source site is copied to the destination site.
   *   - The email address of the copied user is sanitized on the destination site.
   *
   * General handling of site aliases will be in sitealiasTest.php.
   */
  public function testLocalSqlSync() {
    if ($this->db_driver() == 'sqlite') {
      $this->markTestSkipped('SQL Sync does not apply to SQLite.');
      return;
    }

    $sites = $this->setUpDrupal(2, TRUE);
    return $this->localSqlSync();
  }

  public function localSqlSync() {

    $options = array(
      'root' => $this->webroot(),
      'uri' => 'stage',
      'yes' => NULL,
    );

    // Create a user in the staging site
    $name = 'joe.user';
    $mail = "joe.user@myhome.com";

    // Add user fields and a test User.
    $this->drush('pm-enable', array('field,text,telephone,comment'), $options + array('yes' => NULL));
    $this->drush('php-script', array('user_fields-D' . UNISH_DRUPAL_MAJOR_VERSION, $name, $mail), $options + array('script-path' => __DIR__ . '/resources', 'debug' => NULL));

    // Copy stage to dev with --sanitize.
    $sync_options = array(
      'sanitize' => NULL,
      'yes' => NULL,
      // Test wildcards expansion from within sql-sync. Also avoid D8 persistent entity cache.
      'structure-tables-list' => 'cache,cache*',
    );
    $this->drush('sql-sync', array('@stage', '@dev'), $sync_options);

    // Confirm that the sample user has the correct email address on the staging site
    $this->drush('user-information', array($name), $options + array('format' => 'csv', 'include-field-labels' => 0, 'strict' => 0));
    $output = $this->getOutput();
    $row  = str_getcsv($output);
    $uid = $row[0];
    $this->assertEquals($mail, $row[2], 'email address is unchanged on source site.');
    $this->assertEquals($name, $row[1]);

    $options = array(
      'root' => $this->webroot(),
      'uri' => 'dev',
      'yes' => NULL,
    );
    // Confirm that the sample user's email address has been sanitized on the dev site
    $this->drush('user-information', array($name), $options + array('format' => 'csv', 'include-field-labels' => 0, 'strict' => 0));
    $output = $this->getOutput();
    $row  = str_getcsv($output);
    $uid = $row[0];
    $this->assertEquals("user+$uid@localhost.localdomain", $row[2], 'email address was sanitized on destination site.');
    $this->assertEquals($name, $row[1]);

    // @todo Confirm that the role_permissions table no longer exists in dev site (i.e. wildcard expansion works in sql-sync).
    // $this->drush('sql-query', array('SELECT * FROM role_permission'), $options, NULL, NULL, self::EXIT_ERROR);

    // Copy stage to dev with --sanitize and a fixed sanitized email
    $sync_options = array(
      'sanitize' => NULL,
      'yes' => NULL,
      'sanitize-email' => 'user@mysite.org',
      // Test wildcards expansion from within sql-sync. Also avoid D8 persistent entity cache.
      'structure-tables-list' => 'cache,cache*',
    );
    $this->drush('sql-sync', array('@stage', '@dev'), $sync_options);

    $options = array(
      'root' => $this->webroot(),
      'uri' => 'dev',
      'yes' => NULL,
    );
    // Confirm that the sample user's email address has been sanitized on the dev site
    $this->drush('user-information', array($name), $options + array('format' => 'csv', 'include-field-labels' => 0, 'strict' => 0));
    $output = $this->getOutput();
    $row  = str_getcsv($output);
    $uid = $row[0];
    $this->assertEquals("user@mysite.org", $row[2], 'email address was sanitized (fixed email) on destination site.');
    $this->assertEquals($name, $row[1]);

    // @assert that other user fields have been sanitized.
  }
}
