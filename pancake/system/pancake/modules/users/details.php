<?php defined('BASEPATH') or exit('No direct script access allowed');

class Module_Users extends Module {

	public $version = '1.0';
	
	public $author 		=	'Pancake Payments';
	public $author_url	=	'http://pancakeapp.com';

	public function info()
	{
		return array(
			'name' => array(
				'english' => 'Users',
			),
			'description' => array(
				'english' => 'Users can be placed into groups to manage permissions.',
			),
			'frontend' => TRUE,
			'backend'  => TRUE,
			'menu'	  => 'users',
			
			'roles' => array(
				'create', 'view', 'edit', 'change_status',
			),
			
			'sections' => array(
				'users' => array(
				    'name' => 'global:overview',
				    'uri' => 'admin/users',
				    'shortcuts' => array(
						array(
						    'name' => 'users:create_user',
						    'uri' => 'admin/users/create',
						    'class' => 'add blue-btn',
						),
						array(
							'name' => 'groups:manage',
						 	'uri' => 'admin/users/groups/add',
						 	'class' => 'add blue-btn',
						),
				    ),
			    ),
			    'groups' => array(
				    'name' => 'groups:groups',
				    'uri' => 'admin/users/groups',
				    'shortcuts' => array(
						array(
							'name' => 'groups:create',
						 	'uri' => 'admin/users/groups/add',
						 	'class' => 'add blue-btn',
						),
					),
				),
		    ),
		);
	}
}
/* End of file details.php */