<?php defined('BASEPATH') OR exit('No direct script access allowed');
/*
|--------------------------------------------------------------------------
| Enable/Disable Migrations
|--------------------------------------------------------------------------
|
| Migrations are disabled by default for security reasons.
| You should enable migrations whenever you intend to do a schema migration
| and disable it back when you're done.
|
*/
$config['migration_enabled'] = TRUE;

if (!file_exists(APPPATH.'migrations')) {
    $path = APPPATH . '../system/pancake/migrations';
} else {
    $path = APPPATH . 'migrations';
}

/*
|--------------------------------------------------------------------------
| Migrations version
|--------------------------------------------------------------------------
|
| This is used to set migration version that the file system should be on.
| If you run $this->migration->latest() this is the version that schema will
| be upgraded / downgraded to.
|
*/

$config['migration_version'] = scandir($path);
$config['migration_version'] = explode('_', end($config['migration_version']), 2);
$config['migration_version'] = reset($config['migration_version']);
$config['migration_version'] = ((int) $config['migration_version']);


/*
|--------------------------------------------------------------------------
| Migrations Path
|--------------------------------------------------------------------------
|
| Path to your migrations folder.
| Typically, it will be within your application path.
| Also, writing permission is required within the migrations path.
|
*/
$config['migration_path'] = $path;


/* End of file migration.php */
/* Location: ./application/config/migration.php */
